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

class CompressionTest extends AsyncTestCase
{
    public function testEncoder()
    {
        $data = random_bytes(8192 * 100);
        
        $stream = new ReadableDeflateStream(new ReadableMemoryStream($data));
        
        try {
            $buffer = '';
            
            while (null !== ($chunk = $stream->read(random_int(4096, 8192)))) {
                $buffer .= $chunk;
            }
        } finally {
            $stream->close();
        }
        
        $this->assertEquals($data, gzdecode($buffer));
    }
    
    public function testDecoder()
    {
        $data = random_bytes(8192 * 100);
        
        $stream = new ReadableInflateStream(new ReadableMemoryStream(gzencode($data)));
        
        try {
            $buffer = '';
            
            while (null !== ($chunk = $stream->read(random_int(4096, 8192)))) {
                $buffer .= $chunk;
            }
        } finally {
            $stream->close();
        }
        
        $this->assertEquals($data, $buffer);
    }
    
    public function testCombination()
    {
        $data = random_bytes(8192 * 100);

        $stream = new ReadableInflateStream(new ReadableDeflateStream(new ReadableMemoryStream($data)));

        $this->assertEquals($data, buffer($stream));
    }
}
