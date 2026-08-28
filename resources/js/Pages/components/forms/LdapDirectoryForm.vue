<template>
    <TransitionRoot as="div" :show="show">
        <Dialog as="div" class="relative z-20" @close="emit('close')">
            <TransitionChild as="div" enter="ease-out duration-300" enter-from="opacity-0" enter-to="opacity-100"
                leave="ease-in duration-200" leave-from="opacity-100" leave-to="opacity-0">
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" />
            </TransitionChild>

            <div class="fixed inset-0 z-20 w-screen overflow-y-auto">
                <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
                    <TransitionChild as="template" enter="ease-out duration-300"
                        enter-from="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                        enter-to="opacity-100 translate-y-0 sm:scale-100" leave="ease-in duration-200"
                        leave-from="opacity-100 translate-y-0 sm:scale-100"
                        leave-to="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95">
                        <DialogPanel
                            class="relative transform rounded-lg bg-white px-4 pb-4 pt-5 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-6xl sm:p-6">
                            <div class="flex flex-wrap items-start justify-between gap-3 pr-10">
                                <div>
                                    <DialogTitle as="h3" class="text-base font-semibold leading-6 text-gray-900">
                                        {{ directory ? directory.name : $t('Add Directory') }}
                                    </DialogTitle>
                                    <p class="mt-1 text-sm text-gray-500">
                                        {{ $t('LDAP directory settings for the current account.') }}
                                    </p>
                                </div>
                                <div v-if="directory" class="flex flex-wrap gap-2">
                                    <button v-if="permissions.test" type="button" class="secondary-button"
                                        :disabled="actionBusy" @click="runAction('test')">
                                        {{ $t('Test Connection') }}
                                    </button>
                                    <button v-if="permissions.sync && directory.enabled" type="button"
                                        class="primary-button" :disabled="actionBusy" @click="runAction('sync')">
                                        {{ $t('Sync Now') }}
                                    </button>
                                </div>
                            </div>

                            <button type="button"
                                class="absolute right-4 top-4 rounded-md bg-white text-gray-400 hover:text-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                                @click="emit('close')">
                                <span class="sr-only">{{ $t('Close') }}</span>
                                <XMarkIcon class="h-6 w-6" aria-hidden="true" />
                            </button>

                            <div v-if="directory" class="mt-4 flex flex-wrap gap-x-6 gap-y-2 border-y border-gray-200 py-3 text-sm">
                                <div><span class="text-gray-500">{{ $t('Connection') }}:</span> <span class="ml-1 font-medium capitalize text-gray-900">{{ statusLabel(directory.connection_status) }}</span></div>
                                <div><span class="text-gray-500">{{ $t('Last Sync') }}:</span> <span class="ml-1 font-medium text-gray-900">{{ formatDate(directory.last_sync_at) }}</span></div>
                                <div><span class="text-gray-500">{{ $t('Next Sync') }}:</span> <span class="ml-1 font-medium text-gray-900">{{ formatDate(directory.next_sync_at) }}</span></div>
                            </div>

                            <Vueform :key="formKey" ref="form$" class="mt-5" :endpoint="submitForm"
                                :default="defaultValues" :display-errors="false"
                                @success="handleSuccess" @error="handleError" @response="handleResponse">
                                <template #empty>
                                    <div class="lg:grid lg:grid-cols-12 lg:gap-x-5">
                                        <div class="px-2 py-4 sm:px-6 lg:col-span-3 lg:px-0 lg:py-0">
                                            <FormTabs view="vertical">
                                                <FormTab name="directory" :label="$t('Directory')" :elements="directoryElements" />
                                                <FormTab name="operational" :label="$t('Operational Settings')" :elements="operationalElements" />
                                                <FormTab name="users" :label="$t('User Configuration')" :elements="userElements" />
                                                <FormTab name="groups" :label="$t('Group Configuration')" :elements="groupElements" />
                                                <FormTab v-if="directory && permissions.map_groups" name="mappings"
                                                    :label="$t('Group Mappings')" :elements="mappingElements" />
                                            </FormTabs>
                                        </div>

                                        <div class="space-y-6 bg-gray-50 px-4 py-6 text-gray-600 shadow sm:rounded-md sm:px-6 lg:col-span-9">
                                            <FormElements>
                                                <StaticElement name="directory_header" tag="h4" :content="$t('Directory Settings')"
                                                    :description="$t('Configure the connection, service account, and synchronization schedule.')" />
                                                <SelectElement name="type" :label="$t('Directory Type')" :items="directoryTypes"
                                                    :native="false" :description="fieldDescriptions.type" :floating="false"
                                                    :columns="halfColumns" />
                                                <TextElement name="name" :label="$t('Directory Name')" rules="required"
                                                    :description="fieldDescriptions.name" :floating="false" :columns="halfColumns" />
                                                <ToggleElement name="enabled" :text="$t('Enable Directory')"
                                                    :labels="{ on: $t('Yes'), off: $t('No') }" label="&nbsp;"
                                                    :description="fieldDescriptions.enabled" :columns="halfColumns" />
                                                <SelectElement name="sync_interval_minutes" :label="$t('Synchronize')"
                                                    :items="syncIntervals" :native="false" :description="fieldDescriptions.sync_interval_minutes"
                                                    :floating="false" :columns="halfColumns" />
                                                <TextElement name="priority" input-type="number" :label="$t('Priority')"
                                                    :description="fieldDescriptions.priority" :floating="false" :columns="halfColumns" />
                                                <SelectElement name="secure_connection" :label="$t('Secure Connection Type')"
                                                    :items="secureConnections" :native="false" :description="fieldDescriptions.secure_connection"
                                                    :floating="false" :columns="halfColumns" />
                                                <TextElement name="port" input-type="number" :label="$t('Port')" rules="required"
                                                    :description="fieldDescriptions.port" :floating="false" :columns="halfColumns" />
                                                <TextElement name="hosts" :label="$t('Host(s)')"
                                                    placeholder="dc01.domain.local, dc02.domain.local" rules="required"
                                                    :description="fieldDescriptions.hosts" :floating="false" />
                                                <TextElement name="bind_username" :label="$t('Username')" placeholder="ldap-sync" input-type="search"
                                                    rules="required" :floating="false" autocomplete="off"
                                                    :description="fieldDescriptions.bind_username" :columns="halfColumns" />
                                                <TextElement name="bind_password" input-type="password" :label="$t('Password')"
                                                    :description="bindPasswordDescription"
                                                    :rules="directory?.has_bind_password ? [] : ['required']" :floating="false"
                                                    autocomplete="new-password" :columns="halfColumns" />
                                                <TextElement name="ad_domain" :label="$t('Domain')" placeholder="domain.local"
                                                    rules="required" :description="fieldDescriptions.ad_domain" :floating="false" :columns="halfColumns" />
                                                <TextElement name="base_dn" :label="$t('Base DN')" placeholder="DC=domain,DC=local"
                                                    rules="required" :description="fieldDescriptions.base_dn" :floating="false" :columns="halfColumns" />
                                                <GroupElement name="directory_buttons" />
                                                <ButtonElement name="directory_submit" :button-label="$t('Save')" :submits="true"
                                                    align="right" />

                                                <StaticElement name="operational_header" tag="h4" :content="$t('Operational Settings')"
                                                    :description="$t('Control extension provisioning and whether directory group membership manages local roles.')" />
                                                <SelectElement name="create_missing_extensions" :label="$t('Create Missing Extensions')"
                                                    :items="extensionModes" :native="false" :description="fieldDescriptions.create_missing_extensions"
                                                    :floating="false" :columns="halfColumns" />
                                                <ToggleElement name="manage_groups_locally" :text="$t('Manage groups locally')"
                                                    :labels="{ on: $t('Yes'), off: $t('No') }" label="&nbsp;"
                                                    :description="fieldDescriptions.manage_groups_locally" :columns="halfColumns" />
                                                <TextElement name="common_name_attribute" :label="$t('Common Name attribute')"
                                                    :description="fieldDescriptions.common_name_attribute" :floating="false" :columns="halfColumns" />
                                                <TextElement name="description_attribute" :label="$t('Description attribute')"
                                                    :description="fieldDescriptions.description_attribute" :floating="false" :columns="halfColumns" />
                                                <TextElement name="unique_identifier_attribute" :label="$t('Unique identifier attribute')"
                                                    :description="fieldDescriptions.unique_identifier_attribute" :floating="false" :columns="halfColumns" />
                                                <GroupElement name="operational_buttons" />
                                                <ButtonElement name="operational_submit" :button-label="$t('Save')" :submits="true"
                                                    align="right" />

                                                <StaticElement name="users_header" tag="h4" :content="$t('User Configuration')"
                                                    :description="$t('Attribute defaults are optimized for Active Directory. Adjust them for other LDAP schemas.')" />
                                                <TextElement name="user_dn" :label="$t('User DN')" :description="fieldDescriptions.user_dn"
                                                    :floating="false" />
                                                <TextElement name="user_object_class" :label="$t('User object class')"
                                                    :description="fieldDescriptions.user_object_class" :floating="false" :columns="halfColumns" />
                                                <TextElement name="user_object_filter" :label="$t('User object filter')"
                                                    :description="fieldDescriptions.user_object_filter" :floating="false" />
                                                <TextElement v-for="field in userAttributeFields" :key="field.name" :name="field.name"
                                                    :label="field.label" :description="field.description"
                                                    :input-type="field.name === 'user_name_attribute' ? 'search' : 'text'"
                                                    :floating="false" :columns="halfColumns" />
                                                <GroupElement name="users_buttons" />
                                                <ButtonElement name="users_submit" :button-label="$t('Save')" :submits="true"
                                                    align="right" />

                                                <StaticElement name="groups_header" tag="h4" :content="$t('Group Configuration')"
                                                    :description="$t('Groups are imported separately and can be mapped to local roles.')" />
                                                <TextElement name="group_dn" :label="$t('Group DN')" :description="fieldDescriptions.group_dn"
                                                    :floating="false" />
                                                <TextElement name="group_object_class" :label="$t('Group object class')"
                                                    :description="fieldDescriptions.group_object_class" :floating="false" :columns="halfColumns" />
                                                <TextElement name="group_object_filter" :label="$t('Group object filter')"
                                                    :description="fieldDescriptions.group_object_filter" :floating="false" :columns="halfColumns" />
                                                <TextElement name="group_members_attribute" :label="$t('Group members attribute')"
                                                    :description="fieldDescriptions.group_members_attribute" :floating="false" :columns="halfColumns" />
                                                <GroupElement name="groups_buttons" />
                                                <ButtonElement name="groups_submit" :button-label="$t('Save')" :submits="true"
                                                    align="right" />

                                                <StaticElement name="mappings_header" tag="h4" :content="$t('Group Mappings')"
                                                    :description="$t('Map imported directory groups to existing local roles. Unmapped groups grant no local permissions.')" />
                                                <StaticElement name="mappings_content">
                                                    <div v-if="mappingData?.manage_groups_locally"
                                                        class="mb-4 flex gap-3 rounded-md bg-amber-50 p-4 text-amber-800 ring-1 ring-inset ring-amber-600/20">
                                                        <ExclamationTriangleIcon class="mt-0.5 h-5 w-5 flex-none" aria-hidden="true" />
                                                        <div>
                                                            <p class="text-sm font-semibold">{{ $t('Group mappings are currently inactive.') }}</p>
                                                            <p class="mt-1 text-sm">
                                                                {{ $t('Set Manage groups locally to No in Operational Settings and save before mapping groups.') }}
                                                            </p>
                                                        </div>
                                                    </div>
                                                    <div v-if="mappingsLoading" class="rounded-md bg-white p-4 text-sm text-gray-600">
                                                        {{ $t('Loading group mappings...') }}
                                                    </div>
                                                    <div v-else-if="!mappingData?.directory_groups?.length"
                                                        class="rounded-md bg-white p-4 text-sm text-gray-600">
                                                        {{ $t('Run synchronization once to discover directory groups.') }}
                                                    </div>
                                                    <div v-else class="divide-y divide-gray-200 rounded-md border border-gray-200 bg-white">
                                                        <div v-for="group in mappingData.directory_groups" :key="group.directory_group_uuid"
                                                            class="grid gap-3 p-3 sm:grid-cols-2 sm:items-center">
                                                            <div>
                                                                <div class="text-sm font-medium text-gray-900">{{ group.name }}</div>
                                                                <div v-if="group.description" class="text-xs text-gray-500">{{ group.description }}</div>
                                                                <button type="button"
                                                                    class="mt-1.5 inline-flex items-center gap-1.5 rounded text-xs font-medium text-indigo-700 hover:text-indigo-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                                                                    @click="openGroupMembers(group)">
                                                                    <UsersIcon class="h-4 w-4" aria-hidden="true" />
                                                                    {{ $t('View members') }}
                                                                    <span class="text-gray-500">({{ group.directory_users_count ?? 0 }})</span>
                                                                </button>
                                                            </div>
                                                            <select v-model="mappingSelections[group.directory_group_uuid]"
                                                                :disabled="mappingData?.manage_groups_locally"
                                                                :aria-label="$t('Local role for :group', { group: group.name })"
                                                                class="rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500 disabled:cursor-not-allowed disabled:bg-gray-100 disabled:text-gray-500">
                                                                <option value="">{{ $t('No local role') }}</option>
                                                                <option v-for="local in mappingData.local_groups" :key="local.group_uuid"
                                                                    :value="local.group_uuid">{{ local.group_name }}</option>
                                                            </select>
                                                        </div>
                                                    </div>
                                                    <div v-if="mappingData?.directory_groups?.length" class="mt-4 flex justify-end">
                                                        <button type="button" class="primary-button"
                                                            :disabled="actionBusy || mappingData?.manage_groups_locally" @click="saveMappings">
                                                            {{ $t('Save Group Mappings') }}
                                                        </button>
                                                    </div>
                                                </StaticElement>
                                            </FormElements>
                                        </div>
                                    </div>
                                </template>
                            </Vueform>
                        </DialogPanel>
                    </TransitionChild>
                </div>
            </div>

            <LdapGroupMembersModal :show="Boolean(selectedMemberGroup)" :group="selectedMemberGroup"
                @close="selectedMemberGroup = null" @error="handleError" />
        </Dialog>
    </TransitionRoot>
