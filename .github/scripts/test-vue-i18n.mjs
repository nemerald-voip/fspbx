import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import process from 'node:process';
import { fileURLToPath } from 'node:url';
import { compileTemplate, parse } from '@vue/compiler-sfc';
import {
    normalizeTranslationReplacements,
    trans,
    transChoice,
} from '../../resources/js/i18n.mjs';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..');
const sourceRoots = [
    path.join(root, 'resources/js'),
    path.join(root, 'Modules'),
];

const walk = (directory) => {
    if (!fs.existsSync(directory)) {
        return [];
    }

    return fs.readdirSync(directory, { withFileTypes: true }).flatMap((entry) => {
        const entryPath = path.join(directory, entry.name);

        return entry.isDirectory() ? walk(entryPath) : [entryPath];
    });
};

const sourceFiles = sourceRoots.flatMap(walk).filter((file) => /\.(?:js|mjs|vue)$/.test(file));
const vueFiles = sourceFiles.filter((file) => file.endsWith('.vue'));
const failures = [];

assert.deepEqual(normalizeTranslationReplacements(), {});
assert.deepEqual(
    normalizeTranslationReplacements({ missing: undefined, empty: null, zero: 0, disabled: false }),
    { missing: '', empty: '', zero: 0, disabled: false }
);
assert.equal(trans('Value: :value', { value: undefined }), 'Value: ');
assert.equal(trans('Value: :value', { value: null }), 'Value: ');
assert.equal(trans('Value: :value', { value: 0 }), 'Value: 0');
assert.equal(trans('Value: :value', { value: false }), 'Value: false');
assert.equal(transChoice(':count item|:count items', undefined), '0 items');

for (const file of sourceFiles) {
    if (file.endsWith('resources/js/i18n.mjs')) {
        continue;
    }

    const source = fs.readFileSync(file, 'utf8');

    if (/from\s+['"]laravel-vue-i18n['"]/.test(source)) {
        failures.push(`${path.relative(root, file)} imports laravel-vue-i18n directly; import from @i18n instead.`);
    }
}

for (const file of vueFiles) {
    const source = fs.readFileSync(file, 'utf8');
    const filename = path.relative(root, file);
    const parsed = parse(source, { filename });

    for (const error of parsed.errors) {
        failures.push(`${filename}: ${String(error)}`);
    }

    if (!parsed.descriptor.template) {
        continue;
    }

    const compiled = compileTemplate({
        source: parsed.descriptor.template.content,
        filename,
        id: filename,
        compilerOptions: {
            isCustomElement: (tag) => tag === 'deep-chat',
        },
    });

    for (const error of compiled.errors) {
        failures.push(`${filename}: ${String(error)}`);
    }
}

if (failures.length > 0) {
    console.error('Vue i18n validation failed:');
    failures.forEach((failure) => console.error(`  - ${failure}`));
    process.exit(1);
}

console.log(`Vue i18n validation passed for ${vueFiles.length} components.`);
