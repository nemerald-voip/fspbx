<template>
    <MainLayout />

    <div class="m-3">
        <DataTable v-if="viewMode === 'list'" @search-action="fetchData(1)" @reset-filters="resetFilters">
            <template #title>Music on Hold</template>

            <template #subtitle>
                Manage hold music streams and audio files.
            </template>

            <template #filters>
                <div class="relative min-w-64 focus-within:z-10 mb-2 sm:mr-4">
                    <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                        <MagnifyingGlassIcon class="h-5 w-5 text-gray-400" aria-hidden="true" />
                    </div>
                    <input
                        v-model="filterData.search"
                        type="text"
                        class="block w-full rounded-md border-0 py-1.5 pl-10 text-sm text-gray-900 ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-blue-600"
                        placeholder="Search"
                        @keydown.enter="fetchData(1)"
                    />
                </div>
            </template>

            <template #action>
                <ViewToggle :model-value="viewMode" @update:model-value="setViewMode" />

                <button
                    v-if="permissions.reload"
                    type="button"
                    class="ml-2 sm:ml-4 inline-flex items-center gap-1 rounded-md bg-white px-2.5 py-1.5 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50"
                    @click="confirmReload"
                >
                    <ArrowPathIcon class="h-4 w-4 text-gray-500" />
                    Reload
                </button>

                <button
                    v-if="permissions.create"
                    type="button"
                    class="ml-2 sm:ml-4 rounded-md bg-white px-2.5 py-1.5 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50"
                    @click="openUploadModal()"
                >
                    Upload
                </button>

                <button
                    v-if="permissions.create"
                    type="button"
                    class="ml-2 sm:ml-4 rounded-md bg-indigo-600 px-2.5 py-1.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500"
                    @click="openCreateForm"
                >
                    Create stream
                </button>

                <button
                    v-if="permissions.view_all && filterData.showGlobal"
                    type="button"
                    class="ml-2 sm:ml-4 rounded-md bg-white px-2.5 py-1.5 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50"
                    @click="showLocal"
                >
                    Show local
                </button>

                <button
                    v-else-if="permissions.view_all"
                    type="button"
                    class="ml-2 sm:ml-4 rounded-md bg-white px-2.5 py-1.5 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50"
                    @click="showAll"
                >
                    Show all
                </button>
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
                    :bulk-actions="bulkActions"
                    :has-selected-items="selectedItems.length > 0"
                    @pagination-change-page="changePage"
                    @bulk-action="handleBulkAction"
                />
            </template>

            <template #table-header>
                <TableColumnHeader class="px-4 py-3.5 text-left text-sm font-semibold text-gray-900">
                    <div class="flex items-center">
                        <input
                            v-model="selectPageItems"
                            type="checkbox"
                            :disabled="selectablePageIds.length === 0"
                            class="h-4 w-4 rounded border-gray-300 text-indigo-600 disabled:cursor-not-allowed disabled:opacity-50"
                        />
                        <button class="ml-4 flex items-center" @click="setSort('music_on_hold_name')">
                            <span class="mr-2">Name</span>
                            <ChevronUpIcon v-if="sortData.name === 'music_on_hold_name' && sortData.order === 'asc'" class="h-4 w-4 text-gray-500" />
                            <ChevronDownIcon v-else-if="sortData.name === 'music_on_hold_name' && sortData.order === 'desc'" class="h-4 w-4 text-gray-500" />
                        </button>
                    </div>
                </TableColumnHeader>
                <TableColumnHeader v-if="filterData.showGlobal" header="Domain" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />
                <TableColumnHeader header="Rate" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />
                <TableColumnHeader header="Options" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />
                <TableColumnHeader header="Files" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />
                <TableColumnHeader header="" class="px-2 py-3.5 text-right text-sm font-semibold text-gray-900" />
            </template>

            <template v-if="selectPageItems" #current-selection>
                <td :colspan="columnCount">
                    <div class="m-2 text-center text-sm">
                        <span class="font-semibold">{{ selectedItems.length }}</span> streams are selected.
                        <button
                            v-if="!selectAll && selectedItems.length !== data.total"
                            class="rounded px-2 py-2 text-blue-500 transition duration-500 ease-in-out hover:bg-blue-200 hover:text-blue-500 focus:bg-blue-200 focus:outline-none focus:ring-1 focus:ring-blue-300"
                            @click="handleSelectAll"
                        >
                            Select all {{ data.total }} streams
                        </button>
                        <button
                            v-if="selectAll"
                            class="rounded px-2 py-2 text-blue-500 transition duration-500 ease-in-out hover:bg-blue-200 hover:text-blue-500 focus:bg-blue-200 focus:outline-none focus:ring-1 focus:ring-blue-300"
                            @click="clearSelection"
                        >
                            Clear selection
                        </button>
                    </div>
                </td>
            </template>

            <template #table-body>
                <template v-for="row in data.data" :key="row.music_on_hold_uuid">
                    <tr>
                        <TableField class="px-4 py-2 text-sm text-gray-500">
                            <div class="flex items-center">
                                <input
                                    v-model="selectedItems"
                                    type="checkbox"
                                    :value="row.music_on_hold_uuid"
                                    :disabled="!row.can_modify"
                                    class="h-4 w-4 rounded border-gray-300 text-indigo-600 disabled:cursor-not-allowed disabled:opacity-50"
                                />
                                <div class="ml-4 min-w-0">
                                    <button
                                        type="button"
                                        class="font-medium text-gray-900"
                                        :class="{ 'cursor-pointer hover:text-indigo-600': permissions.update && row.can_modify }"
                                        @click="permissions.update && row.can_modify && openEditForm(row.music_on_hold_uuid)"
                                    >
                                        {{ row.music_on_hold_name }}
                                    </button>
                                    <div v-if="permissions.view_path && row.music_on_hold_path" class="mt-1 max-w-md truncate text-xs text-gray-400">
                                        {{ row.music_on_hold_path }}
                                    </div>
                                </div>
                            </div>
                        </TableField>

                        <TableField v-if="filterData.showGlobal" class="whitespace-nowrap px-2 py-2 text-sm text-gray-500" :text="row.domain_label" />
                        <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500" :text="row.rate_label" />
                        <TableField class="px-2 py-2 text-sm text-gray-500">
                            <div class="flex flex-wrap gap-1">
                                <Badge :text="row.music_on_hold_shuffle === 'true' ? 'Shuffle' : 'Ordered'" v-bind="row.music_on_hold_shuffle === 'true' ? blueBadge : grayBadge" />
                                <Badge :text="row.music_on_hold_channels === '2' ? 'Stereo' : 'Mono'" v-bind="grayBadge" />
                                <Badge v-if="row.music_on_hold_chime_list" text="Chime" v-bind="amberBadge" />
                            </div>
                        </TableField>
                        <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500" :text="`${row.files.length} file${row.files.length === 1 ? '' : 's'}`" />
                        <TableField class="px-2 py-1 text-sm text-gray-500">
                            <template #action-buttons>
                                <div class="flex items-center justify-end gap-1">
                                    <button
                                        v-if="permissions.update && row.can_modify"
                                        type="button"
                                        class="rounded-full p-2 text-gray-400 transition hover:bg-gray-200 hover:text-gray-600"
                                        title="Edit"
                                        @click="openEditForm(row.music_on_hold_uuid)"
                                    >
                                        <PencilSquareIcon class="h-5 w-5" />
                                    </button>
                                    <button
                                        v-if="permissions.destroy && row.can_modify"
                                        type="button"
                                        class="rounded-full p-2 text-gray-400 transition hover:bg-gray-200 hover:text-gray-600"
                                        title="Delete"
                                        @click="confirmStreamDelete([row.music_on_hold_uuid])"
                                    >
                                        <TrashIcon class="h-5 w-5" />
                                    </button>
                                </div>
                            </template>
                        </TableField>
                    </tr>

                    <tr>
                        <td :colspan="columnCount" class="bg-gray-50 px-8 py-3">
                            <div v-if="row.files.length" class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <tbody class="divide-y divide-gray-200">
                                        <tr v-for="file in row.files" :key="`${row.music_on_hold_uuid}-${file.name}`">
                                            <td class="py-2 pr-4 text-sm text-gray-700">
                                                <span class="break-all">{{ file.name }}</span>
                                            </td>
                                            <td class="whitespace-nowrap px-4 py-2 text-right text-sm text-gray-500">
                                                {{ file.size_label }}
                                            </td>
                                            <td class="whitespace-nowrap px-4 py-2 text-right text-sm text-gray-500">
                                                {{ formatDate(file.modified_at) }}
                                            </td>
                                            <td class="whitespace-nowrap py-1 pl-4 text-right">
                                                <button type="button" class="rounded-full p-2 text-blue-500 transition hover:bg-blue-100 hover:text-blue-700" title="Play" @click="openPlayer(row, file)">
                                                    <PlayCircleIcon class="h-5 w-5" />
                                                </button>
                                                <button type="button" class="rounded-full p-2 text-gray-400 transition hover:bg-gray-200 hover:text-gray-600" title="Download" @click="downloadFile(file.download_url)">
                                                    <ArrowDownTrayIcon class="h-5 w-5" />
                                                </button>
                                                <button
                                                    v-if="permissions.destroy && row.can_modify"
                                                    type="button"
                                                    class="rounded-full p-2 text-gray-400 transition hover:bg-gray-200 hover:text-gray-600"
                                                    title="Delete"
                                                    @click="confirmFileDelete(row, file)"
                                                >
                                                    <TrashIcon class="h-5 w-5" />
                                                </button>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <div v-else class="text-sm text-gray-500">No audio files in this stream.</div>
                        </td>
                    </tr>
                </template>
            </template>

            <template #empty>
                <div v-if="!loading && data.data.length === 0" class="text-center my-5">
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
                    :page-size="perPage"
                    :page-size-options="props.pagination?.per_page_options ?? []"
                    :show-page-size-selector="true"
                    @pagination-change-page="changePage"
                    @page-size-change="handlePageSizeChange"
                />
            </template>
        </DataTable>

        <div v-else class="px-4 sm:px-6 lg:px-8">
            <div class="sm:flex sm:items-center">
                <div class="sm:flex-auto">
                    <div class="mt-3 text-lg font-semibold leading-6 text-gray-600">Music on Hold</div>
                    <p class="mt-2 text-sm text-gray-700">Manage hold music streams and audio files.</p>
                </div>
                <div class="mt-4 flex flex-wrap items-center gap-2 sm:ml-16 sm:mt-0 sm:flex-none">
                    <ViewToggle :model-value="viewMode" @update:model-value="setViewMode" />

                    <button
                        v-if="permissions.reload"
                        type="button"
                        class="inline-flex items-center gap-1 rounded-md bg-white px-2.5 py-1.5 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50"
                        @click="confirmReload"
                    >
                        <ArrowPathIcon class="h-4 w-4 text-gray-500" />
                        Reload
                    </button>

                    <button
                        v-if="permissions.create"
                        type="button"
                        class="rounded-md bg-white px-2.5 py-1.5 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50"
                        @click="openUploadModal()"
                    >
                        Upload
                    </button>

                    <button
                        v-if="permissions.create"
                        type="button"
                        class="rounded-md bg-indigo-600 px-2.5 py-1.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500"
                        @click="openCreateForm"
                    >
                        Create stream
                    </button>

                    <button
                        v-if="permissions.view_all && filterData.showGlobal"
                        type="button"
                        class="rounded-md bg-white px-2.5 py-1.5 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50"
                        @click="showLocal"
                    >
                        Show local
                    </button>

                    <button
                        v-else-if="permissions.view_all"
                        type="button"
                        class="rounded-md bg-white px-2.5 py-1.5 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50"
                        @click="showAll"
                    >
                        Show all
                    </button>
                </div>
            </div>

            <div class="mt-6 flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center">
                <div class="relative min-w-64 focus-within:z-10 sm:mr-4">
                    <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                        <MagnifyingGlassIcon class="h-5 w-5 text-gray-400" aria-hidden="true" />
                    </div>
                    <input
                        v-model="filterData.search"
                        type="text"
                        class="block w-full rounded-md border-0 py-1.5 pl-10 text-sm text-gray-900 ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-blue-600"
                        placeholder="Search"
                        @keydown.enter="fetchData(1)"
                    />
                </div>

                <button
                    type="button"
                    class="inline-flex items-center gap-1 rounded-md bg-white px-2.5 py-1.5 text-sm font-semibold text-gray-700 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50"
                    title="Toggle sort order"
                    @click="toggleNameSort"
                >
                    <span>Name</span>
                    <ChevronUpIcon v-if="sortData.order === 'asc'" class="h-4 w-4 text-gray-500" />
                    <ChevronDownIcon v-else class="h-4 w-4 text-gray-500" />
                </button>

                <button
                    type="button"
                    class="rounded-md bg-indigo-600 px-2.5 py-1.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500"
                    @click.prevent="fetchData(1)"
                >
                    Search
                </button>

                <button
                    type="button"
                    class="rounded-md bg-white px-2.5 py-1.5 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50"
                    @click.prevent="resetFilters"
                >
                    Reset
                </button>
            </div>

            <div v-if="selectedItems.length > 0" class="mt-4 flex flex-wrap items-center justify-between gap-3 rounded-md bg-indigo-50 px-4 py-2 text-sm">
                <div class="text-indigo-900">
                    <span class="font-semibold">{{ selectedItems.length }}</span> selected
                    <button
                        v-if="!selectAll && selectedItems.length !== data.total"
                        class="ml-2 rounded px-2 py-1 text-blue-600 transition hover:bg-blue-100"
                        @click="handleSelectAll"
                    >
                        Select all {{ data.total }}
                    </button>
                    <button
                        v-if="selectAll"
                        class="ml-2 rounded px-2 py-1 text-blue-600 transition hover:bg-blue-100"
                        @click="clearSelection"
                    >
                        Clear selection
                    </button>
                </div>
                <div class="flex items-center gap-2">
                    <button
                        v-if="permissions.destroy"
                        type="button"
                        class="inline-flex items-center gap-1 rounded-md bg-white px-2.5 py-1.5 text-sm font-semibold text-red-600 shadow-sm ring-1 ring-inset ring-red-200 hover:bg-red-50"
                        @click="handleBulkAction('bulk_delete')"
                    >
                        <TrashIcon class="h-4 w-4" />
                        Delete
                    </button>
                    <button
                        type="button"
                        class="rounded-md px-2 py-1 text-sm text-gray-600 hover:bg-white"
                        @click="clearSelection"
                    >
                        Clear
                    </button>
                </div>
            </div>

            <div class="relative mt-6">
                <Loading :show="loading" />

                <div v-if="!loading && data.data.length === 0" class="text-center my-12">
                    <MagnifyingGlassIcon class="mx-auto h-12 w-12 text-gray-400" />
                    <h3 class="mt-2 text-sm font-semibold text-gray-900">No results found</h3>
                    <p class="mt-1 text-sm text-gray-500">Adjust your search and try again.</p>
                </div>

                <div
                    v-else
                    class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4"
                >
                    <div
                        v-for="row in data.data"
                        :key="row.music_on_hold_uuid"
                        :class="[
                            'group relative flex cursor-pointer flex-col rounded-lg bg-white ring-1 transition hover:-translate-y-0.5 hover:shadow-md',
                            selectedItems.includes(row.music_on_hold_uuid)
                                ? 'ring-2 ring-indigo-500'
                                : 'ring-gray-200 hover:ring-indigo-200',
                        ]"
                        @click="openDrawer(row)"
                    >
                        <div class="absolute left-3 top-3 z-10" @click.stop>
                            <input
                                v-model="selectedItems"
                                type="checkbox"
                                :value="row.music_on_hold_uuid"
                                :disabled="!row.can_modify"
                                :class="[
                                    'h-4 w-4 rounded border-gray-300 text-indigo-600 transition',
                                    !row.can_modify
                                        ? 'cursor-not-allowed opacity-0'
                                        : '',
                                    selectedItems.includes(row.music_on_hold_uuid)
                                        ? 'opacity-100'
                                        : 'opacity-0 group-hover:opacity-100 focus:opacity-100',
                                ]"
                            />
                        </div>

                        <div class="absolute right-3 top-3 z-10 flex items-center gap-1 opacity-0 transition group-hover:opacity-100" @click.stop>
                            <button
                                v-if="permissions.update && row.can_modify"
                                type="button"
                                class="rounded-full bg-white/80 p-1.5 text-gray-500 shadow-sm hover:bg-white hover:text-indigo-600"
                                title="Edit"
                                @click="openEditForm(row.music_on_hold_uuid)"
                            >
                                <PencilSquareIcon class="h-4 w-4" />
                            </button>
                            <button
                                v-if="permissions.destroy && row.can_modify"
                                type="button"
                                class="rounded-full bg-white/80 p-1.5 text-gray-500 shadow-sm hover:bg-white hover:text-red-600"
                                title="Delete"
                                @click="confirmStreamDelete([row.music_on_hold_uuid])"
                            >
                                <TrashIcon class="h-4 w-4" />
                            </button>
                        </div>

                        <div class="flex flex-col items-center px-6 pb-5 pt-8">
                            <div class="relative flex h-20 w-20 items-center justify-center rounded-full bg-indigo-50 text-indigo-500 ring-1 ring-indigo-100 transition group-hover:scale-105">
                                <MusicalNoteIcon class="h-9 w-9" />
                                <button
                                    v-if="row.files.length"
                                    type="button"
                                    class="absolute -bottom-1 -right-1 flex h-9 w-9 items-center justify-center rounded-full bg-indigo-600 text-white shadow-sm transition hover:bg-indigo-500"
                                    title="Play first track"
                                    @click.stop="quickPlay(row)"
                                >
                                    <PlayIcon class="h-4 w-4" />
                                </button>
                            </div>

                            <h3
                                class="mt-4 w-full truncate text-center font-semibold text-gray-900"
                                :title="row.music_on_hold_name"
                            >
                                {{ row.music_on_hold_name }}
                            </h3>
                            <p
                                v-if="filterData.showGlobal && row.domain_label"
                                class="mt-0.5 truncate text-xs text-gray-500"
                                :title="row.domain_label"
                            >
                                {{ row.domain_label }}
                            </p>

                            <div class="mt-3 flex flex-wrap justify-center gap-1">
                                <Badge v-if="row.rate_label" :text="row.rate_label" v-bind="grayBadge" />
                                <Badge :text="row.music_on_hold_channels === '2' ? 'Stereo' : 'Mono'" v-bind="grayBadge" />
                                <Badge
                                    :text="row.music_on_hold_shuffle === 'true' ? 'Shuffle' : 'Ordered'"
                                    v-bind="row.music_on_hold_shuffle === 'true' ? blueBadge : grayBadge"
                                />
                                <Badge v-if="row.music_on_hold_chime_list" text="Chime" v-bind="amberBadge" />
                            </div>
                        </div>

                        <div class="mt-auto border-t border-gray-100 px-6 py-3 text-center text-xs text-gray-500">
                            {{ row.files.length }} file{{ row.files.length === 1 ? "" : "s" }}
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-6">
                <Paginator
                    :previous="data.prev_page_url"
                    :next="data.next_page_url"
                    :from="data.from"
                    :to="data.to"
                    :total="data.total"
                    :currentPage="data.current_page"
                    :lastPage="data.last_page"
                    :links="data.links"
                    :page-size="perPage"
                    :page-size-options="props.pagination?.per_page_options ?? []"
                    :show-page-size-selector="true"
                    @pagination-change-page="changePage"
                    @page-size-change="handlePageSizeChange"
                />
            </div>
        </div>
    </div>

    <MusicOnHoldDrawer
        :show="showDrawer"
        :stream="drawerStream"
        :permissions="permissions"
        @close="closeDrawer"
        @edit="editFromDrawer"
        @delete="deleteFromDrawer"
        @delete-file="confirmFileDelete(drawerStream, $event)"
        @download="downloadFile"
        @upload="uploadFromDrawer"
    />

    <MusicOnHoldForm
        :show="showForm"
        :loading="loadingForm"
        :header="formHeader"
        :mode="formMode"
        :routes="routes"
        :options="itemOptions"
        :permissions="permissions"
        @close="closeForm"
        @error="handleError"
        @success="showNotification"
        @refresh-data="refreshCurrentPage"
    />

    <AddEditItemModal :show="showUploadModal" :loading="loadingForm || formSubmitting" header="Upload Music on Hold" custom-class="sm:max-w-2xl" @close="closeUploadModal">
        <template #modal-body>
            <Vueform :key="uploadFormKey" ref="uploadForm$" :endpoint="false" :default="uploadDefaultValues">
                <SelectElement name="music_on_hold_uuid" :items="uploadStreamOptions" label="Stream"
                    :native="false" :floating="false" :columns="{ container: 12, sm: 6 }" />

                <SelectElement v-if="permissions.manage_domain" name="domain_uuid" :items="itemOptions.domains"
                    label="Domain" :native="false" :floating="false" :columns="{ container: 12, sm: 6 }"
                    :conditions="[() => !selectedUploadStreamUuid]" />

                <TextElement name="music_on_hold_name" label="New Stream Name" :floating="false"
                    :columns="{ container: 12, sm: 6 }" :error="formErrors.music_on_hold_name?.[0]"
                    :conditions="[() => !selectedUploadStreamUuid]" />

                <FileElement name="file" label="Audio File" accept=".wav,.mp3,.ogg"
                    description="The file will be converted to mono WAV at 8 and 16 kHz." :upload-temp-endpoint="false"
                    :remove-temp-endpoint="false" :remove-endpoint="false" :drop="true"
                    :error="formErrors.file?.[0]" @change="handleVueformFileUpload"
                    :columns="{ container: 12 }" />

                <GroupElement name="button_container" />

                <ButtonElement name="cancel" :secondary="true" :submits="false" align="right"
                    :columns="{ container: 12, sm: 6 }" @click="closeUploadModal">
                    Cancel
                </ButtonElement>

                <ButtonElement name="upload" :loading="formSubmitting" :submits="false" align="right"
                    :columns="{ container: 12, sm: 6 }" @click="submitUpload">
                    Upload
                </ButtonElement>
            </Vueform>
        </template>
    </AddEditItemModal>

    <AddEditItemModal :show="showPlayerModal" :loading="false" :header="selectedFile?.name || 'Music on Hold'" custom-class="sm:max-w-3xl" @close="showPlayerModal = false">
        <template #modal-body>
            <AudioPlayer
                v-if="selectedFile?.download_url"
                :url="selectedFile.download_url"
                :download-url="selectedFile.download_url"
                :file-name="selectedFile.name"
            />
        </template>
    </AddEditItemModal>

    <ConfirmationModal
        :show="showConfirmationModal"
        :loading="formSubmitting"
        :header="confirmationHeader"
        :text="confirmationText"
        :confirm-button-label="confirmationButtonLabel"
        cancel-button-label="Cancel"
        @close="showConfirmationModal = false"
        @confirm="confirmAction"
    />

    <Notification :show="notificationShow" :type="notificationType" :messages="notificationMessages" @update:show="hideNotification" />
