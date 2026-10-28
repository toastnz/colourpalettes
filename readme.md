# ColourPalette Module
This module allows you to manage and use colour palettes in your SilverStripe project.

### Installation
To install the module, use Composer:

``` bash
composer require toastnz/colourpalettes
```

### Adding and editing colours
Colours can be managed in the CMS site config under 'Customisation' -> 'Colour Palettes'. Here you can add, edit and delete colours.

### Colour palettes configuration
You can define default colours as well as colour groups in a yml config file. Default colours need to be hex codes without the #, contract colours are more customisable, and can be set to other variables, therefore a # is required if you want to use a hex code.

``` yml
---
Name: colours
After: colourpalettes
---

Toast\ColourPalettes\Models\Colour:
  colour_groups:
    - Products
    - Buttons
  default_colours:
    - primary: null
    - secondary: null
    - white: 'ffffff'
    - black: '000000'
    - off-white: null
    - off-black: null
  theme_colours:
    - primary
    - secondary
  contrast_colours:
    - on-dark: '#ffffff'
    - on-light: '#000000'

```

Adding colour groups will add a listbox to each colour added in the site config, allowing you to assign a colour to a single group or multiple groups.

Colours with a value will be locked and cannot be deleted or edited in the CMS. Groups are optional, but can be used to separate colours into various colour palette fields.

Theme colours are a list of default colours that can be separated from the rest of the colours. These colours will be shown at the top of the colour palette field when selecting a colour.

Contrast colours are the colours used when generating the css variables, on-dark will be used if the colour is set to dark, and on-light will be used if the colour is set to light. Makes senses right?

### Adding Colour Palette Field to a Class

To add a colour palette field to a class, you can use the following code as an example:

``` php
use Toast\ColourPalettes\Models\Colour;
use Toast\ColourPalettes\Fields\ColourPaletteField;

class YourClass extends DataObject
{
    private static array $has_one = [
        'PrimaryColour' => Colour::class,
        'SecondaryColour' => Colour::class,
    ];

    public function getCMSFields(): FieldList
    {
        $this->beforeUpdateCMSFields(function (FieldList $fields) {
            $fields->removeByName([
                'PrimaryColourID',
                'SecondaryColourID',
            ]);

            // Add the colour palette field to the class (the name is the has_one relation name)
            $fields->addFieldToTab('Root.Main', ColourPaletteField::create('PrimaryColour', 'Primary Colour'));

            // Optionally add groups as an array to the field as the 3rd parameter
            $fields->addFieldToTab('Root.Main', ColourPaletteField::create('SecondaryColour', 'Secondary Colour', ['Products']));
        });

        return parent::getCMSFields();
    }
}
```
### Template usage

To use the colours in your templates, you can use the following code as an example:

``` ss
<!-- Add styles to the root -->
<style>
    :root {
        {$PrimaryColour.RootVars}
        {$SecondaryColour.RootVars}
    }
</style>
```

``` ss
<!-- Add styles in a style tag scoped to this element -->
<div class="my-element">
    <p>lorem ipsum</p>

    <style>
        .my-element {
            {$PrimaryColour.CSSVars}
            {$SecondaryColour.CSSVars}
        }
    </style>
</div>
```

### Using the colours in your CSS
The colours will automatically generate their own CSS variables based on the name you give them on the class.
For example, if you have a colour field called 'PrimaryColour', the following CSS variables will be generated:


``` css
/* $PrimaryColour.RootVars */
:root {
    --primary-colour: #000000;
    --primary-colour-contrast: #ffffff;
}

/* PrimaryColour.CSSVars */
.my-element {
    --_primary-colour: #000000;
    --_primary-colour-contrast: #ffffff;
}
```

### Helper functions
Sometimes you just want to know if the colour is light or dark:

``` ss
<!-- Check if the colour is light or dark -->
<% if $PrimaryColour.IsLight %>
    <p>Primary colour is light</p>
<% end_if %>

<% if $PrimaryColour.IsDark %>
    <p>Primary colour is dark</p>
<% end_if %>

<% if $PrimaryColour.Luminance <= 5 %>
    <p>Primary colour is nearly black</p>
<% end_if %>

<% if $PrimaryColour.Luminance >= 95 %>
    <p>Primary colour is nearly white</p>
<% end_if %>
```