</template>

<script setup>
import { computed, reactive, ref, watch } from 'vue'
import axios from 'axios'
import { Dialog, DialogPanel, DialogTitle, TransitionChild, TransitionRoot } from '@headlessui/vue'
import { XMarkIcon } from '@heroicons/vue/24/solid'
import { ExclamationTriangleIcon, UsersIcon } from '@heroicons/vue/24/outline'
import { trans } from '@i18n'
import LdapGroupMembersModal from '../modal/LdapGroupMembersModal.vue'

const props = defineProps({
    show: Boolean,
    directory: { type: Object, default: null },
    settings: { type: Object, required: true },
    mappingData: { type: Object, default: null },
    mappingsLoading: Boolean,
})
const emit = defineEmits(['close', 'success', 'error', 'refresh'])
const MASK = '********************************'
const form$ = ref(null)
const formKey = ref(0)
const actionBusy = ref(false)
const selectedMemberGroup = ref(null)
const mappingSelections = reactive({})
const permissions = computed(() => props.settings?.permissions ?? {})
const halfColumns = { sm: { container: 6 } }

const defaultValues = computed(() => ({
    ...(props.settings?.defaults ?? {}),
    ...(props.directory ?? {}),
    bind_password: props.directory?.has_bind_password ? MASK : '',
}))

