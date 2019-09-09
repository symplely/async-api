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

use Concurrent\Channel;

class WritableChannelStream implements WritableStream
{
    protected $channel;
    
    protected $closed;
    
    protected $cascadeClose;
    
    public function __construct(Channel $channel, bool $cascadeClose = true)
    {
        $this->channel = $channel;
        $this->cascadeClose = $cascadeClose;
    }
    
    /**
     * {@inheritdoc}
     */
    public function close(?\Throwable $e = null): void
    {
        if ($this->closed === null) {
            $this->closed = $e ?? true;
            
            if ($this->cascadeClose) {
                $this->channel->close($e);
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function write(string $data): void
    {
        if ($this->closed) {
            throw new StreamClosedException('Cannot write to closed stream', 0, ($this->closed instanceof \Throwable) ? $this->closed : null);
        }
        
        $this->channel->send($data);
    }
}
