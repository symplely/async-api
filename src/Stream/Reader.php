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

class Reader implements ReadableStream
{
    protected $resource;
    
    protected $watcher;
    
    protected $bufferSize;
    
    protected $buffer = '';
    
    protected $reading = false;
    
    public function __construct($resource, ?Poll $watcher = null, int $bufferSize = 0x8000)
    {
        $this->resource = $resource;
        $this->watcher = $watcher ?? new Poll($resource);
        $this->bufferSize = $bufferSize;
        
        if (!\stream_set_blocking($resource, false)) {
            throw new \InvalidArgumentException('Cannot switch resource to non-blocking mode');
        }
        
        \stream_set_read_buffer($resource, 0);
    }
    
    public function __destruct()
    {
        $this->close();
    }
    
    /**
     * {@inheritdoc}
     */
    public function close(?\Throwable $e = null): void
    {
        if (!\is_resource($this->resource)) {
            return;
        }
        
        $resource = $this->resource;
        $this->resource = null;
        
        $meta = @\stream_get_meta_data($resource);
        
        if ($meta && \strpos($meta['mode'], '+') !== false) {
            @\stream_socket_shutdown($resource, \STREAM_SHUT_RD);
        } else {
            $this->watcher->close($e);
            
            @\fclose($resource);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function read(?int $length = null): ?string
    {
        if ($length === null) {
            $length = $this->bufferSize;
        }
        
        if ($this->reading) {
            throw new PendingReadException('Cannot read from stream while another read is pending');
        }
        
        while ($this->buffer === '') {
            if (!\is_resource($this->resource)) {
                throw new StreamClosedException('Cannot read from closed stream');
            }
            
            if (false === ($chunk = @\stream_get_contents($this->resource, $this->bufferSize))) {
                throw new StreamException(\sprintf('Failed to read data from stream: "%s"', \error_get_last()['message'] ?? ''));
            }
            
            if ($chunk !== '') {
                $this->buffer = $chunk;
                
                break;
            }
            
            if (\feof($this->resource)) {
                return null;
            }
            
            $this->reading = true;
            
            try {
                $this->watcher->awaitReadable();
            } finally {
                $this->reading = false;
            }
        }
        
        $chunk = \substr($this->buffer, 0, $length);
        $this->buffer = \substr($this->buffer, \strlen($chunk));
        
        return $chunk;
    }
}
