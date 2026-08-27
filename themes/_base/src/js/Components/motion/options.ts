/** WARNING **/

import type { Easing, scroll } from "motion";

// Motion doesn't re-export the scroll offset type, so take it from scroll's
// own signature — that way it tracks the version in use rather than a copy
// of its literals going stale here.
type ScrollOffset = NonNullable<NonNullable<Parameters<typeof scroll>[1]>['offset']>;

//ALL Interface values must match the objects below
export interface Options extends ResponsiveOptions {
    /**
    * Target ID of the container that generates the event (inView, scroll)
    * - defaults to this element
    */
    targetId?: string | null;
    /**
    * classes or data attributes of the children used to target the animation
    * - defaults to this element
    */
    childTarget?: string | null;
    /**
    * classes or data attributes of the element needed to be scaled.
    * - used with parallax transition
    */
    scaleTarget?: string | null;
    /**
    * type of transition: 'scroll | inout | clip | inview | etc...'
    */
    transition?: string;
    /**
     * clip path
     */
    clipPath?: Array<number | string> | null;
    /**
     * translate offset [-50, 50]
     */
    translateOffset?: Array<number | string>;
    /**
    * scale offset
    */
    scaleOffset?: number | null;
    /**
    * scroll offset
    */
    offset?: ScrollOffset;
    /**
    * scroll opacity
    */
    opacity?: Array<number>;
    /**
    * invView amount offset
    */
    amount?: number;
    /**
    * animation delay
    */
    delay?: number;
    /**
    * when there are targeted children the staggerDelay will delay each childs animation
    */
    staggerDelay?: number;
    /**
    * how fast the animation should take
    */
    speed?: number;
    
    /**
    * a named easing or a four-number cubic bezier — NOT a CSS
    * `cubic-bezier(...)` string, which Motion silently ignores
    */
    ease?: Easing;
    /**
    * Animation translate direction
    */
    direction?: 'y' | 'x';
    /**
     * Options for specific breakpoints.
     *
     * @example
     *
     * breakpoints{
     *   700: {
     *     opacity: [0,1]
     *   },
     *   1200: {
     *     opacity: [1,0]
     *     translateOffset: [150, 0],
     *   }
     * }
     */
    breakpoints?: Record<string | number, ResponsiveOptions>,
    /**
    * typewriter transition: ms between each typed/deleted character (base
    * value, jittered per typewriterVariance)
    */
    typewriterInterval?: number;
    /**
    * typewriter transition: ms to hold a fully-typed phrase before deleting it
    */
    typewriterPause?: number;
    /**
    * typewriter transition: 'natural' for human-like jitter, or a number
    * (ms) for uniform timing
    */
    typewriterVariance?: string;
    /**
    * typewriter transition: how much faster deleting is than typing
    * (0.5 = twice as fast)
    */
    typewriterBackspaceFactor?: number;
}

/**
 * Interface for breakpoint options.
 */
export interface ResponsiveOptions {
    /**
    * scroll opacity
    */
    opacity?: Array<number>;    
    /**
    * animation delay
    */
    delay?: number;
    /**
    * when there are targeted children the staggerDelay will delay each childs animation
    */
    staggerDelay?: number;
    /**
    * how fast the animation should take
    */
    speed?: number;
    /**
    * Animation translate direction
    */
    direction?: 'y' | 'x';
    /**
     * clip path
     */
    clipPath?: Array<number | string> | null;
    /**
     * translate offset [-50, 50]
     */
    translateOffset?: Array<number | string>;
    /**
    * scale offset
    */
    scaleOffset?: number | null;
}

/** WARNING **/
//manual labor, harding coding values to match above. These values must match the interfaces above
//plugins: ts-transformer-keys
export const OptionsSimple = {
    targetId: 'selector',
    childTarget: 'selector',
    scaleTarget: 'selector',
    transition: 'string',
    clipPath: 'array',
    translateOffset: 'array',
    scaleOffset: 'number',
    offset: 'array',
    opacity: 'array',
    amount: 'number',
    delay: 'number',
    staggerDelay: 'number',
    speed: 'number',
    ease: 'easing',
    direction: 'string',
    breakpoints: 'object', //**
    typewriterInterval: 'number',
    typewriterPause: 'number',
    typewriterVariance: 'string',
    typewriterBackspaceFactor: 'number'
}
export const OptionsResponsiveSimple = {
    opacity: 'array',
    delay: 'number',
    staggerDelay: 'number',
    speed: 'number',
    direction: 'string',
    clipPath: 'array',
    translateOffset: 'array',
    scaleOffset: 'number'
}