<template>
    <TransitionRoot as="div" :show="show">
        <Dialog as="div" class="relative z-10">
            <TransitionChild as="div" enter="ease-out duration-300" enter-from="opacity-0" enter-to="opacity-100"
                leave="ease-in duration-200" leave-from="opacity-100" leave-to="opacity-0">
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" />
            </TransitionChild>
            <div class="fixed inset-0 z-10 w-screen overflow-y-auto">
                <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
                    <TransitionChild as="template" enter="ease-out duration-300"
                        enter-from="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                        enter-to="opacity-100 translate-y-0 sm:scale-100" leave="ease-in duration-200"
                        leave-from="opacity-100 translate-y-0 sm:scale-100"
                        leave-to="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95">

                        <DialogPanel
                            class="relative transform  rounded-lg bg-white px-4 pb-4 pt-5 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-5xl sm:p-6">

                            <DialogTitle as="h3" class="mb-4 pr-8 text-base font-semibold leading-6 text-gray-900">
                                {{ header }}
                            </DialogTitle>

                            <div class="absolute right-0 top-0 pr-4 pt-4 sm:block">
                                <button type="button"
                                    class="rounded-md bg-white text-gray-400 hover:text-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                                    @click="emit('close')">
                                    <span class="sr-only">Close</span>
                                    <XMarkIcon class="h-6 w-6" aria-hidden="true" />
                                </button>
                            </div>

                            <div v-if="loading" class="w-full h-full">
                                <div class="flex justify-center items-center space-x-3">
                                    <div>
                                        <svg class="animate-spin  h-10 w-10 text-blue-600"
                                            xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                                stroke-width="4">
                                            </circle>
                                            <path class="opacity-75" fill="currentColor"
                                                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                                            </path>
                                        </svg>
                                    </div>
                                    <div class="text-lg text-blue-600 m-auto">Loading...</div>
                                </div>
                            </div>


                            <Vueform v-if="!loading" ref="form$" :endpoint="submitForm" @success="handleSuccess"
                                @error="handleError" @response="handleResponse" :display-errors="false" :default="{
                                    device_address: options.item?.device_address ?? null,
                                    serial_number: options.item?.serial_number ?? null,
                                    device_template: options.item?.device_template_uuid
                                        ?? options.item?.device_template
                                        ?? null,
                                    device_profile_uuid: options.item?.device_profile_uuid,
                                    domain_uuid: options.item?.domain_uuid,
                                    device_lines: options.lines,
                                    device_description: options.item?.device_description ?? null,
                                }">

                                <template #empty>

                                    <div class="lg:grid lg:grid-cols-12 lg:gap-x-5">
                                        <div class="px-2 py-6 sm:px-6 lg:col-span-3 lg:px-0 lg:py-0">
                                            <FormTabs view="vertical" @select="handleTabSelected">
                                                <FormTab name="page0" label="Device Settings" :elements="[
                                                    'h4',
                                                    'device_address',
                                                    'device_template',
                                                    'device_profile_uuid',
                                                    'domain_uuid',
                                                    'device_description',
                                                    'serial_number',
                                                    'container_3',
                                                    'submit',

                                                ]" />
                                                <FormTab name="lines" label="Lines" :elements="[
                                                    'lines_container',
                                                    'lines_title',
                                                    'add_key',
                                                    'device_lines',
                                                    'advanced',
                                                    'lines_container2',
                                                    'submit_lines',
                                                ]" />

                                                <FormTab name="keys" label="Function Keys" :elements="[
                                                    'lines_container',
                                                    'keys_title',
                                                    'add_key',
                                                    'device_keys',
                                                    'advanced',
                                                    'keys_container2',
                                                    'submit_keys',
                                                ]" />

                                            </FormTabs>
                                        </div>

                                        <div
                                            class="sm:px-6 lg:col-span-9 shadow sm:rounded-md space-y-6 text-gray-600 bg-gray-50 px-4 py-6 sm:p-6">
                                            <FormElements>

                                                <StaticElement name="h4" tag="h4" content="Device Settings" />

                                                <TextElement name="device_address" label="MAC Address"
                                                    placeholder="Enter MAC address" :floating="false" :columns="{
                                                        sm: {
                                                            container: 6,
                                                        },
                                                    }" />

                                                <TextElement name="serial_number" label="Serial Number (Optional)"
                                                    placeholder="Enter Serial Number" :floating="false" :columns="{
                                                        sm: {
                                                            container: 6,
                                                        },
                                                    }" />

                                                <SelectElement name="device_template" :items="options.templates"
                                                    :search="true" :native="false" label="Device Template"
                                                    input-type="search" autocomplete="off" label-prop="name"
                                                    value-prop="value" :floating="false"
                                                    placeholder="Select Template" />

                                                <SelectElement name="device_profile_uuid" :items="options.profiles"
                                                    :search="true" :native="false" label="Device Profile"
                                                    input-type="search" autocomplete="off" label-prop="name"
                                                    value-prop="value" placeholder="Select Profile (Optional)"
                                                    :floating="false" />

                                                <TextElement name="device_description" label="Description"
                                                    placeholder="Enter description" :floating="false" />


                                                <GroupElement name="container_3" />

                                                <ButtonElement name="submit" button-label="Save" :submits="true"
                                                    align="right" />


                                                <!-- Lines tab-->
                                                <StaticElement name="lines_title" tag="h4" content="Device Lines"
                                                    description="Assign lines to this device." />

                                                <GroupElement name="lines_container" />

                                                <ListElement name="device_lines" :sort="true" size="sm"
                                                    :controls="{ add: options.permissions.device_line_create, remove: options.permissions.device_line_destroy, sort: options.permissions.device_line_update }"
                                                    :add-classes="{ ListElement: { listItem: 'bg-white p-4 mb-4 rounded-lg shadow-md' } }">
                                                    <template #default="{ index }">
                                                        <ObjectElement :name="index">
                                                            <HiddenElement name="device_line_uuid" :meta="true" />
                                                            <HiddenElement name="domain_uuid" :meta="true"
                                                                :default="options.default_line_options?.domain_uuid" />
                                                            <HiddenElement name="server_address" :meta="true"
                                                                :default="options.default_line_options?.server_address" />
                                                            <HiddenElement name="server_address_primary" :meta="true"
                                                                :default="options.default_line_options?.server_address_primary" />
                                                            <HiddenElement name="server_address_secondary" :meta="true"
                                                                :default="options.default_line_options?.server_address_secondary" />
                                                            <HiddenElement name="outbound_proxy_primary" :meta="true"
                                                                :default="options.default_line_options?.outbound_proxy_primary" />
                                                            <HiddenElement name="outbound_proxy_secondary" :meta="true"
                                                                :default="options.default_line_options?.outbound_proxy_secondary" />
                                                            <HiddenElement name="sip_port" :meta="true"
                                                                :default="options.default_line_options?.sip_port" />
                                                            <HiddenElement name="sip_transport" :meta="true"
                                                                :default="options.default_line_options?.sip_transport" />
                                                            <HiddenElement name="register_expires" :meta="true"
                                                                :default="options.default_line_options?.register_expires" />
                                                            <HiddenElement name="user_id" :meta="true"
                                                                :default="null" />
                                                            <HiddenElement name="auth_id" :meta="true"
                                                                :default="null" />
                                                            <HiddenElement name="password" :meta="true"
                                                                :default="null" />
                                                            <HiddenElement name="shared_line" :meta="true"
                                                                :default="null" />
                                                            <HiddenElement name="external_line" :meta="true"
                                                                :default="null" />


                                                            <TextElement name="line_number" label="Key" :rules="[
                                                                'nullable',
                                                                'numeric',
                                                            ]" autocomplete="off" :columns="{

                                                                sm: {
                                                                    container: 1,
                                                                },
                                                            }" :default="nextLineNumber" />

                                                            <SelectElement name="line_type_id" label="Function"
                                                                :items="options.line_key_types" :search="true"
                                                                label-prop="name" :native="false" input-type="search"
                                                                autocomplete="off" :columns="{

                                                                    sm: {
                                                                        container: 3,
                                                                    },
                                                                }" placeholder="Choose Function" :floating="false"
                                                                @change="(newValue, oldValue, el$) => {

                                                                    if (newValue == 'sharedline') {
                                                                        el$.form$.el$('device_lines').children$[index].children$['shared_line'].update('1');
                                                                    } else {
                                                                        el$.form$.el$('device_lines').children$[index].children$['shared_line'].update(null);
                                                                    }


                                                                }" />

                                                            <SelectElement name="auth_id" label="Ext/Number"
                                                                :items="options.extensions" label-prop="name"
                                                                :search="true" :native="false" input-type="search"
                                                                autocomplete="off" :columns="{

                                                                    sm: {
                                                                        container: 4,
                                                                    },
                                                                }" placeholder="Choose Ext/Number" :floating="false"
                                                                @change="(newValue, oldValue, el$) => {

                                                                    el$.form$.el$('device_lines').children$[index].children$['display_name'].update(newValue);
                                                                    el$.form$.el$('device_lines').children$[index].children$['user_id'].update(newValue);


                                                                }" />

                                                            <TextElement name="display_name" label="Display Name"
                                                                :columns="{

                                                                    default: {
                                                                        container: 10,
                                                                    },
                                                                    sm: {
                                                                        container: 3,
                                                                    },
                                                                }" placeholder="Display Name" :floating="false" />

                                                            <StaticElement label="&nbsp;" name="key_advanced" :columns="{

                                                                default: {
                                                                    container: 1,
                                                                },
                                                                sm: {
                                                                    container: 1,
                                                                },
                                                            }"
                                                                :conditions="[() => options?.permissions?.device_key_advanced]">


                                                                <Cog8ToothIcon @click="showLineAdvSettings(index)"
                                                                    class="h-9 w-9 transition duration-500 ease-in-out py-2 rounded-full text-gray-400 hover:bg-gray-200 hover:text-gray-600 active:bg-gray-300 active:duration-150 cursor-pointer" />

                                                            </StaticElement>

                                                            <FormChildModal :show="advModalIndex === index"
                                                                header="Advanced Line Settings" :loading="false"
                                                                @close="closeAdvSettings">
                                                                <div class="px-5 grid gap-y-4">
                                                                    <TextElement name="server_address" label="Domain"
                                                                        placeholder="Enter domain name"
                                                                        :floating="false"
                                                                        :default="options.default_line_options?.server_address" />

                                                                    <TextElement name="user_id"
                                                                        label="User ID"
                                                                        placeholder="Enter user Id"
                                                                        :floating="false"
                                                                        :default="options.default_line_options?.user_id"
                                                                        :conditions="[
                                                                            () => options?.permissions?.manage_device_line_user_id,
                                                                            () => form$.el$(`device_lines.${index}.line_type_id`)?.value === 'externalline'
                                                                        ]" />

                                                                    <TextElement name="auth_id"
                                                                        label="Auth ID"
                                                                        placeholder="Enter auth Id"
                                                                        :floating="false"
                                                                        :default="options.default_line_options?.auth_id"
                                                                        :conditions="[
                                                                            () => options?.permissions?.manage_device_line_auth_id,
                                                                            () => form$.el$(`device_lines.${index}.line_type_id`)?.value === 'externalline'
                                                                        ]" />

                                                                    <TextElement name="password"
                                                                        label="SIP Password"
                                                                        placeholder="Enter sip password"
                                                                        :floating="false"
                                                                        :default="options.default_line_options?.password"
                                                                        :conditions="[
                                                                            () => options?.permissions?.manage_device_line_password,
                                                                            () => form$.el$(`device_lines.${index}.line_type_id`)?.value === 'externalline'
                                                                        ]" />

                                                                    <TextElement name="server_address_primary"
                                                                        label="Primary Server Address"
                                                                        placeholder="Enter primary server address"
                                                                        :floating="false"
                                                                        :default="options.default_line_options?.server_address_primary"
                                                                        :conditions="[() => options?.permissions?.manage_device_line_primary_server]" />

                                                                    <TextElement name="server_address_secondary"
                                                                        label="Secondary Server Address"
                                                                        placeholder="Enter secondary server address"
                                                                        :floating="false"
                                                                        :default="options.default_line_options?.server_address_secondary"
                                                                        :conditions="[() => options?.permissions?.manage_device_line_secondary_server]" />

                                                                    <TextElement name="outbound_proxy_primary"
                                                                        label="Primary Proxy Address"
                                                                        placeholder="Enter primary proxy address"
                                                                        :floating="false"
                                                                        :default="options.default_line_options?.outbound_proxy_primary"
                                                                        :conditions="[() => options?.permissions?.manage_device_line_primary_proxy]" />

                                                                    <TextElement name="outbound_proxy_secondary"
                                                                        label="Secondary Proxy Address"
                                                                        placeholder="Enter secondary Proxy address"
                                                                        :floating="false"
                                                                        :default="options.default_line_options?.outbound_proxy_secondary"
                                                                        :conditions="[() => options?.permissions?.manage_device_line_secondary_proxy]" />

                                                                    <TextElement name="sip_port" label="SIP Port"
                                                                        placeholder="Enter SIP port" :floating="false"
                                                                        :default="options.default_line_options?.sip_port" />

                                                                    <SelectElement name="sip_transport"
                                                                        label="SIP Transport"
                                                                        :items="options.sip_transport_types"
                                                                        :search="true" label-prop="name" :native="false"
                                                                        input-type="search" autocomplete="off"
                                                                        placeholder="Select SIP Transport"
                                                                        :floating="false"
                                                                        :default="options.default_line_options?.sip_transport" />

                                                                    <TextElement name="register_expires"
                                                                        label="Register Expires (Seconds)"
                                                                        placeholder="Enter expiry time (seconds)"
                                                                        :floating="false"
                                                                        :default="options.default_line_options?.register_expires" />

                                                                    <ButtonElement name="close_advanced"
                                                                        button-label="Close" align="center" :full="true"
                                                                        @click="closeAdvSettings" />
                                                                </div>
                                                            </FormChildModal>


                                                        </ObjectElement>
                                                    </template>
                                                </ListElement>

                                                <GroupElement name="lines_container2" />

                                                <ButtonElement name="submit_lines" button-label="Save" :submits="true"
                                                    align="right" />


                                                <!-- Function Keys -->
                                                <StaticElement name="keys_title" tag="h4" content="Device Function Keys"
                                                    description="Assign fucntion keys to this device." />


                                                <GroupElement name="keys_container" />
                                                    <ListElement name="device_keys" :sort="true" size="sm"
                                                    :controls="{ add: options.permissions.device_key_create, remove: options.permissions.device_key_destroy, sort: options.permissions.device_key_up }"
                                                    :add-classes="{ ListElement: { listItem: 'bg-white p-4 mb-4 rounded-lg shadow-md' } }">
                                                    <template #default="{ index }">
                                                        <ObjectElement :name="index"
                                                            :key="form$?.data?.device_keys?.[index]?.key_uuid">

                                                            <HiddenElement name="key_uuid" :meta="true"
                                                                :default="Math.random().toString(36).slice(2)" />
                                                            <HiddenElement name="_generated_label" :meta="true"
                                                                :default="null" />

                                                            <TextElement name="key_index" label="Key" :rules="[
                                                                'nullable',
                                                                'numeric',
                                                            ]" autocomplete="off" :columns="{

                                                                sm: {
                                                                    container: 1,
                                                                },
                                                            }" :default="nextKeyNumber" />

                                                            <SelectElement name="key_type" label="Type"
                                                                :items="keyTypes" :search="true" label-prop="name"
                                                                :native="false" input-type="search" autocomplete="off"
                                                                :columns="{

                                                                    sm: {
                                                                        container: 3,
                                                                    },
                                                                }" placeholder="Choose Function" :floating="false"
                                                                @change="(newValue, oldValue, el$) => {

                                                                    let key_value_select = el$.form$.el$('device_keys.' + index + '.key_value_select')

                                                                    // only clear when this isn’t the very first time (i.e. oldValue was set)
                                                                    if (oldValue !== null && oldValue !== undefined) {
                                                                        key_value_select.clear();
                                                                    }

                                                                    key_value_select.updateItems()

                                                                }" />

                                                            <SelectElement name="key_value_select" label="Value"
                                                                label-prop="name" value-prop="extension" :search="true"
                                                                :native="false" :submit="false"
                                                                :create="['blf', 'speed_dial', 'park']
                                                                    .includes(form$?.data?.device_keys?.[index]?.key_type)" :append-new-option="false"
                                                                input-type="search" autocomplete="off" :columns="{

                                                                    sm: {
                                                                        container: 4,
                                                                    },
                                                                }" placeholder="Choose Ext/Number" :floating="false"
                                                                :items="(query, input) => getKeyValueSelectItems(query, input, index)"
                                                                @change="(newValue, oldValue, el$) => updateLabel(newValue, oldValue, el$, index)"
                                                                :conditions="[
                                                                    ['device_keys.*.key_type', ['line', 'check_voicemail', 'blf', 'speed_dial', 'park']]
                                                                ]" />

                                                            <TextElement name="key_value_text" label="Value" :columns="{
                                                                sm: {
                                                                    container: 4,
                                                                },
                                                            }" placeholder="Enter Value" :floating="false" :disabled="[
                                                                ['device_keys.*.key_type', '']
                                                            ]" :conditions="[
                                                                ['device_keys.*.key_type', '!=', ['line', 'check_voicemail', 'blf', 'speed_dial', 'park']]
                                                            ]" />

                                                            <HiddenElement name="key_value" :meta="true"
                                                                :default="null" />

                                                            <TextElement name="key_label" label="Label" :columns="{

                                                                default: {
                                                                    container: 10,
                                                                },
                                                                sm: {
                                                                    container: 3,
                                                                },
                                                            }" :placeholder="form$?.data?.device_keys?.[index]?._generated_label ?? 'Enter Value'"
                                                                :floating="false" :disabled="[
                                                                    ['device_keys.*.key_type', ['', 'line']]
                                                                ]" />

                                                            <StaticElement label="&nbsp;" name="key_advanced" :columns="{

                                                                default: {
                                                                    container: 1,
                                                                },
                                                                sm: {
                                                                    container: 1,
                                                                },
                                                            }"
                                                                :conditions="[() => options?.permissions?.device_key_advanced]">


                                                                <!-- <Cog8ToothIcon @click="showLineAdvSettings(index)"
                                                                    class="h-9 w-9 transition duration-500 ease-in-out py-2 rounded-full text-gray-400 hover:bg-gray-200 hover:text-gray-600 active:bg-gray-300 active:duration-150 cursor-pointer" /> -->

                                                            </StaticElement>




                                                        </ObjectElement>
                                                    </template>
                                                </ListElement>


                                                <GroupElement name="keys_container2" />

                                                <ButtonElement name="submit_keys" button-label="Save" :submits="true"
                                                    align="right" />

                                            </FormElements>
                                        </div>
                                    </div>
                                </template>
                            </Vueform>
                        </DialogPanel>


                    </TransitionChild>
                </div>
            </div>
        </Dialog>
    </TransitionRoot>
