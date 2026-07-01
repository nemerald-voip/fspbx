<template>
    <MainLayout />

    <div class="m-3">
        <DataTable @search-action="handleSearchButtonClick" @reset-filters="handleFiltersReset">
            <template #title>Devices</template>

            <template #filters>
                <div class="relative min-w-64 focus-within:z-10 mb-2 sm:mr-4">
                    <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                        <MagnifyingGlassIcon class="h-5 w-5 text-subtle" aria-hidden="true" />
                    </div>
                    <input type="text" v-model="filterData.search" name="mobile-search-candidate"
                        id="mobile-search-candidate"
                        class="block w-full rounded-md border-0 py-1.5 pl-10 text-heading ring-1 bg-surface ring-inset ring-strong placeholder:text-subtle focus:ring-2 focus:ring-inset focus:ring-focus sm:hidden"
                        placeholder="Search" @keydown.enter="handleSearchButtonClick" />
                    <input type="text" v-model="filterData.search" name="desktop-search-candidate"
                        id="desktop-search-candidate"
                        class="hidden w-full rounded-md border-0 py-1.5 pl-10 text-sm leading-6 text-heading ring-1 bg-surface ring-inset ring-strong placeholder:text-subtle focus:ring-2 focus:ring-inset focus:ring-focus sm:block"
                        placeholder="Search" @keydown.enter="handleSearchButtonClick" />
                </div>
            </template>

            <template #action>
                <button v-if="page.props.auth.can.device_create" type="button"
                    @click.prevent="handleCreateButtonClick()"
                    class="rounded-md bg-accent px-2.5 py-1.5 text-sm font-semibold text-on-accent shadow-sm hover:bg-accent-hover focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent">
                    Create
                </button>

                <button v-if="page.props.auth.can.manage_cloud_provision_providers" type="button"
                    @click.prevent="handleCloudProvisioningButtonClick()"
                    class="rounded-md bg-surface px-2.5 py-1.5 ml-2 sm:ml-4 text-sm font-semibold text-heading shadow-sm ring-1 ring-inset ring-strong hover:bg-surface-2">
                    Cloud
                </button>

                <button v-if="permissions.device_import" type="button" @click.prevent="handleImportButtonClick()"
                    class="inline-flex items-center gap-x-1.5 rounded-md bg-surface px-2.5 py-1.5 ml-2 sm:ml-4 text-sm font-semibold text-heading shadow-sm ring-1 ring-inset ring-strong hover:bg-surface-2">
                    <DocumentArrowUpIcon class="h-5 w-5" aria-hidden="true" />
                    Import CSV
                </button>

                <a v-if="permissions.device_key_template_view" type="button" :href="routes.key_templates"
                    class="rounded-md bg-surface px-2.5 py-1.5 ml-2 sm:ml-4 text-sm font-semibold text-heading shadow-sm ring-1 ring-inset ring-strong hover:bg-surface-2">
                    Key Templates
                </a>

                <a v-if="page.props.auth.can.device_profile_index" type="button" href="app/devices/device_profiles.php"
                    class="rounded-md bg-surface px-2.5 py-1.5 ml-2 sm:ml-4 text-sm font-semibold text-heading shadow-sm ring-1 ring-inset ring-strong hover:bg-surface-2">
                    Profiles
                </a>

                <button v-if="!filterData.showGlobal && page.props.auth.can.device_view_global" type="button"
                    @click.prevent="handleShowGlobal()"
                    class="rounded-md bg-surface px-2.5 py-1.5 ml-2 sm:ml-4 text-sm font-semibold text-heading shadow-sm ring-1 ring-inset ring-strong hover:bg-surface-2">
                    Show global
                </button>

                <button v-if="filterData.showGlobal && page.props.auth.can.device_view_global" type="button"
                    @click.prevent="handleShowLocal()"
                    class="rounded-md bg-surface px-2.5 py-1.5 ml-2 sm:ml-4 text-sm font-semibold text-heading shadow-sm ring-1 ring-inset ring-strong hover:bg-surface-2">
                    Show local
                </button>
            </template>

            <template #navigation>
                <Paginator :previous="data.prev_page_url" :next="data.next_page_url" :from="data.from" :to="data.to"
                    :total="data.total" :currentPage="data.current_page" :lastPage="data.last_page" :links="data.links"
                    @pagination-change-page="renderRequestedPage" :bulk-actions="bulkActions"
                    @bulk-action="handleBulkActionRequest" :has-selected-items="selectedItems.length > 0" />
            </template>
            <template #table-header>

                <TableColumnHeader header="MAC Address"
                    class="flex whitespace-nowrap px-4 py-3.5 text-left text-sm font-semibold text-heading items-center justify-start">
                    <input type="checkbox" v-model="selectPageItems" @change="handleSelectPageItems"
                        class="h-4 w-4 rounded border-strong text-accent-fg">
                    <!-- <BulkActionButton :actions="bulkActions" @bulk-action="handleBulkActionRequest"
                        :has-selected-items="selectedItems.length > 0" /> -->
                    <div class="pl-4 flex items-center cursor-pointer select-none"
                        @click="handleSortRequest('device_address')">
                        <span class="mr-2">MAC Address</span>
                        <ChevronUpIcon v-if="sortData.name === 'device_address' && sortData.order === 'asc'" class="h-4 w-4 text-muted" />
                        <ChevronDownIcon v-else-if="sortData.name === 'device_address' && sortData.order === 'desc'" class="h-4 w-4 text-muted" />
                    </div>                </TableColumnHeader>
                <TableColumnHeader v-if="filterData.showGlobal" header="Domain"
                    class="px-2 py-3.5 text-left text-sm font-semibold text-heading" />

                <TableColumnHeader header="Template"
                    class="px-2 py-3.5 text-left text-sm font-semibold text-heading" />
                <TableColumnHeader header="Profile / Key Template" class="px-2 py-3.5 text-left text-sm font-semibold text-heading" />
                <TableColumnHeader v-if="!filterData.showGlobal" header="Assigned extension"
                    class="px-2 py-3.5 text-left text-sm font-semibold text-heading" />
                <TableColumnHeader class="px-2 py-3.5 text-left text-sm font-semibold text-heading">
                    <div class="flex items-center cursor-pointer select-none" @click="handleSortRequest('device_description')">
                        <span class="mr-2">Description</span>
                        <ChevronUpIcon v-if="sortData.name === 'device_description' && sortData.order === 'asc'" class="h-4 w-4 text-muted" />
                        <ChevronDownIcon v-else-if="sortData.name === 'device_description' && sortData.order === 'desc'" class="h-4 w-4 text-muted" />
                    </div>
                </TableColumnHeader>
                <TableColumnHeader class="px-2 py-3.5 text-left text-sm font-semibold text-heading">
                    <div class="flex items-center cursor-pointer select-none" @click="handleSortRequest('device_provisioned_date')">
                        <span class="mr-2">Last Contact</span>
                        <ChevronUpIcon v-if="sortData.name === 'device_provisioned_date' && sortData.order === 'asc'" class="h-4 w-4 text-muted" />
                        <ChevronDownIcon v-else-if="sortData.name === 'device_provisioned_date' && sortData.order === 'desc'" class="h-4 w-4 text-muted" />
                    </div>
                </TableColumnHeader>
                                <TableColumnHeader header="Cloud" class="px-2 py-3.5 text-left text-sm font-semibold text-heading" />
                <TableColumnHeader header="" class="px-2 py-3.5 text-right text-sm font-semibold text-heading" />
            </template>

            <template v-if="selectPageItems" v-slot:current-selection>
                <td colspan="9">
                    <div class="text-sm text-center m-2">
                        <span class="font-semibold ">{{ selectedItems.length }} </span> items are selected.
                        <button v-if="!selectAll && selectedItems.length !== data.total"
                            class="text-info rounded py-2 px-2 hover:bg-info-subtle  hover:text-info focus:outline-none focus:ring-1 focus:bg-info-subtle focus:ring-focus transition duration-500 ease-in-out"
                            @click="handleSelectAll">
                            Select all {{ data.total }} items
                        </button>
                        <button v-if="selectAll"
                            class="text-info rounded py-2 px-2 hover:bg-info-subtle  hover:text-info focus:outline-none focus:ring-1 focus:bg-info-subtle focus:ring-focus transition duration-500 ease-in-out"
                            @click="handleClearSelection">
                            Clear selection
                        </button>
                    </div>
                </td>
            </template>

            <template #table-body>
                <tr v-for="row in data.data" :key="row.device_uuid">
                    <TableField class="whitespace-nowrap px-4 py-2 text-sm text-muted"
                        :text="row.device_address_formatted">
                        <div class="flex items-center">
                            <input v-if="row.device_address" v-model="selectedItems" type="checkbox" name="action_box[]"
                                :value="row.device_uuid" class="h-4 w-4 rounded border-strong text-accent-fg">
                            <div class="ml-4"
                                :class="{ 'cursor-pointer hover:text-heading': page.props.auth.can.device_update, }"
                                @click="page.props.auth.can.device_update && handleEditButtonClick(row.device_uuid)">
                                {{ row.device_address_formatted }}
                            </div>
                            <ejs-tooltip :content="tooltipCopyContent" position='TopLeft' class="ml-2"
                                @click="handleCopyToClipboard(row.device_address)" target="#copy_tooltip_target">
                                <div id="copy_tooltip_target">
                                    <ClipboardDocumentIcon
                                        class="h-5 w-5 text-muted hover:text-heading pt-1 cursor-pointer" />
                                </div>
                            </ejs-tooltip>
                        </div>
                    </TableField>

                    <TableField v-if="filterData.showGlobal" class="whitespace-nowrap px-2 py-2 text-sm text-muted"
                        :text="row.domain?.domain_description">
                        <ejs-tooltip :content="row.domain?.domain_name" position='TopLeft'
                            target="#domain_tooltip_target">
                            <div id="domain_tooltip_target">
                                {{ row.domain?.domain_description }}
                            </div>
                        </ejs-tooltip>
                    </TableField>
                    <TableField class="whitespace-nowrap px-2 py-2 text-sm text-muted" :text="row.template?.name
                        ? (() => {
                            const t = row.template;

                            const base = t.vendor ? `${t.vendor}/${t.name}` : t.name;

                            const suffixParts = [];
                            if (t.version) suffixParts.push(`v${t.version}`);

                            // show revision ONLY if it's non-zero / meaningful
                            const rev = Number(t.revision);
                            if (Number.isFinite(rev) && rev > 0) suffixParts.push(`r${rev}`);

                            return suffixParts.length ? `${base} (${suffixParts.join(', ')})` : base;
                        })()
                        : (row.device_template || '—')" />

                    <TableField class="whitespace-nowrap px-2 py-2 text-sm text-muted">
                        <template #default>
                            <div v-if="row.profile?.device_profile_name || row.key_template?.name">
                                <div v-if="row.profile?.device_profile_name">
                                    <span class="font-semibold">Profile:</span>
                                    <span> {{ row.profile.device_profile_name }}</span>
                                </div>
                                <div v-if="row.key_template?.name">
                                    <span class="font-semibold">Key Template:</span>
                                    <span> {{ row.key_template.name }}</span>
                                </div>
                            </div>
                            <div v-else>—</div>
                        </template>
                    </TableField>
                    <TableField v-if="!filterData.showGlobal" class="whitespace-nowrap px-2 py-2 text-sm text-muted">
                        <template #default>
                            <div v-if="row.lines?.length === 0">—</div>
                            <div v-else>
                                <div v-for="line in [...row.lines].sort((a, b) => Number(a.line_number) - Number(b.line_number))"
                                    :key="line.device_line_uuid">
                                    <span v-if="row.lines.length > 1" class="font-semibold">
                                        Line {{ line.line_number }}:
                                    </span>
                                    <span>{{ line.external_line ? line.auth_id : (line.extension?.name_formatted || line.auth_id) }}</span>
                                </div>
                            </div>
                        </template>
                    </TableField>
                    <TableField class="whitespace-nowrap px-2 py-2 text-sm text-muted"
                        :text="row.device_description" />

                    <TableField class="whitespace-nowrap px-2 py-2 text-sm text-muted">
                        <div v-if="row.device_provisioned_ip || row.device_provisioned_agent" 
                            class="group relative inline-block cursor-help focus:outline-none" 
                            tabindex="0">
                            
                            <span>{{ row.device_provisioned_date_formatted ?? row.device_provisioned_date }}</span>
                            
                            <div class="invisible opacity-0 group-hover:visible group-hover:opacity-100 group-focus:visible group-focus:opacity-100 transition-opacity duration-300 absolute z-50 bottom-full left-1/2 -translate-x-1/2 pb-2">
                                
                                <div class="px-3 py-2 text-xs leading-relaxed text-white bg-gray-900 rounded shadow-lg whitespace-nowrap cursor-text select-text">
                                    <div v-if="row.device_provisioned_ip">IP: {{ row.device_provisioned_ip }}</div>
                                    <div v-if="row.device_provisioned_agent">Agent: {{ row.device_provisioned_agent }}</div>
                                    
                                    <div class="absolute top-full left-1/2 -translate-x-1/2 border-4 border-transparent border-t-gray-900"></div>
                                </div>
                                
                            </div>
                            
                        </div>
                        <div v-else>
                            {{ row.device_provisioned_date_formatted ?? row.device_provisioned_date }}
                        </div>
                    </TableField>

                    <TableField class="whitespace-nowrap px-2 py-2 text-sm text-muted">
                        <div class="flex items-center whitespace-nowrap">
                            <ejs-tooltip :content="!row.cloud_provisioning ? 'Not provisioned'
                                : (row.cloud_provisioning.status === 'success' && row.cloud_provisioning.last_action === 'register') ? 'Provisioned'
                                    : row.cloud_provisioning.status === 'pending' ? 'Pending'
                                        : row.cloud_provisioning.status === 'error' ? 'Error'
                                            : 'Not provisioned'" position='TopCenter'
                                target="#cloud_status_tooltip_target">
                                <div id="cloud_status_tooltip_target">
                                    <CloudIcon :class="[
                                        'h-9 w-9 py-2 rounded-full',
                                        !row.cloud_provisioning ? 'text-subtle'
                                            : (row.cloud_provisioning.status === 'success' && row.cloud_provisioning.last_action === 'register') ? 'text-success'
                                                : row.cloud_provisioning.status === 'error' ? 'text-danger'
                                                    : row.cloud_provisioning.status === 'pending' ? 'text-warning'
                                                        : 'text-subtle'
                                    ]" />
                                </div>
                            </ejs-tooltip>
                        </div>
                    </TableField>
                    <TableField class="whitespace-nowrap px-2 py-1 text-sm text-muted">
                        <template #action-buttons>
                            <div class="flex items-center whitespace-nowrap justify-end">
                                <ejs-tooltip v-if="page.props.auth.can.device_update" :content="'Edit'"
                                    position='TopCenter' target="#destination_tooltip_target">
                                    <div id="destination_tooltip_target">
                                        <PencilSquareIcon @click="handleEditButtonClick(row.device_uuid)"
                                            class="h-9 w-9 transition duration-500 ease-in-out py-2 rounded-full text-subtle hover:bg-surface-3 hover:text-body active:bg-surface-3 active:duration-150 cursor-pointer" />

                                    </div>
                                </ejs-tooltip>

                                <ejs-tooltip v-if="permissions.device_provisioning_preview"
                                    :content="'Preview provisioning'" position='TopCenter'
                                    :target="'#provisioning_preview_tooltip_target_' + row.device_uuid">
                                    <div :id="'provisioning_preview_tooltip_target_' + row.device_uuid">
                                        <MagnifyingGlassIcon @click="handleProvisioningPreview(row.device_uuid)"
                                            class="h-9 w-9 transition duration-500 ease-in-out py-2 rounded-full text-subtle hover:bg-surface-3 hover:text-body active:bg-surface-3 active:duration-150 cursor-pointer" />
                                    </div>
                                </ejs-tooltip>

                                <ejs-tooltip :content="'Restart device'" position='TopCenter'
                                    target="#restart_tooltip_target">
                                    <div id="restart_tooltip_target">
                                        <RestartIcon @click="handleRestart(row.device_uuid)"
                                            class="h-9 w-9 transition duration-500 ease-in-out py-2 rounded-full text-subtle hover:bg-surface-3 hover:text-body active:bg-surface-3 active:duration-150 cursor-pointer" />
                                    </div>
                                </ejs-tooltip>

                                <ejs-tooltip v-if="page.props.auth.can.device_destroy" :content="'Delete'"
                                    position='TopCenter' target="#delete_tooltip_target">
                                    <div id="delete_tooltip_target">
                                        <TrashIcon @click="handleSingleItemDeleteRequest(row.device_uuid)"
                                            class="h-9 w-9 transition duration-500 ease-in-out py-2 rounded-full text-subtle hover:bg-surface-3 hover:text-body active:bg-surface-3 active:duration-150 cursor-pointer" />
                                    </div>
                                </ejs-tooltip>
                                <div class="relative z-20 ml-2">
                                    <AdvancedActionButton :actions="advancedActions"
                                        @advanced-action="(action) => handleAdvancedActionRequest(action, row.device_uuid)" />
                                </div>
                            </div>
                        </template>
                    </TableField>
                </tr>
            </template>
            <template #empty>
                <!-- Conditional rendering for 'no records' message -->
                <div v-if="data.data.length === 0" class="text-center my-5 ">
                    <MagnifyingGlassIcon class="mx-auto h-12 w-12 text-subtle" />
                    <h3 class="mt-2 text-sm font-semibold text-heading">No results found</h3>
                    <p class="mt-1 text-sm text-muted">
                        Adjust your search and try again.
                    </p>
                </div>
            </template>

            <template #loading>
                <Loading :show="loading" />
            </template>

            <template #footer>
                <Paginator :previous="data.prev_page_url" :next="data.next_page_url" :from="data.from" :to="data.to"
                    :total="data.total" :currentPage="data.current_page" :lastPage="data.last_page" :links="data.links"
                    :page-size="perPage" :page-size-options="props.pagination?.per_page_options ?? []"
                    :show-page-size-selector="true"
                    @pagination-change-page="renderRequestedPage" @page-size-change="handlePageSizeChange" />
            </template>
        </DataTable>
        <div class="px-4 sm:px-6 lg:px-8"></div>
    </div>

    <NotificationSimple :show="restartRequestNotificationErrorTrigger" :isSuccess="false" :header="'Warning'"
        :text="'Please select at least one device'" @update:show="restartRequestNotificationErrorTrigger = false" />
    <NotificationSimple :show="restartRequestNotificationSuccessTrigger" :isSuccess="true" :header="'Success'"
        :text="'Restart request has been submitted'" @update:show="restartRequestNotificationSuccessTrigger = false" />

    <CreateDeviceForm :show="showCreateModal" :options="itemOptions" :loading="isModalLoading"
        :header="'Create New Device'" @close="showCreateModal = false" @error="handleErrorResponse"
        @success="showNotification" @refresh-data="refreshCurrentPage" />

    <UpdateDeviceForm :show="showUpdateModal" :options="itemOptions" :loading="isModalLoading"
        :header="'Update Device - ' + (itemOptions?.item?.device_address_formatted ?? 'loading')"
        @close="showUpdateModal = false" @error="handleErrorResponse" @success="showNotification"
        @refresh-data="refreshCurrentPage" />

    <BulkUpdateDeviceForm :items="selectedItems" :options="itemOptions" :show="showBulkUpdateModal"
        :header="'Bulk Update'" :loading="isModalLoading" @close="handleModalClose"
        @refresh-data="refreshCurrentPage" />


    <CloudProvisioningSettings :show="showCloudProvisioningSettings" @close="showCloudProvisioningSettings = false"
        :header="'Cloud Provisioning Settings'" :loading="isModalLoading" :routes="routes" @error="handleErrorResponse"
        @success="showNotification" />


    <DeleteConfirmationModal :show="confirmationModalTrigger" @close="confirmationModalTrigger = false"
        @confirm="confirmDeleteAction" />

    <ConfirmationModal :show="confirmationRestartTrigger" @close="confirmationRestartTrigger = false"
        @confirm="confirmRestartAction" :header="'Are you sure?'" :text="'Confirm restart of selected devices.'"
        :confirm-button-label="'Restart'" cancel-button-label="Cancel" />

    <Notification :show="notificationShow" :type="notificationType" :messages="notificationMessages"
        @update:show="hideNotification" />

    <UploadModal :show="showUploadModal" @close="showUploadModal = false" :header="'Upload File'" @upload="uploadFile"
        @download-template="downloadTemplateFile" :is-submitting="isUploadingFile" :errors="uploadErrors" />

    <ImportDevicesModal v-if="showImportPreviewModal" :show="showImportPreviewModal" :options="itemOptions"
        :import-data="importPreviewData" :loading="isCommittingImport" @close="showImportPreviewModal = false"
        @success="handleImportSuccess" @error="handleErrorResponse" />

    <AddEditItemModal :show="showProvisioningPreviewModal" :header="provisioningPreviewHeader"
        :loading="isProvisioningPreviewLoading" customClass="sm:max-w-6xl h-[85vh] max-h-[85vh] flex flex-col"
        contentClass="flex min-h-0 flex-1 flex-col" bodyClass="min-h-0 flex-1 overflow-hidden"
        @close="closeProvisioningPreview">
        <template #modal-body>
            <div class="flex h-full min-h-0 flex-col">
                <div v-if="provisioningPreviewError" class="rounded-md bg-danger-subtle p-4 text-sm text-danger">
                    {{ provisioningPreviewError }}
                </div>

                <div v-else class="flex min-h-0 flex-1 flex-col gap-3">
                    <div class="grid gap-2 text-sm text-body sm:grid-cols-3">
                        <div>
                            <span class="font-semibold text-heading">Device:</span>
                            {{ provisioningPreviewData?.device?.device_address_formatted || provisioningPreviewData?.device?.device_address || '—' }}
                        </div>
                        <div>
                            <span class="font-semibold text-heading">Vendor:</span>
                            {{ provisioningPreviewData?.device?.device_vendor || '—' }}
                        </div>
                        <div>
                            <span class="font-semibold text-heading">Template:</span>
                            {{ provisioningPreviewTemplateLabel }}
                        </div>
                    </div>

                    <div v-if="provisioningPreviewFiles.length === 0"
                        class="flex min-h-0 flex-1 flex-col items-center justify-center rounded-md border border-dashed border-strong bg-surface-2 p-8 text-center">
                        <MagnifyingGlassIcon class="h-10 w-10 text-subtle" aria-hidden="true" />
                        <p class="mt-2 text-sm font-medium text-heading">No provisioning files generated</p>
                        <p class="mt-1 text-sm text-muted">This device's template did not produce any files to preview.</p>
                    </div>

                    <template v-else>
                        <div role="tablist" aria-label="Provisioning files"
                            class="flex flex-nowrap items-center gap-2 overflow-x-auto border-b border-default pb-2">
                            <button v-for="(file, index) in provisioningPreviewFiles" :key="file.flavor" type="button"
                                role="tab" :aria-selected="activeProvisioningPreviewFlavor === file.flavor"
                                :tabindex="activeProvisioningPreviewFlavor === file.flavor ? 0 : -1"
                                @click="activeProvisioningPreviewFlavor = file.flavor"
                                @keydown.left.prevent="focusProvisioningPreviewTab(index - 1, $event)"
                                @keydown.right.prevent="focusProvisioningPreviewTab(index + 1, $event)" :class="[
                                    activeProvisioningPreviewFlavor === file.flavor
                                        ? 'border-accent bg-accent-subtle text-accent-fg'
                                        : 'border-default bg-surface text-body hover:bg-surface-2',
                                    'shrink-0 whitespace-nowrap rounded-md border px-3 py-1.5 text-sm font-medium'
                                ]">
                                {{ file.filename }}
                            </button>
                        </div>

                        <div class="flex items-center justify-between gap-3">
                            <div class="min-w-0 text-sm text-body">
                                <span class="font-semibold text-heading">{{ activeProvisioningPreviewFile?.flavor || '—' }}</span>
                                <span v-if="activeProvisioningPreviewFile">
                                    · {{ activeProvisioningPreviewFile.mime }} · {{ formatBytes(activeProvisioningPreviewFile.bytes) }}
                                </span>
                            </div>
                            <div class="flex shrink-0 items-center gap-2">
                                <button type="button" @click="provisioningPreviewWrap = !provisioningPreviewWrap"
                                    :aria-pressed="provisioningPreviewWrap" :class="[
                                        provisioningPreviewWrap
                                            ? 'bg-accent-subtle text-accent-fg ring-accent'
                                            : 'bg-surface text-heading ring-strong hover:bg-surface-2',
                                        'rounded-md px-2.5 py-1.5 text-sm font-semibold shadow-sm ring-1 ring-inset'
                                    ]">
                                    Wrap
                                </button>
                                <button type="button" @click="copyProvisioningPreview"
                                    class="rounded-md bg-surface px-2.5 py-1.5 text-sm font-semibold text-heading shadow-sm ring-1 ring-inset ring-strong hover:bg-surface-2">
                                    Copy
                                </button>
                                <button type="button" @click="downloadProvisioningPreview"
                                    class="rounded-md bg-surface px-2.5 py-1.5 text-sm font-semibold text-heading shadow-sm ring-1 ring-inset ring-strong hover:bg-surface-2">
                                    Download
                                </button>
                            </div>
                        </div>

                        <div class="min-h-0 flex-1 overflow-hidden rounded-md border border-default">
                            <AceEditor
                                :key="`${activeProvisioningPreviewFile?.flavor}-${provisioningPreviewWrap}`"
                                :model-value="activeProvisioningPreviewFile?.content || ''"
                                :lang="activeProvisioningPreviewLang" theme="one_dark"
                                :options="{ readOnly: true, wrap: provisioningPreviewWrap, fontSize: 13, tabSize: 2, useWorker: false, highlightActiveLine: false, showGutter: true }"
                                height="100%" />
                        </div>
                    </template>
                </div>
            </div>
        </template>
    </AddEditItemModal>

    <AddEditItemModal :show="showDuplicateModal" :header="'Duplicate Device'" :loading="isModalLoading"
        @close="showDuplicateModal = false">
        <template #modal-body>
            <div class="p-6">
                <div class="mb-4">
                    <label for="new_mac" class="block text-sm font-medium leading-6 text-heading">
                        New MAC Address
                    </label>
                    <div class="mt-2">
                        <input type="text" id="new_mac" v-model="newMacAddress" placeholder="00:00:00:00:00:00"
                            class="block w-full rounded-md border-0 py-1.5 text-heading shadow-sm ring-1 bg-surface ring-inset ring-strong placeholder:text-subtle focus:ring-2 focus:ring-inset focus:ring-focus sm:text-sm sm:leading-6"
                            @keydown.enter="submitDuplicateRequest" />
                    </div>
                    <div v-if="formErrors?.new_mac_address" class="mt-2 text-sm text-danger">
                        {{ formErrors.new_mac_address[0] }}
                    </div>
                    <div v-if="formErrors?.server" class="mt-2 text-sm text-danger">
                        {{ formErrors.server[0] }}
                    </div>
                </div>

                <div class="mt-6 flex items-center justify-end gap-x-6">
                    <button type="button" @click="showDuplicateModal = false"
                        class="text-sm font-semibold leading-6 text-heading">Cancel</button>
                    <button type="button" @click="submitDuplicateRequest"
                        class="rounded-md bg-accent px-3 py-2 text-sm font-semibold text-on-accent shadow-sm hover:bg-accent-hover focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent">
                        Duplicate
                    </button>
                </div>
            </div>
        </template>
    </AddEditItemModal>