const syncIntervals = [
    { value: 15, label: trans('15 Minutes') }, { value: 30, label: trans('30 Minutes') },
    { value: 60, label: trans('1 Hour') }, { value: 360, label: trans('6 Hours') },
    { value: 720, label: trans('12 Hours') }, { value: 1440, label: trans('1 Day') },
]
const secureConnections = [{ value: 'ldaps', label: 'LDAPS' }, { value: 'starttls', label: 'StartTLS' }, { value: 'none', label: trans('None') }]
const directoryTypes = [{ value: 'active_directory', label: trans('Microsoft Active Directory') }, { value: 'ldap', label: trans('Generic LDAP') }]
const extensionModes = [{ value: 'none', label: trans("Don't Create") }, { value: 'default', label: trans('Create with Defaults') }]
const fieldDescriptions = {
    type: trans('Choose Microsoft Active Directory for AD-specific defaults, or Generic LDAP for another LDAP-compatible server.'),
    name: trans('A unique name for this directory connection in the current account.'),
    enabled: trans('Controls scheduled synchronization and whether users from this directory may authenticate.'),
    sync_interval_minutes: trans('How often users and groups are synchronized from an enabled directory.'),
    priority: trans('Lower values are tried first when a user is linked to more than one enabled directory.'),
    secure_connection: trans('Use LDAPS for TLS from connection start, StartTLS to upgrade an LDAP connection, or None for plaintext.'),
    hosts: trans('Domain controller hostnames or IP addresses, separated by commas or spaces. Hosts are tried in order.'),
    port: trans('TCP port used by the directory server. Typically 389 for LDAP or StartTLS and 636 for LDAPS.'),
    bind_username: trans('Account used to bind and search the directory. Bare usernames are combined with the configured domain.'),
    bind_password: trans('Password for the bind account. A dedicated read-only directory service account is recommended.'),
    ad_domain: trans('DNS domain used to complete bare bind usernames and identify directory users, usually in the form domain.local.'),
    base_dn: trans('Root distinguished name for directory searches, for example DC=domain,DC=local.'),
    create_missing_extensions: trans('Creates a default extension when the user extension attribute contains a number that does not exist in this account.'),
    manage_groups_locally: trans('When enabled, imported directory memberships do not add or remove mapped local role memberships.'),
    common_name_attribute: trans('LDAP attribute used as the common name for imported directory objects.'),
    description_attribute: trans('LDAP attribute used as the description for imported users and groups.'),
    unique_identifier_attribute: trans('Immutable single-value identifier used to track objects across renames. Active Directory normally uses objectGUID.'),
    user_dn: trans('Relative DN added before the Base DN when searching users, for example OU=Users. Leave blank to search from the Base DN.'),
    user_object_class: trans('LDAP object class added to user searches. Active Directory normally uses user.'),
    user_object_filter: trans('LDAP filter combined with the user object class to select which user objects are synchronized.'),
    user_name_attribute: trans('LDAP attribute used as the local username and directory login name. Active Directory normally uses sAMAccountName.'),
    user_first_name_attribute: trans('LDAP attribute containing the user first name.'),
    user_last_name_attribute: trans('LDAP attribute containing the user last name.'),
    user_display_name_attribute: trans('LDAP attribute containing the user display name.'),
    user_group_attribute: trans('Multi-value LDAP attribute containing the distinguished names of groups where the user is a direct member.'),
    user_email_attribute: trans('LDAP attribute containing the user email address. Leave blank to manage login emails locally.'),
    user_title_attribute: trans('LDAP attribute containing the user job title.'),
    user_company_attribute: trans('LDAP attribute containing the user company name.'),
    user_department_attribute: trans('LDAP attribute containing the user department name.'),
    user_home_phone_attribute: trans('LDAP attribute containing the user home telephone number.'),
    user_work_phone_attribute: trans('LDAP attribute containing the user work telephone number.'),
    user_cell_phone_attribute: trans('LDAP attribute containing the user mobile telephone number.'),
    user_fax_attribute: trans('LDAP attribute containing the user fax number.'),
    user_extension_attribute: trans('LDAP attribute containing the extension number used to link or create a local extension.'),
    group_dn: trans('Relative DN added before the Base DN when searching groups, for example OU=Groups. Leave blank to search from the Base DN.'),
    group_object_class: trans('LDAP object class added to group searches. Active Directory normally uses group.'),
    group_object_filter: trans('LDAP filter combined with the group object class to select which group objects are synchronized.'),
    group_members_attribute: trans('Multi-value LDAP attribute containing the distinguished names of users and groups that belong to each group.'),
}
const userAttributeFields = [
    ['user_name_attribute', trans('User name attribute')], ['user_first_name_attribute', trans('User first name attribute')], ['user_last_name_attribute', trans('User last name attribute')],
    ['user_display_name_attribute', trans('User display name attribute')], ['user_group_attribute', trans('User group attribute')], ['user_email_attribute', trans('User email attribute')],
    ['user_title_attribute', trans('User Title attribute')], ['user_company_attribute', trans('User Company attribute')], ['user_department_attribute', trans('User Department attribute')],
    ['user_home_phone_attribute', trans('User Home Phone attribute')], ['user_work_phone_attribute', trans('User Work Phone attribute')], ['user_cell_phone_attribute', trans('User Cell Phone attribute')],
    ['user_fax_attribute', trans('User Fax attribute')], ['user_extension_attribute', trans('User extension Link attribute')],
].map(([name, label]) => ({ name, label, description: fieldDescriptions[name] }))

