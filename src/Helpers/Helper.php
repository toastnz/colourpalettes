<?php

namespace Toast\ColourPalettes\Helpers;

use SilverStripe\ORM\DataObject;
use SilverStripe\Control\Director;
use SilverStripe\Core\Environment;
use SilverStripe\Security\Security;
use SilverStripe\Core\Config\Config;
use SilverStripe\SiteConfig\SiteConfig;
use Toast\ColourPalettes\Models\Colour;


class Helper
{
    /**
     * Get the current SiteConfig object.
     * @return SiteConfig|null
     */
    public static function getCurrentSiteConfig(): ?SiteConfig
    {
        return DataObject::get_one(SiteConfig::class) ?: null;
    }

    /**
     * Check if the current user is the super admin.
     * @return bool
     */
    public static function isSuperAdmin(): bool
    {
        $defaultUser = Environment::getEnv('SS_DEFAULT_ADMIN_USERNAME');
        $currentUser = Security::getCurrentUser();
        return $defaultUser && $currentUser && $currentUser->Email === $defaultUser;
    }


    /**
     * Get an array of colour hex values, optionally filtered by groups or callback.
     * @param array $groupsToInclude
     * @param callable|null $filterFunction
     * @return array<int, string>
     */
    public static function getColourPaletteArray(array $groupsToInclude = [], $filterFunction = null): array
    {
        $siteConfig = self::getCurrentSiteConfig();
        if (!$siteConfig) return [];
        $colours = $siteConfig->Colours();
        if ($filterFunction && is_callable($filterFunction)) {
            $colours = $colours->filterByCallback($filterFunction);
        }
        $normalColours = $colours->filterByCallback(fn($c) => !$c->isThemeColour())->sort('SortOrder');
        $themeColours = $colours->filterByCallback(fn($c) => $c->isThemeColour())->sort('SortOrder');
        $allColours = array_merge($themeColours->toArray(), $normalColours->toArray());
        $palette = [];
        foreach ($allColours as $colour) {
            if ($colour->getValue() === 'transparent') continue;
            $colourGroups = $colour->getColourGroups();
            if (empty($colourGroups)) $colourGroups[] = 'Global';
            $include = empty($groupsToInclude) || count(array_intersect($colourGroups, $groupsToInclude)) > 0;
            if (!$include) continue;
            $palette[$colour->ID] = $colour->HexValue;
        }
        return $palette;
    }

    /**
     * Get TinyMCE colour formats for the current site's colours.
     * @return array|null
     */
    public static function getColoursForTinyMCE(): ?array
    {
        $siteConfig = self::getCurrentSiteConfig();
        if (!$siteConfig) return null;
        $colours = $siteConfig->Colours();
        $colourFormats = [];
        foreach ($colours as $colour) {
            if (!$colour->HexValue) continue;
            $title = ucwords($colour->Title);
            $colourFormats[] = [
                'title'          => 'Colours / ' . $title,
                'inline'         => 'span',
                'classes'        => 'colour-' . $colour->getCSSReference(),
                'wrapper'        => true,
                'merge_siblings' => true,
            ];
        }
        return [['title' => 'Colours', 'items' => $colourFormats]];
    }

    /**
     * Generate CSS files for the current site's colour palette.
     * @return void
     */
    public static function generateCSSFiles(): void
    {
        $siteConfig = self::getCurrentSiteConfig();
        if (!$siteConfig) return;
        $colours = $siteConfig->Colours();
        if (!$colours) return;
        $fileName = ($siteConfig->ID == 1) ? 'mainsite' : 'subsite-' . $siteConfig->ID;
        $contrastColoursConfig = Config::inst()->get(Colour::class, 'contrast_colours');
        $contrastColours = array_merge(...($contrastColoursConfig ?? []));
        $folderPath = Config::inst()->get(SiteConfig::class, 'css_folder_path');
        $baseFolder = Director::baseFolder();
        $cssDir = $baseFolder . $folderPath;
        if (!file_exists($cssDir)) {
            mkdir($cssDir, 0777, true);
        }
        $themeCSSFilePath = $cssDir . $fileName . '-theme.css';
        $editorCSSFilePath = $cssDir . $fileName . '-editor.css';
        @unlink($themeCSSFilePath);
        @unlink($editorCSSFilePath);
        $CSSVars = ':root {';
        foreach ($colours as $colour) {
            $cssReference = $colour->getCSSReference();
            $value = $colour->getValue();
            $contrast = $colour->isDark() ? $contrastColours['on-dark'] : $contrastColours['on-light'];
            $onContrast = $colour->isDark() ? $contrastColours['on-light'] : $contrastColours['on-dark'];
            $CSSVars .= "--colour-{$cssReference}: {$value};";
            $CSSVars .= "--colour-{$cssReference}-contrast: {$contrast};";
            $CSSVars .= "--colour-{$cssReference}-on-contrast: {$onContrast};";
        }
        $CSSVars .= '}';
        $themeStyles = $CSSVars;
        $editorStyles = $CSSVars;
        foreach ($colours as $colour) {
            $cssReference = $colour->getCSSReference();
            $themeStyles .= $colour->getColourCSS('.colour-' . $cssReference);
            $editorStyles .= $colour->getColourCSS('body.mce-content-body  .colour-' . $cssReference);
        }
        try {
            file_put_contents($themeCSSFilePath, $themeStyles);
            file_put_contents($editorCSSFilePath, $editorStyles);
        } catch (\Exception $e) {
            // Optionally log error
        }
    }

    /**
     * Get all related colour styles for an object with has_one Colour relations.
     * @param DataObject $object
     * @param string|null $cssTarget
     * @return string
     */
    public static function getRelatedColourStyles(DataObject $object, ?string $cssTarget = null): string
    {
        $colours = array_filter($object->hasOne(), fn($class) => $class === Colour::class);
        $styles = array_map(function ($relation) use ($object, $cssTarget) {
            $colour = $object->$relation();
            return $colour && $colour->exists() ? $colour->getScopedStyles($relation, $cssTarget) : '';
        }, array_keys($colours));
        return implode('', $styles);
    }
}
