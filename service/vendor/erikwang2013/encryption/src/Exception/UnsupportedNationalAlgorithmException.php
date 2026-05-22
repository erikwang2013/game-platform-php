<?php

declare(strict_types=1);

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\Encryption\Exception;

/**
 * SM1、SM7、SM9 等需专用硬件/未公开规范或本库未集成的国密算法。
 */
final class UnsupportedNationalAlgorithmException extends EncryptionException
{
}
