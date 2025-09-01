function drawPattern(opts) {
  const canvas = document.createElement('canvas');
  const width = 400;
  const height = 150;
  canvas.width = width;
  canvas.height = height;
  const ctx = canvas.getContext('2d');
  ctx.strokeStyle = '#fff';
  ctx.lineWidth = 2;
  ctx.beginPath();
  for (let x = 0; x < width; x++) {
    const nx = x / width;
    let y = height / 2 + opts.amplitude * Math.sin(nx * opts.frequency * Math.PI * 2);
    if (Array.isArray(opts.poly)) {
      let poly = 0;
      for (let i = 0; i < opts.poly.length; i++) {
        poly += opts.poly[i] * Math.pow(nx, i);
      }
      y += poly;
    }
    if (x === 0) {
      ctx.moveTo(x, y);
    } else {
      ctx.lineTo(x, y);
    }
  }
  ctx.stroke();
  return canvas.toDataURL();
}

function applyPattern(s) {
  const root = document.documentElement;
  if (!s || Object.keys(s).length === 0) {
    root.style.setProperty('--header-pattern', 'none');
    root.style.setProperty('--footer-pattern', 'none');
    return;
  }
  const url = drawPattern({
    frequency: Number(s.frequency) || 0,
    amplitude: Number(s.amplitude) || 0,
    poly: Array.isArray(s.poly) ? s.poly : [0]
  });
  root.style.setProperty('--header-pattern', `url(${url})`);
  root.style.setProperty('--footer-pattern', `url(${url})`);
  root.style.setProperty('--pattern-hue', (s.hue || 0) + 'deg');
  root.style.setProperty('--pattern-sat', (s.sat || 100) + '%');
}

window.generateVaporwavePattern = applyPattern;

document.addEventListener('DOMContentLoaded', () => {
  if (window.vaporwavePatternSettings) {
    applyPattern(window.vaporwavePatternSettings);
  }
});
