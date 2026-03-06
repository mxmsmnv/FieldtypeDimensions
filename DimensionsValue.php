<?php namespace ProcessWire;

/**
 * DimensionsValue — value object for FieldtypeDimensions.
 *
 * Properties:
 *   float|null $length      — length
 *   float|null $width       — width
 *   float|null $height      — height
 *   float|null $weight      — weight
 *   string     $length_unit — length unit (mm, cm, dm, m, in, ft)
 *   string     $weight_unit — weight unit (g, kg, t, oz, lb)
 */
class DimensionsValue extends WireData {

    public function __construct() {
        parent::__construct();
        $this->set('length',      null);
        $this->set('width',       null);
        $this->set('height',      null);
        $this->set('weight',      null);
        $this->set('length_unit', 'mm');
        $this->set('weight_unit', 'g');
    }

    // ── Magic ─────────────────────────────────────────────────────────────────

    public function __get($key) {
        return $this->get($key);
    }

    public function __set($key, $value) {
        $this->set($key, $value);
    }

    // ── Checks ────────────────────────────────────────────────────────────────

    /** Returns true when all three size values are filled. */
    public function hasDimensions(): bool {
        return $this->length !== null && $this->width !== null && $this->height !== null;
    }

    /** Returns true when no value has been set. */
    public function isEmpty(): bool {
        return $this->length === null
            && $this->width  === null
            && $this->height === null
            && $this->weight === null;
    }

    // ── Volume ────────────────────────────────────────────────────────────────

    /**
     * Calculate volume in the current length unit.
     * @return float|null
     */
    public function volume(): ?float {
        if (!$this->hasDimensions()) return null;
        return round($this->length * $this->width * $this->height, 6);
    }

    // ── Conversion ────────────────────────────────────────────────────────────

    /**
     * Return length converted to the given unit.
     * @param  string $toUnit  Target unit (mm, cm, m, in, ft, …)
     * @return float|null
     */
    public function lengthIn(string $toUnit): ?float {
        return $this->convertLength($this->length, $this->length_unit, $toUnit);
    }

    public function widthIn(string $toUnit): ?float {
        return $this->convertLength($this->width, $this->length_unit, $toUnit);
    }

    public function heightIn(string $toUnit): ?float {
        return $this->convertLength($this->height, $this->length_unit, $toUnit);
    }

    public function weightIn(string $toUnit): ?float {
        return $this->convertWeight($this->weight, $this->weight_unit, $toUnit);
    }

    private function convertLength(?float $val, string $from, string $to): ?float {
        if ($val === null) return null;
        $units  = FieldtypeDimensions::LENGTH_UNITS;
        $fromF  = $units[$from]['factor'] ?? 1.0;
        $toF    = $units[$to]['factor']   ?? 1.0;
        return round($val * $fromF / $toF, 6);
    }

    private function convertWeight(?float $val, string $from, string $to): ?float {
        if ($val === null) return null;
        $units  = FieldtypeDimensions::WEIGHT_UNITS;
        $fromF  = $units[$from]['factor'] ?? 1.0;
        $toF    = $units[$to]['factor']   ?? 1.0;
        return round($val * $fromF / $toF, 6);
    }

    // ── String representation ─────────────────────────────────────────────────

    /**
     * Returns a human-readable string such as "120 × 80 × 50 mm, 1.5 kg".
     */
    public function __toString(): string {
        $lu    = $this->length_unit;
        $wu    = $this->weight_unit;
        $lLabel = FieldtypeDimensions::LENGTH_UNITS[$lu]['label'] ?? $lu;
        $wLabel = FieldtypeDimensions::WEIGHT_UNITS[$wu]['label'] ?? $wu;

        $parts = [];
        if ($this->hasDimensions()) {
            $parts[] = "{$this->length} × {$this->width} × {$this->height} {$lLabel}";
        } elseif ($this->length !== null || $this->width !== null || $this->height !== null) {
            $dims = array_filter([
                $this->length !== null ? "{$this->length}" : '—',
                $this->width  !== null ? "{$this->width}"  : '—',
                $this->height !== null ? "{$this->height}" : '—',
            ]);
            $parts[] = implode(' × ', $dims) . " {$lLabel}";
        }
        if ($this->weight !== null) {
            $parts[] = "{$this->weight} {$wLabel}";
        }

        return implode(', ', $parts);
    }

    // ── Export ────────────────────────────────────────────────────────────────

    public function toArray(): array {
        return [
            'length'      => $this->length,
            'width'       => $this->width,
            'height'      => $this->height,
            'weight'      => $this->weight,
            'length_unit' => $this->length_unit,
            'weight_unit' => $this->weight_unit,
        ];
    }
}