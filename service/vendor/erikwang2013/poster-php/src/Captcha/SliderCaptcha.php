<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */

namespace Erikwang2013\Poster\Captcha;

class SliderCaptcha extends AbstractCaptcha
{
    private int $puzzleWidth = 50;
    private int $puzzleHeight = 50;

    protected function getType(): string
    {
        return 'slider';
    }

    public function generate(): array
    {
        $this->generateKey();
        $bg = $this->createBackground();

        if ($this->difficulty === 'hard') {
            $this->puzzleWidth = 40;
            $this->puzzleHeight = 40;
        }

        $puzzleX = mt_rand(50, $this->width - $this->puzzleWidth - 50);
        $puzzleY = mt_rand(20, $this->height - $this->puzzleHeight - 20);

        // Draw gap on background
        $bg->rectangle($puzzleX, $puzzleY, $this->puzzleWidth, $this->puzzleHeight, [
            'color'  => '#00000026',
            'filled' => true,
        ]);
        $bg->rectangle($puzzleX, $puzzleY, $this->puzzleWidth, $this->puzzleHeight, [
            'color'       => '#0000004D',
            'filled'      => false,
            'strokeWidth' => 2,
        ]);

        // Create puzzle piece
        $piece = $this->imageDriver->clone();
        $piece->create($this->puzzleWidth, $this->puzzleHeight);
        $piece->rectangle(0, 0, $this->puzzleWidth, $this->puzzleHeight, [
            'color'  => '#FFFFFFE6',
            'filled' => true,
        ]);
        $piece->rectangle(0, 0, $this->puzzleWidth, $this->puzzleHeight, [
            'color'       => '#666666',
            'filled'      => false,
            'strokeWidth' => 2,
        ]);

        $this->store(['x' => $puzzleX, 'y' => $puzzleY]);

        $bgImage = $bg->output('png');
        $pzImage = $piece->output('png');

        $bg->destroy();
        $piece->destroy();

        return [
            'key'   => $this->key,
            'type'  => 'slider',
            'image' => $bgImage,
            'extra' => [
                'x'         => $puzzleX,
                'puzzle'    => $pzImage,
                'puzzle_w'  => $this->puzzleWidth,
                'puzzle_h'  => $this->puzzleHeight,
            ],
        ];
    }
}
