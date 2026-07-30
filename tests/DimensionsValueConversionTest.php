<?php
namespace ProcessWire;

class WireData {
    private array $data = [];
    public function __construct() {}
    public function set(string $key, mixed $value): void { $this->data[$key] = $value; }
    public function get(string $key): mixed { return $this->data[$key] ?? null; }
}

class FieldtypeDimensions {
    public const LENGTH_UNITS = [
        'mm' => ['factor' => 1.0], 'cm' => ['factor' => 10.0],
        'm' => ['factor' => 1000.0], 'in' => ['factor' => 25.4], 'ft' => ['factor' => 304.8],
    ];
    public const WEIGHT_UNITS = [
        'g' => ['factor' => 1.0], 'kg' => ['factor' => 1000.0],
        'oz' => ['factor' => 28.349523125], 'lb' => ['factor' => 453.59237],
    ];
}

require dirname(__DIR__) . '/DimensionsValue.php';

function check(bool $condition, string $message): void {
    if (!$condition) throw new \RuntimeException($message);
}

$metric = new DimensionsValue();
$metric->length = 12.5;
$metric->length_unit = 'cm';
$metric->weight = 2.0;
$metric->weight_unit = 'kg';
check(abs($metric->lengthIn('mm') - 125.0) < 0.000001, 'Metric length conversion failed.');
check(abs($metric->weightIn('g') - 2000.0) < 0.000001, 'Metric weight conversion failed.');

$imperial = new DimensionsValue();
$imperial->length = 12.0;
$imperial->length_unit = 'in';
$imperial->weight = 2.0;
$imperial->weight_unit = 'lb';
check(abs($imperial->lengthIn('cm') - 30.48) < 0.000001, 'Imperial length conversion failed.');
check(abs($imperial->weightIn('kg') - 0.907185) < 0.000001, 'Imperial weight conversion failed.');

echo "DimensionsValue conversion tests passed.\n";
