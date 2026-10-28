<?php

namespace Toast\ColourPalettes\Extensions;

use PageController;
use SilverStripe\Control\Director;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Extension;
use SilverStripe\SiteConfig\SiteConfig;
use SilverStripe\View\Requirements;
use Toast\ColourPalettes\Helpers\Helper;

/**
 * Adds the current site's generated theme CSS to every page.
 *
 * By default the CSS is inlined. Set `SiteConfig.inline_theme_css: false` to link the file instead,
 * so browsers can cache it.
 *
 * @extends Extension<PageController>
 */
class PageControllerExtension extends Extension
{
    protected function onBeforeInit(): void
    {
        if (!Config::inst()->get(SiteConfig::class, 'inline_theme_css') && $this->linkThemeCSS()) {
            return;
        }

        $css = $this->getThemeCSS();

        if ($css) {
            Requirements::customCSS($css, 'colourpalettes-theme');
        }
    }

    /**
     * Link the theme CSS file. Returns false if it can't be linked (e.g. it's outside the public folder).
     */
    public function linkThemeCSS(): bool
    {
        $publicPath = Helper::getThemeCSSPublicPath();

        if (!$publicPath) {
            return false;
        }

        if (!file_exists(Director::publicFolder() . '/' . $publicPath)) {
            Helper::generateCSSFiles();
        }

        if (!file_exists(Director::publicFolder() . '/' . $publicPath)) {
            return false;
        }

        $this->getOwner()->extend('updateThemeCSSPublicPath', $publicPath);

        Requirements::css($publicPath);

        return true;
    }

    /**
     * The generated theme CSS for the current site. The file is generated first if it's missing.
     */
    public function getThemeCSS(): ?string
    {
        $css = null;

        if ($themeCssPath = Helper::getThemeCSSPath()) {
            $filePath = Director::baseFolder() . $themeCssPath;

            if (!file_exists($filePath)) {
                Helper::generateCSSFiles();
            }

            if (file_exists($filePath)) {
                $css = file_get_contents($filePath) ?: null;
            }
        }

        $this->getOwner()->extend('updateThemeCSS', $css);

        return $css;
    }
}
