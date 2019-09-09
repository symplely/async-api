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

class Writer implements WritableStream
{
    protected $resource;
    
    protected $watcher;
    
    protected $writing = false;
    
    public function __construct($resource, ?Poll $watcher = null)
    {
        $this->resource = $resource;
        $this->watcher = $watcher ?? new Poll($resource);
        
        if (!\stream_set_blocking($resource, false)) {
            throw new \InvalidArgumentException('Cannot switch resource to non-blocking mode');
        }
        
        \stream_set_write_buffer($resource, 0);
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
            @\stream_socket_shutdown($resource, \STREAM_SHUT_WR);
        } else {
            $this->watcher->close($e);
            
            @\fclose($resource);
        }
    }
    
    /**
     * {@inheritdoc}
     */
    public function write(string $data): void
    {
        $retried = 0;
        
        while ($this->writing) {
            $this->watcher->awaitWritable();
        }
        
        while ($data !== '') {
            if (!\is_resource($this->resource)) {
                throw new StreamClosedException('Cannot write to closed stream');
            }
            
            if (false === ($len = @\fwrite($this->resource, $data, 0xFFFF))) {
                throw new StreamException(\sprintf('Could not write to stream: %s', \error_get_last()['message'] ?? ''));
            }
            
            if ($len > 0) {
                $data = \substr($data, $len);
                $retried = 0;
            } elseif (@\feof($this->resource)) {
                throw new StreamClosedException('Cannot write to closed stream');
            } else {
                if ($retried++ > 1) {
                    throw new StreamClosedException(\sprintf('Could not write bytes after %u retries, assuming broken pipe', $retried));
                }
                
                $this->writing = true;
                
                try {
                    $this->watcher->awaitWritable();
                } finally {
                    $this->writing = false;
                }
            }
        }
    }
}
