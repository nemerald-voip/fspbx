<template>
    <!-- FormChildModal is the project's shell for a dialog nested inside a form dialog. -->
    <FormChildModal :show="show" :header="$t('Phone book')" :loading="false" @close="emit('close')">
        <div class="space-y-4">
            <!-- Search -->
            <div class="relative">
                <MagnifyingGlassIcon
                    class="pointer-events-none absolute inset-y-0 left-3 my-auto h-4 w-4 text-gray-400" />
                <input ref="searchInput" v-model="search" type="text" autocomplete="off"
                    :placeholder="$t('Search by name, company, or number')"
                    class="block w-full rounded-lg border-0 py-2 pl-9 pr-9 text-sm text-gray-900 ring-1 ring-inset ring-gray-300 transition placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600"
                    @keydown.down.prevent="moveActive(1)" @keydown.up.prevent="moveActive(-1)"
                    @keydown.enter.prevent="chooseActive" />
                <!-- Searching shows a quiet spinner here, so the list below can stay put. -->
                <svg v-if="loading" class="absolute inset-y-0 right-3 my-auto h-4 w-4 animate-spin text-gray-400"
                    xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                    <path class="opacity-75" fill="currentColor"
                        d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                </svg>
                <button v-else-if="search" type="button"
                    class="absolute inset-y-0 right-2 my-auto h-6 w-6 rounded-full text-gray-400 transition hover:bg-gray-100 hover:text-gray-600"
                    @click="search = ''">
                    <span class="sr-only">{{ $t('Clear') }}</span>
                    <XMarkIcon class="mx-auto h-4 w-4" aria-hidden="true" />
                </button>
            </div>

            <!-- Only part of the phone book came back: say so rather than look complete. -->
            <div v-if="truncated"
                class="flex items-start gap-2 rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-800 ring-1 ring-inset ring-amber-600/20">
                <InformationCircleIcon class="mt-0.5 h-4 w-4 flex-none" aria-hidden="true" />
                <span>
                    {{ search
                        ? $t('Showing the first :limit matches. Keep typing to narrow the list.', { limit })
                        : $t('Showing the first :limit contacts. Search to find the rest.', { limit }) }}
                </span>
            </div>

            <!--
                min-height keeps the panel from resizing as result counts change, and
                the list is only replaced by a spinner on the very first load - later
                searches dim it in place so the modal doesn't appear to reload.
            -->
            <div class="max-h-72 min-h-[12rem] overflow-y-auto rounded-lg ring-1 ring-gray-200"
                :aria-busy="loading">
                <div v-if="loading && !hasLoaded" class="px-4 py-12">
                    <Loading :show="true" :absolute="false" />
                </div>

                <ul v-else-if="contacts.length" role="list"
                    class="divide-y divide-gray-100 transition-opacity" :class="{ 'opacity-50': loading }">
                    <li v-for="(contact, index) in contacts" :key="rowKey(contact)"
                        class="transition-colors"
                        :class="index === activeIndex && !isConfirming(contact) ? 'bg-indigo-50/70' : 'hover:bg-gray-50'">

                        <!-- Deleting removes the contact, so every number it holds goes with it. -->
                        <div v-if="isConfirming(contact)"
                            class="flex flex-wrap items-center justify-between gap-2 bg-red-50/60 px-4 py-3">
                            <p class="text-sm text-gray-700">
                                {{ $t('Remove :name and all of their saved numbers?', { name: contact.name }) }}
                            </p>
                            <div class="flex flex-none items-center gap-2">
                                <button type="button" :disabled="deleting"
                                    class="rounded-lg px-2.5 py-1.5 text-xs font-medium text-gray-600 transition hover:bg-white disabled:opacity-50"
                                    @click="confirmingKey = null">
                                    {{ $t('Cancel') }}
                                </button>
                                <button type="button" :disabled="deleting"
                                    class="rounded-lg bg-red-600 px-2.5 py-1.5 text-xs font-semibold text-white shadow-sm transition hover:bg-red-500 disabled:opacity-50"
                                    @click="remove(contact)">
                                    {{ $t('Remove') }}
                                </button>
                            </div>
                        </div>

                        <div v-else class="flex items-center gap-1 pr-2">
                            <button :ref="el => setRowRef(el, index)" type="button"
                                class="flex min-w-0 flex-1 items-center gap-3 px-4 py-3 text-left"
                                @mousemove="activeIndex = index" @click="choose(contact)">
                                <span
                                    class="flex h-9 w-9 flex-none items-center justify-center rounded-full bg-gradient-to-br from-indigo-500 to-indigo-600 text-xs font-semibold text-white">
                                    {{ initials(contact.name) }}
                                </span>

                                <span class="min-w-0 flex-1">
                                    <span class="block truncate text-sm font-medium text-gray-900">
                                        {{ contact.name }}
                                    </span>
                                    <span v-if="contact.organization" class="block truncate text-xs text-gray-500">
                                        {{ contact.organization }}
                                    </span>
                                </span>

                                <span class="flex flex-none flex-col items-end gap-1">
                                    <span class="text-sm tabular-nums text-gray-700">
                                        {{ contact.number_formatted || contact.value }}
                                    </span>
                                    <span v-if="contact.phone_type"
                                        class="rounded-md px-1.5 py-0.5 text-[11px] font-medium capitalize"
                                        :class="contact.phone_type.toLowerCase() === 'fax'
                                            ? 'bg-teal-50 text-teal-700 ring-1 ring-inset ring-teal-600/20'
                                            : 'bg-gray-100 text-gray-600'">
                                        {{ phoneTypeLabel(contact.phone_type) }}
                                    </span>
                                </span>
                            </button>

                            <button v-if="destroyRoute && contact.contact_uuid" type="button"
                                class="flex-none rounded-lg p-2 text-gray-400 transition hover:bg-red-50 hover:text-red-600"
                                @click="confirmingKey = rowKey(contact)">
                                <span class="sr-only">{{ $t('Remove from phone book') }}</span>
                                <TrashIcon class="h-4 w-4" aria-hidden="true" />
                            </button>
                        </div>
                    </li>
                </ul>

                <div v-else class="px-6 py-12 text-center">
                    <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-gray-100">
                        <BookOpenIcon class="h-6 w-6 text-gray-400" aria-hidden="true" />
                    </div>
                    <p class="mt-3 text-sm font-medium text-gray-900">
                        {{ search ? $t('No contacts found') : $t('Your phone book is empty') }}
                    </p>
                    <p class="mt-1 text-xs text-gray-500">
                        {{ search
                            ? $t('Adjust your search and try again.')
                            : $t('Save a recipient to reuse it on your next fax.') }}
                    </p>
                </div>
            </div>

            <!-- Footer -->
            <div class="flex items-center justify-between gap-3">
                <p class="hidden text-xs text-gray-500 sm:block">
                    {{ $t('Use the arrow keys to browse, Enter to select') }}
                </p>
                <button type="button"
                    class="inline-flex items-center gap-1.5 rounded-lg bg-white px-3 py-2 text-sm font-medium text-indigo-700 shadow-sm ring-1 ring-inset ring-indigo-200 transition hover:bg-indigo-50"
                    @click="emit('create', search)">
                    <UserPlusIcon class="h-4 w-4" aria-hidden="true" />
                    {{ $t('Add new contact') }}
                </button>
            </div>
        </div>
    </FormChildModal>
