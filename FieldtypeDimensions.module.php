<?php namespace ProcessWire;

require_once __DIR__ . '/DimensionsValue.php';

/**
 * FieldtypeDimensions — Product dimensions (L × W × H + Weight) with selectable units
 *
 * @author  Maxim Semenov <maxim@smnv.org> (smnv.org)
 * @version 1.0.0
 * @license MIT
 *
 * ProcessWire Fieldtype for storing product dimensions: length, width, height, weight.
 * Values are always persisted in mm and grams; display units are configured per field.
 */
class FieldtypeDimensions extends Fieldtype implements ConfigurableModule {

    public static function getModuleInfo(): array {
        return [
            'title'        => 'Dimensions',
            'summary'      => 'Stores product dimensions (L×W×H) and weight with selectable units of measurement.',
            'version'      => '1.0.3',
            'author'       => 'Maxim Semenov',
            'href'     => 'https://smnv.org',
            'requires'     => 'ProcessWire>=3.0.0',
            'installs'     => 'InputfieldDimensions',
            'configurable' => 3,
        ];
    }

    /** Default module-level config values */
    public static function getDefaultConfig(): array {
        return ['language' => 'en'];
    }

    /** Renders the module config inputfields in Modules > Configure */
    public static function getModuleConfigInputfields(array $data): InputfieldWrapper {
        $data    = array_merge(self::getDefaultConfig(), $data);
        $modules = wire('modules');
        $wrapper = new InputfieldWrapper();

        /** @var InputfieldSelect $f */
        $f = $modules->get('InputfieldSelect');
        $f->attr('name', 'language');
        $f->label       = 'Admin language';
        $f->description = 'Language used for field labels (Length, Width, Height, Weight) in the page editor.';
        $options = [
            'en' => 'English',        'de' => 'Deutsch',         'fr' => 'Français',
            'pl' => 'Polski',         'ru' => 'Русский',
            'uk' => 'Українська',
            'zh' => '中文',   'ro' => 'Română', 'es' => 'Español',
            'nl' => 'Nederlands',     'fi' => 'Suomi',            'sv' => 'Svenska',
            'hy' => 'Հայերևն',
            'ka' => 'ქართული',
            'tr' => 'Türkçe', 'ja' => '日本語',
            'af' => 'Afrikaans',      'it' => 'Italiano',         'cs' => 'Čeština',
            'sr' => 'Srpski',         'lt' => 'Lietuvių',    'lv' => 'Latviešu',
            'hu' => 'Magyar',         'el' => 'Ελληνικά',
            'bg' => 'Български',
            'hr' => 'Hrvatski',       'az' => 'Azərbaycan',  'ko' => '한국어',
        ];
        foreach ($options as $code => $label) {
            $f->addOption($code, $label);
        }
        $f->attr('value', $data['language']);
        $wrapper->add($f);

        return $wrapper;
    }

        // ── Length units: conversion factor to millimeters ───────────────────────
    const LENGTH_UNITS = [
        'mm' => ['label' => 'mm', 'factor' => 1.0],
        'cm' => ['label' => 'cm', 'factor' => 10.0],
        'dm' => ['label' => 'dm', 'factor' => 100.0],
        'm'  => ['label' => 'm',  'factor' => 1000.0],
        'in' => ['label' => 'in', 'factor' => 25.4],
        'ft' => ['label' => 'ft', 'factor' => 304.8],
    ];

    // ── Weight units: conversion factor to grams ──────────────────────────────
    const WEIGHT_UNITS = [
        'g'  => ['label' => 'g',  'factor' => 1.0],
        'kg' => ['label' => 'kg', 'factor' => 1000.0],
        't'  => ['label' => 't',  'factor' => 1_000_000.0],
        'oz' => ['label' => 'oz', 'factor' => 28.3495],
        'lb' => ['label' => 'lb', 'factor' => 453.592],
    ];

    public function init() {
        // Hook into Pages::save to force-save our field directly from POST data,
        // bypassing PW's change-tracking which doesn't work for WireData objects.
        $this->addHookBefore('Pages::save', $this, 'hookSaveFromPost');
    }