</template>

<script setup>
import { ref, computed, reactive } from "vue";

import { Dialog, DialogPanel, DialogTitle, TransitionChild, TransitionRoot } from '@headlessui/vue'

import { XMarkIcon } from "@heroicons/vue/24/solid";
import { Cog8ToothIcon } from "@heroicons/vue/24/outline";
import FormChildModal from "../FormChildModal.vue"


const props = defineProps({
    show: Boolean,
    options: Object,
    header: String,
    loading: Boolean,
});

const form$ = ref(null)

const advModalIndex = ref(null)

const emit = defineEmits(['close', 'error', 'success', 'refresh-data'])

const keyValueOptionsByIndex = reactive({})

const parkLabelFromValue = (value, base = 5900) => {
    const n = parseInt(value, 10)
    if (Number.isFinite(n) && n > base) {
        return `Park ${n - base}`
    }

    // Fallback: last 1–2 digits
    const m = String(value).match(/(\d{1,2})$/)
    return m ? `Park ${parseInt(m[1], 10)}` : ''
}

const nameOnlyFromOption = (opt) => {
    if (!opt) return null

    const s = String(opt.name ?? '').trim()
    if (!s) return null

    // Split on first " - " and keep the remainder
    const parts = s.split(' - ')
    return (parts.length > 1 ? parts.slice(1).join(' - ') : s).trim()
}


