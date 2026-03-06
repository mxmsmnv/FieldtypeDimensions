<?php namespace ProcessWire;

/**
 * InputfieldDimensions — admin input field for FieldtypeDimensions.
 */
class InputfieldDimensions extends Inputfield {

    public static function getModuleInfo(): array {
        return [
            'title'    => 'Dimensions Inputfield',
            'summary'  => 'Inputfield for FieldtypeDimensions.',
            'version'  => '1.0.3',
            'author'   => 'Maxim Semenov',
            'requires' => 'FieldtypeDimensions',
        ];
    }

    protected Field $fieldDef;

    public function setField(Field $field): void {
        $this->fieldDef = $field;
    }

    // ── Render ────────────────────────────────────────────────────────────────

    public function ___render(): string {
        $value = $this->attr('value');
        if (!$value instanceof DimensionsValue) $value = new DimensionsValue();

        $name = $this->attr('name');

        // Units always come from field config
        $lu = $this->fieldDef->get('length_unit') ?: 'mm';
        $wu = $this->fieldDef->get('weight_unit') ?: 'g';

        $showWeight = (bool)($this->fieldDef->get('show_weight') ?? true);

        $lLabel = FieldtypeDimensions::LENGTH_UNITS[$lu]['label'] ?? $lu;
        $wLabel = FieldtypeDimensions::WEIGHT_UNITS[$wu]['label'] ?? $wu;

        $fmt = function($v) {
            if ($v === null) return '';
            // Format with up to 4 decimal places, strip trailing zeros
            $s = rtrim(rtrim(number_format((float)$v, 4, '.', ''), '0'), '.');
            return $s;
        };

        $length = htmlspecialchars($fmt($value->length));
        $width  = htmlspecialchars($fmt($value->width));
        $height = htmlspecialchars($fmt($value->height));
        $weight = htmlspecialchars($fmt($value->weight));

        $lOptions = $this->renderOptions(FieldtypeDimensions::LENGTH_UNITS, $lu);
        $wOptions = $this->renderOptions(FieldtypeDimensions::WEIGHT_UNITS, $wu);

        $labelLength = FieldtypeDimensions::t('length');
        $labelWidth  = FieldtypeDimensions::t('width');
        $labelHeight = FieldtypeDimensions::t('height');
        $labelWeight = FieldtypeDimensions::t('weight');

        $weightHtml = '';
        if ($showWeight) {
            $weightHtml = <<<HTML

                <div class="InputfieldDimensions__group" style="margin-left:16px;">
                    <label class="InputfieldDimensions__label" for="{$name}_weight">{$labelWeight}</label>
                    <div class="InputfieldDimensions__cell">
                        <input type="text" inputmode="decimal" name="{$name}_weight"
                               id="{$name}_weight" value="{$weight}" placeholder="0"
                               class="InputfieldDimensions__input">
                        <span class="InputfieldDimensions__unit-label">{$wLabel}</span>
                    </div>
                </div>
            HTML;
        }

        return <<<HTML
        <div class="InputfieldDimensions">
            <input type="hidden" name="{$name}_length_unit" value="{$lu}">
            <input type="hidden" name="{$name}_weight_unit" value="{$wu}">
            <div class="InputfieldDimensions__row">
                <div class="InputfieldDimensions__group">
                    <label class="InputfieldDimensions__label" for="{$name}_length">{$labelLength}</label>
                    <div class="InputfieldDimensions__cell">
                        <input type="text" inputmode="decimal" name="{$name}_length"
                               id="{$name}_length" value="{$length}" placeholder="0"
                               class="InputfieldDimensions__input">
                        <span class="InputfieldDimensions__unit-label">{$lLabel}</span>
                    </div>
                </div>
                <div class="InputfieldDimensions__sep">×</div>
                <div class="InputfieldDimensions__group">
                    <label class="InputfieldDimensions__label" for="{$name}_width">{$labelWidth}</label>
                    <div class="InputfieldDimensions__cell">
                        <input type="text" inputmode="decimal" name="{$name}_width"
                               id="{$name}_width" value="{$width}" placeholder="0"
                               class="InputfieldDimensions__input">
                        <span class="InputfieldDimensions__unit-label">{$lLabel}</span>
                    </div>
                </div>
                <div class="InputfieldDimensions__sep">×</div>
                <div class="InputfieldDimensions__group">
                    <label class="InputfieldDimensions__label" for="{$name}_height">{$labelHeight}</label>
                    <div class="InputfieldDimensions__cell">
                        <input type="text" inputmode="decimal" name="{$name}_height"
                               id="{$name}_height" value="{$height}" placeholder="0"
                               class="InputfieldDimensions__input">
                        <span class="InputfieldDimensions__unit-label">{$lLabel}</span>
                    </div>
                </div>{$weightHtml}
            </div>
        </div>
        <style>
        .InputfieldDimensions__row { display: flex; align-items: flex-end; flex-wrap: wrap; gap: 0; }
        .InputfieldDimensions__group { display: flex; flex-direction: column; }
        .InputfieldDimensions__label { font-size: 0.8em; color: #777; margin-bottom: 3px; display: block; }
        .InputfieldDimensions__cell { display: flex; }
        .InputfieldDimensions__input { width: 120px; }
        .InputfieldDimensions__unit-label { display: flex; align-items: center; padding: 0 6px; font-size: 0.85em; color: #777; }
        .InputfieldDimensions__unit { width: auto; }
        .InputfieldDimensions__sep { padding: 0 4px; line-height: 1; color: #aaa; margin-bottom: 6px; align-self: flex-end; }
        </style>
        HTML;
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function renderOptions(array $units, string $selected): string {
        $html = '';
        foreach ($units as $key => $info) {
            $sel  = $key === $selected ? ' selected' : '';
            $html .= "<option value=\"{$key}\"{$sel}>{$info['label']}</option>";
        }
        return $html;
    }

    // ── POST processing ───────────────────────────────────────────────────────

    public function ___processInput(WireInputData $input): self {
        $name = $this->attr('name');

        $raw = [
            'length'      => $input->get("{$name}_length"),
            'width'       => $input->get("{$name}_width"),
            'height'      => $input->get("{$name}_height"),
            'weight'      => $input->get("{$name}_weight"),
            'length_unit' => $input->get("{$name}_length_unit"),
            'weight_unit' => $input->get("{$name}_weight_unit"),
        ];

        $dim = new DimensionsValue();

        foreach (['length', 'width', 'height', 'weight'] as $k) {
            $v = $raw[$k];
            if ($v !== null && $v !== '') {
                $v = str_replace(',', '.', trim((string)$v));
                if (is_numeric($v)) $dim->$k = (float)$v;
            }
        }

        if (!empty($raw['length_unit']) && isset(FieldtypeDimensions::LENGTH_UNITS[$raw['length_unit']])) {
            $dim->length_unit = $raw['length_unit'];
        }
        if (!empty($raw['weight_unit']) && isset(FieldtypeDimensions::WEIGHT_UNITS[$raw['weight_unit']])) {
            $dim->weight_unit = $raw['weight_unit'];
        }

        $this->set('value', $dim);

        $page = $this->hasPage;
        if ($page && $page instanceof Page) {
            $page->set($name, $dim);
            $page->trackChange($name);
        }

        return $this;
    }

    // ── Render ready ──────────────────────────────────────────────────────────

    public function ___renderReady(): bool {
        // No additional assets required
        return parent::___renderReady();
    }
}
