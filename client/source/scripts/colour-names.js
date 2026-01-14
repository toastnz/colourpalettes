/*------------------------------------------------------------------
Import modules
------------------------------------------------------------------*/

import DomObserverController from 'domobserverjs';
import ColourNamer from 'color-namer';

/*------------------------------------------------------------------
Dom Observer
------------------------------------------------------------------*/

const CMSObserver = new DomObserverController();

/*------------------------------------------------------------------
Functions
------------------------------------------------------------------*/

function createSuggestionsForInput(input) {
  const suggestions = document.createElement('div');
  suggestions.classList.add('colour-name-suggestions');
  suggestions.id = input.id + '_NameSuggestions';
  input.parentNode.appendChild(suggestions);
  return suggestions;
}

function getColourNameSuggestions(nameInput, suggestions, hexValue) {
  if (!suggestions) return;

  suggestions.innerHTML = '';

  // Ensure the hexValue is a valid 6 character hex string
  if (!/^[0-9A-Fa-f]{6}$/.test(hexValue)) return;

  const colourNames = ColourNamer('#' + hexValue);
  const options = [];

  colourNames.pantone.forEach((colour, index) => {
    if (index >= 8) return;

    const option = document.createElement('div');

    option.classList.add('colour-name-option');
    option.innerText = colour.name;

    option.addEventListener('click', () => {
      nameInput.value = colour.name;
      nameInput.form.dispatchEvent(new Event('change', { bubbles: true }));

      // Update selected state
      options.forEach((opt) => {
        opt.classList.toggle('selected', opt === option);
      });
    });

    suggestions.appendChild(option);

    options.push(option);
  });

  options.forEach((option) => {
    option.classList.toggle('selected', option.innerText.toLowerCase() === nameInput.value.toLowerCase());
  });
}

/*------------------------------------------------------------------
Document setup
------------------------------------------------------------------*/

CMSObserver.observe('#Form_ItemEditForm_HexValue', (inputs) => {
  inputs.forEach((hexInput) => {
    const nameInput = document.getElementById('Form_ItemEditForm_Title');
    const suggestions = createSuggestionsForInput(nameInput);

    let timeout = null;

    const hexObserver = new MutationObserver(() => {
      clearTimeout(timeout);

      timeout = setTimeout(() => {
        getColourNameSuggestions(nameInput, suggestions, hexInput.value);
      }, 100);
    });

    // The input's background colour changes via inline styles, watch for those updates to fire the suggestion update
    hexObserver.observe(hexInput, { attributes: true, attributeFilter: ['style'] });

    hexInput.addEventListener('input', () => {
      getColourNameSuggestions(nameInput, suggestions, hexInput.value);
    });

    // Initial population
    getColourNameSuggestions(nameInput, suggestions, hexInput.value);
  });
});
