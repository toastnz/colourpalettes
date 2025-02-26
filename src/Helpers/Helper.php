<?php

namespace Toast\ColourPalettes\Helpers;

use SilverStripe\ORM\DataObject;
use SilverStripe\Control\Director;
use SilverStripe\Core\Environment;
use SilverStripe\Security\Security;
use SilverStripe\Core\Config\Config;
use SilverStripe\SiteConfig\SiteConfig;

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

    static function getColourPaletteArray($group = null)
    {
        // Get the current site config ID
        $siteConfig = self::getCurrentSiteConfig();
        // Get the colour palette for the current site
        $colours = $siteConfig->Colours();

        $hexValues = [];
        $palette = [];

        $groups = null;

        // Loop through the colours and add them to the palette array
        foreach ($colours as $colour) {
            // If the colour is transparent, skip it
            if ($colour->ColourValue == 'transparent') continue;

            // If the hexValue is already in the array, skip it
            if (in_array($colour->ColourValue, $hexValues)) continue;

            // Grab the groups if we haven't already
            if ($groups == null) {
                $groups = $colour->getColourGroups();
            }

            if (count($groups) > 0) {
                // If a group is set, check if the colour is in the group
                if ($group && $colour->ColourGroup != $group) continue;
                // If there is no group, but the colour has a group, skip it
                if (!$group && $colour->ColourGroup) continue;
            }

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
                        $CSSVars .= '--colour-' . $colour->getColourID() . ': ' . $colour->getColourValue() . ';';
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
