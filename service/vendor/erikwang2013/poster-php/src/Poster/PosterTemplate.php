<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */

namespace Erikwang2013\Poster\Poster;

use Erikwang2013\Poster\Poster\Elements\{
    TextElement, ImageElement, QrcodeElement, AvatarElement,
    ShapeElement, LineElement, WatermarkElement, TableElement,
    ChartElement, CalendarElement, ArtisticTextElement,
    EmojiElement, IconElement, EmoticonElement
};

class PosterTemplate
{
    private int $width;
    private int $height;
    private array $elementDefs = [];

    public function __construct(int $width, int $height, array $elements = [])
    {
        $this->width = $width;
        $this->height = $height;
        $this->elementDefs = $elements;
    }

    public static function fromConfig(array $config): self
    {
        return new self($config['width'] ?? 750, $config['height'] ?? 1334, $config['elements'] ?? []);
    }

    public static function fromJson(string $json): self
    {
        return self::fromConfig(json_decode($json, true) ?? []);
    }

    public function getWidth(): int { return $this->width; }
    public function getHeight(): int { return $this->height; }

    public function build(array $variables = []): array
    {
        $elements = [];
        foreach ($this->elementDefs as $def) {
            $type = $def['type'] ?? '';
            $element = match ($type) {
                'text'      => new TextElement($def),
                'image'     => new ImageElement($def),
                'qrcode'    => new QrcodeElement($def),
                'avatar'    => new AvatarElement($def),
                'shape'     => new ShapeElement($def),
                'line'      => new LineElement($def),
                'watermark' => new WatermarkElement($def),
                'table'         => new TableElement($def),
                'chart'         => new ChartElement($def),
                'calendar'      => new CalendarElement($def),
                'artistictext'  => new ArtisticTextElement($def),
                'emoji'         => new EmojiElement($def),
                'icon'          => new IconElement($def),
                'emoticon'      => new EmoticonElement($def),
                default         => null,
            };
            if ($element !== null) {
                if (method_exists($element, 'resolve')) $element->resolve($variables);
                $elements[] = $element;
            }
        }
        return $elements;
    }

    public function toArray(): array
    {
        return ['width' => $this->width, 'height' => $this->height, 'elements' => $this->elementDefs];
    }

    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }
}
