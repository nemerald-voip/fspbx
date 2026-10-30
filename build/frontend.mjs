import fs from 'node:fs';
import path from 'node:path';
import os from 'node:os';
import crypto from 'node:crypto';
import { spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import { contract, runtimeName } from './module-assets.mjs';

const root = path.resolve(fileURLToPath(new URL('..', import.meta.url)));
const args = process.argv.slice(2);
for (let index = 0; index < args.length; index++) {
    if (args[index] === '--release') continue;
    if (args[index] === '--module' || args[index] === '--outDir') { index++; continue; }
    throw new Error(`Unknown build option: ${args[index]}`);
}
const option = (name) => {
    const index = args.indexOf(name);
    if (index < 0) return null;
    if (!args[index + 1] || args[index + 1].startsWith('--')) throw new Error(`${name} requires a value`);
    return args[index + 1];
};
const moduleName = option('--module');
const destination = option('--outDir');
const release = args.includes('--release');
if ((release || moduleName) && !destination) throw new Error('Release builds require --outDir; live assets will not be replaced.');
if (moduleName && !/^[A-Za-z][A-Za-z0-9]*$/.test(moduleName)) throw new Error('Invalid module name');
const hash = (value) => crypto.createHash('sha256').update(value).digest('hex');
const runtime = hash(fs.readFileSync(path.join(root, 'build/frontend-contract.json')) + '\n' + fs.readFileSync(path.join(root, 'package-lock.json')));
const version = fs.readFileSync(path.join(root, 'config/version.php'), 'utf8').match(/'release'\s*=>\s*'([^']+)'/)?.[1];
if (!version) throw new Error('Application version is missing');

function run(command, arguments_, env = {}) {
    const result = spawnSync(command, arguments_, { cwd: root, stdio: 'inherit', env: { ...process.env, ...env } });
    if (result.error) throw result.error;
    if (result.status !== 0) throw new Error(`${command} failed (${result.signal ?? result.status}); active assets were preserved.`);
}

function files(directory, prefix = '') {
    return fs.readdirSync(directory, { withFileTypes: true }).sort((a, b) => a.name.localeCompare(b.name, 'en')).flatMap((entry) => {
        if (entry.isSymbolicLink()) throw new Error(`Symbolic links are not allowed in asset packages: ${entry.name}`);
        const relative = prefix + entry.name;
        return entry.isDirectory() ? files(path.join(directory, entry.name), relative + '/') : [relative];
    });
}

function sourceFiles(name) {
    const base = name ? path.join(root, 'Modules', name) : root;
    const paths = name ? ['module.json', 'Resources/assets'] : [
        'package.json', 'package-lock.json', 'vite.config.js', 'tailwind.config.js', 'postcss.config.js',
        'config/version.php', 'build', 'resources/js', 'resources/scss', 'resources/lang',
    ];
    return Object.fromEntries(paths.flatMap((item) => {
        const file = path.join(base, item);
        if (!fs.existsSync(file)) return [];
        return fs.statSync(file).isDirectory() ? files(file, item + '/') : [item];
    }).filter((item) => !/^resources\/lang\/php_.*\.json$/.test(item)).sort().map((item) => [item, hash(fs.readFileSync(path.join(base, item)))]));
}

function build(name, out) {
    const sources = sourceFiles(name);
    run(process.execPath, ['node_modules/vite/bin/vite.js', 'build', '--outDir', out], {
        FSPBX_BUILD_MODULE: name ?? '', FSPBX_FRONTEND_BUILD: '1',
    });
    const manifest = JSON.parse(fs.readFileSync(path.join(out, 'manifest.json')));
    const fileHashes = Object.fromEntries(files(out).map((file) => [file, hash(fs.readFileSync(path.join(out, file)))]));
    const metadata = {
        schema: 1, api: contract.api, kind: name ? 'module' : 'core', name: name ?? 'fspbx',
        version: name ? JSON.parse(fs.readFileSync(path.join(root, 'Modules', name, 'module.json'))).version : version,
        core_version: version, runtime, origin: release ? 'release' : 'local', sources, files: fileHashes,
        css: Object.keys(fileHashes).filter((file) => file.endsWith('.css')),
    };
    if (name) {
        const entry = Object.values(manifest).find((value) => value.isEntry && value.file.endsWith('.js'));
        if (!entry) throw new Error('Module entry was not generated');
        metadata.entry = entry.file;
    } else {
        metadata.imports = Object.fromEntries(Object.entries(contract.shared).map(([name, source]) => {
            if (!manifest[source]?.file) throw new Error(`Missing shared runtime entry: ${source}`);
            return [runtimeName(name), manifest[source].file];
        }));
    }
    if (JSON.stringify(sources) !== JSON.stringify(sourceFiles(name))) throw new Error('Source changed during the build; retry before publishing.');
    fs.writeFileSync(path.join(out, 'frontend.json'), JSON.stringify(metadata, null, 2) + '\n');
    return out;
}

const stage = fs.mkdtempSync(path.join(os.tmpdir(), 'fspbx-frontend-'));
try {
    const core = build(moduleName, path.join(stage, moduleName ?? 'core'));
    if (destination) {
        const target = path.resolve(destination);
        if (fs.existsSync(target)) throw new Error(`Output already exists: ${target}`);
        fs.cpSync(core, target, { recursive: true });
        console.log(`Asset package ready: ${target}`);
    } else {
        const packages = [core];
        const statuses = fs.existsSync(path.join(root, 'modules_statuses.json'))
            ? JSON.parse(fs.readFileSync(path.join(root, 'modules_statuses.json'))) : {};
        for (const [name, enabled] of Object.entries(statuses)) {
            if (enabled !== true || !/^[A-Za-z][A-Za-z0-9]*$/.test(name)) continue;
            if (fs.existsSync(path.join(root, 'Modules', name, 'Resources/assets/js/Pages'))) {
                packages.push(build(name, path.join(stage, name)));
            }
        }
        // Publish all packages together, only after every build has succeeded.
        run('php', ['build/publish-assets.php', ...packages]);
    }
} finally {
    fs.rmSync(stage, { recursive: true, force: true });
}
