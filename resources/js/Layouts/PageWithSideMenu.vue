<template>
    <MainLayout>
        <div class="relative w-full min-h-screen flex flex-col max-w-full">
            <main class="flex flex-1 gap-2 pb-10 pt-12 md:gap-8">

                <!-- Sidebar -->
                <aside :class="isNavCollapsed ? 'w-14' : 'w-64'"
                    class="relative z-10 flex flex-col flex-none transition-[width] duration-300 ease-in-out">
                    <div class="flex grow flex-col gap-y-5 border-r border-gray-200 bg-white"
                        :class="isNavCollapsed ? 'overflow-visible px-2.5' : 'overflow-y-auto px-4'">

                        <!-- Header -->
                        <div class="flex h-16 items-center">
                            <div :class="['flex flex-1 items-center', isNavCollapsed ? 'justify-center' : 'gap-x-3']">
                                <component :is="props.headerIcon"
                                    class="size-7 text-indigo-600 shrink-0" />
                                <span v-show="!isNavCollapsed" class="font-semibold text-gray-800 truncate">
                                    {{ title }}
                                </span>
                            </div>
                        </div>

                        <!-- Navigation -->
                        <nav class="flex flex-1 flex-col pb-5">
                            <ul role="list" class="flex flex-1 flex-col gap-y-7">
                                <li>
                                    <ul role="list" class="-mx-2 space-y-1">
                                        <li v-for="item in navigation" :key="item.key">
                                            <!-- Item WITHOUT children -->
                                            <div v-if="!item.children" class="relative group">
                                                <button type="button" @click="select(item.key)" :class="[
                                                    isActive(item.key) ? 'bg-gray-100' : 'hover:bg-gray-100',
                                                    'flex items-center w-full text-left rounded-md p-2 text-sm/6 font-semibold text-gray-700',
                                                    isNavCollapsed ? 'justify-center' : 'gap-x-3'
                                                ]">
                                                    <component :is="item.icon" class="size-6 shrink-0"
                                                        :class="isActive(item.key) ? 'text-indigo-600' : 'text-gray-400 group-hover:text-indigo-600'" />
                                                    <span class="truncate" v-show="!isNavCollapsed">{{ item.name
                                                    }}</span>
                                                </button>
                                                <span v-if="isNavCollapsed"
                                                    class="absolute left-full top-1/2 -translate-y-1/2 ml-4 w-auto min-w-max scale-0 rounded bg-gray-900 p-2 text-xs font-bold text-white transition-all group-hover:scale-100 origin-left z-30">
                                                    {{ item.name }}
                                                </span>
                                            </div>

                                            <!-- Item WITH children -->
                                            <div v-else>
                                                <!-- Expanded -->
                                                <Disclosure as="div" v-if="!isNavCollapsed" v-slot="{ open }"
                                                    :default-open="parentHasActiveChild(item)">
                                                    <DisclosureButton
                                                        :class="[parentHasActiveChild(item) ? 'bg-gray-100' : 'hover:bg-gray-100', 'flex w-full items-center gap-x-3 rounded-md p-2 text-left text-sm/6 font-semibold text-gray-700']">
                                                        <component :is="item.icon" class="size-6 shrink-0"
                                                            :class="parentHasActiveChild(item) ? 'text-indigo-600' : 'text-gray-400 group-hover:text-indigo-600'" />
                                                        <span class="truncate">{{ item.name }}</span>
                                                        <ChevronRightIcon
                                                            :class="[open ? 'rotate-90 text-gray-500' : 'text-gray-400', 'ml-auto size-5 shrink-0']" />
                                                    </DisclosureButton>
                                                    <DisclosurePanel as="ul" class="mt-1 pl-6">
                                        <li v-for="sub in item.children" :key="sub.key">
                                            <button type="button" @click="select(sub.key)" :class="[
                                                isActive(sub.key) ? 'bg-gray-100' : 'hover:bg-gray-100',
                                                'group flex w-full items-center gap-x-3 rounded-md py-2 px-3 text-sm/6 font-semibold text-gray-700'
                                            ]">
                                                <component v-if="sub.icon" :is="sub.icon" class="size-5 shrink-0"
                                                    :class="isActive(sub.key) ? 'text-indigo-600' : 'text-gray-400 group-hover:text-indigo-600'" />
                                                <span class="truncate">{{ sub.name }}</span>
                                            </button>
                                        </li>
                                        </DisclosurePanel>
                                        </Disclosure>

                                        <!-- Collapsed -->
                                        <div v-else class="relative group">
                                            <div
                                                :class="[parentHasActiveChild(item) ? 'bg-gray-100' : '', 'flex items-center justify-center rounded-md p-2']">
                                                <component :is="item.icon" class="size-6 shrink-0"
                                                    :class="parentHasActiveChild(item) ? 'text-indigo-600' : 'text-gray-400 group-hover:text-indigo-600'" />
                                            </div>
                                            <div class="absolute left-full top-0 h-full w-4" />
                                            <div
                                                class="absolute left-full top-0 ml-4 w-auto min-w-max scale-0 rounded-md bg-white shadow-lg ring-1 ring-gray-900/5 transition-transform group-hover:scale-100 origin-left z-30">
                                                <div class="p-2">
                                                    <p class="px-2 py-1 text-sm font-semibold text-gray-800">{{
                                                        item.name }}</p>
                                                    <ul role="list" class="mt-1 space-y-1">
                                                        <li v-for="sub in item.children" :key="sub.key">
                                                            <button type="button" @click="select(sub.key)" :class="[
                                                                isActive(sub.key) ? 'bg-gray-100 text-indigo-600' : 'hover:bg-gray-100 text-gray-700',
                                                                'group flex w-full items-center gap-x-3 rounded-md p-2 text-left text-sm/6 font-semibold'
                                                            ]">
                                                                <component v-if="sub.icon" :is="sub.icon"
                                                                    class="size-5 shrink-0"
                                                                    :class="isActive(sub.key) ? 'text-indigo-600' : 'text-gray-400 group-hover:text-indigo-600'" />
                                                                <span class="truncate">{{ sub.name }}</span>
                                                            </button>
                                                        </li>
                                                    </ul>
                                                </div>
                                            </div>
                                        </div>
                    </div>
                    </li>
                    </ul>
                    </li>
                    </ul>
                    </nav>
        </div>
        </aside>

        <!-- MAIN CONTENT COLUMN -->
        <div class="flex flex-1 flex-col space-y-3">

            <!-- Breadcrumbs + sidebar toggle -->
            <nav class="flex py-2" aria-label="Breadcrumb">
                <ol role="list" class="flex items-center space-x-4">
                    <li>
                        <button type="button" @click="toggleNav"
                            :aria-label="isNavCollapsed ? 'Open sidebar' : 'Collapse sidebar'"
                            class="inline-flex items-center justify-center rounded-md border border-gray-300 bg-white p-1.5 text-gray-600 hover:text-indigo-600 hover:border-indigo-600 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                            <ChevronDoubleLeftIcon v-if="!isNavCollapsed" class="size-4" />
                            <ChevronDoubleLeftIcon v-else class="size-4 rotate-180" />
                        </button>
                    </li>
                    <li role="separator" aria-hidden="true" class="h-5 border-l border-gray-400 mx-2"></li>
                    <li v-for="(page, i) in pages" :key="page.name">
                        <div class="flex items-center">
                            <ChevronRightIcon v-if="i > 0" class="size-5 shrink-0 text-gray-400" aria-hidden="true" />
                            <a :href="page.href" class="ml-4 text-sm font-medium text-gray-500 hover:text-gray-700"
                                :aria-current="page.current ? 'page' : undefined">{{ page.name }}</a>
                        </div>
                    </li>
                </ol>
            </nav>

            <!-- Content wrapper (everything BELOW breadcrumbs) -->
            <div class="flex-1 shadow md:rounded-md text-gray-600 bg-gray-50 px-4 py-6 md:p-6">
                <!-- inject any child content -->
                <slot :selected-menu-option="selectedMenuOption"></slot>
            </div>
        </div>
        </main>
        </div>

        <!-- Global notifications slot (optional) -->
        <slot name="overlays" />
    </MainLayout>
