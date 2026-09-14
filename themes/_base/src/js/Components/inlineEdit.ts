declare global {
	interface Window {
		csrfTokenName?: string;
		csrfTokenValue?: string;
	}
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

	constructor() {
		super();

		// Real Craft action URLs, rendered server-side via actionUrl() —
		// never hardcoded here, since the exact path structure depends on
		// this install's own routing config (config/general.php).
		this.saveUrl = this.getAttribute('data-save-url') ?? '';
		this.reorderUrl = this.getAttribute('data-reorder-url') ?? '';

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
				this.deactivate();
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
	}

	deactivate(): void {
		if (!this.activeBlock) return;

		this.activeBlock.querySelectorAll<HTMLElement>('[data-inline-edit]').forEach((field) => {
			field.contentEditable = 'false';
		});

		this.activeBlock.removeAttribute('data-inline-active');
		this.activeBlock = null;
		this.toolbar.remove();
	}

	buildToolbar(): HTMLElement {
		const toolbar = document.createElement('div');
		toolbar.className = 'inline-edit__toolbar';
		toolbar.setAttribute('data-inline-edit-toolbar', '');

		const up = document.createElement('button');
		up.type = 'button';
		up.className = 'inline-edit__toolbar-btn';
		up.setAttribute('aria-label', 'Move block up');
		up.title = 'Move block up';
		up.innerHTML = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 15l7-7 7 7"/></svg>';
		up.addEventListener('click', () => this.moveActiveBlock(-1));

		const down = document.createElement('button');
		down.type = 'button';
		down.className = 'inline-edit__toolbar-btn';
		down.setAttribute('aria-label', 'Move block down');
		down.title = 'Move block down';
		down.innerHTML = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 9l7 7 7-7"/></svg>';
		down.addEventListener('click', () => this.moveActiveBlock(1));

		toolbar.append(up, down);

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
		const [upBtn, downBtn] = this.toolbar.querySelectorAll<HTMLButtonElement>('.inline-edit__toolbar-btn');
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
});

export {};
