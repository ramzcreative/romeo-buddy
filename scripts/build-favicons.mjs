// Generates each theme's favicon.ico + favicon-16x16.png + favicon-32x32.png
// + apple-touch-icon.png (the exact set scaffold.twig references) from a
// single source PNG.
//
// Source resolution per theme, in order:
//   1. themes/<handle>/src/favicon.png — a theme-specific redesign
//   2. themes/_base/src/favicon.png    — the shared default
// A palette-only theme variant usually has no reason to ship its own
// favicon.png, since a handful of recolored pixels isn't worth a distinct
// icon — it just inherits the shared default. A theme that's a genuine
// redesign drops its own favicon.png in and this picks it up automatically,
// no script changes needed.
//
// Output goes to web/assets/themes/<handle>/ — same per-theme folder as the
// logo, no runtime fallback there (see README's "Per-theme static assets").

import { existsSync, mkdirSync } from 'node:fs';
import { writeFile } from 'node:fs/promises';
import { join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import sharp from 'sharp';
import pngToIco from 'png-to-ico';
import { discoverThemeHandles } from './lib/discover-themes.mjs';

const root = resolve(fileURLToPath(import.meta.url), '../..');
const themesDir = join(root, 'themes');
const defaultSource = join(themesDir, '_base', 'src', 'favicon.png');

// Sizes written out as standalone PNGs (referenced directly by scaffold.twig).
// 48px is only used inside the .ico, which also holds the 16/32 versions.
const PNG_SIZES = [16, 32];
const ICO_SIZES = [16, 32, 48];
const APPLE_TOUCH_ICON_SIZE = 180;

async function buildFavicon(handle) {
	const themeSource = join(themesDir, handle, 'src', 'favicon.png');
	const usingDefault = !existsSync(themeSource);
	const source = usingDefault ? defaultSource : themeSource;

	if (!existsSync(source)) {
		throw new Error(
			`No favicon source for theme "${handle}", and no shared default at ` +
			`${defaultSource} to fall back to. Add one of the two.`
		);
	}

	const outDir = join(root, 'web', 'assets', 'themes', handle);
	mkdirSync(outDir, { recursive: true });

	const pngBuffers = {};
	for (const size of PNG_SIZES) {
		pngBuffers[size] = await sharp(source).resize(size, size).png().toBuffer();
	}
	await Promise.all(
		PNG_SIZES.map((size) =>
			writeFile(join(outDir, `favicon-${size}x${size}.png`), pngBuffers[size])
		)
	);

	const icoInputs = await Promise.all(
		ICO_SIZES.map((size) =>
			pngBuffers[size] ?? sharp(source).resize(size, size).png().toBuffer()
		)
	);
	await writeFile(join(outDir, 'favicon.ico'), await pngToIco(icoInputs));

	// iOS renders transparent pixels on a touch icon as black, so flatten
	// onto white rather than shipping the source's alpha channel through.
	const appleTouchIcon = await sharp(source)
		.resize(APPLE_TOUCH_ICON_SIZE, APPLE_TOUCH_ICON_SIZE)
		.flatten({ background: '#ffffff' })
		.png()
		.toBuffer();
	await writeFile(join(outDir, 'apple-touch-icon.png'), appleTouchIcon);

	console.log(
		`[favicon-build] ${handle}: built from ${usingDefault ? '_base default' : 'its own'} favicon.png -> web/assets/themes/${handle}/`
	);
}

for (const handle of discoverThemeHandles(themesDir)) {
	await buildFavicon(handle);
}
