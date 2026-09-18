declare global {
	interface Window {
		csrfTokenName?: string;
		csrfTokenValue?: string;
	}
}

/** Mirrors InlineEdit::gearFields()'s own PHP return shape (services/InlineEdit.php). */
interface GearFields {
	background?: { handle: string; value: string };
	layout?: { handle: string; value: string; options: { value: string; label: string; imageUrl?: string }[] };
}

/**
 * Front-end inline content editing (admin bar tier 4) — see
 * docs/inline-editing-spec.md. A page-wide CONTROLLER, not a visual widget
 * of its own (unlike <admin-bar>): the markup it acts on
 * (data-inline-edit / data-inline-block) is rendered by arbitrary block
 * templates all over the page, so this delegates from `document` rather
 * than owning a subtree. Deliberately knows nothing about the admin bar —
 * see the spec's "Component boundary". The only thing read from that
 * separate module is body[data-inline-editing], the show/hide toggle's
 * own state.
 *
 * Phase 1 scope: content editing (contenteditable text fields, saved on
 * blur) + block reorder. Reorder ships as keyboard-native move-up/-down
 * buttons rather than drag-and-drop — the spec explicitly allows either
 * ("move-up/move-down actions, or an accessible drag pattern"), and this
 * is the one that's fully accessible without a separate keyboard
 * fallback to build. The save endpoint already accepts an arbitrary new
 * order, so drag-and-drop can be added later as a pure front-end
 * enhancement with no backend change.
 */
