(function () {
	function initIconPicker(root) {
		if (root.dataset.iconpickerInit) return;
		root.dataset.iconpickerInit = '1';

		const hiddenInput = root.querySelector('[data-iconpicker-value]');
		const current = root.querySelector('[data-iconpicker-current]');
		const chooseBtn = root.querySelector('.iconpicker-choose-btn');
		const removeBtn = root.querySelector('.iconpicker-remove-btn');
		const modalEl = root.querySelector('[data-iconpicker-modal]');
		const tabs = root.querySelectorAll('[data-iconpicker-tab]');
		const searchInput = root.querySelector('[data-iconpicker-search]');

		function selectIcon(key, svgHtml) {
			hiddenInput.value = key;
			hiddenInput.dispatchEvent(new Event('change', { bubbles: true }));

			current.innerHTML = svgHtml || '';
			current.classList.toggle('is-empty', !key);

			// Scoped to modalEl, not root: Garnish.Modal reparents modalEl to
			// <body> as soon as it's first shown, so by the time this runs
			// it's no longer a descendant of root — a root-scoped query here
			// would silently find nothing.
			modalEl.querySelectorAll('[data-iconpicker-icon]').forEach((b) => {
				const selected = b.dataset.iconpickerIcon === key;
				b.classList.toggle('is-selected', selected);
				b.setAttribute('aria-pressed', selected ? 'true' : 'false');
			});

			if (chooseBtn) {
				chooseBtn.textContent = key
					? (window.Craft ? Craft.t('app', 'Change') : 'Change')
					: (window.Craft ? Craft.t('app', 'Choose') : 'Choose');
			}
			if (removeBtn) {
				removeBtn.classList.toggle('hidden', !key);
			}
		}

		// Core selection behavior is wired up unconditionally, before the
		// modal chrome — if Garnish.Modal fails for any reason below, icon
		// selection/search/tabs still work, just without the overlay/shade.
		if (removeBtn) {
			removeBtn.addEventListener('click', (e) => {
				e.preventDefault();
				selectIcon('', '');
			});
		}

		tabs.forEach((tab) => {
			tab.addEventListener('click', () => {
				const set = tab.dataset.iconpickerTab;
				tabs.forEach((t) => {
					const isActive = t === tab;
					t.classList.toggle('is-active', isActive);
					t.setAttribute('aria-selected', isActive ? 'true' : 'false');
				});
				modalEl.querySelectorAll('[data-iconpicker-set]').forEach((grid) => {
					grid.classList.toggle('hidden', grid.dataset.iconpickerSet !== set);
				});
			});
		});

		if (searchInput) {
			searchInput.addEventListener('input', () => {
				const query = searchInput.value.trim().toLowerCase();
				modalEl.querySelectorAll('[data-iconpicker-icon]').forEach((btn) => {
					const label = btn.dataset.iconpickerLabel || '';
					btn.classList.toggle('is-filtered-out', query.length > 0 && !label.includes(query));
				});
			});
		}

		// Modal chrome. autoShow defaults to true in Garnish — without
		// autoShow:false the modal would pop open immediately on render.
		let modal = null;
		if (window.Garnish && Garnish.Modal) {
			try {
				modal = new Garnish.Modal(modalEl, {
					autoShow: false,
					hideOnEsc: true,
					hideOnShadeClick: true,
				});
			} catch (err) {
				console.error('IconPicker: failed to initialize Garnish.Modal', err);
			}
		} else {
			console.error('IconPicker: Garnish.Modal is not available — is GarnishAsset loaded?');
		}

		// Opening the modal should land on whatever's currently selected —
		// switch to its tab, clear any stale search filter that could hide
		// it, and scroll it into view — rather than always opening on the
		// first tab with the selection possibly off-screen or filtered out.
		function revealSelected() {
			const key = hiddenInput.value;
			if (!key) return;

			const selectedBtn = modalEl.querySelector(
				'[data-iconpicker-icon="' + CSS.escape(key) + '"]'
			);
			if (!selectedBtn) return;

			if (searchInput && searchInput.value) {
				searchInput.value = '';
				modalEl.querySelectorAll('[data-iconpicker-icon]').forEach((b) => {
					b.classList.remove('is-filtered-out');
				});
			}

			const grid = selectedBtn.closest('[data-iconpicker-set]');
			if (grid && tabs.length) {
				const set = grid.dataset.iconpickerSet;
				tabs.forEach((t) => {
					const isActive = t.dataset.iconpickerTab === set;
					t.classList.toggle('is-active', isActive);
					t.setAttribute('aria-selected', isActive ? 'true' : 'false');
				});
				modalEl.querySelectorAll('[data-iconpicker-set]').forEach((g) => {
					g.classList.toggle('hidden', g.dataset.iconpickerSet !== set);
				});
			}

			selectedBtn.scrollIntoView({ block: 'center' });
		}

		if (chooseBtn) {
			chooseBtn.addEventListener('click', (e) => {
				e.preventDefault();
				if (modal) {
					modal.show();
				} else {
					// no modal chrome available — fall back to just revealing
					// the picker inline rather than doing nothing at all
					modalEl.classList.toggle('is-visible-fallback');
				}
				revealSelected();
			});
		}

		modalEl.querySelectorAll('[data-iconpicker-icon]').forEach((btn) => {
			btn.addEventListener('click', () => {
				const key = btn.dataset.iconpickerIcon;
				const svgEl = btn.querySelector('.iconpicker-icon-svg');
				selectIcon(key, svgEl ? svgEl.innerHTML : '');
				if (modal) {
					modal.hide();
				} else {
					modalEl.classList.remove('is-visible-fallback');
				}
			});
		});
	}

	function scan(root) {
		(root || document).querySelectorAll('[data-iconpicker]').forEach(initIconPicker);
	}

	// The asset bundle can load after DOMContentLoaded has already fired
	// (e.g. scripts placed at the end of the CP page) — in that case the
	// event never fires again, so scan immediately if the DOM is already
	// ready instead of relying solely on the event.
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', () => scan());
	} else {
		scan();
	}

	// Catch pickers added dynamically (new Matrix block, slideout, etc.).
	new MutationObserver((mutations) => {
		for (const mutation of mutations) {
			mutation.addedNodes.forEach((node) => {
				if (node.nodeType !== 1) return;
				if (node.matches && node.matches('[data-iconpicker]')) {
					initIconPicker(node);
				} else if (node.querySelectorAll) {
					scan(node);
				}
			});
		}
	}).observe(document.body, { childList: true, subtree: true });
})();
