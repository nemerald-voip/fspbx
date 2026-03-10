<template>
  <MainLayout>
    <div class="m-3">
      <DataTable @search-action="handleSearchButtonClick" @reset-filters="handleFiltersReset">
        <template #title>Fax Logs</template>

        <template #filters>
          <div class="relative min-w-64 focus-within:z-10 mb-2 sm:mr-4">
            <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
              <MagnifyingGlassIcon class="h-5 w-5 text-gray-400" aria-hidden="true" />
            </div>
            <input
              type="search"
              v-model="filterData.search"
              class="block w-full rounded-md border-0 py-1.5 pl-10 text-gray-900 ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-blue-600 sm:hidden"
              placeholder="Search"
              @keydown.enter="handleSearchButtonClick"
            />
            <input
              type="search"
              v-model="filterData.search"
              class="hidden w-full rounded-md border-0 py-1.5 pl-10 text-sm leading-6 text-gray-900 ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-blue-600 sm:block"
              placeholder="Search"
              @keydown.enter="handleSearchButtonClick"
            />
          </div>

          <div class="relative z-10 min-w-64 -mt-0.5 mb-2 scale-y-95 shrink-0 sm:mr-4">
            <DatePicker :dateRange="filterData.dateRange" :timezone="props.timezone" @update:date-range="handleUpdateDateRange" />
          </div>

          <!-- Status filter (Success / Failed / All) -->
          <div class="relative min-w-40 mb-2 shrink-0 sm:mr-4">
            <select
              v-model="filterData.status"
              class="block w-full rounded-md border-0 py-2 pl-3 pr-10 text-sm text-gray-900 ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-blue-600"
            >
              <option value="all">All</option>
              <option value="success">Success</option>
              <option value="failed">Failed</option>
            </select>
          </div>
        </template>

        <template #navigation>
          <Paginator
            :previous="data.prev_page_url"
            :next="data.next_page_url"
            :from="data.from"
            :to="data.to"
            :total="data.total"
            :currentPage="data.current_page"
            :lastPage="data.last_page"
            :links="data.links"
            @pagination-change-page="renderRequestedPage"
            :bulk-actions="bulkActions"
            @bulk-action="handleBulkActionRequest"
            :has-selected-items="selectedItems.length > 0"
          />
        </template>

        <template #table-header>
          <TableColumnHeader
            class="flex whitespace-nowrap px-4 py-3.5 text-left text-sm font-semibold text-gray-900 items-center justify-start"
          >
            <input
              v-if="permissions.delete"
              type="checkbox"
              v-model="selectPageItems"
              @change="handleSelectPageItems"
              class="h-4 w-4 rounded border-gray-300 text-indigo-600"
            />
            <span class="pl-4">Date</span>
          </TableColumnHeader>

          <TableColumnHeader header="Status" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />
          <TableColumnHeader header="Code" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />
          <TableColumnHeader header="Result" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />
          <TableColumnHeader header="File" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />
          <TableColumnHeader header="ECM" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />
          <TableColumnHeader header="Local Station ID" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />
          <TableColumnHeader header="Bad Rows" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />
          <TableColumnHeader header="Transfer Rate" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />
          <TableColumnHeader header="Retry" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />
          <TableColumnHeader header="Destination" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />
          <TableColumnHeader v-if="permissions.delete" header="" class="px-2 py-3.5 text-center text-sm font-semibold text-gray-900" />
        </template>

        <template v-if="permissions.delete && (selectPageItems || selectAll)" v-slot:current-selection>
          <td colspan="12">
            <div class="text-sm text-center m-2">
              <span class="font-semibold">{{ selectedItems.length }}</span> items are selected.
              <button
                v-if="!selectAll && selectedItems.length != data.total"
                class="text-blue-500 rounded py-2 px-2 hover:bg-blue-200 hover:text-blue-500 focus:outline-none focus:ring-1 focus:bg-blue-200 focus:ring-blue-300 transition duration-500 ease-in-out"
                @click="handleSelectAll"
              >
                Select all {{ data.total }} items
              </button>
              <button
                v-if="selectAll"
                class="text-blue-500 rounded py-2 px-2 hover:bg-blue-200 hover:text-blue-500 focus:outline-none focus:ring-1 focus:bg-blue-200 focus:ring-blue-300 transition duration-500 ease-in-out"
                @click="handleClearSelection"
              >
                Clear selection
              </button>
            </div>
          </td>
        </template>

        <template #table-body>
          <tr v-for="row in data.data" :key="row.fax_log_uuid">
            <TableField class="whitespace-nowrap px-4 py-2 text-sm text-gray-500">
              <div class="flex items-center">
                <input
                  v-if="permissions.delete"
                  v-model="selectedItems"
                  type="checkbox"
                  name="action_box[]"
                  :value="row.fax_log_uuid"
                  class="h-4 w-4 rounded border-gray-300 text-indigo-600"
                />
                <div class="ml-4">
                  <div class="text-gray-900">{{ formatDate(row.fax_date) }}</div>
                  <div class="text-gray-500 text-xs">{{ formatTime(row.fax_date) }}</div>
                </div>
              </div>
            </TableField>

            <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500">
              <template #default>
                <span
                  class="inline-flex items-center rounded-md px-2 py-1 text-xs font-semibold"
                  :class="row.fax_success ? 'bg-green-50 text-green-700 ring-1 ring-inset ring-green-600/20' : 'bg-red-50 text-red-700 ring-1 ring-inset ring-red-600/20'"
                >
                  {{ row.fax_success ? 'Success' : 'Failed' }}
                </span>
              </template>
            </TableField>

            <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500" :text="String(row.fax_result_code ?? '')" />
            <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500" :text="row.fax_result_text ?? ''" />
            <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500" :text="fileBase(row.fax_file)" />
            <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500" :text="row.fax_ecm_used ?? ''" />
            <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500" :text="row.fax_local_station_id ?? ''" />
            <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500" :text="String(row.fax_bad_rows ?? '')" />
            <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500" :text="String(row.fax_transfer_rate ?? '')" />
            <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500" :text="retryText(row)" />
            <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500" :text="row.fax_uri ?? ''" />

            <TableField v-if="permissions.delete" class="whitespace-nowrap px-2 py-1 text-sm text-gray-500">
              <template #action-buttons>
                <div class="flex items-center whitespace-nowrap justify-end">
                  <ejs-tooltip :content="'Delete'" position="TopCenter" target="#delete_tooltip_target">
                    <div id="delete_tooltip_target">
                      <TrashIcon
                        @click="handleDeleteButtonClick(row.fax_log_uuid)"
                        class="h-9 w-9 transition duration-500 ease-in-out py-2 rounded-full text-gray-400 hover:bg-gray-200 hover:text-gray-600 active:bg-gray-300 active:duration-150 cursor-pointer"
                      />
                    </div>
                  </ejs-tooltip>
                </div>
              </template>
            </TableField>
          </tr>
        </template>

        <template #empty>
          <div v-if="data.data.length === 0" class="text-center my-5">
            <MagnifyingGlassIcon class="mx-auto h-12 w-12 text-gray-400" />
            <h3 class="mt-2 text-sm font-semibold text-gray-900">No results found</h3>
            <p class="mt-1 text-sm text-gray-500">Adjust your search and try again.</p>
          </div>
        </template>

        <template #loading>
          <Loading :show="loading" />
        </template>

        <template #footer>
          <Paginator
            :previous="data.prev_page_url"
            :next="data.next_page_url"
            :from="data.from"
            :to="data.to"
            :total="data.total"
            :currentPage="data.current_page"
            :lastPage="data.last_page"
            :links="data.links"
            @pagination-change-page="renderRequestedPage"
          />
        </template>
      </DataTable>
    </div>
  </MainLayout>

  <Notification :show="notificationShow" :type="notificationType" :messages="notificationMessages" @update:show="hideNotification" />

  <ConfirmationModal
    :show="showDeleteConfirmationModal"
    @close="showDeleteConfirmationModal = false"
    @confirm="confirmDeleteAction"
    :header="'Are you sure?'"
    :text="'Are you sure you want to permanently delete selected fax logs? This action can not be undone.'"
    :confirm-button-label="'Delete'"
    cancel-button-label="Cancel"
  />
