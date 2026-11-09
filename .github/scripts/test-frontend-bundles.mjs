// Link the actual compiled core/module graph without booting the PBX. This
// catches missing chunks, missing exports, and accidental private dependencies.
// node --experimental-vm-modules .github/scripts/test-frontend-bundles.mjs CORE MODULE...
import fs from 'node:fs';
import path from 'node:path';
import vm from 'node:vm';
import assert from 'node:assert/strict';
import { fileURLToPath, pathToFileURL } from 'node:url';

const [coreDirectory, ...moduleDirectories] = process.argv.slice(2).map((value) => path.resolve(value));
if (!coreDirectory) throw new Error('Supply a core package directory and optional module packages');
const read = (directory) => JSON.parse(fs.readFileSync(path.join(directory, 'frontend.json')));
const core = read(coreDirectory);
assert.equal(core.kind, 'core');
const imports = new Map(Object.entries(core.imports).map(([name, file]) => [name, pathToFileURL(path.join(coreDirectory, file)).href]));
const modules = new Map();
const context = vm.createContext({});
function load(identifier) {
    if (!modules.has(identifier)) {
        const filename = fileURLToPath(identifier);
        assert.ok([coreDirectory, ...moduleDirectories].some((directory) => filename.startsWith(directory + path.sep)), 'Import escaped the asset packages');
        modules.set(identifier, new vm.SourceTextModule(fs.readFileSync(filename, 'utf8'), { context, identifier }));
    }
    return modules.get(identifier);
}
const entrypoints = [];
for (const directory of [coreDirectory, ...moduleDirectories]) {
    const metadata = read(directory);
    assert.equal(metadata.runtime, core.runtime, 'Module runtime differs from core');
    assert.equal(metadata.api, core.api, 'Module API differs from core');
    for (const file of Object.keys(metadata.files)) {
        if (file.endsWith('.js')) entrypoints.push(pathToFileURL(path.join(directory, file)).href);
    }
}
const probe = new vm.SourceTextModule(entrypoints.map((file) => `import ${JSON.stringify(file)};`).join('\n'), { context });
await probe.link((specifier, parent) => {
    const identifier = imports.get(specifier)
        ?? (specifier.startsWith('file:') ? specifier : specifier.startsWith('.') ? new URL(specifier, parent.identifier).href : null);
    assert.ok(identifier, `Unexpected external dependency: ${specifier}`);
    return load(identifier);
});
console.log(`Linked ${modules.size} compiled JavaScript chunks across core and ${moduleDirectories.length} module packages.`);
