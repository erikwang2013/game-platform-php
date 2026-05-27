<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */


namespace Erikwang2013\ClickHouse\Schema;

class Blueprint
{
    public array $columns = [];
    private ?string $engine = null;
    private ?string $partitionBy = null;
    private array $orderBy = [];
    private ?string $primaryKey = null;
    private ?string $sampleBy = null;
    private ?string $ttl = null;
    private array $settings = [];

    public function string(string $name): Column { return $this->addColumn($name, 'String'); }
    public function fixedString(string $name, int $length): Column { return $this->addColumn($name, "FixedString($length)"); }
    public function int8(string $name): Column { return $this->addColumn($name, 'Int8'); }
    public function int16(string $name): Column { return $this->addColumn($name, 'Int16'); }
    public function int32(string $name): Column { return $this->addColumn($name, 'Int32'); }
    public function int64(string $name): Column { return $this->addColumn($name, 'Int64'); }
    public function uint8(string $name): Column { return $this->addColumn($name, 'UInt8'); }
    public function uint16(string $name): Column { return $this->addColumn($name, 'UInt16'); }
    public function uint32(string $name): Column { return $this->addColumn($name, 'UInt32'); }
    public function uint64(string $name): Column { return $this->addColumn($name, 'UInt64'); }
    public function float32(string $name): Column { return $this->addColumn($name, 'Float32'); }
    public function float64(string $name): Column { return $this->addColumn($name, 'Float64'); }
    public function decimal(string $name, int $precision, int $scale): Column { return $this->addColumn($name, "Decimal($precision, $scale)"); }
    public function date(string $name): Column { return $this->addColumn($name, 'Date'); }
    public function dateTime(string $name): Column { return $this->addColumn($name, 'DateTime'); }
    public function dateTime64(string $name, int $precision = 3): Column { return $this->addColumn($name, "DateTime64($precision)"); }
    public function uuid(string $name): Column { return $this->addColumn($name, 'UUID'); }
    public function bool(string $name): Column { return $this->addColumn($name, 'Bool'); }
    public function array(string $name, string $type): Column { return $this->addColumn($name, "Array($type)"); }
    public function nullable(string $name, string $type): Column { return $this->addColumn($name, "Nullable($type)"); }
    public function lowCardinality(string $name, string $type): Column { return $this->addColumn($name, "LowCardinality($type)"); }

    public function engine(string $engine): static { $this->engine = $engine; return $this; }
    public function partitionBy(string $expression): static { $this->partitionBy = $expression; return $this; }
    public function orderBy(array $columns): static { $this->orderBy = $columns; return $this; }
    public function primaryKey(string $expression): static { $this->primaryKey = $expression; return $this; }
    public function sampleBy(string $expression): static { $this->sampleBy = $expression; return $this; }
    public function ttl(string $expression): static { $this->ttl = $expression; return $this; }
    public function settings(array $settings): static { $this->settings = $settings; return $this; }

    public function getEngine(): ?string { return $this->engine; }
    public function getPartitionBy(): ?string { return $this->partitionBy; }
    public function getOrderBy(): array { return $this->orderBy; }
    public function getPrimaryKey(): ?string { return $this->primaryKey; }
    public function getSampleBy(): ?string { return $this->sampleBy; }
    public function getTtl(): ?string { return $this->ttl; }
    public function getSettings(): array { return $this->settings; }

    private function addColumn(string $name, string $type, array $modifiers = []): Column
    {
        $column = new Column($name, $type, $modifiers);
        $this->columns[] = $column;
        return $column;
    }
}