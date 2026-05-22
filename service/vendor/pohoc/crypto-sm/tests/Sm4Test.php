<?php

namespace CryptoSm\Tests;

use PHPUnit\Framework\TestCase;
use CryptoSm\SM4\Sm4;
use CryptoSm\SM4\Sm4Options;

class Sm4Test extends TestCase
{
    public function testSm4EncryptReturnsHex()
    {
        $msg = 'hello world! test';
        $key = '0123456789abcdeffedcba9876543210';

        $encrypted = Sm4::encrypt($msg, $key);
        $this->assertNotEmpty($encrypted);
        $this->assertTrue(ctype_xdigit($encrypted));
    }

    public function testSm4DecryptReturnsString()
    {
        $msg = 'hello world! test message12345';
        $key = '0123456789abcdeffedcba9876543210';

        $encrypted = Sm4::encrypt($msg, $key);
        $decrypted = Sm4::decrypt($encrypted, $key);

        $this->assertIsString($decrypted);
    }

    public function testSm4KeyValidation()
    {
        $this->expectException(\CryptoSm\Exception\InvalidKeyException::class);

        $msg = 'test';
        $key = '0123456789abcdeffedcba987654321';

        Sm4::encrypt($msg, $key);
    }

    public function testSm4KeyLength()
    {
        $key = '0123456789abcdeffedcba9876543210';
        $this->assertEquals(32, strlen($key));
    }

    public function testSm4NoPadding()
    {
        $msg = '1234567890123456';
        $key = '0123456789abcdeffedcba9876543210';

        $options = (new Sm4Options())->setPadding('none');

        $encrypted = Sm4::encrypt($msg, $key, $options);
        $this->assertEquals(32, strlen($encrypted));

        $decrypted = Sm4::decrypt($encrypted, $key, $options);
        $this->assertEquals($msg, $decrypted);
    }

    public function testSm4CbcMode()
    {
        $msg = 'hello world test';
        $key = '0123456789abcdeffedcba9876543210';
        $iv = 'fedcba98765432100123456789abcdef';

        $options = (new Sm4Options())->setMode('cbc')->setIv($iv);

        $encrypted = Sm4::encrypt($msg, $key, $options);
        $this->assertNotEmpty($encrypted);

        $decrypted = Sm4::decrypt($encrypted, $key, $options);
        $this->assertEquals($msg, $decrypted);
    }

    public function testSm4Consistency()
    {
        $msg = 'consistent test';
        $key = '0123456789abcdeffedcba9876543210';

        $encrypted1 = Sm4::encrypt($msg, $key);
        $encrypted2 = Sm4::encrypt($msg, $key);

        $this->assertEquals($encrypted1, $encrypted2);
    }

    public function testSm4EncryptDecrypt()
    {
        $msg = 'Hello, SM4!';
        $key = '0123456789abcdeffedcba9876543210';

        $encrypted = Sm4::encrypt($msg, $key);
        $decrypted = Sm4::decrypt($encrypted, $key);

        $this->assertEquals($msg, $decrypted);
    }

    public function testSm4EncryptDecryptChinese()
    {
        $msg = '你好，SM4！';
        $key = '0123456789abcdeffedcba9876543210';

        $encrypted = Sm4::encrypt($msg, $key);
        $decrypted = Sm4::decrypt($encrypted, $key);

        $this->assertEquals($msg, $decrypted);
    }

    public function testSm4EncryptDecryptLongMessage()
    {
        $msg = str_repeat('Long message test. ', 100);
        $key = '0123456789abcdeffedcba9876543210';

        $encrypted = Sm4::encrypt($msg, $key);
        $decrypted = Sm4::decrypt($encrypted, $key);

        $this->assertEquals($msg, $decrypted);
    }

