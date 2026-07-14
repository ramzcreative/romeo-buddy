import { animate, inView, stagger } from 'motion';

import { Animations } from '../../animations.ts';

// baseClass.ts
export class BaseInView {

    // store merged options for the instance
    private readonly _Animations: Animations;
    readonly root: HTMLElement;

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
        inView(
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
}