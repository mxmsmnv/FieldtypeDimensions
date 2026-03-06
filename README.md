# FieldtypeDimensions

Stores product dimensions (L×W×H) and weight with selectable units of measurement. Designed for e-commerce and catalog sites built on ProcessWire.

---

## Features

- Four fields in one — length, width, height and weight stored as a single field value
- Flexible units — choose display units per field: mm, cm, dm, m, in, ft for length; g, kg, t, oz, lb for weight
- Normalized storage — values are always saved in mm and grams internally, so changing the display unit never corrupts existing data
- Unit conversion helpers — convert any dimension to any unit directly from the value object
- Volume calculation — `volume()` method returns L×W×H in the current length unit
- Optional weight field — can be hidden per-field if weight is not needed
- Selector engine support — find pages by dimension ranges using standard ProcessWire selectors
- Admin language — 28 languages for field labels in the page editor (configured globally in Modules → Configure)
- Clean admin UI — three dimension inputs arranged in a row with `×` separators, inline unit labels

---

## Installation

1. Download or clone the repository into your `site/modules/` directory:

```bash
cd site/modules
git clone https://github.com/mxmsmnv/FieldtypeDimensions.git
```

2. In the ProcessWire admin, go to **Modules → Refresh**, then find **FieldtypeDimensions** and click **Install**.  
   `InputfieldDimensions` is installed automatically as a dependency.

3. Create a new field and set its type to **Dimensions**.

---

## Configuration

### Module-level (Modules → Configure → FieldtypeDimensions)

| Setting | Description | Default |
|---|---|---|
| Admin language | Language for field labels in the page editor | English |

Supported languages: English, Deutsch, Français, Polski, Русский, Українська, 中文, Română, Español, Nederlands, Suomi, Svenska, Հայերեն, ქართული, Türkçe, 日本語, Afrikaans, Italiano, Čeština, Srpski, Lietuvių, Latviešu, Magyar, Ελληνικά, Български, Hrvatski, Azərbaycan, 한국어.

### Per-field (Fields → Edit → your field)

| Setting | Description | Default |
|---|---|---|
| Length unit | Unit used for entering and displaying L/W/H values | `mm` |
| Weight unit | Unit used for entering and displaying weight | `g` |
| Show weight field | Whether the weight input is shown in the editor | on |

---

## Units Reference

**Length** — `mm`, `cm`, `dm`, `m`, `in`, `ft`

**Weight** — `g`, `kg`, `t`, `oz`, `lb`

---

## Storage

Values are always persisted in **millimeters** (length/width/height) and **grams** (weight), regardless of the display unit configured on the field. Changing the unit setting on an existing field does not require data migration.

| Column | Type | Stores |
|---|---|---|
| `data` | FLOAT | Length in mm |
| `xtra1` | FLOAT | Width in mm |
| `xtra2` | FLOAT | Height in mm |
| `xtra3` | FLOAT | Weight in g |

---

## Template Usage

```php
$dim = $page->dimensions; // DimensionsValue object

// String representation
echo $dim; // → "120 × 80 × 50 mm, 1.5 kg"

// Individual values (in the unit configured on the field)
echo $dim->length;       // 120
echo $dim->width;        // 80
echo $dim->height;       // 50
echo $dim->weight;       // 1500
echo $dim->length_unit;  // "mm"
echo $dim->weight_unit;  // "g"

// On-the-fly unit conversion
echo $dim->lengthIn('cm');  // → 12.0
echo $dim->widthIn('in');   // → 3.1496...
echo $dim->weightIn('kg');  // → 1.5

// Volume in current length units
echo $dim->volume();  // → 480000 (mm³)

// Checks
if ($dim->hasDimensions()) { /* all three size fields are filled */ }
if (!$dim->isEmpty())      { /* at least one value is set */ }

// Export to array
$arr = $dim->toArray();
// ['length' => 120, 'width' => 80, 'height' => 50, 'weight' => 1500,
//  'length_unit' => 'mm', 'weight_unit' => 'g']
```

---

## Selector Engine

```php
// Products longer than 100 mm (DB values are always in mm/g)
$items = $pages->find("template=product, dimensions.length>100");

// Products heavier than 500 g
$heavy = $pages->find("template=product, dimensions.weight>500");

// Combined dimension filter
$boxes = $pages->find("template=product, dimensions.length<=300, dimensions.height<=200");
```

Supported subfields for selectors: `length`, `width`, `height`, `weight`.

---

## How It Works

The Fieldtype stores four numeric columns in a dedicated database table. On load, raw mm/gram values are converted to the field's configured display unit via a linear factor. On save, the entered values are multiplied back to mm/grams before writing to the database. All conversion factors are defined as constants in `FieldtypeDimensions` and are available statically for use in custom code.

The `DimensionsValue` object extends `WireData` and is the value returned when accessing the field on any page. It carries both the numeric values and the active unit identifiers, and exposes conversion and formatting helpers.

---

## Security Notes

- No user-supplied strings are executed or eval'd — all inputs are cast to `float` before use
- Database writes use PDO prepared statements with bound parameters
- The module does not register any hooks on the admin or frontend outside of standard Fieldtype/Inputfield lifecycle methods

---

## Author

**Maxim Semenov**  
[smnv.org](https://smnv.org) · [GitHub @mxmsmnv](https://github.com/mxmsmnv)

---

## License

MIT License. See [LICENSE](LICENSE) for details.