    public function hookSaveFromPost(HookEvent $event) {
        /** @var Page $page */
        $page = $event->arguments(0);
        if (!$page instanceof Page) return;

        $input = $this->wire('input');
        if (!$input || !$input->requestMethod('POST')) return;

        foreach ($page->template->fieldgroup as $field) {
            if (!$field->type instanceof FieldtypeDimensions) continue;

            $name = $field->name;
            $lu   = $field->get('length_unit') ?: 'mm';
            $wu   = $field->get('weight_unit') ?: 'g';

            $rawLength = $input->post("{$name}_length");
            $rawWidth  = $input->post("{$name}_width");
            $rawHeight = $input->post("{$name}_height");
            $rawWeight = $input->post("{$name}_weight");

            if ($rawLength === null && $rawWidth === null && $rawHeight === null) continue;

            $dim = new DimensionsValue();
            $dim->length_unit = $lu;
            $dim->weight_unit = $wu;

            foreach (['length' => $rawLength, 'width' => $rawWidth, 'height' => $rawHeight, 'weight' => $rawWeight] as $k => $v) {
                if ($v !== null && $v !== '') {
                    $v = str_replace(',', '.', trim((string)$v));
                    if (is_numeric($v)) $dim->$k = (float)$v;
                }
            }

            $data  = $this->sleepValueForField($field, $dim);
            $table = $field->getTable();
            $sql   = "INSERT INTO `{$table}` (pages_id, data, width, height, weight)
                      VALUES(:pid, :length, :width, :height, :weight)
                      ON DUPLICATE KEY UPDATE
                        data=VALUES(data), width=VALUES(width),
                        height=VALUES(height), weight=VALUES(weight)";

            $stmt = $this->wire('database')->prepare($sql);
            $stmt->bindValue(':pid',    $page->id,      \PDO::PARAM_INT);
            $stmt->bindValue(':length', $data['length'], $data['length'] === null ? \PDO::PARAM_NULL : \PDO::PARAM_STR);
            $stmt->bindValue(':width',  $data['width'],  $data['width']  === null ? \PDO::PARAM_NULL : \PDO::PARAM_STR);
            $stmt->bindValue(':height', $data['height'], $data['height'] === null ? \PDO::PARAM_NULL : \PDO::PARAM_STR);
            $stmt->bindValue(':weight', $data['weight'], $data['weight'] === null ? \PDO::PARAM_NULL : \PDO::PARAM_STR);
            $stmt->execute();
        }
    }

    protected function sleepValueForField(Field $field, DimensionsValue $value): array {
        $lu = $field->get('length_unit') ?: 'mm';
        $wu = $field->get('weight_unit') ?: 'g';
        $lf = self::LENGTH_UNITS[$lu]['factor'] ?? 1.0;
        $wf = self::WEIGHT_UNITS[$wu]['factor'] ?? 1.0;

        return [
            'length' => $value->length !== null ? round((float)$value->length * $lf, 4) : null,
            'width'  => $value->width  !== null ? round((float)$value->width  * $lf, 4) : null,
            'height' => $value->height !== null ? round((float)$value->height * $lf, 4) : null,
            'weight' => $value->weight !== null ? round((float)$value->weight * $wf, 4) : null,
        ];
    }



    // ── Translations ──────────────────────────────────────────────────────────

