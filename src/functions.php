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

namespace Concurrent
{
    /**
     * Resolve when all input awaitables have been resolved.
     * 
     * Requires at least one input awaitable, resolves with an array containing all resolved values. The order and
     * keys of the input array are preserved in the result array.
     * 
     * The combinator will fail if any input awaitable fails.
     */
    function all(array $awaitables): Awaitable
    {
        $result = \array_fill_keys(\array_keys($awaitables), null);

        $all = function (Deferred $defer, bool $last, $k, ?\Throwable $e, $v = null) use (& $result) {
            if ($e) {
                $defer->fail($e);
            } else {
                $result[$k] = $v;

                if ($last) {
                    $defer->resolve($result);
                }
            }
        };

        return Deferred::combine($awaitables, $all);
    }

    /**
     * Resolves with the value or error of the first input awaitable that resolves.
     */
    function race(array $awaitables): Awaitable
    {
        $race = function (Deferred $defer, bool $last, $k, ?\Throwable $e, $v = null) {
            if ($e) {
                $defer->fail($e);
            } else {
                $defer->resolve($v);
            }
        };

        return Deferred::combine($awaitables, $race);
    }
    
    /**
     * Merges all messages received from multiple input channels into single sequence (generator).
     * 
     * You can supply channels or channel iterators as inputs.
     */
    function merge(...$channels): \Generator
    {
        if ($channels) {
            $group = new ChannelGroup($channels);
            
            do {
                if (null !== ($entry = $group->select())) {
                    yield $entry->value;
                }
            } while ($group->count());
        }
    }
}

namespace Concurrent\Stream
{
    /**
     * Reads a fixed length chunk of data from a stream.
     * 
     * @param ReadableStream $stream The stream to be read from.
     * @param int $len The number of bytes to read.
     * @param bool $enforceLength Throw an exception if less than the given number of bytes were read.
     * @return string Read bytes (might be less than the given number if length is not enforced or NULL in case of EOF).
     * 
     * @throws StreamException When length is enforced and the stream does not provide enough data.
     */
    function read(ReadableStream $stream, int $len, bool $enforceLength = true): ?string
    {
        $buffer = '';
        $i = 0;

        while ($i < $len && null !== ($chunk = $stream->read($len - $i))) {
            $buffer .= $chunk;
            $i += \strlen($chunk);
        }

        if ($enforceLength && $i < $len) {
            throw new StreamException("Failed to read {$len} bytes from stream");
        }

        return ($buffer === '') ? null : $buffer;
    }
    
    /**
     * Read contents of the given stream into a string.
     * 
     * @param ReadableStream $stream The stream to be read from.
     * @param bool $close Close the stream after all data has been read?
     */
    function buffer(ReadableStream $stream, bool $close = true): string
    {
        $buffer = '';

        try {
            while (null !== ($chunk = $stream->read())) {
                $buffer .= $chunk;
            }
        } finally {
            if ($close) {
                $stream->close();
            }
        }

        return $buffer;
    }

    /**
     * Writa all data from a readable stream into a writable stream.
     * 
     * @param ReadableStream $a Stream to be read from.
     * @param WritableStream $b Stream to be written to.
     * @param bool $close Close the readable stream after all data has been read?
     * @return int Number of bytes that have been written.
     */
    function pipe(ReadableStream $a, WritableStream $b, bool $close = true): int
    {
        $len = 0;

        try {
            while (null !== ($chunk = $a->read())) {
                $b->write($chunk);

                $len += \strlen($chunk);
            }
        } finally {
            if ($close) {
                $a->close();
            }
        }

        return $len;
    }
}