</template>

<script setup>
import { ref, computed, onMounted } from "vue";
import axios from "axios";
import moment from "moment-timezone";

import MainLayout from "../Layouts/MainLayout.vue";
import DataTable from "./components/general/DataTable.vue";
import TableColumnHeader from "./components/general/TableColumnHeader.vue";
import TableField from "./components/general/TableField.vue";
import Paginator from "./components/general/Paginator.vue";
import DatePicker from "./components/general/DatePicker.vue";
import Loading from "./components/general/Loading.vue";
import Notification from "./components/notifications/Notification.vue";
import ConfirmationModal from "./components/modal/ConfirmationModal.vue";

import { MagnifyingGlassIcon, TrashIcon } from "@heroicons/vue/24/solid";
import { TooltipComponent as EjsTooltip } from "@syncfusion/ej2-vue-popups";
import { registerLicense } from "@syncfusion/ej2-base";

const loading = ref(false);

const notificationType = ref(null);
const notificationMessages = ref(null);
const notificationShow = ref(null);

const selectedItems = ref([]);
const selectPageItems = ref(false);
const selectAll = ref(false);

const showDeleteConfirmationModal = ref(false);
const confirmDeleteAction = ref(null);

const data = ref({
  data: [],
  prev_page_url: null,
  next_page_url: null,
  from: 0,
  to: 0,
  total: 0,
  current_page: 1,
  last_page: 1,
  links: [],
});

const props = defineProps({
  startPeriod: String,
  endPeriod: String,
  timezone: String,
  routes: Object,
  permissions: Object,
});

