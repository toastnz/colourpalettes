<?php

namespace Toast\ColourPalettes\Extensions;

use SilverStripe\Core\Extension;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldConfig;
use SilverStripe\Forms\GridField\GridFieldConfig_RecordEditor;
use SilverStripe\Forms\GridField\GridFieldEditButton;
use SilverStripe\Forms\HeaderField;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Forms\ReadonlyField;
use SilverStripe\Forms\TabSet;
use SilverStripe\Model\List\SS_List;
use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\ORM\FieldType\DBHTMLText;
use SilverStripe\Security\Security;
use SilverStripe\SiteConfig\SiteConfig;
use Symbiote\GridFieldExtensions\GridFieldEditableColumns;
use Symbiote\GridFieldExtensions\GridFieldOrderableRows;
use Toast\ColourPalettes\Fields\ColourPaletteField;
use Toast\ColourPalettes\Helpers\Helper;
use Toast\ColourPalettes\Models\Colour;

/**
 * Adds the colour palette to the SiteConfig.
 *
 * @extends Extension<SiteConfig>
 * @method \SilverStripe\ORM\ManyManyList<Colour> Colours()
 */
class SiteConfigColourExtension extends Extension
{
    /**
     * @var array<string, class-string>
     */
    private static array $many_many = [
        'Colours' => Colour::class,
    ];

    /**
     * Update the CMS fields to include the Colours and Theme Colours grid fields.
     */
    protected function updateCMSFields(FieldList $fields): void
    {
        $fields->removeByName(['Colours']);

        if (!Helper::isSuperAdmin() || !Security::database_is_ready()) {
            return;
        }

        $this->addCustomisationTab($fields);

        $coloursConfig = GridFieldConfig_RecordEditor::create(50);
        $coloursConfig->addComponent(GridFieldOrderableRows::create('SortOrder'));
        $coloursConfig->removeComponentsByType(GridFieldEditButton::class);
        $coloursField = GridField::create('Colours', 'Colours', $this->getColoursForCMS(), $coloursConfig);
        $fields->addFieldToTab('Root.Customization.ColourPalette', $coloursField);

        // Exit here if we have no theme colours
        $themeColours = $this->getThemeColoursForCMS();

        if (!$themeColours || !$themeColours->count()) {
            return;
        }

        // Set up the theme colours grid field
        $themeColoursConfig = GridFieldConfig::create()->addComponent($editableColumns = GridFieldEditableColumns::create());
        $editableColumns->setDisplayFields([
            'CSSName' => [
                'title' => 'Title',
                'field' => ReadonlyField::create('CSSName', 'CSS Name'),
            ],
            'ReferenceColourID' => [
                'title'    => 'Select Colour',
                'callback' => fn($record, $column, $grid) => ColourPaletteField::create($column, $column, ['Global'], fn(Colour $item) => !$item->IsThemeColour),
            ],
        ]);

        $fields->addFieldsToTab('Root.Customization.ColourPalette', [
            HeaderField::create('ThemeColoursHeader', 'Theme Colours')->setHeadingLevel(2),
            GridField::create('ThemeColours', 'Theme Colours', $themeColours, $themeColoursConfig),
            LiteralField::create('ThemeColoursSpace', '<br><br><br>'),
        ], 'Colours');
    }

    /**
     * Get the theme colours for the CMS, allowing extensions to modify the list.
     */
    public function getThemeColoursForCMS(): ?SS_List
    {
        $themeColours = null;

        if ($this->getOwner()->isInDB()) {
            $themeColours = $this->getOwner()->Colours()->filter('IsThemeColour', 1);
        }

        $this->getOwner()->extend('updateThemeColoursForCMS', $themeColours);

        return $themeColours;
    }

    /**
     * Get the (non-theme) colours for the CMS, allowing extensions to modify the list.
     */
    public function getColoursForCMS(): SS_List
    {
        $colours = $this->getOwner()->Colours()->exclude('IsThemeColour', 1)->sort('SortOrder');

        $this->getOwner()->extend('updateColoursForCMS', $colours);

        return $colours;
    }

    /**
     * Add the Customization tab to the CMS if it doesn't exist.
     */
    public function addCustomisationTab(FieldList $fields): void
    {
        if (!$fields->fieldByName('Root.Customization')) {
            $fields->addFieldToTab('Root', TabSet::create('Customization'));
        }

        $this->getOwner()->extend('updateCustomisationTab', $fields);
    }

    /**
     * Get the site's colour CSS variables as HTMLText.
     */
    public function getSiteColourCSSVars(): DBHTMLText
    {
        $styles = Helper::getRelatedColourStyles($this->getOwner());

        $this->getOwner()->extend('updateSiteColourCSSVars', $styles);

        /** @var DBHTMLText $field */
        $field = DBField::create_field(DBHTMLText::class, $styles);

        return $field;
    }

    /**
     * After write, ensure default colours exist and regenerate the CSS files.
     */
    protected function onAfterWrite(): void
    {
        $owner = $this->getOwner();

        if ($owner->ID && !$owner->Colours()->exists()) {
            Colour::singleton()->createDefaultColours($owner);
        }

        Helper::generateCSSFiles($owner);
    }
}
