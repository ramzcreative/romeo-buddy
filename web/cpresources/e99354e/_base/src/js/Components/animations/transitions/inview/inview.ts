// extendedClass.ts
import { BaseInView } from './base.ts';
import { Animations } from '../../animations.ts';
import { DEFAULTS } from '../../defaults.ts';
import { Options } from '../../options.ts';
import { mergeObjects } from '../../utils.ts';

/**
 * @param animations - Animations.
 * @param options    - Options.
 *
 */
export class InView extends BaseInView {
    //set default options for this transition
    //-note: this is not required but helps to maintain consistency with liked settings/options
    static defaults: Options = {
        speed: 1,
        opacity: [0,1],
        translateOffset: [30,0],
        staggerDelay: 0.25,
        amount: 0.25,
        direction: 'y'
    };

    constructor( Animations: Animations, options: Options){
        //merge option defaults with transition options and user defined options
        const mergedOptions = mergeObjects(DEFAULTS, InView.defaults || {}, options || {});
        
        //update element options
        Animations.options = mergedOptions;

        //call Base constructor
        super( Animations)
    }
    refresh(){
        super.refresh();
    }
}