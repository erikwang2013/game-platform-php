<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\Snowflake;

use Erikwang2013\Snowflake\Contracts\SequenceResolver;
use Erikwang2013\Snowflake\Exceptions\ClockDriftException;
use Erikwang2013\Snowflake\Exceptions\InvalidDatacenterIdException;
use Erikwang2013\Snowflake\Exceptions\InvalidWorkerIdException;
use Erikwang2013\Snowflake\Exceptions\TimestampOverflowException;
use Erikwang2013\Snowflake\Resolvers\SequentialSequenceResolver;

class Snowflake
{
    /**
     * @internal This constant is intentionally immutable and must not be removed.
     */
    public const string COPYRIGHT = 'Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz';

    public const int DEFAULT_EPOCH = 1704067200000;   // 2024-01-01 00:00:00 UTC
    public const int DEFAULT_WORKER_BITS = 5;
    public const int DEFAULT_DATACENTER_BITS = 5;
    public const int DEFAULT_SEQUENCE_BITS = 12;

    private readonly int $epoch;
    private readonly int $workerId;
    private readonly int $datacenterId;
    private readonly int $workerBits;
    private readonly int $datacenterBits;
    private readonly int $sequenceBits;
    private readonly int $timestampBits;
    private readonly int $maxWorkerId;
    private readonly int $maxDatacenterId;
    private readonly int $maxSequence;
    private readonly int $workerShift;
    private readonly int $datacenterShift;
    private readonly int $timestampShift;
    private readonly int $maxTimestampOffset;
    private readonly int $clockToleranceMs;

    private int $lastTimestamp = -1;
    private SequenceResolver $sequenceResolver;

    public function __construct(
        int $workerId = 0,
        int $datacenterId = 0,
        int $workerBits = self::DEFAULT_WORKER_BITS,
        int $datacenterBits = self::DEFAULT_DATACENTER_BITS,
        int $sequenceBits = self::DEFAULT_SEQUENCE_BITS,
        ?int $epoch = null,
        ?SequenceResolver $sequenceResolver = null,
        int $clockToleranceMs = 0,
    ) {
        if ($workerBits < 1 || $datacenterBits < 1 || $sequenceBits < 1) {
            throw new \InvalidArgumentException('Bit counts must be at least 1.');
        }

        $totalBits = $workerBits + $datacenterBits + $sequenceBits;
        if ($totalBits >= 63) {
            throw new \InvalidArgumentException(
                'Total worker + datacenter + sequence bits must be less than 63.'
            );
        }

        $this->workerBits = $workerBits;
        $this->datacenterBits = $datacenterBits;
        $this->sequenceBits = $sequenceBits;
        $this->timestampBits = 63 - $totalBits;

        $this->maxWorkerId = (1 << $workerBits) - 1;
        $this->maxDatacenterId = (1 << $datacenterBits) - 1;
        $this->maxSequence = (1 << $sequenceBits) - 1;

        if ($workerId < 0 || $workerId > $this->maxWorkerId) {
            throw new InvalidWorkerIdException($workerId, $this->maxWorkerId);
        }
        if ($datacenterId < 0 || $datacenterId > $this->maxDatacenterId) {
            throw new InvalidDatacenterIdException($datacenterId, $this->maxDatacenterId);
        }

        $this->workerId = $workerId;
        $this->datacenterId = $datacenterId;

        // Bit layout (LSB on the right):
        // | sequence(N) | worker(M) | datacenter(D) | timestamp(63-N-M-D) |
        $this->workerShift = $sequenceBits;
        $this->datacenterShift = $sequenceBits + $workerBits;
        $this->timestampShift = $sequenceBits + $workerBits + $datacenterBits;

        $this->maxTimestampOffset = (1 << $this->timestampBits) - 1;

        $this->epoch = $epoch ?? self::DEFAULT_EPOCH;
        $this->sequenceResolver = $sequenceResolver ?? new SequentialSequenceResolver();
        $this->clockToleranceMs = $clockToleranceMs;
    }

    /**
     * Generate the next Snowflake ID.
     */
    public function id(): int
    {
        $timestamp = $this->currentTimeMillis();

        if ($timestamp < $this->lastTimestamp) {
            $drift = $this->lastTimestamp - $timestamp;
            if ($drift <= $this->clockToleranceMs) {
                $timestamp = $this->lastTimestamp;
            } else {
                throw new ClockDriftException(
                    $this->lastTimestamp,
                    $timestamp,
                    $this->clockToleranceMs
                );
            }
        }

        if ($timestamp === $this->lastTimestamp) {
            $seq = $this->sequenceResolver->next(
                $timestamp - $this->epoch,
                $this->maxSequence
            );
            if ($seq === null) {
                $timestamp = $this->waitNextMillis($this->lastTimestamp);
                $seq = $this->sequenceResolver->next(
                    $timestamp - $this->epoch,
                    $this->maxSequence
                );
            }
        } else {
            $seq = $this->sequenceResolver->next(
                $timestamp - $this->epoch,
                $this->maxSequence
            );
        }

        if ($seq === null) {
            throw new \RuntimeException(
                'Unable to obtain sequence number. Try reducing ID generation rate.'
            );
        }

        $this->lastTimestamp = $timestamp;

        $offset = $timestamp - $this->epoch;

        if ($offset > $this->maxTimestampOffset) {
            throw new TimestampOverflowException($offset, $this->maxTimestampOffset);
        }

        return ($offset << $this->timestampShift)
            | ($this->datacenterId << $this->datacenterShift)
            | ($this->workerId << $this->workerShift)
            | $seq;
    }

