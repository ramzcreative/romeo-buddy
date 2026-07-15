import { Animations } from './animations.ts';
import { Options } from './options.ts';
import { Scroll, InOut, Clip, Custom, InView, Text } from './transitions';
import { mergeObjects } from './utils.ts';

// Shared across every Animations instance on the page: the same breakpoint
// value (e.g. 1024) shows up repeatedly across templates, so each instance
// requesting its own fresh window.matchMedia() call for the same query was
// duplicating browser-side query parsing/tracking for no reason. One
// MediaQueryList per unique breakpoint value is reused by every listener.
const _mediaQueryCache = new Map<string, MediaQueryList>();

function getMediaQueryList(px: string): MediaQueryList {
    let mql = _mediaQueryCache.get(px);
    if (!mql) {
        mql = window.matchMedia(`(min-width: ${px}px)`);
        _mediaQueryCache.set(px, mql);
    }
    return mql;
}

export function Breakpoints(Animations: Animations, Transition: any) {

    const _Animations: Animations = Animations;
    const _ogOptions = _Animations.options;
    const _breakpoints = _Animations.options.breakpoints;
    const _sortedMediaQueryList = new Map();

    let _options = _Animations.options;

    /**
    * Initializes the component.
    */
    function init() {
        if(!_breakpoints)
            return

        /**
         * Sort _breakpoints into a map
         * - (map): javascript doesn't keep objects in order, reason for updating into a new map
         */

        // 1. Get the keys as an array
        const keys = Object.keys(_breakpoints);

        // 2. Sort the keys numerically in descending order
        keys.sort((a, b) => parseInt(b) - parseInt(a));

        // 3. Construct a new object with sorted keys
        for (const key of keys) {

            //get (or create) the shared media query for this breakpoint
            const mediaQueryList = getMediaQueryList(key);

            //add media query to sorted list
            if(mediaQueryList)
                _sortedMediaQueryList.set(key, mediaQueryList);
        }

        //creat events
        createBreakpointsEvent();
    }
    
    /**
     * Create Media query for each breakpoint and add listener
     */
    function createBreakpointsEvent(){
        
        // add media query events
        for (const [key, mq] of _sortedMediaQueryList) {
            if (mq){
                mq.addEventListener('change', handleBreakpointChange);
            }
        }

        // first event
        handleBreakpointChange();
    }
    
    /**
     * Handle breakpoint and update responsive options
     * 
     */
    function handleBreakpointChange(){
        if(!_breakpoints)
            return;

        let flagHasBreakpointOptions = false;

        //loop through sorted media query list and update responsive options based off breakpoints options
        for (const [key, mq] of _sortedMediaQueryList) {

            //option defined breakpoint matches media breakpoint
            if (mq.matches){

                //get options for this breakpoint
                const breakPointOptions = _breakpoints[key];

                //return if there is no value
                if(!breakPointOptions) return;

                //merge this options and breakpoint specific options
                const mergedOptions = mergeObjects(_options, _ogOptions, breakPointOptions || {});

                //set this and element options
                setOptions(mergedOptions);

                //set flag to true, we have located and found options for this breakpoint
                flagHasBreakpointOptions = true;
                
                //console.log("key:", key, "value:", mq.media);

                //refresh this transition
                Transition.refresh();

                //break out of loop, we have the options for this breakpoint
                break;
            }
            else
                flagHasBreakpointOptions = false;
        }

        //no options found or no breakpoints for this screen size
        if(!flagHasBreakpointOptions){
            //set options back to orginal settings
            setOptions(_ogOptions);

            //refresh this transition
            Transition.refresh();
        }
        
    }

    //set options for element and this
    function setOptions(optionsParam: Options){
        _Animations.options = optionsParam;
        _options = _Animations.options;
    }

    function getOptions(){
        return _options;
        //return _Animations.options;
    }

    /**
     * Remove this instance's listeners from the (shared) MediaQueryLists.
     * Safe to call even if init() bailed out early (no breakpoints).
     */
    function destroy(){
        for (const [, mq] of _sortedMediaQueryList) {
            mq.removeEventListener('change', handleBreakpointChange);
        }
    }

    return{
        init,
        destroy
    }
}