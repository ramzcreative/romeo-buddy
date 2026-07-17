// return UserList class
export default class headerOnScroll {

	static headerHeight = 40;
	static header = null;
	static savedWidth = 0;
	static resizeTimer = null;

    constructor() {
        this.selector = "[data-header-container]";
		this.header = null;

		headerOnScroll.header = document.querySelector(this.selector);

        this.init();
    }

    // initialize plugin
    init() {
		// { passive: true } — this handler never calls preventDefault(), so
		// telling the browser that up front lets it keep scrolling on the
		// compositor thread instead of waiting on the main thread each tick.
		window.addEventListener('scroll', this.onScroll, { passive: true });
        window.addEventListener('resize', this.resize, { passive: true });
        // Was `this.resize(true)` — invokes resize() immediately and passes
        // its (undefined) return value as the listener, so this never
        // actually re-ran on DOMContentLoaded.
       	window.addEventListener('DOMContentLoaded', () => this.resize(true));
		this.resize(true);
    }
	
	/** static methods in the classes for utility purposes **/
	static setHeight() {
		if(this.header){
			headerOnScroll.headerHeight = this.header.offsetHeight;
		}
	}

	resize(loading = false) {
		/* prevent any animations during a resize - example: the nav fade from mobile -> desktop */
		/** css tricks  **/
		let currentWidth = window.innerWidth;

		if (loading == true || ( currentWidth !== headerOnScroll.savedWidth)) {
			headerOnScroll.savedWidth = currentWidth;
			headerOnScroll.setHeight();

			document.body.classList.add("resize-animation-stopper");
			// resizeTimer was a local `let` re-declared on every call, so
			// clearTimeout() below always cleared a fresh `undefined` instead
			// of the previous call's pending timer — every resize tick during
			// a drag queued its own uncancelled timeout, and whichever fired
			// first removed the stopper class while the resize was still in
			// progress. Static property instead, so it actually persists and
			// debounces across calls.
			clearTimeout(headerOnScroll.resizeTimer);
			headerOnScroll.resizeTimer = setTimeout(() => {
				document.body.classList.remove("resize-animation-stopper");
			}, 400);
		}

	}

	onScroll() {
		//check if user scrolled passed the top bar
		if (Math.abs(window.scrollY) <= (headerOnScroll.headerHeight)){
			document.body.classList.add('header-top');
		}
		else{
			document.body.classList.remove('header-top');           
		}
	}
}