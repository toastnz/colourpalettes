<?php

namespace Toast\ColourPalettes\Fields;

use SilverStripe\View\Requirements;
use SilverStripe\Forms\OptionsetField;
use Toast\ColourPalettes\Helpers\Helper;
use SilverStripe\ORM\DataObjectInterface;
use Toast\ColourPalettes\Models\Colour;

class ColourPaletteField extends OptionsetField
{
    // $source is an array of groups to get the colours from
    public function __construct($name, $title = null, $source = [], $filter = null)
    {
        // Add a 'None' option to the first position
        $options = ['0' => 'None'];

        // Set the source to the colour palette array
        $palette = Helper::getColourPaletteArray($source, $filter);

        // Set the source to the palette array
        $source = $options + $palette;

        // Set the source for use in the field
        $this->setSource($source);

        // If no title is provided, use the name
        if (!isset($title)) $title = $name;

        // Call the parent constructor
        parent::__construct($name, $title, $source, $filter);
    }

    public function Field($properties = [])
    {
        Requirements::css('toastnz/colourpalettes: client/dist/styles/colour-palette-field.css');
        Requirements::javascript('toastnz/colourpalettes: client/dist/scripts/colour-palette-field.js');

        return parent::Field($properties);
    }

    // A function to return the whole Colour object for use in templates if needed
    public function getColour($value = null)
    {
        if (!$value) $value = $this->value;

        $value = (int)$value;

        // Get the current site config
        $siteConfig = Helper::getCurrentSiteConfig();
        if (!$siteConfig) return null;

        // Get the colours related to this site config
        $colours = $siteConfig->Colours();
        if (!$colours) return null;

        // Find the colour with the matching ID
        $colour = $colours->find('ID', $value);
        if (!$colour) return null;

        return $colour;
    }

    public function saveInto(DataObjectInterface $record)
    {
        // Find the relation based on the field name
        $relation = $this->name;

        // Set the relation on the record to the selected value
        $record->$relation = $this->value;

        // Write the record
        $record->write();

        parent::saveInto($record);
    }
}