const bindPasswordDescription = computed(() => [
    fieldDescriptions.bind_password,
    props.directory?.has_bind_password ? trans('A password is stored. Enter a new password to replace it.') : null,
].filter(Boolean).join(' '))

const directoryElements = ['directory_header', 'type', 'name', 'enabled', 'sync_interval_minutes', 'priority', 'secure_connection', 'port', 'hosts', 'bind_username', 'bind_password', 'ad_domain', 'base_dn', 'directory_buttons', 'directory_submit']
const operationalElements = ['operational_header', 'create_missing_extensions', 'manage_groups_locally', 'common_name_attribute', 'description_attribute', 'unique_identifier_attribute', 'operational_buttons', 'operational_submit']
const userElements = ['users_header', 'user_dn', 'user_object_class', 'user_object_filter', ...userAttributeFields.map(field => field.name), 'users_buttons', 'users_submit']
const groupElements = ['groups_header', 'group_dn', 'group_object_class', 'group_object_filter', 'group_members_attribute', 'groups_buttons', 'groups_submit']
const mappingElements = ['mappings_header', 'mappings_content']

const messageBag = message => ({ server: [message] })
const formatDate = value => value ? new Date(value).toLocaleString() : '—'
const statusLabel = value => String(value || 'not tested').replaceAll('_', ' ')

