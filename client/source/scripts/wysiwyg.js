/*------------------------------------------------------------------
Import styles
------------------------------------------------------------------*/

import 'styles/index.scss';

/*------------------------------------------------------------------
Import modules
------------------------------------------------------------------*/

import DomObserverController from 'domobserverjs';

/*------------------------------------------------------------------
Dom Observer
------------------------------------------------------------------*/

const CMSObserver = new DomObserverController();

/*------------------------------------------------------------------
Functions
------------------------------------------------------------------*/

import { getBrightess } from 'scripts/components/functions';

/*------------------------------------------------------------------
Document setup
------------------------------------------------------------------*/


// Observe the CMS for the colourpalette fieldsets
CMSObserver.observe('ul.colourpalette', (fieldsets) => {
  // Set up an object to store the theme colours
  window.ColourPalettes = {};

  // Find the main cms element
  const main = document.querySelector('#Root_Main');

  // If the main element doesn't exist, return
  if (!main) return;

  // Loop through the fieldsets
  (async () => {
    for (const fieldset of fieldsets) {
      // Find all the inputs in the fieldset
      const inputs = fieldset.querySelectorAll('input');

      // Loop the images
      for (const input of inputs) {
        // Find the input label
        const label = input.labels[0];
        // Find the input name
        const name = input.name;

        // Get the computed background colour of the label as the value
        const value = window.getComputedStyle(label).backgroundColor;

        const onChange = () => {
          // If the input is not checked, return
          if (!input.checked) return;
          // Get the brightness attribute from the input
          const brightnessAttribute = input.getAttribute('data-brightness');
          // Calculate the brightness
          const brightness = (brightnessAttribute) ? (brightnessAttribute === 'light') ? 255 : 0 : getBrightess(value);
          // Find all the html text editor iframes
          const htmlEditors = document.querySelectorAll('.tox-edit-area__iframe');

          // Set the value as a CSS variable on the body
          main.style.setProperty(`--ColourPalettes-${name}`, value);

          // Assign a text colour based on the brightness
          if (brightness < 130) {
            main.style.setProperty(`--ColourPalettes-${name}_Text`, '#fff');
          } else {
            main.style.setProperty(`--ColourPalettes-${name}_Text`, '#000');
          }

          if (value === 'rgba(0, 0, 0, 0)') {
            // Remove the style properties
            main.style.removeProperty(`--ColourPalettes-${name}`);
            main.style.removeProperty(`--ColourPalettes-${name}_Text`);
          }

          window.ColourPalettes[name] = {
            value,
            brightness: (brightness > 130) ? 'light' : 'dark',
          };

          // fire a window event and pass the value and the brightness
          window.dispatchEvent(new CustomEvent('ColourPaletteChanged', { detail: { input, name, value, brightness: (brightness > 130) ? 'light' : 'dark' } }));

          if (fieldset === fieldsets[0]) {
            // Loop through the html text editors
            for (const editor of htmlEditors) {
              if (value === 'rgba(0, 0, 0, 0)') {
                editor.contentDocument.body.classList.remove('light', 'dark');
                return;
              }

              // Switch between light and dark classes
              editor.contentDocument.body.classList.toggle('light', brightness > 130);
              editor.contentDocument.body.classList.toggle('dark', brightness <= 130);
            }
          }
        };

        // Add an event listener to the input
        input.addEventListener('change', onChange);

        // Call the onChange function
        onChange();
      }
    }
  })();
});
