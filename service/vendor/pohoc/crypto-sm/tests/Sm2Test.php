<?php

namespace CryptoSm\Tests;

use CryptoSm\SM2\Keypair;
use CryptoSm\SM2\SignatureOptions;
use CryptoSm\SM2\Sm2;
use CryptoSm\SM2\Sm2CipherOptions;
use PHPUnit\Framework\TestCase;

class Sm2Test extends TestCase
{
    public function testGenerateKeyPair()
    {
        $keypair = Sm2::generateKeyPairHex();
        $this->assertInstanceOf(Keypair::class, $keypair);
        $this->assertEquals(64, strlen($keypair->getPrivateKey()));
        $this->assertEquals(128, strlen($keypair->getPublicKey()));
    }

    public function testEncryptDecryptRoundTrip()
    {
        $keypair = Sm2::generateKeyPairHex();
        $msg = 'Hello, SM2!';
        $ct = Sm2::doEncrypt($msg, $keypair->getPublicKey());
        $pt = Sm2::doDecrypt($ct, $keypair->getPrivateKey());
        $this->assertEquals($msg, $pt);
    }

    public function testEncryptDecryptMode0()
    {
        $kp = Sm2::generateKeyPairHex();
        $opts = (new Sm2CipherOptions())->setCipherMode(Sm2::CIPHER_MODE_0);
        $msg = 'mode0';
        $ct = Sm2::doEncrypt($msg, $kp->getPublicKey(), $opts);
        $this->assertEquals($msg, Sm2::doDecrypt($ct, $kp->getPrivateKey(), $opts));
    }

    public function testSignVerify()
    {
        $kp = Sm2::generateKeyPairHex();
        $msg = 'sign me';
        $sig = Sm2::doSignature($msg, $kp->getPrivateKey());
        $this->assertTrue(Sm2::doVerifySignature($msg, $sig, $kp->getPublicKey()));
    }

    public function testSignVerifyWithHashAndUserId()
    {
        $kp = Sm2::generateKeyPairHex();
        $opts = (new SignatureOptions())->setHash(true)->setPublicKey($kp->getPublicKey())->setUserId('customUserId1234');
        $msg = 'hash mode';
        $sig = Sm2::doSignature($msg, $kp->getPrivateKey(), $opts);
        $this->assertTrue(Sm2::doVerifySignature($msg, $sig, $kp->getPublicKey(), $opts));
    }

    public function testDerSignature()
    {
        $kp = Sm2::generateKeyPairHex();
        $opts = (new SignatureOptions())->setDer(true);
        $msg = 'der format';
        $sig = Sm2::doSignature($msg, $kp->getPrivateKey(), $opts);
        $this->assertTrue(Sm2::doVerifySignature($msg, $sig, $kp->getPublicKey(), $opts));
    }

    public function testTamperedSignatureFails()
    {
        $kp = Sm2::generateKeyPairHex();
        $msg = 'original';
        $sig = Sm2::doSignature($msg, $kp->getPrivateKey());
        // flip a nibble
        $sig = substr_replace($sig, $sig[0] === 'a' ? 'b' : 'a', 0, 1);
        $this->assertFalse(Sm2::doVerifySignature($msg, $sig, $kp->getPublicKey()));
    }

    public function testInvalidPublicKeyRejected()
    {
        $this->expectException(\CryptoSm\Exception\InvalidKeyException::class);
        Sm2::doEncrypt('msg', str_repeat('a', 128));
    }

    public function testInvalidCiphertextRejected()
    {
        $this->expectException(\CryptoSm\Exception\InvalidKeyException::class);
        $kp = Sm2::generateKeyPairHex();
        Sm2::doDecrypt(str_repeat('a', 190), $kp->getPrivateKey());
    }

    public function testSignatureOptionsChainable()
    {
        $options = new SignatureOptions();
        $result = $options->setDer(true)->setHash(true)->setPublicKey(str_repeat('0', 128))->setUserId('user');
        $this->assertInstanceOf(SignatureOptions::class, $result);
        $this->assertTrue($options->getDer());
        $this->assertTrue($options->getHash());
        $this->assertEquals('user', $options->getUserId());
    }

    public function testSm2CipherOptionsChainable()
    {
        $options = new Sm2CipherOptions();
        $this->assertSame($options, $options->setCipherMode(Sm2::CIPHER_MODE_0));
        $this->assertEquals(Sm2::CIPHER_MODE_0, $options->getCipherMode());
    }
}
