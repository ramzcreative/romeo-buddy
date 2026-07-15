import { animate, inView, stagger } from 'motion';

import { Animations } from '../../animations.ts';

// baseClass.ts
export class BaseInView {

    // store merged options for the instance
    private readonly _Animations: Animations;
    readonly root: HTMLElement;

    // unsubscribe function returned by motion's inView(), so refresh()/destroy()
    // can tear down the previous observer instead of stacking a new one on
    // top of it every time (this leaked on every breakpoint crossing before).
    private _cleanup: VoidFunction | null = null;

    constructor( Animations: Animations ) {
        this._Animations = Animations;
        this.root = Animations.root;
        
        this.init();
    }
    init(){
        //setup up responsive
        //const generateBreakpoints = Breakpoints(this._Animations);
        //generateBreakpoints.init();

        this.setup();
    }
    setup(){
        // tear down the previous binding before creating a new one (setup()
        // is re-run on every breakpoint change via refresh())
        this._cleanup?.();
        this._cleanup = null;

        // get this element options
        const options = this._Animations.options;

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
        // - need to fix this: issue - this will blink if we don't set initial animations/settings
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
                        delay: stagger(options.staggerDelay, {
                            startDelay: options.delay,
                        }),
                        duration: options.speed,
                        easing: options.easing,
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