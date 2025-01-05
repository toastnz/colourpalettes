<?php

namespace Toast\ColourPalettes\Extensions;

use SilverStripe\Core\Extension;
use SilverStripe\Security\Security;
use Toast\ColourPalettes\Helpers\Helper;

class DatabaseAdminExtension extends Extension
{
    public function onAfterBuild()
    {
         //generate all the required css files by theme colours
         if (Security::database_is_ready()) {
            // theme colours
            if (Helper::getCurrentSiteConfig()) Helper::generateCSSFiles();
        }
    }
}