    public function testSm4CbcModeRequiresIv()
    {
        $this->expectException(\CryptoSm\Exception\InvalidKeyException::class);
        $this->expectExceptionMessage('CBC mode requires IV');

        $msg = 'test message';
        $key = '0123456789abcdeffedcba9876543210';

        $options = new Sm4Options();
        $options->setMode('cbc');

        Sm4::encrypt($msg, $key, $options);
    }

    public function testSm4InvalidKeyLength()
    {
        $this->expectException(\CryptoSm\Exception\InvalidKeyException::class);
        $this->expectExceptionMessage('Key must be 128 bits (32 hex chars)');

        $msg = 'test message';
        $key = 'shortkey';

        Sm4::encrypt($msg, $key);
    }

    public function testSm4EmptyMessage()
    {
        $msg = '';
        $key = '0123456789abcdeffedcba9876543210';

        $encrypted = Sm4::encrypt($msg, $key);
        $decrypted = Sm4::decrypt($encrypted, $key);

        $this->assertEquals($msg, $decrypted);
    }

    public function testSm4DifferentKeysProduceDifferentResults()
    {
        $msg = 'test message';
        $key1 = '0123456789abcdeffedcba9876543210';
        $key2 = 'fedcba98765432100123456789abcdef';

        $encrypted1 = Sm4::encrypt($msg, $key1);
        $encrypted2 = Sm4::encrypt($msg, $key2);

        $this->assertNotEquals($encrypted1, $encrypted2);
    }

    public function testSm4OptionsChainable()
    {
        $options = new Sm4Options();

        $result = $options->setPadding('none')->setMode('cbc')->setIv('000102030405060708090a0b0c0d0e0f');

        $this->assertInstanceOf(Sm4Options::class, $result);
        $this->assertEquals('none', $options->getPadding());
        $this->assertEquals('cbc', $options->getMode());
        $this->assertEquals('000102030405060708090a0b0c0d0e0f', $options->getIv());
    }

    public function testSm4HexToBytesStatic()
    {
        $hex = '0123456789abcdef';
        $bytes = Sm4::hexToBytesStatic($hex);

        $this->assertIsArray($bytes);
        $this->assertEquals(8, count($bytes));
    }

    public function testSm4Utf8ToArray()
    {
        $str = 'hello';
        $array = Sm4::utf8ToArray($str);

        $this->assertIsArray($array);
        $this->assertEquals(5, count($array));
    }

    public function testSm4MultipleEncryptDecrypt()
    {
        $key = '0123456789abcdeffedcba9876543210';
        $messages = [
            'short',
            'medium length message',
            'a bit longer message for testing',
            str_repeat('x', 100),
            '中文测试消息'
        ];

        foreach ($messages as $msg) {
            $encrypted = Sm4::encrypt($msg, $key);
            $decrypted = Sm4::decrypt($encrypted, $key);
            $this->assertEquals($msg, $decrypted, "Failed for message: $msg");
        }
    }

    public function testSm4CbcModeDifferentIvProduceDifferentResults()
    {
        $msg = 'test message';
        $key = '0123456789abcdeffedcba9876543210';
        $iv1 = 'fedcba98765432100123456789abcdef';
        $iv2 = '0123456789abcdeffedcba9876543210';

        $options1 = new Sm4Options();
        $options1->setMode('cbc')->setIv($iv1);

        $options2 = new Sm4Options();
        $options2->setMode('cbc')->setIv($iv2);

        $encrypted1 = Sm4::encrypt($msg, $key, $options1);
        $encrypted2 = Sm4::encrypt($msg, $key, $options2);

        $this->assertNotEquals($encrypted1, $encrypted2);
    }

    public function testSm4Pkcs5Padding()
    {
        $key = '0123456789abcdeffedcba9876543210';

        $msg1 = '1';
        $encrypted1 = Sm4::encrypt($msg1, $key);
        $this->assertEquals(32, strlen($encrypted1));

        $msg16 = '1234567890123456';
        $encrypted16 = Sm4::encrypt($msg16, $key);
        $this->assertEquals(64, strlen($encrypted16));
    }
}