</template>

<script setup>
import { computed, h, onMounted, ref, watch } from "vue";
import axios from "axios";
import MainLayout from "../Layouts/MainLayout.vue";
import DataTable from "./components/general/DataTable.vue";
import TableColumnHeader from "./components/general/TableColumnHeader.vue";
import TableField from "./components/general/TableField.vue";
import Paginator from "./components/general/Paginator.vue";
import Loading from "./components/general/Loading.vue";
import Badge from "./components/general/Badge.vue";
import AddEditItemModal from "./components/modal/AddEditItemModal.vue";
import ConfirmationModal from "./components/modal/ConfirmationModal.vue";
import Notification from "./components/notifications/Notification.vue";
import AudioPlayer from "./components/general/AudioPlayer.vue";
import MusicOnHoldForm from "./components/forms/MusicOnHoldForm.vue";
import MusicOnHoldDrawer from "./components/MusicOnHoldDrawer.vue";
import {
    ArrowPathIcon,
    ArrowDownTrayIcon,
    ChevronDownIcon,
    ChevronUpIcon,
    ListBulletIcon,
    MagnifyingGlassIcon,
    MusicalNoteIcon,
    PencilSquareIcon,
    PlayCircleIcon,
    PlayIcon,
    Squares2X2Icon,
    TrashIcon,
} from "@heroicons/vue/24/solid";

