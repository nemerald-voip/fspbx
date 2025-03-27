<template>
    <!-- <div class="p-4">
      <h2 class="text-xl font-bold mb-4">Emergency Call Groups</h2>
  
      <table class="min-w-full border rounded shadow text-sm">
        <thead class="bg-gray-100">
          <tr>
            <th class="text-left px-4 py-2">Number</th>
            <th class="text-left px-4 py-2">Description</th>
            <th class="text-left px-4 py-2">Members</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="call in emergencyCalls" :key="call.id" class="border-t">
            <td class="px-4 py-2">{{ call.emergency_number }}</td>
            <td class="px-4 py-2">{{ call.description || '-' }}</td>
            <td class="px-4 py-2">{{ call.members.length }}</td>
          </tr>
        </tbody>
      </table>
  
      <div v-if="loading" class="mt-4 text-gray-500">Loading...</div>
      <div v-if="error" class="mt-4 text-red-500">Error: {{ error }}</div>
    </div> -->

    <DataTable @search-action="handleSearchButtonClick" @reset-filters="handleFiltersReset">
            <template #title>Virtual Receptionists</template>

            <template #filters>
                <div class="relative min-w-64 focus-within:z-10 mb-2 sm:mr-4">
                    <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                        <MagnifyingGlassIcon class="h-5 w-5 text-gray-400" aria-hidden="true" />
                    </div>
                    <input type="text" v-model="search" name="mobile-search-candidate"
                        id="mobile-search-candidate"
                        class="block w-full rounded-md border-0 py-1.5 pl-10 text-gray-900 ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-blue-600 sm:hidden"
                        placeholder="Search" @keydown.enter="handleSearchButtonClick" />
                    <input type="text" v-model="search" name="desktop-search-candidate"
                        id="desktop-search-candidate"
                        class="hidden w-full rounded-md border-0 py-1.5 pl-10 text-sm leading-6 text-gray-900 ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-blue-600 sm:block"
                        placeholder="Search" @keydown.enter="handleSearchButtonClick"/>
                </div>
            </template>

            <template #action>
                <button  type="button" @click.prevent="handleCreateButtonClick()"
                    class="rounded-md bg-indigo-600 px-2.5 py-1.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                    Create
                </button>


            </template>


            <template #table-header>

                <TableColumnHeader 
                    class="flex whitespace-nowrap px-4 py-1.5 text-left text-sm font-semibold text-gray-900 items-center justify-start">
                    <input type="checkbox" v-model="selectPageItems" @change="handleSelectPageItems"
                        class="h-4 w-4 rounded border-gray-300 text-indigo-600">
                    <BulkActionButton :actions="bulkActions" @bulk-action="handleBulkActionRequest"
                        :has-selected-items="selectedItems.length > 0" />
                    <span class="pl-4">Virtual Receptionist</span>
                </TableColumnHeader>
                <TableColumnHeader header="Extension" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />
                <TableColumnHeader header="Description" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />
                <TableColumnHeader header="Status" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />
                <TableColumnHeader header="" class="px-2 py-3.5 text-right text-sm font-semibold text-gray-900" />
            </template>

            <template v-if="selectPageItems" v-slot:current-selection>
                <td colspan="6">
                    <div class="text-sm text-center m-2">
                        <span class="font-semibold ">{{ selectedItems.length }} </span> items are selected.
                        <button v-if="!selectAll && selectedItems.length != data.total"
                            class="text-blue-500 rounded py-2 px-2 hover:bg-blue-200  hover:text-blue-500 focus:outline-none focus:ring-1 focus:bg-blue-200 focus:ring-blue-300 transition duration-500 ease-in-out"
                            @click="handleSelectAll">
                            Select all {{ data.total }} items
                        </button>
                        <button v-if="selectAll"
                            class="text-blue-500 rounded py-2 px-2 hover:bg-blue-200  hover:text-blue-500 focus:outline-none focus:ring-1 focus:bg-blue-200 focus:ring-blue-300 transition duration-500 ease-in-out"
                            @click="handleClearSelection">
                            Clear selection
                        </button>
                    </div>
                </td>
            </template>

            <template #table-body>
                <tr v-for="call in emergencyCalls" :key="call.id" >
                    <TableField class="whitespace-nowrap px-4 py-2 text-sm text-gray-500"
                        :text="row.voicemail_id">
                        <div class="flex items-center">
                            <input v-if="row.ivr_menu_uuid" v-model="selectedItems" type="checkbox" name="action_box[]"
                                :value="row.ivr_menu_uuid" class="h-4 w-4 rounded border-gray-300 text-indigo-600">
                            <div class="ml-9"
                                :class="{ 'cursor-pointer hover:text-gray-900': page.props.auth.can.virtual_receptionist_update, }"
                                @click="page.props.auth.can.virtual_receptionist_update && handleEditRequest(row.ivr_menu_uuid)">
                                    {{ row.ivr_menu_name }}
                            </div>
                        </div>
                    </TableField>

                    <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500" :text="row.ivr_menu_extension" />
                    <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500" :text="row.ivr_menu_description" />
                    <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500" :text="row.ivr_menu_enabled" >
                        <Badge v-if="row.ivr_menu_enabled=='true'" text="Enabled" backgroundColor="bg-green-50"
                            textColor="text-green-700"
                            ringColor="ring-green-600/20" />
                        <Badge v-else text="Disabled" backgroundColor="bg-rose-50"
                            textColor="text-rose-700"
                            ringColor="ring-rose-600/20" />

                        </TableField>


                    <TableField class="whitespace-nowrap px-2 py-1 text-sm text-gray-500">
                        <template #action-buttons>
                            <div class="flex items-center whitespace-nowrap justify-end">
                                <ejs-tooltip v-if="page.props.auth.can.virtual_receptionist_update" :content="'Edit'" position='TopCenter'
                                    target="#destination_tooltip_target">
                                    <div id="destination_tooltip_target">
                                        <PencilSquareIcon @click="handleEditRequest(row.ivr_menu_uuid)"
                                            class="h-9 w-9 transition duration-500 ease-in-out py-2 rounded-full text-gray-400 hover:bg-gray-200 hover:text-gray-600 active:bg-gray-300 active:duration-150 cursor-pointer" />

                                    </div>
                                </ejs-tooltip>


                                <ejs-tooltip v-if="page.props.auth.can.virtual_receptionist_destroy" :content="'Delete'"
                                    position='TopCenter' target="#delete_tooltip_target">
                                    <div id="delete_tooltip_target">
                                        <TrashIcon @click="handleSingleItemDeleteRequest(row.destroy_route)"
                                            class="h-9 w-9 transition duration-500 ease-in-out py-2 rounded-full text-gray-400 hover:bg-gray-200 hover:text-gray-600 active:bg-gray-300 active:duration-150 cursor-pointer" />
                                    </div>
                                </ejs-tooltip>
                            </div>
                        </template>
                    </TableField>
                </tr>
            </template>
            <template #empty>
                <!-- Conditional rendering for 'no records' message -->
                <div v-if="emergencyCalls.length === 0" class="text-center my-5 ">
                    <MagnifyingGlassIcon class="mx-auto h-12 w-12 text-gray-400" />
                    <h3 class="mt-2 text-sm font-semibold text-gray-900">No results found</h3>
                    <p class="mt-1 text-sm text-gray-500">
                        Adjust your search and try again.
                    </p>
                </div>
            </template>

            <template #loading>
                <Loading :show="loading" />
            </template>


        </DataTable>
        
  </template>

<script setup>
import { ref, onMounted } from 'vue';
import DataTable from "./general/DataTable.vue";
import TableColumnHeader from "./general/TableColumnHeader.vue";
import TableField from "./general/TableField.vue";
import { MagnifyingGlassIcon, TrashIcon, PencilSquareIcon } from "@heroicons/vue/24/solid";
import BulkActionButton from "./general/BulkActionButton.vue";
import { registerLicense } from '@syncfusion/ej2-base';
import { TooltipComponent as EjsTooltip } from "@syncfusion/ej2-vue-popups";
import Badge from "@generalComponents/Badge.vue";


const props = defineProps({
    routes: Object,
})

const emergencyCalls = ref([]);
const loading = ref(false);
const error = ref(null);
const selectedItems = ref([]);
const search = ref(null);

onMounted(async () => {
  loading.value = true;
  try {
    const response = await axios.get(props.routes.emergency_calls);
    emergencyCalls.value = response.data;
  } catch (err) {
    error.value = err.response?.data?.message || err.message;
  } finally {
    loading.value = false;
  }
});

</script>