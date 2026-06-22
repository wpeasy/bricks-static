/**
 * Rasterize the wp.org assets from their SVG masters. Run after editing any
 * `.wordpress-org/*.svg`:   npm run assets
 *
 * - icon.svg   → icon-256x256.png, icon-128x128.png            (resvg)
 * - banner.svg → banner-772x250.png, banner-1544x500.png
 *     banner.svg is the FOREGROUND (shade + text + logo pill); it's composited
 *     by sharp over the darkened dashboard screenshot (_banner-bg.jpg), and the
 *     BRXProd logo (_brxprod-logo.png) is dropped onto the pill.
 *
 * Dev-only (uses @resvg/resvg-js + sharp); output lives in .wordpress-org/ and
 * never ships in the plugin zip. System fonts are loaded so banner text renders.
 */

import { Resvg } from '@resvg/resvg-js';
import sharp from 'sharp';
import { readFileSync, writeFileSync } from 'node:fs';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const WPORG = join(dirname(fileURLToPath(import.meta.url)), '..', '.wordpress-org');
const FONT = { loadSystemFonts: true, defaultFontFamily: 'Arial' };

function svgToPng(svg, width) {
  return new Resvg(svg, { fitTo: { mode: 'width', value: width }, font: FONT }).render().asPng();
}

// ---- Icon ----
const icon = readFileSync(join(WPORG, 'icon.svg'));
for (const s of [256, 128]) {
  writeFileSync(join(WPORG, `icon-${s}x${s}.png`), svgToPng(icon, s));
  console.log(`  → icon-${s}x${s}.png`);
}

// ---- Banner (photo bg + foreground + logo) ----
const bannerSvg = readFileSync(join(WPORG, 'banner.svg'));
const BG = join(WPORG, '_banner-bg.jpg');
const LOGO = join(WPORG, '_brxprod-logo.webp');

// Pill geometry in 772x250 design coords (must match banner.svg).
const PILL = { x: 52, y: 192, w: 142, h: 52 };

for (const [W, H] of [[772, 250], [1544, 500]]) {
  const f = W / 772;
  const fg = svgToPng(bannerSvg, W);

  // Logo sized to the pill (by height), centred within it. flatten() composites
  // the WebP's real transparency onto white (so no black background leaks in).
  const logo = await sharp(LOGO)
    .flatten({ background: '#ffffff' })
    .resize({ height: Math.round(34 * f) })
    .png()
    .toBuffer();
  const lm = await sharp(logo).metadata();
  const left = Math.round(PILL.x * f + (PILL.w * f - lm.width) / 2);
  const top = Math.round(PILL.y * f + (PILL.h * f - lm.height) / 2);

  await sharp(BG)
    .resize(W, H, { fit: 'cover', position: 'centre' })
    .composite([{ input: fg }, { input: logo, left, top }])
    .png()
    .toFile(join(WPORG, `banner-${W}x${H}.png`));
  console.log(`  → banner-${W}x${H}.png`);
}

console.log('✓ Assets rendered from .wordpress-org/*.svg');
