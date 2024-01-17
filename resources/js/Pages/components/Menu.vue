
<template>
    <Disclosure as="nav" style="background: linear-gradient(180deg,#6379c3,#546ee5)">
        <div class="mx-auto px-1 sm:px-6 lg:px-8">
            <div class="relative flex h-16 items-center justify-between">
                <div class="absolute inset-y-0 left-0 flex items-center sm:hidden">
                    <!-- Mobile menu button-->
                    <DisclosureButton
                        class="relative inline-flex items-center justify-center rounded-md p-2 text-gray-400 hover:bg-gray-700 hover:text-white focus:outline-none focus:ring-2 focus:ring-inset focus:ring-white">
                        <span class="absolute -inset-0.5" />
                        <span class="sr-only">Open main menu</span>
                        <Bars3Icon v-if="!open" class="block h-6 w-6" aria-hidden="true" />
                        <XMarkIcon v-else class="block h-6 w-6" aria-hidden="true" />
                    </DisclosureButton>
                </div>
                <div class="flex flex-1 items-center justify-center sm:items-stretch sm:justify-start">
                    <div class="flex flex-shrink-0 items-center">
                        <a href="/dashboard"><img class="h-10 w-auto" :src="logoUrl" /></a>

                    </div>
                    <div class="hidden sm:ml-6 sm:flex sm:space-x-4 sm:items-center font-nunito">
                        <div class="flex space-x-4">
                            <div v-for="item in menus" :key="menus.menu_item_id">
                                <Menu as="div" class="relative inline-block text-left group">
                                    <MenuButton
                                        class="inline-flex bg-transparent text-blue-50 text-bold text-opacity-50 border-none hover:text-opacity-75 cursor-pointer">

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
                                            class="absolute mt-1 shadow-xl bg-white ring-1 ring-black ring-opacity-5 focus:outline-none z-10">
                                            <div v-for="child in item.child_menu" :key="child.menu_item_uuid">
                                                <MenuItem v-slot="{ active }">
                                                <a :href="child.menu_item_link"
                                                    :class="[active ? 'bg-gray-100' : '', 'block px-5 py-2 text-sm text-gray-600 whitespace-nowrap cursor-pointer no-underline']">
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
                </div>
                <div
                    class="flex justify-end text-blue-50 text-bold text-opacity-50 border-none hover:text-opacity-75 cursor-pointer">
                    <a @click="openDomainPanel">{{ selectedDomain }}</a>

                </div>
            </div>
        </div>

    </Disclosure>

    <TransitionRoot as="template" :show="isDomainPanelVisible">
        <Dialog as="div" class="relative z-10" :initialFocus="searchFieldRef" @close="isDomainPanelVisible = false">
            <div class="fixed inset-0" />
            <div class="fixed inset-0 overflow-hidden">
                <div class="absolute inset-0 overflow-hidden">
                    <div class="pointer-events-none fixed inset-y-0 right-0 flex max-w-full pl-10">
                        <TransitionChild as="template" enter="transform transition ease-in-out duration-500 sm:duration-700"
                            enter-from="translate-x-full" enter-to="translate-x-0"
                            leave="transform transition ease-in-out duration-500 sm:duration-700" leave-from="translate-x-0"
                            leave-to="translate-x-full">
                            <DialogPanel class="pointer-events-auto w-screen max-w-md">
                                <div class="flex h-full flex-col overflow-y-scroll bg-white py-6 shadow-xl">
                                    <div class="px-4 sm:px-6">
                                        <div class="flex items-start justify-between">
                                            <DialogTitle class="text-base font-semibold leading-6 text-gray-900">Select
                                                company
                                            </DialogTitle>
                                            <div class="ml-3 flex h-7 items-center">
                                                <button type="button"
                                                    class="relative rounded-md bg-white text-gray-400 hover:text-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
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
                                        <input type="text" v-model="searchQuery" placeholder="Search ..." ref="searchFieldRef"
                                            class="mt-2 mb-4 w-full rounded-md border-gray-300 shadow-sm" />
                                    </div>
                                    <!-- End SEARCH BUTTON -->

                                    <div class="relative mt-6 flex-1 px-4 sm:px-6">
                                        <div v-if="filteredDomains && filteredDomains.length > 0" id="domainSearchList">
                                            <div v-for="domain in filteredDomains" :key="domain.domain_uuid">

                                                <a href="#" @click.prevent="selectDomain(domain.domain_uuid)"
                                                    class="cursor-pointer no-underline">

                                                    <div class="flex flex-col p-2 border-b border-gray-200 "
                                                        :class="selectedDomainUuid === domain.domain_uuid ? 'bg-blue-500 text-white' : 'bg-white text-gray-700 hover:bg-gray-300'">
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


import { Disclosure, DisclosureButton, DisclosurePanel, Menu, MenuButton, MenuItem, MenuItems } from '@headlessui/vue'
import { Bars3Icon, XMarkIcon } from '@heroicons/vue/24/outline'
import { ChevronDownIcon, ChevronUpIcon } from '@heroicons/vue/20/solid'
import { Dialog, DialogPanel, DialogTitle, TransitionChild, TransitionRoot } from '@headlessui/vue'


const props = defineProps({
    menus: Array,
    domainSelectPermission: Boolean,
    selectedDomain: String,
    selectedDomainUuid: String,
    domains: Array
});


const isDomainPanelVisible = ref(false);

const openDomainPanel = () => {
    isDomainPanelVisible.value = true;
};

const searchFieldRef = ref(null);

const searchQuery = ref('');

const logoUrl = ref('/storage/logo.png');

const selectDomain = async (domainUuid) => {

    try {
        const response = await axios.post('/domains/switch', {
            domain_uuid: domainUuid
        });
        window.location.href = response.data.redirectUrl;
        // Handle successful response
    } catch (error) {
        console.error(error);
        // Handle error
    }
};

const filteredDomains = computed(() => {
    if (!searchQuery.value) {
        return props.domains;
    }
    return props.domains.filter(domain => 
        domain.domain_description.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
        domain.domain_name.toLowerCase().includes(searchQuery.value.toLowerCase())
    );
});


</script>
