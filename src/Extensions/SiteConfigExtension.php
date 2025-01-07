<?php

namespace Toast\ColourPalettes\Extensions;

use SilverStripe\Forms\TabSet;
use SilverStripe\Core\Extension;
use SilverStripe\Forms\FieldList;
use SilverStripe\Security\Security;
use Toast\ColourPalettes\Models\Colour;
use Toast\ColourPalettes\Helpers\Helper;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldDeleteAction;
use Symbiote\GridFieldExtensions\GridFieldOrderableRows;
use SilverStripe\Forms\GridField\GridFieldConfig_RecordEditor;

class SiteConfigExtension extends Extension
{
    private static $many_many = [
        'Colours' => Colour::class,
    ];

    public function updateCMSFields(FieldList $fields)
    {
        $fields->removeByName([
            'Colours',
        ]);

        if (Security::database_is_ready() && Helper::isSuperAdmin()) {
            $coloursConfig = GridFieldConfig_RecordEditor::create(50);
            $coloursConfig->addComponent(GridFieldOrderableRows::create('SortOrder'));
            $coloursConfig->removeComponentsByType(GridFieldDeleteAction::class);

            $colours = $this->owner->Colours();

            $coloursField = GridField::create(
                'Colours',
                'Colour Palette',
                $colours,
                $coloursConfig
            );

            // if Root.Customization doesn't exist, create it
            if (!$fields->fieldByName('Root.Customization')) {
                $fields->addFieldToTab('Root', TabSet::create('Customization'));
            }

            $fields->removeByName([
                'Colours',
            ]);

            $fields->addFieldToTab('Root.Customization.ColourPalette', $coloursField);
        }
    }

    public function onAfterWrite()
    {
        // only create colours if the site has been created, and there are no colours already
        if($this->owner->ID && !$this->owner->Colours()->exists()){
            $colour = new Colour();
            $colour->requireDefaultRecords();
        }

        Helper::generateCSSFiles();
    }
}
