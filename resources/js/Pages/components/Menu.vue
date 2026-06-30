<template>
    <Disclosure as="nav" class="bg-surface shadow" v-slot="{ open }">
        <div class="mx-auto max-w-9xl px-4 sm:px-6 lg:px-8">
            <div class="flex h-16 justify-between">
                <div class="flex">
                    <div class="-ml-2 mr-2 flex items-center lg:hidden">
                        <!-- Mobile menu button -->
                        <DisclosureButton
                            class="relative inline-flex items-center justify-center rounded-md p-2 text-subtle hover:bg-surface-3 hover:text-muted focus:outline-none focus:ring-2 focus:ring-inset focus:ring-focus">
                            <span class="absolute -inset-0.5" />
                            <span class="sr-only">Open main menu</span>
                            <Bars3Icon v-if="!open" class="block h-6 w-6" aria-hidden="true" />
                            <XMarkIcon v-else class="block h-6 w-6" aria-hidden="true" />
                        </DisclosureButton>
                    </div>
                    <div class="flex flex-shrink-0 items-center">
                        <a href="/dashboard"><img class="h-10 w-auto" :src="logoUrl" /></a>
                    </div>
                    <div class="hidden lg:ml-6 lg:flex lg:space-x-4">

                        <div v-for="item in visibleMenus" :key="page.props.menus.menu_item_id"
                            class="inline-flex items-center">
                            <Menu as="div" class="">
                                <MenuButton
                                    class="inline-flex  border-none text-sm font-medium text-muted hover:text-body cursor-pointer">

                                    <div class="font-nunito text-sm">{{ item.menu_item_title }}</div>
                                    <ChevronDownIcon class="h-5 w-5" />
                                </MenuButton>

                                <transition enter-active-class="transition ease-out duration-100"
                                    enter-from-class="transform opacity-0 scale-95"
                                    enter-to-class="transform opacity-100 scale-100"
                                    leave-active-class="transition ease-in duration-75"
                                    leave-from-class="transform opacity-100 scale-100"
                                    leave-to-class="transform opacity-0 scale-95">
                                    <MenuItems
                                        class="absolute mt-1 shadow-xl bg-surface ring-1 ring-black/5 dark:ring-white/10 focus:outline-none z-20">
                                        <div v-for="child in item.child_menu" :key="child.menu_item_uuid">
                                            <MenuItem v-slot="{ active }">
                                                <a :href="child.menu_item_link"
                                                    :class="[active ? 'bg-surface-3' : '', 'block px-5 py-2 text-sm text-body whitespace-nowrap cursor-pointer no-underline']">
                                                    {{ child.menu_item_title }}
                                                </a>
                                            </MenuItem>
                                        </div>
                                    </MenuItems>
                                </transition>
                            </Menu>
                        </div>
                    </div>
                </div>
                <div class="flex items-center gap-x-1">
                    <!-- Dark/light mode toggle -->
                    <button type="button" @click="toggleTheme"
                        :title="isDark ? 'Switch to light mode' : 'Switch to dark mode'"
                        class="inline-flex items-center justify-center rounded-md p-2 text-muted hover:text-body focus:outline-none focus:ring-2 focus:ring-focus cursor-pointer">
                        <span class="sr-only">Toggle dark mode</span>
                        <SunIcon v-if="isDark" class="h-5 w-5" aria-hidden="true" />
                        <MoonIcon v-else class="h-5 w-5" aria-hidden="true" />
                    </button>

                    <!-- User menu -->
                    <Menu as="div" class="relative flex-shrink-0">
                        <MenuButton
                            class="inline-flex items-center gap-x-1.5 rounded-md px-3 py-2 text-sm font-medium text-muted hover:text-body cursor-pointer">
                            <UserCircleIcon class="h-6 w-6 text-subtle" />
                            <span class="hidden sm:block">{{ userName }}</span>
                            <ChevronDownIcon class="h-5 w-5" />
                        </MenuButton>

                        <transition enter-active-class="transition ease-out duration-100"
                            enter-from-class="transform opacity-0 scale-95"
                            enter-to-class="transform opacity-100 scale-100"
                            leave-active-class="transition ease-in duration-75"
                            leave-from-class="transform opacity-100 scale-100"
                            leave-to-class="transform opacity-0 scale-95">
                            <MenuItems
                                class="absolute right-0 mt-1 w-56 origin-top-right shadow-xl bg-surface ring-1 ring-black/5 dark:ring-white/10 focus:outline-none z-20">
                                <div class="px-4 py-3 border-b border-default">
                                    <p class="text-xs text-subtle">Signed in as</p>
                                    <p class="text-sm font-medium text-body truncate">{{ userEmail }}</p>
                                </div>
                                <MenuItem v-slot="{ active }">
                                    <a href="/logout"
                                        :class="[active ? 'bg-surface-3' : '', 'flex items-center gap-x-2 px-4 py-2 text-sm text-body whitespace-nowrap cursor-pointer no-underline']">
                                        <ArrowRightOnRectangleIcon class="h-5 w-5 text-subtle" />
                                        Logout
                                    </a>
                                </MenuItem>
                            </MenuItems>
                        </transition>
                    </Menu>

                    <div class="flex-shrink-0">
                        <button v-if="page.props.domainSelectPermission" type="button" @click="openDomainPanel"
                            class="relative inline-flex items-center gap-x-1.5 rounded-md  px-3 py-2 text-sm font-medium text-muted hover:text-body cursor-pointer ">
                            <span class="hidden sm:block lg:hidden xl:block">{{ page.props.selectedDomain }}</span>
                            <!-- Hide text on small screens -->

                            <svg class="h-5 w-5 sm:hidden lg:block xl:hidden" xmlns="http://www.w3.org/2000/svg"
                                viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor"
                                stroke-linecap="round" stroke-linejoin="round" stroke-width="2">
                                <circle cx="12" cy="12" r="10" />
                                <line x1="2" x2="22" y1="12" y2="12" />
                                <path
                                    d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z" />
                            </svg>

                        </button>
                    </div>

                </div>
            </div>
        </div>

        <DisclosurePanel class="lg:hidden">
            <nav class="flex flex-1 flex-col">
                <ul role="list" class="flex flex-1 flex-col gap-y-7 mx-3">
                    <li>
                        <ul role="list" class="-mx-2 space-y-1">
                            <li v-for="item in visibleMenus" :key="page.props.menus.menu_item_id">
                                <a v-if="!item.child_menu" :href="item.href"
                                    :class="[item.current ? 'bg-surface-2' : 'hover:bg-surface-2', 'block rounded-md py-2 pr-2 pl-10 text-sm leading-6 font-semibold text-body']">{{
                                    item.menu_item_title }}</a>
                                <Disclosure as="div" v-else v-slot="{ open }">
                                    <DisclosureButton
                                        :class="'hover:bg-surface-2 flex items-center w-full text-left rounded-md p-2 gap-x-3 text-sm leading-6 font-semibold text-body'">
                                        <ChevronRightIcon
                                            :class="[open ? 'rotate-90 text-muted' : 'text-subtle', 'h-5 w-5 shrink-0']"
                                            aria-hidden="true" />
                                        {{ item.menu_item_title }}
                                    </DisclosureButton>
                                    <DisclosurePanel as="ul" class="mt-1 px-2">
                            <li v-for="child in item.child_menu" :key="child.menu_item_uuid">
                                <DisclosureButton as="a" :href="child.menu_item_link"
                                    :class="'hover:bg-surface-2 block rounded-md py-2 pr-2 pl-9 text-sm leading-6 text-body'">
                                    {{ child.menu_item_title }}</DisclosureButton>
                            </li>
        </DisclosurePanel>
    </Disclosure>
    </li>
    </ul>
    </li>

    </ul>
    </nav>

    </DisclosurePanel>
    </Disclosure>

    <TransitionRoot as="template" :show="isDomainPanelVisible">
        <Dialog as="div" class="relative z-20" :initialFocus="searchFieldRef" @close="isDomainPanelVisible = false">
            <div class="fixed inset-0" />
            <div class="fixed inset-0 overflow-hidden">
                <div class="absolute inset-0 overflow-hidden">
                    <div class="pointer-events-none fixed inset-y-0 right-0 flex max-w-full pl-10">
                        <TransitionChild as="template"
                            enter="transform transition ease-in-out duration-500 sm:duration-700"
                            enter-from="translate-x-full" enter-to="translate-x-0"
                            leave="transform transition ease-in-out duration-500 sm:duration-700"
                            leave-from="translate-x-0" leave-to="translate-x-full">
                            <DialogPanel class="pointer-events-auto w-screen max-w-md">
                                <div class="flex h-full flex-col overflow-y-scroll bg-surface py-6 shadow-xl">
                                    <div class="px-4 sm:px-6">
                                        <div class="flex items-start justify-between">
                                            <DialogTitle class="text-base font-semibold leading-6 text-heading">Select
                                                account
                                            </DialogTitle>
                                            <div class="ml-3 flex h-7 items-center">
                                                <button type="button"
                                                    class="relative rounded-md bg-surface text-subtle hover:text-muted focus:outline-none focus:ring-2 focus:ring-focus focus:ring-offset-2"
                                                    @click="isDomainPanelVisible = false">
                                                    <span class="absolute -inset-2.5" />
                                                    <span class="sr-only">Close panel</span>
                                                    <XMarkIcon class="h-6 w-6" aria-hidden="true" />
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                    <!-- SEARCH BUTTON -->
                                    <div class="relative mt-6 px-4 sm:px-6">
                                        <input type="text" v-model="searchQuery" placeholder="Search ..."
                                            ref="searchFieldRef"
                                            class="mt-2 mb-4 w-full rounded-md border-strong shadow-sm" />
                                    </div>
                                    <!-- End SEARCH BUTTON -->

                                    <div class="relative mt-6 flex-1 px-4 sm:px-6">
                                        <div v-if="filteredDomains && filteredDomains.length > 0" id="domainSearchList">
                                            <div v-for="domain in filteredDomains" :key="domain.domain_uuid">

                                                <a href="#" @click.prevent="selectDomain(domain.domain_uuid)"
                                                    class="cursor-pointer no-underline">

                                                    <div class="flex flex-col p-2 border-b border-default "
                                                        :class="page.props.selectedDomainUuid === domain.domain_uuid ? 'bg-accent text-on-accent' : 'bg-surface text-body hover:bg-surface-3'">
                                                        <div class="text-base font-semibold mb-0">{{
                                                            domain.domain_description }}</div>
                                                        <div class="text-xs text-muted">{{ domain.domain_name }}</div>
                                                    </div>
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </DialogPanel>
                        </TransitionChild>
                    </div>
                </div>
            </div>
        </Dialog>
    </TransitionRoot>
