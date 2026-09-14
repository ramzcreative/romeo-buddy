import { defineConfig, loadEnv } from 'vite'

import { compression } from 'vite-plugin-compression2'
import manifestSRI from 'vite-plugin-manifest-sri'
import postcss from './postcss.config.js'
import * as fs from 'fs'
import * as path from 'path'

const themesDir = path.resolve(__dirname, 'themes')

// Base's JS once, plus each site theme's two CSS wrappers, its theme.js if it has one, and its own main.js
// only under "js": "replace". Page themes have no bundle.
function buildInputs() {
    const inputs = { main: path.join(themesDir, '_base/src/js/main.js') }
    const add = (name, file) => {
        if (inputs[name]) throw new Error(`Build entry "${name}" is claimed by both ${inputs[name]} and ${file}`)
        inputs[name] = file
    }

    for (const handle of fs.readdirSync(themesDir)) {
        const manifestPath = path.join(themesDir, handle, 'theme.json')
        if (!fs.existsSync(manifestPath)) continue

        let manifest
        try {
            manifest = JSON.parse(fs.readFileSync(manifestPath, 'utf8'))
        } catch (error) {
            throw new Error(`${manifestPath}: ${error.message}`)
        }

        if ((manifest.type ?? 'site') !== 'site') continue

        add(`${handle}-critical`, path.join(themesDir, handle, 'src/js/critical.js'))
        add(`${handle}-maincss`, path.join(themesDir, handle, 'src/js/maincss.js'))

        const themeJs = path.join(themesDir, handle, 'src/js/theme.js')
        if (fs.existsSync(themeJs)) add(`theme-${handle}`, themeJs)

        // A main.js without the flag is a leftover per-theme wrapper and is ignored.
        if (manifest.js !== undefined) {
            if (manifest.js !== 'replace') throw new Error(`${manifestPath}: "js" must be "replace" or left out`)
            const mainJs = path.join(themesDir, handle, 'src/js/main.js')
            if (!fs.existsSync(mainJs)) throw new Error(`${manifestPath}: "js": "replace" needs ${mainJs}`)
            add(`${handle}-main`, mainJs)
        }
    }

    return inputs
}

// `@src/…` resolves to the importing file's own theme, so bundles written for per-theme builds still resolve.
const themeSrc = {
    name: 'stables-theme-src',
    resolveId(id, importer) {
        if (!id.startsWith('@src/') || !importer) return null
        const match = importer.replace(/\\/g, '/').match(/\/themes\/([^/]+)\/src\//)
        return match ? path.join(themesDir, match[1], 'src', id.slice('@src/'.length)) : null
    },
}

// Full reload when any theme's Twig changes, new themes included. Listens on Vite's own watcher: a
// `themes/*/templates` glob (vite-plugin-restart) made chokidar drop every other change under themes/.
const reloadOnTemplates = {
    name: 'stables-reload-on-templates',
    apply: 'serve',
    configureServer(server) {
        let timer
        const reload = (file) => {
            if (!/\/themes\/[^/]+\/templates\//.test(file.replace(/\\/g, '/'))) return
            clearTimeout(timer)
            timer = setTimeout(() => server.ws.send({ type: 'full-reload' }), 100)
        }
        for (const event of ['add', 'change', 'unlink']) server.watcher.on(event, reload)
    },
}

// https://vitejs.dev/config/
export default defineConfig(({ command }) => {
    const env = loadEnv(command, process.cwd(), '');

    const HTTP_PORT = `${env.DEV_PORT_HTTP ?? '8082'}`

    return {
        base: command === 'serve' ? '' : '/dist/site/',
        build: {
            manifest: true,
            emptyOutDir: true,
            outDir: './web/dist/site/',
            rollupOptions: {
                input: buildInputs(),
            },
        },
        css: {
            postcss,
        },
        plugins: [
            themeSrc,
            compression({
                include: [/\.(js|mjs|json|css|map)$/i],
            }),
            manifestSRI(),
            reloadOnTemplates,
        ],
        resolve: {
            symlinks: false,
            alias: {
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
