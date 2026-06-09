<template>
    <TransitionRoot as="template" :show="show">
        <Dialog as="div" class="relative z-10" @close="emit('close')">
            <TransitionChild as="template" enter="ease-out duration-200" enter-from="opacity-0" enter-to="opacity-100"
                leave="ease-in duration-150" leave-from="opacity-100" leave-to="opacity-0">
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" />
            </TransitionChild>

            <div class="fixed inset-0 z-10 w-screen overflow-y-auto">
                <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
                    <TransitionChild as="template" enter="ease-out duration-200"
                        enter-from="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                        enter-to="opacity-100 translate-y-0 sm:scale-100" leave="ease-in duration-150"
                        leave-from="opacity-100 translate-y-0 sm:scale-100"
                        leave-to="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95">
                        <DialogPanel
                            class="relative flex max-h-[85vh] w-full transform flex-col overflow-hidden rounded-2xl bg-white text-left shadow-2xl ring-1 ring-black/5 transition-all sm:my-8 sm:max-w-3xl">
                            <div class="flex items-start justify-between gap-4 border-b border-gray-100 bg-gradient-to-br from-indigo-50/80 to-white px-5 py-5 sm:px-6">
                                <div class="flex min-w-0 items-center gap-3">
                                    <div class="flex h-11 w-11 flex-none items-center justify-center rounded-xl bg-indigo-600/10 ring-1 ring-inset ring-indigo-600/20">
                                        <UsersIcon class="h-6 w-6 text-indigo-600" aria-hidden="true" />
                                    </div>
                                    <div class="min-w-0">
                                        <DialogTitle as="h3" class="truncate text-base font-semibold leading-6 text-gray-900">
                                            {{ group?.group_name }}
                                        </DialogTitle>
                                        <p class="mt-0.5 flex items-center gap-1.5 text-sm text-gray-500">
                                            <span class="inline-flex items-center rounded-full bg-indigo-50 px-2 py-0.5 text-xs font-medium text-indigo-700 ring-1 ring-inset ring-indigo-600/10">
                                                {{ members.length }}
                                            </span>
                                            member{{ members.length === 1 ? '' : 's' }}
                                        </p>
                                    </div>
                                </div>
                                <button type="button"
                                    class="flex-none rounded-lg p-1 text-gray-400 transition hover:bg-gray-100 hover:text-gray-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                                    @click="emit('close')">
                                    <span class="sr-only">Close</span>
                                    <XMarkIcon class="h-6 w-6" aria-hidden="true" />
                                </button>
                            </div>

                            <div class="flex min-h-0 flex-1 flex-col gap-5 overflow-y-auto px-5 py-5 sm:px-6">
                                <div v-if="permissions.add && availableUsers.length"
                                    class="rounded-xl bg-gray-50 p-4 ring-1 ring-inset ring-gray-200">
                                    <Vueform :key="addFormKey" ref="addMemberForm$" :endpoint="submitAddMember"
                                        @success="handleAddSuccess" @error="handleAddError" @response="handleAddResponse"
                                        :display-errors="false" size="sm">
                                        <SelectElement name="user_uuid" :items="availableUsers" :search="true"
                                            :native="false" input-type="search" autocomplete="off" label="Add member"
                                            placeholder="Select a user to add" :floating="false" :strict="false"
                                            :columns="{ sm: { container: 9 } }" />

                                        <ButtonElement name="submit" label="&nbsp;" button-label="Add" :submits="true" :loading="saving"
                                            align="right" :columns="{ sm: { container: 3 } }" />
                                    </Vueform>
                                </div>

                                <p v-else-if="permissions.add"
                                    class="rounded-xl bg-gray-50 px-4 py-3 text-sm text-gray-500 ring-1 ring-inset ring-gray-200">
                                    All available users are already members of this group.
                                </p>

                                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                    <div class="relative sm:w-72">
                                        <MagnifyingGlassIcon
                                            class="pointer-events-none absolute inset-y-0 left-3 my-auto h-4 w-4 text-gray-400" />
                                        <input v-model="search" type="text" placeholder="Search members"
                                            class="block w-full rounded-lg border-0 py-2 pl-9 text-sm text-gray-900 ring-1 ring-inset ring-gray-300 transition placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600" />
                                    </div>

                                    <Transition enter-active-class="transition duration-150 ease-out"
                                        enter-from-class="opacity-0 scale-95" enter-to-class="opacity-100 scale-100"
                                        leave-active-class="transition duration-100 ease-in"
                                        leave-from-class="opacity-100 scale-100" leave-to-class="opacity-0 scale-95">
                                        <button v-if="permissions.delete && selectedItems.length" type="button"
                                            class="inline-flex items-center justify-center gap-1.5 rounded-lg bg-white px-3 py-2 text-sm font-medium text-red-700 shadow-sm ring-1 ring-inset ring-red-200 transition hover:bg-red-50"
                                            @click="showRemoveConfirmation = true">
                                            <TrashIcon class="h-4 w-4" />
                                            Remove
                                            <span class="inline-flex items-center rounded-full bg-red-100 px-1.5 text-xs font-semibold">{{ selectedItems.length }}</span>
                                        </button>
                                    </Transition>
                                </div>

                                <div class="overflow-hidden rounded-xl ring-1 ring-gray-200">
                                    <div v-if="loading" class="px-4 py-16">
                                        <Loading :show="true" :absolute="false" />
                                    </div>

                                    <template v-else-if="filteredMembers.length">
                                        <div v-if="permissions.delete"
                                            class="flex items-center gap-3 border-b border-gray-100 bg-gray-50 px-4 py-2.5">
                                            <input type="checkbox" :checked="allVisibleSelected"
                                                class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-600"
                                                @change="toggleSelectAllVisible" />
                                            <span class="text-xs font-semibold uppercase tracking-wide text-gray-500">
                                                {{ selectedItems.length ? `${selectedItems.length} selected` : 'Select all' }}
                                            </span>
                                        </div>

                                        <ul role="list" class="divide-y divide-gray-100">
                                            <li v-for="member in filteredMembers" :key="member.user_group_uuid"
                                                class="flex items-center gap-3 px-4 py-3 transition-colors"
                                                :class="selectedItems.includes(member.user_group_uuid) ? 'bg-indigo-50/60' : 'hover:bg-gray-50'">
                                                <input v-if="permissions.delete" v-model="selectedItems" type="checkbox"
                                                    :value="member.user_group_uuid"
                                                    class="h-4 w-4 flex-none rounded border-gray-300 text-indigo-600 focus:ring-indigo-600" />

                                                <span class="flex h-9 w-9 flex-none items-center justify-center rounded-full bg-gradient-to-br from-indigo-500 to-indigo-600 text-xs font-semibold text-white">
                                                    {{ initials(member.username) }}
                                                </span>

                                                <div class="min-w-0 flex-1">
                                                    <p class="truncate text-sm font-medium text-gray-900">{{ member.username }}</p>
                                                    <p v-if="member.user_email" class="truncate text-xs text-gray-500">{{ member.user_email }}</p>
                                                </div>

                                                <span v-if="permissions.show_domain"
                                                    class="flex-none rounded-md bg-gray-100 px-2 py-1 text-xs font-medium text-gray-600">
                                                    {{ member.domain_name || 'Global' }}
                                                </span>
                                            </li>
                                        </ul>
                                    </template>

                                    <div v-else class="px-4 py-16 text-center">
                                        <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-gray-100">
                                            <UsersIcon class="h-6 w-6 text-gray-400" />
                                        </div>
                                        <p class="mt-3 text-sm font-medium text-gray-900">No members found</p>
                                        <p class="mt-1 text-xs text-gray-500">Add a user to this group or adjust your search.</p>
                                    </div>
                                </div>
                            </div>
                        </DialogPanel>
                    </TransitionChild>
                </div>
            </div>
        </Dialog>
    </TransitionRoot>

    <ConfirmationModal :show="showRemoveConfirmation" @close="showRemoveConfirmation = false"
        @confirm="deleteSelectedMembers" header="Remove Members"
        text="Remove the selected users from this group?" confirm-button-label="Remove"
        cancel-button-label="Cancel" />
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import axios from 'axios'
import { Dialog, DialogPanel, DialogTitle, TransitionChild, TransitionRoot } from '@headlessui/vue'
import { MagnifyingGlassIcon, TrashIcon, UsersIcon, XMarkIcon } from '@heroicons/vue/24/outline'
import Loading from '../general/Loading.vue'
import ConfirmationModal from './ConfirmationModal.vue'