onMounted(() => handleSearchButtonClick());

const startLocal = moment.utc(props.startPeriod).tz(props.timezone);
const endLocal = moment.utc(props.endPeriod).tz(props.timezone);

const filterData = ref({
  search: null,
  status: "all",
  dateRange: [
    startLocal.clone().startOf("day").toISOString(),
    endLocal.clone().endOf("day").toISOString(),
  ],
});

const bulkActions = computed(() => {
  if (!props.permissions?.delete) return [];
  return [{ id: "bulk_delete", label: "Delete", icon: "TrashIcon" }];
});

const getData = (page = 1) => {
  loading.value = true;

  axios
    .get(props.routes.data_route, {
      params: {
        filter: filterData.value,
        page,
      },
    })
    .then((response) => {
      data.value = response.data;
    })
    .catch((error) => {
      handleErrorResponse(error);
    })
    .finally(() => {
      loading.value = false;
    });
};

const handleSearchButtonClick = () => getData();

const renderRequestedPage = (url) => {
  const urlObj = new URL(url, window.location.origin);
  const pageParam = urlObj.searchParams.get("page") ?? 1;
  getData(pageParam);
};

const handleUpdateDateRange = (newDateRange) => {
  filterData.value.dateRange = newDateRange;
};

const handleFiltersReset = () => {
  filterData.value.search = null;
  filterData.value.status = "all";
  filterData.value.dateRange = [
    startLocal.clone().startOf("day").toISOString(),
    endLocal.clone().endOf("day").toISOString(),
  ];
  handleSearchButtonClick();
};

const handleBulkActionRequest = (action) => {
  if (action === "bulk_delete") {
    showDeleteConfirmationModal.value = true;
    confirmDeleteAction.value = () => executeBulkDelete();
  }
};

const handleDeleteButtonClick = (uuid) => {
  showDeleteConfirmationModal.value = true;
  confirmDeleteAction.value = () => executeBulkDelete([uuid]);
};

const executeBulkDelete = (items = selectedItems.value) => {
  axios
    .post(props.routes.bulk_delete, { items })
    .then((response) => {
      handleModalClose();
      showNotification("success", response.data.messages);
      handleClearSelection();
      handleSearchButtonClick();
    })
    .catch((error) => {
      handleClearSelection();
      handleModalClose();
      handleErrorResponse(error);
    });
};

const handleSelectAll = () => {
  axios
    .post(props.routes.select_all, { filter: filterData.value })
    .then((response) => {
      selectedItems.value = (response.data.items || []).map(String);
      selectAll.value = true;
      selectPageItems.value = true;
      showNotification("success", response.data.messages);
    })
    .catch((error) => {
      handleClearSelection();
      handleErrorResponse(error);
    });
};

const handleSelectPageItems = () => {
  if (selectPageItems.value) {
    selectedItems.value = (data.value.data || []).map((item) => String(item.fax_log_uuid));
  } else {
    selectedItems.value = [];
    selectAll.value = false;
  }
};

const handleClearSelection = () => {
  selectedItems.value = [];
  selectPageItems.value = false;
  selectAll.value = false;
};

const handleModalClose = () => {
  showDeleteConfirmationModal.value = false;
};

const hideNotification = () => {
  notificationShow.value = false;
  notificationType.value = null;
  notificationMessages.value = null;
};

const showNotification = (type, messages = null) => {
  notificationType.value = type;
  notificationMessages.value = messages;
  notificationShow.value = true;
};

const handleErrorResponse = (error) => {
  if (error.response) {
    showNotification("error", error.response.data.errors || { request: [error.message] });
  } else if (error.request) {
    showNotification("error", { request: [String(error.request)] });
  } else {
    showNotification("error", { request: [error.message] });
  }
};

// Helpers (match your old blade display intent)
const formatDate = (utc) => (utc ? moment.utc(utc).tz(props.timezone).format("ddd, MMM DD, YYYY") : "");
const formatTime = (utc) => (utc ? moment.utc(utc).tz(props.timezone).format("hh:mm:ss A") : "");

const fileBase = (path) => {
  if (!path) return "";
  const name = String(path).split("/").pop() || "";
  return name.replace(/\.[^.]+$/, ""); // remove extension
};

const retryText = (row) => {
  const a = row?.fax_retry_attempts ?? "";
  const l = row?.fax_retry_limit ?? "";
  if (a === "" && l === "") return "";
  return `${a}${l !== "" ? " / " + l : ""}`;
};

registerLicense("Ngo9BigBOggjHTQxAR8/V1NAaF5cWWdCf1FpRmJGdld5fUVHYVZUTXxaS00DNHVRdkdnWX5eeHVSQ2hYUkB3WEI=");
</script>

<style>
@import "@syncfusion/ej2-base/styles/tailwind.css";
@import "@syncfusion/ej2-vue-popups/styles/tailwind.css";
</style>