# SM2 算法使用指南

## 概述

SM2 是中国椭圆曲线公钥密码算法标准 (GM/T 0003-2012)，提供以下功能：
- 密钥对生成
- 公钥加密/解密
- 数字签名与验证

## 密钥生成

```php
use CryptoSm\SM2\Sm2;
use CryptoSm\SM2\Keypair;

// 生成新的密钥对
$keypair = Sm2::generateKeyPairHex();

$privateKey = $keypair->getPrivateKey(); // 64 个十六进制字符
$publicKey = $keypair->getPublicKey();    // 128 个十六进制字符
```

## 加密和解密

### 基本用法

```php
use CryptoSm\SM2\Sm2;
use CryptoSm\SM2\Sm2CipherOptions;

// 公钥加密
$publicKey = '...'; // 128 个十六进制字符
$plaintext = 'Hello World';
$ciphertext = Sm2::doEncrypt($plaintext, $publicKey);

// 私钥解密
$privateKey = '...'; // 64 个十六进制字符
$decrypted = Sm2::doDecrypt($ciphertext, $privateKey);
```

### 密码模式

SM2 支持两种密码模式：

- 模式 1（默认）：C1 || C3 || C2
- 模式 0：C1 || C2 || C3

```php
use CryptoSm\SM2\Sm2;
use CryptoSm\SM2\Sm2CipherOptions;

// 使用密码模式 0
$options = (new Sm2CipherOptions())->setCipherMode(Sm2::CIPHER_MODE_0);
$ciphertext = Sm2::doEncrypt($plaintext, $publicKey, $options);
$decrypted = Sm2::doDecrypt($ciphertext, $privateKey, $options);
```

## 数字签名

### 不带哈希的签名

```php
use CryptoSm\SM2\Sm2;

// 签名
$privateKey = '...'; // 64 个十六进制字符
$message = '待签名消息';
$signature = Sm2::doSignature($message, $privateKey);
// 签名为 128 个十六进制字符 (r || s)

// 验证
$publicKey = '...'; // 128 个十六进制字符
$isValid = Sm2::doVerifySignature($message, $signature, $publicKey);
```

### 带哈希和用户 ID 的签名

```php
use CryptoSm\SM2\Sm2;
use CryptoSm\SM2\SignatureOptions;

// 使用哈希和公钥签名
$options = (new SignatureOptions())
    ->setHash(true)
    ->setPublicKey($publicKey);

$signature = Sm2::doSignature($message, $privateKey, $options);

// 使用相同选项验证
$isValid = Sm2::doVerifySignature($message, $signature, $publicKey, $options);
```

### 自定义用户 ID

```php
use CryptoSm\SM2\Sm2;
use CryptoSm\SM2\SignatureOptions;

// 自定义用户 ID（默认为 '1234567812345678'）
$options = (new SignatureOptions())
    ->setHash(true)
    ->setPublicKey($publicKey)
    ->setUserId('custom_user_id');

$signature = Sm2::doSignature($message, $privateKey, $options);
```

### DER 格式签名

SM2 支持 DER 编码的签名格式：

```php
use CryptoSm\SM2\Sm2;
use CryptoSm\SM2\SignatureOptions;

// 使用 DER 格式
$options = (new SignatureOptions())->setDer(true);
$signature = Sm2::doSignature($message, $privateKey, $options);

// 验证
$isValid = Sm2::doVerifySignature($message, $signature, $publicKey, $options);
```

## 完整示例

```php
<?php

declare(strict_types=1);

require_once 'vendor/autoload.php';

use CryptoSm\SM2\Sm2;
use CryptoSm\SM2\SignatureOptions;
use CryptoSm\SM2\Sm2CipherOptions;

// 1. 生成密钥对
$keypair = Sm2::generateKeyPairHex();
$privateKey = $keypair->getPrivateKey();
$publicKey = $keypair->getPublicKey();

echo "私钥: $privateKey\n";
echo "公钥: $publicKey\n\n";

// 2. 加密/解密
$plaintext = 'Hello World';
$ciphertext = Sm2::doEncrypt($plaintext, $publicKey);
echo "密文: $ciphertext\n";

$decrypted = Sm2::doDecrypt($ciphertext, $privateKey);
echo "解密: $decrypted\n\n";

// 3. 数字签名
$message = '测试消息';

// 简单签名
$signature = Sm2::doSignature($message, $privateKey);
echo "签名: $signature\n";

$isValid = Sm2::doVerifySignature($message, $signature, $publicKey);
echo "验证（简单签名）: " . ($isValid ? '有效' : '无效') . "\n";

// 带哈希的签名
$options = (new SignatureOptions())
    ->setHash(true)
    ->setPublicKey($publicKey);

$signature = Sm2::doSignature($message, $privateKey, $options);
$isValid = Sm2::doVerifySignature($message, $signature, $publicKey, $options);
echo "验证（带哈希）: " . ($isValid ? '有效' : '无效') . "\n";
```

## 错误处理

```php
use CryptoSm\SM2\Sm2;

try {
    $ciphertext = Sm2::doEncrypt('data', $publicKey);
    $decrypted = Sm2::doDecrypt($ciphertext, $privateKey);
} catch (Exception $e) {
    echo "错误: " . $e->getMessage();
}

// 解密验证失败会抛出异常
try {
    $decrypted = Sm2::doDecrypt($invalidCiphertext, $privateKey);
} catch (Exception $e) {
    echo "解密失败: " . $e->getMessage();
}
```

## 注意事项

1. 私钥必须为 64 个十六进制字符（256 位）
2. 公钥必须为 128 个十六进制字符（512 位，X||Y 坐标）
3. 密文格式：C1(128 字符) + C3(64 字符) + C2(可变长度)
4. 签名：128 个十六进制字符 (r||s) 或 DER 格式
5. SM2 签名的默认用户 ID 为 '1234567812345678'
