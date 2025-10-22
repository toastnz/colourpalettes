<?php

namespace Toast\ColourPalettes\Extensions;

use Dom\Text;
use SilverStripe\Forms\TabSet;
use SilverStripe\Core\Extension;
use SilverStripe\Forms\FieldList;
use SilverStripe\Security\Security;
use SilverStripe\ORM\FieldType\DBField;
use Toast\ColourPalettes\Models\Colour;
use Toast\ColourPalettes\Helpers\Helper;
use SilverStripe\Forms\GridField\GridField;
use Symbiote\GridFieldExtensions\GridFieldOrderableRows;
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
        $isSuperAdmin = Helper::isSuperAdmin();
        $databaseReady = Security::database_is_ready();
        $colours = $this->getColoursForCMS();
        $fields->removeByName(['Colours']);
        $this->addCustomisationTab($fields);
        if (!$isSuperAdmin || !$databaseReady) {
            return;
        }
        $coloursConfig = GridFieldConfig_RecordEditor::create(50);
        $coloursConfig->addComponent(new GridFieldOrderableRows('SortOrder'));
        $coloursConfig->removeComponentsByType('GridFieldEditButton');
        $coloursField = GridField::create('Colours', 'Colours', $colours, $coloursConfig);
        $fields->addFieldToTab('Root.Customization.ColourPalette', $coloursField);
    }

    /**
     * Get the colours for the CMS, allowing extensions to modify the list.
     *
     * @return \SilverStripe\ORM\ManyManyList
     */
    public function getColoursForCMS()
    {
        $colours = $this->owner->Colours();
        $this->owner->extend('updateColoursForCMS', $colours);
        return $colours;
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
