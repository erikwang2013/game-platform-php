<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */

namespace Erikwang2013\Poster\Poster;

use Erikwang2013\Poster\Drivers\DriverFactory;
use Erikwang2013\Poster\Drivers\ImageDriverInterface;
use Erikwang2013\Poster\PosterConfig;
use Erikwang2013\Poster\Poster\Elements\{
    TextElement, ImageElement, QrcodeElement, AvatarElement,
    ShapeElement, LineElement, WatermarkElement, TableElement,
    ChartElement, CalendarElement, ArtisticTextElement,
    EmojiElement, IconElement, EmoticonElement
};

class PosterBuilder
{
    private ImageDriverInterface $canvas;
    private int $width;
    private int $height;
    private array $elements = [];
    private ?PosterTemplate $template = null;
    private array $templateVars = [];
    private ?string $pendingBgColor = null;
    private ?string $pendingBgImage = null;
    private ?array $pendingGradient = null;
    private bool $canvasReady = false;

    public function __construct(?ImageDriverInterface $driver = null)
    {
        $this->canvas = $driver ?? DriverFactory::create();
    }

    public function width(int $w): static { $this->width = $w; return $this; }
    public function height(int $h): static { $this->height = $h; return $this; }

    public function background(string $colorOrPath): static
    {
        if (preg_match('/^#?[0-9a-fA-F]{3,8}$/', $colorOrPath)) {
            $this->pendingBgColor = $colorOrPath;
        } elseif (is_file($colorOrPath)) {
            $this->pendingBgImage = $colorOrPath;
        }
        return $this;
    }

    public function backgroundGradient(string $color1, string $color2, string $direction = 'vertical'): static
    {
        $this->pendingGradient = [$color1, $color2, $direction];
        return $this;
    }

    public function addText(string $text, array $options = []): static { $this->elements[] = new TextElement(array_merge($options, ['text'=>$text])); return $this; }
    public function addImage(string $src, array $options = []): static { $this->elements[] = new ImageElement(array_merge($options, ['src'=>$src])); return $this; }
    public function addQrcode(string $content, array $options = []): static { $this->elements[] = new QrcodeElement(array_merge($options, ['content'=>$content])); return $this; }
    public function addAvatar(string $src, array $options = []): static { $this->elements[] = new AvatarElement(array_merge($options, ['src'=>$src])); return $this; }
    public function addShape(string $shape, array $options = []): static { $this->elements[] = new ShapeElement(array_merge($options, ['shape'=>$shape])); return $this; }
    public function addLine(array $options = []): static { $this->elements[] = new LineElement($options); return $this; }
    public function addWatermark(string $text, array $options = []): static { $this->elements[] = new WatermarkElement(array_merge($options, ['text'=>$text])); return $this; }
    public function addTable(array $options = []): static { $this->elements[] = new TableElement($options); return $this; }
    public function addChart(string $type, array $data, array $options = []): static { $this->elements[] = new ChartElement(array_merge($options, ['type'=>$type, 'data'=>$data])); return $this; }
    public function addCalendar(array $options = []): static { $this->elements[] = new CalendarElement($options); return $this; }
    public function addArtisticText(string $text, string $style, array $options = []): static { $this->elements[] = new ArtisticTextElement(array_merge($options, ['text'=>$text, 'style'=>$style])); return $this; }
    public function addEmoji(string $emoji, array $options = []): static { $this->elements[] = new EmojiElement(array_merge($options, ['emoji'=>$emoji])); return $this; }
    public function addIcon(string $icon, array $options = []): static { $this->elements[] = new IconElement(array_merge($options, ['icon'=>$icon])); return $this; }
    public function addEmoticon(string $expression, array $options = []): static { $this->elements[] = new EmoticonElement(array_merge($options, ['expression'=>$expression])); return $this; }
    public function useTemplate(PosterTemplate $template): static { $this->template = $template; return $this; }
    public function with(array $variables): static { $this->templateVars = $variables; return $this; }

    public function save(string $path, int $quality = 90): bool { $this->render(); return $this->canvas->save($path, 'jpg', $quality); }
    public function output(string $format = 'jpg', int $quality = 90): string { $this->render(); return $this->canvas->output($format, $quality); }

    private function render(): void
    {
        if ($this->template !== null) {
            $this->elements = $this->template->build($this->templateVars);
            $this->width = $this->template->getWidth();
            $this->height = $this->template->getHeight();
        }
        if (!isset($this->width)) $this->width = PosterConfig::get('poster.default_width', 750);
        if (!isset($this->height)) $this->height = PosterConfig::get('poster.default_height', 1334);

        // Deferred canvas creation with resolved dimensions
        if (!$this->canvasReady) {
            if ($this->pendingGradient !== null) {
                $this->canvas->create($this->width, $this->height);
                [$c1, $c2, $dir] = $this->pendingGradient;
                $r1 = hexdec(substr($c1, 1, 2)); $g1 = hexdec(substr($c1, 3, 2)); $b1 = hexdec(substr($c1, 5, 2));
                $r2 = hexdec(substr($c2, 1, 2)); $g2 = hexdec(substr($c2, 3, 2)); $b2 = hexdec(substr($c2, 5, 2));
                $steps = $dir === 'vertical' ? $this->height : $this->width;
                for ($i = 0; $i < $steps; $i++) {
                    $ratio = $i / max($steps - 1, 1);
                    $color = sprintf('#%02X%02X%02X', intval($r1 + ($r2-$r1)*$ratio), intval($g1 + ($g2-$g1)*$ratio), intval($b1 + ($b2-$b1)*$ratio));
                    if ($dir === 'vertical') $this->canvas->line(0, $i, $this->width-1, $i, ['color'=>$color]);
                    else $this->canvas->line($i, 0, $i, $this->height-1, ['color'=>$color]);
                }
            } elseif ($this->pendingBgImage !== null) {
                $this->canvas->create($this->width, $this->height);
                $bg = DriverFactory::create()->load($this->pendingBgImage);
                $bg->resize($this->width, $this->height);
                $this->canvas->image($bg, 0, 0);
                $bg->destroy();
            } else {
                $this->canvas->create($this->width, $this->height);
                $color = $this->pendingBgColor ?? '#FFFFFF';
                $this->canvas->rectangle(0, 0, $this->width, $this->height, ['color' => $color, 'filled' => true]);
            }
            $this->canvasReady = true;
        }

        foreach ($this->elements as $element) {
            if (method_exists($element, 'resolve')) $element->resolve($this->templateVars);
            $element->render($this->canvas);
        }
    }

    public function destroy(): void { $this->canvas->destroy(); }
}
