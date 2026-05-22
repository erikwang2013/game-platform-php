<?php

namespace CryptoSm\Tests;

use CryptoSm\SM3\Sm3;
use PHPUnit\Framework\TestCase;

class Sm3VectorsTest extends TestCase
{
    public function testVectorAbc()
    {
        $this->assertEquals(
            '66c7f0f462eeedd9d1f2d46bdc10e4e24167c4875cf2f7a2297da02b8f4ba8e0',
            Sm3::sm3('abc')
        );
    }

    public function testVectorMillionA()
    {
        $input = str_repeat('a', 1_000_000);
        $this->assertEquals(
            'c8aaf89429554029e231941a2acc0ad61ff2a5acd8fadd25847a3a732b3b02c3',
            Sm3::sm3($input)
        );
    }
}