customElements.define('inline-edit', class extends HTMLElement {
	activeBlock: HTMLElement | null = null;
	toolbar: HTMLElement;
	liveRegion: HTMLElement;
	saveUrl: string;
	reorderUrl: string;
	gearUrl: string;
	// Built inside buildToolbar(), called from the constructor below — not
	// a direct constructor assignment, so TS can't trace the initializer
	// itself; both are always set before use.
	gearButton!: HTMLButtonElement;
	gearPanel!: HTMLElement;
	gearFields: GearFields | null = null;
	gearBackgroundLoaded = false;
	// The layout value the page actually rendered with (captured once per
	// activation, before any live change) — the block's own markup only
	// swaps to a new layout's template on reload (see hero.twig's own
	// {% include %} dispatch), so this is what renderGearLayout() compares
	// against to know whether to prompt for one.
	initialLayoutValue: string | null = null;

	constructor() {
		super();

		// Real Craft action URLs, rendered server-side via actionUrl() —
		// never hardcoded here, since the exact path structure depends on
		// this install's own routing config (config/general.php).
		this.saveUrl = this.getAttribute('data-save-url') ?? '';
		this.reorderUrl = this.getAttribute('data-reorder-url') ?? '';
		this.gearUrl = this.getAttribute('data-gear-url') ?? '';

		this.liveRegion = document.createElement('div');
		this.liveRegion.className = 'inline-edit__status visually-hidden';
		this.liveRegion.setAttribute('role', 'status');
		this.liveRegion.setAttribute('aria-live', 'polite');
		this.append(this.liveRegion);

		this.toolbar = this.buildToolbar();
	}

	connectedCallback(): void {
		document.addEventListener('click', this);
		document.addEventListener('focusin', this);
		document.addEventListener('focusout', this);
		document.addEventListener('keydown', this);
	}

	disconnectedCallback(): void {
		document.removeEventListener('click', this);
		document.removeEventListener('focusin', this);
		document.removeEventListener('focusout', this);
		document.removeEventListener('keydown', this);
	}

	handleEvent(event: Event): void {
		if (!this.editingEnabled()) return;

		const target = event.target as HTMLElement;

		if (event.type === 'keydown') {
			if ((event as KeyboardEvent).key === 'Escape') {
				// Closing the panel takes one Escape press, not two — the
				// block itself stays active, so a second Escape (or a
				// normal blur) is still what ends editing entirely.
				if (!this.gearPanel.hidden) {
					this.closeGearPanel();
					this.gearButton.focus();
				} else {
					this.deactivate();
				}
			}
			return;
		}

		if (event.type === 'focusin') {
			const block = target.closest<HTMLElement>('[data-inline-block]');
			if (block) this.activate(block);
			return;
		}

		if (event.type === 'focusout') {
			const field = target.closest<HTMLElement>('[data-inline-edit]');
			if (field && field.isContentEditable) this.saveField(field);
			return;
		}

		// click
		if (target.closest('[data-inline-edit-toolbar]')) return; // its own buttons handle themselves
		if (target.closest('[data-inline-item-handle]')) return; // ditto

		const block = target.closest<HTMLElement>('[data-inline-block]');
		if (block) {
			// A card is commonly a stretched-link (the whole block wrapped in
			// or overlaid by an <a>, e.g. format.getLinkAria()) — without
			// this, activating a block also navigates away via that link's
			// own default action, which real-browser testing caught (a
			// click meant to start editing instead left the page).
			event.preventDefault();
			this.activate(block);

			// contentEditable is set reactively, inside activate() — by the
			// time it's true the browser's own click-to-focus decision has
			// already happened, so it never lands in the field on its own.
			// Confirmed by real-browser testing: without this, clicking
			// straight into "card 1" focused <body>, not the heading.
			const field = target.closest<HTMLElement>('[data-inline-edit]');
			field?.focus();
		} else if (this.activeBlock) {
			this.deactivate();
		}
	}

	/**
	 * The show/hide toggle only hides the visual affordance via CSS
	 * (body[data-inline-editing="false"] hides outline+toolbar) — this is
	 * the functional counterpart, so a hidden region also can't be
	 * activated by tabbing into it while the toggle is off.
	 */
	editingEnabled(): boolean {
		return document.body.dataset.inlineEditing === 'true';
	}

	activate(block: HTMLElement): void {
		if (this.activeBlock === block) return;

		if (this.activeBlock) this.deactivate();

		this.activeBlock = block;
		block.setAttribute('data-inline-active', 'true');

		block.querySelectorAll<HTMLElement>('[data-inline-edit]').forEach((field) => {
			field.contentEditable = 'true';
		});

		const header = block.closest('header');
		this.toolbar.classList.toggle('inline-edit__toolbar--bottom-seam', !!header);
		block.append(this.toolbar);
		this.updateMoveButtons();
		this.addItemHandles(block);
		this.updateGearButton();
	}

	deactivate(): void {
		if (!this.activeBlock) return;

		this.activeBlock.querySelectorAll<HTMLElement>('[data-inline-edit]').forEach((field) => {
			field.contentEditable = 'false';
		});

		this.removeItemHandles(this.activeBlock);
		this.activeBlock.removeAttribute('data-inline-active');
		this.activeBlock = null;
		this.closeGearPanel();
		this.toolbar.remove();
	}

	buildToolbar(): HTMLElement {
		const toolbar = document.createElement('div');
		toolbar.className = 'inline-edit__toolbar';
		toolbar.setAttribute('data-inline-edit-toolbar', '');

		const up = document.createElement('button');
		up.type = 'button';
		up.className = 'inline-edit__toolbar-btn';
		up.dataset.inlineMove = 'up';
		up.setAttribute('aria-label', 'Move block up');
		up.title = 'Move block up';
		up.innerHTML = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 15l7-7 7 7"/></svg>';
		up.addEventListener('click', () => this.moveActiveBlock(-1));

		const down = document.createElement('button');
		down.type = 'button';
		down.className = 'inline-edit__toolbar-btn';
		down.dataset.inlineMove = 'down';
		down.setAttribute('aria-label', 'Move block down');
		down.title = 'Move block down';
		down.innerHTML = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 9l7 7 7-7"/></svg>';
		down.addEventListener('click', () => this.moveActiveBlock(1));

		this.gearButton = document.createElement('button');
		this.gearButton.type = 'button';
		this.gearButton.className = 'inline-edit__toolbar-btn';
		this.gearButton.hidden = true;
		this.gearButton.setAttribute('aria-label', 'Block settings');
		this.gearButton.setAttribute('aria-expanded', 'false');
		this.gearButton.title = 'Block settings';
		this.gearButton.innerHTML =
			'<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="3"/><path d="M12 3v2.5M12 18.5V21M21 12h-2.5M5.5 12H3M18.4 5.6l-1.8 1.8M7.4 16.6l-1.8 1.8M18.4 18.4l-1.8-1.8M7.4 7.4 5.6 5.6"/></svg>';
		this.gearButton.addEventListener('click', () => this.toggleGearPanel());

		this.gearPanel = document.createElement('div');
		this.gearPanel.className = 'inline-edit__gear-panel';
		this.gearPanel.hidden = true;
		this.gearPanel.addEventListener('change', (event) => this.onGearPanelChange(event));
		// A layout button's own click handler rebuilds the row it belongs
		// to (renderGearLayout()'s optimistic update) — that detaches the
		// just-clicked button from the DOM before its click event finishes
		// bubbling, so by the time it reached document, target.closest()
		// on the now-parentless node couldn't find the toolbar wrapper and
		// deactivate() fired (confirmed live: the whole toolbar vanished
		// mid-click). A listener directly on the panel itself — which
		// never gets replaced, only its children do — stops it going any
		// further up, independent of what any control inside it does to
		// its own subtree.
		this.gearPanel.addEventListener('click', (event) => event.stopPropagation());

		toolbar.append(up, down, this.gearButton, this.gearPanel);

		return toolbar;
	}

	/** Every other block sharing this one's owner + field — its reorder siblings. */
	blockGroup(block: HTMLElement): { owner: string; field: string; blocks: HTMLElement[] } | null {
		const parsed = this.parseBlockAttr(block);
		if (!parsed) return null;

		const blocks = Array.from(document.querySelectorAll<HTMLElement>('[data-inline-block]')).filter((el) => {
			const p = this.parseBlockAttr(el);
			return p && p.ownerId === parsed.ownerId && p.fieldHandle === parsed.fieldHandle;
		});

		return { owner: `${parsed.ownerId}:${parsed.ownerSiteId}`, field: parsed.fieldHandle, blocks };
	}

	updateMoveButtons(): void {
		if (!this.activeBlock) return;

		const group = this.blockGroup(this.activeBlock);
		// By selector, not position — the toolbar also holds a gear button
		// sharing this same .inline-edit__toolbar-btn class, so grabbing
		// the first two children by class alone would silently break here.
		const upBtn = this.toolbar.querySelector<HTMLButtonElement>('[data-inline-move="up"]');
		const downBtn = this.toolbar.querySelector<HTMLButtonElement>('[data-inline-move="down"]');
		if (!group || !upBtn || !downBtn) return;

		const index = group.blocks.indexOf(this.activeBlock);
		upBtn.disabled = index <= 0;
		downBtn.disabled = index === -1 || index >= group.blocks.length - 1;
	}

	async moveActiveBlock(direction: -1 | 1): Promise<void> {
		const block = this.activeBlock;
		if (!block) return;

		const parsed = this.parseBlockAttr(block);
		const group = this.blockGroup(block);
		if (!parsed || !group) return;

		const index = group.blocks.indexOf(block);
		const swapWith = group.blocks[index + direction];
		if (!swapWith) return;

		// Optimistic DOM reorder — swap the two block elements' positions
		// immediately, then persist. Reverted if the save fails. An exact
		// exchange, so a block without inline-edit markup between them stays
		// put, as it does on the server.
		this.swapBlocks(block, swapWith);

		const newOrder = group.blocks
			.map((el) => (el === block ? swapWith : el === swapWith ? block : el))
			.map((el) => this.parseBlockAttr(el)?.blockId)
			.filter((id): id is number => typeof id === 'number');

		try {
			const response = await fetch(this.reorderUrl, {
				method: 'POST',
				headers: { Accept: 'application/json' },
				body: this.formData({
					ownerId: String(parsed.ownerId),
					siteId: String(parsed.ownerSiteId),
					fieldHandle: parsed.fieldHandle,
					order: JSON.stringify(newOrder),
				}),
			});
			const data = await response.json();

			if (!data.success) {
				this.swapBlocks(block, swapWith);
				this.announce(data.message || 'Could not reorder.');
				return;
			}

			this.announce('Reordered.');
		} catch {
			this.swapBlocks(block, swapWith);
			this.announce('Could not reorder — check your connection.');
		}
	}

	swapBlocks(a: HTMLElement, b: HTMLElement): void {
		// Moving a node drops focus to <body>, which would end a keyboard user's run of moves.
		const active = document.activeElement;
		const focused = active instanceof HTMLElement && (a.contains(active) || b.contains(active)) ? active : null;

		const marker = document.createComment('');
		a.replaceWith(marker);
		b.replaceWith(a);
		marker.replaceWith(b);
		this.updateMoveButtons();

		if (focused) {
			const target = focused instanceof HTMLButtonElement && focused.disabled
				? this.toolbar.querySelector<HTMLButtonElement>('.inline-edit__toolbar-btn:not(:disabled)')
				: focused;
			target?.focus();
		}
	}

	/**
	 * Gear panel (Phase 3) — a block's own non-content settings (Background
	 * color, Layout variant), not its content. `data-inline-gear` is JSON
	 * embedded on the block's root tag alongside data-inline-block
	 * (InlineEdit::gearFields(), services/InlineEdit.php) — this is the
	 * single source of truth for which sections apply and their current
	 * values, resolved server-side against the active theme's own block
	 * rules (BlockFieldVisibility) so this file never re-implements that
	 * logic; it only renders whatever the server already decided.
	 */
	updateGearButton(): void {
		this.gearFields = null;
		this.gearBackgroundLoaded = false;
		this.initialLayoutValue = null;
		this.closeGearPanel();

		const raw = this.activeBlock?.dataset.inlineGear;

		if (!raw) {
			this.gearButton.hidden = true;
			return;
		}

		try {
			this.gearFields = JSON.parse(raw) as GearFields;
		} catch {
			this.gearFields = null;
		}

		this.initialLayoutValue = this.gearFields?.layout?.value ?? null;
		this.gearButton.hidden = !this.gearFields || (!this.gearFields.background && !this.gearFields.layout);
	}

	toggleGearPanel(): void {
		if (this.gearPanel.hidden) {
			this.openGearPanel();
		} else {
			this.closeGearPanel();
		}
	}

	closeGearPanel(): void {
		this.gearPanel.hidden = true;
		this.gearButton.setAttribute('aria-expanded', 'false');
	}

	async openGearPanel(): Promise<void> {
		this.gearPanel.hidden = false;
		this.gearButton.setAttribute('aria-expanded', 'true');
		this.renderGearLayout();

		// Background needs a real fetch (ColorChip::getInputHtml() wants a
		// live Entry for its per-page-theme color resolution) — layout
		// doesn't, its options are already embedded. Fetched once per
		// activation, not once per open (updateGearButton() resets the
		// flag whenever a different block becomes active).
		if (!this.gearBackgroundLoaded) {
			await this.refreshGearPanel();
		}
	}

	renderGearLayout(): void {
		const layout = this.gearFields?.layout;
		let row = this.gearPanel.querySelector<HTMLElement>('[data-inline-gear-row="layout"]');

		if (!layout) {
			row?.remove();
			return;
		}

		if (!row) {
			row = document.createElement('div');
			row.className = 'inline-edit__gear-panel-row';
			row.dataset.inlineGearRow = 'layout';
			// Above Background: picking a layout can change whether
			// Background even applies, so the control that decides comes
			// first, not the one that might disappear.
			this.gearPanel.prepend(row);
		}

		row.innerHTML = '';

		const options = document.createElement('div');
		options.className = 'inline-edit__layout-options';

		layout.options.forEach((option) => {
			const button = document.createElement('button');
			button.type = 'button';
			button.className = 'inline-edit__layout-option';
			button.classList.toggle('is-active', option.value === layout.value);

			// The CP's own layout icons (verbb/buttonbox's imageUrl option
			// config — already a root-relative URL, e.g. /assets/cms/
			// images/layout-hero-standard.svg) are stroke SVGs with the
			// CP's own light-background color baked into an inline
			// style="color:..." attribute — wrong (near-invisible) on this
			// dark panel, and not something an <img> tag lets CSS repaint.
			// A CSS mask sidesteps that entirely: it only ever reads the
			// image's rendered alpha as a stencil, never its color, so the
			// icon comes out in whatever background-color this panel
			// already uses for icons elsewhere, regardless of what color
			// the source file itself was drawn in.
			if (option.imageUrl) {
				const icon = document.createElement('span');
				icon.className = 'inline-edit__layout-option-icon';
				icon.style.maskImage = `url("${option.imageUrl}")`;
				icon.style.setProperty('-webkit-mask-image', `url("${option.imageUrl}")`);
				button.append(icon);
				// Icon-only, per the mockup — the label still exists (an
				// accessible name, and a native tooltip on hover) but
				// isn't shown when there's an icon to stand in for it. A
				// field with no icon (layoutForms' plain Dropdown options
				// have no imageUrl) falls back to showing it, since
				// there'd otherwise be nothing on the button at all.
				button.title = option.label;
			}

			const label = document.createElement('span');
			label.textContent = option.label;
			label.className = option.imageUrl ? 'visually-hidden' : '';
			button.append(label);
			button.setAttribute('aria-pressed', String(option.value === layout.value));
			button.addEventListener('click', () => this.saveGearLayout(layout.handle, option.value));
			options.append(button);
		});

		row.append(options);

		// The block's own markup only swaps to a new layout's template on
		// reload (hero.twig's own {% include %} dispatch, etc. — never
		// re-templated live, see the spec's "Save endpoint" note on this),
		// so once the saved value has actually moved away from what the
		// page rendered with, offer the one action that would show it.
		if (this.initialLayoutValue !== null && layout.value !== this.initialLayoutValue) {
			const reload = document.createElement('button');
			reload.type = 'button';
			reload.className = 'inline-edit__gear-reload';
			reload.setAttribute('aria-label', 'Reload the page to see the new layout');
			reload.title = 'Reload the page to see the new layout';
			reload.innerHTML =
				'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3.5 12a8.5 8.5 0 0 1 14.6-5.9M20.5 12a8.5 8.5 0 0 1-14.6 5.9"/><path d="M18.1 3.5v3.6h-3.6M5.9 20.5v-3.6h3.6"/></svg>';
			reload.addEventListener('click', () => window.location.reload());
			row.append(reload);
		}
	}

	renderGearBackground(background?: { visible: boolean; html?: string }): void {
		let row = this.gearPanel.querySelector<HTMLElement>('[data-inline-gear-row="background"]');

		if (!background?.visible || !background.html) {
			row?.remove();
			return;
		}

		if (!row) {
			row = document.createElement('div');
			row.className = 'inline-edit__gear-panel-row';
			row.dataset.inlineGearRow = 'background';
			this.gearPanel.append(row);
		}

		// The real ColorChip field markup, reused verbatim (see
		// InlineEditController::actionGearPanel()) — restyled only via CSS
		// (inlineEdit.pcss), never reimplemented here.
		row.innerHTML = background.html;
	}

	/**
	 * Re-fetches the gear panel's own data for the active block —
	 * Background's visibility + real markup, always; `candidateLayoutValues`
	 * is what makes "switch layout, Background shows/hides live" possible:
	 * it asks the server "if this were saved instead," without saving
	 * anything, using the exact same BlockFieldVisibility check the first
	 * render and the save endpoint both use.
	 */
	async refreshGearPanel(candidateLayoutValues?: Record<string, string>): Promise<void> {
		const block = this.activeBlock;
		const parsed = block ? this.parseBlockAttr(block) : null;
		if (!block || !parsed || !this.gearUrl) return;

		// Not `${this.gearUrl}?${params}` — actionUrl() already returns a
		// `?p=...`-style URL when pretty URLs are off, so naively appending
		// a second `?` produced a malformed request (confirmed live: a 404,
		// Craft read the whole "gear-panel?elementId=..." as the route).
		// The URL API merges into whichever form (query-string or clean
		// path) actionUrl() actually returned.
		const url = new URL(this.gearUrl, window.location.origin);
		url.searchParams.set('elementId', String(parsed.blockId));
		url.searchParams.set('siteId', String(parsed.ownerSiteId));

		if (candidateLayoutValues) {
			url.searchParams.set('layoutValues', JSON.stringify(candidateLayoutValues));
		}

		try {
			const response = await fetch(url.toString(), {
				headers: { Accept: 'application/json' },
			});
			const data = await response.json();
			this.renderGearBackground(data.background);
			this.gearBackgroundLoaded = true;
		} catch {
			this.announce('Could not load block settings — check your connection.');
		}
	}

	async onGearPanelChange(event: Event): Promise<void> {
		const target = event.target;

		// The real ColorChip radios, firing their own native `change` —
		// its field handle is hardcoded rather than read off gearFields,
		// since Background can become visible live (a layout switch) after
		// the block activated with no `background` key in its embedded
		// JSON at all, and there is exactly one Background field handle in
		// this whole config (config/stables/inline-editing.php's
		// `gearFields`) to hardcode.
		if (!(target instanceof HTMLInputElement) || !target.matches('.colorchip__input')) return;

		const block = this.activeBlock;
		const ok = await this.saveGearField('backgroundRole', target.value);

		// Unlike Layout, Background's own markup (a plain class on the
		// block's own root, background.twig's classes() macro) has nothing
		// else riding on it — no template swap, no nested-field visibility
		// — so it's safe to apply live instead of prompting for a reload.
		if (ok && block) this.applyBackgroundRoleLive(block, target.value);
	}

	/**
	 * Mirrors _blocks/partials/background.twig's classes() macro: `bg--
	 * {role}` (only when a role is set) plus `section--has-bg` (when a
	 * role OR an image is set — `has-bg-image` marks the image half,
	 * untouched here since this only ever changes the role).
	 */
	applyBackgroundRoleLive(block: HTMLElement, role: string): void {
		Array.from(block.classList)
			.filter((cls) => cls.startsWith('bg--'))
			.forEach((cls) => block.classList.remove(cls));

		if (role) {
			block.classList.add(`bg--${role}`);
		}

		block.classList.toggle('section--has-bg', Boolean(role) || block.classList.contains('has-bg-image'));
	}

	async saveGearLayout(fieldHandle: string, value: string): Promise<void> {
		const layout = this.gearFields?.layout;
		if (!layout) return;

		const previous = layout.value;
		layout.value = value;
		this.renderGearLayout();

		const ok = await this.saveGearField(fieldHandle, value);

		if (!ok) {
			layout.value = previous;
			this.renderGearLayout();
			return;
		}

		// Live re-check: does Background now show/hide given this new,
		// just-saved layout? Never computed here — only the server
		// (BlockFieldVisibility, the same check actionSave() itself
		// enforces) decides that.
		await this.refreshGearPanel({ [fieldHandle]: value });
	}

	/** Shared by both gear sections — same shape as saveField() below, minus the optimistic-lock token (data-inline-block carries no dateUpdated). */
	async saveGearField(fieldHandle: string, value: string): Promise<boolean> {
		const block = this.activeBlock;
		const parsed = block ? this.parseBlockAttr(block) : null;
		if (!block || !parsed) return false;

		try {
			const response = await fetch(this.saveUrl, {
				method: 'POST',
				headers: { Accept: 'application/json' },
				body: this.formData({
					elementId: String(parsed.blockId),
					siteId: String(parsed.ownerSiteId),
					fieldHandle,
					value,
				}),
			});
			const data = await response.json();

			if (!data.success) {
				this.announce(data.message || 'Could not save.');
				return false;
			}

			this.announce('Saved.');
			return true;
		} catch {
			this.announce('Could not save — check your connection.');
			return false;
		}
	}

	/**
	 * Reorder within a block's own items (a card in Cards, a slide in a
	 * Slider) — same mechanism as block reorder, one field level down: the
	 * actionReorder endpoint is already generic (any owner id + field
	 * handle), so no server changes were needed for this. Every item in an
	 * active block gets its own move-up/-down handle simultaneously
	 * (unlike the single shared block toolbar), since there's no one
	 * "active item" the way there's one active block.
	 */
	addItemHandles(block: HTMLElement): void {
		block.querySelectorAll<HTMLElement>('[data-inline-item]').forEach((item) => {
			const handle = this.buildItemHandle(item);
			item.append(handle);
			this.updateItemHandleButtons(item, handle);
		});
	}

	removeItemHandles(block: HTMLElement): void {
		block.querySelectorAll('[data-inline-item-handle]').forEach((handle) => handle.remove());
	}

	buildItemHandle(item: HTMLElement): HTMLElement {
		const handle = document.createElement('div');
		handle.className = 'inline-edit__item-handle';
		handle.setAttribute('data-inline-item-handle', '');

		const up = document.createElement('button');
		up.type = 'button';
		up.className = 'inline-edit__item-handle-btn';
		up.setAttribute('aria-label', 'Move item up');
		up.title = 'Move item up';
		up.innerHTML = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 15l7-7 7 7"/></svg>';
		up.addEventListener('click', () => this.moveItem(item, -1));

		const down = document.createElement('button');
		down.type = 'button';
		down.className = 'inline-edit__item-handle-btn';
		down.setAttribute('aria-label', 'Move item down');
		down.title = 'Move item down';
		down.innerHTML = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 9l7 7 7-7"/></svg>';
		down.addEventListener('click', () => this.moveItem(item, 1));

		handle.append(up, down);

		return handle;
	}

	/** Every other item sharing this one's owner (the block) + field — its reorder siblings. */
	itemGroup(item: HTMLElement): { items: HTMLElement[] } | null {
		const parsed = this.parseItemAttr(item);
		if (!parsed) return null;

		const items = Array.from(document.querySelectorAll<HTMLElement>('[data-inline-item]')).filter((el) => {
			const p = this.parseItemAttr(el);
			return p && p.ownerId === parsed.ownerId && p.fieldHandle === parsed.fieldHandle;
		});

		return { items };
	}

	updateItemHandleButtons(item: HTMLElement, handle: HTMLElement): void {
		const group = this.itemGroup(item);
		const [upBtn, downBtn] = handle.querySelectorAll<HTMLButtonElement>('.inline-edit__item-handle-btn');
		if (!group || !upBtn || !downBtn) return;

		const index = group.items.indexOf(item);
		upBtn.disabled = index <= 0;
		downBtn.disabled = index === -1 || index >= group.items.length - 1;
		// A single-item group (most imageText/hero/banner blocks) can never
		// move either way — nothing to show a handle for.
		handle.hidden = upBtn.disabled && downBtn.disabled;
	}

	async moveItem(item: HTMLElement, direction: -1 | 1): Promise<void> {
		const parsed = this.parseItemAttr(item);
		const group = this.itemGroup(item);
		if (!parsed || !group) return;

		const index = group.items.indexOf(item);
		const swapWith = group.items[index + direction];
		if (!swapWith) return;

		this.swapItems(item, swapWith);

		const newOrder = group.items
			.map((el) => (el === item ? swapWith : el === swapWith ? item : el))
			.map((el) => this.parseItemAttr(el)?.itemId)
			.filter((id): id is number => typeof id === 'number');

		try {
			const response = await fetch(this.reorderUrl, {
				method: 'POST',
				headers: { Accept: 'application/json' },
				body: this.formData({
					// The item's "owner" is the BLOCK entry (an item is a
					// nested Matrix entry exactly like a block is, one level
					// deeper) — same endpoint, same shape as block reorder.
					ownerId: String(parsed.ownerId),
					siteId: String(parsed.ownerSiteId),
					fieldHandle: parsed.fieldHandle,
					order: JSON.stringify(newOrder),
				}),
			});
			const data = await response.json();

			if (!data.success) {
				this.swapItems(item, swapWith);
				this.announce(data.message || 'Could not reorder.');
				return;
			}

			this.announce('Reordered.');
		} catch {
			this.swapItems(item, swapWith);
			this.announce('Could not reorder — check your connection.');
		}
	}

	swapItems(a: HTMLElement, b: HTMLElement): void {
		const active = document.activeElement;
		const focused = active instanceof HTMLElement && (a.contains(active) || b.contains(active)) ? active : null;

		const marker = document.createComment('');
		a.replaceWith(marker);
		b.replaceWith(a);
		marker.replaceWith(b);

		const handleA = a.querySelector<HTMLElement>('[data-inline-item-handle]');
		const handleB = b.querySelector<HTMLElement>('[data-inline-item-handle]');
		if (handleA) this.updateItemHandleButtons(a, handleA);
		if (handleB) this.updateItemHandleButtons(b, handleB);

		if (focused) {
			const stillEnabled = focused instanceof HTMLButtonElement && !focused.disabled;
			const target = stillEnabled
				? focused
				: (handleA?.querySelector<HTMLButtonElement>('.inline-edit__item-handle-btn:not(:disabled)') ??
					handleB?.querySelector<HTMLButtonElement>('.inline-edit__item-handle-btn:not(:disabled)'));
			target?.focus();
		}
	}

	async saveField(field: HTMLElement): Promise<void> {
		const parsed = this.parseFieldAttr(field);
		if (!parsed) return;

		try {
			const response = await fetch(this.saveUrl, {
				method: 'POST',
				headers: { Accept: 'application/json' },
				body: this.formData({
					elementId: String(parsed.elementId),
					siteId: String(parsed.siteId),
					fieldHandle: parsed.fieldHandle,
					// Plain text only — v1's whole fields are "minimal/no
					// formatting UI" (per spec), and some of them (textPlain,
					// citeName, citeTitle, excerpt) are genuine PlainText
					// fields with no purification of their own. innerHTML
					// would let a browser-inserted <br>/<div> (e.g. from
					// pressing Enter) end up stored verbatim in one of those.
					value: field.textContent ?? '',
					dateUpdated: String(parsed.dateUpdated),
				}),
			});
			const data = await response.json();

			if (!data.success) {
				this.announce(data.message || 'Could not save.');
				return;
			}

			field.textContent = data.value;
			this.setFieldAttr(field, { ...parsed, dateUpdated: data.dateUpdated ?? parsed.dateUpdated });
			this.announce('Saved.');
		} catch {
			this.announce('Could not save — check your connection.');
		}
	}

	formData(params: Record<string, string>): FormData {
		const data = new FormData();
		if (window.csrfTokenName && window.csrfTokenValue) {
			data.set(window.csrfTokenName, window.csrfTokenValue);
		}
		Object.entries(params).forEach(([key, value]) => data.set(key, value));
		return data;
	}

	announce(message: string): void {
		this.liveRegion.textContent = '';
		// Re-triggers the live-region announcement even if the text is
		// identical to last time (e.g. two "Saved." in a row).
		window.requestAnimationFrame(() => {
			this.liveRegion.textContent = message;
		});
	}

	parseFieldAttr(el: HTMLElement): { elementId: number; siteId: number; fieldHandle: string; dateUpdated: number } | null {
		const raw = el.getAttribute('data-inline-edit');
		if (!raw) return null;
		const [elementId, siteId, fieldHandle, dateUpdated] = raw.split(':');
		if (!elementId || !siteId || !fieldHandle) return null;
		return { elementId: Number(elementId), siteId: Number(siteId), fieldHandle, dateUpdated: Number(dateUpdated ?? 0) };
	}

	setFieldAttr(el: HTMLElement, value: { elementId: number; siteId: number; fieldHandle: string; dateUpdated: number }): void {
		el.setAttribute('data-inline-edit', `${value.elementId}:${value.siteId}:${value.fieldHandle}:${value.dateUpdated}`);
	}

	parseBlockAttr(el: HTMLElement): { ownerId: number; ownerSiteId: number; fieldHandle: string; blockId: number } | null {
		const raw = el.getAttribute('data-inline-block');
		if (!raw) return null;
		const [ownerId, ownerSiteId, fieldHandle, blockId] = raw.split(':');
		if (!ownerId || !ownerSiteId || !fieldHandle || !blockId) return null;
		return { ownerId: Number(ownerId), ownerSiteId: Number(ownerSiteId), fieldHandle, blockId: Number(blockId) };
	}

	/** Same shape as parseBlockAttr — an item is a nested Matrix entry
	 * exactly like a block is, just one level deeper; its "owner" here is
	 * the block entry, not the page. */
	parseItemAttr(el: HTMLElement): { ownerId: number; ownerSiteId: number; fieldHandle: string; itemId: number } | null {
		const raw = el.getAttribute('data-inline-item');
		if (!raw) return null;
		const [ownerId, ownerSiteId, fieldHandle, itemId] = raw.split(':');
		if (!ownerId || !ownerSiteId || !fieldHandle || !itemId) return null;
		return { ownerId: Number(ownerId), ownerSiteId: Number(ownerSiteId), fieldHandle, itemId: Number(itemId) };
	}
});

export {};