</template>

<script setup>
import { computed, onMounted, ref } from "vue";
import { usePage } from '@inertiajs/vue3'
import axios from 'axios';
import DataTable from "./components/general/DataTable.vue";
import TableColumnHeader from "./components/general/TableColumnHeader.vue";
import TableField from "./components/general/TableField.vue";
import Paginator from "./components/general/Paginator.vue";
import NotificationSimple from "./components/notifications/Simple.vue";
import DeleteConfirmationModal from "./components/modal/DeleteConfirmationModal.vue";
import ConfirmationModal from "./components/modal/ConfirmationModal.vue";
import Loading from "./components/general/Loading.vue";
import { registerLicense } from '@syncfusion/ej2-base';
import { MagnifyingGlassIcon, TrashIcon, PencilSquareIcon, CloudIcon, ChevronUpIcon, ChevronDownIcon } from "@heroicons/vue/24/solid";
import { ClipboardDocumentIcon, DocumentArrowUpIcon } from "@heroicons/vue/24/outline";
import { TooltipComponent as EjsTooltip } from "@syncfusion/ej2-vue-popups";
import BulkUpdateDeviceForm from "./components/forms/BulkUpdateDeviceForm.vue";
import MainLayout from "../Layouts/MainLayout.vue";
import RestartIcon from "./components/icons/RestartIcon.vue";
import CreateDeviceForm from "./components/forms/CreateDeviceForm.vue";
import UpdateDeviceForm from "./components/forms/UpdateDeviceForm.vue";
import Notification from "./components/notifications/Notification.vue";
import CloudProvisioningSettings from "./components/forms/CloudProvisioningSettings.vue";
import AdvancedActionButton from "./components/general/AdvancedActionButton.vue";
import AddEditItemModal from "./components/modal/AddEditItemModal.vue";
import AceEditor from "./components/general/AceEditor.vue";
import UploadModal from "./components/modal/UploadModal.vue";
import ImportDevicesModal from "./components/modal/ImportDevicesModal.vue";

