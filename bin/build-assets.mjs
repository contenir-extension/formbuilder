import { mkdir, readdir, rm, writeFile } from 'node:fs/promises'
import { dirname, join, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'
import { build } from 'esbuild'
import { compile } from 'sass'

/**
 * Build the add-on's dashboard assets into public/, which is committed:
 *
 *   - public/js/<Component>.js, one ES module per component in
 *     assets/js/components, each default-exporting its component class for
 *     the host's registry to mount, with the code they share (BaseComponent,
 *     the HTTP client, SortableJS) split into public/js/chunks/.
 *   - public/css/formbuilder.css, compiled from assets/css/formbuilder.scss.
 *
 * The output must depend on the sources alone, so CI can rebuild and diff
 * it: public/js is emptied first so a chunk whose hash changed leaves no
 * stale file behind, paths are taken relative to the package root, and
 * neither step writes a source map.
 */
const root = resolve(dirname(fileURLToPath(import.meta.url)), '..')
const componentsDir = join(root, 'assets/js/components')
const jsOut = join(root, 'public/js')
const cssOut = join(root, 'public/css/formbuilder.css')

const entryPoints = (await readdir(componentsDir))
    .filter((file) => file.endsWith('.js'))
    .sort()
    .map((file) => join(componentsDir, file))

await rm(jsOut, { recursive: true, force: true })

await build({
    absWorkingDir: root,
    entryPoints,
    outdir: jsOut,
    entryNames: '[name]',
    chunkNames: 'chunks/[name]-[hash]',
    format: 'esm',
    bundle: true,
    splitting: true,
    minify: true,
    target: 'es2020',
    sourcemap: false,
    legalComments: 'eof',
    logLevel: 'warning',
})

const { css } = compile(join(root, 'assets/css/formbuilder.scss'), {
    style: 'compressed',
    sourceMap: false,
    loadPaths: [join(root, 'node_modules')],
})
await mkdir(dirname(cssOut), { recursive: true })
await writeFile(cssOut, `${css}\n`)
