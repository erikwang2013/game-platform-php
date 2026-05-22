<?php

/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Encryption key
    |--------------------------------------------------------------------------
    |
    | The key used to encrypt data.
    | Once defined, never change it or encrypted data cannot be correctly decrypted.
    |
    */

    'key' => env('ENCRYPTION_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Encryption cipher
    |--------------------------------------------------------------------------
    |
    | The cipher used to encrypt data.
    | Once defined, never change it or encrypted data cannot be correctly decrypted.
    |
    | Default is aes-256-gcm (authenticated encryption with random IV). GCM provides
    | both confidentiality and integrity — ciphertext tampering is detectable.
    | Use aes-128-ecb only if you need deterministic encryption for encrypted-column
    | querying (UniqueEncrypted / ExistsEncrypted rules). ECB produces identical
    | ciphertext for identical plaintext, which enables equality lookups but leaks
    | data patterns and provides no integrity protection.
    |
    */

    'cipher' => env('ENCRYPTION_CIPHER', 'aes-256-gcm'),

    /*
    |--------------------------------------------------------------------------
    | Previous encryption keys (rotation)
    |--------------------------------------------------------------------------
    |
    | Retired keys still accepted for decrypting existing ciphertext. Use the
    | same cipher as the primary key. Env ENCRYPTION_PREVIOUS_KEYS: comma list
    | or JSON array, e.g. "old16bytekeyaaa,older16bytekey" or ["k1","k2"].
    |
    */

    'previous_keys' => \Erikwang2013\Encryptable\Support\PreviousKeysParser::parse(env('ENCRYPTION_PREVIOUS_KEYS')),
];