const page = usePage()
const props = defineProps({
    routes: Object,
    permissions: {
        type: Object,
        default: () => ({}),
    },
    pagination: Object,
})
const routes = props.routes
const permissions = props.permissions
const itemOptions = ref({})
const loading = ref(false)
const isModalLoading = ref(false)
const currentPage = ref(1)
const selectAll = ref(false);
const selectedItems = ref([]);
const selectPageItems = ref(false);
const restartRequestNotificationSuccessTrigger = ref(false);
const restartRequestNotificationErrorTrigger = ref(false);
const createModalTrigger = ref(false);
const showUpdateModal = ref(false);
const showCreateModal = ref(false);
const showBulkUpdateModal = ref(false);
const confirmationModalTrigger = ref(false);
const confirmationRestartTrigger = ref(false);
const confirmDeleteAction = ref(null);
const confirmRestartAction = ref(null);
const showCloudProvisioningSettings = ref(false);
const formErrors = ref(null);
const notificationType = ref(null);
const notificationMessages = ref(null);
const notificationShow = ref(null);
const showDuplicateModal = ref(false);
const itemToDuplicate = ref(null);
const newMacAddress = ref('');
const showProvisioningPreviewModal = ref(false);
const showUploadModal = ref(false);
const isUploadingFile = ref(false);
const uploadErrors = ref(null);
const showImportPreviewModal = ref(false);
const importPreviewData = ref([]);
const isCommittingImport = ref(false);
const isProvisioningPreviewLoading = ref(false);
const provisioningPreviewData = ref(null);
const provisioningPreviewError = ref(null);
const activeProvisioningPreviewFlavor = ref(null);
const provisioningPreviewWrap = ref(false);
let tooltipCopyContent = ref('Copy to Clipboard');

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

