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

// One config for every theme: nothing theme-specific is compiled in (the logo mask comes from Twig).
export default {
    plugins: [
        postcssImport,
		postcsseach,
		postcssadvancedvariables(),
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
}

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