function showLineAdvSettings(index) {
    advModalIndex.value = index
}

function closeAdvSettings() {
    advModalIndex.value = null
}

const handleTabSelected = (activeTab, previousTab) => {

}

const nextLineNumber = computed(() => {
    const deviceLines = form$?.value?.el$('device_lines')
    const children = deviceLines?.children$Array ?? []
    const maxLine = children.reduce((max, child) => {
        const n = parseInt(child?.value?.line_number, 10)
        return Number.isFinite(n) && n > max ? n : max
    }, 0)
    return maxLine + 1
})

const nextKeyNumber = computed(() => {
    const deviceKeys = form$?.value?.el$('device_keys')
    const children = deviceKeys?.children$Array ?? []
    const maxLine = children.reduce((max, child) => {
        const n = parseInt(child?.value?.key_index, 10)
        return Number.isFinite(n) && n > max ? n : max
    }, 0)
    return maxLine + 1
})

const keyTypes = [
    { value: '', name: 'N/A' },
    { value: 'line', name: 'Line' },
    { value: 'blf', name: 'BLF' },
    { value: 'speed_dial', name: 'Speed Dial' },
    { value: 'check_voicemail', name: 'Check Voicemail' },
    { value: 'park', name: 'Park & Retrieve' },
]

const getKeyValueSelectItems = async (query, input, index) => {
    const form$ = input.$parent.el$.form$
    const keyTypeEl = form$.el$('device_keys.' + index + '.key_type')
    const keyType = keyTypeEl?.value

    // line => Line 1..N based on device_lines count
    if (keyType === 'line') {
        const deviceLinesEl = form$.el$('device_lines')
        const count = deviceLinesEl?.children$Array?.length ?? deviceLinesEl?.value?.length ?? 0

        return Array.from({ length: count }, (_, i) => {
            const displayName = form$.el$(`device_lines.${i}.display_name`)?.value ?? ''
            return {
                extension: `${i + 1}`,
                name: `Line ${i + 1}${displayName ? ' - ' + displayName : ''}`,
            }
        })
    }

    // check_voicemail => fetch voicemails
    if (keyType === 'check_voicemail') {
        try {
            const axios = keyTypeEl.$vueform.services.axios
            const response = await axios.post(
                props.options.routes.get_routing_options,
                { category: 'voicemails' }
            )
            keyValueOptionsByIndex[index] = response.data.options ?? []
            return response.data.options ?? []
        } catch (error) {
            emit('error', error)
            return []
        }
    }

    if (keyType === 'blf' || keyType === 'speed_dial') {
        try {
            const axios = keyTypeEl.$vueform.services.axios
            const response = await axios.post(
                props.options.routes.get_routing_options,
                { category: 'extensions' }
            )
            keyValueOptionsByIndex[index] = response.data.options ?? []
            return response.data.options ?? []
        } catch (error) {
            emit('error', error)
            return []
        }
    }

    if (keyType === 'park') {
        const base = 5901

        return Array.from({ length: 10 }, (_, i) => {
            const ext = String(base + i)
            return {
                extension: ext,
                name: `Park ${i + 1} (${ext})`,
            }
        })
    }

    return []
}

