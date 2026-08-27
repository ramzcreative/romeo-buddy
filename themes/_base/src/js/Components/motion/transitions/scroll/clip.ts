// extendedClass.ts
import { BaseScroll } from './base.ts';
import { Motion } from '../../motion.ts';
import { DEFAULTS } from '../../defaults.ts';
import { Options } from '../../options.ts';
import { mergeObjects } from '../../utils.ts';

/**
 * @param motion - Motion.
 * @param options    - Options.
 *
 */
export class Clip extends BaseScroll {
    //set default options for this transition
    //-note: this is not required but helps to maintain consistency with liked settings/options
    static defaults: Options = {
        speed: 2,
        opacity: [1,1],
        translateOffset: [0, 0],
        offset: ['5% end', '-15% start'],
        clipPath: ['inset(0px round 0px)', 'inset(5% round var(--radius-md))'],
        ease: 'linear'
    };

    constructor( motion: Motion, options: Options){
        //merge option defaults with transition options and user defined options
        const mergedOptions = mergeObjects(DEFAULTS, Clip.defaults || {}, options || {});

        //update element options
        motion.options = mergedOptions;

        //call Base constructor
        super( motion )
    }
    refresh(){
        super.refresh();
    }
}