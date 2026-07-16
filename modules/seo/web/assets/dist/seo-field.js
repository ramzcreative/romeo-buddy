(function () {
	// Google's current on-screen truncation guidance — presentation only,
	// not persisted, so no field-settings config needed for these numbers.
	const LIMITS = { title: 60, description: 160 };

	function initSeoField(root) {
		if (root.dataset.seoInit) return;
		root.dataset.seoInit = '1';

		const titleInput = root.querySelector('[data-seo-title]');
		const descriptionInput = root.querySelector('[data-seo-description]');
		const serpTitle = root.querySelector('[data-seo-serp-title]');
		const serpDescription = root.querySelector('[data-seo-serp-description]');
		const fallbackTitle = root.dataset.seoFallbackTitle || '';
		const fallbackDescription = root.dataset.seoFallbackDescription || '';

		function updateCount(field, input, limit) {
			const wrapper = root.querySelector('[data-seo-count="' + field + '"]');
			if (!wrapper || !input) return;
			const length = input.value.length;
			wrapper.textContent = length + ' / ' + limit;
			wrapper.classList.toggle('is-over-limit', length > limit);
		}

		function updatePreview() {
			if (serpTitle) {
				serpTitle.textContent = (titleInput && titleInput.value) || fallbackTitle || '';
			}
			if (serpDescription) {
				serpDescription.textContent = (descriptionInput && descriptionInput.value) || fallbackDescription || '';
			}
		}

		if (titleInput) {
			titleInput.addEventListener('input', () => {
				updateCount('title', titleInput, LIMITS.title);
				updatePreview();
			});
			updateCount('title', titleInput, LIMITS.title);
		}

		if (descriptionInput) {
			descriptionInput.addEventListener('input', () => {
				updateCount('description', descriptionInput, LIMITS.description);
				updatePreview();
			});
			updateCount('description', descriptionInput, LIMITS.description);
		}

		updatePreview();
	}

	function scan(root) {
		(root || document).querySelectorAll('[data-seo-field]').forEach(initSeoField);
	}

	// Same DOMContentLoaded-already-fired guard used by iconpicker's JS —
	// the asset bundle can load after the CP's own scripts have already run.
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', () => scan());
	} else {
		scan();
	}

	// Catch fields added dynamically (new Matrix block, slideout, etc.).
	new MutationObserver((mutations) => {
		for (const mutation of mutations) {
			mutation.addedNodes.forEach((node) => {
				if (node.nodeType !== 1) return;
				if (node.matches && node.matches('[data-seo-field]')) {
					initSeoField(node);
				} else if (node.querySelectorAll) {
					scan(node);
				}
			});
		}
	}).observe(document.body, { childList: true, subtree: true });
})();