const ViewToggle = {
    name: "ViewToggle",
    props: { modelValue: { type: String, default: "grid" } },
    emits: ["update:modelValue"],
    setup(props, { emit }) {
        const baseBtn = "inline-flex items-center justify-center px-2.5 py-1.5 text-sm font-medium transition";
        const select = (mode) => emit("update:modelValue", mode);
        return () =>
            h("div", { class: "inline-flex overflow-hidden rounded-md shadow-sm ring-1 ring-inset ring-gray-300" }, [
                h(
                    "button",
                    {
                        type: "button",
                        title: "Grid view",
                        class: [
                            baseBtn,
                            props.modelValue === "grid" ? "bg-indigo-600 text-white" : "bg-white text-gray-700 hover:bg-gray-50",
                        ],
                        onClick: () => select("grid"),
                    },
                    [h(Squares2X2Icon, { class: "h-4 w-4" })]
                ),
                h(
                    "button",
                    {
                        type: "button",
                        title: "List view",
                        class: [
                            baseBtn,
                            "border-l border-gray-300",
                            props.modelValue === "list" ? "bg-indigo-600 text-white" : "bg-white text-gray-700 hover:bg-gray-50",
                        ],
                        onClick: () => select("list"),
                    },
                    [h(ListBulletIcon, { class: "h-4 w-4" })]
                ),
            ]);
    },
};

