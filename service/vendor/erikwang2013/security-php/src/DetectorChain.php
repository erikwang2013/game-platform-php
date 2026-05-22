<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\Security;

class DetectorChain
{
    private array $detectors = [];

    public function add(DetectorInterface $detector): self
    {
        $this->detectors[] = $detector;
        return $this;
    }

    /**
     * Run all registered detectors against the data.
     * Returns all threats found (empty array = safe).
     */
    public function scan(array $data): array
    {
        $threats = [];
        foreach ($this->detectors as $detector) {
            $result = $detector->detect($data);
            if ($result !== null) {
                $threats[] = $result;
            }
        }
        return $threats;
    }

    /**
     * Get registered detector count.
     */
    public function count(): int
    {
        return count($this->detectors);
    }
}
