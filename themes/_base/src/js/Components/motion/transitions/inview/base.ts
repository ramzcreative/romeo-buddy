import { animate, inView } from 'motion';

/** Most stagger steps applied before the delay stops growing. */
const STAGGER_MAX = 7;

import { Motion } from '../../motion.ts';
import type { TransitionComponent } from '../../base.ts';

// baseClass.ts
export class BaseInView implements TransitionComponent {

    // store merged options for the instance
    private readonly _motion: Motion;
    readonly root: HTMLElement;

    // unsubscribe function returned by motion's inView(), so refresh()/destroy()
    // can tear down the previous observer instead of stacking a new one on
    // top of it every time (this leaked on every breakpoint crossing before).
    private _cleanup: VoidFunction | null = null;

    constructor( motion: Motion ) {
        this._motion = motion;
        this.root = motion.root;
        
        this.init();
    }
    init(){
        //setup up responsive
        //const generateBreakpoints = Breakpoints(this._motion);
        //generateBreakpoints.init();

        this.setup();
    }
    setup(){
        // tear down the previous binding before creating a new one (setup()
        // is re-run on every breakpoint change via refresh())
        this._cleanup?.();
        this._cleanup = null;

        // Respect the user's OS-level motion preference: bail out before
        // ever setting an initial (possibly hidden) opacity, so elements
        // stay in their natural resting CSS state instead of getting stuck
        // pre-animation.
        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            return;
        }

        // get this element options
        const options = this._motion.options;

        //get targeted parent element if provided
        const id = options.targetId;
        const driver = id ? (document.getElementById(id) ?? this.root) : this.root;

        //children
        const childrenTarget = options.childTarget;
        const showChildren = driver.querySelectorAll(`${childrenTarget}`);
        //('[data-list-x] > *')

        //children items or this element — normalize to Element[]
        const passenger = showChildren.length ? Array.from(showChildren) : [this.root];

        //add not visible class
        driver.classList.add('invisible');

        //setting initial values for page load
        // - need to fix this: issue - this will blink if we don't set initial motion/settings
        const initialOpacity = options.opacity?.[0] ?? 1;
        //const initialOffset = options.translateOffset?.[0] ?? 0;

        //initial settings
        animate(passenger, { 
            opacity: initialOpacity, 
            x: 0, //initialOffset
            y: 0  //initialOffset
        });

        //setting options for x or y direction
        let animationArr : Record<string, any> = {
            opacity: options.opacity,
            x: options.translateOffset,
            clipPath: options.clipPath,
            //transform: [transformOffset, "translate(0)"],
        }
        if(options.direction == 'y'){
            animationArr = {
                opacity: options.opacity,
                y: options.translateOffset,
                clipPath: options.clipPath,
                //transform: [transformOffset, "translate(0)"],
            }
        }

        //
        this._cleanup = inView(
            driver,
            (element, info) => {
                animate(
                    passenger,
                    animationArr,
                    {
                        // A delay function rather than stagger(), which has no
                        // way to clamp: past STAGGER_MAX steps a long list is
                        // still arriving well after attention has moved on.
                        // Same cap the [data-reveal] CSS applies.
                        delay: ( i: number ) => ( options.delay ?? 0 )
                            + Math.min( i, STAGGER_MAX ) * ( options.staggerDelay ?? 0 ),
                        duration: options.speed,
                        ease: options.ease,
                    }
                );

                //add and remove visible classes
                driver.classList.add('visible');
                driver.classList.remove('invisible');
            },
            { amount: options.amount }
        )
    }
    refresh(){
        this.setup();
    }
    destroy(){
        this._cleanup?.();
        this._cleanup = null;
    }
}