const props = defineProps({
    routes: Object,
    permissions: Object,
    pagination: Object,
});

const routes = props.routes;
const permissions = props.permissions;
const perPage = ref(props.pagination?.per_page);

const VIEW_MODE_KEY = "moh:viewMode";

const data = ref({
    data: [],
    current_page: 1,
    from: 0,
    last_page: 1,
    links: [],
    next_page_url: null,
    prev_page_url: null,
    to: 0,
    total: 0,
});

const loading = ref(false);
const loadingForm = ref(false);
const formSubmitting = ref(false);
const showForm = ref(false);
const showUploadModal = ref(false);
const showPlayerModal = ref(false);
const showConfirmationModal = ref(false);
const showDrawer = ref(false);
const drawerStream = ref(null);
const notificationShow = ref(false);
const notificationType = ref(null);
const notificationMessages = ref(null);
const selectedItems = ref([]);
const selectAll = ref(false);
const formMode = ref("create");
const formErrors = ref({});
const selectedFile = ref(null);
const uploadForm$ = ref(null);
const uploadFormKey = ref(0);
const uploadFile = ref(null);
const uploadInitialStreamUuid = ref("");
const confirmAction = ref(() => {});
const confirmationHeader = ref("Are you sure?");
const confirmationText = ref("");
const confirmationButtonLabel = ref("Continue");
const filterData = ref({ search: null, showGlobal: false });
const sortData = ref({ name: "music_on_hold_name", order: "asc" });
const viewMode = ref(loadViewMode());

