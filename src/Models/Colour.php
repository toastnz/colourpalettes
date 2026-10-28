<?php

namespace Toast\ColourPalettes\Models;

use SilverStripe\Core\Validation\ValidationResult;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\ListboxField;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Forms\OptionsetField;
use SilverStripe\Forms\TextField;
use SilverStripe\Model\ArrayData;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DB;
use SilverStripe\Security\Security;
use SilverStripe\SiteConfig\SiteConfig;
use SilverStripe\View\Requirements;
use Toast\ColourPalettes\Helpers\Helper;
use TractorCow\Colorpicker\Color;
use TractorCow\Colorpicker\Forms\ColorField;

/**
 * A single colour in a site's palette.
 *
 * Every public method that returns a computed value passes it through an `update*` extension hook
 * (e.g. `updateBrightness`, `updateLightenedBy`), so projects can adjust results without subclassing.
 *
 * @property string $Title
 * @property string $HexValue Hex code without the leading #
 * @property string $CSSName
 * @property string $Colour Legacy field, superseded by HexValue
 * @property string $CustomColourID Legacy field, superseded by CSSName
 * @property string $Groups JSON encoded list of group names
 * @property string $ContrastColour 'light' or 'dark'
 * @property bool $IsThemeColour
 * @property int $SortOrder
 * @property int $ReferenceColourID
 * @method Colour ReferenceColour()
 */
class Colour extends DataObject
{
    private static string $table_name = 'Colour';

    /**
     * @var array<string, string>
     */
    private static array $db = [
        'Title'          => 'Varchar(255)',
        'HexValue'       => Color::class,
        'CSSName'        => 'Varchar(255)',
        'Colour'         => 'Color', // Legacy field, to be removed in future
        'CustomColourID' => 'Varchar(255)', // Legacy field, to be removed in future
        'Groups'         => 'Text',
        'ContrastColour' => 'Varchar(8)',
        'IsThemeColour'  => 'Boolean(0)',
        'SortOrder'      => 'Int',
    ];

    /**
     * @var array<string, class-string>
     */
    private static array $has_one = [
        'ReferenceColour' => self::class,
    ];

    /**
     * @var array<string, class-string>
     */
    private static array $belongs_many_many = [
        'SiteConfigs' => SiteConfig::class,
    ];

    /**
     * @var array<string, bool|string>
     */
    private static array $indexes = [
        'CSSName' => true,
    ];

    /**
     * @var array<string, string>
     */
    private static array $summary_fields = [
        'Title'             => 'Title',
        'HexValue.ColorCMS' => 'Colour',
        'getGroupsSummary'  => 'Groups',
        'getCSSReference'   => 'CSS Reference',
    ];

    private static string $default_sort = 'ID ASC';

