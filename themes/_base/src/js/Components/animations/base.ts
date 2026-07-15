import { Animations } from './animations.ts';
import { Options } from './options.ts';


/**
 * Component Type
 */
export type ComponentConstructor = ( Animations: Animations, options: Options, Transition: TransitionComponent ) => BaseComponent;

/**
 * Component interface
 */
export interface BaseComponent {
  setup?(): void;
  mount?(): void;
  destroy?(): void;
}

/**
 * Transition interface
 */
export interface TransitionComponent extends BaseComponent {
  refresh(): void;
  destroy(): void;
}