const itemOptions = ref({
    item: {},
    rates: [],
    domains: [],
    current_domain_uuid: null,
    streams: [],
    chime_options: [],
});

const grayBadge = { backgroundColor: "bg-gray-50", textColor: "text-gray-700", ringColor: "ring-gray-600/20" };
const blueBadge = { backgroundColor: "bg-blue-50", textColor: "text-blue-700", ringColor: "ring-blue-600/20" };
const amberBadge = { backgroundColor: "bg-amber-50", textColor: "text-amber-700", ringColor: "ring-amber-600/20" };

const columnCount = computed(() => filterData.value.showGlobal ? 6 : 5);
const formHeader = computed(() => formMode.value === "create"
    ? "Create stream"
    : `Update stream - ${itemOptions.value?.item?.music_on_hold_name || "Loading..."}`);

const sortParam = computed(() => sortData.value.order === "desc" ? `-${sortData.value.name}` : sortData.value.name);
const uploadStreamOptions = computed(() => [
    { label: "New stream", value: "" },
    ...(itemOptions.value.streams ?? []),
]);

const uploadDefaultValues = computed(() => ({
    music_on_hold_uuid: uploadInitialStreamUuid.value || "",
    music_on_hold_name: "",
    domain_uuid: itemOptions.value.current_domain_uuid
        ?? itemOptions.value.domains.find((domain) => domain.value)?.value
        ?? null,
}));

