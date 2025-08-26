<template>
    <VAceEditor v-model:value="modelValue" @init="editorInit" :lang="lang" :theme="theme" :options="editorOptions"
        :style="editorStyle" />
</template>

<script setup>
import { ref, watch, computed } from 'vue'
import { VAceEditor } from 'vue3-ace-editor'
import ace from 'ace-builds'

// If you prefer serving ACE assets yourself, you can also set a basePath:
// ace.config.set('basePath', '/ace'); // make sure assets are available at this path

// --- Modes ---
import modeXmlUrl from 'ace-builds/src-noconflict/mode-xml?url'
ace.config.setModuleUrl('ace/mode/xml', modeXmlUrl)

import modeYamlUrl from 'ace-builds/src-noconflict/mode-yaml?url'
ace.config.setModuleUrl('ace/mode/yaml', modeYamlUrl)

import modeLuaUrl from 'ace-builds/src-noconflict/mode-lua?url'
ace.config.setModuleUrl('ace/mode/lua', modeLuaUrl)

import modePhpUrl from 'ace-builds/src-noconflict/mode-php?url'
ace.config.setModuleUrl('ace/mode/php', modePhpUrl)

import modePhpBladeUrl from 'ace-builds/src-noconflict/mode-php_laravel_blade?url'
ace.config.setModuleUrl('ace/mode/php_laravel_blade', modePhpBladeUrl)


// --- Themes ---
import themeChromeUrl from 'ace-builds/src-noconflict/theme-chrome?url'
ace.config.setModuleUrl('ace/theme/chrome', themeChromeUrl)

import themeGithubUrl from 'ace-builds/src-noconflict/theme-one_dark?url'
ace.config.setModuleUrl('ace/theme/one_dark', themeGithubUrl)

// --- Workers ---
import workerBaseUrl from 'ace-builds/src-noconflict/worker-base?url'
ace.config.setModuleUrl('ace/mode/base', workerBaseUrl)

import workerXmlUrl from 'ace-builds/src-noconflict/worker-xml?url'
ace.config.setModuleUrl('ace/mode/xml_worker', workerXmlUrl)

import workerYamlUrl from 'ace-builds/src-noconflict/worker-yaml?url'
ace.config.setModuleUrl('ace/mode/yaml_worker', workerYamlUrl)

import workerLuaUrl from 'ace-builds/src-noconflict/worker-lua?url'
ace.config.setModuleUrl('ace/mode/lua_worker', workerLuaUrl)

import workerPhpUrl from 'ace-builds/src-noconflict/worker-php?url'
ace.config.setModuleUrl('ace/mode/php_worker', workerPhpUrl)

// --- Snippets ---
import snippetsHtmlUrl from 'ace-builds/src-noconflict/snippets/html?url'
ace.config.setModuleUrl('ace/snippets/html', snippetsHtmlUrl)

import snippetsXmlUrl from 'ace-builds/src-noconflict/snippets/xml?url'
ace.config.setModuleUrl('ace/snippets/xml', snippetsXmlUrl)

import snippetsYamlUrl from 'ace-builds/src-noconflict/snippets/yaml?url'
ace.config.setModuleUrl('ace/snippets/yaml', snippetsYamlUrl)

import snippetsPhpUrl from 'ace-builds/src-noconflict/snippets/php?url'
ace.config.setModuleUrl('ace/snippets/php', snippetsPhpUrl)

// Searchbox
import extSearchboxUrl from 'ace-builds/src-noconflict/ext-searchbox?url';
ace.config.setModuleUrl('ace/ext/searchbox', extSearchboxUrl);
// Language tools (autocomplete/snippets)
import 'ace-builds/src-noconflict/ext-language_tools'
ace.require('ace/ext/language_tools')

// Props
const props = defineProps({
    modelValue: { type: String, default: '' },
    lang: { type: String, default: 'javascript' },
    theme: { type: String, default: 'chrome' },
    options: { type: Object, default: () => ({}) },
    height: { type: [Number, String], default: 400 },
})

import themeUrl from 'ace-builds/src-noconflict/theme-chrome?url'
ace.config.setModuleUrl('ace/theme/chrome', themeUrl)

// Emit
const emit = defineEmits(['update:modelValue'])

// Local state proxy for v-model
const modelValue = ref(props.modelValue)

const editorStyle = computed(() => ({
    height: typeof props.height === 'number' ? `${props.height}px` : props.height,
    width: '100%',
}))


// Sync prop → local
watch(() => props.modelValue, val => {
    if (val !== modelValue.value) modelValue.value = val
})

// Sync local → prop
watch(modelValue, val => emit('update:modelValue', val))

// Merge default options with user options
const editorOptions = {
    useWorker: true,
    enableBasicAutocompletion: true,
    enableSnippets: true,
    enableLiveAutocompletion: true,
    fontSize: 14,
    tabSize: 2,
    showPrintMargin: false,
    highlightActiveLine: true,
    ...props.options,
}

function editorInit(editor) {
    // optional setup
}
</script>