const perPage = ref(props.pagination?.per_page);


onMounted(() => {
    handleSearchButtonClick();
})

const filterData = ref({
    search: null,
    showGlobal: false,
});

const sortData = ref({
    name: 'device_address',
    order: 'asc',
});

const advancedActions = computed(() => [
    {
        category: "Advanced",
        actions: [
            { id: 'duplicate', label: 'Duplicate', icon: 'DocumentDuplicateIcon' },
        ],
    },
]);

const provisioningPreviewFiles = computed(() => provisioningPreviewData.value?.files ?? []);

const activeProvisioningPreviewFile = computed(() => {
    return provisioningPreviewFiles.value.find((file) => file.flavor === activeProvisioningPreviewFlavor.value)
        ?? provisioningPreviewFiles.value[0]
        ?? null;
});

const activeProvisioningPreviewLang = computed(() => {
    const file = activeProvisioningPreviewFile.value;
    if (!file) return 'text';

    const isXml = /xml/i.test(file.mime || '') || /\.xml$/i.test(file.filename || '');

    return isXml ? 'xml' : 'text';
});

const focusProvisioningPreviewTab = (index, event) => {
    const files = provisioningPreviewFiles.value;
    if (files.length === 0) return;

    const wrapped = (index + files.length) % files.length;
    activeProvisioningPreviewFlavor.value = files[wrapped].flavor;
    event?.target?.parentElement?.children?.[wrapped]?.focus();
};

