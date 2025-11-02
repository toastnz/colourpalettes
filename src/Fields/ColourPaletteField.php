<?php

namespace Toast\ColourPalettes\Fields;

use SilverStripe\View\Requirements;
use SilverStripe\Forms\OptionsetField;
use Toast\ColourPalettes\Helpers\Helper;
use SilverStripe\ORM\DataObjectInterface;
use Toast\ColourPalettes\Models\ColourPalette;

class ColourPaletteField extends OptionsetField
{
    // $source is an array of groups to get the colours from
    public function __construct($name, $title = null, $source = [], $value = null)
    {
        // Add a 'None' option to the first position
        $options = ['None' => ''];

        // Set the source to the colour palette array
        $palette = Helper::getColourPaletteArray($source);

        // Set the source to the palette array
        $source = array_merge($options, $palette);

        $this->setSource($source);

        if (!isset($title)) {
            $title = $name;
        }

         // Ensure value is always scalar
        if (is_object($value) && isset($value->ID)) {
            $value = $value->ID;
        }

        parent::__construct($name, $title, $source, $value);
    }

    public function setValue($value, $data = null)
    {
        if (is_object($value) && isset($value->ID)) {
            $value = $value->ID;
        }
        return parent::setValue($value, $data);
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
            $fieldName = $this->name;
            
            // Try direct field value (e.g. FooterPrimaryColourID)
            if (isset($record->$fieldName)) {
                $fieldValue = $record->$fieldName;
        
                if (is_object($fieldValue) && isset($fieldValue->ID)) {
                    return $fieldValue->ColourPaletteID == $value;
                }
            }
        }
        return false;
        // if ($this->form && $record = $this->form->getRecord()) {
        //     $name = $this->name;
        //     $relationName = preg_replace('/ID$/', '', $name);
        //     if (method_exists($record, $relationName)) {
        //         $relation = $record->$relationName();
        //         if ($relation && $relation->exists()) {
        //             $colour = $relation->Colour();
        //             $fieldValue = $colour ? $colour->ColourPaletteID : null;
        //             if (is_object($fieldValue) && isset($fieldValue->ID)) {
        //                 $fieldValue = $fieldValue->ID;
        //             }
        //             return $fieldValue == $value;
        //         }
        //     }
        // }

        // return false;
    }

    // A function to return the whole Colour object for use in templates if needed
    public function getColour($id = null)
    {
        return Helper::getColour($id);
    }

    public function saveInto(DataObjectInterface $record)
    {
        // if record in db
        if(!$record || !$record->exists()){
            return;
        }
        // Get the values from the selected colour option that we want
        $title = $this->name;
        $relation = $this->name . 'ID';
        $value = $this->value;
        $recordID = $record->ID;
        $className = $record->ClassName;

         if ($record->$title() && $colourPaletteID = $record->$title()->ID) {
            $colourPalette = ColourPalette::get()->byID($colourPaletteID);
            if($colourPalette && $colourPalette->exists() && $colourPalette->ParentID === $recordID){ 
                $colourPalette->setColourPalette($title, $value);
                $colourPalette->ColourPaletteID = $value;
                $colourPalette->ParentClass = $className;
                $colourPalette->ParentID = $recordID;
                $colourPalette->write();
                return;
            }
        }

        // Create a new ColourPalette object
        $colourPalette = new ColourPalette();

        // Assign the values to the ColourPalette object
        $colourPalette->setColourPalette($title, $value);

        // Set the values on the ColourPalette object
        $colourPalette->ColourPaletteID = $value;
        $colourPalette->ParentClass = $className;
        $colourPalette->ParentID = $recordID;

        // Write the ColourPalette object
        $colourPalette->write();

        // Assign the ColourPalette object ID to the record
        $record->$relation = $colourPalette->ID;

        // Write the record
        $record->write();
    }
}
