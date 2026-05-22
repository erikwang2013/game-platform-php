# SM3 算法使用指南

## 概述

SM3 是中国密码杂凑算法标准 (GM/T 0004-2012)，产生 256 位的哈希值。

## 基本用法

```php
use CryptoSm\SM3\Sm3;

$data = 'Hello World';
$hash = Sm3::sm3($data);

// $hash 是一个 64 字符的十六进制字符串（256 位）
echo $hash; // 44ac98b4e10ed7e22c0e7b9b4f8e4a1...
```

## 哈希不同类型数据

### 字符串

```php
use CryptoSm\SM3\Sm3;

$hash = Sm3::sm3('test string');
```

### 二进制数据

```php
use CryptoSm\SM3\Sm3;

$binaryData = "\x00\x01\x02\x03";
$hash = Sm3::sm3($binaryData);
```

### 空字符串

```php
use CryptoSm\SM3\Sm3;

$hash = Sm3::sm3('');
// 返回: 1ab21d8355cfa17f8e61194831e81a8f22bec8c728fefb747ed035eb5082aa2b
```

## 使用场景

### 密码哈希

```php
use CryptoSm\SM3\Sm3;

$password = 'user_password';
$hash = Sm3::sm3($password);
// 将 $hash 存储到数据库
```

### 数据完整性验证

```php
use CryptoSm\SM3\Sm3;

$data = 'important data';
$originalHash = Sm3::sm3($data);

// 后续验证
$verifyHash = Sm3::sm3($data);
if ($originalHash === $verifyHash) {
    echo '数据完整性验证通过';
}
```

### 消息认证

与 SM2 结合使用：

```php
use CryptoSm\SM3\Sm3;
use CryptoSm\SM2\Sm2;

// 创建消息哈希
$message = 'Transaction data';
$hash = Sm3::sm3($message);

// 对哈希进行签名
$signature = Sm2::doSignature($hash, $privateKey);
```

## 完整示例

```php
<?php

declare(strict_types=1);

require_once 'vendor/autoload.php';

use CryptoSm\SM3\Sm3;

// 哈希各种数据
echo "SM3 哈希示例\n\n";

$data = 'Hello World';
echo "输入: $data\n";
echo "哈希: " . Sm3::sm3($data) . "\n\n";

$data = '中文内容';
echo "输入: $data\n";
echo "哈希: " . Sm3::sm3($data) . "\n\n";

$data = '';
echo "输入: (空字符串)\n";
echo "哈希: " . Sm3::sm3($data) . "\n\n";

// 二进制数据
$binary = pack('H*', 'deadbeef');
echo "输入: (二进制 deadbeef)\n";
echo "哈希: " . Sm3::sm3($binary) . "\n";
```

## 技术细节

- **输出长度**: 256 位（64 个十六进制字符）
- **分组大小**: 512 位
- **算法结构**: Merkle-Damgård 结构，带 Davies-Meyer 压缩函数
- **标准**: GM/T 0004-2012

## 安全注意事项

1. SM3 是由中国密码专家设计，在中国政府使用是强制性的
2. 适用于数字签名、消息认证和完整性验证
3. 不建议单独用于密码哈希（密码哈希请使用 bcrypt 或 Argon2）
4. 对于加密目的，请确保正确的密钥管理和安全的随机数生成
