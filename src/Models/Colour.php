<?php

namespace Toast\ColourPalettes\Models;

use SilverStripe\ORM\DB;
use SilverStripe\ORM\DataObject;
use SilverStripe\Forms\TextField;
use TractorCow\Colorpicker\Color;
use SilverStripe\Security\Security;
use SilverStripe\View\Requirements;
use SilverStripe\Forms\ListboxField;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Forms\OptionsetField;
use SilverStripe\SiteConfig\SiteConfig;
use Toast\ColourPalettes\Helpers\Helper;
use TractorCow\Colorpicker\Forms\ColorField;
use SilverStripe\Core\Validation\ValidationResult;

class Colour extends DataObject
{
    private static $table_name = 'Colour';

    private static $db = [
        'Title'          => 'Varchar(255)',
        'HexValue'       => Color::class,
        'CSSName'        => 'Varchar(255)',
        'Colour'        =>   'Color', // Legacy field, to be removed in future
        'CustomColourID' => 'Varchar(255)', // Legacy field, to be removed in future
        'Groups'         => 'Text',
        'ContrastColour' => 'Varchar(8)',
        'SortOrder'      => 'Int',
    ];

    private static $has_one = [
        'ReferenceColour' => self::class,
    ];

    private static $belongs_many_many = [
        'SiteConfigs' => SiteConfig::class
    ];

    private static $summary_fields = [
        'Title' => 'Title',
        'HexValue.ColorCMS' => 'Colour',
        'getGroupsSummary' => 'Groups',
        'getCSSReference' => 'CSS Reference',
    ];

    private static $default_sort = 'ID ASC';

    public function getCMSFields()
    {
        Requirements::css('toastnz/colourpalettes: client/dist/styles/colour-palette-field.css');
        Requirements::javascript('toastnz/colourpalettes: client/dist/scripts/accessibility.js');

        $fields = parent::getCMSFields();
        $restrictions = $this->getColourRestrictions();
        $canChangeColour = $restrictions->canChange;
        $canRemoveColour = $restrictions->canRemove;

        // Remove unnecessary fields
        $fields->removeByName([
            'Groups',
            'CSSName',
            'HexValue',
            'SortOrder',
            'SiteConfigs',
            'ContrastColour',
            'ReferenceColourID',
            'Colour', // Legacy field, to be removed in future
            'CustomColourID' // Legacy field, to be removed in future
        ]);

        // Add the necessary fields to the Main tab
        $fields->addFieldsToTab('Root.Main', [
            TextField::create('Title', 'Title')->setReadOnly(!$canChangeColour),
            ColorField::create('HexValue', 'Hex Code Value')->setReadOnly(!$canChangeColour)
        ]);

        if ($this->HexValue) {
            $fields->insertBefore(
                'HexValue',
                OptionsetField::create('ContrastColour', 'Select the option with the highest contrast', [
                    'light' => 'Light',
                    'dark' => 'Dark'
                ])->setDescription('Click on the text colour to be used when this theme colour is used as a background. If left unselected, a calculation will be made to determine the best text colour for legibility.'),
            );
        }

        if (!$canChangeColour) {
            $fields->addFieldToTab('Root.Main', LiteralField::create('ReadOnlyNotice', '<p class="message warning">This colour cannot be changed or removed.</p>'));
        } else if (!$canRemoveColour) {
            $fields->addFieldToTab('Root.Main', LiteralField::create('ReadOnlyNotice', '<p class="message warning">This colour cannot be removed.</p>'));
        }

        // Add the Groups field
        $this->getCMSGroupsField($fields);

        return $fields;
    }

    public function getCMSGroupsField($fields)
    {
        // Get the colour groups from the config
        $groups = $this->getColourGroupsConfig();

        // If there are no groups, return the fields now
        if (count($groups) == 0) return null;

        // Add a new group called "Global" to the array
        $groups = array_merge(['Global' => 'Global'], $groups);

        $fields->addFieldToTab('Root.Main', ListboxField::create('Groups', 'Groups', $groups)->setDescription('Select the groups this colour belongs to. If left blank, it will be treated as a global colour.'));
    }

    // Ensure the Colour has a Hex Value before saving
    // public function validate(): ValidationResult
    // {
    //     $result = parent::validate();
    //     if ($this->isInDB()) {
    //         if(!$this->HexValue){
    //             $result->addFieldError('HexValue', 'A valid Hex Value for ' . $this->Title . ' is required.');
    //         }
    //     }
    //     return $result;
    // }

    public function canDelete($member = null)
    {
        return $this->getColourRestrictions()->canRemove;
    }

