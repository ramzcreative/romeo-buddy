// extendedClass.ts
import { BaseInView } from './base.ts';
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
export class Custom extends BaseInView {

    //set default options for this transition
    //-note: this is not required but helps with maintain consistency with liked settings/options
    static defaults: Options = {
        speed: 0,
        opacity: [1,1],
        translateOffset: [0,0],
        staggerDelay: 0,
        amount: 0.45
    };

    constructor( Animations: Animations, options: Options){
        //merge option defaults with transition options and user defined options
        const mergedOptions = mergeObjects(DEFAULTS, Custom.defaults || {}, options || {});

        //update element options
        Animations.options = mergedOptions;

        //call Base constructor
        super( Animations)
    }

    refresh(){
        super.refresh();
    }
}