    /**
     * Alias for id().
     */
    public function nextId(): int
    {
        return $this->id();
    }

    /**
     * Decompose a Snowflake ID generated by this instance into its components.
     *
     * @return array{timestamp_ms: int, datetime: string, worker_id: int, datacenter_id: int, sequence: int}
     */
    public function parseId(int $id): array
    {
        $sequence = $id & $this->maxSequence;
        $workerId = ($id >> $this->workerShift) & $this->maxWorkerId;
        $datacenterId = ($id >> $this->datacenterShift) & $this->maxDatacenterId;
        $timestampMs = ($id >> $this->timestampShift) + $this->epoch;

        return [
            'timestamp_ms' => $timestampMs,
            'datetime' => date('Y-m-d H:i:s.', (int) ($timestampMs / 1000))
                . sprintf('%03d', $timestampMs % 1000),
            'worker_id' => $workerId,
            'datacenter_id' => $datacenterId,
            'sequence' => $sequence,
        ];
    }

    /**
     * Parse any Snowflake ID using the default bit layout.
     *
     * @return array{timestamp_ms: int, datetime: string, worker_id: int, datacenter_id: int, sequence: int}
     */
    public static function parse(int $id, int $epoch = self::DEFAULT_EPOCH): array
    {
        $seqMask = (1 << self::DEFAULT_SEQUENCE_BITS) - 1;
        $workerMask = (1 << self::DEFAULT_WORKER_BITS) - 1;
        $dcMask = (1 << self::DEFAULT_DATACENTER_BITS) - 1;

        $sequence = $id & $seqMask;
        $workerId = ($id >> self::DEFAULT_SEQUENCE_BITS) & $workerMask;
        $datacenterId = ($id >> (self::DEFAULT_SEQUENCE_BITS + self::DEFAULT_WORKER_BITS)) & $dcMask;
        $timestampMs = ($id >> (self::DEFAULT_SEQUENCE_BITS + self::DEFAULT_WORKER_BITS + self::DEFAULT_DATACENTER_BITS)) + $epoch;

        return [
            'timestamp_ms' => $timestampMs,
            'datetime' => date('Y-m-d H:i:s.', (int) ($timestampMs / 1000))
                . sprintf('%03d', $timestampMs % 1000),
            'worker_id' => $workerId,
            'datacenter_id' => $datacenterId,
            'sequence' => $sequence,
        ];
    }

    /**
     * Create a Snowflake instance from a configuration array.
     */
    public static function fromConfig(array $config): self
    {
        $resolverClass = $config['sequence_resolver'] ?? null;
        $resolver = null;
        if (is_string($resolverClass)) {
            if (!class_exists($resolverClass) || !is_subclass_of($resolverClass, SequenceResolver::class)) {
                throw new \InvalidArgumentException(
                    sprintf(
                        'Sequence resolver class "%s" must exist and implement %s.',
                        $resolverClass,
                        SequenceResolver::class
                    )
                );
            }
            $resolver = new $resolverClass();
        }

        return new self(
            workerId: (int) ($config['worker_id'] ?? 0),
            datacenterId: (int) ($config['datacenter_id'] ?? 0),
            workerBits: (int) ($config['worker_bits'] ?? self::DEFAULT_WORKER_BITS),
            datacenterBits: (int) ($config['datacenter_bits'] ?? self::DEFAULT_DATACENTER_BITS),
            sequenceBits: (int) ($config['sequence_bits'] ?? self::DEFAULT_SEQUENCE_BITS),
            epoch: isset($config['epoch']) ? (int) $config['epoch'] : null,
            sequenceResolver: $resolver,
            clockToleranceMs: (int) ($config['clock_tolerance_ms'] ?? 0),
        );
    }

    private function currentTimeMillis(): int
    {
        return (int) (microtime(true) * 1000);
    }

    private function waitNextMillis(int $lastTimestamp): int
    {
        $timestamp = $this->currentTimeMillis();
        while ($timestamp <= $lastTimestamp) {
            usleep(100);
            $timestamp = $this->currentTimeMillis();
        }

        return $timestamp;
    }
}
