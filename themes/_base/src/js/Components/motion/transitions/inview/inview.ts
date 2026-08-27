// extendedClass.ts
import { BaseInView } from './base.ts';
import { Motion } from '../../motion.ts';
import { DEFAULTS } from '../../defaults.ts';
import { Options } from '../../options.ts';
import { mergeObjects } from '../../utils.ts';

/**
 * @param motion - Motion.
 * @param options    - Options.
 *
 */
export class InView extends BaseInView {
    //set default options for this transition
    //-note: this is not required but helps to maintain consistency with liked settings/options
    // Deliberately mirrors the [data-reveal] tokens in base/themes.pcss
    // (--transition-reveal 1s, --reveal-shift 6.25rem, --reveal-stagger 100ms)
    // so a block on this path and one on the CSS path look the same.
    static defaults: Options = {
        speed: 1,
        opacity: [0,1],
        translateOffset: [100,0],
        staggerDelay: 0.1,
        amount: 0.25,
        direction: 'y'
    };

    constructor( motion: Motion, options: Options){
        //merge option defaults with transition options and user defined options
        const mergedOptions = mergeObjects(DEFAULTS, InView.defaults || {}, options || {});
        
        //update element options
        motion.options = mergedOptions;

        //call Base constructor
        super( motion )
    }
    refresh(){
        super.refresh();
    }
}