<template>
    <div>
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <Vueform class="min-w-0">
                <StaticElement name="number_translations_title" tag="h4" :content="$t('Number Translations')"
                    :description="$t('Create reusable rules for rewriting phone numbers. Saving or enabling a profile does not automatically change calls. Your dialplan must call the profile and use its result.')" />
            </Vueform>

            <div class="flex shrink-0">
                <button v-if="permissions.number_translation_add" type="button" :disabled="busy"
                    class="rounded-md bg-indigo-600 px-2.5 py-1.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 disabled:cursor-not-allowed disabled:opacity-50"
                    @click="create">
                    {{ $t('Create') }}
                </button>
                <button v-if="permissions.number_translation_edit" type="button" :disabled="syncing || busy"
                    class="ml-2 inline-flex items-center gap-x-1.5 rounded-md bg-white px-2.5 py-1.5 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50 sm:ml-4"
                    @click="sync">
                    <ArrowPathIcon class="h-5 w-5" :class="{ 'animate-spin': syncing }" aria-hidden="true" />
                    {{ syncing ? $t('Syncing...') : $t('Sync') }}
                </button>
            </div>
        </div>

        <details class="mt-2 text-sm text-gray-600">
            <summary class="w-fit cursor-pointer rounded font-medium text-indigo-600 hover:text-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                {{ $t('Show dialplan example') }}
            </summary>
            <div class="mt-3 max-w-3xl space-y-2">
                <p>{{ $t('For example, calling :profile converts :original to :result by removing the leading plus sign.', {
                    profile: 'remove_leading_plus', original: '+442071234567', result: '442071234567',
                }) }}</p>
                <pre class="overflow-x-auto rounded-md bg-white p-3 text-xs leading-6 text-gray-900 ring-1 ring-inset ring-gray-200"><code v-text="dialplanExample"></code></pre>
                <p>{{ $t('Requires :module. The result is stored in :variable; use that variable in the following routing action.', {
                    module: 'mod_translate', variable: '${translated}',
                }) }}</p>
            </div>
        </details>

        <div v-if="syncError" role="alert" class="mt-4 flex items-start gap-3 rounded-md border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
            <ExclamationTriangleIcon class="mt-0.5 h-5 w-5 shrink-0 text-amber-500" aria-hidden="true" />
            <p>{{ syncError }}</p>
        </div>

        <div class="mt-6 flex flex-col sm:flex-row sm:flex-wrap">
            <div class="relative mb-2 min-w-64 focus-within:z-10 sm:mr-4">
                <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                    <MagnifyingGlassIcon class="h-5 w-5 text-gray-400" aria-hidden="true" />
                </div>
                <input v-model="search" type="search" :aria-label="$t('Search number translations')" :placeholder="$t('Search')"
                    class="block w-full rounded-md border-0 py-1.5 pl-10 text-sm leading-6 text-gray-900 ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-blue-600"
                    @keydown.enter="load(1)" />
            </div>
            <div class="mb-2 flex">
                <button type="button"
                    class="rounded-md bg-indigo-600 px-2.5 py-1.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600"
                    @click="load(1)">
                    {{ $t('Search') }}
                </button>
                <button type="button"
                    class="ml-2 rounded-md bg-white px-2.5 py-1.5 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 sm:ml-4"
                    @click="resetSearch">
                    {{ $t('Reset') }}
                </button>
            </div>
        </div>

        <div class="relative mt-4 overflow-hidden rounded-lg bg-white shadow ring-1 ring-black ring-opacity-5">
            <Loading v-if="data.data.length" :show="loading" />
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-300">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-4 py-3.5 text-left text-sm font-semibold text-gray-900">
                                <button type="button" class="flex items-center" @click="sortBy('number_translation_name')">
                                    <span class="mr-2">{{ $t('Name') }}</span>
                                    <component :is="sortIcon('number_translation_name')" v-if="sortIcon('number_translation_name')"
                                        class="h-4 w-4 text-gray-500" aria-hidden="true" />
                                </button>
                            </th>
                            <th scope="col" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900">{{ $t('Rules') }}</th>
                            <th scope="col" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900">
                                <button type="button" class="flex items-center" @click="sortBy('number_translation_enabled')">
                                    <span class="mr-2">{{ $t('Status') }}</span>
                                    <component :is="sortIcon('number_translation_enabled')" v-if="sortIcon('number_translation_enabled')"
                                        class="h-4 w-4 text-gray-500" aria-hidden="true" />
                                </button>
                            </th>
                            <th scope="col" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900">{{ $t('Description') }}</th>
                            <th scope="col" class="px-2 py-3.5"><span class="sr-only">{{ $t('Actions') }}</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white">
                        <tr v-for="row in data.data" :key="row.uuid">
                            <td class="whitespace-nowrap px-4 py-2 text-sm text-gray-500">
                                <button type="button" :disabled="busy"
                                    class="rounded-sm hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                                    @click="open(row)">
                                    {{ row.name }}
                                </button>
                            </td>
                            <td class="whitespace-nowrap px-2 py-2 text-sm text-gray-500">{{ row.rules_count }}</td>
                            <td class="whitespace-nowrap px-2 py-2 text-sm text-gray-500">
                                <Badge :text="row.enabled ? $t('Enabled') : $t('Disabled')" v-bind="statusBadge(row.enabled)" />
                            </td>
                            <td class="max-w-md truncate px-2 py-2 text-sm text-gray-500" :title="row.description">{{ row.description }}</td>
                            <td class="whitespace-nowrap px-2 py-1 text-sm text-gray-500">
                                <div class="flex items-center justify-end">
                                    <button type="button" :disabled="busy" :title="permissions.number_translation_edit ? $t('Edit') : $t('View')"
                                        class="rounded-full text-gray-400 hover:bg-gray-200 hover:text-gray-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                                        @click="open(row)">
                                        <span class="sr-only">{{ permissions.number_translation_edit ? $t('Edit') : $t('View') }}</span>
                                        <PencilSquareIcon v-if="permissions.number_translation_edit" class="h-9 w-9 py-2" aria-hidden="true" />
                                        <EyeIcon v-else class="h-9 w-9 py-2" aria-hidden="true" />
                                    </button>
                                    <button v-if="permissions.number_translation_delete" type="button" :disabled="busy" :title="$t('Delete')"
                                        class="rounded-full text-gray-400 hover:bg-gray-200 hover:text-gray-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                                        @click="deleting = row">
                                        <span class="sr-only">{{ $t('Delete') }}</span>
                                        <TrashIcon class="h-9 w-9 py-2" aria-hidden="true" />
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="loading && !data.data.length" class="py-10">
                <Loading :show="true" :absolute="false" />
            </div>
            <div v-else-if="!data.data.length" class="my-5 text-center">
                <MagnifyingGlassIcon class="mx-auto h-12 w-12 text-gray-400" aria-hidden="true" />
                <h3 class="mt-2 text-sm font-semibold text-gray-900">{{ loadError || $t('No results found') }}</h3>
                <button v-if="loadError" type="button" class="mt-2 text-sm font-medium text-indigo-600 hover:text-indigo-500"
                    @click="load(page)">{{ $t('Retry') }}</button>
            </div>

            <Paginator v-if="data.total" :previous="data.prev_page_url" :next="data.next_page_url" :from="data.from"
                :to="data.to" :total="data.total" :current-page="data.current_page" :last-page="data.last_page"
                :links="data.links" @pagination-change-page="changePage" />
        </div>

        <NumberTranslationForm v-if="editing" :item="editing" :route="editing.uuid ? itemUrl(editing.uuid) : routes.store"
            :read-only="!!editing.uuid && !permissions.number_translation_edit" @close="editing = null"
            @saved="saved" @error="error => emit('error', error)" />
        <ConfirmationModal :show="!!deleting" :header="$t('Delete Number Translation')"
            :text="$t('Delete :name and all its rules? Call routes using this profile will no longer translate numbers.', { name: deleting?.name ?? '' })"
            :confirm-button-label="$t('Delete')" :cancel-button-label="$t('Cancel')" :loading="busy"
            @confirm="remove" @close="closeDelete" />
    </div>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import axios from 'axios'
