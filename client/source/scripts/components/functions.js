export function getBrightess(color) {
  // Convert the color to an array
  const rgb = color.replace(/[^\d,]/g, '').split(',');
  // Calculate the brightness
  const brightness = Math.round(((parseInt(rgb[0]) * 299) + (parseInt(rgb[1]) * 587) + (parseInt(rgb[2]) * 114)) / 1000);

  return brightness;
}

export function calculateColorContrast(color1, color2) {
  // Helper function to convert hex to RGB
  const hexToRGB = (hex) => {
    let r = parseInt(hex.substring(1, 3), 16);
    let g = parseInt(hex.substring(3, 5), 16);
    let b = parseInt(hex.substring(5, 7), 16);
    return { r, g, b };
  }

  // Helper function to calculate luminance
  const calculateLuminance = (rgb) => {
    const { r, g, b } = rgb;
    const sRGB = [r, g, b].map(value => {
      value /= 255;
      return value <= 0.03928 ? value / 12.92 : Math.pow((value + 0.055) / 1.055, 2.4);
    });
    return 0.2126 * sRGB[0] + 0.7152 * sRGB[1] + 0.0722 * sRGB[2];
  }

  // Calculate color contrast ratio
  const rgb1 = hexToRGB(color1);
  const rgb2 = hexToRGB(color2);
  const luminance1 = calculateLuminance(rgb1);
  const luminance2 = calculateLuminance(rgb2);
  const contrastRatio = (Math.max(luminance1, luminance2) + 0.05) / (Math.min(luminance1, luminance2) + 0.05);

  // Determine accessibility score
  if (contrastRatio >= 7) {
    return "AAA";
  } else if (contrastRatio >= 4.5) {
    return "AA";
  } else if (contrastRatio >= 3) {
    return "AA Large";
  } else {
    return "Fail";
  }
}

export function rgbToHex(rgb) {
  // Accepts either separate r, g, b or a string like "rgb(199, 107, 63)"
  let r, g, b;
  if (typeof rgb === 'string') {
    // Extract numbers from the string
    [r, g, b] = rgb.match(/\d+/g).map(Number);
  } else {
    // Assume arguments are r, g, b
    [r, g, b] = arguments;
  }
  const toHex = (value) => {
    const hex = value.toString(16);
    return hex.length === 1 ? '0' + hex : hex;
  };

  console.log(`Converting RGB(${r}, ${g}, ${b}) to Hex`, `#${toHex(r)}${toHex(g)}${toHex(b)}`);
  return `#${toHex(r)}${toHex(g)}${toHex(b)}`;
}