const updateLabel = (newValue, oldValue, el$, index) => {
    // Update key_value field
    el$?.form$?.el$('device_keys').children$[index].children$['key_value'].update(newValue)

    const keyLabelEl = el$?.form$?.el$('device_keys').children$[index].children$['key_label']

    // Get the Hidden Element instance
    const generatedLabelEl = el$?.form$?.el$('device_keys').children$[index].children$['_generated_label']

    const keyType = el$?.form$.el$('device_keys.' + index + '.key_type')?.value
    let label = null

    if (keyType === 'park') {
        label = parkLabelFromValue(newValue, 5900)
    }

    if (keyType === 'check_voicemail') {
        const selected = (keyValueOptionsByIndex[index] ?? []).find(o => String(o.extension) === String(newValue))
        label = selected?.extension ? `VM ${selected.extension}` : null
    }

    if (keyType === 'blf' || keyType === 'speed_dial') {
        const selected = (keyValueOptionsByIndex[index] ?? [])
            .find(o => String(o.extension) === String(newValue))

        label = nameOnlyFromOption(selected)

        // Clear the actual input value
        keyLabelEl.update(null)

        // Save the placeholder text to the hidden element
        generatedLabelEl.update(label)

        return
    }

    generatedLabelEl.update(null)

    keyLabelEl.update(label)
}

