import { animate, scroll, stagger } from 'motion';

import { Animations } from '../../animations.ts';

// baseClass.ts
export class BaseScroll {

    // store merged options for the instance
    private readonly _Animations: Animations;
    readonly root: HTMLElement;

    // unsubscribe function returned by motion's scroll(), so refresh()/destroy()
    // can tear down the previous binding instead of stacking a new one on top
    // of it every time (this leaked on every breakpoint crossing before).
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
        const passenger = this.root;
        
        //parallax settings
        if(options.transition == 'parallax'){
            //get element from options selecter ('scaleTarget')
            const scaleTarget = options.scaleTarget;
            const scaleEl = driver.querySelector(`${scaleTarget}`)
            
            //set scalling based off options
            const scaleOffset = options.scaleOffset ?? 15;
            const scale = 1 + scaleOffset / 100
            
            //scale the element
            if (scaleEl) {
                animate(
                    scaleEl,
                    { transform: [`scale(${scale})`, `scale(${scale})`] },
                    { duration: 0, delay: stagger(0, { startDelay: 0 }) }
                )
            }   
        }

        //setting options for x or y direction
        let animationArr : Record<string, any> = {
            opacity: options.opacity,
            x: options.translateOffset,
            clipPath: options.clipPath,
            //transform: [transformOffset, "translate(0)"],
        }
        if(options.direction == 'y' && options.transition != 'parallax'){
            animationArr = {
                opacity: options.opacity,
                y: options.translateOffset,
                clipPath: options.clipPath,
                //transform: [transformOffset, "translate(0)"],
            }
        }
        else if(options.transition == 'parallax'){
            animationArr = {
                opacity: options.opacity,
                transform: [
                    `translateY(-${options.scaleOffset}%)`,
                    `translateY(${options.scaleOffset}%)`,
                ],
                clipPath: options.clipPath,
            }
        }

        //
        const animation = animate(
            passenger,
            animationArr,
            {
                delay: stagger(options.staggerDelay, {
                    startDelay: options.delay,
                }),
                duration: options.speed,
                easing: options.easing,
            }
        )

        this._cleanup = scroll(animation, {
            target: driver,
            offset: options.offset,
        });
    }
    refresh(){
        this.setup();
    }
    destroy(){
        this._cleanup?.();
        this._cleanup = null;
    }
}