import { Options } from './options.ts';

export const DEFAULTS: Options = {
  transition        : 'scroll',
  amount            : 0,
  delay             : 0,
  staggerDelay      : 0.25,
  speed             : 1,
  translateOffset   : [50, 0],
  offset            : ["start end", "end end"],
  opacity           : [0, 1],
  ease              : [0.25, 1, 0.5, 1],
  direction         : 'y'
};


export const PROJECT_CODE = 'motion';

export const DATA_ATTRIBUTE = `data-${ PROJECT_CODE }`;