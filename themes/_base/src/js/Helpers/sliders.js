// Swiper slider
const swiperEls = document.querySelectorAll('swiper-container');
if(swiperEls.length){
	Promise.all([
        import ('swiper/element/bundle'),
		//import('swiper'),
		//import ('swiper/modules'),
		import('../SliderEffects/effect-carousel.esm.js'),
        import('../SliderEffects/effect-material.esm.js'),
        import('../SliderEffects/effect-stack.esm.js'),
        import('../SliderEffects/effect-ramz-carousel.esm.js'),
		import('swiper/swiper-bundle.css')
	]).then(
		([{ Swiper, register }, {default: EffectCarousel},{default: EffectMaterial},{default: EffectStack},{default: EffectRamzCarousel}]) => {
            register();

			// A thumbs strip is a Swiper in its own right, and the slider that
			// uses it needs the live instance at init time — so every element
			// marked as thumbs is initialized first, and the rest afterwards.
			const isThumbs = (el) => el.dataset.sliderRole === 'thumbs';
			const ordered = [
				...[...swiperEls].filter(isThumbs),
				...[...swiperEls].filter((el) => !isThumbs(el)),
			];

			//above forEach loop breaks, we must find these one by one for now
			// - we'll only allow MAX 10 sliders
			ordered.forEach((swiperEl) => {
                // Lift any [data-modal-move] out of the slider BEFORE Swiper
                // initialises.
                //
                // This is what makes loop="true" safe. In loop mode Swiper
                // clones slides to fake the wrap-around, and a clone is a deep
                // copy — including any id inside it. Components/modal.ts claims
                // its triggers with
                // document.querySelectorAll('[data-toggle-modal="' + this.id + '"]'),
                // so a cloned <cta-modal> carrying a duplicate id would grab the
                // original's button and the wrong panel would open.
                //
                // Helpers/modal.js already performs this move, but main.js
                // imports the two in the same Promise.all, so which lands first
                // is a race. Doing it here as well makes the ordering
                // deterministic: by the time Swiper clones anything, the slides
                // hold only the toggle buttons, whose data-toggle-modal still
                // points at the single surviving modal. Idempotent — modal.js
                // then finds nothing left to move.
                //
                // It also survives re-cloning: Swiper rebuilds clones on update
                // and resize, and none of those slides can contain a modal any
                // more.
                const modalHolder = document.getElementById('modal-holder');

                if (modalHolder) {
                    swiperEl.querySelectorAll('[data-modal-move]').forEach((modalEl) => {
                        modalHolder.appendChild(modalEl);
                    });
                }

                // The block wrapping this slider — its arrows, tabs and
                // progress bars live outside the swiper-container.
                const block = swiperEl.closest('[data-slider-block]') ?? swiperEl.parentElement;

                // Look inside the swiper first, then the wider block: the hero
                // layout deliberately puts its arrows outside the
                // <swiper-container> so they can share the content container
                // and line up with the copy. Scoping the lookup to the swiper
                // alone found nothing there, and navigation silently did not
                // bind — the arrows rendered and did nothing on click.
                const find = (sel) => swiperEl.querySelector(sel) ?? block?.querySelector(sel) ?? null;

                const nextBtn = find('.swiper-btn-next');
                const prevBtn = find('.swiper-btn-prev');
                const pagination = find('.swiper-pag');

                // swiper parameters
                // `data-slider-thumbs` holds the selector of this slider's
                // thumbs strip. It was initialized in the first pass above, so
                // its instance is already on the element.
                const thumbsSel = swiperEl.dataset.sliderThumbs;
                const thumbsEl = thumbsSel ? document.querySelector(thumbsSel) : null;

                const swiperParams = {
                    modules: [EffectCarousel,EffectMaterial,EffectStack,EffectRamzCarousel],
                    slidesPerView: 'auto',
                    watchSlidesProgress: true,
                    //a11y: false,
                    navigation: {
                        nextEl: nextBtn,
                        prevEl: prevBtn,
                    },
                    pagination: {
                        el: pagination,
                        clickable: true,
                        //dynamicBullets: true,
                        //dynamicMainBullets: 3
                    },
                    ...(thumbsEl?.swiper ? { thumbs: { swiper: thumbsEl.swiper, autoScrollOffset: 1 } } : {}),
                    on: {
                        init() {
                            //ScrollTrigger.refresh();
                        },
                        // Swiper's own thumbs auto-scroll is disabled whenever
                        // the thumbs slider loops — `useOffset = autoScrollOffset
                        // && !thumbsSwiper.params.loop` in its thumbs module — so
                        // a looping strip never brings the active thumb into
                        // view on its own. Do it here instead, and only when the
                        // thumb is actually clipped, so a thumb already on
                        // screen doesn't cause the strip to jump.
                        slideChange(sw) {
                            const strip = sw.thumbs?.swiper;
                            if (!strip || strip.destroyed) return;

                            const active = strip.slides.find(
                                (el) => el.getAttribute('data-swiper-slide-index') === `${sw.realIndex}`,
                            );
                            if (!active) return;

                            // Layout position, not getBoundingClientRect: a rect
                            // includes transforms, so any transform on the
                            // active thumb makes it measure as overhanging its
                            // slot and the strip repositions on every change.
                            // (An active-state scale did exactly that.)
                            const left = active.offsetLeft + strip.translate;
                            const right = left + active.offsetWidth;

                            if (left < 0 || right > strip.width) {
                                strip.slideToLoop(sw.realIndex);
                            }
                        },
                        // Progress indicators read the real autoplay clock
                        // rather than running their own animation, so they
                        // cannot drift from it — and they stay correct when a
                        // viewer interrupts autoplay with an arrow or a thumb.
                        autoplayTimeLeft(sw, time, progress) {
                            if (!block) return;

                            const remaining = 1 - progress;

                            block.querySelectorAll('[data-slider-tab]').forEach((tab, i) => {
                                tab.classList.toggle('is-active', i === sw.realIndex);
                            });

                            block.querySelectorAll('[data-slider-progress]').forEach((fill) => {
                                // `swiper-slide` here is the TAG, not a class —
                                // swiper-element never adds the class — so a
                                // `.swiper-slide` selector matches nothing and
                                // every thumb progress bar silently stayed at 0.
                                const owner = fill.closest('[data-slider-tab], swiper-slide, .swiper-slide');
                                const active = owner?.classList.contains('is-active')
                                    || owner?.classList.contains('swiper-slide-thumb-active');
                                fill.style.transform = `scaleX(${active ? remaining : 0})`;
                            });
                        },
                    },
                };

                // now we need to assign all parameters to Swiper element
                Object.assign(swiperEl, swiperParams);

                // and now initialize it
                swiperEl.initialize();

                // Tabs drive the slider; slideToLoop because it loops.
                block?.querySelectorAll('[data-slide-to]').forEach((tab) => {
                    tab.addEventListener('click', () => {
                        swiperEl.swiper?.slideToLoop(Number(tab.dataset.slideTo));
                    });
                });
			});
		}
	);
}