</template>

<script setup>
import { nextTick, ref, watch } from 'vue'
import axios from 'axios'
import { trans } from '@i18n'
import { BookOpenIcon, InformationCircleIcon, MagnifyingGlassIcon, TrashIcon, UserPlusIcon, XMarkIcon } from '@heroicons/vue/24/outline'
import FormChildModal from '../FormChildModal.vue'
import Loading from '../general/Loading.vue'

const emit = defineEmits(['close', 'select', 'create', 'deleted', 'error'])

const props = defineProps({
    show: Boolean,
    route: String,
    // Contains a ":contact" placeholder, as built by route('contacts.destroy').
    destroyRoute: String,
    channel: {
        type: String,
        default: 'fax',
    },
})

const searchInput = ref(null)
const search = ref('')
const contacts = ref([])
const loading = ref(false)
// True once any response has landed, so only the very first load blanks the list.
const hasLoaded = ref(false)
const truncated = ref(false)
const limit = ref(0)
const activeIndex = ref(-1)
const rowRefs = ref([])
const confirmingKey = ref(null)
const deleting = ref(false)

const rowKey = (contact) => contact.phone_uuid ?? contact.value
const isConfirming = (contact) => confirmingKey.value === rowKey(contact)

// Guards against a slow response overwriting the results of a newer search.
let latestRequest = 0
let searchTimer = null

