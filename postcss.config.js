import postcssImport from 'postcss-import';
import postcsssimplevars from 'postcss-simple-vars';
import postcsspxtorem from 'postcss-pxtorem';
import postcssnested from 'postcss-nested';
import autoprefixer from 'autoprefixer';
import postcsshovermediafeature from 'postcss-hover-media-feature';
import postcsscustomselectors from 'postcss-custom-selectors';
import postcsscustommedia from 'postcss-custom-media';
import postcsseach from 'postcss-each';
import postcssadvancedvariables from 'postcss-advanced-variables';

// Factory so callers (vite.config.js) can pass the active theme handle in as
// a `$theme` postcss variable — used by url() refs that need to resolve to
// that theme's own web/assets/<handle>/ folder (see media.pcss).
// postcss-advanced-variables resolves $vars (including inside url()) before
// postcss-simple-vars ever sees them, so it needs the value, not simple-vars.
export default (theme = 'default') => ({
    plugins: [
        postcssImport,
		postcsseach,
		postcssadvancedvariables({
			variables: { theme },
		}),
		postcsssimplevars({
			silent: true,
		}),
        postcssnested,
        postcsshovermediafeature,
        postcsscustomselectors,
        postcsscustommedia,
        postcsspxtorem({
            propList: ['font', 'font-size', 'letter-spacing', '*--font-size*'],
        }),
        autoprefixer,
    ],
})

/*
postcss plugins we are using

https://github.com/postcss/postcss-import
https://github.com/postcss/postcss-nested
https://github.com/csstools/postcss-custom-selectors
https://github.com/csstools/postcss-custom-media
https://github.com/saulhardman/postcss-hover-media-feature
https://github.com/postcss/postcss-color-function


old setup
module.exports = {
    plugins: [
        require('postcss-pxtorem')({ propList: ['font', 'font-size', 'letter-spacing'] }),
        require('postcss-nesting'),
        require('autoprefixer')
        //plugin: https://github.com/cuth/postcss-pxtorem
    ]
}
*/