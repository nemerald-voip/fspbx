<template>
    <MainLayout>
        <div class="relative flex min-h-screen w-full max-w-full flex-col">
            <main class="flex flex-1 gap-4 pb-10 pt-12 md:gap-6">

                <!-- Sidebar -->
                <aside class="relative z-10 flex-none" :style="{ width: collapsed ? '3.5rem' : `${width}px` }"
                    :class="resizing ? '' : 'transition-[width] duration-200 ease-in-out'">
                    <div class="sticky top-4 flex flex-col rounded-md border border-gray-200 bg-white shadow-sm"
                        :class="collapsed ? 'overflow-visible' : 'max-h-[calc(100vh-2rem)] overflow-hidden'">

                        <!-- Header -->
                        <div class="flex shrink-0 items-center gap-x-2 border-b border-gray-200 px-2 py-2.5"
                            :class="collapsed ? 'justify-center' : ''">
                            <component :is="headerIcon" v-if="headerIcon && !collapsed"
                                class="ml-1 size-5 shrink-0 text-indigo-600" aria-hidden="true" />
                            <span v-if="!collapsed" class="min-w-0 flex-1 truncate text-sm font-semibold text-gray-900"
                                :title="title">{{ title }}</span>
                            <button type="button" :aria-label="collapsed ? $t('Expand sidebar') : $t('Collapse sidebar')"
                                :title="collapsed ? $t('Expand sidebar') : $t('Collapse sidebar')"
                                class="shrink-0 rounded-md p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                                @click="toggleNav">
                                <ChevronDoubleLeftIcon class="size-4 transition-transform"
                                    :class="collapsed ? 'rotate-180' : ''" aria-hidden="true" />
                            </button>
                        </div>

                        <!-- Filter: only earns its space once the list is long enough to scan -->
                        <div v-if="!collapsed && searchable" class="shrink-0 border-b border-gray-200 p-2">
                            <div class="relative">
                                <MagnifyingGlassIcon
                                    class="pointer-events-none absolute left-2 top-1/2 size-4 -translate-y-1/2 text-gray-400"
                                    aria-hidden="true" />
                                <input v-model="query" type="search" :placeholder="$t('Filter')" :aria-label="$t('Filter')"
                                    class="w-full rounded-md border-0 py-1.5 pl-8 pr-2 text-sm text-gray-900 ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-indigo-500" />
                            </div>
                        </div>

                        <!-- Navigation -->
                        <nav class="flex-1 p-2" :aria-label="title"
                            :class="collapsed ? 'overflow-visible' : 'overflow-y-auto overflow-x-hidden'">
                            <p v-if="!collapsed && query.trim() && !visibleNavigation.length"
                                class="px-2 py-6 text-center text-sm text-gray-500">
                                {{ $t('Nothing matches this filter.') }}
                            </p>
                            <ul role="list" class="space-y-0.5">
                                <li v-for="item in visibleNavigation" :key="item.key">

                                    <!-- Expanded -->
                                    <template v-if="!collapsed">
                                        <!-- Leaf -->
                                        <button v-if="!item.children" type="button" :title="label(item)"
                                            :aria-current="isActive(item.key) ? 'page' : undefined" :class="leafClass(item)"
                                            @click="select(item.key)">
                                            <span v-if="isActive(item.key)"
                                                class="absolute inset-y-1 left-0 w-0.5 rounded-full bg-indigo-600"
                                                aria-hidden="true" />
                                            <component :is="item.icon" v-if="item.icon" class="size-5 shrink-0"
                                                :class="isActive(item.key) ? 'text-indigo-600' : 'text-gray-400 group-hover:text-gray-500'"
                                                aria-hidden="true" />
                                            <span class="min-w-0 flex-1 truncate">{{ item.name }}</span>
                                            <span v-if="item.meta" class="shrink-0 text-xs tabular-nums"
                                                :class="isActive(item.key) ? 'text-indigo-500' : 'text-gray-400'">{{ item.meta }}</span>
                                        </button>

                                        <!-- Group: a section heading, never a selectable row, so it
                                             cannot be mistaken for the current selection. -->
                                        <div v-else>
                                            <button type="button" :aria-expanded="isOpen(item.key)"
                                                :aria-controls="`${uid}-${item.key}`"
                                                class="group flex w-full items-center gap-x-1.5 rounded-md py-1.5 pl-1 pr-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 hover:bg-gray-50"
                                                @click="toggleGroup(item.key)">
                                                <ChevronRightIcon
                                                    class="size-4 shrink-0 text-gray-400 transition-transform"
                                                    :class="isOpen(item.key) ? 'rotate-90' : ''" aria-hidden="true" />
                                                <component :is="item.icon" v-if="item.icon"
                                                    class="size-4 shrink-0 text-gray-400" aria-hidden="true" />
                                                <span class="min-w-0 flex-1 truncate" :title="item.name">{{ item.name }}</span>
                                                <span v-if="!isOpen(item.key) && groupHasActive(item)"
                                                    class="size-1.5 shrink-0 rounded-full bg-indigo-600"
                                                    :title="$t('Current selection is in this group')" />
                                                <span class="shrink-0 text-xs font-medium tabular-nums text-gray-400">
                                                    {{ item.children.length }}
                                                </span>
                                            </button>
                                            <ul v-show="isOpen(item.key)" :id="`${uid}-${item.key}`" role="list"
                                                class="ml-3 mt-0.5 space-y-0.5 border-l border-gray-200 pl-1.5">
                                                <li v-for="sub in item.children" :key="sub.key">
                                                    <button type="button" :title="label(sub)"
                                                        :aria-current="isActive(sub.key) ? 'page' : undefined"
                                                        :class="leafClass(sub)" @click="select(sub.key)">
                                                        <span v-if="isActive(sub.key)"
                                                            class="absolute inset-y-1 left-0 w-0.5 rounded-full bg-indigo-600"
                                                            aria-hidden="true" />
                                                        <component :is="sub.icon" v-if="sub.icon" class="size-4 shrink-0"
                                                            :class="isActive(sub.key) ? 'text-indigo-600' : 'text-gray-400 group-hover:text-gray-500'"
                                                            aria-hidden="true" />
                                                        <span class="min-w-0 flex-1 truncate">{{ sub.name }}</span>
                                                        <span v-if="sub.meta" class="shrink-0 text-xs tabular-nums"
                                                            :class="isActive(sub.key) ? 'text-indigo-500' : 'text-gray-400'">{{ sub.meta }}</span>
                                                    </button>
                                                </li>
                                            </ul>
                                        </div>
                                    </template>

                                    <!-- Collapsed rail: the flyout opens on hover and on keyboard
                                         focus, so it is reachable without a pointer. -->
                                    <div v-else class="relative" @mouseenter="flyout = item.key"
                                        @mouseleave="flyout = null" @focusin="flyout = item.key"
                                        @focusout="closeFlyout($event, item.key)">
                                        <button type="button" :aria-label="item.name" :title="item.name"
                                            :aria-current="isActive(item.key) ? 'page' : undefined"
                                            :aria-expanded="item.children ? flyout === item.key : undefined"
                                            class="relative flex w-full items-center justify-center rounded-md p-2 hover:bg-gray-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                                            :class="railActive(item) ? 'bg-indigo-50' : ''"
                                            @click="item.children ? (flyout = flyout === item.key ? null : item.key) : select(item.key)">
                                            <component :is="item.icon" v-if="item.icon" class="size-5 shrink-0"
                                                :class="railActive(item) ? 'text-indigo-600' : 'text-gray-400'"
                                                aria-hidden="true" />
                                            <span v-if="railActive(item)"
                                                class="absolute inset-y-1 left-0 w-0.5 rounded-full bg-indigo-600"
                                                aria-hidden="true" />
                                        </button>
                                        <div v-if="flyout === item.key"
                                            class="absolute left-full top-0 z-30 ml-2 w-60 rounded-md bg-white p-2 shadow-lg ring-1 ring-gray-900/5">
                                            <p class="truncate px-2 py-1 text-xs font-semibold uppercase tracking-wide text-gray-500">
                                                {{ item.name }}
                                            </p>
                                            <ul v-if="item.children" role="list" class="mt-1 max-h-80 space-y-0.5 overflow-y-auto">
                                                <li v-for="sub in item.children" :key="sub.key">
                                                    <button type="button" :title="label(sub)"
                                                        :aria-current="isActive(sub.key) ? 'page' : undefined"
                                                        :class="leafClass(sub)" @click="select(sub.key); flyout = null">
                                                        <component :is="sub.icon" v-if="sub.icon" class="size-4 shrink-0"
                                                            :class="isActive(sub.key) ? 'text-indigo-600' : 'text-gray-400'"
                                                            aria-hidden="true" />
                                                        <span class="min-w-0 flex-1 truncate">{{ sub.name }}</span>
                                                        <span v-if="sub.meta" class="shrink-0 text-xs tabular-nums"
                                                            :class="isActive(sub.key) ? 'text-indigo-500' : 'text-gray-400'">{{ sub.meta }}</span>
                                                    </button>
                                                </li>
                                            </ul>
                                        </div>
                                    </div>
                                </li>
                            </ul>
                        </nav>
                    </div>

                    <!-- Drag to widen: the only real answer to a name that does not fit. -->
                    <div v-if="!collapsed" role="separator" aria-orientation="vertical" tabindex="0"
                        :aria-label="$t('Resize sidebar')" :aria-valuenow="width" :aria-valuemin="MIN_WIDTH"
                        :aria-valuemax="MAX_WIDTH" :title="$t('Drag to resize. Double-click to reset.')"
                        class="group absolute inset-y-0 -right-2 z-20 flex w-4 cursor-col-resize touch-none justify-center focus:outline-none"
                        @pointerdown="startResize" @keydown="resizeByKey" @dblclick="setWidth(DEFAULT_WIDTH)">
                        <div class="h-full w-0.5 rounded-full transition-colors group-hover:bg-indigo-300 group-focus:bg-indigo-500"
                            :class="resizing ? 'bg-indigo-500' : 'bg-transparent'" />
                    </div>
                </aside>

                <!-- Main content column -->
                <div class="flex min-w-0 flex-1 flex-col space-y-3">
                    <nav class="flex py-2" aria-label="Breadcrumb">
                        <ol role="list" class="flex items-center space-x-4">
                            <li v-for="(page, i) in pages" :key="page.name">
                                <div class="flex items-center">
                                    <ChevronRightIcon v-if="i > 0" class="size-5 shrink-0 text-gray-400"
                                        aria-hidden="true" />
                                    <a :href="page.href"
                                        class="text-sm font-medium text-gray-500 hover:text-gray-700"
                                        :class="i > 0 ? 'ml-4' : ''"
                                        :aria-current="page.current ? 'page' : undefined">{{ page.name }}</a>
                                </div>
                            </li>
                        </ol>
                    </nav>

                    <div class="flex-1 bg-gray-50 px-4 py-6 text-gray-600 shadow md:rounded-md md:p-6">
                        <slot :selected-menu-option="selectedMenuOption"></slot>
                    </div>
                </div>
            </main>
        </div>

        <slot name="overlays" />
    </MainLayout>
