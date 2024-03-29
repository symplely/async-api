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

class TaskPool
{
    private $channel;

    private $group;

    private $concurrency;

    private $count = 0;

    private $context;

    private $job;

    public function __construct(int $concurrency = 1, int $backlog = 0)
    {
        $this->concurrency = \max(1, $concurrency);

        $this->group = new ChannelGroup([
            $this->channel = new Channel($backlog)
        ]);

        $this->context = Context::background();

        $this->job = static function (iterable $it) {
            foreach ($it as list ($defer, $context, $work, $args)) {
                if ($defer !== null) {
                    try {
                        $defer->resolve($context->run($work, ...$args));
                    } catch (\Throwable $e) {
                        $defer->fail($e);
                    }
                } else {
                    try {
                        $context->run($work, ...$args);
                    } catch (\Throwable $e) {
                        \fwrite(\STDERR, "$e\n\n");
                    }
                }
            }
        };
    }

    public function close(?\Throwable $e = null): void
    {
        $this->count = \PHP_INT_MAX;
        $this->channel->close($e);
    }

    public function submit(callable $work, ...$args): void
    {
        $this->enqueueJob($work, $args);
    }

    public function execute(callable $work, ...$args): Awaitable
    {
        $this->enqueueJob($work, $args, $defer = new Deferred());

        return $defer->awaitable();
    }

    protected function enqueueJob(callable $work, array $args, ?Deferred $defer = null): void
    {
        $job = [
            $defer,
            Context::current(),
            $work,
            $args
        ];

        if ($this->count < $this->concurrency) {
            if (null !== $this->group->send($job, 0)) {
                return;
            }

            $this->count++;

            Task::asyncWithContext($this->context, $this->job, $this->channel->getIterator());
        }

        $this->channel->send($job);
    }
}
