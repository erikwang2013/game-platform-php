<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;

final class EventBusMessageFormatTest extends TestCase
{
    public function testEmitMessageShape(): void
    {
        $message = json_encode([
            'event' => 'game.played',
            'payload' => ['user_id' => 1, 'game_id' => 2],
            'timestamp' => 1730000000,
        ], JSON_UNESCAPED_UNICODE);

        $decoded = json_decode((string) $message, true);
        $this->assertIsArray($decoded);
        $this->assertSame('game.played', $decoded['event']);
        $this->assertSame(1, $decoded['payload']['user_id']);
        $this->assertSame(1730000000, $decoded['timestamp']);
    }
}