const formatBytes = (bytes) => {
    const value = Number(bytes);
    if (!Number.isFinite(value) || value < 0) return '— bytes';
    if (value < 1024) return `${value} bytes`;
    if (value < 1024 * 1024) return `${(value / 1024).toFixed(1)} KB`;

    return `${(value / (1024 * 1024)).toFixed(1)} MB`;
};

const provisioningPreviewHeader = computed(() => {
    const device = provisioningPreviewData.value?.device;
    const label = device?.device_address_formatted || device?.device_address;

    return label ? `Provisioning Preview - ${label}` : 'Provisioning Preview';
});

const provisioningPreviewTemplateLabel = computed(() => {
    const template = provisioningPreviewData.value?.template;
    if (!template) return '—';

    const base = template.vendor ? `${template.vendor}/${template.name}` : template.name;
    const suffixParts = [];
    if (template.version) suffixParts.push(`v${template.version}`);
    if (Number(template.revision) > 0) suffixParts.push(`r${template.revision}`);

    return suffixParts.length ? `${base} (${suffixParts.join(', ')})` : base;
});

const provisioningPreviewRoute = (uuid) => {
    return (props.routes.provisioning_preview || '/api/devices/__DEVICE_UUID__/provisioning-preview')
        .replace('__DEVICE_UUID__', uuid);
};