    public function getColourRestrictions($member = null)
    {
        $defaultColours = $this->getDefaultColours();
        $canChange = true;
        $canRemove = true;

        if (count($defaultColours) > 0) {
            foreach ($defaultColours as $colour) {
                $colourKey = key($colour);
                $colourValue = $colour[$colourKey];

                // If the CSSName matches the key, we cannot remove it
                if ($this->CSSName == $colourKey) {
                    $canRemove = false;
                }

                // If the CSSName matches the key, and there is a value, we cannot change it
                if ($this->CSSName == $colourKey && $colourValue) {
                    $canChange = false;
                    break;
                }
            }
        }

        return (object)[
            'canChange' => $canChange,
            'canRemove' => $canRemove
        ];
    }

    public function isThemeColour()
    {
        $restrictions = $this->getColourRestrictions();
        return ($this->CSSName != null && $restrictions->canChange) ? true : false;
    }

    public function requireDefaultRecords()
    {
        parent::requireDefaultRecords();
        $siteConfig = Helper::getCurrentSiteConfig();
        if (!$siteConfig) return;
        $defaultColours = $this->getDefaultColours();
        if (count($defaultColours) == 0) return;

        // 1. Migrate CustomColourID to CSSName if needed
        if (self::singleton()->hasField('CustomColourID') && self::singleton()->hasField('Colour')) {
            $toMigrate = self::get()->filter(['CSSName' => null])->exclude(['CustomColourID' => null]);
            foreach ($toMigrate as $legacy) {
                if (!$legacy->Colour) {
                    continue;
                }
                $legacy->CSSName = $legacy->CustomColourID;
                $legacy->HexValue = $legacy->Colour; // Migrate the HexValue too
                $legacy->write();
            }
        }

        // 2. Only create missing default colours by CSSName or legacy CustomColourID
        foreach ($defaultColours as $colour) {
            $colourKey = key($colour);
            $colourValue = $colour[$colourKey];
            $exists = $siteConfig->Colours()->filterAny([
                'CSSName:nocase' => $colourKey
            ]);
            // If CustomColourID exists, check it too
            if (self::get()->hasField('CustomColourID')) {
                $exists = $siteConfig->Colours()->filterAny([
                    'CSSName:nocase' => $colourKey,
                    'CustomColourID:nocase' => $colourKey
                ]);
            }
            if ($exists->count() > 0) {
                continue;
            }
            $colourObj = new self();
            $colourObj->CSSName = $colourKey;
            if ($colourValue) $colourObj->HexValue = $colourValue;
            $colourObj->write();
            $siteConfig->Colours()->add($colourObj->ID);
            DB::alteration_message("Colour '$colourKey' created", 'created');
        }
    }

    // Method to return the hex code
    public function getValue()
    {
        return $this->HexValue ? '#' . $this->HexValue : 'transparent';
    }

    /**
     * Returns true if the colour is dark.
     */
    public function isDark(): bool
    {
        return $this->getBrightness() === 'dark';
    }

    /**
     * Returns true if the colour is bright/light.
     */
    public function isBright(): bool
    {
        return $this->getBrightness() === 'light';
    }

    /**
     * Returns 'light' or 'dark' based on the colour's brightness.
     * Handles 3/6 digit hex and validates input.
     */
    public function getBrightness(): string
    {
        if ($this->ContrastColour) {
            return $this->ContrastColour;
        }
        $hex = preg_replace('/[^a-fA-F0-9]/', '', (string)$this->HexValue);
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        if (strlen($hex) !== 6) {
            return 'light'; // fallback
        }
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        $yiq = (($r * 299) + ($g * 587) + ($b * 114)) / 1000;
        return ($yiq >= 130) ? 'light' : 'dark';
    }


    /**
     * Returns a CSS variable name for a relation (e.g. 'PrimaryColourID' => 'primary-colour').
     */
    public function getCSSVarName(string $relation): string
    {
        return strtolower(preg_replace('/([a-z])([A-Z])/', '$1-$2', str_replace('ID', '', $relation)));
    }

    /**
     * Returns an array of CSS variable definitions for this colour.
     * @param string $relation
     * @param string $prefix
     * @return string[]
     */
    public function getCSSVarsArray(string $relation, string $prefix = '--_'): array
    {
        $varName = $this->getCSSVarName($relation);
        $cssReference = $this->getCSSReference();
        return [
            $prefix . $varName . ': var(--colour-' . $cssReference . ');',
            $prefix . $varName . '-contrast: var(--colour-' . $cssReference . '-contrast);',
            $prefix . $varName . '-on-contrast: var(--colour-' . $cssReference . '-on-contrast);',
        ];
    }

    /**
     * Returns the CSS variable definitions as a string for this colour.
     */
    public function getCSSVars(string $relation): string
    {
        return implode('', $this->getCSSVarsArray($relation));
    }

