<?php

/*
 +----------------------------------------------------------------------+
 | PHP Version 7                                                        |
 +----------------------------------------------------------------------+
 | Copyright (c) 1997-2018 The PHP Group                                |
 +----------------------------------------------------------------------+
 | This source file is subject to version 3.01 of the PHP license,      |
 | that is bundled with this package in the file LICENSE, and is        |
 | available through the world-wide-web at the following url:           |
 | http://www.php.net/license/3_01.txt                                  |
 | If you did not receive a copy of the PHP license and are unable to   |
 | obtain it through the world-wide-web, please send a note to          |
 | license@php.net so we can mail you a copy immediately.               |
 +----------------------------------------------------------------------+
 | Authors: Martin Schröder <m.schroeder2007@gmail.com>                 |
 +----------------------------------------------------------------------+
 */

namespace Concurrent\Stream;

use Concurrent\Poll;
use Concurrent\Task;

class Server
{
    protected $server;
    
    protected $watcher;

    protected function __construct($server, callable $handler, bool $encrypt, array $options)
    {
        $this->server = $server;
        $this->watcher = new Poll($server);
        
        Task::async(function () use ($handler, $encrypt, $options) {
            while (true) {
                $this->watcher->awaitReadable();
                
                $socket = @\stream_socket_accept($this->server, 0);
                
                if ($socket === false) {
                    continue;
                }
                
                Task::async(static function () use ($socket, $encrypt, $handler) {
                    try {
                        $socket = Socket::enableNonBlockingMode($socket);
                        $watcher = new Poll($socket);
                        
                        while ($encrypt) {
                            $result = @\stream_socket_enable_crypto($socket, true);
                            
                            if ($result === true) {
                                break;
                            }
                            
                            if ($result === false) {
                                throw new \RuntimeException(\sprintf('Failed to enable socket encryption: %s', \error_get_last()['message'] ?? ''));
                            }
                            
                            $watcher->awaitReadable();
                        }
                        
                        $socket = new Socket($socket, $watcher);
                    } catch (\Throwable $e) {
                        return @\fclose($socket);
                    }
                    
                    $e = null;
                    
                    try {
                        $handler($socket);
                    } catch (\Throwable $e) {
                        // Forwarded to close...
                    } finally {
                        $socket->close($e);
                    }
                });
            }
        });
    }
    
    public static function listen(string $url, callable $handler, array $options = []): Server
    {
        static $defaults = [
            'ssl' => [
                'crypto_method' => \STREAM_CRYPTO_METHOD_TLSv1_2_SERVER,
                'reneg_limit' => 0,
                'verify_peer' => true,
                'verify_peer_name' => true,
                'verify_depth' => 10,
                'ciphers' => \OPENSSL_DEFAULT_STREAM_CIPHERS,
                'capture_peer_cert' => false,
                'capture_peer_cert_chain' => false
            ]
        ];
        
        $m = null;
        
        if (!\preg_match("'^([^:]+)://(.+)$'", $url, $m)) {
            throw new \InvalidArgumentException(\sprintf('Invalid socket URL: "%s"', $url));
        }
        
        $protocol = \strtolower($m[1]);
        $encrypt = false;
        $host = $m[2];
        
        $flags = \STREAM_SERVER_BIND | \STREAM_SERVER_LISTEN;
        
        switch ($protocol) {
            case 'tls':
                $encrypt = true;
                $url = 'tcp://' . $host;
                break;
            case 'udp':
                $flags = \STREAM_SERVER_BIND;
                break;
        }
        
        // Merge options, ensure TLS compression is disabled to protect from CRIME attacks.
        $ctx = \stream_context_create($options = \array_replace_recursive($defaults, $options, [
            'ssl' => [
                'disable_compression' => true
            ]
        ]));
        
        $errno = null;
        $errstr = null;
        
        $server = @\stream_socket_server($url, $errno, $errstr, $flags, $ctx);
        
        if ($server === false) {
            throw new \RuntimeException(\sprintf('Failed to bind server "%s": [%s] %s', $url, $errno, $errstr));
        }
        
        @\stream_set_blocking($server, false);
        
        return new static($server, $handler, $encrypt, $options);
    }
    
    public function close(?\Throwable $e = null): void
    {
        if (\is_resource($this->server)) {
            @\fclose($this->server);
            
            $this->watcher->close($e);
        }
    }
}
