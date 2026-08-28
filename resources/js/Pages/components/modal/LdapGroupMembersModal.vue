<template>
    <TransitionRoot as="template" :show="show">
        <Dialog as="div" class="relative z-50" @close="emit('close')">
            <TransitionChild as="template" enter="ease-out duration-200" enter-from="opacity-0" enter-to="opacity-100"
                leave="ease-in duration-150" leave-from="opacity-100" leave-to="opacity-0">
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" />
            </TransitionChild>

            <div class="fixed inset-0 z-50 w-screen overflow-y-auto">
                <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
                    <TransitionChild as="template" enter="ease-out duration-200"
                        enter-from="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                        enter-to="opacity-100 translate-y-0 sm:scale-100" leave="ease-in duration-150"
                        leave-from="opacity-100 translate-y-0 sm:scale-100"
                        leave-to="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95">
                        <DialogPanel
                            class="relative flex max-h-[85vh] w-full transform flex-col overflow-hidden rounded-lg bg-white text-left shadow-xl transition-all sm:my-8 sm:max-w-3xl">
                            <div class="flex items-start justify-between gap-4 border-b border-gray-200 px-5 py-4 sm:px-6">
                                <div class="min-w-0">
                                    <DialogTitle as="h3" class="truncate text-base font-semibold leading-6 text-gray-900">
                                        {{ resolvedGroup?.name || group?.name }}
                                    </DialogTitle>
                                    <p class="mt-1 text-sm text-gray-500">
                                        {{ $tChoice(':count member|:count members', displayedCount, { count: displayedCount }) }}
                                    </p>
                                </div>
                                <button type="button"
                                    class="flex-none rounded-md text-gray-400 hover:text-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                                    :aria-label="$t('Close')" @click="emit('close')">
                                    <XMarkIcon class="h-6 w-6" aria-hidden="true" />
                                </button>
                            </div>

                            <div class="min-h-0 flex-1 overflow-y-auto px-5 py-5 sm:px-6">
                                <div v-if="loading" class="space-y-3" aria-live="polite" :aria-label="$t('Loading group members...')">
                                    <div v-for="index in 5" :key="index"
                                        class="grid animate-pulse grid-cols-12 items-center gap-3 rounded-md border border-gray-100 px-3 py-3 motion-reduce:animate-none">
                                        <div class="col-span-5 h-4 rounded bg-gray-200" />
                                        <div class="col-span-4 h-4 rounded bg-gray-100" />
                                        <div class="col-span-3 h-4 rounded bg-gray-100" />
                                    </div>
                                </div>

                                <template v-else>
                                    <div v-if="members.length" class="mb-4 sm:max-w-sm">
                                        <label for="ldap-group-member-search" class="sr-only">{{ $t('Search members') }}</label>
                                        <div class="relative">
                                            <MagnifyingGlassIcon
                                                class="pointer-events-none absolute inset-y-0 left-3 my-auto h-4 w-4 text-gray-400"
                                                aria-hidden="true" />
                                            <input id="ldap-group-member-search" v-model="search" type="search"
                                                :placeholder="$t('Search members')"
                                                class="block w-full rounded-md border-0 py-2 pl-9 pr-3 text-sm text-gray-900 ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600" />
                                        </div>
                                    </div>

                                    <div v-if="filteredMembers.length" class="overflow-x-auto rounded-md border border-gray-200">
                                        <table class="min-w-full divide-y divide-gray-200">
                                            <thead class="bg-gray-50">
                                                <tr class="text-left text-xs font-semibold text-gray-500">
                                                    <th class="px-3 py-2.5">{{ $t('Name') }}</th>
                                                    <th class="px-3 py-2.5">{{ $t('Email') }}</th>
                                                    <th class="px-3 py-2.5">{{ $t('Extension') }}</th>
                                                    <th class="px-3 py-2.5">{{ $t('Status') }}</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-gray-100 bg-white">
                                                <tr v-for="member in filteredMembers" :key="member.directory_user_uuid"
                                                    class="hover:bg-gray-50">
                                                    <td class="whitespace-nowrap px-3 py-3">
                                                        <div class="flex items-center gap-3">
                                                            <span class="flex h-8 w-8 flex-none items-center justify-center rounded-full bg-gray-100 text-xs font-semibold text-gray-600">
                                                                {{ initials(member.name || member.username) }}
                                                            </span>
                                                            <div class="min-w-0">
                                                                <div class="max-w-56 truncate text-sm font-medium text-gray-900" :title="member.name">
                                                                    {{ member.name }}
                                                                </div>
                                                                <div class="max-w-56 truncate text-xs text-gray-500" :title="member.username">
                                                                    {{ member.username }}
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td class="max-w-64 truncate px-3 py-3 text-sm text-gray-600" :title="member.email">
                                                        {{ member.email || '—' }}
                                                    </td>
                                                    <td class="whitespace-nowrap px-3 py-3 text-sm text-gray-600">
                                                        {{ member.extension || '—' }}
                                                    </td>
                                                    <td class="whitespace-nowrap px-3 py-3">
                                                        <span class="inline-flex rounded-md px-2 py-1 text-xs font-medium ring-1 ring-inset"
                                                            :class="member.enabled
                                                                ? 'bg-green-50 text-green-700 ring-green-600/20'
                                                                : 'bg-gray-100 text-gray-600 ring-gray-500/20'">
                                                            {{ member.enabled ? $t('Enabled') : $t('Disabled') }}
                                                        </span>
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>

                                    <div v-else class="rounded-md border border-dashed border-gray-300 px-4 py-12 text-center">
                                        <UsersIcon class="mx-auto h-8 w-8 text-gray-400" aria-hidden="true" />
                                        <p class="mt-3 text-sm font-medium text-gray-900">
                                            {{ members.length ? $t('No matching members') : $t('No members in this group') }}
                                        </p>
                                        <p v-if="members.length" class="mt-1 text-xs text-gray-500">
                                            {{ $t('Adjust your search and try again.') }}
                                        </p>
                                        <p v-else class="mt-1 text-xs text-gray-500">
                                            {{ $t('Membership will appear after the next successful synchronization.') }}
                                        </p>
                                    </div>
                                </template>
                            </div>
                        </DialogPanel>
                    </TransitionChild>
                </div>
            </div>
        </Dialog>
    </TransitionRoot>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import axios from 'axios'
