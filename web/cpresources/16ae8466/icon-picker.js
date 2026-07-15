(function () {
	function initIconPicker(root) {
		if (root.dataset.iconpickerInit) return;
		root.dataset.iconpickerInit = '1';

		const hiddenInput = root.querySelector('[data-iconpicker-value]');
		const tabs = root.querySelectorAll('[data-iconpicker-tab]');
		const searchInput = root.querySelector('[data-iconpicker-search]');

		tabs.forEach((tab) => {
			tab.addEventListener('click', () => {
				const set = tab.dataset.iconpickerTab;
				tabs.forEach((t) => {
					const isActive = t === tab;
					t.classList.toggle('is-active', isActive);
					t.setAttribute('aria-selected', isActive ? 'true' : 'false');
				});
				root.querySelectorAll('[data-iconpicker-set]').forEach((grid) => {
					grid.classList.toggle('hidden', grid.dataset.iconpickerSet !== set);
				});
			});
		});

		if (searchInput) {
			searchInput.addEventListener('input', () => {
				const query = searchInput.value.trim().toLowerCase();
				root.querySelectorAll('[data-iconpicker-icon]').forEach((btn) => {
					const label = btn.dataset.iconpickerLabel || '';
					btn.classList.toggle('is-filtered-out', query.length > 0 && !label.includes(query));
				});
			});
		}

		root.querySelectorAll('[data-iconpicker-icon]').forEach((btn) => {
			btn.addEventListener('click', () => {
				const key = btn.dataset.iconpickerIcon;
				const newValue = hiddenInput.value === key ? '' : key; // click again to deselect

				hiddenInput.value = newValue;
				hiddenInput.dispatchEvent(new Event('change', { bubbles: true }));

				root.querySelectorAll('[data-iconpicker-icon]').forEach((b) => {
					const selected = b.dataset.iconpickerIcon === newValue;
					b.classList.toggle('is-selected', selected);
					b.setAttribute('aria-pressed', selected ? 'true' : 'false');
				});
			});
		});
	}

	function scan(root) {
		(root || document).querySelectorAll('[data-iconpicker]').forEach(initIconPicker);
	}

	document.addEventListener('DOMContentLoaded', () => scan());

	// Catch pickers added dynamically (new Matrix block, slideout, etc.) —
	// the CP doesn't reload the page for these, so DOMContentLoaded alone
	// would miss them.
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
