// return UserList class
export default class headerOnScroll {

	static headerHeight = 40;
	static header = null;
	static savedWidth = 0;

    constructor() {
        this.selector = "[data-header-container]";
		this.header = null;

		headerOnScroll.header = document.querySelector(this.selector);
		
        this.init();
    }

    // initialize plugin
    init() {
		window.addEventListener('scroll', this.onScroll);
        window.addEventListener('resize', this.resize);
       	window.addEventListener('DOMContentLoaded', this.resize(true));
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
		let resizeTimer;

		let currentWidth = window.innerWidth;

		if (loading == true || ( currentWidth !== headerOnScroll.savedWidth)) {	
			headerOnScroll.savedWidth = currentWidth;
			headerOnScroll.setHeight();

			document.body.classList.add("resize-animation-stopper");
				clearTimeout(resizeTimer);
					resizeTimer = setTimeout(() => {
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