    public function getCMSFields(): FieldList
    {
        Requirements::css('toastnz/colourpalettes: client/dist/styles/main.css');
        Requirements::javascript('toastnz/colourpalettes: client/dist/scripts/accessibility.js');
        Requirements::javascript('toastnz/colourpalettes: client/dist/scripts/colour-names.js');

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
            'IsThemeColour',
            'Colour', // Legacy field, to be removed in future
            'CustomColourID', // Legacy field, to be removed in future
        ]);

        // Add the necessary fields to the Main tab
        $fields->addFieldsToTab('Root.Main', [
            TextField::create('Title', 'Title')->setReadOnly(!$canChangeColour),
            ColorField::create('HexValue', 'Hex Code Value')->setReadOnly(!$canChangeColour),
        ]);

        if ($this->HexValue) {
            $fields->insertBefore(
                'HexValue',
                OptionsetField::create('ContrastColour', 'Select the option with the highest contrast', [
                    'light' => 'Light',
                    'dark'  => 'Dark',
                ])->setDescription('Click on the text colour to be used when this theme colour is used as a background. If left unselected, a calculation will be made to determine the best text colour for legibility.')
            );
        }

        if (!$canChangeColour) {
            $fields->addFieldToTab('Root.Main', LiteralField::create('ReadOnlyNotice', '<p class="message warning">This colour cannot be changed or removed.</p>'));
        } elseif (!$canRemoveColour) {
            $fields->addFieldToTab('Root.Main', LiteralField::create('ReadOnlyNotice', '<p class="message warning">This colour cannot be removed.</p>'));
        }

        // Add the Groups field
        $this->getCMSGroupsField($fields);

        return $fields;
    }

    /**
     * Add the Groups field to the CMS fields (only when groups are configured).
     */
    public function getCMSGroupsField(FieldList $fields): void
    {
        // Get the colour groups from the config
        $groups = $this->getColourGroupsConfig();

        // If there are no groups, there is nothing to select
        if (!$groups) {
            return;
        }

        // Add a new group called "Global" to the array
        $groups = array_merge(['Global' => 'Global'], $groups);

        $groupsField = ListboxField::create('Groups', 'Groups', $groups)
            ->setDescription('Select the groups this colour belongs to. If left blank, it will be treated as a global colour.');

        $this->extend('updateCMSGroupsField', $groupsField, $fields);

        $fields->addFieldToTab('Root.Main', $groupsField);
    }

    /**
     * Enforce the read-only state of locked default colours server side (the CMS fields are only disabled).
     * Setting a value for the first time (e.g. a legacy migration) is still allowed.
     */
    public function validate(): ValidationResult
    {
        $result = parent::validate();

        if ($this->isInDB() && !$this->getColourRestrictions()->canChange) {
            $changed = $this->getChangedFields(true, DataObject::CHANGE_VALUE);

            foreach (['Title', 'HexValue'] as $field) {
                if (isset($changed[$field]) && (string) $changed[$field]['before'] !== '') {
                    $result->addFieldError($field, sprintf('%s cannot be changed for the "%s" colour.', $field, $this->CSSName));
                }
            }
        }

        return $result;
    }

    /**
     * @param \SilverStripe\Security\Member|null $member
     */
    public function canDelete($member = null): bool
    {
        // Colours defined in config can never be removed
        if (!$this->getColourRestrictions()->canRemove) {
            return false;
        }

        return (bool) parent::canDelete($member);
    }

    /**
     * Work out whether this colour can be changed / removed, based on the default_colours config.
     * A default colour can't be removed, and if the config gives it a value it can't be changed either.
     *
     * @return object{canChange: bool, canRemove: bool}
     */
    public function getColourRestrictions(): object
    {
        $canChange = true;
        $canRemove = true;

        $defaultColours = $this->getDefaultColours();
        $cssName = (string) $this->CSSName;

        if ($cssName !== '' && array_key_exists($cssName, $defaultColours)) {
            $canRemove = false;

            if ($defaultColours[$cssName]) {
                $canChange = false;
            }
        }

        $restrictions = (object) [
            'canChange' => $canChange,
            'canRemove' => $canRemove,
        ];

        $this->extend('updateColourRestrictions', $restrictions);

        return $restrictions;
    }

    public function requireDefaultRecords(): void
    {
        parent::requireDefaultRecords();

        $siteConfig = Helper::getCurrentSiteConfig();

        if (!$siteConfig) {
            return;
        }

        $this->createDefaultColours($siteConfig);
    }

    /**
     * Migrate legacy fields, then create any default colours (from the default_colours config)
     * that are missing from the given SiteConfig.
     *
     * @return Colour[] The newly created colours
     */
    public function createDefaultColours(?SiteConfig $siteConfig = null): array
    {
        $siteConfig ??= Helper::getCurrentSiteConfig();

        if (!$siteConfig) {
            return [];
        }

        return Helper::withoutCSSGeneration(function () use ($siteConfig): array {
            $created = [];

            // 1. Migrate CustomColourID / Colour to CSSName / HexValue
            $this->migrateLegacyFields();

            // 2. Only create missing default colours (matched by CSSName, or legacy CustomColourID)
            $themeColours = $this->getThemeColours();

            foreach ($this->getDefaultColours() as $cssName => $hexValue) {
                $exists = $siteConfig->Colours()->filterAny([
                    'CSSName:nocase'        => $cssName,
                    'CustomColourID:nocase' => $cssName,
                ])->exists();

                if ($exists) {
                    continue;
                }

                $colour = static::create();
                $colour->CSSName = $cssName;

                if ($hexValue) {
                    $colour->HexValue = $hexValue;
                }

                // If this is a theme colour, set the flag
                $colour->IsThemeColour = in_array($cssName, $themeColours, true);
                $colour->write();

                $siteConfig->Colours()->add($colour->ID);
                $created[] = $colour;

                DB::alteration_message("Colour '{$cssName}' created", 'created');
            }

            $this->extend('onAfterCreateDefaultColours', $created, $siteConfig);

            return $created;
        });
    }

    /**
     * Copy the legacy Colour / CustomColourID fields into HexValue / CSSName.
     * Safe to run repeatedly, only colours that haven't been migrated yet are touched.
     */
    public function migrateLegacyFields(): int
    {
        $migrated = 0;

        $toMigrate = static::get()
            ->where('"Colour"."CSSName" IS NULL OR "Colour"."CSSName" = \'\'')
            ->where('"Colour"."CustomColourID" IS NOT NULL AND "Colour"."CustomColourID" != \'\'');

        foreach ($toMigrate as $legacy) {
            if (!$legacy->Colour) {
                continue;
            }

            $legacy->CSSName = $legacy->CustomColourID;
            $legacy->HexValue = $legacy->Colour;
            $legacy->write();
            $migrated++;
        }

        return $migrated;
    }

    /**
     * After a database build, sync the theme colour flags with config and regenerate the CSS files.
     */
    public function onAfterBuild(): void
    {
        parent::onAfterBuild();

        if (!Security::database_is_ready() || !Helper::getCurrentSiteConfig()) {
            return;
        }

        Helper::setThemeColours();
        Helper::generateCSSFiles();
    }

    /**
     * Get the CSS colour value, e.g. '#ff0000', or 'transparent' if there is no hex value.
     */
    public function getValue(): string
    {
        // Only output valid hex, as the value is written straight into generated / inline CSS
        $hex = (string) $this->HexValue;
        $value = preg_match('/^[a-fA-F0-9]{3,8}$/', $hex) ? '#' . $hex : 'transparent';

        $this->extend('updateValue', $value);

        return $value;
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
    public function isLight(): bool
    {
        return $this->getBrightness() === 'light';
    }

    /**
     * Returns 'light' or 'dark' based on the colour's brightness, unless a ContrastColour has been chosen.
     * Handles 3/6 digit hex and validates input.
     */
    public function getBrightness(): string
    {
        $brightness = 'light'; // fallback

        if ($this->ContrastColour) {
            $brightness = (string) $this->ContrastColour;
        } elseif ($rgb = $this->getColourRGBArray()) {
            [$r, $g, $b] = $rgb;
            $yiq = (($r * 299) + ($g * 587) + ($b * 114)) / 1000;
            $brightness = ($yiq >= 130) ? 'light' : 'dark';
        }

        $this->extend('updateBrightness', $brightness);

        return $brightness;
    }

    /**
     * Converts the colour's hex value to HSL: ['H' => 0-360, 'S' => 0-100, 'L' => 0-100].
     * Optionally adjusts H (wraps around), S and L (clamped) by the given amounts.
     * Returns null if the hex value is invalid.
     */
    public function getHSL(int $hAdjust = 0, int $sAdjust = 0, int $lAdjust = 0): ?ArrayData
    {
        $hsl = null;

        if ($rgb = $this->getColourRGBArray()) {
            [$r, $g, $b] = array_map(fn(int $channel) => $channel / 255, $rgb);

            $max = max($r, $g, $b);
            $min = min($r, $g, $b);
            $h = $s = 0.0;
            $l = ($max + $min) / 2;

            if ($max !== $min) {
                $d = $max - $min;
                $s = $l > 0.5 ? $d / (2 - $max - $min) : $d / ($max + $min);
                $h = match ($max) {
                    $r      => ($g - $b) / $d + ($g < $b ? 6 : 0),
                    $g      => ($b - $r) / $d + 2,
                    default => ($r - $g) / $d + 4,
                };
                $h /= 6;
            }

            $hsl = ArrayData::create([
                'H' => (((int) round($h * 360) + $hAdjust) % 360 + 360) % 360,
                'S' => max(0, min(100, (int) round($s * 100) + $sAdjust)),
                'L' => max(0, min(100, (int) round($l * 100) + $lAdjust)),
            ]);
        }

        $this->extend('updateHSL', $hsl, $hAdjust, $sAdjust, $lAdjust);

        return $hsl;
    }

    /**
     * Relative luminance (WCAG) scaled to 0-100, or null if the hex value is invalid.
     */
    public function getLuminance(): ?int
    {
        $luminance = null;

        if ($rgb = $this->getColourRGBArray()) {
            [$r, $g, $b] = array_map(function (int $channel): float {
                $channel /= 255;
                return ($channel <= 0.03928) ? $channel / 12.92 : (($channel + 0.055) / 1.055) ** 2.4;
            }, $rgb);

            $luminance = (int) round(((0.2126 * $r) + (0.7152 * $g) + (0.0722 * $b)) * 100);
        }

        $this->extend('updateLuminance', $luminance);

        return $luminance;
    }

    /**
     * The colour as [r, g, b] integers (0-255), or null if the hex value is missing / invalid.
     * Accepts 3 and 6 digit hex.
     *
     * @return int[]|null
     */
    public function getColourRGBArray(): ?array
    {
        $hex = preg_replace('/[^a-fA-F0-9]/', '', (string) $this->HexValue);

        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }

        $rgb = strlen($hex) === 6
            ? [(int) hexdec(substr($hex, 0, 2)), (int) hexdec(substr($hex, 2, 2)), (int) hexdec(substr($hex, 4, 2))]
            : null;

        $this->extend('updateColourRGBArray', $rgb);

        return $rgb;
    }

    /**
     * Lighten the colour by a percentage (0-100), returns a hex code with leading #.
     */
    public function getLightenedBy(int|float $amount = 0): ?string
    {
        $result = $this->shiftBrightness(abs($amount));

        $this->extend('updateLightenedBy', $result, $amount);

        return $result;
    }

    /**
     * Darken the colour by a percentage (0-100), returns a hex code with leading #.
     */
    public function getDarkenedBy(int|float $amount = 0): ?string
    {
        $result = $this->shiftBrightness(-abs($amount));

        $this->extend('updateDarkenedBy', $result, $amount);

        return $result;
    }

    /**
     * Add (or subtract if negative) a percentage of 255 to each channel.
     */
    protected function shiftBrightness(int|float $percent): ?string
    {
        $rgb = $this->getColourRGBArray();

        if (!$rgb) {
            return null;
        }

        $offset = 255 * max(-100, min(100, $percent)) / 100;
        [$r, $g, $b] = array_map(fn(int $channel) => (int) max(0, min(255, $channel + $offset)), $rgb);

        return sprintf('#%02x%02x%02x', $r, $g, $b);
    }

    /**
     * Returns a CSS variable name for a relation (e.g. 'PrimaryColourID' => 'primary-colour').
     */
    public function getCSSVarName(string $relation): string
    {
        $varName = strtolower(preg_replace('/([a-z])([A-Z])/', '$1-$2', preg_replace('/ID$/', '', $relation)));

        $this->extend('updateCSSVarName', $varName, $relation);

        return $varName;
    }

    /**
     * Returns an array of CSS variable definitions for this colour.
     *
     * @return string[]
     */
    public function getCSSVarsArray(string $relation, string $prefix = '--_'): array
    {
        $varName = $this->getCSSVarName($relation);
        $cssReference = $this->getCSSReference();

        $vars = [
            $prefix . $varName . ': var(--colour-' . $cssReference . ');',
            $prefix . $varName . '-contrast: var(--colour-' . $cssReference . '-contrast);',
            $prefix . $varName . '-on-contrast: var(--colour-' . $cssReference . '-on-contrast);',
        ];

        $this->extend('updateCSSVarsArray', $vars, $relation, $prefix);

        return $vars;
    }

    /**
     * Returns the CSS variable definitions as a string for this colour.
     */
    public function getCSSVars(string $relation = ''): string
    {
        $vars = implode('', $this->getCSSVarsArray($relation));

        $this->extend('updateCSSVars', $vars, $relation);

        return $vars;
    }

    /**
     * Returns the root CSS variable definitions as a string for this colour.
     */
    public function getRootVars(string $relation = ''): string
    {
        $vars = str_replace('--_', '--', $this->getCSSVars($relation));

        $this->extend('updateRootVars', $vars, $relation);

        return $vars;
    }

    /**
     * Returns a CSS rule for the colour.
     */
    public function getColourCSS(?string $target = null): string
    {
        $cssReference = $this->getCSSReference();
        $target = $target ?: '.colour-' . $cssReference;

        $css = $target . ' { ' . $this->getCSSVars('colour') . ' color: var(--colour-' . $cssReference . '); }';

        $this->extend('updateColourCSS', $css, $target);

        return $css;
    }

    /**
     * Returns scoped CSS styles for a relation and optional selector.
     */
    public function getScopedStyles(string $relation, ?string $cssSelector = null): string
    {
        if (!$relation) {
            return '';
        }

        if ($cssSelector) {
            $styles = $cssSelector . ' {' . implode('', $this->getCSSVarsArray($relation)) . '}';
            $styles .= $cssSelector . '.cms-preview {' . implode('', $this->getCSSVarsArray($relation, '--_preview-')) . '}';
        } else {
            $styles = $this->getRootVars($relation);
        }

        $this->extend('updateScopedStyles', $styles, $relation, $cssSelector);

        return $styles;
    }

    /**
     * The default_colours config as a map of CSS name => hex value (null if the value is editable).
     * The config is written as a list of single-key maps, e.g. `- white: 'ffffff'`.
     *
     * @return array<string, string|null>
     */
    public function getDefaultColours(): array
    {
        $defaultColours = static::normaliseConfigMap($this->config()->get('default_colours'));

        $this->extend('updateDefaultColours', $defaultColours);

        return $defaultColours;
    }

    /**
     * The CSS names of the theme colours from the theme_colours config.
     *
     * @return string[]
     */
    public function getThemeColours(): array
    {
        $themeColours = array_values($this->config()->get('theme_colours') ?: []);

        $this->extend('updateThemeColours', $themeColours);

        return $themeColours;
    }

    /**
     * Flatten a config list of single-key maps (or plain strings) into one map.
     *
     * @return array<string, mixed>
     */
    protected static function normaliseConfigMap(?array $config): array
    {
        $map = [];

        foreach ($config ?: [] as $key => $item) {
            if (is_array($item)) {
                foreach ($item as $name => $value) {
                    $map[(string) $name] = $value;
                }
            } elseif (is_string($key)) {
                $map[$key] = $item;
            } else {
                $map[(string) $item] = null;
            }
        }

        return $map;
    }

    /**
     * The groups this colour belongs to.
     *
     * @return string[]
     */
    public function getColourGroups(): array
    {
        $groups = $this->Groups ? json_decode((string) $this->Groups, true) : [];
        $groups = is_array($groups) ? $groups : [];

        $this->extend('updateColourGroups', $groups);

        return $groups;
    }

    /**
     * The available colour groups from config, as a map of name => name (for dropdowns).
     *
     * @return array<string, string>
     */
    public function getColourGroupsConfig(): array
    {
        $options = [];

        foreach ($this->config()->get('colour_groups') ?: [] as $group) {
            $options[$group] = $group;
        }

        $this->extend('updateColourGroupsConfig', $options);

        return $options;
    }

    public function getGroupsSummary(): string
    {
        $groups = $this->getColourGroups();
        $summary = $groups ? implode(', ', $groups) : 'Global';

        $this->extend('updateGroupsSummary', $summary);

        return $summary;
    }

    /**
     * Regenerate the CSS files for the current site (no-op until the record exists).
     */
    public function generateCSS(): void
    {
        if (!$this->exists()) {
            return;
        }

        $this->extend('onBeforeGenerateCSS');

        Helper::generateCSSFiles();

        $this->extend('onAfterGenerateCSS');
    }

    /**
     * The name used in CSS variables / classes: the CSSName, falling back to the ID.
     */
    public function getCSSReference(): string
    {
        // Restrict to characters that are valid in CSS custom property / class names
        $reference = preg_replace('/[^a-zA-Z0-9_-]/', '-', (string) ($this->CSSName ?: $this->ID));

        $this->extend('updateCSSReference', $reference);

        return $reference;
    }

    /**
     * Copy the hex and contrast values from the reference colour (theme colours only).
     */
    public function inheritColourFromReference(bool $write = false): void
    {
        // Only theme colours with a (different) reference colour inherit
        if (!$this->IsThemeColour || !$this->ReferenceColourID || (int) $this->ReferenceColourID === (int) $this->ID) {
            return;
        }

        $referenceColour = $this->ReferenceColour();

        if (!$referenceColour || !$referenceColour->exists()) {
            return;
        }

        $oldHexValue = $this->HexValue;
        $oldContrastColour = $this->ContrastColour;

        // Inherit the values from the reference colour
        $this->HexValue = $referenceColour->HexValue;
        $this->ContrastColour = $referenceColour->ContrastColour;

        $this->extend('onAfterInheritColour', $referenceColour);

        // Only write if requested and there are changes
        if ($write && ($this->HexValue !== $oldHexValue || $this->ContrastColour !== $oldContrastColour)) {
            $this->write();
        }
    }

    protected function onBeforeWrite(): void
    {
        parent::onBeforeWrite();

        // Set IsThemeColour based on whether the CSS name is in the theme colours list
        $this->IsThemeColour = in_array($this->getCSSReference(), Helper::getThemeColoursFromConfig(), true);

        // If the title is empty, fall back to the CSS name
        if (!$this->Title) {
            $this->Title = $this->CSSName ?: 'Colour';
        }

        // Inherit the colour from the reference if set
        $this->inheritColourFromReference();
    }

    /**
     * Update colours referencing this one, then regenerate the CSS (once) after the record is written.
     */
    protected function onAfterWrite(): void
    {
        parent::onAfterWrite();

        Helper::withoutCSSGeneration(function (): void {
            /** @var Colour $colour */
            foreach (static::get()->filter('ReferenceColourID', $this->ID) as $colour) {
                $colour->inheritColourFromReference(true);
            }

            $this->generateCSS();
        });
    }

    protected function onBeforeDelete(): void
    {
        parent::onBeforeDelete();

        // Detach colours that reference this one
        Helper::withoutCSSGeneration(function (): void {
            /** @var Colour $colour */
            foreach (static::get()->filter('ReferenceColourID', $this->ID) as $colour) {
                $colour->ReferenceColourID = 0;
                $colour->HexValue = null;
                $colour->ContrastColour = null;
                $colour->write();
            }
        });
    }

    /**
     * Regenerate the CSS so the deleted colour is removed from the files.
     */
    protected function onAfterDelete(): void
    {
        parent::onAfterDelete();

        Helper::generateCSSFiles();
    }
}
