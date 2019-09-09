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
use Concurrent\Channel;
use Concurrent\Task;
use Concurrent\TaskPool;

class TaskPoolTest extends AsyncTestCase
{
    public function testSingleExecute()
    {
        $pool = new TaskPool();

        $this->assertEquals(123, Task::await($pool->execute(function (int $v): int {
            return $v + 23;
        }, 100)));
    }

    public function testSingleExecuteWithError()
    {
        $pool = new TaskPool();
        $job = $pool->execute(function (int $x) {}, 'foo');

        $this->expectException(\TypeError::class);

        Task::await($job);
    }

    public function testSubmit()
    {
        $pool = new TaskPool(1, 1);
        $channel = new Channel(1);

        $pool->submit(function (Channel $channel) {
            $channel->send(1);
            $channel->send(2);
        }, $channel);

        $pool->submit(function (Channel $channel) {
            try {
                $channel->send(3);
                $channel->send(4);
            } finally {
                $channel->close();
            }
        }, $channel);

        $channel->send(0);

        $this->assertEquals(range(0, 4), iterator_to_array($channel->getIterator(), false));
    }
}