import { trans } from '@i18n'
import { ChevronDownIcon, ChevronUpIcon, EyeIcon, MagnifyingGlassIcon, PencilSquareIcon, TrashIcon } from '@heroicons/vue/24/solid'
import { ArrowPathIcon, ExclamationTriangleIcon } from '@heroicons/vue/24/outline'
import Badge from '@generalComponents/Badge.vue'
import Loading from './general/Loading.vue'
import Paginator from './general/Paginator.vue'
import ConfirmationModal from './modal/ConfirmationModal.vue'
import NumberTranslationForm from './forms/NumberTranslationForm.vue'

const props = defineProps({ routes: Object, permissions: Object })
const emit = defineEmits(['success', 'error'])
const dialplanExample = '<action application="translate" data="${destination_number} remove_leading_plus"/>'
const data = ref({ data: [], links: [] })
const search = ref('')
const sort = ref('number_translation_name')
const page = ref(1)
const loading = ref(false)
const busy = ref(false)
const syncing = ref(false)
const loadError = ref('')
const syncError = ref('')
const editing = ref(null)
const deleting = ref(null)
let requestId = 0
const itemUrl = uuid => props.routes.item.replace('__UUID__', uuid)
const statusBadge = enabled => enabled
    ? { backgroundColor: 'bg-green-50', textColor: 'text-green-700', ringColor: 'ring-green-600/20' }
    : { backgroundColor: 'bg-red-50', textColor: 'text-red-700', ringColor: 'ring-red-600/20' }