    const TRANSLATIONS = [
        'en' => [
            'length'      => 'Length (L)',
            'width'       => 'Width (W)',
            'height'      => 'Height (H)',
            'weight'      => 'Weight',
            'length_unit' => 'Length unit',
            'weight_unit' => 'Weight unit',
            'show_weight' => 'Show weight field',
            'lu_desc'     => 'Unit used by editors when entering length, width and height values.',
            'wu_desc'     => 'Unit used by editors when entering weight values.',
        ],
        'de' => [
            'length'      => 'Länge (L)',
            'width'       => 'Breite (B)',
            'height'      => 'Höhe (H)',
            'weight'      => 'Gewicht',
            'length_unit' => 'Längeneinheit',
            'weight_unit' => 'Gewichtseinheit',
            'show_weight' => 'Gewichtsfeld anzeigen',
            'lu_desc'     => 'Einheit für die Eingabe von Länge, Breite und Höhe.',
            'wu_desc'     => 'Einheit für die Eingabe des Gewichts.',
        ],
        'fr' => [
            'length'      => 'Longueur (L)',
            'width'       => 'Largeur (l)',
            'height'      => 'Hauteur (H)',
            'weight'      => 'Poids',
            'length_unit' => 'Unité de longueur',
            'weight_unit' => 'Unité de poids',
            'show_weight' => 'Afficher le champ poids',
            'lu_desc'     => 'Unité utilisée pour saisir la longueur, la largeur et la hauteur.',
            'wu_desc'     => 'Unité utilisée pour saisir le poids.',
        ],
        'pl' => [
            'length'      => 'Długość (D)',
            'width'       => 'Szerokość (S)',
            'height'      => 'Wysokość (W)',
            'weight'      => 'Waga',
            'length_unit' => 'Jednostka długości',
            'weight_unit' => 'Jednostka wagi',
            'show_weight' => 'Pokaż pole wagi',
            'lu_desc'     => 'Jednostka używana przez redaktorów do wprowadzania długości, szerokości i wysokości.',
            'wu_desc'     => 'Jednostka używana przez redaktorów do wprowadzania wagi.',
        ],
        'ru' => [
            'length'      => 'Длина (Д)',
            'width'       => 'Ширина (Ш)',
            'height'      => 'Высота (В)',
            'weight'      => 'Вес',
            'length_unit' => 'Единица длины',
            'weight_unit' => 'Единица веса',
            'show_weight' => 'Показывать поле веса',
            'lu_desc'     => 'Единица, используемая редакторами для ввода длины, ширины и высоты.',
            'wu_desc'     => 'Единица, используемая редакторами для ввода веса.',
        ],
        'uk' => [
            'length'      => 'Довжина (Д)',
            'width'       => 'Ширина (Ш)',
            'height'      => 'Висота (В)',
            'weight'      => 'Вага',
            'length_unit' => 'Одиниця довжини',
            'weight_unit' => 'Одиниця ваги',
            'show_weight' => 'Показувати поле ваги',
            'lu_desc'     => 'Одиниця, яку редактори використовують для введення довжини, ширини та висоти.',
            'wu_desc'     => 'Одиниця, яку редактори використовують для введення ваги.',
        ],
        'zh' => [
            'length'      => '长度 (L)',
            'width'       => '宽度 (W)',
            'height'      => '高度 (H)',
            'weight'      => '重量',
            'length_unit' => '长度单位',
            'weight_unit' => '重量单位',
            'show_weight' => '显示重量字段',
            'lu_desc'     => '编辑人员输入长度、宽度和高度时使用的单位。',
            'wu_desc'     => '编辑人员输入重量时使用的单位。',
        ],
        'ro' => [
            'length'      => 'Lungime (L)',
            'width'       => 'Lățime (l)',
            'height'      => 'Înălțime (Î)',
            'weight'      => 'Greutate',
            'length_unit' => 'Unitate de lungime',
            'weight_unit' => 'Unitate de greutate',
            'show_weight' => 'Afișează câmpul greutate',
            'lu_desc'     => 'Unitatea folosită de editori pentru introducerea lungimii, lățimii și înălțimii.',
            'wu_desc'     => 'Unitatea folosită de editori pentru introducerea greutății.',
        ],
        'es' => [
            'length'      => 'Longitud (L)',
            'width'       => 'Anchura (A)',
            'height'      => 'Altura (H)',
            'weight'      => 'Peso',
            'length_unit' => 'Unidad de longitud',
            'weight_unit' => 'Unidad de peso',
            'show_weight' => 'Mostrar campo de peso',
            'lu_desc'     => 'Unidad utilizada por los editores al introducir longitud, anchura y altura.',
            'wu_desc'     => 'Unidad utilizada por los editores al introducir el peso.',
        ],
        'nl' => [
            'length'      => 'Lengte (L)',
            'width'       => 'Breedte (B)',
            'height'      => 'Hoogte (H)',
            'weight'      => 'Gewicht',
            'length_unit' => 'Lengte-eenheid',
            'weight_unit' => 'Gewichtseenheid',
            'show_weight' => 'Gewichtsveld weergeven',
            'lu_desc'     => 'Eenheid die redacteurs gebruiken voor het invoeren van lengte, breedte en hoogte.',
            'wu_desc'     => 'Eenheid die redacteurs gebruiken voor het invoeren van gewicht.',
        ],
        'fi' => [
            'length'      => 'Pituus (P)',
            'width'       => 'Leveys (L)',
            'height'      => 'Korkeus (K)',
            'weight'      => 'Paino',
            'length_unit' => 'Pituusyksikkö',
            'weight_unit' => 'Painoyksikkö',
            'show_weight' => 'Näytä painokenttä',
            'lu_desc'     => 'Yksikkö, jota toimittajat käyttävät pituuden, leveyden ja korkeuden syöttämiseen.',
            'wu_desc'     => 'Yksikkö, jota toimittajat käyttävät painon syöttämiseen.',
        ],
        'sv' => [
            'length'      => 'Längd (L)',
            'width'       => 'Bredd (B)',
            'height'      => 'Höjd (H)',
            'weight'      => 'Vikt',
            'length_unit' => 'Längdenhet',
            'weight_unit' => 'Viktenhet',
            'show_weight' => 'Visa viktfält',
            'lu_desc'     => 'Enhet som redaktörer använder för att ange längd, bredd och höjd.',
            'wu_desc'     => 'Enhet som redaktörer använder för att ange vikt.',
        ],
        'hy' => [
            'length'      => 'Երկարություն (Ե)',
            'width'       => 'Լայնություն (Լ)',
            'height'      => 'Բարձրություն (Բ)',
            'weight'      => 'Քաշ',
            'length_unit' => 'Երկարության միավոր',
            'weight_unit' => 'Քաշի միավոր',
            'show_weight' => 'Ցուցադրել քաշի դաշտը',
            'lu_desc'     => 'Միավոր, որն օգտագործում են խմբագիրները երկարություն, լայնություն և բարձրություն մուտքագրելու համար։',
            'wu_desc'     => 'Միավոր, որն օգտագործում են խմբագիրները քաշ մուտքագրելու համար։',
        ],
        'ka' => [
            'length'      => 'სიგრძე (ს)',
            'width'       => 'სიგანე (ს)',
            'height'      => 'სიმაღლე (მ)',
            'weight'      => 'წონა',
            'length_unit' => 'სიგრძის ერთეული',
            'weight_unit' => 'წონის ერთეული',
            'show_weight' => 'წონის ველის ჩვენება',
            'lu_desc'     => 'ერთეული, რომელსაც რედაქტორები იყენებენ სიგრძის, სიგანისა და სიმაღლის შეყვანისთვის.',
            'wu_desc'     => 'ერთეული, რომელსაც რედაქტორები იყენებენ წონის შეყვანისთვის.',
        ],
        'tr' => [
            'length'      => 'Uzunluk (U)',
            'width'       => 'Genişlik (G)',
            'height'      => 'Yükseklik (Y)',
            'weight'      => 'Ağırlık',
            'length_unit' => 'Uzunluk birimi',
            'weight_unit' => 'Ağırlık birimi',
            'show_weight' => 'Ağırlık alanını göster',
            'lu_desc'     => 'Editörlerin uzunluk, genişlik ve yükseklik girerken kullandığı birim.',
            'wu_desc'     => 'Editörlerin ağırlık girerken kullandığı birim.',
        ],
        'ja' => [
            'length'      => '長さ (L)',
            'width'       => '幅 (W)',
            'height'      => '高さ (H)',
            'weight'      => '重量',
            'length_unit' => '長さの単位',
            'weight_unit' => '重量の単位',
            'show_weight' => '重量フィールドを表示',
            'lu_desc'     => '編集者が長さ・幅・高さを入力する際に使用する単位。',
            'wu_desc'     => '編集者が重量を入力する際に使用する単位。',
        ],
        'af' => [
            'length'      => 'Lengte (L)',
            'width'       => 'Breedte (B)',
            'height'      => 'Hoogte (H)',
            'weight'      => 'Gewig',
            'length_unit' => 'Lengte-eenheid',
            'weight_unit' => 'Gewig-eenheid',
            'show_weight' => 'Wys gewigsveld',
            'lu_desc'     => 'Eenheid wat redakteurs gebruik om lengte, breedte en hoogte in te voer.',
            'wu_desc'     => 'Eenheid wat redakteurs gebruik om gewig in te voer.',
        ],
        'it' => [
            'length'      => 'Lunghezza (L)',
            'width'       => 'Larghezza (l)',
            'height'      => 'Altezza (A)',
            'weight'      => 'Peso',
            'length_unit' => 'Unità di lunghezza',
            'weight_unit' => 'Unità di peso',
            'show_weight' => 'Mostra campo peso',
            'lu_desc'     => 'Unità usata dagli editor per inserire lunghezza, larghezza e altezza.',
            'wu_desc'     => 'Unità usata dagli editor per inserire il peso.',
        ],
        'cs' => [
            'length'      => 'Délka (D)',
            'width'       => 'Šířka (Š)',
            'height'      => 'Výška (V)',
            'weight'      => 'Hmotnost',
            'length_unit' => 'Jednotka délky',
            'weight_unit' => 'Jednotka hmotnosti',
            'show_weight' => 'Zobrazit pole hmotnosti',
            'lu_desc'     => 'Jednotka, kterou editoři používají při zadávání délky, šířky a výšky.',
            'wu_desc'     => 'Jednotka, kterou editoři používají při zadávání hmotnosti.',
        ],
        'sr' => [
            'length'      => 'Dužina (D)',
            'width'       => 'Širina (Š)',
            'height'      => 'Visina (V)',
            'weight'      => 'Težina',
            'length_unit' => 'Jedinica dužine',
            'weight_unit' => 'Jedinica težine',
            'show_weight' => 'Prikaži polje težine',
            'lu_desc'     => 'Jedinica koju urednici koriste za unos dužine, širine i visine.',
            'wu_desc'     => 'Jedinica koju urednici koriste za unos težine.',
        ],
        'lt' => [
            'length'      => 'Ilgis (I)',
            'width'       => 'Plotis (P)',
            'height'      => 'Aukštis (A)',
            'weight'      => 'Svoris',
            'length_unit' => 'Ilgio vienetas',
            'weight_unit' => 'Svorio vienetas',
            'show_weight' => 'Rodyti svorio lauką',
            'lu_desc'     => 'Vienetas, kurį redaktoriai naudoja įvesdami ilgį, plotį ir aukštį.',
            'wu_desc'     => 'Vienetas, kurį redaktoriai naudoja įvesdami svorį.',
        ],
        'lv' => [
            'length'      => 'Garums (G)',
            'width'       => 'Platums (P)',
            'height'      => 'Augstums (A)',
            'weight'      => 'Svars',
            'length_unit' => 'Garuma vienība',
            'weight_unit' => 'Svara vienība',
            'show_weight' => 'Rādīt svara lauku',
            'lu_desc'     => 'Vienība, ko redaktori izmanto garuma, platuma un augstuma ievadīšanai.',
            'wu_desc'     => 'Vienība, ko redaktori izmanto svara ievadīšanai.',
        ],
        'hu' => [
            'length'      => 'Hossz (H)',
            'width'       => 'Szélesség (Sz)',
            'height'      => 'Magasság (M)',
            'weight'      => 'Tömeg',
            'length_unit' => 'Hosszmértékegység',
            'weight_unit' => 'Tömegmértékegység',
            'show_weight' => 'Tömegmező megjelenítése',
            'lu_desc'     => 'A szerkesztők által használt egység a hossz, szélesség és magasság megadásához.',
            'wu_desc'     => 'A szerkesztők által használt egység a tömeg megadásához.',
        ],
        'el' => [
            'length'      => 'Μήκος (Μ)',
            'width'       => 'Πλάτος (Π)',
            'height'      => 'Ύψος (Υ)',
            'weight'      => 'Βάρος',
            'length_unit' => 'Μονάδα μήκους',
            'weight_unit' => 'Μονάδα βάρους',
            'show_weight' => 'Εμφάνιση πεδίου βάρους',
            'lu_desc'     => 'Μονάδα που χρησιμοποιούν οι συντάκτες κατά την εισαγωγή μήκους, πλάτους και ύψους.',
            'wu_desc'     => 'Μονάδα που χρησιμοποιούν οι συντάκτες κατά την εισαγωγή βάρους.',
        ],
        'bg' => [
            'length'      => 'Дължина (Д)',
            'width'       => 'Ширина (Ш)',
            'height'      => 'Височина (В)',
            'weight'      => 'Тегло',
            'length_unit' => 'Мерна единица за дължина',
            'weight_unit' => 'Мерна единица за тегло',
            'show_weight' => 'Покажи поле за тегло',
            'lu_desc'     => 'Единица, използвана от редакторите при въвеждане на дължина, ширина и височина.',
            'wu_desc'     => 'Единица, използвана от редакторите при въвеждане на тегло.',
        ],
        'hr' => [
            'length'      => 'Duljina (D)',
            'width'       => 'Širina (Š)',
            'height'      => 'Visina (V)',
            'weight'      => 'Težina',
            'length_unit' => 'Jedinica duljine',
            'weight_unit' => 'Jedinica težine',
            'show_weight' => 'Prikaži polje težine',
            'lu_desc'     => 'Jedinica koju urednici koriste za unos duljine, širine i visine.',
            'wu_desc'     => 'Jedinica koju urednici koriste za unos težine.',
        ],
        'az' => [
            'length'      => 'Uzunluq (U)',
            'width'       => 'En (E)',
            'height'      => 'Hündürlük (H)',
            'weight'      => 'Çəki',
            'length_unit' => 'Uzunluq vahidi',
            'weight_unit' => 'Çəki vahidi',
            'show_weight' => 'Çəki sahəsini göstər',
            'lu_desc'     => 'Redaktorların uzunluq, en və hündürlük daxil edərkən istifadə etdiyi vahid.',
            'wu_desc'     => 'Redaktorların çəki daxil edərkən istifadə etdiyi vahid.',
        ],
        'ko' => [
            'length'      => '길이 (L)',
            'width'       => '너비 (W)',
            'height'      => '높이 (H)',
            'weight'      => '무게',
            'length_unit' => '길이 단위',
            'weight_unit' => '무게 단위',
            'show_weight' => '무게 필드 표시',
            'lu_desc'     => '편집자가 길이, 너비, 높이를 입력할 때 사용하는 단위입니다.',
            'wu_desc'     => '편집자가 무게를 입력할 때 사용하는 단위입니다.',
        ],
    ];

