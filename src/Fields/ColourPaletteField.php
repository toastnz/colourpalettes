<?php

namespace Toast\ColourPalettes\Fields;

use SilverStripe\Forms\OptionsetField;
use SilverStripe\ORM\DataObjectInterface;
use SilverStripe\ORM\FieldType\DBHTMLText;
use SilverStripe\View\Requirements;
use Toast\ColourPalettes\Helpers\Helper;
use Toast\ColourPalettes\Models\Colour;

/**
 * An optionset of the current site's colours, rendered as swatches.
 *
 * Extensions can use `updateColourPaletteSource` to change the options, and `onBeforeSaveInto` / `onAfterSaveInto`
 * to react to the value being saved.
 */
class ColourPaletteField extends OptionsetField
{
    /**
     * The site's colours keyed by ID, loaded on first use so the template can look colours up cheaply.
     *
     * @var array<int, Colour>|null
     */
    protected ?array $colours = null;

    /**
     * @param string[] $source Names of the colour groups to get the colours from (empty = all colours)
     * @param callable|null $filter Receives a Colour and returns true to include it
     */
    public function __construct(string $name, ?string $title = null, array $source = [], ?callable $filter = null, mixed $value = null)
    {
        // Add a 'None' option to the first position, followed by the colour palette
        $options = ['0' => 'None'] + Helper::getColourPaletteArray($source, $filter);

        parent::__construct($name, $title ?? $name, $options, $value);

        $this->extend('updateColourPaletteSource', $options);
        $this->setSource($options);
    }

    public function setValue($value, $data = null): static
    {
        if (is_object($value) && isset($value->ID)) {
            $value = $value->ID;
        }

        return parent::setValue($value, $data);
    }

    public function Field($properties = []): DBHTMLText
    {
        Requirements::css('toastnz/colourpalettes: client/dist/styles/main.css');
        Requirements::javascript('toastnz/colourpalettes: client/dist/scripts/colour-palette-field.js');

        return parent::Field($properties);
    }

    /**
     * Get the whole Colour object for a value (defaults to the field's value). Used by the template.
     */
    public function getColour(int|string|null $value = null): ?Colour
    {
        // Only fall back to the field's value when no value is passed, so the 'None' option ('0') stays empty
        $value = (int) ($value ?? $this->value);

        if (!$value) {
            return null;
        }

        if ($this->colours === null) {
            $this->colours = [];

            if ($siteConfig = Helper::getCurrentSiteConfig()) {
                foreach ($siteConfig->Colours() as $colour) {
                    $this->colours[(int) $colour->ID] = $colour;
                }
            }
        }

        $colour = $this->colours[$value] ?? null;

        $this->extend('updateColour', $colour, $value);

        return $colour;
    }

    public function saveInto(DataObjectInterface $record): void
    {
        $this->extend('onBeforeSaveInto', $record);

        // The record is written by whatever is saving the form (CMS action, GridFieldEditableColumns, etc.)
        parent::saveInto($record);

        $this->extend('onAfterSaveInto', $record);
    }
}
