import fs from 'node:fs';
import path from 'node:path';

export const contract = JSON.parse(fs.readFileSync(new URL('./frontend-contract.json', import.meta.url)));
export const runtimeName = (name) => `@fspbx/runtime/${name === '@i18n' ? 'i18n' : name}`;

// Development uses source modules and Vite HMR. Released core assets discover
// separately installed module bundles; they never contain optional module code.
export function moduleAssets({ root, serve, moduleName }) {
    const resolver = 'virtual:fspbx-module-pages';
    const entry = 'virtual:fspbx-module-entry';
    return {
        name: 'fspbx-module-assets',
        enforce: 'pre',
        resolveId(id, importer) {
            if (id === resolver || id === entry) return `\0${id}`;
            if (moduleName && Object.hasOwn(contract.shared, id)) {
                return { id: runtimeName(id), external: true };
            }
            // Relative imports of the shared translation wrapper need the same
            // singleton as @i18n, even when imported by a shared core component.
            const resolved = id.startsWith('.') && importer ? path.resolve(path.dirname(importer), id) : id;
            if (moduleName && resolved === path.join(root, 'resources/js/i18n.mjs')) {
                return { id: runtimeName('@i18n'), external: true };
            }
        },
        load(id) {
            if (id === `\0${resolver}`) {
                if (serve) return `
                    const pages = import.meta.glob('/Modules/**/Resources/assets/js/Pages/**/*.vue');
                    export async function resolveModulePage(module, page) {
                        const load = pages['/Modules/' + module + '/Resources/assets/js/Pages/' + page + '.vue'];
                        if (!load) throw new Error('Module page not found: ' + module + '/' + page);
                        return load();
                    }`;
                return `
                    const loaded = new Map();
                    export async function resolveModulePage(module, page) {
                        const entry = window.fspbxFrontend?.modules?.[module];
                        if (!entry) throw new Error('Frontend assets are unavailable or incompatible for module: ' + module);
                        if (!loaded.has(module)) loaded.set(module, import(/* @vite-ignore */ entry));
                        return (await loaded.get(module)).resolvePage(page);
                    }`;
            }
            if (id === `\0${entry}`) {
                if (!/^[A-Za-z][A-Za-z0-9]*$/.test(moduleName ?? '')) throw new Error('Invalid module name');
                const style = `Modules/${moduleName}/Resources/assets/sass/app.scss`;
                return `
                    ${fs.existsSync(path.join(root, style)) ? `import ${JSON.stringify('/' + style)};` : ''}
                    import '/resources/scss/tailwind.css';
                    const pages = import.meta.glob('/Modules/${moduleName}/Resources/assets/js/Pages/**/*.vue');
                    export async function resolvePage(page) {
                        const load = pages['/Modules/${moduleName}/Resources/assets/js/Pages/' + page + '.vue'];
                        if (!load) throw new Error('Module page not found: ${moduleName}/' + page);
                        return load();
                    }`;
            }
        },
    };
}