const load = async (requestedPage = page.value) => {
    const id = ++requestId
    loading.value = true
    loadError.value = ''
    try {
        const response = await axios.get(props.routes.index, {
            params: { page: requestedPage, sort: sort.value, filter: { search: search.value } },
        })
        if (id !== requestId) return
        data.value = response.data
        page.value = response.data.current_page
        if (!data.value.data.length && page.value > data.value.last_page) await load(data.value.last_page)
    } catch (error) {
        if (id !== requestId) return
        data.value = { data: [], links: [] }
        loadError.value = trans('Could not load number translations.')
        emit('error', error)
    } finally {
        if (id === requestId) loading.value = false
    }
}
const resetSearch = () => { search.value = ''; load(1) }
const sortIcon = field => sort.value === field ? ChevronUpIcon : sort.value === `-${field}` ? ChevronDownIcon : null
const sortBy = field => {
    sort.value = sort.value === field ? `-${field}` : field
    load(1)
}
const changePage = url => { if (url) load(Number(new URL(url, window.location.origin).searchParams.get('page')) || 1) }
const create = () => { editing.value = { name: '', description: '', enabled: true, rules: [{ regex: '', replace: '' }] } }
const open = async row => {
    busy.value = true
    try { editing.value = (await axios.get(itemUrl(row.uuid))).data }
    catch (error) { emit('error', error) }
    finally { busy.value = false }
}
const report = response => {
    syncError.value = response.runtime_synchronized === false ? response.messages.error[0] : ''
    emit('success', response.runtime_synchronized === false ? 'error' : 'success', response.messages)
}
const saved = response => { editing.value = null; report(response); load() }
const closeDelete = () => { if (!busy.value) deleting.value = null }
const remove = async () => {
    if (busy.value || !deleting.value) return
    busy.value = true
    try {
        report((await axios.delete(itemUrl(deleting.value.uuid))).data)
        deleting.value = null
        await load()
    } catch (error) { emit('error', error) }
    finally { busy.value = false }
}
const sync = async () => {
    syncing.value = true
    try { report((await axios.post(props.routes.sync)).data) }
    catch (error) { emit('error', error) }
    finally { syncing.value = false }
}
onMounted(() => load(1))
</script>