const selectedUploadStreamUuid = computed(() => uploadForm$.value?.data?.music_on_hold_uuid ?? "");
const selectablePageIds = computed(() => data.value.data
    .filter((item) => item.can_modify)
    .map((item) => item.music_on_hold_uuid));

const bulkActions = computed(() => {
    if (!permissions.destroy) {
        return [];
    }

    return [{ id: "bulk_delete", label: "Delete", icon: "TrashIcon" }];
});

const selectPageItems = computed({
    get() {
        return selectablePageIds.value.length > 0
            && selectablePageIds.value.every((id) => selectedItems.value.includes(id));
    },
    set(value) {
        if (value) {
            selectedItems.value = Array.from(new Set([...selectedItems.value, ...selectablePageIds.value]));
            return;
        }

        selectedItems.value = selectedItems.value.filter((id) => !selectablePageIds.value.includes(id));
        selectAll.value = false;
    },
});

watch(
    () => data.value.data,
    (rows) => {
        if (!showDrawer.value || !drawerStream.value) return;
        const fresh = rows.find((r) => r.music_on_hold_uuid === drawerStream.value.music_on_hold_uuid);
        if (fresh) {
            drawerStream.value = fresh;
        } else {
            closeDrawer();
        }
    }
);

onMounted(() => fetchData());

