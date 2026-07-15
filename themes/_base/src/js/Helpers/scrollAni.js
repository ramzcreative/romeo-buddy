import { animate, scroll, inView, stagger, transform } from 'motion'
import Lenis from 'lenis'
import { Animations } from '../Components/animations'


const animationsEls = document.querySelectorAll('[data-animations]');
if (animationsEls.length) {

    animationsEls.forEach((element) => {
        // Isolate failures per-element — a typo'd data-animations blob or an
        // invalid selector on one block shouldn't abort setup for every
        // other animated element after it in the DOM.
        try {
            new Animations(element);
        } catch (error) {
            console.error('Animations: failed to initialize element', element, error);
        }
    })
}

const lenis = new Lenis({
    lerp: 0.1,
    //duration: 0.95,
    orientation: 'vertical',
    gestureOrientation: 'vertical',
    smoothWheel: true,
    smoothTouch: false,
    wheelMultiplier: 1,
    touchMultiplier: 2,
    normalizeWheel: true,
    anchors: {
        offset: 100,
        lerp: 0.1,
        duration: 0.75,
        onComplete: () => {
            console.log('scrolled to anchor')
        },
    },
    prevent: (node) => {
        return (
            node.classList.contains('modal')
            //|| node.classList.contains('.ignore-scroll')
        )
    },
})

function raf(time) {
    lenis.raf(time);
    requestAnimationFrame(raf);
}

requestAnimationFrame(raf);

let resizeTimeout;

function refreshLenis() {
    clearTimeout(resizeTimeout) // Cancel previous resize calls
    resizeTimeout = setTimeout(() => {
        requestAnimationFrame(() => {
            lenis.resize();
        })
    }, 300) // Adjust delay as needed
}

window.addEventListener('load', refreshLenis);

const iframeZ = document.querySelectorAll('iframe');

iframeZ.forEach((iframe) => {
    iframe.addEventListener('load', refreshLenis);
})

/*
document.addEventListener('DOMContentLoaded', function() {
  // Your code here will execute when the DOM is ready.
  console.log('DOM fully loaded and parsed');
});
*/

// text
const textEls = document.querySelectorAll('[data-text]')
if (textEls.length) {
    async function animateText() {
        // motion-plus (splitText) is only needed on pages that actually use
        // [data-text] — loading it unconditionally alongside every
        // [data-animations] page was paying for a feature most pages don't use.
        const { splitText } = await import('motion-plus')

        textEls.forEach((textEl) => {
            const text = textEl.querySelector('.split-text')

            // options
            const delay = textEl.getAttribute('data-delay') ?? '0.05'
            const startDelay = textEl.getAttribute('data-start-delay') ?? '.05'
            const duration = textEl.getAttribute('data-duration') ?? '2'
            const mobileOffset = textEl.getAttribute('data-mobile-offset') ?? '10'
            const desktopOffset = textEl.getAttribute('data-desktop-offset') ?? '10'
            const offset =  parseInt(desktopOffset)
            const splitType = textEl.getAttribute('data-type') ?? 'lines'

            const { lines } = splitText(text)

            // Initially set the characters to be invisible and slightly offset
            lines.forEach((line) => {
                line.style.opacity = 0
                line.style.transform = `translateY(${offset})`
            })

            /**
             * Animate the words in the h1. By changing words to chars or lines
             * we can animate different parts of the text.
             */
            inView(
                text,
                (element, info) => {
                    animate(
                        splitType == 'lines'
                            ? splitText(text).lines
                            : splitType == 'chars'
                            ? splitText(text).chars
                            : splitText(text).words,
                        {
                            opacity: [0, 1],
                            y: [offset, 0],
                        },
                        {
                            duration: duration,
                            delay: stagger(parseFloat(delay), {
                                startDelay: parseFloat(startDelay),
                            }),
                            onUpdate: (latest) => {
                                if (latest == 0) {
                                    // TBC...
                                    // console.log("complete");
                                }
                            },
                        }
                    )
                },
                { amount: 0.15 }
            )
        })
    }

    document.fonts.ready.then(animateText)
}