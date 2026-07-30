# Changelog

All notable changes to FieldtypeDimensions are documented here.

---

## [1.0.4] — 2026-07-30

### Fixed
- Restored the configured length and weight unit identifiers on values loaded from the database, so `lengthIn()`, `weightIn()`, `toArray()`, and integrations perform correct metric and imperial conversions.

---

## [1.0.3] — 2026-03-05

Initial release.

### Added
- `FieldtypeDimensions` Fieldtype — stores length, width, height and weight as a single composite field
- `InputfieldDimensions` Inputfield — three dimension inputs arranged in a row with `×` separators; weight input below
- `DimensionsValue` value object extending `WireData` — returned on page field access, carries values and active unit identifiers
- Normalized storage — all values written to the database in **millimeters** and **grams** regardless of the display unit; changing the unit setting requires no data migration
- Six length units with linear conversion factors: `mm`, `cm`, `dm`, `m`, `in`, `ft`
- Five weight units with linear conversion factors: `g`, `kg`, `t`, `oz`, `lb`
- Per-field unit configuration — **Length unit** and **Weight unit** selects in the field config panel
- **Show weight field** toggle — hides the weight input when weight is not relevant for the field
- `DimensionsValue::lengthIn()`, `widthIn()`, `heightIn()`, `weightIn()` — on-the-fly unit conversion helpers
- `DimensionsValue::volume()` — returns L×W×H product in the current length unit
- `DimensionsValue::hasDimensions()` — returns `true` when all three size values are filled
- `DimensionsValue::isEmpty()` — returns `true` when no value has been set
- `DimensionsValue::toArray()` — exports all values and unit identifiers as a plain array
- `DimensionsValue::__toString()` — produces a human-readable string such as `120 × 80 × 50 mm, 1.5 kg`
- Selector engine support via `getMatchQuery()` — pages can be found using `dimensions.length>100` or `dimensions.weight<=500` selectors
- PDO prepared statements with bound parameters for all database writes
- Module-level **Admin language** setting — 28 languages for field labels in the page editor

---

*Maintained by [Maxim Semenov](https://smnv.org) · [github.com/mxmsmnv/FieldtypeDimensions](https://github.com/mxmsmnv/FieldtypeDimensions)*
