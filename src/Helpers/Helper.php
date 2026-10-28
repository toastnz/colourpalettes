<?php

namespace Toast\ColourPalettes\Helpers;

use SilverStripe\Control\Director;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Environment;
use SilverStripe\Core\Extensible;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Member;
use SilverStripe\Security\Security;
use SilverStripe\SiteConfig\SiteConfig;
use Toast\ColourPalettes\Models\Colour;

/**
 * Static helpers for the colour palette module.
 *
 * The public API stays static, but every method passes its result through
 * `Helper::singleton()->extend('update...')` so projects can adjust the output
 * with an Extension applied to this class:
 *
 * <code>
 * Toast\ColourPalettes\Helpers\Helper:
 *   extensions:
 *     - App\Extensions\MyHelperExtension
 * </code>
 */
class Helper
{
    use Extensible;
    use Injectable;
    use Configurable;

    /**
     * Number of active {@see Helper::withoutCSSGeneration()} calls.
     */
    protected static int $cssSuspensions = 0;

    /**
     * True if CSS generation was requested while it was suspended.
     */
    protected static bool $cssPending = false;

    /**
     * Get the current SiteConfig object.
     */
    public static function getCurrentSiteConfig(): ?SiteConfig
    {
        $siteConfig = SiteConfig::get()->setUseCache(true)->first();

        static::singleton()->extend('updateCurrentSiteConfig', $siteConfig);

        return $siteConfig;
    }

    /**
     * Check if the current user is the super admin.
     */
    public static function isSuperAdmin(?Member $member = null): bool
    {
        $member ??= Security::getCurrentUser();
        $defaultUser = Environment::getEnv('SS_DEFAULT_ADMIN_USERNAME');
        $isSuperAdmin = false;

        if ($member) {
            // Administrators count as super admins regardless of username when the bypass is enabled
            if (Config::inst()->get(SiteConfig::class, 'bypass_super_admin_check') && $member->inGroup('administrators')) {
                $isSuperAdmin = true;
            } else {
                $isSuperAdmin = $defaultUser && $member->Email === $defaultUser;
            }
        }

        static::singleton()->extend('updateIsSuperAdmin', $isSuperAdmin, $member);

        return (bool) $isSuperAdmin;
    }

    /**
     * Get an array of colour hex values keyed by Colour ID, optionally filtered by groups or callback.
     * Theme colours are listed first.
     *
     * @param string[] $groupsToInclude Only include colours in one of these groups (empty = all)
     * @param callable|null $filterFunction Receives a Colour and returns true to keep it
     * @return array<int, string>
     */
    public static function getColourPaletteArray(array $groupsToInclude = [], ?callable $filterFunction = null): array
    {
        $palette = [];
        $siteConfig = static::getCurrentSiteConfig();

        if ($siteConfig) {
            /** @var Colour[] $colours */
            $colours = $siteConfig->Colours()->sort('SortOrder')->toArray();

            if ($filterFunction) {
                $colours = array_filter($colours, $filterFunction);
            }

            $themeColours = array_filter($colours, fn(Colour $colour) => (bool) $colour->IsThemeColour);
            $normalColours = array_filter($colours, fn(Colour $colour) => !$colour->IsThemeColour);

            foreach (array_merge($themeColours, $normalColours) as $colour) {
                if ($colour->getValue() === 'transparent') {
                    continue;
                }

                $colourGroups = $colour->getColourGroups() ?: ['Global'];

                if ($groupsToInclude && !array_intersect($colourGroups, $groupsToInclude)) {
                    continue;
                }

                $palette[(int) $colour->ID] = (string) $colour->HexValue;
            }
        }

        static::singleton()->extend('updateColourPaletteArray', $palette, $groupsToInclude);

        return $palette;
    }

    /**
     * Get TinyMCE colour formats for the current site's colours.
     *
     * @return array<int, array<string, mixed>>|null
     */
    public static function getColoursForTinyMCE(): ?array
    {
        $siteConfig = static::getCurrentSiteConfig();
        $formats = null;

        if ($siteConfig) {
            $colourFormats = [];

            foreach ($siteConfig->Colours() as $colour) {
                if (!$colour->HexValue) {
                    continue;
                }

                $colourFormats[] = [
                    'title'          => 'Colours / ' . ucwords((string) $colour->Title),
                    'inline'         => 'span',
                    'classes'        => 'colour-' . $colour->getCSSReference(),
                    'wrapper'        => true,
                    'merge_siblings' => true,
                ];
            }

            $formats = [['title' => 'Colours', 'items' => $colourFormats]];
        }

        static::singleton()->extend('updateColoursForTinyMCE', $formats);

        return $formats;
    }

