import { defineConfig, loadEnv } from 'vite'

import { compression } from 'vite-plugin-compression2'
import manifestSRI from 'vite-plugin-manifest-sri'
import postcss from './postcss.config.js'
import * as fs from 'fs'
import * as path from 'path'
import { chainOf, INHERIT_IMPORT, readManifests } from './scripts/lib/theme-chain.mjs'

const themesDir = path.resolve(__dirname, 'themes')

// theme.json's `parent` is the source of truth; the entry's import line is generated from it. Letting the two
// disagree produces "some styles are missing", which is the least debuggable failure here — so it fails the build.
function assertEntriesMatchParent(handle, manifests) {
    const want = chainOf(handle, manifests)[1] ?? '_base'

    for (const entry of ['critical.pcss', 'main.pcss']) {
        const file = path.join(themesDir, handle, 'src/css', entry)
        if (!fs.existsSync(file)) throw new Error(`themes/${handle}/src/css/${entry} is missing.`)

        const found = fs.readFileSync(file, 'utf8').match(INHERIT_IMPORT)?.[1]
        if (!found) throw new Error(`themes/${handle}/src/css/${entry} imports nothing to build on; it should import ${want}'s ${entry}.`)
        if (found !== want) {
            throw new Error(
                `themes/${handle}/src/css/${entry} imports ${found}'s ${entry}, but theme.json says its parent is ${want}.\n` +
                `  Fix: php craft theme-picker/themes/relink ${handle}`
            )
        }
    }
}

// `_base` carries two pairs of entries: `main`/`critical` (its full look) and `main-core`/`critical-core` (the
// machine a library theme takes instead — docs/base-layer-spec.md). They are parallel lists rather than one
// importing the other, because folding them would reorder `layer(components)` and order decides ties inside a
// layer. Parallel lists drift, so this refuses a core file that imports something its full counterpart doesn't.
function assertCoreIsSubsetOfFull() {
    for (const entry of ['main', 'critical']) {
        const corePath = path.join(themesDir, '_base/src/css', `${entry}-core.pcss`)
        if (!fs.existsSync(corePath)) continue

        const imports = (css) => [...css.matchAll(/@import\s+["']([^"']+)["']/g)].map((m) => m[1])
        const full = new Set(imports(fs.readFileSync(path.join(themesDir, '_base/src/css', `${entry}.pcss`), 'utf8')))
        const extra = imports(fs.readFileSync(corePath, 'utf8')).filter((i) => !full.has(i))

        if (extra.length) {
            throw new Error(
                `themes/_base/src/css/${entry}-core.pcss imports ${extra.join(', ')}, which ${entry}.pcss doesn't.\n` +
                `  The two lists have drifted — see the note at the top of critical-core.pcss.`
            )
        }
    }
}

// Base's JS once, plus each site theme's two CSS wrappers, its theme.js if it has one, and its own main.js
// only under "js": "replace". Page themes have no bundle.
function buildInputs() {
    const inputs = { main: path.join(themesDir, '_base/src/js/main.js') }
    const add = (name, file) => {
        if (inputs[name]) throw new Error(`Build entry "${name}" is claimed by both ${inputs[name]} and ${file}`)
        inputs[name] = file
    }

    assertCoreIsSubsetOfFull()

    // Every manifest first: a chain can name a theme that comes later in readdir order.
    const manifests = readManifests(themesDir)

    for (const [handle, manifest] of Object.entries(manifests)) {
        if ((manifest.type ?? 'site') !== 'site') continue

        const manifestPath = path.join(themesDir, handle, 'theme.json')

        assertEntriesMatchParent(handle, manifests)

        // Every theme needs its own two wrappers even when it inherits everything else: the CP reads the build
        // manifest for both, and a theme missing either counts as unbuilt and can't be activated.
        for (const wrapper of ['critical.js', 'maincss.js']) {
            const file = path.join(themesDir, handle, 'src/js', wrapper)
            if (!fs.existsSync(file)) throw new Error(`themes/${handle}/src/js/${wrapper} is missing — a theme without it can't be activated.`)
        }

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
