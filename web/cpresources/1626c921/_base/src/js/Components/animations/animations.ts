import { DEFAULTS, DATA_ATTRIBUTE } from './defaults.ts';
import { Options, OptionsResponsiveSimple, OptionsSimple } from './options.ts';
import { isString, sanitizeString, isFloat, query, getAttribute, mergeObjects, assert} from './utils.ts';
import { Scroll, InOut, Clip, Custom, InView, Text, Parallax } from './transitions';
import { Breakpoints } from './breakpoints.ts';

export class Animations {
  /**
   * The root element where the animation is applied.
   */
  readonly root: HTMLElement;

  /**
   * The current options.
   */
  private _opt: Options = {};

  /**
   * The animations constructor.
   *
   * @param target  - The selector for the target element, or the element itself.
   * @param options - Optional. An object with options.
   */
  constructor( target: string | HTMLElement, options?: Options ) {
    const root = isString( target ) ? query<HTMLElement>( document, target ) : target;
    assert( root, `${ root } is invalid.` );

    this.root = root as HTMLElement;

    options = mergeObjects({}, options || {} );

    this._opt = options;

    try {
     this._opt = mergeObjects( options, JSON.parse( getAttribute( root as Element, DATA_ATTRIBUTE ) || '{}' ) );
    } catch ( e ) {
      assert( false, 'Invalid JSON' );
    }

    this.setup();
  }

  //let prefersReducedMotion = window.matchMedia('(prefers-reduced-motion)');
  //let doesPreferReducedMotion = prefersReducedMotion.matches;
  setup(){
    //const regularOptions = this.optionsCheck(this._opt, 'OptionsSimple');
    //const breakpointOptions = this.optionsCheck(this._opt.breakpoints, 'OptionsResponsiveSimple');
    //this._opt = Object.assign(regularOptions, breakpointOptions);
    this._opt = this.optionsCheck(this._opt, 'OptionsSimple');
    //console.log(JSON.stringify(this._opt))


    /**
     * 
     *  Get selected transition function
     * 
    */
    //merging options and default arrays to get selected transition
    const tempOpt = mergeObjects(DEFAULTS, this._opt );
    
    //map of available transitions
    const TransitionMap = {
      'scroll': Scroll,
      'inout': InOut,
      'clip': Clip,
      'custom': Custom,
      'inview': InView,
      'text': Text,
      'parallax': Parallax
    } as const;

    //key values 
    type TransitionKey = keyof typeof TransitionMap; // 'scroll' | '...'

    //geting matching/available selected transition
    const transitionRaw = tempOpt.transition || 'scroll';
    const transitionString: TransitionKey = (typeof transitionRaw === 'string' && Object.prototype.hasOwnProperty.call(TransitionMap, transitionRaw))
      ? transitionRaw as TransitionKey
      : 'scroll';
    //turning option selected transition into exported tranistion function
    const transitionAction = TransitionMap[transitionString];

    //fire away
    if (transitionAction) {
        const Transition = new transitionAction(this, this._opt);

        //setup responsive breakpoint options for this transition
        const generateBreakpoints = Breakpoints(this, Transition);
        generateBreakpoints.init();
    }
  }


  /**
   *  Manual labor
   * 
   * Checking that user option values match type and are part of the available "Options".
   * - cleans user values
   * - WARNING: interface properties must match option objects (hard coded)
   *  **/
  optionsCheck(obj: Options | Record<string | number, any> | undefined, optionsToCheck: 'OptionsSimple' | 'OptionsResponsiveSimple' ) {
    
    if (!obj) {
      // nothing to check
      return {};
    }

    //obj: object passed into function that we are checking against
    const objOptions = obj as Record<string, any>;
    
    //temp object of options, used to rebuild options
    const tempOptions: Record<string, any> = {};

    //type optionsKeys = keyof Options; // "targetId" | "childTarget" | "transition"

    //get the proper object to check against (options | responsive options)
    const optionsChecking = (optionsToCheck == 'OptionsResponsiveSimple' ? OptionsResponsiveSimple : OptionsSimple) as Record<string, string>;

    //
    for (const key in objOptions) {
      if (Object.prototype.hasOwnProperty.call(objOptions, key)) {
          //get proper value
          const value = objOptions[key];

          //object options: check if this is an object ('breakpoints')
          if (typeof value === 'object' && value !== null && !Array.isArray(value) ) {
        
            //getting object child value (recursive call)
            const children = this.optionsCheck(value, 'OptionsResponsiveSimple');

            //
            if (Object.keys(children).length > 0) {
              
              //building object of objects
              const currOption = {
                  [`${key}`]: children
              }
              
              //add new option into our returned temp object
              Object.assign(tempOptions, currOption);
            }
          }

          //standard options
          else{
            //make sure option key is within our list of available options
            // - don't include any options that we don't need/want
            if(key in optionsChecking){
              const optionType = optionsChecking[key];
              
              let cleanedValue;

              //check if value matches available types (string | number | array)
              if(optionType == 'string'){
                if(typeof value === "string"){
                  cleanedValue = sanitizeString(`${value}`);
                }
                else {
                  console.warn(`Animations Warning: Option of ${key} skipped, ${key} value (${value}) is not of type string.`);
                  continue;
                }
              }
              else if(optionType == 'array'){
                //doing something for arrays
                if(!Array.isArray(value)) {
                  console.warn(`Animations Warning: Option of ${key} skipped, ${key} value (${value}) is not of type array.`);
                  continue;
                }
                cleanedValue = value.map((e) => {
                    if(isFloat(e) || Number.isInteger(e))
                      return e;
                    else
                      return sanitizeString(`${e}`);
                    
                });
              }
              else{
                //do something for numbers and etc.
                if(isFloat(value) || Number.isInteger(value)){
                  cleanedValue = value;
                }
                else{
                  console.warn(`Animations Warning: Option of ${key} skipped, ${key} value (${value}) is not of type number.`);
                  continue;
                }
              }

              const currOption = {
                [`${key}`]: cleanedValue
              }

              //add new option into our returned temp object
              Object.assign(tempOptions, currOption);
            }
            else{
              if(optionsToCheck == 'OptionsResponsiveSimple')
                console.warn(`Animations Warning: Invalid option '${key}', not supported in breakpoint options.`);
              else
                console.warn(`Animations Warning: Invalid option '${key}'.`);
            }
          }
      }
    }

    //returned cleanded user defined options
    return tempOptions;
  }

  set options(options: Options){
    this._opt = options;
  }

  get options(){
    return this._opt;
  }
}