const submitForm = async (FormData, form$) => {
    // Using form$.requestData will EXCLUDE conditional elements and it 
    // will submit the form as Content-Type: application/json . 
    const requestData = form$.requestData
    // console.log(requestData);

    // Using form$.data will INCLUDE conditional elements and it
    // will submit the form as "Content-Type: application/json".
    // const data = form$.data

    return await form$.$vueform.services.axios.post(props.options.routes.store_route, requestData)
};


function clearErrorsRecursive(el$) {
    // clear this element’s errors
    el$.messageBag?.clear()

    // if it has child elements, recurse into each
    if (el$.children$) {
        Object.values(el$.children$).forEach(childEl$ => {
            clearErrorsRecursive(childEl$)
        })
    }
}

const handleResponse = (response, form$) => {
    // Clear form including nested elements 
    Object.values(form$.elements$).forEach(el$ => {
        clearErrorsRecursive(el$)
    })

    // Display custom errors for elements
    if (response.data.errors) {
        Object.keys(response.data.errors).forEach((elName) => {
            if (form$.el$(elName)) {
                form$.el$(elName).messageBag.append(response.data.errors[elName][0])
            }
        })
    }
}

const handleSuccess = (response, form$) => {
    // console.log(response) // axios response
    // console.log(response.status) // HTTP status code
    // console.log(response.data) // response data

    emit('success', 'success', response.data.messages);
    emit('close');
    emit('refresh-data');
}

const handleError = (error, details, form$) => {
    form$.messageBag.clear() // clear message bag

    switch (details.type) {
        // Error occured while preparing elements (no submit happened)
        case 'prepare':
            console.log(error) // Error object

            form$.messageBag.append('Could not prepare form')
            break

        // Error occured because response status is outside of 2xx
        case 'submit':
            emit('error', error);
            console.log(error) // AxiosError object
            // console.log(error.response) // axios response
            // console.log(error.response.status) // HTTP status code
            // console.log(error.response.data) // response data

            // console.log(error.response.data.errors)


            break

        // Request cancelled (no response object)
        case 'cancel':
            console.log(error) // Error object

            form$.messageBag.append('Request cancelled')
            break

        // Some other errors happened (no response object)
        case 'other':
            console.log(error) // Error object

            form$.messageBag.append('Couldn\'t submit form')
            break
    }
}

</script>
