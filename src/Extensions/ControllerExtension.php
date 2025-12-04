<?php

namespace Toast\ColourPalettes\Extensions;

use SilverStripe\Core\Extension;
use Toast\ColourPalettes\Models\Colour;

class ControllerExtension extends Extension
{
    public function getFirstColour(...$colours): ?Colour
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

    public function isValidColour($colour): bool
    {
        // make sure the colour is a instance of Colour and exists
        if (!$colour) return false;
        if (!$colour->exists()) return false;
        if (!$colour instanceof Colour) return false;

        return true;
    }

    public function getColourByName($colourName): ?Colour
    {
        // Get all the Colours
        $allColours = Colour::get();

        $colour = $allColours->filterAny([
            'CSSName' => $colourName,
        ])->first();

        return $this->isValidColour($colour) ? $colour : null;
    }

    public function getColourByID($colourID): ?Colour
    {
        // Get all the Colours
        $allColours = Colour::get();

        // Filter the colours by the given ID, checking both ID and CustomColourID fields
        $colour = $allColours->byID($colourID);

        return $this->isValidColour($colour) ? $colour : null;
    }

    public function getLightenColour($colourNameOrID, $amount = 0): ?string
    {
        $colour = $this->getColourByID($colourNameOrID);

        if (!$colour) return null;

        return $colour->getLightenedBy($amount);
    }

    public function getDarkenColour($colourNameOrID, $amount = 0): ?string
    {
        $colour = $this->getColourByID($colourNameOrID);

        if (!$colour) return null;

        return $colour->getDarkenedBy($amount);
    }
}
