// extendedClass.ts
import { BaseScroll } from './base.ts';
import { Animations } from '../../animations.ts';
import { DEFAULTS } from '../../defaults.ts';
import { Options } from '../../options.ts';
import { mergeObjects } from '../../utils.ts';

/**
 * @param animations - Animations.
 * @param options    - Options.
 *
 */
export class InOut extends BaseScroll {
    //set default options for this transition
    //-note: this is not required but helps to maintain consistency with liked settings/options
    static defaults: Options = {
        speed: 2,
        opacity: [0, 1, 1, 0],
        translateOffset: [-100, 0, -100, 0],
        offset: ["start end", "end end", "start start", "end start"]
    };

    constructor( Animations: Animations, options: Options){
        //merge option defaults with transition options and user defined options
        const mergedOptions = mergeObjects(DEFAULTS, InOut.defaults || {}, options || {});
        
        //update element options
        Animations.options = mergedOptions;
        
        //call Base constructor
        super( Animations)
    }
    refresh(){
        super.refresh();
    }
}