const handleSortRequest = (column) => {
    if (sortData.value.name === column) {
        sortData.value.order = sortData.value.order === 'asc' ? 'desc' : 'asc';
    } else {
        sortData.value.name = column;
        sortData.value.order = 'asc';
    }
    getData();
};


const handleAdvancedActionRequest = (action, uuid) => {
    if (action === 'duplicate') {
        itemToDuplicate.value = uuid;
        newMacAddress.value = '';
        formErrors.value = null;
        showDuplicateModal.value = true;
    }
};

const handleProvisioningPreview = async (uuid) => {
    showProvisioningPreviewModal.value = true;
    isProvisioningPreviewLoading.value = true;
    provisioningPreviewData.value = null;
    provisioningPreviewError.value = null;
    activeProvisioningPreviewFlavor.value = null;

    try {
        const response = await axios.get(provisioningPreviewRoute(uuid));
        provisioningPreviewData.value = response.data;
        activeProvisioningPreviewFlavor.value = response.data?.files?.[0]?.flavor ?? null;
    } catch (error) {
        const errors = error.response?.data?.errors;
        provisioningPreviewError.value = errors
            ? Object.values(errors).flat().join(' ')
            : 'Could not render provisioning preview.';
    } finally {
        isProvisioningPreviewLoading.value = false;
    }
};