</template>

<script setup>
import { ref, computed } from 'vue';
import { usePage } from '@inertiajs/vue3'

import { Disclosure, DisclosureButton, DisclosurePanel, Menu, MenuButton, MenuItem, MenuItems } from '@headlessui/vue'
import { Bars3Icon, XMarkIcon, UserCircleIcon, ArrowRightOnRectangleIcon, SunIcon, MoonIcon } from '@heroicons/vue/24/outline'
import { ChevronDownIcon, ChevronRightIcon } from '@heroicons/vue/20/solid'
import { Dialog, DialogPanel, DialogTitle, TransitionChild, TransitionRoot, } from '@headlessui/vue'
import { useTheme } from '../../composables/useTheme'

const page = usePage()

// Dark/light mode toggle
const { isDark, toggle: toggleTheme } = useTheme()

// Logged-in user info (shown in the top-right user menu)
const userName = computed(() => page.props.auth?.user?.name ?? '')
const userEmail = computed(() => page.props.auth?.user?.email ?? '')

// Top navigation menus, excluding the legacy "Home" menu.
// Its only useful items were Dashboard (now reachable via the logo) and
// Logout (now in the user menu above).
const visibleMenus = computed(() =>
    (page.props.menus ?? []).filter(item => item.menu_item_title !== 'Home')
)

const isDomainPanelVisible = ref(false);

const openDomainPanel = () => {
    isDomainPanelVisible.value = true;
};

const searchFieldRef = ref(null);

const searchQuery = ref('');

const logoUrl = ref('/storage/logo.png');

const emit = defineEmits(['reset-filters']);

const selectDomain = async (domainUuid) => {
    try {
        const response = await axios.post('/domains/switch', {
            domain_uuid: domainUuid,
            _token: page.props.csrf_token,
        });
        emit('reset-filters');

        window.location.href = response.data.redirectUrl;
        // Handle successful response
    } catch (error) {
        console.error(error);
        // Handle error
    }
};

const filteredDomains = computed(() => {
    if (!searchQuery.value) {
        return page.props.domains;
    }
    return page.props.domains.filter(domain =>
        domain.domain_description.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
        domain.domain_name.toLowerCase().includes(searchQuery.value.toLowerCase())
    );
});


</script>
