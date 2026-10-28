<?php

namespace Toast\ColourPalettes\Extensions;

use SilverStripe\Control\Controller;
use SilverStripe\Core\Extension;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBField;
use Toast\ColourPalettes\Helpers\Helper;
use Toast\ColourPalettes\Models\Colour;

/**
 * Colour lookup helpers available on every controller (and therefore in templates).
 *
 * Each method passes its result through an `update*` hook on the owner, so projects can adjust it with
 * their own Extension on SilverStripe\Control\Controller.
 *
 * @extends Extension<Controller>
 */
class ControllerExtension extends Extension
{
    /**
     * Return the first of the given arguments that is an existing Colour.
     */
    public function getFirstColour(mixed ...$colours): ?Colour
    {
        $first = null;

        foreach ($colours as $colour) {
            if ($this->isValidColour($colour)) {
                $first = $colour;
                break;
            }
        }

        $this->getOwner()->extend('updateFirstColour', $first, $colours);

        return $first;
    }

    /**
     * Whether the value is a Colour that exists in the database.
     */
    public function isValidColour(mixed $colour): bool
    {
        $isValid = $colour instanceof Colour && $colour->exists();

        $this->getOwner()->extend('updateIsValidColour', $isValid, $colour);

        return $isValid;
    }

    /**
     * Find a colour by its CSS name, within a SiteConfig. Defaults to the current SiteConfig.
     */
    public function getColourByName(mixed $colourName, mixed $siteConfigID = null): ?Colour
    {
        $colourName = $this->normaliseArgument($colourName);
        $siteConfigID = $this->normaliseArgument($siteConfigID);
        $colour = null;

        if ($colourName) {
            $siteConfigID ??= Helper::getCurrentSiteConfig()?->ID;

            $colours = Colour::get()->filter('CSSName', (string) $colourName);

            if ($siteConfigID) {
                $colours = $colours->filter('SiteConfigs.ID', (int) $siteConfigID);
            }

            $colour = $colours->first();
        }

        $colour = $this->isValidColour($colour) ? $colour : null;

        $this->getOwner()->extend('updateColourByName', $colour, $colourName, $siteConfigID);

        return $colour;
    }

    public function getColourByID(mixed $colourID): ?Colour
    {
        $colourID = $this->normaliseArgument($colourID);
        $colour = $colourID ? Colour::get()->byID((int) $colourID) : null;
        $colour = $this->isValidColour($colour) ? $colour : null;

        $this->getOwner()->extend('updateColourByID', $colour, $colourID);

        return $colour;
    }

    /**
     * Find a colour by ID (numeric) or CSS name.
     */
    public function getColourByNameOrID(mixed $colourNameOrID): ?Colour
    {
        $colourNameOrID = $this->normaliseArgument($colourNameOrID);

        if ($colourNameOrID === null || $colourNameOrID === '') {
            return null;
        }

        return is_numeric($colourNameOrID)
            ? $this->getColourByID($colourNameOrID)
            : $this->getColourByName((string) $colourNameOrID);
    }

    /**
     * Lighten a colour (by ID or CSS name) by a percentage. Returns a hex code with leading #.
     */
    public function getLightenColour(mixed $colourNameOrID, int|float|string $amount = 0): ?string
    {
        $result = $this->getColourByNameOrID($colourNameOrID)?->getLightenedBy((float) $amount);

        $this->getOwner()->extend('updateLightenColour', $result, $colourNameOrID, $amount);

        return $result;
    }

    /**
     * Darken a colour (by ID or CSS name) by a percentage. Returns a hex code with leading #.
     */
    public function getDarkenColour(mixed $colourNameOrID, int|float|string $amount = 0): ?string
    {
        $result = $this->getColourByNameOrID($colourNameOrID)?->getDarkenedBy((float) $amount);

        $this->getOwner()->extend('updateDarkenColour', $result, $colourNameOrID, $amount);

        return $result;
    }

    /**
     * Template arguments can be DBField or DataObject instances, reduce them to a scalar value / ID.
     */
    protected function normaliseArgument(mixed $value): mixed
    {
        if ($value instanceof DataObject) {
            return $value->ID;
        }

        if ($value instanceof DBField) {
            return $value->getValue();
        }

        return is_scalar($value) || $value === null ? $value : null;
    }
}
