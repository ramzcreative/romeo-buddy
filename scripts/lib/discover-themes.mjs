// Shared by the per-theme asset build scripts (favicons, logos): a theme is
// any top-level folder under themes/ (excluding _base) with a theme.json —
// same discovery rule modules/themepicker uses for the CP theme picker.
import { existsSync, readdirSync } from 'node:fs';
import { join } from 'node:path';

export function discoverThemeHandles(themesDir) {
	return readdirSync(themesDir, { withFileTypes: true })
		.filter((entry) => entry.isDirectory() && entry.name !== '_base')
		.map((entry) => entry.name)
		.filter((handle) => existsSync(join(themesDir, handle, 'theme.json')));
}
