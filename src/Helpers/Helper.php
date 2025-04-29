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
    static function getCurrentSiteConfig()
    {
        if ($siteConfig = DataObject::get_one(SiteConfig::class)) {
            return $siteConfig;
        }
        return;
    }

    static function isSuperAdmin()
    {
        if ($defaultUser = Environment::getEnv('SS_DEFAULT_ADMIN_USERNAME')) {
            if ($currentUser = Security::getCurrentUser()) {
                return $currentUser->Email == $defaultUser;
            }
        }
        return false;
    }

    static function getColour($id)
    {
        $siteConfig = Helper::getCurrentSiteConfig();
        $colours = $siteConfig->Colours();

        if (!$id) return null;

        foreach ($colours as $colour) {
            if ($colour->ColourPaletteID == $id || $colour->ID == $id) {
                return $colour;
            }
        }

        return null;
    }

    static function getColourPaletteArray($groupsToInclude = [])
    {
        // Get the current site config ID
        $siteConfig = self::getCurrentSiteConfig();
        // Get the colour palette for the current site
        $colours = $siteConfig->Colours();

        // Sort the $colours by sort order
        $colours = $colours->sort('SortOrder');

        $hexValues = [];
        $palette = [];

        // Loop through the colours and add them to the palette array
        foreach ($colours as $colour) {
            // If the colour is transparent, skip it
            if ($colour->ColourValue == 'transparent') continue;

            // If the hexValue is already in the array, skip it
            if (in_array($colour->ColourValue, $hexValues)) continue;

            // This colour's groups
            $colourGroups = $colour->getColourGroups();

            // If the colourGroups is empty, add 'Global' to it
            if (count($colourGroups) == 0) $colourGroups[] = 'Global';

            $include = count($groupsToInclude) == 0 ? true : false;

            // If there are any groups in the config
            if (count($groupsToInclude) > 0) {
                // If any colour groups match, set include to true
                if (count(array_intersect($colourGroups, $groupsToInclude)) > 0) $include = true;
            }

            if (!$include) continue;

            // Add the colour value to the hexValues array
            $hexValues[] = $colour->ColourValue;

            // Get the ID
            $id = $colour->ColourPaletteID;
            // Add the colour to the palette
            $palette[$id] = $id;
        }

        return $palette;
    }

    static function getColoursForTinyMCE()
    {
        // Get the current site's config
        if ($siteConfig = self::getCurrentSiteConfig()) {
            // Get the site's colours
            $colours = $siteConfig->Colours();

            $formats = [];
            $colourFormats = [];

            // get current colours
            foreach ($colours as $colour) {
                // If the colour has not been set, skip it
                if (!$colour->Colour) continue;

                // Grab the title and make it title case
                $title = ucwords($colour->Title);

                // Set up the colour formats
                $colourFormats[] = [
                    'title'          => 'Colours / ' . $title,
                    'inline'         => 'span',
                    'classes'        => 'colour-' . $colour->ColourID,
                    'wrapper'        => true,
                    'merge_siblings' => true,
                ];
            }

            $formats[] = [
                'title' => 'Colours',
                'items' => $colourFormats,
            ];

            return $formats;
        }
    }

    static function generateCSSFiles()
    {
        // Get the current site's config
        if ($siteConfig = self::getCurrentSiteConfig()) {
            // Get the site' ID and append to the css file name
            $styleID = ($siteConfig->ID == 1) ? 'mainsite' : 'subsite-' . $siteConfig->ID;
            // Get the site's colours
            $colours = $siteConfig->Colours();

            // If we have colours
            if ($colours) {
                $contrastColoursConfig = Config::inst()->get(Colour::class, 'contrast_colours');
                $contrastColours = array_merge(...$contrastColoursConfig ?? []);
                //get folder path from config
                $folderPath = Config::inst()->get(SiteConfig::class, 'css_folder_path');
                // if folder doesnt exist, create it
                if (!file_exists(Director::baseFolder() . $folderPath)) {
                    mkdir(Director::baseFolder() . $folderPath, 0777, true);
                }

                $CSSFilePath = Director::baseFolder() . $folderPath;

                $themeCSSFilePath = $CSSFilePath . $styleID . '-theme.css';
                $editorCSSFilePath = $CSSFilePath . $styleID . '-editor.css';

                // Remove files if they exist
                if (file_exists($themeCSSFilePath)) unlink($themeCSSFilePath);
                if (file_exists($editorCSSFilePath)) unlink($editorCSSFilePath);

                // Create a new file
                $CSSVars = ':root {';

                // Loop through colours and add CSS vars
                foreach ($colours as $colour) {
                    if ($colour->Colour) {
                        $contrast = $colour->getColourIsDark() ? $contrastColours['on-dark'] : $contrastColours['on-light'];
                        $CSSVars .= '--colour-' . $colour->getColourID() . ': ' . $colour->getColourValue() . ';';
                        $CSSVars .= '--colour-on-' . $colour->getColourID() . ': #' . $contrast . ';';
                    }
                }

                // Close the file
                $CSSVars .= '}';

                // Create a new file for the theme
                $themeStyles = $CSSVars;
                // Create a new file for the editor
                $editorStyles = $CSSVars;

                // Loop through colours and add styles
                foreach ($colours as $colour) {
                    if ($colour->Colour) {
                        $colourID = $colour->getColourID();
                        // Theme styles
                        $themeStyles .= $colour->getColourCSS('.colour-' . $colourID);

                        // Editor styles
                        $editorStyles .= $colour->getColourCSS('body.mce-content-body  .colour-' . $colourID);
                    }
                }

                // Write to file
                try {
                    file_put_contents($themeCSSFilePath, $themeStyles);
                    file_put_contents($editorCSSFilePath, $editorStyles);
                } catch (\Exception $e) {
                    // Do nothing
                }
            }
        }
    }
}
