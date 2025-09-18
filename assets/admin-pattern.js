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
    const amplitude = Number.isFinite(opts.amplitude) ? opts.amplitude : 0;
    const frequency = Number.isFinite(opts.frequency) ? opts.frequency : 0;
    let y = height / 2 + amplitude * Math.sin(nx * frequency * Math.PI * 2);
    if (Array.isArray(opts.poly)) {
      let poly = 0;
      for (let i = 0; i < opts.poly.length; i++) {
        const coeff = Number(opts.poly[i]);
        if (!Number.isFinite(coeff)) continue;
        poly += coeff * Math.pow(nx, i);
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
    root.style.setProperty('--pattern-hue', '0deg');
    root.style.setProperty('--pattern-sat', '100%');
    root.removeAttribute('data-pattern-preset');
    return;
  }
  const features = s.features && typeof s.features === 'object' ? s.features : {};
  const polyValues = features.poly
    ? (Array.isArray(s.poly) ? s.poly.map(Number).filter(n => Number.isFinite(n)) : [])
    : [];
  const data = {
    frequency: Number(s.frequency) || 0,
    amplitude: Number(s.amplitude) || 0,
    poly: polyValues,
  };
  const url = drawPattern(data);
  root.style.setProperty('--header-pattern', `url(${url})`);
  root.style.setProperty('--footer-pattern', `url(${url})`);
  const hue = features.hue ? Number(s.hue) || 0 : 0;
  const sat = features.sat ? Number(s.sat) || 100 : 100;
  root.style.setProperty('--pattern-hue', `${hue}deg`);
  root.style.setProperty('--pattern-sat', `${sat}%`);
  if (s.preset) {
    root.setAttribute('data-pattern-preset', s.preset);
  } else {
    root.removeAttribute('data-pattern-preset');
  }
}

window.generateVaporwavePattern = applyPattern;

document.addEventListener('DOMContentLoaded', () => {
  if (window.vaporwavePatternSettings) {
    applyPattern(window.vaporwavePatternSettings);
  }
});