    /**
     * Returns the root CSS variable definitions as a string for this colour.
     */
    public function getRootVars(string $relation): string
    {
        return str_replace('--_', '--', $this->getCSSVars($relation));
    }

    /**
     * Returns a CSS rule for the colour.
     */
    public function getColourCSS(?string $target = null): string
    {
        $cssReference = $this->getCSSReference();
        if (!$target) {
            $target = '.colour-' . $cssReference;
        }
        return $target . ' { ' . $this->getCSSVars('colour') . ' color: var(--colour-' . $cssReference . '); }';
    }

    /**
     * Returns scoped CSS styles for a relation and optional selector.
     */
    public function getScopedStyles(string $relation, ?string $cssSelector = null): string
    {
        if (!$relation) return '';
        $varName = $this->getCSSVarName($relation);
        $cssReference = $this->getCSSReference($relation);
        $styles = '';
        if ($cssSelector) {
            $styles .= $cssSelector . ' {';
            $styles .= implode('', $this->getCSSVarsArray($relation));
            $styles .= '}';
            $styles .= $cssSelector . '.cms-preview {';
            $styles .= implode('', $this->getCSSVarsArray($relation, '--_preview-'));
            $styles .= '}';
        } else {
            $styles .= $this->getRootVars($relation);
        }
        return $styles;
    }

    // Method to get the default colours from the yml config
    protected function getDefaultColours()
    {
        return $this->config()->get('default_colours') ?: [];
    }

    public function getColourGroups()
    {
        return $this->Groups ? json_decode($this->Groups, true) : [];
    }

    // A method to get the colour groups
    public function getColourGroupsConfig()
    {
        $colourGroupsConfig = $this->config()->get('colour_groups') ?: [];

        // Transform the array to use the title as the value
        $options = [];
        foreach ($colourGroupsConfig as $group) {
            $options[$group] = $group;
        }

        return $options;
    }

    public function getGroupsSummary()
    {
        $groups = $this->getColourGroups();

        if (empty($groups)) {
            return 'Global';
        }

        // Join the groups with a comma
        return implode(', ', $groups);
    }

    public function generateCSS()
    {
        // Return if this Colour doesn't exist yet
        if (!$this->exists()) return;
        // Exit if the database is not ready
        if (!Security::database_is_ready()) return;
        // Return if there is no current site config
        if (!Helper::getCurrentSiteConfig()) return;

        // Generate the CSS files
        Helper::generateCSSFiles();
    }

    public function getCSSReference()
    {
        return ($this->CSSName) ?? $this->ID;
    }

    public function inheritColourFromReference($write = false)
    {
        // Make sure this colour is a theme colour
        if (!$this->isThemeColour()) return;
        // Check if there is a reference colour set
        if (!$this->ReferenceColourID) return;
        // Prevent self-referencing
        if ($this->ReferenceColourID == $this->ID) return;

        // Get the reference colour
        $referenceColour = $this->ReferenceColour();

        // If there is no reference colour, return
        if (!$referenceColour || !$referenceColour->exists()) return;

        $oldHexValue = $this->HexValue;
        $oldContrastColour = $this->ContrastColour;

        // Inherit the values from the reference colour
        $this->HexValue = $referenceColour->HexValue;
        $this->ContrastColour = $referenceColour->ContrastColour;

        // Return here if we are not writing
        if (!$write) return;
        // Only write if there are changes
        if ($this->HexValue === $oldHexValue && $this->ContrastColour === $oldContrastColour) return;

        // Write the changes
        $this->write();
    }

    public function onBeforeWrite()
    {
        parent::onBeforeWrite();

        // Use the CSSName if it exists, otherwise fallback to the ID
        $cssName = $this->getCSSReference();

        // If the title is empty, set it to the CSSReference
        if (!$this->Title) return $this->Title = $cssName;

        // Inherit the colour from the reference if set
        $this->inheritColourFromReference();
    }

    // Make sure we regenerate the CSS after the record is written
    public function onAfterWrite()
    {
        parent::onAfterWrite();

        // Loop through all the other colours, and check if any are referencing this one
        $referencingColours = self::get()->filter('ReferenceColourID', $this->ID);

        foreach ($referencingColours as $colour) {
            $colour->inheritColourFromReference(true);
        }

        $this->generateCSS();
    }

    public function onBeforeDelete()
    {
        parent::onBeforeDelete();

        // Loop through all the other colours, and check if any are referencing this one
        $referencingColours = self::get()->filter('ReferenceColourID', $this->ID);

        foreach ($referencingColours as $colour) {
            $colour->ReferenceColourID = null;
            $colour->HexValue = null;
            $colour->ContrastColour = null;
            $colour->write();
        }
    }

    // Make sure we regenerate the CSS after a write is skipped
    public function onAfterSkippedWrite()
    {
        $this->generateCSS();
    }
}
