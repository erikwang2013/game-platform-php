<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */

namespace Erikwang2013\Poster\Captcha;

use Erikwang2013\Poster\Drivers\ImageDriverInterface;
use Erikwang2013\Poster\Storage\StorageInterface;
use Erikwang2013\Poster\PosterConfig;

abstract class AbstractCaptcha implements CaptchaInterface
{
    protected ImageDriverInterface $imageDriver;
    protected StorageInterface $storage;
    protected string $difficulty = 'medium';
    protected ?string $backgroundPath = null;
    protected string $key = '';
    protected int $width = 300;
    protected int $height = 200;

    public function __construct(ImageDriverInterface $imageDriver, StorageInterface $storage)
    {
        $this->imageDriver = $imageDriver;
        $this->storage = $storage;
    }

    public function setDifficulty(string $difficulty): static
    {
        $this->difficulty = $difficulty;
        return $this;
    }

    public function setBackground(?string $imagePath): static
    {
        $this->backgroundPath = $imagePath;
        return $this;
    }

    protected function createBackground(): ImageDriverInterface
    {
        $bg = $this->imageDriver->clone();
        if ($this->backgroundPath !== null && is_file($this->backgroundPath)) {
            $bg->load($this->backgroundPath);
            $size = $bg->getSize();
            $this->width = $size['width'];
            $this->height = $size['height'];
        } else {
            $bg->create($this->width, $this->height);
            $bg->rectangle(0, 0, $this->width, $this->height, ['color' => $this->randomLightColor()]);
            for ($i = 0; $i < 50; $i++) {
                $x = mt_rand(0, $this->width - 1);
                $y = mt_rand(0, $this->height - 1);
                $bg->ellipse($x, $y, 2, 2, ['color' => $this->randomColor(), 'filled' => true]);
            }
        }
        return $bg;
    }

    protected function generateKey(): string
    {
        $this->key = bin2hex(random_bytes(16));
        return $this->key;
    }

    protected function randomColor(): string
    {
        return sprintf('#%02X%02X%02X', mt_rand(0, 200), mt_rand(0, 200), mt_rand(0, 200));
    }

    protected function randomLightColor(): string
    {
        return sprintf('#%02X%02X%02X', mt_rand(200, 255), mt_rand(200, 255), mt_rand(200, 255));
    }

    protected function store(array $answerData): void
    {
        $this->storage->set($this->key, array_merge($answerData, [
            'type'       => $this->getType(),
            'attempts'   => 0,
            'created_at' => time(),
        ]), PosterConfig::get('captcha.ttl', 300));
    }

    abstract protected function getType(): string;
}