    /**
     * Translate a UI string key using the module's configured language.
     * PW populates $this->language automatically from saved module config.
     */
    public static function t(string $key): string {
        $lang = 'en';
        try {
            $cfg  = wire('modules')->getModuleConfigData('FieldtypeDimensions');
            if (!empty($cfg['language'])) $lang = $cfg['language'];
        } catch(\Throwable $e) {}
        if (!isset(self::TRANSLATIONS[$lang])) $lang = 'en';
        return self::TRANSLATIONS[$lang][$key] ?? self::TRANSLATIONS['en'][$key] ?? $key;
    }






    protected function roundDisplay(float $v): float {
        for ($p = 0; $p <= 4; $p++) {
            $rounded = round($v, $p);
            if (abs($rounded - $v) < 1e-9 * max(1.0, abs($v))) return $rounded;
        }
        return round($v, 4);
    }

    public function getBlankValue(Page $page, Field $field): DimensionsValue {
        return new DimensionsValue();
    }

    // ── Database → object ─────────────────────────────────────────────────────

    public function wakeupValue(Page $page, Field $field, $value): DimensionsValue {
        $dim = $this->getBlankValue($page, $field);
        if (!is_array($value)) return $dim;

        // Units always come from field config; stored values are in mm/g
        $lu = $field->get('length_unit') ?: 'mm';
        $wu = $field->get('weight_unit') ?: 'g';
        $lf = self::LENGTH_UNITS[$lu]['factor'] ?? 1.0;
        $wf = self::WEIGHT_UNITS[$wu]['factor'] ?? 1.0;

        $dim->length = isset($value['length']) ? $this->roundDisplay((float)$value['length'] / $lf) : null;
        $dim->width  = isset($value['width'])  ? $this->roundDisplay((float)$value['width']  / $lf) : null;
        $dim->height = isset($value['height']) ? $this->roundDisplay((float)$value['height'] / $lf) : null;
        $dim->weight = isset($value['weight']) ? $this->roundDisplay((float)$value['weight'] / $wf) : null;
        // Do NOT store units in the value — they are always read from field config at render time

        return $dim;
    }

