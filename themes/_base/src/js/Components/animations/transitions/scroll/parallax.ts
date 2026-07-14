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
export class Parallax extends BaseScroll {
    //set default options for this transition
    //-note: this is not required but helps to maintain consistency with liked settings/options
    static defaults: Options = {
        scaleTarget: '[data-scale-image]',
        speed: 2,
        opacity: [1,1],
        translateOffset: [0, 0],
        scaleOffset: 15,
        offset: ['start end', 'end start']
    };

    constructor( Animations: Animations, options: Options){
        //merge option defaults with transition options and user defined options
        const mergedOptions = mergeObjects(DEFAULTS, Parallax.defaults || {}, options || {});

        //update element options
        Animations.options = mergedOptions;

        //call Base constructor
        super( Animations)
    }
    refresh(){
        super.refresh();
    }
}