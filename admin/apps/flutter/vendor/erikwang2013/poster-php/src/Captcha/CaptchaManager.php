<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */

namespace Erikwang2013\Poster\Captcha;

use Erikwang2013\Poster\Drivers\ImageDriverInterface;
use Erikwang2013\Poster\Storage\StorageInterface;
use Erikwang2013\Poster\PosterConfig;

class CaptchaManager
{
    private ImageDriverInterface $imageDriver;
    private StorageInterface $storage;
    private ?CaptchaInterface $currentCaptcha = null;

    public function __construct(ImageDriverInterface $imageDriver, StorageInterface $storage)
    {
        $this->imageDriver = $imageDriver;
        $this->storage = $storage;
    }

    public function create(string $type): CaptchaInterface
    {
        $this->currentCaptcha = CaptchaFactory::create($type, $this->imageDriver, $this->storage);
        return $this->currentCaptcha;
    }

    public function verify(string $key, array $data): bool
    {
        $stored = $this->storage->get($key);
        if ($stored === null) {
            return false;
        }

        $maxAttempts = PosterConfig::get('captcha.max_attempts', 3);
        $currentAttempts = $stored['attempts'] ?? 0;
        if ($currentAttempts >= $maxAttempts) {
            $this->storage->del($key);
            return false;
        }

        $type = $data['type'] ?? '';
        $userData = $data['data'] ?? null;
        $result = $this->check($type, $stored, $userData);

        if ($result) {
            $this->storage->del($key);
        } else {
            $this->storage->incrementAttempts($key);
        }

        return $result;
    }

    private function check(string $type, array $stored, mixed $userData): bool
    {
        if ($type !== ($stored['type'] ?? '')) {
            return false;
        }

        $tolerance = PosterConfig::get('captcha.tolerance', ['click' => 18, 'rotate' => 5, 'slider' => 4]);

        return match ($type) {
            'click'  => $this->checkClick($stored, $userData, $tolerance['click']),
            'rotate' => $this->checkRotate($stored, $userData, $tolerance['rotate']),
            'slider' => $this->checkSlider($stored, $userData, $tolerance['slider']),
            default  => false,
        };
    }

    private function checkClick(array $stored, mixed $userData, int $tolerance): bool
    {
        if (!is_array($userData) || !isset($stored['targets']) || !is_array($stored['targets'])) {
            return false;
        }
        if (count($userData) !== count($stored['targets'])) {
            return false;
        }
        foreach ($stored['targets'] as $i => $target) {
            $ux = $userData[$i][0] ?? -999;
            $uy = $userData[$i][1] ?? -999;
            $dx = $ux - $target['x'];
            $dy = $uy - $target['y'];
            if (sqrt($dx * $dx + $dy * $dy) > $tolerance) {
                return false;
            }
        }
        return true;
    }

    private function checkRotate(array $stored, mixed $userData, int $tolerance): bool
    {
        if (!is_numeric($userData) || !isset($stored['angle'])) {
            return false;
        }
        $angle = floatval($userData);
        $actual = floatval($stored['angle']);
        $diff = abs($angle - (360 - $actual));
        if ($diff > 180) {
            $diff = 360 - $diff;
        }
        return $diff <= $tolerance;
    }

    private function checkSlider(array $stored, mixed $userData, int $tolerance): bool
    {
        if (!is_numeric($userData) || !isset($stored['x'])) {
            return false;
        }
        return abs(floatval($userData) - floatval($stored['x'])) <= $tolerance;
    }
}
