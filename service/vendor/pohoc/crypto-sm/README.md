# Crypto-SM

[![PHP Version](https://img.shields.io/badge/PHP-8.0+-blue.svg)](https://www.php.net/)
[![License](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE)

国密算法 SM2、SM3、SM4 的 PHP 实现。

## 安装

```bash
composer require pohoc/crypto-sm
```

## 环境要求

- PHP >= 8.0
- GMP 扩展

### 安装 GMP 扩展

**Ubuntu/Debian:**
```bash
sudo apt-get install php-gmp
```

**CentOS/RHEL:**
```bash
sudo yum install php-gmp
```

**macOS (Homebrew):**
```bash
brew install php@gmp
```

**Windows:**
在 php.ini 中启用 php_gmp.dll 扩展（取消注释）:
```ini
extension=php_gmp.dll
```

**验证安装:**
```php
<?php
var_dump(extension_loaded('gmp'));
```

## 支持的算法

### SM2
中国椭圆曲线公钥密码算法 (GM/T 0003-2012)
- 密钥生成
- 加密/解密
- 签名/验签

### SM3
中国密码杂凑算法 (GM/T 0004-2012)
- 哈希计算

### SM4
中国分组密码算法 (GM/T 0002-2012)
- 加密/解密
- ECB/CBC 模式
- PKCS5/PKCS7 填充

## 快速开始

### SM2

```php
use CryptoSm\SM2\Sm2;
use CryptoSm\SM2\Keypair;
use CryptoSm\SM2\SignatureOptions;

// 生成密钥对
$keypair = Sm2::generateKeyPairHex();
$privateKey = $keypair->getPrivateKey();
$publicKey = $keypair->getPublicKey();

// 加密
$ciphertext = Sm2::doEncrypt('Hello World', $publicKey);

// 解密
$plaintext = Sm2::doDecrypt($ciphertext, $privateKey);

// 签名（不哈希）
$signature = Sm2::doSignature('Message', $privateKey);
$isValid = Sm2::doVerifySignature('Message', $signature, $publicKey);

// 签名（哈希并指定用户 ID）
$options = (new SignatureOptions())
    ->setHash(true)
    ->setPublicKey($publicKey);
$signature = Sm2::doSignature('Message', $privateKey, $options);
$isValid = Sm2::doVerifySignature('Message', $signature, $publicKey, $options);

// DER 格式签名
$options = (new SignatureOptions())->setDer(true);
$signature = Sm2::doSignature('Message', $privateKey, $options);
$isValid = Sm2::doVerifySignature('Message', $signature, $publicKey, $options);
```

### SM3

```php
use CryptoSm\SM3\Sm3;

$hash = Sm3::sm3('Hello World');
```

### SM4

```php
use CryptoSm\SM4\Sm4;
use CryptoSm\SM4\Sm4Options;

$key = '0123456789abcdeffedcba9876543210'; // 32 位十六进制，128 bit
$data = 'Hello World';

// ECB 模式（默认），返回十六进制密文
$ciphertext = Sm4::encrypt($data, $key);
$plaintext = Sm4::decrypt($ciphertext, $key);

// CBC 模式，IV 同样为 32 位十六进制
$options = (new Sm4Options())->setMode('cbc')->setIv('000102030405060708090a0b0c0d0e0f');
$ciphertext = Sm4::encrypt($data, $key, $options);
$plaintext = Sm4::decrypt($ciphertext, $key, $options);
```

## 使用 SmCrypto 门面类

```php
use CryptoSm\SmCrypto;

// SM2
$keypair = SmCrypto::generateKeyPair();
$ciphertext = SmCrypto::encrypt($data, $publicKey);
$plaintext = SmCrypto::decrypt($ciphertext, $privateKey);
$signature = SmCrypto::sign($data, $privateKey);
$isValid = SmCrypto::verify($data, $signature, $publicKey);

// SM3
$hash = SmCrypto::sm3($data);

// SM4
$ciphertext = SmCrypto::sm4Encrypt($data, $key);
$plaintext = SmCrypto::sm4Decrypt($ciphertext, $key);
```

## 测试

```bash
composer install
vendor/bin/phpunit
```

## 许可证

MIT 许可证
