import { defineConfig, loadEnv } from 'vite'

import { compression } from 'vite-plugin-compression2'
import legacy from '@vitejs/plugin-legacy'
import manifestSRI from 'vite-plugin-manifest-sri'
import restart from 'vite-plugin-restart'
import postcss from './postcss.config.js'
import * as path from 'path'

// https://vitejs.dev/config/
export default defineConfig(({ command }) => {
    const env = loadEnv(command, process.cwd(), ''); 

    const HTTP_PORT = `${env.DEV_PORT_HTTP ?? '8082'}`
    const HTTPS_PORT = `${env.DEV_PORT_HTTPS ?? '8083'}`

    const THEME = `${env.CUSTOM_THEME ?? 'default'}`

    return {
        base: command === 'serve' ? '' : '/dist/' + THEME + '/',
        build: {
            manifest: true,
            emptyOutDir: true,
            outDir: './web/dist/' + THEME + '/',
            rollupOptions: {
                input: {
                    main: './' + `${env.CUSTOM_SRC_PATH ?? 'src'}` + '/js/main.js',
                    critical: './' + `${env.CUSTOM_SRC_PATH ?? 'src'}` + '/js/critical.js',
                    maincss: './' +`${env.CUSTOM_SRC_PATH ?? 'src'}` + '/js/maincss.js'
                },
                output: {
                    //sourcemap: true,
                },
            },
        },
        css: {
            postcss: postcss(THEME),
        },
        plugins: [
            // critical({
            // 	criticalUrl: 'http://nginx:80',
            // 	criticalBase: './web/dist/criticalcss/',

            // 	criticalConfig: {}
            // }),
            compression({
                include: [/\.(js|mjs|json|css|map)$/i],
            }),
            legacy({
                targets: ['defaults', 'not IE 11'],
            }),
            manifestSRI(),
            restart({
                reload: ['./' + `${env.CUSTOM_TEMPLATES_PATH ?? 'templates'}` + '/**/*'],
                //reload: ['./templates/**/*'],
            }),
        ],
        resolve: {
            symlinks: false,
            alias: {
                '@src': path.resolve(__dirname, `${env.CUSTOM_SRC_PATH ?? './src'}`),
                '@build': path.resolve(__dirname, '.'),
            },
            preserveSymlinks: true,
        },
        server: {
            // Allow cross-origin requests -- https://github.com/vitejs/vite/security/advisories/GHSA-vg6x-rcgg-rjx6
            allowedHosts: true,
            cors: {
                origin: /(\.local|\.test|localhost)/
            },
            fs: {
                strict: false
            },
            headers: {
                "Access-Control-Allow-Private-Network": "true",
            },
            host: '0.0.0.0',
            port: HTTP_PORT,
            strictPort: true,
            watch: {
                usePolling: true,
                ignored: ['**/storage/**', '**/vendor/**', '**/web/cpresources/**']
            },
        },
    }
})