const handleImportButtonClick = () => {
    uploadErrors.value = null;
    showUploadModal.value = true;
    getItemOptions(null, { mode: 'create' });
};

const uploadFile = (file) => {
    isUploadingFile.value = true;
    uploadErrors.value = null;
    const formData = new FormData();
    formData.append('file', file);

    axios.post(props.routes.import, formData)
        .then((response) => {
            handleModalClose();
            setTimeout(() => {
                importPreviewData.value = response.data.data;
                showImportPreviewModal.value = true;
            }, 300);
        })
        .catch((error) => {
            handleClearSelection();
            handleErrorResponse(error);
            if (error.response) {
                uploadErrors.value = error.response.data.errors;
            }
        })
        .finally(() => {
            isUploadingFile.value = false;
        });
};

const handleImportSuccess = (messages) => {
    showNotification('success', messages);
    showImportPreviewModal.value = false;
    importPreviewData.value = [];
    refreshCurrentPage();
};

const downloadTemplateFile = () => {
    axios.get(props.routes.download_template, {
        responseType: 'blob',
    })
        .then((response) => {
            const fileBlob = new Blob([response.data], { type: 'text/csv' });
            const fileURL = window.URL.createObjectURL(fileBlob);
            const link = document.createElement('a');
            link.href = fileURL;
            link.setAttribute('download', 'devices_template.csv');
            document.body.appendChild(link);
            link.click();
            link.remove();
            window.URL.revokeObjectURL(fileURL);
        })
        .catch(handleErrorResponse);
};

const closeProvisioningPreview = () => {
    showProvisioningPreviewModal.value = false;
    provisioningPreviewData.value = null;
    provisioningPreviewError.value = null;
    activeProvisioningPreviewFlavor.value = null;
};

const copyProvisioningPreview = async () => {
    if (!activeProvisioningPreviewFile.value?.content) return;

    try {
        await navigator.clipboard.writeText(activeProvisioningPreviewFile.value.content);
        showNotification('success', { preview: ['Provisioning file copied.'] });
    } catch (error) {
        showNotification('error', { preview: ['Could not copy provisioning file.'] });
    }
};

