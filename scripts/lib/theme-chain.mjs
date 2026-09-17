// A theme's inheritance chain, for the build. The JS twin of
// craft-modules' ThemeRegistry::chain() — the build has no PHP, and a theme with a broken chain must never reach a
// deployed site: it can't be activated once built, so refusing at build time is what makes that impossible rather
// than merely unlikely. See docs/theme-inheritance-spec.md §4.2 and §4.11.
//
// Kept in step with the PHP by hand. If you change a rule here, change it there.
import { existsSync, readdirSync, readFileSync } from 'node:fs';
import { join } from 'node:path';

/** Matches ThemeRegistry::MAX_CHAIN. Readability, not cost — D1. */
export const MAX_CHAIN = 4;

/**
 * Which theme a `.pcss` entry builds on, read out of its own text. Matches ThemeEntries::PATTERN.
 *
 * `-core` is accepted because a library theme imports `_base`'s core entry rather than its full one — it takes
 * Base's machine without Base's look (docs/base-layer-spec.md). Only `_base` has core entries, so a child theme's
 * import of its parent never carries the suffix.
 */
export const INHERIT_IMPORT = /@import\s+['"]\.\.\/\.\.\/\.\.\/([\w-]+)\/src\/css\/(?:main|critical)(?:-core)?\.pcss['"]/;

/** Every theme.json under themes/, keyed by handle. `_base` has none, so it is never in here. */
export function readManifests(themesDir) {
	const manifests = {};

	for (const entry of readdirSync(themesDir, { withFileTypes: true })) {
		if (!entry.isDirectory() || entry.name === '_base') continue;

		const path = join(themesDir, entry.name, 'theme.json');
		if (!existsSync(path)) continue;

		try {
			manifests[entry.name] = JSON.parse(readFileSync(path, 'utf8'));
		} catch (error) {
			throw new Error(`themes/${entry.name}/theme.json: ${error.message}`);
		}
	}

	return manifests;
}

/**
 * A theme's chain, itself first, `_base` never in it. Throws on a chain that can't be used, with a message meant for
 * whoever is reading the build output.
 */
export function chainOf(handle, manifests) {
	const chain = [handle];
	const seen = new Set([handle]);
	let current = handle;

	for (;;) {
		const parent = manifests[current]?.parent;

		if (parent === undefined || parent === null || parent === '' || parent === '_base') return chain;
		if (parent === false) throw new Error(`themes/${current}/theme.json: "parent": false isn't supported yet — _base holds the default block templates.`);
		if (typeof parent !== 'string') throw new Error(`themes/${current}/theme.json: "parent" must be a theme handle.`);
		if (parent === current) throw new Error(`themes/${current}/theme.json names itself as its parent.`);
		if (seen.has(parent)) throw new Error(`Themes can't inherit in a circle: ${[...chain, parent].join(' → ')}.`);
		if (!manifests[parent]) throw new Error(`themes/${current} extends ${parent}, which isn't installed.`);
		if ((manifests[parent].type ?? 'site') !== 'site') throw new Error(`themes/${current} extends ${parent}, which is a Theme variant. Extend a site theme.`);
		if (chain.length >= MAX_CHAIN) throw new Error(`Inheritance goes too deep at ${parent}: ${MAX_CHAIN} themes is the limit (${[...chain, parent].join(' → ')}).`);

		chain.push(parent);
		seen.add(parent);
		current = parent;
	}
}

/**
 * The first theme in the chain that has the named file under `src/`, as a full path — how a child with no asset of
 * its own uses its parent's. Returns null when nobody in the chain has it; callers fall back to `_base` themselves,
 * since what they fall back to differs.
 */
export function fileInChain(themesDir, handle, manifests, relative) {
	for (const inChain of chainOf(handle, manifests)) {
		const path = join(themesDir, inChain, 'src', relative);
		if (existsSync(path)) return { path, owner: inChain };
	}

	return null;
}
