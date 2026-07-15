// Copies each theme's logo.svg into web/assets/themes/<handle>/, from a
// single source SVG resolved per theme — same fallback order as favicons:
//   1. themes/<handle>/src/logo.svg — a theme-specific logo
//   2. themes/_base/src/logo.svg    — the shared default
// Unlike favicons there's no processing involved: a logo is used as-is, so
// this is a straight file copy rather than a resize/pack pipeline. A
// palette-only theme variant just inherits the shared default; a genuine
// redesign drops its own logo.svg in and this picks it up automatically.

import { copyFileSync, existsSync, mkdirSync } from 'node:fs';
import { join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { discoverThemeHandles } from './lib/discover-themes.mjs';

const root = resolve(fileURLToPath(import.meta.url), '../..');
const themesDir = join(root, 'themes');
const defaultSource = join(themesDir, '_base', 'src', 'logo.svg');

function buildLogo(handle) {
	const themeSource = join(themesDir, handle, 'src', 'logo.svg');
	const usingDefault = !existsSync(themeSource);
	const source = usingDefault ? defaultSource : themeSource;

	if (!existsSync(source)) {
		throw new Error(
			`No logo source for theme "${handle}", and no shared default at ` +
			`${defaultSource} to fall back to. Add one of the two.`
		);
	}

	const outDir = join(root, 'web', 'assets', 'themes', handle);
	mkdirSync(outDir, { recursive: true });
	copyFileSync(source, join(outDir, 'logo.svg'));

	console.log(
		`[logo-build] ${handle}: copied from ${usingDefault ? '_base default' : 'its own'} logo.svg -> web/assets/themes/${handle}/logo.svg`
	);
}

for (const handle of discoverThemeHandles(themesDir)) {
	buildLogo(handle);
}
