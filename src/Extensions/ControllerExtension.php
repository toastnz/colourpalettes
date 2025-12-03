<?php

namespace Toast\ColourPalettes\Extensions;

use SilverStripe\Core\Extension;
use Toast\ColourPalettes\Models\Colour;

class ControllerExtension extends Extension
{
    public function getFirstColour(...$colours)
    {
        foreach ($colours as $colour) {
            // If the $arg is not a Colour, skip it
            if (!$colour instanceof Colour) continue;
            // If the colour exists, return it
            if ($colour && $colour->exists()) return $colour;
        }

        // If no colours exist, return null
        return null;
    }

    public function isValidColour($colour)
    {
        // make sure the colour is a instance of Colour and exists
        if (!$colour) return false;
        if (!$colour->exists()) return false;
        if (!$colour instanceof Colour) return false;

        return true;
    }

    public function getColourByName($colourName) {
        // Get all the Colours
        $allColours = Colour::get();

        $colour = $allColours->filterAny([
            'CSSName' => $colourName,
        ])->first();

        return $this->isValidColour($colour) ? $colour : null;
    }

    public function getColourByID($colourID)
    {
        // Get all the Colours
        $allColours = Colour::get();

        // Filter the colours by the given ID, checking both ID and CustomColourID fields
        $colour = $allColours->byID($colourID);

        return $this->isValidColour($colour) ? $colour : null;
    }

    public function getColourRGBArrayFromNameOrID($colourNameOrID)
    {
        $colour = is_numeric($colourNameOrID) ? $this->getColourByID($colourNameOrID) : $this->getColourByName($colourNameOrID);

        if (!$colour) return null;

        return sscanf($colour->Value, "#%02x%02x%02x");
    }

    public function getLightenColour($colourNameOrID, $amount = 0)
    {
        $rgb = $this->getColourRGBArrayFromNameOrID($colourNameOrID);

        if (!$rgb) return null;

        $amount = abs($amount);
        $amount = min(100, $amount);
        $percentage = (255 * $amount / 100);

        $r = min(255, $rgb[0] + $percentage);
        $g = min(255, $rgb[1] + $percentage);
        $b = min(255, $rgb[2] + $percentage);

        return sprintf("#%02x%02x%02x", (int)$r, (int)$g, (int)$b);
    }

    public function getDarkenColour($colourNameOrID, $amount = 0)
    {
        $rgb = $this->getColourRGBArrayFromNameOrID($colourNameOrID);

        if (!$rgb) return null;

        $amount = abs($amount);
        $amount = min(100, $amount);
        $percentage = (255 * $amount / 100);

        $r = max(0, $rgb[0] - $percentage);
        $g = max(0, $rgb[1] - $percentage);
        $b = max(0, $rgb[2] - $percentage);

        return sprintf("#%02x%02x%02x", (int)$r, (int)$g, (int)$b);
    }
}