    /**
     * The prefix used for a site's generated CSS files ('mainsite' or 'subsite-{ID}').
     */
    public static function getCSSFileName(SiteConfig $siteConfig): string
    {
        $fileName = ((int) $siteConfig->ID === 1) ? 'mainsite' : 'subsite-' . $siteConfig->ID;

        static::singleton()->extend('updateCSSFileName', $fileName, $siteConfig);

        return $fileName;
    }

    /**
     * The folder (relative to the base folder, with leading and trailing slash) generated CSS is written to.
     *
     * Uses `SiteConfig.css_folder_path` (relative to the base folder) if set, otherwise
     * `SiteConfig.css_folder` inside the public folder.
     */
    public static function getCSSFolderPath(): string
    {
        $folderPath = (string) Config::inst()->get(SiteConfig::class, 'css_folder_path');

        if (!$folderPath) {
            $publicDir = trim((string) Director::publicDir(), '/');
            $cssFolder = trim((string) Config::inst()->get(SiteConfig::class, 'css_folder'), '/');
            $folderPath = '/' . implode('/', array_filter([$publicDir, $cssFolder])) . '/';
        }

        static::singleton()->extend('updateCSSFolderPath', $folderPath);

        return '/' . trim($folderPath, '/') . '/';
    }

    /**
     * The path of a site's generated theme CSS relative to the public folder (for Requirements::css()),
     * or null if the file isn't inside the public folder.
     */
    public static function getThemeCSSPublicPath(?SiteConfig $siteConfig = null): ?string
    {
        $themeCSSPath = static::getThemeCSSPath($siteConfig);
        $publicDir = trim((string) Director::publicDir(), '/');
        $prefix = $publicDir ? '/' . $publicDir . '/' : '/';

        if (!$themeCSSPath || !str_starts_with($themeCSSPath, $prefix)) {
            return null;
        }

        return substr($themeCSSPath, strlen($prefix));
    }

    /**
     * The path of a site's generated theme CSS, relative to the base folder.
     */
    public static function getThemeCSSPath(?SiteConfig $siteConfig = null): ?string
    {
        $siteConfig ??= static::getCurrentSiteConfig();

        if (!$siteConfig) {
            return null;
        }

        return static::getCSSFolderPath() . static::getCSSFileName($siteConfig) . '-theme.css';
    }

    /**
     * The path of a site's generated editor CSS, relative to the base folder.
     */
    public static function getEditorCSSPath(?SiteConfig $siteConfig = null): ?string
    {
        $siteConfig ??= static::getCurrentSiteConfig();

        if (!$siteConfig) {
            return null;
        }

        return static::getCSSFolderPath() . static::getCSSFileName($siteConfig) . '-editor.css';
    }

    /**
     * The contrast colours from config, keyed by name (always includes 'on-dark' and 'on-light').
     *
     * @return array<string, string>
     */
    public static function getContrastColours(): array
    {
        $config = Config::inst()->get(Colour::class, 'contrast_colours') ?: [];
        $contrastColours = ['on-dark' => '#ffffff', 'on-light' => '#000000'];

        foreach ($config as $key => $item) {
            // Config is a list of single-key maps, but a plain map is also accepted
            $contrastColours = is_array($item)
                ? array_merge($contrastColours, $item)
                : array_merge($contrastColours, [$key => $item]);
        }

        static::singleton()->extend('updateContrastColours', $contrastColours);

        return $contrastColours;
    }

    /**
     * Run a callback while CSS generation is suspended, then generate once (if anything asked for it).
     * Use this around bulk writes to avoid rewriting the CSS files for every record.
     *
     * @template T
     * @param callable(): T $callback
     * @return T
     */
    public static function withoutCSSGeneration(callable $callback): mixed
    {
        static::$cssSuspensions++;
        $completed = false;

        try {
            $result = $callback();
            $completed = true;

            return $result;
        } finally {
            static::$cssSuspensions--;

            // Don't generate while an exception is unwinding, a failure here would hide the original error
            if (static::$cssSuspensions === 0 && static::$cssPending) {
                static::$cssPending = false;

                if ($completed) {
                    static::generateCSSFiles();
                }
            }
        }
    }

