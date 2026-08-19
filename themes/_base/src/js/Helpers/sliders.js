// Swiper slider
const swiperEls = document.querySelectorAll('swiper-container');
if(swiperEls.length){
	Promise.all([
        import ('swiper/element/bundle'),
		//import('swiper'),
		//import ('swiper/modules'),
		import('../SliderEffects/effect-carousel.esm.js'),
        import('../SliderEffects/effect-material.esm.js'),
		import('../SliderEffects/effect-material-slant.esm.js'),
		import('swiper/swiper-bundle.css')
	]).then(
		([{ Swiper, register }, {default: EffectCarousel},{default: EffectMaterial},{default: EffectMaterialSlant}]) => {
            register();

			//above forEach loop breaks, we must find these one by one for now
			// - we'll only allow MAX 10 sliders
			swiperEls.forEach((swiperEl) => {
                const nextBtn = swiperEl.querySelector('.swiper-btn-next');
                const prevBtn = swiperEl.querySelector('.swiper-btn-prev');
                const pagination = swiperEl.querySelector('.swiper-pag');

                // swiper parameters
                const swiperParams = {
                    modules: [EffectCarousel,EffectMaterial,EffectMaterialSlant],
                    slidesPerView: 'auto',
                    watchSlidesProgress: true,
                    //a11y: false,
                    // Only declared when the elements exist. Writing
                    // {nextEl: null} does NOT fall back to Swiper Element's own
                    // labelled buttons — update-swiper only supplies those when
                    // the params are `undefined`, and null is not undefined. So
                    // a layout that sets navigation="true" without rendering
                    // buttons ended up with no keyboard affordance at all.
                    ...(nextBtn && prevBtn ? {
                        navigation: { nextEl: nextBtn, prevEl: prevBtn },
                    } : {}),
                    ...(pagination ? {
                        pagination: { el: pagination, clickable: true },
                    } : {}),
                    on: {
                        init() {
                            //ScrollTrigger.refresh();
                        },
                    },
                };

                // now we need to assign all parameters to Swiper element
                Object.assign(swiperEl, swiperParams);

                // and now initialize it
                swiperEl.initialize();
			});
		}
	);
}