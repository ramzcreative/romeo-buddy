// npm run svg-build — optimises Base's icons and every theme's own, after checking them against the icon rules in
// themes/_base/src/CLAUDE.md § Icons. Any failure stops the build before anything is written, naming the icon and
// what's wrong with it.
//
//   themes/_base/src/icons/<set>/     -> web/dist/assets/icons/<set>/
//   themes/<handle>/src/icons/<set>/  -> web/dist/assets/theme-icons/<handle>/<set>/
//
// A theme's directory replaces Base's entirely at runtime (craft-modules' IconRegistry, `themeIconsPath`), so each
// theme is built as a whole library of its own. Base's `ui` set is exempt from the checks: it predates the rules and
// is deliberately left as it is.
import { existsSync, mkdirSync, readdirSync, readFileSync, rmSync, statSync, writeFileSync } from 'node:fs';
import { dirname, join, relative } from 'node:path';
import { fileURLToPath } from 'node:url';
import { optimize } from 'svgo';
import svgoConfig from '../svgo.config.js';
import { discoverThemeHandles } from './lib/discover-themes.mjs';
import { checkIcon } from './lib/icon-rules.mjs';

const root = join(dirname(fileURLToPath(import.meta.url)), '..');
const themesDir = join(root, 'themes');
const EXEMPT = { _base: new Set(['ui', 'all']) };  // `all` is this site's own 64x64 multicolour illustration set.

const libraries = [{ handle: '_base', src: join(themesDir, '_base/src/icons'), out: join(root, 'web/dist/assets/icons') }];

for (const handle of discoverThemeHandles(themesDir)) {
	const src = join(themesDir, handle, 'src/icons');
	if (existsSync(src)) libraries.push({ handle, src, out: join(root, 'web/dist/assets/theme-icons', handle) });
}

const svgFiles = (dir) =>
	readdirSync(dir).flatMap((name) => {
		const path = join(dir, name);
		return statSync(path).isDirectory() ? svgFiles(path) : name.endsWith('.svg') ? [path] : [];
	});

// Check everything first, so one run reports every broken icon rather than the first.
const failures = [];

for (const lib of libraries) {
	for (const file of svgFiles(lib.src)) {
		const rel = relative(lib.src, file);
		const set = rel.split('/')[0];

		if (EXEMPT[lib.handle]?.has(set)) continue;

		const problems = [];
		if (!/^[a-z0-9-]+\/[a-z0-9-]+\.svg$/.test(rel)) problems.push('set and icon names must be lowercase kebab-case, one folder deep (rule 6)');
		problems.push(...checkIcon(readFileSync(file, 'utf8')).problems);

		if (problems.length) failures.push(`  ${relative(root, file)}\n${problems.map((p) => `    - ${p}`).join('\n')}`);
	}
}

if (failures.length) {
	console.error(`\n[svg-build] ${failures.length} icon(s) break the icon rules (themes/_base/src/CLAUDE.md § Icons):\n\n${failures.join('\n')}\n`);
	process.exit(1);
}

// Rebuilt from scratch each time, so an icon deleted from source (or a theme's whole directory) can't linger in dist.
rmSync(join(root, 'web/dist/assets/icons'), { recursive: true, force: true });
rmSync(join(root, 'web/dist/assets/theme-icons'), { recursive: true, force: true });

let count = 0;

for (const lib of libraries) {
	for (const file of svgFiles(lib.src)) {
		const out = join(lib.out, relative(lib.src, file));
		const { data } = optimize(readFileSync(file, 'utf8'), { ...svgoConfig, path: file });
		mkdirSync(dirname(out), { recursive: true });
		writeFileSync(out, data);
		count++;
	}
	console.log(`[svg-build] ${lib.handle}: ${relative(root, lib.src)} -> ${relative(root, lib.out)}`);
}

console.log(`[svg-build] ${count} icons optimised`);
