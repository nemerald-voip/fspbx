<template>
    <div>
        <div v-if="syncError" role="alert" class="mx-4 mb-4 rounded-md bg-amber-50 p-4 text-sm text-amber-900 sm:mx-6">
            {{ syncError }}
        </div>
        <DataTable @search-action="load(1)" @reset-filters="resetSearch">
            <template #title>{{ $t('Number Translations') }}</template>
            <template #subtitle>
                <span class="block max-w-prose">{{ $t('Create reusable rules for rewriting phone numbers. Saving or enabling a profile does not automatically change calls. Your dialplan must call the profile and use its result.') }}</span>
                <span class="mt-2 block max-w-prose">{{ $t('For example, calling :profile converts :original to :result by removing the leading plus sign.', {
                    profile: 'remove_leading_plus', original: '+442071234567', result: '442071234567',
                }) }}</span>
            </template>
            <template #action>
                <div class="flex flex-wrap gap-2">
                    <button v-if="permissions.number_translation_edit" type="button" :disabled="syncing || busy"
                        class="rounded-md bg-white px-2.5 py-1.5 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 disabled:opacity-50"
                        @click="sync">{{ syncing ? $t('Syncing...') : $t('Sync') }}</button>
                    <button v-if="permissions.number_translation_add" type="button" :disabled="busy"
                        class="rounded-md bg-indigo-600 px-2.5 py-1.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 disabled:opacity-50"
                        @click="create">{{ $t('Add') }}</button>
                </div>
            </template>
            <template #filters>
                <details class="mb-4 w-full text-sm text-gray-700">
                    <summary class="w-fit cursor-pointer rounded font-medium text-indigo-600 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                        {{ $t('Show dialplan example') }}
                    </summary>
                    <pre class="mt-3 overflow-x-auto rounded-md bg-gray-50 p-3 text-xs leading-6 text-gray-900"><code v-text="dialplanExample"></code></pre>
                    <p class="mt-2 max-w-prose">{{ $t('Requires :module. The result is stored in :variable; use that variable in the following routing action.', {
                        module: 'mod_translate', variable: '${translated}',
                    }) }}</p>
                </details>
                <input v-model="search" type="search" :aria-label="$t('Search number translations')" :placeholder="$t('Search')"
                    class="mb-2 block rounded-md border-0 py-1.5 text-sm text-gray-900 ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-blue-600 sm:mr-4"
                    @keydown.enter="load(1)" />
            </template>
            <template #table-header>
                <th scope="col" class="px-4 py-3 text-left text-sm font-semibold text-gray-900">
                    <button type="button" class="inline-flex items-center gap-1" @click="toggleSort">
                        {{ $t('Name') }}
                        <ChevronUpIcon v-if="sort[0] !== '-'" class="h-4 w-4" aria-hidden="true" />
                        <ChevronDownIcon v-else class="h-4 w-4" aria-hidden="true" />
                    </button>
                </th>
                <th scope="col" class="px-3 py-3 text-left text-sm font-semibold text-gray-900">{{ $t('Rules') }}</th>
                <th scope="col" class="px-3 py-3 text-left text-sm font-semibold text-gray-900">{{ $t('Status') }}</th>
                <th scope="col" class="px-3 py-3 text-left text-sm font-semibold text-gray-900">{{ $t('Description') }}</th>
                <th scope="col" class="px-3 py-3 text-right text-sm font-semibold text-gray-900">{{ $t('Actions') }}</th>
            </template>
            <template #table-body>
                <tr v-if="loading && !data.data.length">
                    <td colspan="5" class="px-4 py-6 text-sm text-gray-500">{{ $t('Loading...') }}</td>
                </tr>
                <tr v-for="row in data.data" :key="row.uuid" class="hover:bg-gray-50">
                    <td class="px-4 py-2 text-sm font-medium text-gray-900">
                        <button type="button" :disabled="busy" class="max-w-xs truncate text-left text-indigo-600 hover:underline"
                            :title="row.name" @click="open(row)">{{ row.name }}</button>
                    </td>
                    <td class="px-3 py-2 text-sm text-gray-600">{{ row.rules_count }}</td>
                    <td class="whitespace-nowrap px-3 py-2 text-sm">
                        <span :class="row.enabled ? 'text-green-700' : 'text-gray-500'">{{ row.enabled ? $t('Enabled') : $t('Disabled') }}</span>
                    </td>
                    <td class="max-w-sm truncate px-3 py-2 text-sm text-gray-500" :title="row.description">{{ row.description }}</td>
                    <td class="whitespace-nowrap px-3 py-2 text-right text-sm">
                        <button type="button" :disabled="busy" class="rounded px-2 py-1 text-indigo-600 hover:bg-indigo-50"
                            @click="open(row)">{{ permissions.number_translation_edit ? $t('Edit') : $t('View') }}</button>
                        <button v-if="permissions.number_translation_delete" type="button" :disabled="busy"
                            class="rounded px-2 py-1 text-red-600 hover:bg-red-50" @click="deleting = row">{{ $t('Delete') }}</button>
                    </td>
                </tr>
                <tr v-if="!loading && !data.data.length">
                    <td colspan="5" class="px-4 py-6 text-sm text-gray-500">
                        {{ loadError || $t('No number translations found.') }}
                        <button v-if="loadError" type="button" class="ml-2 text-indigo-600 hover:underline" @click="load(page)">{{ $t('Retry') }}</button>
                    </td>
                </tr>
            </template>
            <template #footer>
                <Paginator :previous="data.prev_page_url" :next="data.next_page_url" :from="data.from" :to="data.to"
                    :total="data.total" :current-page="data.current_page" :last-page="data.last_page" :links="data.links"
                    @pagination-change-page="changePage" />
            </template>
        </DataTable>
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
import { ChevronDownIcon, ChevronUpIcon } from '@heroicons/vue/24/outline'
import DataTable from './general/DataTable.vue'
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
        loadError.value = trans('Could not load number translations.')
        emit('error', error)
    } finally {
        if (id === requestId) loading.value = false
    }
}
const resetSearch = () => { search.value = ''; load(1) }
const toggleSort = () => {
    sort.value = sort.value[0] === '-' ? 'number_translation_name' : '-number_translation_name'
    load(1)
}
const changePage = url => { if (url) load(Number(new URL(url, window.location.origin).searchParams.get('page')) || 1) }
const create = () => { editing.value = { name: '', description: '', enabled: true, rules: [] } }
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
