# SM4 算法使用指南

## 概述

SM4 是中国分组密码算法标准 (GM/T 0002-2012)，是一种 128 位分组密码，密钥长度也为 128 位。

## 基本用法

### ECB 模式（默认）

```php
use CryptoSm\SM4\Sm4;

$key = '0123456789abcdef'; // 16 字节
$data = 'Hello World';

$ciphertext = Sm4::encrypt($data, $key);
$plaintext = Sm4::decrypt($ciphertext, $key);
```

## 密码模式

### ECB 模式

电子密码本模式 - 每个分组独立加密。

```php
use CryptoSm\SM4\Sm4;
use CryptoSm\SM4\Sm4Options;

$key = '0123456789abcdef';
$options = (new Sm4Options())->setMode('ecb');

$ciphertext = Sm4::encrypt($data, $key, $options);
$plaintext = Sm4::decrypt($ciphertext, $key, $options);
```

### CBC 模式

密码块链接模式 - 每个分组与前一个密文分组进行 XOR 操作。

```php
use CryptoSm\SM4\Sm4;
use CryptoSm\SM4\Sm4Options;

$key = '0123456789abcdef';
$iv = '0123456789abcdef'; // 16 字节 IV
$options = (new Sm4Options())
    ->setMode('cbc')
    ->setIv($iv);

$ciphertext = Sm4::encrypt($data, $key, $options);
$plaintext = Sm4::decrypt($ciphertext, $key, $options);
```

## 填充模式

### PKCS5/PKCS7 填充（默认）

```php
use CryptoSm\SM4\Sm4;
use CryptoSm\SM4\Sm4Options;

$key = '0123456789abcdef';
$options = (new Sm4Options())->setPadding('pkcs5');

$ciphertext = Sm4::encrypt($data, $key, $options);
$plaintext = Sm4::decrypt($ciphertext, $key, $options);
```

### 无填充

```php
use CryptoSm\SM4\Sm4;
use CryptoSm\SM4\Sm4Options;

$key = '0123456789abcdef';
$data = str_pad('', 16, 'x'); // 必须是 16 的倍数
$options = (new Sm4Options())->setPadding('none');

$ciphertext = Sm4::encrypt($data, $key, $options);
$plaintext = Sm4::decrypt($ciphertext, $key, $options);
```

## 完整示例

### 简单加密

```php
<?php

declare(strict_types=1);

require_once 'vendor/autoload.php';

use CryptoSm\SM4\Sm4;

$key = '0123456789abcdef';
$plaintext = 'Hello World';

$ciphertext = Sm4::encrypt($plaintext, $key);
echo "密文: " . bin2hex($ciphertext) . "\n";

$decrypted = Sm4::decrypt($ciphertext, $key);
echo "解密: $decrypted\n";
```

### CBC 模式带 IV

```php
<?php

declare(strict_types=1);

require_once 'vendor/autoload.php';

use CryptoSm\SM4\Sm4;
use CryptoSm\SM4\Sm4Options;

$key = '0123456789abcdef';
$iv = 'fedcba9876543210';
$plaintext = 'Hello World';

$options = (new Sm4Options())
    ->setMode('cbc')
    ->setIv($iv);

$ciphertext = Sm4::encrypt($plaintext, $key, $options);
echo "密文: " . bin2hex($ciphertext) . "\n";

$decrypted = Sm4::decrypt($ciphertext, $key, $options);
echo "解密: $decrypted\n";
```

### 加密长数据

```php
<?php

declare(strict_types=1);

require_once 'vendor/autoload.php';

use CryptoSm\SM4\Sm4;
use CryptoSm\SM4\Sm4Options;

$key = '0123456789abcdef';
$plaintext = str_repeat('A', 1000); // 长数据

$options = (new Sm4Options())->setMode('cbc')->setIv('0123456789abcdef');
$ciphertext = Sm4::encrypt($plaintext, $key, $options);
$decrypted = Sm4::decrypt($ciphertext, $key, $options);

echo "原始长度: " . strlen($plaintext) . "\n";
echo "解密后长度: " . strlen($decrypted) . "\n";
echo "匹配: " . ($plaintext === $decrypted ? '是' : '否') . "\n";
```

## 密钥和 IV 要求

| 模式 | 密钥长度 | IV 长度 |
|------|----------|---------|
| ECB | 16 字节 | 无 |
| CBC | 16 字节 | 16 字节 |
| CFB | 16 字节 | 16 字节 |
| OFB | 16 字节 | 16 字节 |

## 技术细节

- **分组大小**: 128 位（16 字节）
- **密钥长度**: 128 位（16 字节）
- **轮数**: 32 轮
- **标准**: GM/T 0002-2012

## 安全注意事项

1. 对于安全关键的应用，请始终使用 CBC 模式或其他认证模式
2. 在 CBC 模式下，每次加密操作都应使用随机的 IV
3. 安全存储密钥（使用环境变量或安全的密钥管理）
4. SM4 在中国是政府强制使用的，但已经公开发布

## 错误处理

```php
use CryptoSm\SM4\Sm4;

try {
    $ciphertext = Sm4::encrypt($data, $key);
    $plaintext = Sm4::decrypt($ciphertext, $wrongKey);
    // 会产生乱码，不会抛出异常
    
    // 对于 CBC 模式，错误的密钥会因填充验证失败而抛出异常
    $options = (new Sm4Options())->setMode('cbc')->setIv($iv);
    $plaintext = Sm4::decrypt($ciphertext, $wrongKey, $options);
} catch (Exception $e) {
    echo "错误: " . $e->getMessage();
}
```