Or you might want to adjust the brightness of a colour:
```css
<style>
    /* Lighten the colour by 50% */
    color: {$PrimaryColour.LightenedBy(50)};
     /* Darken the colour by 30% */
    background-color: {$SecondaryColour.DarkenedBy(30)};
</style>
```

This module also provides a controller extension that adds helper methods to retrieve colours by name or ID, and adjust the brightness of colours.

#### Return the first valid colour from a list of colours
``` ss
$FirstColour($Colour1, $Colour2, etc)
```

#### Access a colour by its given name in colours.yml config
``` ss
$ColourByName('primary')
```

#### Access a colour by its given name for a specific SiteConfig (e.g. a subsite's config)
By default the colour is looked up in the current SiteConfig. The second argument is a SiteConfig ID, not a Subsite ID.
``` ss
$ColourByName('primary', $SubsiteConfig.ID)
```

#### Access a colour by ID (probably don't need this ever, but it's here)
``` ss
$ColourByID(1)
```

#### Change the colour’s brightness by percentage (returns hex code)
``` ss
$LightenColour('primary', 50)
$DarkenColour('primary', 50)
```
Both accept a colour ID or a CSS name.


### Generated CSS
The theme and editor CSS files are written to `assets/styles` inside the public folder, and the theme CSS is inlined on every page. Both can be changed:

``` yml
SilverStripe\SiteConfig\SiteConfig:
  # Folder inside the public folder
  css_folder: 'assets/styles'
  # Or a path relative to the project root (takes precedence over css_folder)
  # css_folder_path: '/public/assets/styles/'
  # Link the theme CSS file instead of inlining it, so browsers can cache it
  inline_theme_css: false
```

Locked colours (default colours with a value in config) are also protected server side, so their title and hex value can't be changed by editing the form data.

### Requirements
Silverstripe CMS 6, PHP 8.3+. After installing or upgrading, run `sake db:build --flush`.

### Extending the module
Every method that returns a computed value passes it through an `update*` extension hook, so you can change behaviour without subclassing. Hooks are `protected` methods on an `Extension` (`SilverStripe\Core\Extension`).

| Class | Apply the extension to | Example hooks |
|---|---|---|
| Colour model | `Toast\ColourPalettes\Models\Colour` | `updateBrightness`, `updateValue`, `updateLightenedBy`, `updateDarkenedBy`, `updateHSL`, `updateColourCSS`, `updateScopedStyles`, `updateDefaultColours`, `updateColourRestrictions`, `onAfterCreateDefaultColours`, `onBeforeGenerateCSS` |
| Helper (static helpers) | `Toast\ColourPalettes\Helpers\Helper` | `updateCurrentSiteConfig`, `updateIsSuperAdmin`, `updateColourPaletteArray`, `updateColoursForTinyMCE`, `updateContrastColours`, `updateCSSStyles`, `onAfterGenerateCSSFiles` |
| SiteConfig | `SilverStripe\SiteConfig\SiteConfig` | `updateColoursForCMS`, `updateThemeColoursForCMS`, `updateSiteColourCSSVars` |
| Controllers | `SilverStripe\Control\Controller` | `updateColourByName`, `updateColourByID`, `updateLightenColour`, `updateDarkenColour` |
| ColourPaletteField | `Toast\ColourPalettes\Fields\ColourPaletteField` | `updateColourPaletteSource`, `onBeforeSaveInto`, `onAfterSaveInto` |

``` php
use SilverStripe\Core\Extension;
use Toast\ColourPalettes\Models\Colour;

/**
 * @extends Extension<Colour>
 */
class ColourExtension extends Extension
{
    // Treat anything under 20% luminance as dark, whatever the YIQ calculation says
    protected function updateBrightness(string &$brightness): void
    {
        if (($this->getOwner()->getLuminance() ?? 100) < 20) {
            $brightness = 'dark';
        }
    }
}
```

``` yml
Toast\ColourPalettes\Models\Colour:
  extensions:
    - App\Extensions\ColourExtension
```

When writing many colours in code, wrap the writes in `Helper::withoutCSSGeneration(function () { ... })` so the CSS files are only generated once at the end.

### Upgrading from the m4 module
Run the migration task to move the old `Colour.Colour` / `Colour.CustomColourID` values and `ColourPalette` relations across. It can be run more than once, and `--dry-run` shows what would change:

``` bash
sake tasks:UpdateColoursTask --dry-run
sake tasks:UpdateColoursTask
```