</template>

<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import MainLayout from '../Layouts/MainLayout.vue'
import { ChevronRightIcon } from '@heroicons/vue/20/solid'
import { ChevronDoubleLeftIcon, MagnifyingGlassIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
    title: { type: String, default: '' },
    navigation: { type: Array, default: () => [] },
    pages: { type: Array, default: () => [] },
    initialMenuOption: String,
    headerIcon: { type: [Object, Function, String], default: null },
})

const emit = defineEmits(['updateSelectedMenuOption'])

const MIN_WIDTH = 200
const MAX_WIDTH = 480
const DEFAULT_WIDTH = 264
// The filter is for long lists that have to be scanned -- queues, agents --
// not for a settings menu of a dozen fixed entries. It is measured against the
// longest single list on screen, not the total, so a page made of several short
// groups never gets one.
const SEARCH_THRESHOLD = 10

const selectedMenuOption = ref(null)
const collapsed = ref(false)
const width = ref(DEFAULT_WIDTH)
const resizing = ref(false)
const query = ref('')
const flyout = ref(null)
const openGroups = ref(new Set())
// Once the operator collapses or expands by hand, the breakpoint watcher stops
// overriding them -- the old version slammed the panel shut on every resize
// event, including the browser chrome hiding on mobile.
const userSetCollapsed = ref(false)
const uid = `sidemenu-${Math.random().toString(36).slice(2, 9)}`

