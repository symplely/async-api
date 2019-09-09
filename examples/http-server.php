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

use Concurrent\Signal;
use Concurrent\Stream\Server;
use Concurrent\Stream\Socket;

error_reporting(-1);
ini_set('display_errors', '1');

require_once \dirname(__DIR__) . '/vendor/autoload.php';

$server = Server::listen('tcp://127.0.0.1:8080', function (Socket $socket) {
    $buffer = '';
    
    while (false === \strpos($buffer, "\r\n\r\n")) {
        $buffer .= $socket->read();
    }
    
    $parts = \explode("\r\n\r\n", $buffer, 2);
    $head = \explode("\r\n", $parts[0]);
    $line = \trim(\array_shift($head));
    $body = $parts[1] ?? '';
    
    $m = null;
    
    if (!\preg_match("'^(\S+)\s+(\S+)\s+HTTP/(1\\.[01])$'", $line, $m)) {
        throw new \RuntimeException('Invalid HTTP request line received');
    }
    
//     $method = $m[1];
//     $target = $m[2];
    $version = $m[3];
    
    var_dump($line);
    
    $headers = [];
    
    foreach ($head as $line) {
        list ($k, $v) = \array_map('trim', \explode(':', $line, 2));
        
        $headers[\strtolower($k)][] = $v;
    }
    
    $body = 'Hello client! :)';
    $len = \strlen($body);
    
    $socket->write("HTTP/$version 200 OK\r\nConnection: close\r\nContent-Type: text/plain\r\nContent-Length: $len\r\n\r\n$body");
});

$signal = new Signal(Signal::SIGINT);

echo "Server PID: ", \getmypid(), "\n";

try {
    $signal->awaitSignal();
} finally {
    echo "\n=> SHUTDOWN REQUESTED\n";
    
    $server->close();
}
