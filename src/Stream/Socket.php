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

class Socket implements DuplexStream
{
    protected const CONNECT_FLAGS = STREAM_CLIENT_CONNECT | STREAM_CLIENT_ASYNC_CONNECT;
    
    protected $reader;
    
    protected $writer;

    public function __construct($socket, Poll $watcher, int $bufferSize = 0x8000)
    {
        $this->reader = new Reader($socket, $watcher, $bufferSize);
        $this->writer = new Writer($socket, $watcher);
    }
    
    public function __destruct()
    {
        $this->close();
    }
    
    public static function pair(): array
    {
        $domain = (DIRECTORY_SEPARATOR == '\\') ? \STREAM_PF_INET : \STREAM_PF_UNIX;
        
        return \array_map(function ($socket) {
            return static::enableNonBlockingMode($socket);
        }, \stream_socket_pair($domain, \STREAM_SOCK_STREAM, \STREAM_IPPROTO_IP));
    }

    public static function streamPair(): array
    {
        return \array_map(function ($a) {
            return new Socket($a, new Poll($a));
        }, static::pair());
    }
    
    /**
     * Stablish an unencrypted socket connection to the given URL (supports tcp, tls, udp and unix protocols).
     */
    public static function connect(string $url, array $options = []): Socket
    {
        static $defaults = [
            'ssl' => [
                'allow_self_signed' => false,
                'SNI_enabled' => true,
                'crypto_method' => \STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT,
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
        
        switch ($protocol) {
            case 'tcp':
            case 'udp':
            case 'tls':
                $parts = \explode(':', $host);
                $port = \array_pop($parts);
                $host = \trim(\implode(':', $parts), '][');
                
                if (null === ($options['ssl']['peer_name'] ?? null)) {
                    $options['ssl']['peer_name'] = $host;
                }
                
                if (false === \filter_var($host, \FILTER_VALIDATE_IP)) {
                    $host = \gethostbyname($host);
                }
                
                if ($protocol == 'tls') {
                    $encrypt = true;
                    $protocol = 'tcp';
                }
                
                $url = $protocol . '://' . $host . ':' . $port;
                
                break;
        }
        
        // Merge options, ensure TLS compression is disabled to protect from CRIME attacks.
        $ctx = \stream_context_create(\array_replace_recursive($defaults, $options, [
            'ssl' => [
                'disable_compression' => true
            ]
        ]));
        
        $errno = null;
        $errstr = null;
        
        $socket = @\stream_socket_client($url, $errno, $errstr, 0, self::CONNECT_FLAGS, $ctx);
        
        if ($socket === false) {
            throw new \RuntimeException(\sprintf('Failed connecting to "%s": [%s] %s', $url, $errno, $errstr));
        }
        
        $socket = static::enableNonBlockingMode($socket);
        
        $watcher = new Poll($socket);
        $watcher->awaitWritable();
        
        if (false === @\stream_socket_get_name($socket, true)) {
            \fclose($socket);
            
            throw new \RuntimeException(\sprintf('Connection to %s refused', $url));
        }
        
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
        
        return new Socket($socket, $watcher);
    }

    public static function enableNonBlockingMode($socket)
    {
        if (!\stream_set_blocking($socket, false)) {
            throw new \RuntimeException('Cannot switch resource to non-blocking mode');
        }
        
        \stream_set_read_buffer($socket, 0);
        \stream_set_write_buffer($socket, 0);
        
        return $socket;
    }
    
    /**
     * {@inheritdoc}
     */
    public function close(?\Throwable $e = null): void
    {
        $this->reader->close($e);
        $this->writer->close($e);
    }
    
    /**
     * {@inheritdoc}
     */
    public function read(?int $length = null): ?string
    {
        return $this->reader->read($length);
    }
    
    /**
     * {@inheritdoc}
     */
    public function getReadableStream(): ReadableStream
    {
        return $this->reader;
    }

    /**
     * {@inheritdoc}
     */
    public function write(string $data): void
    {
        $this->writer->write($data);
    }
    
    /**
     * {@inheritdoc}
     */
    public function getWritableStream(): WritableStream
    {
        return $this->writer;
    }
}
