// extendedClass.ts
import { BaseScroll } from './base.ts';
import { Animations } from '../../animations.ts';
import { DEFAULTS } from '../../defaults.ts';
import { Options } from '../../options.ts';
import { mergeObjects } from '../../utils.ts';

/*
* This class could potentially run all of the scroll events. 
*  - However, we have different scroll events that set different options so we don't have to re-enter those options everytime.
* /

/**
 * @param animations - Animations.
 * @param options    - Options.
 *
 */
export class Scroll extends BaseScroll {
    //set default options for this transition
    //-note: this is not required but helps to maintain consistency with liked settings/options
    static defaults: Options = {
        speed: 2,
        opacity: [1,1],
        translateOffset: [200, 0]
    };

    constructor( Animations: Animations, options: Options){
        //merge option defaults with transition options and user defined options
        const mergedOptions = mergeObjects(DEFAULTS, Scroll.defaults || {}, options || {});

        //update element options
        Animations.options = mergedOptions;

        //call Base constructor
        super( Animations)
    }
    refresh(){
        super.refresh();
    }
}