function loadViewMode() {
    try {
        const stored = window.localStorage.getItem(VIEW_MODE_KEY);
        return stored === "list" ? "list" : "grid";
    } catch (e) {
        return "grid";
    }
}

const setViewMode = (mode) => {
    viewMode.value = mode;
    try {
        window.localStorage.setItem(VIEW_MODE_KEY, mode);
    } catch (e) {
        // ignore — non-essential persistence
    }
};

const fetchData = (page = 1) => {
    loading.value = true;

    axios.get(routes.data_route, {
        params: {
            filter: filterData.value,
            sort: sortParam.value,
            per_page: perPage.value,
            page,
        },
    })
        .then((response) => {
            data.value = response.data;
        })
        .catch(handleError)
        .finally(() => {
            loading.value = false;
        });
};

const refreshCurrentPage = () => fetchData(data.value.current_page || 1);

const resetFilters = () => {
    filterData.value.search = null;
    clearSelection();
    fetchData(1);
};

const setSort = (column) => {
    if (sortData.value.name === column) {
        sortData.value.order = sortData.value.order === "asc" ? "desc" : "asc";
    } else {
        sortData.value.name = column;
        sortData.value.order = "asc";
    }

    fetchData(1);
};

const toggleNameSort = () => setSort("music_on_hold_name");

const handlePageSizeChange = (newPerPage) => {
    perPage.value = newPerPage;
    clearSelection();
    fetchData(1);
};

const changePage = (url) => {
    if (!url) return;
    fetchData(Number(new URL(url, window.location.origin).searchParams.get("page") || 1));
};

const showAll = () => {
    filterData.value.showGlobal = true;
    clearSelection();
    fetchData(1);
};

const showLocal = () => {
    filterData.value.showGlobal = false;
    clearSelection();
    fetchData(1);
};

const openCreateForm = () => {
    formMode.value = "create";
    showForm.value = true;
    getItemOptions();
};

const openEditForm = (uuid) => {
    formMode.value = "update";
    showForm.value = true;
    getItemOptions(uuid);
};

const closeForm = () => {
    showForm.value = false;
    formMode.value = "create";
    resetItemOptions();
};

const openUploadModal = (streamUuid = "") => {
    formErrors.value = {};
    uploadInitialStreamUuid.value = streamUuid;
    showUploadModal.value = true;
    resetUploadForm();
    getItemOptions();
};

const closeUploadModal = () => {
    showUploadModal.value = false;
    formErrors.value = {};
    uploadInitialStreamUuid.value = "";
    resetUploadForm();
};

const openDrawer = (row) => {
    drawerStream.value = row;
    showDrawer.value = true;
};

const closeDrawer = () => {
    showDrawer.value = false;
    drawerStream.value = null;
};

const editFromDrawer = () => {
    if (!drawerStream.value) return;
    const uuid = drawerStream.value.music_on_hold_uuid;
    closeDrawer();
    openEditForm(uuid);
};

const deleteFromDrawer = () => {
    if (!drawerStream.value) return;
    confirmStreamDelete([drawerStream.value.music_on_hold_uuid]);
};

const uploadFromDrawer = () => {
    if (!drawerStream.value) return;
    openUploadModal(drawerStream.value.music_on_hold_uuid);
};

const quickPlay = (row) => {
    if (!row.files?.length) return;
    openDrawer(row);
};

const getItemOptions = (itemUuid = null) => {
    loadingForm.value = true;

    axios.post(routes.item_options, itemUuid ? { itemUuid, filter: filterData.value } : { filter: filterData.value })
        .then((response) => {
            itemOptions.value = response.data;
        })
        .catch((error) => {
            closeForm();
            closeUploadModal();
            handleError(error);
        })
        .finally(() => {
            loadingForm.value = false;
        });
};

const submitUpload = () => {
    formErrors.value = {};
    formSubmitting.value = true;
    const uploadData = uploadForm$.value?.data ?? {};

    const requestData = new FormData();
    requestData.append("music_on_hold_uuid", uploadData.music_on_hold_uuid || "");
    requestData.append("music_on_hold_name", uploadData.music_on_hold_name || "");
    requestData.append("domain_uuid", uploadData.domain_uuid || "");
    if (uploadFile.value) {
        requestData.append("file", uploadFile.value);
    }

    axios.post(routes.upload, requestData, { headers: { "Content-Type": "multipart/form-data" } })
        .then((response) => {
            closeUploadModal();
            showNotification("success", response.data.messages);
            fetchData(1);
        })
        .catch((error) => handleError(error, true))
        .finally(() => {
            formSubmitting.value = false;
        });
};