const setRowRef = (el, index) => {
    rowRefs.value[index] = el
}

const initials = (name) => {
    if (!name) return '?'

    return name.trim()
        .split(/\s+/)
        .slice(0, 2)
        .map(part => part.charAt(0).toUpperCase())
        .join('')
}

const phoneTypeLabel = (type) => {
    const labels = {
        fax: trans('Fax'),
        mobile: trans('Mobile'),
        work: trans('Work'),
    }

    return labels[String(type ?? '').toLowerCase()] ?? String(type ?? '')
}

const getContacts = async () => {
    if (!props.route) return

    const requestId = ++latestRequest
    loading.value = true

    try {
        const response = await axios.get(props.route, {
            params: {
                channel: props.channel,
                query: search.value.trim(),
            },
        })

        if (requestId !== latestRequest) return

        // { options, truncated, limit }; a bare array means a stale cached bundle.
        contacts.value = Array.isArray(response.data)
            ? response.data
            : (response.data?.options ?? [])
        truncated.value = !!response.data?.truncated
        limit.value = response.data?.limit ?? 0
        activeIndex.value = contacts.value.length ? 0 : -1
        rowRefs.value = []
    } catch (error) {
        if (requestId !== latestRequest) return

        contacts.value = []
        truncated.value = false
        activeIndex.value = -1
        emit('error', error)
    } finally {
        if (requestId === latestRequest) {
            loading.value = false
            hasLoaded.value = true
        }
    }
}

const moveActive = (step) => {
    if (!contacts.value.length) return

    const next = activeIndex.value + step
    activeIndex.value = Math.min(Math.max(next, 0), contacts.value.length - 1)

    nextTick(() => rowRefs.value[activeIndex.value]?.scrollIntoView({ block: 'nearest' }))
}

const chooseActive = () => {
    const contact = contacts.value[activeIndex.value]
    if (contact) choose(contact)
}

const choose = (contact) => {
    emit('select', contact)
}

const remove = async (contact) => {
    if (!props.destroyRoute || !contact.contact_uuid || deleting.value) return

    deleting.value = true

    try {
        await axios.delete(props.destroyRoute.replace(':contact', contact.contact_uuid))

        confirmingKey.value = null
        emit('deleted', contact)

        // The contact may have held several numbers, so reload rather than splice.
        await getContacts()
    } catch (error) {
        emit('error', error)
    } finally {
        deleting.value = false
    }
}

watch(search, () => {
    if (!props.show) return

    confirmingKey.value = null
    clearTimeout(searchTimer)
    searchTimer = setTimeout(getContacts, 250)
})

watch(() => props.show, (show) => {
    clearTimeout(searchTimer)
    confirmingKey.value = null

    if (!show) {
        contacts.value = []
        activeIndex.value = -1
        rowRefs.value = []
        search.value = ''
        hasLoaded.value = false
        truncated.value = false
        return
    }

    getContacts()

    // Headless UI focuses the panel's first control on open; take it afterwards.
    nextTick(() => setTimeout(() => searchInput.value?.focus(), 50))
})
</script>