const storageKey = computed(() => `sidemenu:${(props.title || 'page').toLowerCase().replace(/\s+/g, '-')}`)

function readStored(suffix) {
    try { return window.localStorage.getItem(`${storageKey.value}:${suffix}`) } catch { return null }
}
function writeStored(suffix, value) {
    try { window.localStorage.setItem(`${storageKey.value}:${suffix}`, String(value)) } catch { /* private mode */ }
}

const clamp = (value) => Math.min(MAX_WIDTH, Math.max(MIN_WIDTH, Math.round(value)))
function setWidth(value) {
    width.value = clamp(value)
    writeStored('width', width.value)
}

const label = (item) => [item.name, item.meta].filter(Boolean).join(' · ')
const isActive = (key) => selectedMenuOption.value === key
const groupHasActive = (item) => Array.isArray(item.children) && item.children.some((child) => isActive(child.key))
const railActive = (item) => isActive(item.key) || groupHasActive(item)

function leafClass(item) {
    return [
        'group relative flex w-full items-center gap-x-2.5 rounded-md py-1.5 pl-2.5 pr-2 text-left text-sm focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500',
        isActive(item.key) ? 'bg-indigo-50 font-semibold text-indigo-700' : 'font-medium text-gray-700 hover:bg-gray-50',
    ]
}

