<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */

namespace Erikwang2013\Poster\Captcha;

class ClickCaptcha extends AbstractCaptcha
{
    private int $targetCount = 3;
    private string $targetType = 'text';
    private array $wordPool = ['树', '鸟', '花', '草', '云', '山', '河', '海', '日', '月', '星', '风', '雨', '雪', '火'];

    public function setTargetCount(int $count): static
    {
        $this->targetCount = min(5, max(1, $count));
        return $this;
    }

    public function setTargetType(string $type): static
    {
        $this->targetType = $type;
        return $this;
    }

    protected function getType(): string
    {
        return 'click';
    }

    public function generate(): array
    {
        $this->generateKey();
        $bg = $this->createBackground();

        if ($this->difficulty === 'easy') {
            $this->targetCount = 2;
        } elseif ($this->difficulty === 'hard') {
            $this->targetCount = 4;
        }

        $targets = $this->placeTargets();
        $fontFile = dirname(__DIR__, 2) . '/assets/font.ttf';

        foreach ($targets as $target) {
            $bg->ellipse($target['x'], $target['y'], 25, 25, [
                'color'  => '#FF000033',
                'filled' => true,
            ]);
            $textY = min($target['y'] + 35, $this->height - 5);
            $bg->text($target['order'] . '.' . $target['text'], $target['x'], $textY, [
                'size'  => 14,
                'color' => '#000000',
                'font'  => is_file($fontFile) ? $fontFile : null,
                'align' => 'center',
            ]);
        }

        $this->store(['targets' => $targets]);
        $image = $bg->output('png');
        $bg->destroy();

        return [
            'key'   => $this->key,
            'image' => $image,
            'extra' => ['targets' => $targets],
        ];
    }

    private function placeTargets(): array
    {
        $targets = [];
        $margin = 40;
        $usedAreas = [];

        for ($i = 0; $i < $this->targetCount; $i++) {
            $attempts = 0;
            do {
                $x = mt_rand($margin, $this->width - $margin);
                $y = mt_rand($margin, $this->height - $margin);
                $attempts++;
            } while ($this->overlaps($x, $y, $usedAreas, 30) && $attempts < 50);

            $word = $this->wordPool[array_rand($this->wordPool)];
            $targets[] = ['x' => $x, 'y' => $y, 'text' => $word, 'order' => $i + 1];
            $usedAreas[] = ['x' => $x, 'y' => $y];
        }

        return $targets;
    }

    private function overlaps(int $x, int $y, array $areas, int $minDist): bool
    {
        foreach ($areas as $area) {
            $dx = $x - $area['x'];
            $dy = $y - $area['y'];
            if (sqrt($dx * $dx + $dy * $dy) < $minDist) {
                return true;
            }
        }
        return false;
    }
}