const downloadProvisioningPreview = () => {
    const file = activeProvisioningPreviewFile.value;
    if (!file) return;

    const blob = new Blob([file.content ?? ''], { type: file.mime || 'text/plain' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = file.filename || 'provisioning-preview.cfg';
    link.click();
    URL.revokeObjectURL(url);
};

const submitDuplicateRequest = () => {
    if (!newMacAddress.value) {
        formErrors.value = { new_mac_address: ['MAC Address is required'] };
        return;
    }

    const url = props.routes.duplicate || '/devices/duplicate';
    isModalLoading.value = true;

    axios.post(url, {
        uuid: itemToDuplicate.value,
        new_mac_address: newMacAddress.value
    })
        .then((response) => {
            showDuplicateModal.value = false;
            showNotification('success', response.data.messages);
            refreshCurrentPage();
        })
        .catch((error) => {
            handleFormErrorResponse(error);
        })
        .finally(() => {
            isModalLoading.value = false;
        });
};

// Computed property for bulk actions based on permissions
const bulkActions = computed(() => {
    const actions = [
        {
            id: 'bulk_restart',
            label: 'Restart',
            icon: 'RestartIcon'
        },
        {
            id: 'bulk_update',
            label: 'Update',
            icon: 'PencilSquareIcon'
        }
    ];

    // Conditionally add the delete action if permission is granted
    if (page.props.auth.can.device_destroy) {
        actions.push({
            id: 'bulk_delete',
            label: 'Delete',
            icon: 'TrashIcon'
        });
    }

    return actions;
});

const handleEditButtonClick = (itemUuid) => {
    //Removed to make way for checking limits:
    //    showUpdateModal.value = true
    getItemOptions(itemUuid, { mode: 'update' });
}

const getItemOptions = async (itemUuid = null, extraPayload = {}) => {
    itemOptions.value = {};
    isModalLoading.value = true;

    try {
        const payload = {
            ...extraPayload,
            ...(itemUuid ? { itemUuid } : {}),
        };
        const response = await axios.post(props.routes.item_options, payload);
        itemOptions.value = response.data;

        if (itemUuid) {
            showUpdateModal.value = true;
        }

    } catch (error) {
        handleModalClose();
        handleErrorResponse(error);
    } finally {
        isModalLoading.value = false;
    }
}


const handleCreateButtonClick = async () => {
    isModalLoading.value = true;

    try {
        const response = await axios.post(props.routes.item_options, {
            itemUuid: null,
            mode: 'create',
        });

        // Only open modal if no limit error
        itemOptions.value = response.data;
        showCreateModal.value = true;

    } catch (error) {
        // Limit reached → show toast, do NOT open modal
        handleErrorResponse(error);
        return;

    } finally {
        isModalLoading.value = false;
    }
}


const handleSingleItemDeleteRequest = (uuid) => {
    confirmationModalTrigger.value = true;
    confirmDeleteAction.value = () => executeBulkDelete([uuid]);
}


const handleBulkActionRequest = (action) => {
    if (action === 'bulk_delete') {
        confirmationModalTrigger.value = true;
        confirmDeleteAction.value = () => executeBulkDelete();
    }
    if (action === 'bulk_update') {
        getItemOptions(null, { mode: 'bulk_update' });
        isModalLoading.value = true
        showBulkUpdateModal.value = true;
    }
    if (action === 'bulk_restart') {
        confirmationRestartTrigger.value = true;
        confirmRestartAction.value = () => executeBulkRestart();
    }
}

const executeBulkRestart = () => {
    axios.post(props.routes.restart,
        { 'devices': selectedItems.value },
    )
        .then((response) => {
            showNotification('success', response.data.messages);
            handleModalClose();
            handleClearSelection();
        }).catch((error) => {
            handleClearSelection();
            handleModalClose();
            handleFormErrorResponse(error);
        });
}

const executeBulkDelete = (items = selectedItems.value) => {
    axios.post(props.routes.bulk_delete, { items })
        .then((response) => {
            handleModalClose();
            showNotification('success', response.data.messages);
            refreshCurrentPage();
        })
        .catch((error) => {
            handleClearSelection();
            handleModalClose();
            handleErrorResponse(error);
        });
}

const handleSelectAll = () => {
    axios.post(props.routes.select_all, filterData._rawValue)
        .then((response) => {
            selectedItems.value = response.data.items;
            selectAll.value = true;
            showNotification('success', response.data.messages);

        }).catch((error) => {
            handleClearSelection();
            handleErrorResponse(error);
        });

};

const handleCloudProvisioningButtonClick = () => {
    showCloudProvisioningSettings.value = true
    isModalLoading.value = false
    // getCloudProvisioningItemOptions()
}


const handleCopyToClipboard = (macAddress) => {
    navigator.clipboard.writeText(macAddress).then(() => {
        tooltipCopyContent.value = 'Copied'
        setTimeout(() => {
            tooltipCopyContent.value = 'Copy to Clipboard'
        }, 500);
    }).catch((error) => {
        // Handle the error case
        console.error('Failed to copy to clipboard:', error);
    });
}


const handleRestart = (device_uuid) => {
    axios.post(props.routes.restart,
        { 'devices': [device_uuid] },
    )
        .then((response) => {
            showNotification('success', response.data.messages);

            handleClearSelection();
        }).catch((error) => {
            handleClearSelection();
            handleFormErrorResponse(error);
        });
}


const handleShowGlobal = () => {
    filterData.value.showGlobal = true;
    getData(1);
}

const handleShowLocal = () => {
    filterData.value.showGlobal = false;
    getData(1);
}

const getData = (page = 1) => {
    loading.value = true;
    currentPage.value = Number(page) || 1;

        let sort = sortData.value.name;
    if (sortData.value.order === 'desc') {
        sort = `-${sort}`;
    }


    axios.get(props.routes.data_route, {
        params: {
            filter: filterData.value,
            page: currentPage.value,
            per_page: perPage.value,
            sort,
        }
    })
        .then((response) => {
            data.value = response.data;
            currentPage.value = response.data.current_page ?? currentPage.value;
            // console.log(data.value);

        }).catch((error) => {

            handleErrorResponse(error);
        }).finally(() => {
            loading.value = false
        })
}

const handleSearchButtonClick = () => {
    getData(1)
};

const refreshCurrentPage = () => {
    getData(currentPage.value)
};


const handleFiltersReset = () => {
    filterData.value.search = null;
    // After resetting the filters, call handleSearchButtonClick to perform the search with the updated filters
    getData(1);
}


const handlePageSizeChange = (newPerPage) => {
    perPage.value = newPerPage;
    getData(1);
};

const renderRequestedPage = (url) => {
    loading.value = true;
    // Extract the page number from the url, e.g. "?page=3"
    const urlObj = new URL(url, window.location.origin);
    const pageParam = urlObj.searchParams.get("page") ?? 1;

    // Now call getData with the page number
    getData(pageParam);
};


const handleFormErrorResponse = (error) => {
    if (error.request?.status === 419) {
        showNotification('error', { request: ["Session expired. Reload the page"] });
    } else if (error.response) {
        // The request was made and the server responded with a status code
        // that falls out of the range of 2xx
        // console.log(error.response.data);
        showNotification('error', error.response.data.errors || { request: [error.message] });
        formErrors.value = error.response.data.errors;
    } else if (error.request) {
        // The request was made but no response was received
        // `error.request` is an instance of XMLHttpRequest in the browser and an instance of
        // http.ClientRequest in node.js
        showNotification('error', { request: [error.request] });
        console.log(error.request);
    } else {
        // Something happened in setting up the request that triggered an Error
        showNotification('error', { request: [error.message] });
        console.log(error.message);
    }

}

const handleErrorResponse = (error) => {
    if (error.response) {
        // The request was made and the server responded with a status code
        // that falls out of the range of 2xx
        // console.log(error.response.data);
        showNotification('error', error.response.data.errors || { request: [error.message] });
    } else if (error.request) {
        // The request was made but no response was received
        // `error.request` is an instance of XMLHttpRequest in the browser and an instance of
        // http.ClientRequest in node.js
        showNotification('error', { request: [error.request] });
        console.log(error.request);
    } else {
        // Something happened in setting up the request that triggered an Error
        showNotification('error', { request: [error.message] });
        console.log(error.message);
    }
}

const handleSelectPageItems = () => {
    if (selectPageItems.value) {
        selectedItems.value = data.value.data.map(item => item.device_uuid);
    } else {
        selectedItems.value = [];
    }
};

const handleClearSelection = () => {
    selectedItems.value = [];
    selectPageItems.value = false;
    selectAll.value = false;
}

const handleModalClose = () => {
    createModalTrigger.value = false;
    showUpdateModal.value = false;
    confirmationModalTrigger.value = false;
    confirmationRestartTrigger.value = false;
    showBulkUpdateModal.value = false;
    showCloudProvisioningSettings.value = false;
    showUploadModal.value = false;
    showImportPreviewModal.value = false;
}

const hideNotification = () => {
    notificationShow.value = false;
    notificationType.value = null;
    notificationMessages.value = null;
}

const showNotification = (type, messages = null) => {
    notificationType.value = type;
    notificationMessages.value = messages;
    notificationShow.value = true;
}

registerLicense('Ngo9BigBOggjHTQxAR8/V1NAaF5cWWdCf1FpRmJGdld5fUVHYVZUTXxaS00DNHVRdkdnWX5eeHVSQ2hYUkB3WEI=');

</script>

<style>
@import "@syncfusion/ej2-base/styles/tailwind.css";
@import "@syncfusion/ej2-vue-popups/styles/tailwind.css";
</style>
