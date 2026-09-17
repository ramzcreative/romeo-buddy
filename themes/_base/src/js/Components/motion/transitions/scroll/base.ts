import { animate, scroll, stagger } from 'motion';

import { Motion } from '../../motion.ts';
import type { Options } from '../../options.ts';
import type { TransitionComponent } from '../../base.ts';

type ScrollOffset = NonNullable<Options['offset']>;

/**
 * Rewrites `start`/`end` and 0–1 numbers as percentages — the same positions,
 * but not a pair Motion recognises as a preset. A preset pair (e.g. the default
 * `['start end', 'end end']`) is handed to the browser's native view timeline,
 * which drives x/y on the `cover` range, and every property on the wrong range
 * once the target is taller than the viewport. Motion's own JS tracking has
 * neither problem.
 */
function jsTrackedOffset( offset: Options['offset'] ): Options['offset'] {
    if (!offset) return offset;

    const edge = ( e: string | number ) =>
        typeof e === 'number' ? `${ e * 100 }%`
            : e === 'start' ? '0%'
            : e === 'end' ? '100%'
            : e;

    return offset.map(( item ) =>
        typeof item === 'string' && item.trim().includes(' ')
            ? item.trim().split(/\s+/).map(edge).join(' ')
            : Array.isArray(item)
                ? item.map(edge)
                : item
    ) as ScrollOffset;
}

// baseClass.ts
export class BaseScroll implements TransitionComponent {

    // store merged options for the instance
    private readonly _motion: Motion;
    readonly root: HTMLElement;

    // unsubscribe function returned by motion's scroll(), so refresh()/destroy()
    // can tear down the previous binding instead of stacking a new one on top
    // of it every time (this leaked on every breakpoint crossing before).
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

        // Respect the user's OS-level motion preference: leave the element
        // in its natural resting CSS state rather than binding a
        // scroll-linked animation.
        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            return;
        }

        // get this element options
        const options = this._motion.options;

        //get targeted parent element if provided
        const id = options.targetId;
        const driver = id ? (document.getElementById(id) ?? this.root) : this.root;
        const passenger = this.root;

        this.beforeAnimate( driver, options );

        const animation = animate(
            passenger,
            this.keyframes( options ),
            {
                delay: stagger(options.staggerDelay, {
                    startDelay: options.delay,
                }),
                duration: options.speed,
                ease: options.ease,
            }
        )

        this._cleanup = scroll(animation, {
            target: driver,
            offset: jsTrackedOffset( options.offset ),
        });
    }

    /**
     * Runs before the scroll animation is bound. A no-op here — a transition
     * that has to set something up first (Parallax pre-scaling its image)
     * overrides it, so this class does not have to know which one it is.
     */
    protected beforeAnimate( _driver: HTMLElement, _options: Options ): void {}

    /**
     * The keyframes this transition animates. Override to animate something
     * other than opacity + a single translate axis.
     */
    protected keyframes( options: Options ): Record<string, any> {
        return {
            opacity: options.opacity,
            [ options.direction === 'y' ? 'y' : 'x' ]: options.translateOffset,
            clipPath: options.clipPath,
        };
    }

    refresh(){
        this.setup();
    }
    destroy(){
        this._cleanup?.();
        this._cleanup = null;
    }
}