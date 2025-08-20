<template>
    <div ref="root" class="h-full w-full"></div>
  </template>
  
  <script setup lang="ts">
  // Ace is big; we lazy-load it so it doesn’t bloat your main bundle.
  import { onMounted, onBeforeUnmount, ref, watch, nextTick } from 'vue'
  
  // ----- Props (extend as needed)
  const props = withDefaults(defineProps<{
    modelValue: string
    lang?: string              // e.g. "javascript", "php", "json", "lua", "markdown"
    theme?: string             // e.g. "monokai", "github", "one_dark"
    readOnly?: boolean
    tabSize?: number
    softTabs?: boolean
    wrap?: boolean
    showGutter?: boolean
    showPrintMargin?: boolean
    minLines?: number
    maxLines?: number
    debounce?: number          // ms debounce for change events
    placeholder?: string
    keyboardHandler?: 'vim' | 'emacs' | 'vscode' | null
  }>(), {
    modelValue: '',
    lang: 'javascript',
    theme: 'one_dark',
    readOnly: false,
    tabSize: 2,
    softTabs: true,
    wrap: true,
    showGutter: true,
    showPrintMargin: false,
    minLines: 8,
    maxLines: 40,
    debounce: 150,
    placeholder: ''
  })
  
  const emit = defineEmits<{
    (e: 'update:modelValue', v: string): void
    (e: 'blur', v: string): void
    (e: 'ready'): void
  }>()
  
  const root = ref<HTMLElement | null>(null)
  let editor: any
  let ace: any
  let changeTimer: ReturnType<typeof setTimeout> | null = null
  
  // Utility: map theme + mode to the CDN-like module IDs Ace expects.
  function aceThemeId(name: string) {
    return `ace/theme/${name}`
  }
  function aceModeId(name: string) {
    return `ace/mode/${name}`
  }
  
  onMounted(async () => {
    if (!root.value) return
  
    // Lazy-load ace core
    ace = await import('ace-builds/src-noconflict/ace')
  
    // Dynamically load theme & mode only when needed
    await Promise.all([
      import(`ace-builds/src-noconflict/theme-${props.theme}`).catch(() => {}),
      import(`ace-builds/src-noconflict/mode-${props.lang}`).catch(() => {}),
    ])
  
    // Optional: keyboard handlers
    if (props.keyboardHandler) {
      await import(`ace-builds/src-noconflict/keybinding-${props.keyboardHandler}`).catch(() => {})
    }
  
    // Workers (Vite-friendly): use ?url so Ace can fetch them at runtime
    // Only set for the chosen language to keep things small.
    try {
      const workerUrl = await import(
        /* @vite-ignore */ `ace-builds/src-noconflict/worker-${props.lang}?url`
      )
      ace.config.setModuleUrl(`ace/mode/${props.lang}_worker`, workerUrl.default)
    } catch {
      // No worker for this lang; that’s fine.
    }
  
    // Create editor
    editor = ace.edit(root.value, {
      value: props.modelValue,
      mode: aceModeId(props.lang),
      theme: aceThemeId(props.theme),
      readOnly: props.readOnly,
      wrap: props.wrap,
      showGutter: props.showGutter,
      showPrintMargin: props.showPrintMargin,
      minLines: props.minLines,
      maxLines: props.maxLines,
      useSoftTabs: props.softTabs,
      tabSize: props.tabSize,
      highlightActiveLine: true,
      highlightSelectedWord: true,
      indentedSoftWrap: false,
      enableBasicAutocompletion: true,
      enableLiveAutocompletion: true,
      enableSnippets: true,
    })
  
    if (props.placeholder) {
      // Lightweight placeholder: show faint text when empty
      const session = editor.getSession()
      const updatePlaceholder = () => {
        const isEmpty = !session.getValue()
        editor.setOption('placeholder', isEmpty ? props.placeholder : '')
      }
      session.on('change', updatePlaceholder)
      updatePlaceholder()
    }
  
    if (props.keyboardHandler) {
      editor.setKeyboardHandler(`ace/keyboard/${props.keyboardHandler}`)
    }
  
    // v-model sync w/ debounce
    editor.on('change', () => {
      if (changeTimer) clearTimeout(changeTimer)
      changeTimer = setTimeout(() => {
        emit('update:modelValue', editor.getValue())
      }, props.debounce)
    })
  
    editor.on('blur', () => emit('blur', editor.getValue()))
  
    // Ensure initial layout
    await nextTick()
    editor.resize(true)
    emit('ready')
  })
  
  // Keep editor in sync if the parent updates modelValue
  watch(() => props.modelValue, (v) => {
    if (!editor) return
    if (v !== editor.getValue()) {
      const pos = editor.getCursorPosition()
      editor.setValue(v ?? '', -1) // -1 = keep viewport
      editor.moveCursorToPosition(pos)
    }
  })
  
  // Respond to prop changes that affect options
  watch(() => [props.readOnly, props.wrap, props.showGutter, props.showPrintMargin, props.softTabs, props.tabSize],
    () => {
      if (!editor) return
      editor.setOptions({
        readOnly: props.readOnly,
        wrap: props.wrap,
        showGutter: props.showGutter,
        showPrintMargin: props.showPrintMargin,
        useSoftTabs: props.softTabs,
        tabSize: props.tabSize
      })
    }, { deep: true })
  
  // Change language / theme on-the-fly
  watch(() => props.lang, async (lang) => {
    if (!editor || !ace) return
    await import(`ace-builds/src-noconflict/mode-${lang}`).catch(() => {})
    try {
      const workerUrl = await import(
        /* @vite-ignore */ `ace-builds/src-noconflict/worker-${lang}?url`
      )
      ace.config.setModuleUrl(`ace/mode/${lang}_worker`, workerUrl.default)
    } catch {}
    editor.session.setMode(aceModeId(lang))
  })
  
  watch(() => props.theme, async (theme) => {
    if (!editor) return
    await import(`ace-builds/src-noconflict/theme-${theme}`).catch(() => {})
    editor.setTheme(aceThemeId(theme))
  })
  
  onBeforeUnmount(() => {
    if (changeTimer) clearTimeout(changeTimer)
    if (editor) {
      editor.destroy()
      editor = null
    }
  })
  </script>
  
  <style scoped>
  /* Ensure the container can grow; control height via parent */
  div { min-height: 2rem; }
  </style>
  