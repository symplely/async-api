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

class ReadableMemoryStream implements ReadableStream
{
    protected $buffer;
    
    protected $closed;
    
    public function __construct(string $buffer = '')
    {
        $this->buffer = $buffer;
    }
    
    public function isClosed(): bool
    {
        return $this->closed !== null;
    }

    /**
     * {@inheritdoc}
     */
    public function close(?\Throwable $e = null): void
    {
        if ($this->closed === null) {
            $this->closed = $e ?? true;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function read(?int $length = null): ?string
    {
        if ($this->closed) {
            throw new StreamClosedException('Cannot read from closed stream', 0, ($this->closed instanceof \Throwable) ? $this->closed : null);
        }
        
        $chunk = \substr($this->buffer, 0, $length ?? 0xFFFF);
        $this->buffer = \substr($this->buffer, \strlen($chunk));
        
        return ($chunk === '') ? null : $chunk;
    }
}
