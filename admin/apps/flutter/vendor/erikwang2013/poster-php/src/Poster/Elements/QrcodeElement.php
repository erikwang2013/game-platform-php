<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */

namespace Erikwang2013\Poster\Poster\Elements;

use Erikwang2013\Poster\Drivers\DriverFactory;
use Erikwang2013\Poster\Drivers\GdDriver;
use Erikwang2013\Poster\Drivers\ImageDriverInterface;
use Erikwang2013\Poster\Qrcode\QrcodeGenerator;

class QrcodeElement extends AbstractElement
{
    public function render(ImageDriverInterface $canvas): void
    {
        $content = $this->options['content'] ?? '';
        if (empty($content)) return;

        $size  = intval($this->options['size'] ?? 200);
        $level = $this->options['level'] ?? 'H';
        $x = intval($this->options['x'] ?? 0);
        $y = intval($this->options['y'] ?? 0);

        $generator = new QrcodeGenerator();
        $generator->setText($content)->setSize($size)->setErrorLevel($level);
        $qrGd = $generator->render();

        $qrDriver = new GdDriver();
        $qrDriver->setGdResource($qrGd);

        if (!empty($this->options['logo']) && is_file($this->options['logo'])) {
            $logo = DriverFactory::create()->load($this->options['logo']);
            $logoSize = intval($size * 0.22);
            $logo->resize($logoSize, $logoSize);
            $logoX = intval(($size - $logoSize) / 2);
            $logoY = intval(($size - $logoSize) / 2);
            $qrDriver->image($logo, $logoX, $logoY);
            $logo->destroy();
        }

        $canvas->image($qrDriver, $x, $y, $this->options);
        $qrDriver->destroy();

        if (!empty($this->options['label'])) {
            $canvas->text($this->options['label'], $x, $y + $size + 20, [
                'size'  => intval($this->options['label_size'] ?? 14),
                'color' => $this->options['label_color'] ?? '#999999',
                'font'  => $this->options['font'] ?? null,
                'align' => 'center',
            ]);
        }
    }

    public function resolve(array $variables): static
    {
        if (isset($this->options['content'])) {
            $this->options['content'] = $this->resolvePlaceholders($this->options['content'], $variables);
        }
        return $this;
    }
}
