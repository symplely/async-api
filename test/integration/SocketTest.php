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

use Concurrent\AsyncTestCase;
use Concurrent\Task;
use Concurrent\Network\TcpSocket;
use Concurrent\Network\TlsClientEncryption;

class SocketTest extends AsyncTestCase
{
    public function providePayloads()
    {
        yield [100, 3];
        yield [4096, 2];
        yield [8192, 10];
        yield [7000, 100];
        yield [9000, 100];
        yield [70000, 5];
    }

    /**
     * @dataProvider providePayloads
     */
    public function testPair(int $len, int $count)
    {
        list ($a, $b) = TcpSocket::pair();

        Task::async(function (TcpSocket $socket) use ($len, $count) {
            try {
                $chunk = str_repeat('A', $len);

                for ($i = 0; $i < $count; $i++) {
                    $socket->write($chunk);
                }
            } finally {
                $socket->close();
            }
        }, $a);

        $this->assertEquals(strlen(buffer($b)), $len * $count);
    }
    
    public function provideTargets()
    {
        yield [
            'tcp://httpbin.org:80',
            false
        ];
        
        yield [
            'tcp://httpbin.org:80',
            true
        ];
        
        yield [
            'tls://httpbin.org:443',
            false
        ];
        
        yield [
            'tls://httpbin.org:443',
            true
        ];
    }

    /**
     * @dataProvider provideTargets
     */
    public function testSocket(string $url, bool $native)
    {
        if ($native) {
            list ($protocol, $host, $port) = \array_map(function (string $p) {
                return \ltrim($p, '/');
            }, \explode(':', $url, 3));

            if ($protocol == 'tcp') {
                $encryption = null;
            } else {
                $encryption = new TlsClientEncryption();
            }

            $socket = TcpSocket::connect($host, $port, $encryption);

            if ($encryption) {
                $socket->encrypt();
            }
        } else {
            $socket = Socket::connect($url);
        }

        try {
            $socket->write(implode("\r\n", [
                'GET /json HTTP/1.0',
                'Host: httpbin.org',
                'Connection: close'
            ]) . "\r\n\r\n");
            
            list ($headers, $data) = \explode("\r\n\r\n", buffer($socket, false));
            $headers = \explode("\r\n", $headers);
            $line = \array_shift($headers);
            $m = null;

            $this->assertEquals(1, \preg_match("'^HTTP/(?<version>1\\.[01])\s+(?<status>[0-9]{3})(.*)$'i", $line, $m));
            $this->assertEquals(200, $m['status']);
            
            $data = \json_decode($data, true);
            
            $this->assertEquals('Sample Slide Show', $data['slideshow']['title']);
        } finally {
            $socket->close();
        }
    }
}
