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

namespace Concurrent;

use Concurrent\Stream\Socket;

\error_reporting(-1);
\ini_set('display_errors', '0');

require_once '../vendor/autoload.php';

list ($a, $b) = Socket::streamPair();

Task::async(function () use ($a) {
    $chunk = \str_repeat('A', 6000);

    try {
        for ($i = 0; $i < 100000; $i++) {
            $a->write($chunk);
        }
    } finally {
        $a->close();
    }
});

$len = 0;
$time = \microtime(true);

try {
    while (null !== ($chunk = $b->read())) {
        $len += \strlen($chunk);
    }
} finally {
    $b->close();
}

\var_dump($len, \microtime(true) - $time);
