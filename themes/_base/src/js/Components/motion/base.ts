/**
 * The shape every transition satisfies.
 *
 * Nothing enforced these until `npm run typecheck` existed — the file sat
 * unimported. They are wired into Motion and both transition base
 * classes now, so a transition that forgets refresh()/destroy() (and leaks
 * its scroll/inView binding on the next breakpoint change) is a type error
 * rather than a silent leak.
 */
export interface BaseComponent {
  setup?(): void;
  mount?(): void;
  destroy?(): void;
}

export interface TransitionComponent extends BaseComponent {
  refresh(): void;
  destroy(): void;
}
