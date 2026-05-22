<?php

namespace CryptoSm\Tests;

use CryptoSm\SM4\Sm4;
use CryptoSm\SM4\Sm4Options;
use PHPUnit\Framework\TestCase;

class Sm4VectorsTest extends TestCase
{
    public function testEcbVector()
    {
        $key = '0123456789abcdeffedcba9876543210';
        $plain = hex2bin('0123456789abcdeffedcba9876543210');
        $cipher = Sm4::encrypt($plain, $key, (new Sm4Options())->setPadding('none'));
        $this->assertEquals('681edf34d206965e86b3e94f536e4246', $cipher);
    }

    public function testCbcVector()
    {
        $key = '0123456789abcdeffedcba9876543210';
        $iv = '000102030405060708090a0b0c0d0e0f';
        $plain = hex2bin('0123456789abcdeffedcba9876543210');
        $opts = (new Sm4Options())->setPadding('none')->setMode('cbc')->setIv($iv);
        $cipher = Sm4::encrypt($plain, $key, $opts);
        $this->assertEquals('a9a268883a336315bac0c9c9ff350ab1', $cipher);
    }

    public function testPaddingRoundTrip()
    {
        $key = '0123456789abcdeffedcba9876543210';
        $msg = 'hello sm4 padding';
        $ct = Sm4::encrypt($msg, $key);
        $this->assertNotEmpty($ct);
        $pt = Sm4::decrypt($ct, $key);
        $this->assertEquals($msg, $pt);
    }
}