const submitForm = async (FormData, form) => {
    const data = { ...form.requestData }
    if (data.bind_password === MASK) data.bind_password = null
    return props.directory
        ? form.$vueform.services.axios.put(props.directory.routes.update, data)
        : form.$vueform.services.axios.post(props.settings.routes.store, data)
}

const handleSuccess = response => {
    emit(response.data.connection_status === 'failed' ? 'error' : 'success', messageBag(response.data.message))
    emit('close')
    emit('refresh')
}
const handleResponse = (response, form) => {
    if (response.data.errors) Object.entries(response.data.errors).forEach(([name, messages]) => form.el$(name)?.messageBag.append(messages[0]))
}
const handleError = error => emit('error', error.response?.data?.errors || messageBag(error.response?.data?.message || error.message))

const runAction = async action => {
    actionBusy.value = true
    try {
        const response = await axios.post(props.directory.routes[action])
        emit('success', messageBag(response.data.message))
        emit('refresh')
    } catch (error) {
        emit('error', error.response?.data?.errors || messageBag(error.response?.data?.message || error.message))
    } finally {
        actionBusy.value = false
    }
}

const saveMappings = async () => {
    actionBusy.value = true
    try {
        const response = await axios.put(props.directory.routes.mappings, { mappings: { ...mappingSelections } })
        emit('success', messageBag(response.data.message))
    } catch (error) {
        emit('error', error.response?.data?.errors || messageBag(error.response?.data?.message || error.message))
    } finally {
        actionBusy.value = false
    }
}

const openGroupMembers = group => {
    selectedMemberGroup.value = group
}

watch(() => [props.show, props.directory?.directory_uuid], () => {
    if (props.show) {
        formKey.value++
    } else {
        selectedMemberGroup.value = null
    }
})
watch(() => props.mappingData, data => {
    Object.keys(mappingSelections).forEach(key => delete mappingSelections[key])
    for (const group of data?.directory_groups ?? []) {
        mappingSelections[group.directory_group_uuid] = data.mappings?.[group.directory_group_uuid] ?? ''
    }
}, { immediate: true })
</script>

<style scoped>
.primary-button { @apply rounded-md bg-indigo-600 px-2.5 py-1.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 disabled:cursor-not-allowed disabled:opacity-50; }
.secondary-button { @apply rounded-md bg-white px-2.5 py-1.5 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50; }

:global(div[data-lastpass-icon-root]),
:global(div[data-lastpass-root]) {
    overflow: hidden !important;
    opacity: 0 !important;
    pointer-events: none !important;
}
div[data-lastpass-icon-root] {
    display: none !important
}

div[data-lastpass-root] {
    display: none !important
}

</style>