    /**
     * Build the CSS custom properties block for a list of colours.
     *
     * @param iterable<Colour> $colours
     */
    public static function buildCSSVars(iterable $colours): string
    {
        $contrastColours = static::getContrastColours();
        $css = ':root {';

        foreach ($colours as $colour) {
            $cssReference = $colour->getCSSReference();
            $isDark = $colour->isDark();
            $contrast = $isDark ? $contrastColours['on-dark'] : $contrastColours['on-light'];
            $onContrast = $isDark ? $contrastColours['on-light'] : $contrastColours['on-dark'];

            $css .= "--colour-{$cssReference}: {$colour->getValue()};";
            $css .= "--colour-{$cssReference}-contrast: {$contrast};";
            $css .= "--colour-{$cssReference}-on-contrast: {$onContrast};";
        }

        $css .= '}';

        static::singleton()->extend('updateCSSVars', $css, $colours);

        return $css;
    }

    /**
     * Generate the theme and editor CSS files for a site's colour palette.
     * Defaults to the current SiteConfig. Returns true when both files were written.
     */
    public static function generateCSSFiles(?SiteConfig $siteConfig = null): bool
    {
        if (static::$cssSuspensions > 0) {
            static::$cssPending = true;
            return false;
        }

        if (!Security::database_is_ready()) {
            return false;
        }

        $siteConfig ??= static::getCurrentSiteConfig();

        if (!$siteConfig) {
            return false;
        }

        $colours = $siteConfig->Colours()->toArray();
        $cssVars = static::buildCSSVars($colours);
        $themeStyles = $cssVars;
        $editorStyles = $cssVars;

        foreach ($colours as $colour) {
            $cssReference = $colour->getCSSReference();
            $themeStyles .= $colour->getColourCSS('.colour-' . $cssReference);
            $editorStyles .= $colour->getColourCSS('body.mce-content-body  .colour-' . $cssReference);
        }

        static::singleton()->extend('updateCSSStyles', $themeStyles, $editorStyles, $siteConfig);

        $baseFolder = Director::baseFolder();
        $cssDir = $baseFolder . static::getCSSFolderPath();

        if (!is_dir($cssDir) && !@mkdir($cssDir, 0755, true) && !is_dir($cssDir)) {
            return false;
        }

        $themeWritten = static::writeFile($baseFolder . static::getThemeCSSPath($siteConfig), $themeStyles);
        $editorWritten = static::writeFile($baseFolder . static::getEditorCSSPath($siteConfig), $editorStyles);

        static::singleton()->extend('onAfterGenerateCSSFiles', $siteConfig, $themeWritten, $editorWritten);

        return $themeWritten && $editorWritten;
    }

    /**
     * Write a file atomically (temp file + rename) so concurrent requests never read a partial file.
     */
    protected static function writeFile(string $path, string $contents): bool
    {
        $tempPath = $path . '.' . uniqid('tmp', true);

        if (@file_put_contents($tempPath, $contents) === false) {
            return false;
        }

        if (!@rename($tempPath, $path)) {
            @unlink($tempPath);
            return false;
        }

        return true;
    }

    /**
     * Get all related colour styles for an object with has_one Colour relations.
     */
    public static function getRelatedColourStyles(DataObject $object, ?string $cssTarget = null): string
    {
        $relations = array_keys(array_filter($object->hasOne(), fn($class) => $class === Colour::class));
        $styles = '';

        foreach ($relations as $relation) {
            /** @var Colour|null $colour */
            $colour = $object->$relation();

            if ($colour && $colour->exists()) {
                $styles .= $colour->getScopedStyles($relation, $cssTarget);
            }
        }

        static::singleton()->extend('updateRelatedColourStyles', $styles, $object, $cssTarget);

        return $styles;
    }

    /**
     * Get the CSS names of the theme colours from config.
     *
     * @return string[]
     */
    public static function getThemeColoursFromConfig(): array
    {
        $themeColours = array_values(Colour::config()->get('theme_colours') ?: []);

        static::singleton()->extend('updateThemeColoursFromConfig', $themeColours);

        return $themeColours;
    }

    /**
     * Sync the IsThemeColour flag on all colours with the theme_colours config.
     */
    public static function setThemeColours(): void
    {
        $themeColours = static::getThemeColoursFromConfig();

        static::withoutCSSGeneration(function () use ($themeColours) {
            foreach (Colour::get() as $colour) {
                $isThemeColour = in_array($colour->CSSName, $themeColours, true);

                // Only write if the value has changed (the DB value is an int, so compare as bool)
                if ((bool) $colour->IsThemeColour === $isThemeColour) {
                    continue;
                }

                $colour->IsThemeColour = $isThemeColour;
                $colour->write();
            }
        });
    }
}