    // ── Object → database ─────────────────────────────────────────────────────

    public function sleepValue(Page $page, Field $field, $value): array {
        if (!$value instanceof DimensionsValue) return [];

        // Units always come from field config
        $lu = $field->get('length_unit') ?: 'mm';
        $wu = $field->get('weight_unit') ?: 'g';
        $lf = self::LENGTH_UNITS[$lu]['factor'] ?? 1.0;
        $wf = self::WEIGHT_UNITS[$wu]['factor'] ?? 1.0;

        // Normalize to mm and grams for storage
        return [
            'length' => $value->length !== null ? round((float)$value->length * $lf, 4) : null,
            'width'  => $value->width  !== null ? round((float)$value->width  * $lf, 4) : null,
            'height' => $value->height !== null ? round((float)$value->height * $lf, 4) : null,
            'weight' => $value->weight !== null ? round((float)$value->weight * $wf, 4) : null,
        ];
    }

    // ── Sanitization ─────────────────────────────────────────────────────────

    public function sanitizeValue(Page $page, Field $field, $value) {
        if ($value instanceof DimensionsValue) return $value;

        $dim = $this->getBlankValue($page, $field);
        if (!is_array($value)) return $dim;

        foreach (['length', 'width', 'height', 'weight'] as $k) {
            if (isset($value[$k]) && $value[$k] !== '' && $value[$k] !== null) {
                $dim->$k = (float)$value[$k];
            }
        }
        if (!empty($value['length_unit'])) $dim->length_unit = $value['length_unit'];
        if (!empty($value['weight_unit'])) $dim->weight_unit = $value['weight_unit'];

        return $dim;
    }

