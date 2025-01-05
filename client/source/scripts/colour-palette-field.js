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

function getSessionValue(input) {
  const name = input.name;
  const namedURL = name + 'URL';
  const url = window.location.href;
  const sessionValue = window.sessionStorage.getItem(name);
  const sessionURL = window.sessionStorage.getItem(namedURL);

  if (sessionURL === url) {
    return sessionValue;
  }

  return null;
}

function updateSessionValue(input) {
  const name = input.name;
  const value = input.value;
  const namedURL = name + 'URL';

  window.sessionStorage.setItem(namedURL, window.location.href);
  window.sessionStorage.setItem(name, value);
}

// Observe the CMS for the themecolourpalette fieldsets
CMSObserver.observe('ul.colourpalette', (fieldsets) => {
  // Set up an object to store the theme colours
  window.ColourPalettes = {};

  // Find the main cms element
  const main = document.querySelector('#Root_Main');

  // If the main element doesn't exist, return
  if (!main) return;

  const actionButtons = [...document.querySelectorAll('.cms-content-actions [type="submit"]')];

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
        const name = input.name.replace(/([a-z])([A-Z])/g, '$1-$2').toLowerCase();

        // Get the computed background colour of the label as the value
        const value = window.getComputedStyle(label).backgroundColor || 'rgba(0, 0, 0, 0)';
        // Get the brightness attribute from the input
        const brightnessAttribute = input.getAttribute('data-brightness');
        // Calculate the brightness
        const brightness = (brightnessAttribute) ? brightnessAttribute : getBrightess(value) > 130 ? 'light' : 'dark';

        // Get the session value
        const sessionValue = getSessionValue(input);

        // Check if the session value exists
        if (sessionValue) {
          // If the session value is the same as the input value, check the input
          input.checked = (input.value === sessionValue);
        };

        const onChange = () => {
          // If the input is not checked, return
          if (!input.checked) return;

          // if (e) updateSessionValue(input);

          // Set the value and brightness to the window object
          window.ColourPalettes[name] = { value, brightness };

          // fire a window event and pass the value and the brightness
          window.dispatchEvent(new CustomEvent('ColourPaletteChanged', { detail: { input, name, value, brightness } }));
        };

        // If the user saves, update the session value
        actionButtons.forEach((button) => {
          button.addEventListener('click', () => {
            if (!input.checked) return;
            updateSessionValue(input);
          });
        });

        // Add an event listener to the input
        input.addEventListener('change', onChange);

        // Call the onChange function
        onChange();
      }
    }
  })();
});
