import 'vite/modulepreload-polyfill';
import 'lazysizes';
//lazyload images
//https://github.com/aFarkas/lazysizes

//add class to the image parent element once image has loaded
// - gives option to add anything fun or hide a placeholder!
document.addEventListener('lazyloaded', function (e) {
    if (e.target)
        e.target.parentNode.classList.add("lazyloaded");
});


// ================================================================
document.addEventListener("DOMContentLoaded", async () => {
  try {
    //await import("./scrollAni.js");
    //await import("./sliders.js");

    //import modules in parallel
    await Promise.all([
        import('./Helpers/scrollAni.js'),
        import('./Helpers/sliders.js'),
        import('./Helpers/modal.js'),
    ]);
    
  } catch (error) {
    console.error("Error loading module:", error);
  }
});

// ================================================================
//load components
import './Components/modal.ts';
import './Components/accordion.ts';

import headerOnScroll from './headerOnScroll.js';
new headerOnScroll;

// ================================================================


// Accept HMR as per: https://vitejs.dev/guide/api-hmr.html
if (import.meta.hot) {
    import.meta.hot.accept(() => {
        console.log('HMR');
    })
}
/* installs
    npm create vite@latest (vite)
    npm i vite-plugin-banner (vite plugin)
    npm i vite-plugin-compression (vite plugin)
    npm i @vitejs/plugin-legacy (vite plugin)
    npm i -D vite-plugin-manifest-sri (vite plugin)
    npm i vite-plugin-restart (vite plugin)
    npm i @vitejs/plugin-vue (vite plugin)
    npm i @originjs/vite-plugin-commonjs (vite plugin)
    npm i postcss (postcss)
    npm install postcss postcss-pxtorem --save-dev (postcss plugin, converts px to rem for fonts)
    npm install lazysizes --save (lazysizes)
*/