    // ── Database schema ───────────────────────────────────────────────────────

    public function getDatabaseSchema(Field $field): array {
        // Return a minimal schema so ProcessWire's schema-diff machinery
        // does not try to manage columns it doesn't understand.
        // The actual table is created/dropped in ___createField / ___deleteField.
        $schema = parent::getDatabaseSchema($field);
        $schema['data'] = 'FLOAT NULL DEFAULT NULL COMMENT "length in mm"';
        unset($schema['keys']['data']);
        return $schema;
    }

    // ── Table lifecycle ───────────────────────────────────────────────────────

    public function ___createField(Field $field): bool {
        $db    = $this->wire('database');
        $table = $db->escapeTable($field->getTable());
        $db->exec("
            CREATE TABLE IF NOT EXISTS `{$table}` (
                `pages_id` INT UNSIGNED NOT NULL,
                `data`     FLOAT        NULL DEFAULT NULL COMMENT 'length in mm',
                `width`    FLOAT        NULL DEFAULT NULL COMMENT 'width in mm',
                `height`   FLOAT        NULL DEFAULT NULL COMMENT 'height in mm',
                `weight`   FLOAT        NULL DEFAULT NULL COMMENT 'weight in g',
                PRIMARY KEY (`pages_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        return true;
    }

    public function ___deleteField(Field $field): bool {
        $db    = $this->wire('database');
        $table = $db->escapeTable($field->getTable());
        $db->exec("DROP TABLE IF EXISTS `{$table}`");
        return true;
    }

    // ── Column mapping ────────────────────────────────────────────────────────

    // We use loadPageField/savePageField directly, so no JOIN-based loading needed.
    public function getLoadQueryAutojoin(Field $field, DatabaseQuerySelect $query): ?DatabaseQuerySelect {
        return null;
    }

    public function loadPageField(Page $page, Field $field) {
        $db    = $this->wire('database');
        $table = $field->getTable();

        try {
            $stmt = $db->prepare("SELECT data, width, height, weight FROM `{$table}` WHERE pages_id=:pid");
            $stmt->bindValue(':pid', $page->id, \PDO::PARAM_INT);
            $stmt->execute();
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        } catch(\Exception $e) {
            return null;
        }

        if (!$row) return null;

        return [
            'length' => $row['data']   !== null ? (float)$row['data']   : null,
            'width'  => $row['width']  !== null ? (float)$row['width']  : null,
            'height' => $row['height'] !== null ? (float)$row['height'] : null,
            'weight' => $row['weight'] !== null ? (float)$row['weight'] : null,
        ];
    }

    public function savePageField(Page $page, Field $field): bool {
        // Saving is handled by hookSaveFromPost which reads POST data directly.
        // This method is kept for API saves (non-POST context).
        $value = $page->get($field->name);
        if (!$value instanceof DimensionsValue) return true;

        $data  = $this->sleepValueForField($field, $value);
        $table = $field->getTable();
        $sql   = "INSERT INTO `{$table}` (pages_id, data, width, height, weight)
                  VALUES(:pid, :length, :width, :height, :weight)
                  ON DUPLICATE KEY UPDATE
                    data=VALUES(data), width=VALUES(width),
                    height=VALUES(height), weight=VALUES(weight)";

        $stmt = $this->wire('database')->prepare($sql);
        $stmt->bindValue(':pid',    $page->id,      \PDO::PARAM_INT);
        $stmt->bindValue(':length', $data['length'], $data['length'] === null ? \PDO::PARAM_NULL : \PDO::PARAM_STR);
        $stmt->bindValue(':width',  $data['width'],  $data['width']  === null ? \PDO::PARAM_NULL : \PDO::PARAM_STR);
        $stmt->bindValue(':height', $data['height'], $data['height'] === null ? \PDO::PARAM_NULL : \PDO::PARAM_STR);
        $stmt->bindValue(':weight', $data['weight'], $data['weight'] === null ? \PDO::PARAM_NULL : \PDO::PARAM_STR);
        $stmt->execute();

        return true;
    }

    // ── Field configuration ───────────────────────────────────────────────────

    public function ___getConfigInputfields(Field $field): InputfieldWrapper {
        $inputfields = parent::___getConfigInputfields($field);

        /** @var InputfieldSelect $f */
        $f = $this->wire('modules')->get('InputfieldSelect');
        $f->attr('name', 'length_unit');
        $f->label = self::t('length_unit');
        $f->description = self::t('lu_desc');
        foreach (self::LENGTH_UNITS as $key => $info) {
            $f->addOption($key, $info['label']);
        }
        $f->attr('value', $field->get('length_unit') ?: 'mm');
        $inputfields->add($f);

        /** @var InputfieldSelect $f2 */
        $f2 = $this->wire('modules')->get('InputfieldSelect');
        $f2->attr('name', 'weight_unit');
        $f2->label = self::t('weight_unit');
        $f2->description = self::t('wu_desc');
        foreach (self::WEIGHT_UNITS as $key => $info) {
            $f2->addOption($key, $info['label']);
        }
        $f2->attr('value', $field->get('weight_unit') ?: 'g');
        $inputfields->add($f2);

        /** @var InputfieldCheckbox $fc */
        $fc = $this->wire('modules')->get('InputfieldCheckbox');
        $fc->attr('name', 'show_weight');
        $fc->label = self::t('show_weight');
        $fc->attr('checked', $field->get('show_weight') ? 'checked' : '');
        $fc->attr('value', 1);
        $inputfields->add($fc);

        return $inputfields;
    }

    // ── Config export / import ────────────────────────────────────────────────

    public function ___exportConfigData(Field $field, array $data): array {
        return $data;
    }

    public function ___importConfigData(Field $field, array $data): array {
        return $data;
    }

    // ── Selector engine ───────────────────────────────────────────────────────

    public function getMatchQuery($query, $table, $subfield, $operator, $value) {
        // e.g. $pages->find("dimensions.length>100")
        $col = match ($subfield) {
            'length' => 'data',
            'width'  => 'width',
            'height' => 'height',
            'weight' => 'weight',
            default  => 'data',
        };
        $query->where("$table.$col $operator ?", [(float)$value]);
        return $query;
    }

    // ── Inputfield ────────────────────────────────────────────────────────────

    public function getInputfield(Page $page, Field $field): Inputfield {
        /** @var InputfieldDimensions $inputfield */
        $inputfield = $this->wire('modules')->get('InputfieldDimensions');
        $inputfield->setField($field);
        return $inputfield;
    }

    public function getCompatibleFieldtypes(Field $field): array {
        return [];
    }
}