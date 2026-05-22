<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\Encryptable;

use Erikwang2013\Encryptable\Contracts\DbDriverDetector;
use Erikwang2013\Encryptable\Contracts\EncryptableConfigContract;
use LogicException;

/**
 * SQL decrypt helpers use the **primary** key only. Rotating DB-side ciphertext
 * requires re-encryption (e.g. read with PHP decrypt after migrating keys, or ALTER pipeline).
 */
class DBEncrypter extends Encrypter
{
    public function __construct(
        EncryptableConfigContract $encryptableConfig,
        protected DbDriverDetector $driverDetector
    ) {
        parent::__construct($encryptableConfig);
    }

    public function encrypt(mixed $value, bool $serialize = true): ?string
    {
        throw new LogicException('DB-level encryption is not supported. Use PHPEncrypter for application-level encrypt().');
    }

    public function decrypt(?string $payload, bool $unserialize = true): mixed
    {
        if (is_null($payload)) {
            return null;
        }

        if ($this->driverDetector->isPostgres()) {
            return sprintf(
                $this->getPostgresGrammarDecrypt(),
                $payload,
                $this->escapeSqlString($this->getEncryptionKey()),
                $this->escapeSqlString($this->getEncryptionCipherAlgorithm())
            );
        }

        return sprintf(
            $this->getMysqlGrammarDecrypt(),
            $payload,
            $this->escapeSqlString($this->getEncryptionKey())
        );
    }

    private function escapeSqlString(string $value): string
    {
        return str_replace("'", "''", $value);
    }

    protected function getMysqlGrammarDecrypt(): string
    {
        return "CONVERT( SUBSTRING_INDEX( AES_DECRYPT( FROM_BASE64(%s), '%s' ), ':', -1 ) USING 'UTF8' )";
    }

    protected function getPostgresGrammarDecrypt(): string
    {
        return "split_part( convert_from( decrypt( decode(%s, 'base64'), '%s', '%s'), 'UTF8' ), ':', 3 )";
    }

    protected function getEncryptionCipherAlgorithm(): string
    {
        $cipher = $this->getEncryptionCipher();

        $dash = strpos($cipher, '-');
        if ($dash === false) {
            return $cipher;
        }

        return substr($cipher, 0, $dash);
    }
}
