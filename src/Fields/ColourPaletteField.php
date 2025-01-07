<?php

namespace Toast\ColourPalettes\Fields;

use SilverStripe\View\Requirements;
use SilverStripe\Forms\OptionsetField;
use Toast\ColourPalettes\Helpers\Helper;
use SilverStripe\ORM\DataObjectInterface;
use Toast\ColourPalettes\Models\ColourPalette;

class ColourPaletteField extends OptionsetField
{
    public function __construct($name, $title = null, $source = [], $value = null)
    {
        // A default empty array for the colour palette
        $palette = [];
        // Add a 'None' option to the first position
        $options = ['None' => ''];

        // Check if the source is an array, and if it is empty
        if (is_array($source) && empty($source)) {
            // Create an array to store the colour palette title and ID or CustomColourID
            $palette = Helper::getColourPaletteArray();
        }

        // If the source is a string
        if (is_string($source)) {
            // Get the colour palette array
            $palette = Helper::getColourPaletteArray($source);
        }

        // Set the source to the palette array
        $source = array_merge($options, $palette);

        $this->setSource($source);

        if (!isset($title)) {
            $title = $name;
        }

        parent::__construct($name, $title, $source, $value);
    }

    public function Field($properties = [])
    {
        Requirements::css('toastnz/colourpalettes: client/dist/styles/colour-palette-field.css');
        Requirements::javascript('toastnz/colourpalettes: client/dist/scripts/colour-palette-field.js');

        return parent::Field($properties);
    }

    public function isChecked($value)
    {
        if ($this->form && $record = $this->form->getRecord()) {
            $name = $this->name;
            $relation = $record->$name();
            $colour = $relation->Colour();

            return $colour->ColourPaletteID == $value;
        }

        return false;
    }

    // A function to return the whole Colour object for use in templates if needed
    public function getColour($id = null)
    {
        return Helper::getColour($id);
    }

    public function saveInto(DataObjectInterface $record)
    {
        // Get the values from the selected colour option that we want
        $title = $this->name;
        $relation = $this->name . 'ID';
        $value = $this->value;

        if ($record->$title() && $colourPaletteID = $record->$title()->ID) {
            $colourPalette = ColourPalette::get()->byID($colourPaletteID);
            $colourPalette->setColourPalette($title, $value);
            $colourPalette->write();
            return;
        }

        // Create a new ColourPalette object
        $colourPalette = new ColourPalette();

        // Assign the values to the ColourPalette object
        $colourPalette->setColourPalette($title, $value);

        // Write the ColourPalette object
        $colourPalette->write();

        // Assign the ColourPalette object ID to the record
        $record->$relation = $colourPalette->ID;

        // Write the record
        $record->write();
    }
}