import { Dialog, DialogPanel, DialogTitle, TransitionChild, TransitionRoot } from '@headlessui/vue'
import { MagnifyingGlassIcon, UsersIcon, XMarkIcon } from '@heroicons/vue/24/outline'

const emit = defineEmits(['close', 'error'])
const props = defineProps({
    show: Boolean,
    group: { type: Object, default: null },
})

const loading = ref(false)
const members = ref([])
const resolvedGroup = ref(null)
const search = ref('')

const displayedCount = computed(() => loading.value
    ? Number(props.group?.directory_users_count ?? 0)
    : members.value.length)

const filteredMembers = computed(() => {
    const needle = search.value.trim().toLowerCase()

    if (!needle) return members.value

    return members.value.filter(member => [
        member.name,
        member.username,
        member.email,
        member.extension,
    ].filter(Boolean).join(' ').toLowerCase().includes(needle))
})

const initials = name => String(name || '?')
    .trim()
    .split(/\s+/)
    .slice(0, 2)
    .map(part => part.charAt(0).toUpperCase())
    .join('') || '?'

const reset = () => {
    loading.value = false
    members.value = []
    resolvedGroup.value = null
    search.value = ''
}

const loadMembers = async () => {
    if (!props.group?.routes?.members) return

    loading.value = true
    search.value = ''

    try {
        const response = await axios.get(props.group.routes.members)
        members.value = response.data.members ?? []
        resolvedGroup.value = response.data.group ?? null
    } catch (error) {
        emit('error', error)
    } finally {
        loading.value = false
    }
}

watch(() => props.show, show => {
    if (show) {
        loadMembers()
    } else {
        reset()
    }
})

watch(() => props.group?.directory_group_uuid, () => {
    if (props.show) loadMembers()
})
</script>
