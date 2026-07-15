/** WARNING **/

import { scale } from "motion";

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
    childTarget?: String | null;
    /**
    * classes or data attributes of the element needed to be scaled.
    * - used with parallax transition
    */
    scaleTarget?: String | null;
    /**
    * type of transition: 'scroll | inout | clip | inview | etc...'
    */
    transition?: String;
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
    offset?: Array<string>;
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
    
    easing?: string;
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
    easing: 'string',
    direction: 'string',
    breakpoints: 'object' //**
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