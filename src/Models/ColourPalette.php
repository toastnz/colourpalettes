<?php

namespace Toast\ColourPalettes\Models;

use SilverStripe\ORM\DataObject;
use Toast\ColourPalettes\Models\Colour;
use Toast\ColourPalettes\Helpers\Helper;

class ColourPalette extends DataObject
{
    private static $table_name = 'ColourPalette';

    private static $db = [
        'Title' => 'Varchar(255)',
        'ColourPaletteID' => 'Varchar(30)',
    ];

    private static $has_one = [
        'Colour' => Colour::class,
        'Parent' => DataObject::class,
    ];

    // A function to set the Title and ColourID based on the selected value
    public function setColourPalette($title, $id)
    {
        // Get the colour object based on the colour palette ID
        $id = $id ?: $this->ColourPaletteID;
        $colour = Helper::getColour($id);

        if ($colour) {
            // Assign the title to the Title
            $this->Title = $title;
            // Assign the colour object to the ColourID
            $this->ColourID = $colour->ID;
        } else {
            // Remove the title and colour ID
            $this->Title = null;
            $this->ColourID = null;
        }

        // Write the changes to the database
        $this->write();
    }

    public function getHasColour()
    {
        $colour = $this->Colour();

        if (!$colour) return false;

        return $colour->ColourValue == 'transparent' ? false : true;
    }

    public function getIsDark()
    {
        $colour = $this->Colour();

        if (!$colour) return false;

        return $colour ? $colour->getColourIsDark() : false;
    }

    public function getIsLight()
    {
        $colour = $this->Colour();

        if (!$colour) return false;

        return $colour ? $colour->getColourIsLight() : false;
    }

    public function getCSSName()
    {
        if (!$this->Title) return null;
        // Convert the title to lowercase and replace spaces with dashes
        return strtolower(preg_replace('/([a-z])([A-Z])/', '$1-$2', $this->Title));
    }

    public function getCSSVars()
    {
        // Set the styles to an empty string
        $styles = '';
        // Get the colour object
        $colour = $this->Colour();

        // Get the title
        if ($title = $this->getCSSName()) {
            $colourID = $colour->getColourID();

            $styles .= '--_' . $title . ': var(' . '--colour-' . $colourID . ');';
            $styles .= '--_' . $title . '-contrast: var(' . '--colour-on-' . $colourID . ');';
            $styles .= '--_' . $title . '-on-contrast: var(' . '--colour-on-' . $colourID . '-contrast);';
        }

        return $styles;
    }

    public function getRootVars()
    {
        // Reurn the CSS variables without the _
        return str_replace('--_', '--', $this->getCSSVars());
    }

    public function getScopedStyles($id = null)
    {
        // Set the styles to an empty string
        $styles = '';
        // Get the colour object
        $colour = $this->Colour();
        // Get the title
        $title = $this->getCSSName();

        // Get the target
        $target = $id ? '#' . $id : ':root';

        // If there is a colour object, return the style tag
        if ($colour) {
            // $styles = '<style>';
            $styles .= $target . ' {';
            $styles .= $this->getCSSVars();
            $styles .= '}';
            // TODO: add a condition here so this only runs when viewing the CMS
            $styles .= $target . '.cms-preview {';
            $styles .= '--_' . $title . ': var(--_preview-' . $title . ');';
            $styles .= '--_' . $title . '-contrast: var(--_preview-' . $title . '-contrast); }';
            // $styles .= '</style>';
        }

        return $styles;
    }

    public function onAfterWrite()
    {
        parent::onAfterWrite();
    }
}
