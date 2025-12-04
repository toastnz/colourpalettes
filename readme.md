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
  contrast_colours:
    - on-dark: '#ffffff'
    - on-light: '#000000'

```

Adding colour groups will add a listbox to each colour added in the site config, allowing you to assign a colour to a single group or multiple groups.

Colours with a value will be locked and cannot be deleted or edited in the CMS. Groups are optional, but can be used to separate colours into various colour palette fields.

Contrast colours are the colours used when generating the css variables, on-dark will be used if the colour is set to dark, and on-light will be used if the colour is set to light. Makes senses right?

### Adding Colour Palette Field to a Class

To add a colour palette field to a class, you can use the following code as an example:

``` php
use Toast\ColourPalettes\Models\ColourPalette;
use Toast\ColourPalettes\Fields\ColourPaletteField;

class YourClass extends DataObject
{
    private static $has_one = [
        'PrimaryColour' => Colour::class,
        'SecondaryColour' => Colour::class,
    ];

    public function updateCMSFields(FieldList $fields)
    {
        $fields->removeByName([
            'PrimaryColourID',
            'SecondaryColourID',
        ]);

        // Add the colour palette field to the class
        $fields->addFieldToTab('Root.Main', ColourPaletteField::create('PrimaryColour', 'Primary Colour'));

        // Optionally add a groups as an array to the field as the 3rd parameter
        $fields->addFieldToTab('Root.Main', ColourPaletteField::create('SecondaryColour', 'Secondary Colour', ['Products']));
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

#### Access a colour by ID (probably don't need this ever, but it's here)
``` ss
$ColourByID(1)
```

#### Change the colour’s brightness by percentage (returns hex code)
``` ss
$LightenColour('primary', 50)
$DarkenColour('primary', 50)
```
