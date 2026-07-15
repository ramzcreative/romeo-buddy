import { inView } from 'motion';
import { Animations } from '../../animations.ts';
import { DEFAULTS } from '../../defaults.ts';
import { Options } from '../../options.ts';
import { mergeObjects } from '../../utils.ts';

/**
 * Typewriter transition — types and deletes through a list of phrases, read
 * from this element's childTarget matches (their text content, in DOM
 * order), once scrolled into view. Loops indefinitely.
 *
 * Doesn't extend BaseInView: that class tweens opacity/transform/clipPath
 * via a single animate() call, which doesn't fit an indefinite text-content
 * loop. This implements the same TransitionComponent contract (refresh(),
 * destroy()) directly instead, using Motion+'s getNextText/getTypewriterDelay.
 *
 * @param animations - Animations.
 * @param options    - Options.
 */
export class Typewriter {
    private readonly _Animations: Animations;
    readonly root: HTMLElement;

    // unsubscribe function returned by motion's inView()
    private _cleanup: VoidFunction | null = null;

    // identity token for the current tick loop — refresh()/destroy() null
    // this out, and every tick() checks it still matches before continuing,
    // so a breakpoint change can't leave two loops racing on the same
    // liveEl (setup() re-runs on every breakpoint change via refresh()).
    private _loopToken: object | null = null;

    static defaults: Options = {
        childTarget: '[data-children]',
        amount: 0.5,
        typewriterInterval: 70,
        typewriterPause: 1500,
        typewriterVariance: 'natural',
        typewriterBackspaceFactor: 0.5,
    };

    constructor( Animations: Animations, options: Options ) {
        const mergedOptions = mergeObjects(DEFAULTS, Typewriter.defaults || {}, options || {});
        Animations.options = mergedOptions;

        this._Animations = Animations;
        this.root = Animations.root;

        this.init();
    }

    init(){
        this.setup();
    }

    setup(){
        // tear down any previous binding/loop before creating a new one
        this._cleanup?.();
        this._cleanup = null;
        this._loopToken = null;

        const options = this._Animations.options;

        const id = options.targetId;
        const driver = id ? (document.getElementById(id) ?? this.root) : this.root;

        const childrenTarget = options.childTarget;
        const wordEls = Array.from(driver.querySelectorAll(`${childrenTarget}`)) as HTMLElement[];
        const words = wordEls.map((el) => el.textContent?.trim() ?? '').filter(Boolean);

        // nothing (or only one phrase) to cycle through — leave whatever's
        // already there alone
        if (words.length < 2) return;

        const liveEl = wordEls[0];

        // hide every phrase but the first, which becomes the live "typing"
        // node — matches BaseInView's existing pattern of setting initial
        // state via JS rather than relying on a separate CSS convention
        wordEls.slice(1).forEach((el) => { el.style.display = 'none'; });

        // respect the user's OS-level motion preference — leave the first
        // phrase showing statically, no animation
        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

        const interval = options.typewriterInterval ?? 70;
        const pause = options.typewriterPause ?? 1500;
        const varianceRaw = options.typewriterVariance ?? 'natural';
        const variance: 'natural' | number = varianceRaw === 'natural' ? 'natural' : parseFloat(`${varianceRaw}`);
        const backspaceFactor = options.typewriterBackspaceFactor ?? 0.5;

        this._cleanup = inView(
            driver,
            () => {
                this._runLoop(liveEl, words, interval, pause, variance, backspaceFactor);
            },
            { amount: options.amount }
        );
    }

    private _runLoop(
        liveEl: HTMLElement,
        words: string[],
        interval: number,
        pause: number,
        variance: 'natural' | number,
        backspaceFactor: number
    ){
        const token = {};
        this._loopToken = token;

        // motion-plus is only fetched by pages that actually use the
        // typewriter transition
        import('motion-plus').then(({ getNextText, getTypewriterDelay }) => {
            if (this._loopToken !== token) return; // superseded before this even loaded

            let wordIndex = 0;
            let currentText = words[0];
            let target = words[0];
            let phase: 'typing' | 'pausing' | 'deleting' = 'pausing';

            const tick = () => {
                if (this._loopToken !== token) return;

                if (phase === 'pausing') {
                    phase = 'deleting';
                    target = '';
                }

                currentText = getNextText(currentText, target, 'type', 'character');
                liveEl.textContent = currentText;

                if (currentText === target) {
                    if (phase === 'deleting') {
                        wordIndex = (wordIndex + 1) % words.length;
                        target = words[wordIndex];
                        phase = 'typing';
                    } else if (phase === 'typing') {
                        phase = 'pausing';
                        setTimeout(tick, pause);
                        return;
                    }
                }

                const delay = getTypewriterDelay(target, currentText, interval, variance, backspaceFactor);
                setTimeout(tick, delay);
            };

            // hold the initial (already-visible) first word on screen for a
            // full pause before starting to delete it — avoids a flash
            setTimeout(tick, pause);
        });
    }

    refresh(){
        this.setup();
    }

    destroy(){
        this._loopToken = null;
        this._cleanup?.();
        this._cleanup = null;
    }
}