</template>

<script setup>
import { ref, onMounted, onUnmounted, watch } from 'vue'
import MainLayout from '../Layouts/MainLayout.vue'
import { Disclosure, DisclosureButton, DisclosurePanel } from '@headlessui/vue'
import { ChevronRightIcon } from '@heroicons/vue/20/solid'
import {
    Cog6ToothIcon,
    ChevronDoubleLeftIcon,
} from '@heroicons/vue/24/outline'


const props = defineProps({
    title: { type: String, default: '' },
    navigation: { type: Object, default: () => [] },
    pages: { type: Object, default: () => [] },
    initialMenuOption: String,
    headerIcon: { type: [Object, Function, String], default: null }, // Vue component

})

const emit = defineEmits(['updateSelectedMenuOption'])

const isNavCollapsed = ref(false)
const selectedMenuOption = ref(null)


watch(() => props.initialMenuOption, () => {
    selectedMenuOption.value = props.initialMenuOption
    emit('updateSelectedMenuOption', selectedMenuOption.value)
}, { immediate: true })

const toggleNav = () => { isNavCollapsed.value = !isNavCollapsed.value }
const isActive = (key) => selectedMenuOption.value === key
const select = (key) => {
    selectedMenuOption.value = key
    emit('updateSelectedMenuOption', key)
}
const parentHasActiveChild = (item) =>
    Array.isArray(item.children) && item.children.some((c) => isActive(c.key))

const checkScreenSize = () => { isNavCollapsed.value = window.innerWidth < 768 }

onMounted(() => {
    checkScreenSize()
    window.addEventListener('resize', checkScreenSize)
})

onUnmounted(() => window.removeEventListener('resize', checkScreenSize))
</script>