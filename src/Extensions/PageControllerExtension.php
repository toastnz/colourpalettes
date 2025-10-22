<?php

namespace Toast\ColourPalettes\Extensions;

use SilverStripe\Core\Extension;
use SilverStripe\Control\Director;
use SilverStripe\View\Requirements;
use SilverStripe\Core\Config\Config;
use Toast\ColourPalettes\Helpers\Helper;
use SilverStripe\SiteConfig\SiteConfig;

class PageControllerExtension extends Extension
{
    // onbeforeinit
    public function onBeforeInit()
    {
        $themeCssFilePath = null;

        // Grab the SiteConfig
        if($siteConfig =  Helper::getCurrentSiteConfig()){
            $siteID = $siteConfig->ID;

            // Get the theme ID / Name
            $theme = ($siteID == 1) ? 'mainsite' : 'subsite-' . $siteID;

            $folderPath = Config::inst()->get(SiteConfig::class, 'css_folder_path');
            $themeCssFilePath = $folderPath . $theme . '-theme.css';

            if ($themeCssFilePath){
                if (!file_exists(Director::baseFolder() .$themeCssFilePath)){
                    $result = Helper::generateCSSFiles($themeCssFilePath);
                }

                if (file_exists(Director::baseFolder() .$themeCssFilePath)) {
                    Requirements::customCSS(file_get_contents(Director::baseFolder() .$themeCssFilePath));
                    // $cssFile = ModuleResourceLoader::resourceURL($themeCssFilePath);
                    // Requirements::css($cssFile);
                }
            }
        }

    }
}
