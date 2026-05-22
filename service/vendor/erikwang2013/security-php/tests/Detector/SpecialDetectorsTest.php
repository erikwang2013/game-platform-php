<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\Security\Tests\Detector;

use Erikwang2013\Security\Detector\UploadDetector;
use Erikwang2013\Security\Detector\JwtAttackDetector;
use Erikwang2013\Security\Detector\DataLeakDetector;
use Erikwang2013\Security\Detector\PrototypePollutionDetector;
use PHPUnit\Framework\TestCase;

class SpecialDetectorsTest extends TestCase
{
    // UploadDetector
    public function testUploadBlocksPhpExtension(): void
    {
        $detector = new UploadDetector();
        $result = $detector->detect([
            'file' => ['name' => 'shell.php', 'tmp_name' => '/tmp/phpXXX'],
        ]);
        $this->assertNotNull($result);
        $this->assertSame('upload', $result->type);
    }

    public function testUploadBlocksPhpContent(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'test_');
        file_put_contents($tmp, '<?php system($_GET["cmd"]); ?>');

        $detector = new UploadDetector();
        $result = $detector->detect([
            'file' => ['name' => 'image.jpg', 'tmp_name' => $tmp],
        ]);
        unlink($tmp);

        $this->assertNotNull($result);
        $this->assertSame('critical', $result->severity);
    }

    public function testUploadAllowsSafeImage(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'test_');
        file_put_contents($tmp, 'fake image data');

        $detector = new UploadDetector();
        $result = $detector->detect([
            'file' => ['name' => 'photo.jpg', 'tmp_name' => $tmp],
        ]);
        unlink($tmp);

        $this->assertNull($result, 'Safe jpg upload should pass');
    }

    public function testUploadSkipsNonFileArrays(): void
    {
        $detector = new UploadDetector();
        $result = $detector->detect(['data' => 'just a string']);
        $this->assertNull($result);
    }

    // JwtAttackDetector
    public function testJwtAlgNoneDetected(): void
    {
        $detector = new JwtAttackDetector();
        // header: {"alg":"none"} → base64url: eyJhbGciOiJub25lIn0
        $result = $detector->detect(['token' => 'eyJhbGciOiJub25lIn0.eyJhZG1pbiI6dHJ1ZX0.']);
        $this->assertNotNull($result);
    }

    public function testJwtWithHmacKidPathTraversal(): void
    {
        $detector = new JwtAttackDetector();
        // header: {"alg":"HS256","kid":"../../etc/passwd"} → base64url
        $result = $detector->detect(['token' => 'eyJhbGciOiJIUzI1NiIsImtpZCI6Ii4uLy4uL2V0Yy9wYXNzd2QifQ.eyJ1c2VyIjoiYWRtaW4ifQ.sig']);
        $this->assertNotNull($result);
    }

    public function testJwtNormalTokenNotDetected(): void
    {
        $detector = new JwtAttackDetector();
        $result = $detector->detect(['token' => 'eyJhbGciOiJSUzI1NiJ9.eyJ1c2VyIjoiam9obiJ9.sig123']);
        $this->assertNull($result, 'Normal RS256 JWT should not trigger');
    }

    // DataLeakDetector
    public function testDataLeakMasksPayload(): void
    {
        $detector = new DataLeakDetector();
        $result = $detector->detect(['x' => 'AKIAIOSFODNN7EXAMPLE']);
        $this->assertNotNull($result);
        $this->assertStringContainsString('***', $result->payload, 'Payload should be masked');
        $this->assertStringNotContainsString('AKIA', $result->payload, 'Real key should not appear in masked payload');
    }

    // PrototypePollutionDetector
    public function testProtoPollutionDirectKey(): void
    {
        $detector = new PrototypePollutionDetector();
        $result = $detector->detect(['__proto__' => ['isAdmin' => true]]);
        $this->assertNotNull($result);
        $this->assertSame('prototype_pollution', $result->type);
    }

    public function testProtoPollutionConstructorKey(): void
    {
        $detector = new PrototypePollutionDetector();
        $result = $detector->detect(['constructor' => ['prototype' => ['isAdmin' => true]]]);
        $this->assertNotNull($result);
    }

    public function testProtoPollutionInString(): void
    {
        $detector = new PrototypePollutionDetector();
        $result = $detector->detect(['data' => 'obj["__proto__"]["isAdmin"] = true']);
        $this->assertNotNull($result);
    }

    public function testProtoPollutionNormalKeysPass(): void
    {
        $detector = new PrototypePollutionDetector();
        $result = $detector->detect(['name' => 'John', 'proto' => 'test']);
        $this->assertNull($result);
    }
}