const emit = defineEmits(['close', 'error', 'success', 'count-changed'])

const props = defineProps({
    show: Boolean,
    group: Object,
    routes: Object,
})

const addMemberForm$ = ref(null)
const addFormKey = ref(0)
const loading = ref(false)
const saving = ref(false)
const members = ref([])
const availableUsers = ref([])
const permissions = ref({ add: false, delete: false, show_domain: false })
const selectedItems = ref([])
const search = ref('')
const showRemoveConfirmation = ref(false)

const filteredMembers = computed(() => {
    const needle = search.value.trim().toLowerCase()
    if (!needle) return members.value

    return members.value.filter(member => {
        return [member.username, member.user_email, member.domain_name]
            .filter(Boolean)
            .join(' ')
            .toLowerCase()
            .includes(needle)
    })
})

const initials = (name) => {
    if (!name) return '?'

    return name.trim()
        .split(/\s+/)
        .slice(0, 2)
        .map(part => part.charAt(0).toUpperCase())
        .join('')
}

const allVisibleSelected = computed(() => {
    return filteredMembers.value.length > 0
        && filteredMembers.value.every(member => selectedItems.value.includes(member.user_group_uuid))
})

watch(() => props.show, (show) => {
    if (!show) {
        resetState()
        return
    }

    getData()
})

