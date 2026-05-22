<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */

namespace Erikwang2013\Poster\Captcha;

interface CaptchaInterface
{
    public function setDifficulty(string $difficulty): static;
    public function setBackground(?string $imagePath): static;
    public function generate(): array;
}
