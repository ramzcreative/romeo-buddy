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
// Each slot resolves through the same tiers the CP's own preview computes
// server-side (DesignerController::buildBrandAssetsTabData()), with each
// "this theme" step widened to this theme AND everything it inherits from
// (theme.json `parent`, docs/theme-inheritance-spec.md §4.6):
//   1. this theme's file for that slot, then each ancestor's
//   2. _base's own file for that slot
//   3. this theme's Standard logo, then each ancestor's
//   4. _base's own Standard logo (the one always-required file)
// Slot still beats Standard across the whole chain: a parent's Dark logo is
// a better answer for the Dark slot than this theme's Standard one.
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
import { fileInChain, readManifests } from './lib/theme-chain.mjs';

const root = resolve(fileURLToPath(import.meta.url), '../..');
const themesDir = join(root, 'themes');
const movedSlotsPath = join(root, 'config', 'stables', 'themes', 'generated', 'logo-slots.json');
const legacySlotsPath = join(root, 'config', 'theme-designer-logo-slots.json');
// Same fallback the PHP side uses (modules/support/ConfigPath) — a site that
// hasn't moved its config files keeps building.
const customSlotsPath = existsSync(movedSlotsPath) ? movedSlotsPath : legacySlotsPath;

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

function resolveSlotSource(handle, slot, manifests) {
	const filename = SLOTS[slot];
	const standardFilename = SLOTS.standard;

	// Each tier's "this theme" step is really "this theme, then everything it inherits from" — a child with no logo
	// of its own uses its parent's, not _base's, which is the whole point of extending it. Slot still beats standard:
	// a parent's Dark logo is a better answer for the Dark slot than this theme's Standard one.
	const inChain = fileInChain(themesDir, handle, manifests, filename);
	const standardInChain = fileInChain(themesDir, handle, manifests, standardFilename);

	const basePath = join(themesDir, '_base', 'src', filename);
	const baseStandardPath = join(themesDir, '_base', 'src', standardFilename);

	if (inChain) return { source: inChain.path, tier: inChain.owner === handle ? 'own' : inChain.owner };
	if (existsSync(basePath)) return { source: basePath, tier: 'base' };
	if (slot !== 'standard' && standardInChain) {
		return { source: standardInChain.path, tier: standardInChain.owner === handle ? 'own-standard' : `${standardInChain.owner}-standard` };
	}
	if (existsSync(baseStandardPath)) return { source: baseStandardPath, tier: 'base-standard' };

	return null;
}

function buildLogo(handle, manifests) {
	const outDir = join(root, 'web', 'assets', 'themes', handle);
	mkdirSync(outDir, { recursive: true });

	for (const slot of Object.keys(SLOTS)) {
		const resolved = resolveSlotSource(handle, slot, manifests);

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

const manifests = readManifests(themesDir);

for (const handle of discoverThemeHandles(themesDir)) {
	buildLogo(handle, manifests);
}
