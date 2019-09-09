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

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\TestResult;

/**
 * Base class for tests that make use of async tasks.
 */
abstract class AsyncTestCase extends TestCase
{
    /**
     * Run the test method in an isolated task scheduler.
     */
    public function run(TestResult $result = null): TestResult
    {
        return TaskScheduler::run(\Closure::bind(function () use ($result) {
            return parent::run($result);
        }, $this), \Closure::fromCallable([
            $this,
            'debugPendingAsyncTasks'
        ]));
    }

    /**
     * Can be used to dump debug info about unfinished tasks created during a test.
     * 
     * @param array $tasks Each element contains info about an unfinished task.
     */
    protected function debugPendingAsyncTasks(array $tasks) { }
}
