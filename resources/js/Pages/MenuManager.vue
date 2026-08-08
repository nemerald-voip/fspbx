<template>
    <MainLayout />

    <div class="m-3 space-y-4">
        <header class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h1 class="text-2xl font-semibold text-gray-900">{{ $t('Menu Manager') }}</h1>
                <p class="mt-1 max-w-3xl text-sm text-gray-500">
                    {{ $t('Manage navigation menus, item hierarchy, and group visibility.') }}
                </p>
            </div>
            <button
                v-if="permissions.menu_create"
                type="button"
                class="inline-flex items-center gap-1.5 rounded-md bg-indigo-600 px-3 py-1.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600"
                @click="openMenuEditor()"
            >
                <PlusIcon class="h-4 w-4" />
                {{ $t('New Menu') }}
            </button>
        </header>

        <div class="flex flex-col gap-4 lg:flex-row">
            <aside class="lg:w-72 lg:shrink-0">
                <div class="rounded-lg bg-white shadow-sm ring-1 ring-gray-200">
                    <div class="border-b border-gray-200 p-3">
                        <div class="relative">
                            <MagnifyingGlassIcon class="pointer-events-none absolute inset-y-0 left-3 my-auto h-4 w-4 text-gray-400" />
                            <input
                                v-model="menuSearch"
                                type="search"
                                :placeholder="$t('Find a menu')"
                                class="block w-full rounded-md border-0 py-1.5 pl-9 text-sm text-gray-900 ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600"
                            />
                        </div>
                    </div>

                    <nav class="max-h-[70vh] overflow-y-auto p-2" :aria-label="$t('Menus')">
                        <button
                            v-for="menu in filteredMenus"
                            :key="menu.menu_uuid"
                            type="button"
                            :class="menuButtonClass(menu)"
                            @click="selectMenu(menu.menu_uuid)"
                        >
                            <span class="min-w-0 flex-1 text-left">
                                <span class="flex items-center gap-2">
                                    <span class="truncate text-sm font-semibold">{{ menu.menu_name }}</span>
                                    <span
                                        v-if="menu.is_active"
                                        class="rounded bg-green-50 px-1.5 py-0.5 text-[10px] font-medium text-green-700 ring-1 ring-inset ring-green-600/20"
                                    >
                                        {{ $t('Active') }}
                                    </span>
                                </span>
                                <span class="mt-0.5 block truncate text-xs text-gray-500">
                                    {{ languageLabel(menu.menu_language) }} ·
                                    {{ $t(':count items', { count: menu.items_count }) }}
                                </span>
                            </span>
                            <ChevronRightIcon class="h-4 w-4 shrink-0 text-gray-400" />
                        </button>

                        <div v-if="!filteredMenus.length" class="px-3 py-8 text-center">
                            <p class="text-sm font-medium text-gray-900">{{ $t('No menus found') }}</p>
                            <p class="mt-1 text-xs text-gray-500">{{ $t('Try a different search.') }}</p>
                        </div>
                    </nav>
                </div>
            </aside>

            <section class="min-w-0 flex-1">
                <div v-if="selectedMenu" class="rounded-lg bg-white shadow-sm ring-1 ring-gray-200">
                    <header class="flex flex-wrap items-start justify-between gap-3 border-b border-gray-200 px-4 py-3">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <h2 class="truncate text-base font-semibold text-gray-900">{{ selectedMenu.menu_name }}</h2>
                                <span class="rounded bg-gray-100 px-1.5 py-0.5 text-xs text-gray-600">
                                    {{ languageLabel(selectedMenu.menu_language) }}
                                </span>
                                <span
                                    v-if="selectedMenu.is_active"
                                    class="rounded bg-green-50 px-1.5 py-0.5 text-xs font-medium text-green-700 ring-1 ring-inset ring-green-600/20"
                                >
                                    {{ $t('Active Menu') }}
                                </span>
                            </div>
                            <p v-if="selectedMenu.menu_description" class="mt-1 text-sm text-gray-500">
                                {{ selectedMenu.menu_description }}
                            </p>
                        </div>
                        <div class="flex items-center gap-2">
                            <button
                                v-if="permissions.menu_update"
                                type="button"
                                class="rounded-md bg-white px-2.5 py-1.5 text-sm font-medium text-gray-700 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50"
                                @click="openMenuEditor(selectedMenu)"
                            >
                                {{ $t('Edit Menu') }}
                            </button>
                            <button
                                v-if="permissions.menu_destroy"
                                type="button"
                                class="rounded-md px-2.5 py-1.5 text-sm font-medium text-rose-600 hover:bg-rose-50"
                                @click="confirmMenuDelete"
                            >
                                {{ $t('Delete Menu') }}
                            </button>
                        </div>
                    </header>

                    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 px-4 py-3">
                        <div class="relative min-w-64 flex-1 sm:max-w-sm">
                            <MagnifyingGlassIcon class="pointer-events-none absolute inset-y-0 left-3 my-auto h-4 w-4 text-gray-400" />
                            <input
                                v-model="itemSearch"
                                type="search"
                                :placeholder="$t('Search menu items')"
                                class="block w-full rounded-md border-0 py-1.5 pl-9 text-sm text-gray-900 ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600"
                            />
                        </div>
                        <div class="flex items-center gap-2">
                            <div
                                v-if="selectedItems.length"
                                class="flex items-center gap-2 rounded-md bg-indigo-50 px-2 py-1 text-xs font-medium text-indigo-700"
                            >
                                <span>{{ $t(':count selected', { count: selectedItems.length }) }}</span>
                                <button
                                    v-if="bulkGroupOperationOptions.length"
                                    type="button"
                                    class="rounded px-1.5 py-0.5 hover:bg-indigo-100"
                                    @click="showBulkGroupEditor = true"
                                >
                                    {{ $t('Edit Groups') }}
                                </button>
                                <button
                                    v-if="permissions.item_destroy"
                                    type="button"
                                    class="rounded px-1.5 py-0.5 text-rose-700 hover:bg-rose-100"
                                    @click="confirmItemsDelete(selectedItems)"
                                >
                                    {{ $t('Delete') }}
                                </button>
                            </div>
                            <button
                                v-if="permissions.item_create"
                                type="button"
                                class="inline-flex items-center gap-1.5 rounded-md bg-indigo-600 px-3 py-1.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500"
                                @click="openItemEditor()"
                            >
                                <PlusIcon class="h-4 w-4" />
                                {{ $t('Add Item') }}
                            </button>
                        </div>
                    </div>

                    <div v-if="loading" class="px-4 py-16">
                        <Loading :show="true" :absolute="false" />
                    </div>

                    <div v-else-if="visibleRows.length" class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="w-10 px-4 py-2 text-left">
                                        <input
                                            type="checkbox"
                                            :checked="allVisibleSelected"
                                            :aria-label="$t('Select visible menu items')"
                                            class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-600"
                                            @change="toggleVisibleSelection"
                                        />
                                    </th>
                                    <th class="px-2 py-2 text-left text-xs font-semibold text-gray-600">{{ $t('Item') }}</th>
                                    <th class="px-2 py-2 text-left text-xs font-semibold text-gray-600">{{ $t('Groups') }}</th>
                                    <th class="w-20 px-2 py-2 text-left text-xs font-semibold text-gray-600">{{ $t('Order') }}</th>
                                    <th class="w-32 px-4 py-2 text-right text-xs font-semibold text-gray-600">{{ $t('Actions') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 bg-white">
                                <tr v-for="row in visibleRows" :key="row.menu_item_uuid" class="hover:bg-gray-50">
                                    <td class="px-4 py-2 align-top">
                                        <input
                                            v-model="selectedItems"
                                            type="checkbox"
                                            :value="row.menu_item_uuid"
                                            :aria-label="$t('Select :item', { item: row.menu_item_title })"
                                            class="mt-0.5 h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-600"
                                        />
                                    </td>
                                    <td class="min-w-80 px-2 py-2">
                                        <div class="flex items-start gap-2" :style="{ paddingLeft: `${row.depth * 24}px` }">
                                            <FolderIcon v-if="row.hasChildren" class="mt-0.5 h-4 w-4 shrink-0 text-indigo-500" />
                                            <LinkIcon v-else class="mt-0.5 h-4 w-4 shrink-0 text-gray-400" />
                                            <div class="min-w-0">
                                                <div class="flex flex-wrap items-center gap-2">
                                                    <button
                                                        v-if="permissions.item_update"
                                                        type="button"
                                                        class="truncate text-left text-sm font-semibold text-gray-900 hover:text-indigo-600"
                                                        @click="openItemEditor(row)"
                                                    >
                                                        {{ row.menu_item_title }}
                                                    </button>
                                                    <span v-else class="truncate text-sm font-semibold text-gray-900">
                                                        {{ row.menu_item_title }}
                                                    </span>
                                                    <code v-if="row.menu_item_icon" class="text-[11px] text-gray-400">{{ row.menu_item_icon }}</code>
                                                </div>
                                                <code
                                                    v-if="row.menu_item_link"
                                                    class="mt-0.5 block max-w-xl truncate text-xs text-gray-500"
                                                    :title="row.menu_item_link"
                                                >
                                                    {{ row.menu_item_link }}
                                                </code>
                                                <p v-if="row.menu_item_description" class="mt-0.5 max-w-2xl truncate text-xs text-gray-400">
                                                    {{ row.menu_item_description }}
                                                </p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-2 py-2 align-top">
                                        <div v-if="row.groups.length" class="flex max-w-sm flex-wrap gap-1">
                                            <span
                                                v-for="group in row.groups"
                                                :key="`${row.menu_item_uuid}-${group.group_uuid || group.label}`"
                                                class="rounded bg-gray-100 px-1.5 py-0.5 text-[11px] text-gray-600"
                                            >
                                                {{ group.label }}
                                            </span>
                                        </div>
                                        <span v-else class="text-xs text-amber-700">{{ $t('No groups assigned') }}</span>
                                    </td>
                                    <td class="px-2 py-2 align-top text-sm text-gray-500">
                                        {{ row.depth === 0 ? (row.menu_item_order ?? '—') : '—' }}
                                    </td>
                                    <td class="px-4 py-2 align-top">
                                        <div class="flex items-center justify-end gap-1 whitespace-nowrap">
                                            <button
                                                v-if="permissions.item_create && row.depth === 0"
                                                type="button"
                                                class="rounded-md px-2 py-1 text-xs font-medium text-gray-600 hover:bg-gray-100 hover:text-gray-900"
                                                @click="openItemEditor(null, row.menu_item_uuid)"
                                            >
                                                {{ $t('Add Child') }}
                                            </button>
                                            <button
                                                v-if="permissions.item_update"
                                                type="button"
                                                class="rounded-md px-2 py-1 text-xs font-medium text-indigo-600 hover:bg-indigo-50"
                                                @click="openItemEditor(row)"
                                            >
                                                {{ $t('Edit') }}
                                            </button>
                                            <button
                                                v-if="permissions.item_destroy"
                                                type="button"
                                                class="rounded-md px-2 py-1 text-xs font-medium text-rose-600 hover:bg-rose-50"
                                                @click="confirmItemsDelete([row.menu_item_uuid])"
                                            >
                                                {{ $t('Delete') }}
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div v-else class="px-4 py-16 text-center">
                        <Bars3BottomLeftIcon class="mx-auto h-10 w-10 text-gray-300" />
                        <p class="mt-2 text-sm font-medium text-gray-900">
                            {{ itemSearch ? $t('No menu items match your search') : $t('This menu has no items') }}
                        </p>
                        <p class="mt-1 text-xs text-gray-500">
                            {{ itemSearch ? $t('Try a different search.') : $t('Add a top-level item to begin building the navigation.') }}
                        </p>
                    </div>
                </div>

                <div v-else class="rounded-lg bg-white px-4 py-16 text-center shadow-sm ring-1 ring-gray-200">
                    <Bars3BottomLeftIcon class="mx-auto h-10 w-10 text-gray-300" />
                    <p class="mt-2 text-sm font-medium text-gray-900">{{ $t('No menu selected') }}</p>
                    <p class="mt-1 text-xs text-gray-500">{{ $t('Choose a menu or create a new one.') }}</p>
                </div>
            </section>
        </div>
    </div>

    <MenuManagerMenuForm
        :show="showMenuEditor"
        :item="menuEditorItem"
        :route="menuEditorRoute"
        :language-options="languageOptions"
        @close="showMenuEditor = false"
        @success="handleMenuSaved"
        @error="handleErrorResponse"
    />

    <MenuManagerItemForm
        :show="showItemEditor"
        :item="itemEditorItem"
        :route="itemEditorRoute"
        :parent-options="parentOptions"
        :group-options="groupOptions"
        :initial-parent-uuid="initialParentUuid"
        :can-manage-groups="permissions.group_manage"
        @close="showItemEditor = false"
        @success="handleItemSaved"
        @error="handleErrorResponse"
    />

    <MenuManagerBulkGroupsForm
        :show="showBulkGroupEditor"
        :route="bulkGroupEditorRoute"
        :items="selectedItems"
        :group-options="groupOptions"
        :operation-options="bulkGroupOperationOptions"
        @close="showBulkGroupEditor = false"
        @success="handleBulkGroupsSaved"
        @error="handleErrorResponse"
    />

    <ConfirmationModal
        :show="showConfirmation"
        :header="confirmationHeader"
        :text="confirmationText"
        :confirm-button-label="$t('Delete')"
        :cancel-button-label="$t('Cancel')"
        @close="showConfirmation = false"
        @confirm="executeConfirmedAction"
    />

    <Notification
        :show="notificationShow"
        :type="notificationType"
        :messages="notificationMessages"
        @update:show="notificationShow = false"
    />
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import axios from 'axios'
import { trans } from '@i18n'
import MainLayout from '../Layouts/MainLayout.vue'
import Loading from './components/general/Loading.vue'
import ConfirmationModal from './components/modal/ConfirmationModal.vue'
import Notification from './components/notifications/Notification.vue'
import MenuManagerMenuForm from './components/forms/MenuManagerMenuForm.vue'
import MenuManagerItemForm from './components/forms/MenuManagerItemForm.vue'
import MenuManagerBulkGroupsForm from './components/forms/MenuManagerBulkGroupsForm.vue'
import {
    Bars3BottomLeftIcon,
    ChevronRightIcon,
    FolderIcon,
    LinkIcon,
    MagnifyingGlassIcon,
    PlusIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
    managed_menus: {
        type: Array,
        default: () => [],
    },
    active_menu_uuid: String,
    routes: Object,
    permissions: Object,
    language_options: {
        type: Array,
        default: () => [],
    },
})

const menuRows = ref([...props.managed_menus])
const selectedMenuUuid = ref(
    props.active_menu_uuid && menuRows.value.some(menu => menu.menu_uuid === props.active_menu_uuid)
        ? props.active_menu_uuid
        : (menuRows.value[0]?.menu_uuid ?? null)
)
const selectedMenu = ref(null)
const items = ref([])
const groupOptions = ref([])
const loading = ref(false)
const menuSearch = ref('')
const itemSearch = ref('')
const selectedItems = ref([])
const showMenuEditor = ref(false)
const menuEditorItem = ref({})
const showItemEditor = ref(false)
const itemEditorItem = ref({})
const initialParentUuid = ref('')
const showBulkGroupEditor = ref(false)
const showConfirmation = ref(false)
const confirmedAction = ref(null)
const confirmationHeader = ref('')
const confirmationText = ref('')
const notificationShow = ref(false)
const notificationType = ref(null)
const notificationMessages = ref(null)

const languageOptions = computed(() => props.language_options)
const languageLabels = computed(() => new Map(languageOptions.value.map(option => [option.value, option.label])))
const languageLabel = (code) => languageLabels.value.get(code) ?? code

const filteredMenus = computed(() => {
    const search = menuSearch.value.trim().toLowerCase()
    if (!search) return menuRows.value

    return menuRows.value.filter(menu =>
        [menu.menu_name, menu.menu_language, menu.menu_description]
            .filter(Boolean)
            .join(' ')
            .toLowerCase()
            .includes(search)
    )
})

const sortedItems = (parentUuid = null) => items.value
    .filter(item => (item.menu_item_parent_uuid || null) === parentUuid)
    .sort((a, b) => {
        if (parentUuid === null) {
            const aOrder = a.menu_item_order ?? Number.MAX_SAFE_INTEGER
            const bOrder = b.menu_item_order ?? Number.MAX_SAFE_INTEGER
            if (aOrder !== bOrder) return aOrder - bOrder
        }

        return String(a.menu_item_title || '').localeCompare(String(b.menu_item_title || ''))
    })

const flatRows = computed(() => {
    const rows = []

    const append = (parentUuid = null, depth = 0, ancestry = new Set()) => {
        for (const item of sortedItems(parentUuid)) {
            if (ancestry.has(item.menu_item_uuid)) continue

            const children = sortedItems(item.menu_item_uuid)
            rows.push({ ...item, depth, hasChildren: children.length > 0 })
            append(item.menu_item_uuid, depth + 1, new Set([...ancestry, item.menu_item_uuid]))
        }
    }

    append()

    const included = new Set(rows.map(row => row.menu_item_uuid))
    for (const item of items.value) {
        if (!included.has(item.menu_item_uuid)) {
            rows.push({ ...item, depth: 0, hasChildren: false, orphaned: true })
        }
    }

    return rows
})

const visibleRows = computed(() => {
    const search = itemSearch.value.trim().toLowerCase()
    if (!search) return flatRows.value

    const matching = new Set()
    const byUuid = new Map(items.value.map(item => [item.menu_item_uuid, item]))

    for (const item of items.value) {
        const haystack = [
            item.menu_item_title,
            item.menu_item_link,
            item.menu_item_icon,
            item.menu_item_description,
            ...item.groups.map(group => group.label),
        ].filter(Boolean).join(' ').toLowerCase()

        if (!haystack.includes(search)) continue

        matching.add(item.menu_item_uuid)
        let parentUuid = item.menu_item_parent_uuid
        while (parentUuid && byUuid.has(parentUuid)) {
            matching.add(parentUuid)
            parentUuid = byUuid.get(parentUuid).menu_item_parent_uuid
        }
    }

    return flatRows.value.filter(row => matching.has(row.menu_item_uuid))
})

const parentOptions = computed(() => [
    { value: '', label: trans('Top Level') },
    ...items.value
        .filter(item => !item.menu_item_parent_uuid && item.menu_item_uuid !== itemEditorItem.value?.menu_item_uuid)
        .sort((a, b) => String(a.menu_item_title).localeCompare(String(b.menu_item_title)))
        .map(item => ({ value: item.menu_item_uuid, label: item.menu_item_title })),
])

const allVisibleSelected = computed(() =>
    visibleRows.value.length > 0
    && visibleRows.value.every(row => selectedItems.value.includes(row.menu_item_uuid))
)

const bulkGroupOperationOptions = computed(() => {
    const options = []

    if (props.permissions.group_add) {
        options.push({ value: 'add', label: trans('Add Groups') })
    }
    if (props.permissions.group_delete) {
        options.push({ value: 'remove', label: trans('Remove Groups') })
    }
    if (props.permissions.group_add && props.permissions.group_delete) {
        options.push({ value: 'replace', label: trans('Replace Groups') })
    }

    return options
})

const menuEditorRoute = computed(() =>
    menuEditorItem.value?.menu_uuid
        ? routeFor(props.routes.update, { __MENU__: menuEditorItem.value.menu_uuid })
        : props.routes.store
)

const itemEditorRoute = computed(() => {
    const menuUuid = selectedMenu.value?.menu_uuid
    if (!menuUuid) return ''

    return itemEditorItem.value?.menu_item_uuid
        ? routeFor(props.routes.items_update, {
            __MENU__: menuUuid,
            __ITEM__: itemEditorItem.value.menu_item_uuid,
        })
        : routeFor(props.routes.items_store, { __MENU__: menuUuid })
})

const bulkGroupEditorRoute = computed(() => {
    const menuUuid = selectedMenu.value?.menu_uuid

    return menuUuid
        ? routeFor(props.routes.items_bulk_groups, { __MENU__: menuUuid })
        : ''
})

onMounted(() => {
    if (selectedMenuUuid.value) fetchMenu(selectedMenuUuid.value)
})

function routeFor(template, replacements) {
    return Object.entries(replacements).reduce(
        (route, [token, value]) => route.replace(token, encodeURIComponent(value)),
        template
    )
}

function menuButtonClass(menu) {
    const base = 'flex w-full items-center gap-2 rounded-md px-3 py-2.5 transition focus:outline-none focus:ring-2 focus:ring-inset focus:ring-indigo-600'
    return menu.menu_uuid === selectedMenuUuid.value
        ? `${base} bg-gray-100 text-gray-900`
        : `${base} text-gray-700 hover:bg-gray-50`
}

async function selectMenu(menuUuid) {
    if (menuUuid === selectedMenuUuid.value && selectedMenu.value) return
    selectedMenuUuid.value = menuUuid
    itemSearch.value = ''
    selectedItems.value = []
    showBulkGroupEditor.value = false
    await fetchMenu(menuUuid)
}

async function fetchMenu(menuUuid) {
    loading.value = true
    try {
        const response = await axios.get(routeFor(props.routes.data, { __MENU__: menuUuid }))
        selectedMenu.value = response.data.menu
        items.value = response.data.items
        groupOptions.value = response.data.group_options
    } catch (error) {
        handleErrorResponse(error)
    } finally {
        loading.value = false
    }
}

function openMenuEditor(menu = null) {
    menuEditorItem.value = menu ? { ...menu } : {}
    showMenuEditor.value = true
}

function openItemEditor(item = null, parentUuid = '') {
    itemEditorItem.value = item ? { ...item } : {}
    initialParentUuid.value = parentUuid || ''
    showItemEditor.value = true
}

async function handleMenuSaved(payload) {
    menuRows.value = payload.menus ?? menuRows.value
    selectedMenuUuid.value = payload.menu?.menu_uuid ?? selectedMenuUuid.value
    showNotification('success', payload.messages)
    if (selectedMenuUuid.value) await fetchMenu(selectedMenuUuid.value)
}

async function handleItemSaved(payload) {
    showNotification('success', payload.messages)
    selectedItems.value = []
    await fetchMenu(selectedMenuUuid.value)
}

async function handleBulkGroupsSaved(payload) {
    showNotification('success', payload.messages)
    selectedItems.value = []
    await fetchMenu(selectedMenuUuid.value)
}

function confirmMenuDelete() {
    confirmationHeader.value = trans('Delete Menu')
    confirmationText.value = trans('This menu and all of its items will be permanently deleted.')
    confirmedAction.value = deleteMenu
    showConfirmation.value = true
}

function confirmItemsDelete(itemUuids) {
    const count = itemUuids.length
    confirmationHeader.value = trans(count === 1 ? 'Delete Menu Item' : 'Delete Menu Items')
    confirmationText.value = count === 1
        ? trans('This item and any child items will be permanently deleted.')
        : trans('The selected items and any child items will be permanently deleted.')
    confirmedAction.value = () => deleteItems(itemUuids)
    showConfirmation.value = true
}

async function executeConfirmedAction() {
    showConfirmation.value = false
    if (confirmedAction.value) await confirmedAction.value()
    confirmedAction.value = null
}

async function deleteMenu() {
    try {
        const response = await axios.delete(routeFor(props.routes.destroy, { __MENU__: selectedMenu.value.menu_uuid }))
        menuRows.value = response.data.menus ?? []
        showNotification('success', response.data.messages)
        selectedMenuUuid.value = menuRows.value[0]?.menu_uuid ?? null
        selectedMenu.value = null
        items.value = []
        if (selectedMenuUuid.value) await fetchMenu(selectedMenuUuid.value)
    } catch (error) {
        handleErrorResponse(error)
    }
}

async function deleteItems(itemUuids) {
    try {
        const menuUuid = selectedMenu.value.menu_uuid
        const response = itemUuids.length === 1
            ? await axios.delete(routeFor(props.routes.items_destroy, {
                __MENU__: menuUuid,
                __ITEM__: itemUuids[0],
            }))
            : await axios.post(
                routeFor(props.routes.items_bulk_destroy, { __MENU__: menuUuid }),
                { items: itemUuids }
            )
        selectedItems.value = []
        showNotification('success', response.data.messages)
        await fetchMenu(menuUuid)
    } catch (error) {
        handleErrorResponse(error)
    }
}

function toggleVisibleSelection(event) {
    const visibleUuids = visibleRows.value.map(row => row.menu_item_uuid)
    if (event.target.checked) {
        selectedItems.value = [...new Set([...selectedItems.value, ...visibleUuids])]
    } else {
        selectedItems.value = selectedItems.value.filter(uuid => !visibleUuids.includes(uuid))
    }
}

function handleErrorResponse(error) {
    const data = error?.response?.data
    const messages = data?.messages
        ?? (data?.errors ? { error: Object.values(data.errors).flat() } : null)
        ?? { error: [trans('The request could not be completed.')] }
    showNotification('error', messages)
}

function showNotification(type, messages) {
    notificationType.value = type
    notificationMessages.value = messages
    notificationShow.value = true
}
</script>
