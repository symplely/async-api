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
use Concurrent\Timer;
use function Concurrent\merge;

class StreamTest extends AsyncTestCase
{
    public function testRead()
    {
        $a = new ReadableMemoryStream('Hello World');

        $this->assertEquals('Hello Worl', read($a, 10));
        $this->assertEquals('d', read($a, 2, false));
        $this->assertNull(read($a, 1, false));

        $this->expectException(StreamException::class);

        read($a, 1);
    }
    
    public function testPipe()
    {
        $a = new ReadableMemoryStream($contents = str_repeat('A', 8192 * 128));
        $b = new WritableMemoryStream();

        $this->assertFalse($a->isClosed());

        $this->assertEquals(strlen($contents), pipe($a, $b));

        $this->assertTrue($a->isClosed());
        $this->assertFalse($b->isClosed());
        $this->assertEquals($contents, $b->getContents());
    }
    
    public function testTimerBasedWrites()
    {
        $messages = str_split($message = 'Hello Socket :)', 4);
        
        list ($a, $b) = Socket::pair();
        
        Task::async(function () use ($a, $messages) {
            $timer = new Timer(150);
            $i = 0;
            
            try {
                while (isset($messages[$i])) {
                    $timer->awaitTimeout();
                    
                    fwrite($a, $messages[$i++]);
                }
            } finally {
                fclose($a);
            }
        });
        
        $reader = new Reader($b);
        $received = '';
        
        try {
            while (null !== ($chunk = $reader->read())) {
                $received .= $chunk;
            }
        } finally {
            $reader->close();
        }
        
        $this->assertEquals($message, $received);
    }
    
    public function provideSendReceiveSettings()
    {
        yield [70, false, false];
        yield [70, true, false];
        yield [70, false, true];
        yield [70, true, true];
        yield [7000, false, false];
        yield [8192, false, false];
        yield [8000 * 100, false, false];
        yield [8000 * 100, true, false];
        yield [8000 * 100, false, true];
        yield [8000 * 100, true, true];
    }

    /**
     * @dataProvider provideSendReceiveSettings
     */
    public function testSenderAndReceiver(int $size, bool $delayedSend, bool $delayedReceive)
    {
        $message = str_repeat('.', $size);
        $received = '';
        
        list ($a, $b) = Socket::streamPair();
        
        $t = Task::async(function (WritableStream $socket) use ($message, $delayedSend) {
            $timer = new Timer(10);
            
            try {
                foreach (str_split($message, 7000) as $chunk) {
                    if ($delayedSend) {
                        $timer->awaitTimeout();
                    }
                    
                    $socket->write($chunk);
                }
            } finally {
                $socket->close();
            }
            
            return 'DONE';
        }, $a);
        
        $timer = new Timer(10);
        
        try {
            if ($delayedReceive) {
                $timer->awaitTimeout();
            }
            
            while (null !== ($chunk = $b->read())) {
                if ($delayedReceive) {
                    $timer->awaitTimeout();
                }
                
                $received .= $chunk;
            }
        } finally {
            $b->close();
        }
        
        $this->assertEquals('DONE', Task::await($t));
        $this->assertEquals($message, $received);
    }
    
    public function testReadableChannelStream()
    {
        $channel = new Channel();
        
        Task::async(function () use ($channel) {
            try {
                $timer = new Timer(30);
                
                for ($i = 0; $i < 10; $i++) {
                    $timer->awaitTimeout();
                    
                    $channel->send('CHUNK!');
                }
            } finally {
                $channel->close();
            }
        });
        
        $stream = new ReadableChannelStream($channel->getIterator());
        
        $this->assertEquals(str_repeat('CHUNK!', 10), buffer($stream));
    }
    
    public function testWritableChannelStream()
    {
        $channel = new Channel();
        $stream = new WritableChannelStream($channel);
        
        Task::async(function () use ($stream) {
            try {
                $timer = new Timer(30);
                
                for ($i = 0; $i < 10; $i++) {
                    $timer->awaitTimeout();
                    
                    $stream->write('CHUNK!');
                }
            } finally {
                $stream->close();
            }
        });
        
        $this->assertEquals(str_repeat('CHUNK!', 10), implode('', iterator_to_array($channel)));
    }
    
    public function testMerge()
    {
        $producer = function (Channel $channel, int $delay) {
            try {
                $timer = new Timer($delay);
                
                for ($i = 0; $i < 3; $i++) {
                    $timer->awaitTimeout();
                    
                    $channel->send($i);
                }
            } finally {
                $channel->close();
            }
        };
        
        Task::async($producer, $c1 = new Channel(), 10);
        Task::async($producer, $c2 = new Channel(), 10);
        
        $this->assertEquals([
            0,
            0,
            1,
            1,
            2,
            2
        ], iterator_to_array(merge($c1, $c2), false));
    }
}
