<?php

namespace Toast\ColourPalettes\Models;

use SilverStripe\ORM\DB;
use SilverStripe\ORM\DataObject;
use SilverStripe\Forms\TextField;
use SilverStripe\Security\Security;
use SilverStripe\View\Requirements;
use SilverStripe\Forms\OptionsetField;
use SilverStripe\Forms\RequiredFields;
use SilverStripe\SiteConfig\SiteConfig;
use Toast\ColourPalettes\Helpers\Helper;
use TractorCow\Colorpicker\Forms\ColorField;

class Colour extends DataObject
{
    private static $table_name = 'Colour';

    private static $db = [
        'Title' => 'Varchar(255)',
        'Colour' => 'Color',
        'Contrast' => 'Varchar(30)',
        'CustomColourID' => 'Varchar(255)',
        'ColourPaletteID' => 'Varchar(30)',
        'SortOrder' => 'Int',
    ];

    private static $belongs_many_many = [
        'SiteConfigs' => SiteConfig::class
    ];

    private static $summary_fields = [
        'Title' => 'Title',
        'Colour.ColorCMS' => 'Colour',
        'CustomColourID' => 'Colour ID',
        'ID' => 'ID',
    ];

    private static $default_sort = 'ID ASC';

    public function getCMSFields()
    {
        Requirements::css('toastnz/colourpalettes: client/dist/styles/colour-palette-field.css');
        Requirements::javascript('toastnz/colourpalettes: client/dist/scripts/accessibility.js');

        $fields = parent::getCMSFields();
        $fields->removeByName([
            'SortOrder',
            'SiteConfigs',
            'CustomColourID',
            'ColourPaletteID',
            'Contrast'
        ]);

        $fields->addFieldsToTab('Root.Main', [
            TextField::create('Title', 'Title')
                ->setReadOnly(!$this->canChangeColour())
                ->setDescription($this->canChangeColour() ? (($this->CustomColourID) ? 'e.g. "' . $this->CustomColourID . '" - ' : '') . 'Please limit to 30 characters' : 'This is the default theme colour "' . $this->CustomColourID . '" and cannot be changed.'),
        ]);

        if ($this->ID) {
            $fields->addFieldsToTab('Root.Main', [
                ColorField::create('Colour', 'Colour')
                    ->setReadOnly(!$this->canChangeColour())
                    ->setDescription($this->canChangeColour() ? 'Please select a colour' : 'This is the default theme colour "' . $this->CustomColourID . '" and cannot be changed.'),
                OptionsetField::create('Contrast', 'Select Text Appearance', [
                    'light' => 'Light',
                    'dark' => 'Dark'
                ])->setDescription('Click on the text colour to be used when this theme colour is used as a background. If left unselected, a calculation will be made to determine the best text colour for legibility.')
            ]);

            // If the Title === 'editmode', then we are in edit mode and we can display the CustomColourID field
            if ($this->Title == 'editmode') {
                $fields->insertAfter('Title', TextField::create('CustomColourID', 'Custom ID'))
                    ->setDescription('You are now in edit mode, allowing you to set a custom ID for this button. If you don\'t know what this means, please leave it blank.');
            }
        } else {
            $fields->removeByName([
                'Colour',
                'Contrast'
            ]);
        }


        return $fields;
    }

    public function getCMSValidator()
    {
        $required = new RequiredFields(['Title', 'Colour']);

        $this->extend('updateCMSValidator', $required);

        return $required;
    }

    public function canDelete($member = null)
    {
        // Get the restricted colours
        $restricted = $this->getColourRestrictions();

        // Check to see if there is a key in the restricted array that matches the CustomColourID
        if (array_key_exists($this->CustomColourID, $restricted)) {
            return false;
        }

        return true;
    }

    public function canChangeColour($member = null)
    {
        // Get the restricted colours
        $restricted = $this->getColourRestrictions();

        if (array_key_exists($this->CustomColourID, $restricted)) {
            if ($restricted[$this->CustomColourID]['Colour']) {
                return false;
            }
        }

        return true;
    }


    public function requireDefaultRecords()
    {
        parent::requireDefaultRecords();

        if($siteConfig = Helper::getCurrentSiteConfig()){
            foreach ($this->getDefaultColours() as $colour) {
                $key = key($colour);
                $value = $colour[$key];

                $colours = $siteConfig->Colours();

                $existingRecord = $colours->filter([
                    'CustomColourID' => $key,
                    'SiteConfigs.ID' => $siteConfig->ID
                ])->first();

                if ($existingRecord) continue;

                $colour = new Colour();

                $colour->Title = $key;
                $colour->CustomColourID = $key;
                $colour->ColourPaletteID = 'ColourPalette-' . $key;

                if ($value) $colour->Colour = $value;

                $colour->write();

                $siteConfig->Colours()->add($colour->ID);

                DB::alteration_message("Colour '$key' created", 'created');
            }
        }
    }

    // Method to return the ID or CustomColourID
    public function getColourID()
    {
        return ($this->CustomColourID) ? $this->CustomColourID : $this->ID;
    }

    // Method to return the hex code
    public function getColourValue()
    {
        if ($colour = $this->Colour) {
            return '#' . $colour;
        }

        return 'transparent';
    }

    public function getColourIsDark()
    {
        if ($this->getColourBrightness() == 'dark') {
            return true;
        }

        return false;
    }

    public function getColourIsBright()
    {
        if ($this->getColourBrightness() == 'light') {
            return true;
        }

        return false;
    }

    // Method to return Brightness
    public function getColourBrightness()
    {
        // First let's check if the Contrast is set
        if ($this->Contrast) {
            // If it is, return that
            return $this->Contrast;
        }

        $hex = $this->Colour ?: 'ffffff';
        $r = hexdec(substr($hex,0,2));
        $g = hexdec(substr($hex,2,2));
        $b = hexdec(substr($hex,4,2));

        $yiq = (($r*299)+($g*587)+($b*114))/1000;

        return ( $yiq >= 130 ) ? 'light' : 'dark';
    }

    public function getColourCSS($target)
    {
        // If there is a colour object, return the style tag
        return $target . ' { color: var(--colour-' . $this->ColourID . ');}';
    }

    // Method to get the restrictions for the colours
    public function getColourRestrictions()
    {
        $retrictions = [];

        foreach ($this->getDefaultColours() as $colour) {
            // We need to get the key, which is the name of the colour
            $name = key($colour);
            // We also need to get the value, which is the hex code
            $value = $colour[$name];

            // The colour cannot be deleted, if it is in the default colours
            // The colour's Colour value cannot be updated, if the $value is not null
            $retrictions[$name] = [
                'Colour' => ($value) ? true : false,
            ];

            // True means the field is read only
        }

        return $retrictions;
    }

    // Method to get the default colours
    protected function getDefaultColours()
    {
        return $this->config()->get('default_colours') ?: [];
    }

    public function generateCSS()
    {
        // if database and siteconfig is ready, run this
        if (Security::database_is_ready()) {
            if ($this->ID && Helper::getCurrentSiteConfig()) Helper::generateCSSFiles();
        }
    }

    public function onBeforeWrite()
    {
        parent::onBeforeWrite();

        $this->ColourPaletteID = 'ColourPalette-' . $this->ID;

        // If the title is empty, set it to the CustomColourID
        if (!$this->Title) return $this->Title = $this->getColourID();
    }

    public function onAfterWrite()
    {
        parent::onAfterWrite();

        $this->generateCSS();
    }

    public function onAfterSkippedWrite()
    {
        $this->generateCSS();
    }
}