const handleVueformFileUpload = (newValue) => {
    uploadFile.value = newValue instanceof File ? newValue : null;
};

const handleBulkAction = (action) => {
    if (action === "bulk_delete") {
        confirmStreamDelete(selectedItems.value);
    }
};

const confirmStreamDelete = (items) => {
    showConfirmationModal.value = true;
    confirmationHeader.value = "Confirm Deletion";
    confirmationText.value = "This action will permanently delete the selected music on hold stream(s).";
    confirmationButtonLabel.value = "Delete";
    confirmAction.value = () => deleteStreams(items);
};

const confirmFileDelete = (stream, file) => {
    if (!stream || !file) return;
    showConfirmationModal.value = true;
    confirmationHeader.value = "Confirm File Deletion";
    confirmationText.value = `Delete ${file.name}?`;
    confirmationButtonLabel.value = "Delete";
    confirmAction.value = () => deleteFile(stream, file);
};

const confirmReload = () => {
    showConfirmationModal.value = true;
    confirmationHeader.value = "Reload mod_local_stream";
    confirmationText.value = "Only continue if there are no current calls on hold being played. FreeSWITCH will not reload mod_local_stream while it is in use.";
    confirmationButtonLabel.value = "Reload";
    confirmAction.value = executeReload;
};

const executeReload = () => {
    formSubmitting.value = true;

    axios.post(routes.reload)
        .then((response) => {
            closeConfirmation();
            showNotification("success", response.data.messages);
        })
        .catch((error) => {
            closeConfirmation();
            handleError(error);
        })
        .finally(() => {
            formSubmitting.value = false;
        });
};

const deleteStreams = (items) => {
    formSubmitting.value = true;

    axios.post(routes.bulk_delete, { items })
        .then((response) => {
            closeConfirmation();
            clearSelection();
            closeDrawer();
            showNotification("success", response.data.messages);
            refreshCurrentPage();
        })
        .catch(handleError)
        .finally(() => {
            formSubmitting.value = false;
        });
};

const deleteFile = (stream, file) => {
    formSubmitting.value = true;

    axios.post(routes.file_delete, {
        music_on_hold_uuid: stream.music_on_hold_uuid,
        file: file.name,
    })
        .then((response) => {
            closeConfirmation();
            showNotification("success", response.data.messages);
            refreshCurrentPage();
        })
        .catch(handleError)
        .finally(() => {
            formSubmitting.value = false;
        });
};

const handleSelectAll = () => {
    axios.post(routes.select_all, { filter: filterData.value })
        .then((response) => {
            selectedItems.value = response.data.items;
            selectAll.value = true;
            showNotification("success", response.data.messages);
        })
        .catch((error) => {
            clearSelection();
            handleError(error);
        });
};

const clearSelection = () => {
    selectedItems.value = [];
    selectAll.value = false;
};

const openPlayer = (stream, file) => {
    selectedFile.value = {
        ...file,
        stream: stream.music_on_hold_name,
    };
    showPlayerModal.value = true;
};

const downloadFile = (url) => {
    if (!url) return;
    const link = document.createElement("a");
    link.href = url;
    link.download = "";
    document.body.appendChild(link);
    link.click();
    link.remove();
};

const closeConfirmation = () => {
    showConfirmationModal.value = false;
    confirmAction.value = () => {};
};

const resetItemOptions = () => {
    itemOptions.value = { item: {}, rates: [], domains: [], current_domain_uuid: null, streams: [], chime_options: [] };
};

const resetUploadForm = () => {
    uploadFile.value = null;
    uploadFormKey.value++;
};

const hideNotification = () => {
    notificationShow.value = false;
    notificationType.value = null;
    notificationMessages.value = null;
};

const showNotification = (type, messages = null) => {
    notificationType.value = messages ? type : "success";
    notificationMessages.value = messages ?? type;
    notificationShow.value = true;
};

const handleError = (error, keepModalOpen = false) => {
    if (error?.response?.data?.errors) {
        formErrors.value = error.response.data.errors;
    } else if (!keepModalOpen) {
        formErrors.value = {};
    }

    notificationType.value = "error";
    notificationMessages.value = normalizeMessages(error);
    notificationShow.value = true;
};

const normalizeMessages = (error) => {
    const payload = error?.response?.data;
    if (payload?.errors) return payload.errors;
    if (payload?.messages) return payload.messages;
    if (payload?.message) return { request: [payload.message] };
    if (error?.message) return { request: [error.message] };

    return { request: ["An unexpected error occurred."] };
};

const formatDate = (value) => {
    if (!value) return "-";
    const date = new Date(value);
    return Number.isNaN(date.getTime()) ? value : date.toLocaleString();
};
</script>
