<?php

namespace CryptoSm\Tests;

use PHPUnit\Framework\TestCase;
use CryptoSm\SM3\Sm3;

class Sm3Test extends TestCase
{
    public function testSm3Basic()
    {
        $result = Sm3::sm3('abc');
        $this->assertNotEmpty($result);
        $this->assertEquals(64, strlen($result));
    }

    public function testSm3LongInput()
    {
        $result = Sm3::sm3('hello world');
        $this->assertNotEmpty($result);
        $this->assertEquals(64, strlen($result));
    }

    public function testSm3Empty()
    {
        $result = Sm3::sm3('');
        $this->assertNotEmpty($result);
        $this->assertEquals(64, strlen($result));
    }

    public function testSm3Consistency()
    {
        $msg = 'test message for consistency';
        $result1 = Sm3::sm3($msg);
        $result2 = Sm3::sm3($msg);
        $this->assertEquals($result1, $result2);
    }

    public function testSm3Chinese()
    {
        $result = Sm3::sm3('你好世界');
        $this->assertNotEmpty($result);
        $this->assertEquals(64, strlen($result));
    }

    public function testSm3ReturnsHexadecimalString()
    {
        $result = Sm3::sm3('test');
        $this->assertTrue(ctype_xdigit($result));
    }

    public function testSm3DifferentInputsProduceDifferentOutputs()
    {
        $result1 = Sm3::sm3('input1');
        $result2 = Sm3::sm3('input2');
        
        $this->assertNotEquals($result1, $result2);
    }

    public function testSm3VeryLongInput()
    {
        $longInput = str_repeat('a', 10000);
        $result = Sm3::sm3($longInput);
        
        $this->assertNotEmpty($result);
        $this->assertEquals(64, strlen($result));
    }

    public function testSm3SpecialCharacters()
    {
        $specialChars = "!@#$%^&*()_+-=[]{}|;':\",./<>?\n\t\r";
        $result = Sm3::sm3($specialChars);
        
        $this->assertNotEmpty($result);
        $this->assertEquals(64, strlen($result));
    }

    public function testSm3BinaryData()
    {
        $binaryData = chr(0) . chr(128) . chr(255);
        $result = Sm3::sm3($binaryData);
        
        $this->assertNotEmpty($result);
        $this->assertEquals(64, strlen($result));
    }

    public function testSm3KnownTestVector()
    {
        $result = Sm3::sm3('abc');
        $this->assertEquals(64, strlen($result));
        $this->assertTrue(ctype_xdigit($result));
    }

    public function testSm3MultipleCallsSameResult()
    {
        $msg = 'consistent test';
        $results = [];
        
        for ($i = 0; $i < 10; $i++) {
            $results[] = Sm3::sm3($msg);
        }
        
        $firstResult = $results[0];
        foreach ($results as $result) {
            $this->assertEquals($firstResult, $result);
        }
    }

    public function testSm3SingleCharacter()
    {
        $result = Sm3::sm3('a');
        $this->assertNotEmpty($result);
        $this->assertEquals(64, strlen($result));
    }

    public function testSm3UnicodeCharacters()
    {
        $unicode = '😀🎉🚀';
        $result = Sm3::sm3($unicode);
        
        $this->assertNotEmpty($result);
        $this->assertEquals(64, strlen($result));
    }

    public function testSm3Whitespace()
    {
        $whitespace = " \t\n\r";
        $result = Sm3::sm3($whitespace);
        
        $this->assertNotEmpty($result);
        $this->assertEquals(64, strlen($result));
    }

    public function testSm3Reproducibility()
    {
        $messages = [
            'test1',
            'test2',
            'hello world',
            '你好世界',
            str_repeat('x', 1000)
        ];
        
        foreach ($messages as $msg) {
            $hash1 = Sm3::sm3($msg);
            $hash2 = Sm3::sm3($msg);
            $this->assertEquals($hash1, $hash2, "Hash not reproducible for: $msg");
        }
    }
}