const longestList = computed(() => Math.max(
    props.navigation.length,
    ...props.navigation.map((item) => item.children?.length ?? 0),
))
const searchable = computed(() => longestList.value >= SEARCH_THRESHOLD)

// Filtering keeps a group whose own name matches, so searching "agents" still
// shows the whole group rather than emptying it.
const visibleNavigation = computed(() => {
    const needle = query.value.trim().toLowerCase()
    if (!needle) return props.navigation
    const matches = (item) => `${item.name ?? ''} ${item.meta ?? ''}`.toLowerCase().includes(needle)
    return props.navigation.reduce((kept, item) => {
        if (!item.children) {
            if (matches(item)) kept.push(item)
            return kept
        }
        if (matches(item)) { kept.push(item); return kept }
        const children = item.children.filter(matches)
        if (children.length) kept.push({ ...item, children })
        return kept
    }, [])
})

const isOpen = (key) => (query.value.trim() ? true : openGroups.value.has(key))
function toggleGroup(key) {
    const next = new Set(openGroups.value)
    next.has(key) ? next.delete(key) : next.add(key)
    openGroups.value = next
}
function openGroupFor(key) {
    const parent = props.navigation.find((item) => item.children?.some((child) => child.key === key))
    if (parent && !openGroups.value.has(parent.key)) openGroups.value = new Set(openGroups.value).add(parent.key)
}

function toggleNav() {
    collapsed.value = !collapsed.value
    userSetCollapsed.value = true
    flyout.value = null
    writeStored('collapsed', collapsed.value)
}

function select(key) {
    selectedMenuOption.value = key
    emit('updateSelectedMenuOption', key)
}

// Only dismiss when focus actually leaves the flyout and its trigger.
function closeFlyout(event, key) {
    if (event.currentTarget.contains(event.relatedTarget)) return
    if (flyout.value === key) flyout.value = null
}

function startResize(event) {
    resizing.value = true
    const startX = event.clientX
    const startWidth = width.value
    const move = (moveEvent) => setWidth(startWidth + moveEvent.clientX - startX)
    const stop = () => {
        resizing.value = false
        window.removeEventListener('pointermove', move)
        window.removeEventListener('pointerup', stop)
    }
    window.addEventListener('pointermove', move)
    window.addEventListener('pointerup', stop)
    event.preventDefault()
}

function resizeByKey(event) {
    const step = event.shiftKey ? 48 : 16
    const moves = { ArrowLeft: -step, ArrowRight: step, Home: MIN_WIDTH - width.value, End: MAX_WIDTH - width.value }
    if (!(event.key in moves)) return
    event.preventDefault()
    setWidth(width.value + moves[event.key])
}

watch(() => props.initialMenuOption, (key) => {
    selectedMenuOption.value = key
    if (key) openGroupFor(key)
    emit('updateSelectedMenuOption', key)
}, { immediate: true })

// A group is opened when it gains the selection, but never force-closed --
// closing one the operator opened by hand would fight them.
watch(selectedMenuOption, (key) => { if (key) openGroupFor(key) })

// Navigation often arrives after the first selection is set, so reveal the
// selected group once it exists. With nothing selected, open the first group so
// a page whose whole job is picking from that list does not land fully closed.
watch(() => props.navigation, (items) => {
    if (selectedMenuOption.value) { openGroupFor(selectedMenuOption.value); return }
    if (openGroups.value.size) return
    const first = items.find((item) => item.children?.length)
    if (first) openGroups.value = new Set([first.key])
}, { immediate: true, deep: false })

const checkScreenSize = () => {
    if (userSetCollapsed.value) return
    collapsed.value = window.innerWidth < 768
}

onMounted(() => {
    const storedWidth = Number(readStored('width'))
    if (storedWidth) width.value = clamp(storedWidth)
    const storedCollapsed = readStored('collapsed')
    if (storedCollapsed !== null) {
        collapsed.value = storedCollapsed === 'true'
        userSetCollapsed.value = true
    }
    checkScreenSize()
    window.addEventListener('resize', checkScreenSize)
})

onUnmounted(() => window.removeEventListener('resize', checkScreenSize))
</script>