watch(() => props.group?.group_uuid, () => {
    if (props.show) getData()
})

const routeFor = (template) => {
    return template?.replace('__group_uuid__', props.group?.group_uuid || '')
}

const getData = () => {
    if (!props.group?.group_uuid) return

    loading.value = true

    axios.get(routeFor(props.routes.members_data))
        .then(response => {
            members.value = response.data.members || []
            availableUsers.value = response.data.available_users || []
            permissions.value = response.data.permissions || permissions.value
            selectedItems.value = []
            addFormKey.value += 1
            emit('count-changed', { group_uuid: props.group.group_uuid, count: members.value.length })
        })
        .catch(error => {
            emit('error', error)
        })
        .finally(() => {
            loading.value = false
        })
}

const submitAddMember = async (FormData, form$) => {
    saving.value = true

    return await form$.$vueform.services.axios.post(routeFor(props.routes.members_store), form$.requestData)
}

const handleAddSuccess = (response) => {
    saving.value = false
    emit('success', 'success', response.data.messages)
    getData()
}

const handleAddError = (error) => {
    saving.value = false
    emit('error', error)
}

function clearErrorsRecursive(el$) {
    el$.messageBag?.clear()

    if (el$.children$) {
        Object.values(el$.children$).forEach(childEl$ => {
            clearErrorsRecursive(childEl$)
        })
    }
}

const handleAddResponse = (response, form$) => {
    Object.values(form$.elements$).forEach(el$ => {
        clearErrorsRecursive(el$)
    })

    if (response.data.errors) {
        Object.keys(response.data.errors).forEach((elName) => {
            if (form$.el$(elName)) {
                form$.el$(elName).messageBag.append(response.data.errors[elName][0])
            }
        })
    }
}

const deleteSelectedMembers = () => {
    if (!selectedItems.value.length) return

    axios.post(routeFor(props.routes.members_delete), { items: selectedItems.value })
        .then(response => {
            showRemoveConfirmation.value = false
            emit('success', 'success', response.data.messages)
            getData()
        })
        .catch(error => {
            showRemoveConfirmation.value = false
            emit('error', error)
        })
}

const toggleSelectAllVisible = () => {
    const visibleIds = filteredMembers.value.map(member => member.user_group_uuid)

    if (allVisibleSelected.value) {
        selectedItems.value = selectedItems.value.filter(item => !visibleIds.includes(item))
        return
    }

    selectedItems.value = Array.from(new Set([...selectedItems.value, ...visibleIds]))
}

const resetState = () => {
    members.value = []
    availableUsers.value = []
    selectedItems.value = []
    addFormKey.value += 1
    search.value = ''
    showRemoveConfirmation.value = false
}
</script>
