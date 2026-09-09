// Copies each theme's logo files into web/assets/themes/<handle>/, from a
// single source SVG resolved per slot per theme. 4 built-in slots: Standard
// (logo.svg), Dark (logo-dark.svg), Light (logo-light.svg), Any
// (logo-any.svg) — same fixed slots the Theme Designer CP's upload feature
// writes to (craft-modules/modules/themedesigner, "Logo & Favicon" card).
// Plus any CUSTOM slots Gary has added there beyond those 4 (e.g. a
// "Stacked Dark" mark) — read from config/theme-designer-logo-slots.json,
// the same registry DesignerController::allLogoSlots() reads on the PHP
// side. JSON, not the PHP-array convention theme-designer-elements-order.php
// uses, specifically because this ONE registry needs to be read by both
// runtimes.
//
// Each slot resolves through the same 4-tier chain the CP's own preview
// already computes server-side (DesignerController::buildBrandAssetsTabData()):
//   1. this theme's own file for that slot
//   2. _base's own file for that slot
//   3. this theme's own Standard logo
//   4. _base's own Standard logo (the one always-required file)
// So every theme always ends up with every slot's file physically present
// after a build — non-Standard slots silently fall back to a real logo
// (never a missing file), letting templates reference e.g. logo-light.svg
// unconditionally. Unlike favicons there's no processing involved: a logo
// is used as-is, so this is a straight file copy rather than a resize/pack
// pipeline.

import { copyFileSync, existsSync, mkdirSync, readFileSync } from 'node:fs';
import { join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { discoverThemeHandles } from './lib/discover-themes.mjs';

const root = resolve(fileURLToPath(import.meta.url), '../..');
const themesDir = join(root, 'themes');
const customSlotsPath = join(root, 'config', 'theme-designer-logo-slots.json');

const SLOTS = {
	standard: 'logo.svg',
	dark: 'logo-dark.svg',
	light: 'logo-light.svg',
	any: 'logo-any.svg',
};

if (existsSync(customSlotsPath)) {
	for (const { key, filename } of JSON.parse(readFileSync(customSlotsPath, 'utf8'))) {
		SLOTS[key] = filename;
	}
}

function resolveSlotSource(handle, slot) {
	const filename = SLOTS[slot];
	const standardFilename = SLOTS.standard;

	const ownPath = join(themesDir, handle, 'src', filename);
	const basePath = join(themesDir, '_base', 'src', filename);
	const ownStandardPath = join(themesDir, handle, 'src', standardFilename);
	const baseStandardPath = join(themesDir, '_base', 'src', standardFilename);

	if (existsSync(ownPath)) return { source: ownPath, tier: 'own' };
	if (existsSync(basePath)) return { source: basePath, tier: 'base' };
	if (slot !== 'standard' && existsSync(ownStandardPath)) return { source: ownStandardPath, tier: 'own-standard' };
	if (existsSync(baseStandardPath)) return { source: baseStandardPath, tier: 'base-standard' };

	return null;
}

function buildLogo(handle) {
	const outDir = join(root, 'web', 'assets', 'themes', handle);
	mkdirSync(outDir, { recursive: true });

	for (const slot of Object.keys(SLOTS)) {
		const resolved = resolveSlotSource(handle, slot);

		if (!resolved) {
			throw new Error(
				`No logo source for theme "${handle}", slot "${slot}", and no shared ` +
				`default at themes/_base/src/${SLOTS.standard} to fall back to. Add one of the two.`
			);
		}

		copyFileSync(resolved.source, join(outDir, SLOTS[slot]));
		console.log(`[logo-build] ${handle}/${slot}: copied via "${resolved.tier}" -> web/assets/themes/${handle}/${SLOTS[slot]}`);
	}
}

for (const handle of discoverThemeHandles(themesDir)) {
	buildLogo(handle);
}
