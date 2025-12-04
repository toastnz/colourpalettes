<?php

namespace Toast\ColourPalettes\Extensions;

use Dom\Text;
use SilverStripe\Forms\TabSet;
use SilverStripe\Core\Extension;
use SilverStripe\Forms\FieldList;
use SilverStripe\ORM\ManyManyList;
use SilverStripe\Forms\HeaderField;
use SilverStripe\Security\Security;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Forms\ReadonlyField;
use SilverStripe\Model\List\ArrayList;
use SilverStripe\ORM\FieldType\DBField;
use Toast\ColourPalettes\Models\Colour;
use Toast\ColourPalettes\Helpers\Helper;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldConfig;
use Toast\ColourPalettes\Fields\ColourPaletteField;
use Symbiote\GridFieldExtensions\GridFieldOrderableRows;
use Symbiote\GridFieldExtensions\GridFieldEditableColumns;
use SilverStripe\Forms\GridField\GridFieldConfig_RecordEditor;
use Toast\ColourPalettes\Helpers\Helper as ColourPalettesHelper;


class SiteConfigColourExtension extends Extension
{
    /**
     * @var array<string, class-string>
     */
    private static $many_many = [
        'Colours' => Colour::class,
    ];

    /**
     * Update the CMS fields to include the Colours grid field.
     *
     * @param FieldList $fields
     * @return void
     */
    public function updateCMSFields(FieldList $fields): void
    {
        $fields->removeByName(['Colours']);

        $isSuperAdmin = Helper::isSuperAdmin();
        $databaseReady = Security::database_is_ready();

        if (!$isSuperAdmin || !$databaseReady) return;

        $colours = $this->getColoursForCMS();

        $this->addCustomisationTab($fields);

        $coloursConfig = GridFieldConfig_RecordEditor::create(50);
        $coloursConfig->addComponent(new GridFieldOrderableRows('SortOrder'));
        $coloursConfig->removeComponentsByType('GridFieldEditButton');
        $coloursField = GridField::create('Colours', 'Colours', $colours, $coloursConfig);
        $fields->addFieldToTab('Root.Customization.ColourPalette', $coloursField);


        // Get the theme colours
        $themeColours = $this->getThemeColoursForCMS();

        // Exit here if we have no theme colours
        if (!$themeColours || !$themeColours->count()) return;

        // Set up the colours grid field config
        $themeColoursConfig = GridFieldConfig::create()->addComponent(new GridFieldEditableColumns());
        $themeColoursField = GridField::create('ThemeColours', 'Theme Colours', $themeColours, $themeColoursConfig);

        // Configure display fields for theme colours grid
        $themeColoursField->getConfig()->getComponentByType(GridFieldEditableColumns::class)->setDisplayFields([
            'Title' => [
                'title' => 'Title',
                'callback' => fn($record, $column, $grid) => ReadonlyField::create($column, $column, $record->Title),
            ],
            'ReferenceColourID' => [
                'title' => 'Select Colour',
                'callback' => fn($record, $column, $grid) => ColourPaletteField::create($column, $column, ['Global'], fn($item) => !$item->isThemeColour()),
            ],
        ]);

        $fields->addFieldsToTab('Root.Customization.ColourPalette', [
            HeaderField::create('ThemeColoursHeader', 'Theme Colours')->setHeadingLevel(2),
            $themeColoursField,
            LiteralField::create('ThemeColoursSpace', '<br><br><br>'),
        ], 'Colours');
    }

    /**
     * Get the theme colours for the CMS.
     *
     * @return ArrayList|null
     */
    private function getThemeColoursForCMS(): ?ArrayList
    {
        if ($this->owner->isInDB()) {
            $themeColours = $this->owner->Colours()->filterByCallback(function ($colour) {
                return $colour->isThemeColour();
            });

            return $themeColours;
        }

        return null;
    }



    /**
     * Get the colours for the CMS, allowing extensions to modify the list.
     *
     * @return ArrayList
     */
    public function getColoursForCMS(): ArrayList
    {
        // Get all the colours related to the SiteConfig
        $colours = $this->owner->Colours();

        // Remove the colours that are theme colours
        $filteredColours = $colours->filterByCallback(function ($colour) {
            return !$colour->isThemeColour();
        });

        // Allow extensions to modify the colours list
        $this->owner->extend('updateColoursForCMS', $filteredColours);

        // Return the list of colours
        return $filteredColours;
    }

    /**
     * Add the Customization tab to the CMS if it doesn't exist.
     *
     * @param FieldList $fields
     * @return void
     */
    public function addCustomisationTab(FieldList $fields): void
    {
        if ($fields->fieldByName('Root.Customization')) {
            return;
        }
        $fields->addFieldToTab('Root', TabSet::create('Customization'));
    }

    /**
     * Get the site's colour CSS variables as HTMLText.
     *
     * @return DBField
     */
    public function getSiteColourCSSVars(): DBField
    {
        $styles = ColourPalettesHelper::getRelatedColourStyles($this->owner);
        return DBField::create_field('HTMLText', $styles);
    }

    /**
     * After write, ensure default colours exist and regenerate CSS files.
     *
     * @return void
     */
    public function onAfterWrite(): void
    {
        if ($this->owner->ID && !$this->owner->Colours()->count()) {
            $colour = new Colour();
            $colour->requireDefaultRecords();
        }
        Helper::generateCSSFiles();
    }

    /**
     * After skipped write, regenerate CSS files.
     *
     * @return void
     */
    public function onAfterSkippedWrite(): void
    {
        Helper::generateCSSFiles();
    }
}
