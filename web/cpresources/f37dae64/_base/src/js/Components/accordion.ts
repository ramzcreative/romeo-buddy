import {v4 as uuidv4} from 'uuid';
import { animate } from 'motion';

customElements.define('accordion-group', class extends HTMLElement {
	headings: NodeListOf<Element>;
	exclusive: boolean;
    active: boolean;

	/**
	 * The class constructor object
	 */
	constructor() {
		// Gives element access to the parent class properties
		super();

		// Get headings
		this.headings = this.querySelectorAll(this.getAttribute('headings') || '');
		if (!this.headings.length) return;

		// Define if exclusive accordion or not
		this.exclusive = this.hasAttribute('exclusive');

		// Setup the DOM
		this.setup();

		// Listen for events
		this.addEventListener('click', this);
	}

	/**
	 * Handle events on the Web Component
	 * @param  {Event} event The event object
	 */
	handleEvent(event: Event): void {
		// Get the clicked trigger button
		const target = event.target as HTMLElement;
		const btn = target.closest('[accordion-trigger]') as HTMLElement | null;
		if (!btn) return;

		// Should accordion be hidden or expanded?
		const isHidden = btn.getAttribute('aria-expanded') === 'true';

		// Toggle the accordion
		this.toggleAccordion(btn, isHidden);

		// If exclusive, close all other open accordions
		if (!this.exclusive) return;
		const triggers = this.querySelectorAll('[accordion-trigger][aria-expanded="true"]');
		for (const trigger of triggers) {
			if (trigger === btn) continue;
			this.toggleAccordion(trigger as HTMLElement, true);
		}
	}

	/**
	 * Toggle the accordion pane
	 * @param  {Element}  btn     The accordion trigger
	 * @param  {Boolean} isHidden If true, the content be hidden
	 */
	toggleAccordion(btn: HTMLElement, isHidden: boolean): void {
		// Get the associated content
		const contentId = btn.getAttribute('aria-controls');
		if (!contentId) return;
		const content = this.querySelector(`#${contentId}`) as HTMLElement | null;
		if (!content) return;

        // Animation configurations
        const transition = { duration: 0.3 }

		// Show the content and update ARIA
		btn.setAttribute('aria-expanded', isHidden ? 'false' : 'true');
		if (isHidden) {
            animate(
                content,
                { height: 0 },
                transition
            )

			//content.setAttribute('hidden', '');
		} else {
            //content.removeAttribute('hidden');

            animate(
                content,
                { height: "auto" },
                transition
            )
		}
	}

	/**
	 * Setup the DOM on initial load
	 */
	setup(): void {
		for (const heading of this.headings) {
			// Create toggle button
			const btn = document.createElement('button');
			btn.setAttribute('accordion-trigger', '');
			btn.innerHTML = heading.innerHTML;
			heading.innerHTML = '';
			heading.append(btn);

			// Get content and hide it
			const content = heading.nextElementSibling as HTMLElement | null;
			if (!content) continue;
			//content.setAttribute('hidden', '');

			//  Define an ID if one is missing
			if (!content.id) {
				content.id = `accordion-group_${uuidv4()}`;
			}

			// Add ARIA attributes
			btn.setAttribute('aria-expanded', 'false');
			btn.setAttribute('aria-controls', content.id);

            // Define if active accordion or not
		    const isActive = heading.getAttribute('active');
            if(isActive == 'true')
                this.toggleAccordion(btn, false)
		}
	}
});