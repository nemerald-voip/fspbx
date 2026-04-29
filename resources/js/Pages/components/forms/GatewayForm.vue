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
                            class="relative transform rounded-lg bg-white px-4 pb-4 pt-5 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-6xl sm:p-6">
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

                            <div v-if="loading" class="w-full h-full py-10">
                                <div class="flex justify-center items-center space-x-3">
                                    <svg class="animate-spin h-10 w-10 text-blue-600"
                                        xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                            stroke-width="4" />
                                        <path class="opacity-75" fill="currentColor"
                                            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                                    </svg>
                                    <div class="text-lg text-blue-600 m-auto">Loading...</div>
                                </div>
                            </div>

                            <Vueform v-if="!loading" ref="form$" :endpoint="submitForm" @success="handleSuccess"
                                @error="handleError" @response="handleResponse" :display-errors="false"
                                :default="defaultValues">
                                <template #empty>
                                    <div class="lg:grid lg:grid-cols-12 lg:gap-x-5">
                                        <div class="px-2 py-6 sm:px-6 lg:col-span-3 lg:px-0 lg:py-0">
                                            <FormTabs view="vertical">
                                                <FormTab name="settings" label="Settings" :elements="[
                                                    'gateway_uuid',
                                                    'gateway_uuid_clean',
                                                    'settings_header',
                                                    'gateway',
                                                    'enabled',
                                                    'proxy',
                                                    'profile',
                                                    'context',
                                                    'register',
                                                    'username',
                                                    'password',
                                                    'expire_seconds',
                                                    'retry_seconds',
                                                    'settings_container',
                                                    'description',
                                                    'button_container',
                                                    'settings_submit',
                                                ]" />
                                                <FormTab name="advanced" label="Advanced" :elements="[
                                                    'advanced_header',
                                                    'domain_uuid',
                                                    'from_user',
                                                    'from_domain',
                                                    'auth_username',
                                                    'realm',
                                                    'distinct_to',
                                                    'register_transport',
                                                    'register_proxy',
                                                    'outbound_proxy',
                                                    'contact_params',
                                                    'extension',
                                                    'ping',
                                                    'ping_min',
                                                    'ping_max',
                                                    'contact_in_ping',
                                                    'channels',
                                                    'caller_id_in_from',
                                                    'supress_cng',
                                                    'sip_cid_type',
                                                    'codec_prefs',
                                                    'extension_in_contact',
                                                    'hostname',
                                                    'advanced_button_container',
                                                    'advanced_submit',
                                                ]" />
                                                <FormTab name="provider_ips" label="Provider IPs" :elements="[
                                                    'acl_header',
                                                    'gateway_acl_cidrs',
                                                    'acl_button_container',
                                                    'acl_submit',
                                                ]" />
                                            </FormTabs>
                                        </div>

                                        <div
                                            class="sm:px-6 lg:col-span-9 shadow sm:rounded-md space-y-6 text-gray-600 bg-gray-50 px-4 py-6 sm:p-6">
                                            <FormElements>
                                                <HiddenElement name="gateway_uuid" :meta="true" />

                                                <StaticElement name="settings_header" tag="h4" content="Gateway Settings"
                                                    description="Configure the SIP provider connection and registration behavior." />

                                                <StaticElement name="gateway_uuid_clean"
                                                    :conditions="[() => props.options?.item?.gateway_uuid]">
                                                    <div class="mb-1">
                                                        <div class="text-sm font-medium text-gray-600 mb-1">Unique ID</div>
                                                        <div class="flex items-center group">
                                                            <span class="text-sm text-gray-900 select-all font-normal">
                                                                {{ props.options?.item?.gateway_uuid }}
                                                            </span>
                                                            <button type="button"
                                                                @click="handleCopyToClipboard(props.options?.item?.gateway_uuid)"
                                                                class="ml-2 p-1 rounded-full text-gray-400 hover:text-blue-600 hover:bg-blue-50 transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2"
                                                                title="Copy to clipboard">
                                                                <ClipboardDocumentIcon
                                                                    class="h-4 w-4 text-gray-500 hover:text-gray-900 cursor-pointer" />
                                                            </button>
                                                        </div>
                                                    </div>
                                                </StaticElement>

                                                <TextElement name="gateway" label="Gateway" placeholder="Provider or gateway name"
                                                    :floating="false" :columns="{ sm: { container: 6 } }" />

                                                <ToggleElement name="enabled" text="Gateway Enabled" true-value="true"
                                                    false-value="false" :labels="{ on: 'On', off: 'Off' }"
                                                    :columns="{ sm: { container: 6 } }" label="&nbsp;" />

                                                <TextElement name="proxy" label="Proxy" placeholder="sip.provider.example"
                                                    :floating="false" :columns="{ sm: { container: 6 } }" />

                                                <SelectElement name="profile" :items="profileOptions" :search="true"
                                                    :native="false" label="SIP Profile" input-type="search"
                                                    autocomplete="off" placeholder="Select profile" :floating="false"
                                                    :strict="true" :columns="{ sm: { container: 6 } }" />

                                                <TextElement name="context" label="Context" placeholder="public"
                                                    :floating="false" :columns="{ sm: { container: 6 } }" />

                                                <SelectElement name="register" :items="booleanOptions" :native="false"
                                                    label="Register" placeholder="Select registration state"
                                                    :floating="false" :strict="true"
                                                    :columns="{ sm: { container: 6 } }" />

                                                <TextElement name="username" label="Username" autocomplete="off"
                                                    placeholder="SIP username" :floating="false"
                                                    :columns="{ sm: { container: 6 } }" />

                                                <TextElement name="password" label="Password" input-type="password"
                                                    autocomplete="new-password" placeholder="SIP password"
                                                    :floating="false" :columns="{ sm: { container: 6 } }" />

                                                <TextElement name="expire_seconds" input-type="number"
                                                    label="Expire Seconds" :floating="false"
                                                    :columns="{ sm: { container: 6 } }" />

                                                <TextElement name="retry_seconds" input-type="number"
                                                    label="Retry Seconds" :floating="false"
                                                    :columns="{ sm: { container: 6 } }" />

                                                <GroupElement name="settings_container" />

                                                <TextareaElement name="description" label="Description" :rows="2" />

                                                <GroupElement name="button_container" />

                                                <ButtonElement name="settings_submit" button-label="Save" :submits="true"
                                                    align="right" />

                                                <StaticElement name="advanced_header" tag="h4" content="Advanced Settings"
                                                    description="Fine tune SIP identity, transport, ping, caller ID, and domain options." />

                                                <SelectElement name="domain_uuid" :items="domainOptions" :search="true"
                                                    :native="false" label="Domain" input-type="search"
                                                    autocomplete="off" placeholder="Select domain" :floating="false"
                                                    :strict="false" :columns="{ sm: { container: 6 } }"
                                                    :conditions="[() => domainOptions.length > 0]" />

                                                <TextElement name="from_user" label="From User" :floating="false"
                                                    :columns="{ sm: { container: 6 } }" />
                                                <TextElement name="from_domain" label="From Domain" :floating="false"
                                                    :columns="{ sm: { container: 6 } }" />
                                                <TextElement name="auth_username" label="Auth Username" :floating="false"
                                                    :columns="{ sm: { container: 6 } }" />
                                                <TextElement name="realm" label="Realm" :floating="false"
                                                    :columns="{ sm: { container: 6 } }" />

                                                <SelectElement name="distinct_to" :items="emptyBooleanOptions"
                                                    :native="false" label="Distinct To" :floating="false"
                                                    :strict="false" :columns="{ sm: { container: 6 } }" />

                                                <SelectElement name="register_transport" :items="transportOptions"
                                                    :native="false" label="Register Transport" :floating="false"
                                                    :strict="false" :columns="{ sm: { container: 6 } }" />

                                                <TextElement name="register_proxy" label="Register Proxy" :floating="false"
                                                    :columns="{ sm: { container: 6 } }" />
                                                <TextElement name="outbound_proxy" label="Outbound Proxy" :floating="false"
                                                    :columns="{ sm: { container: 6 } }" />
                                                <TextElement name="contact_params" label="Contact Params" :floating="false"
                                                    :columns="{ sm: { container: 6 } }" />
                                                <TextElement name="extension" label="Extension" :floating="false"
                                                    :columns="{ sm: { container: 6 } }" />
                                                <TextElement name="ping" input-type="number" label="Ping" :floating="false"
                                                    :columns="{ sm: { container: 6 } }" />
                                                <TextElement name="ping_min" input-type="number" label="Ping Min"
                                                    :floating="false" :columns="{ sm: { container: 6 } }" />
                                                <TextElement name="ping_max" input-type="number" label="Ping Max"
                                                    :floating="false" :columns="{ sm: { container: 6 } }" />

                                                <SelectElement name="contact_in_ping" :items="emptyBooleanOptions"
                                                    :native="false" label="Contact In Ping" :floating="false"
                                                    :strict="false" :columns="{ sm: { container: 6 } }" />

                                                <TextElement name="channels" input-type="number" label="Channels"
                                                    :floating="false" :columns="{ sm: { container: 6 } }"
                                                    :conditions="[() => props.permissions?.channels]" />

                                                <SelectElement name="caller_id_in_from" :items="emptyBooleanOptions"
                                                    :native="false" label="Caller ID In From" :floating="false"
                                                    :strict="false" :columns="{ sm: { container: 6 } }" />

                                                <SelectElement name="supress_cng" :items="emptyBooleanOptions"
                                                    :native="false" label="Suppress CNG" :floating="false"
                                                    :strict="false" :columns="{ sm: { container: 6 } }" />

                                                <SelectElement name="sip_cid_type" :items="sipCidOptions" :native="false"
                                                    label="SIP CID Type" :floating="false" :strict="false"
                                                    :columns="{ sm: { container: 6 } }" />

                                                <TextElement name="codec_prefs" label="Codec Preferences" :floating="false"
                                                    :columns="{ sm: { container: 6 } }" />

                                                <SelectElement name="extension_in_contact" :items="emptyBooleanOptions"
                                                    :native="false" label="Extension In Contact" :floating="false"
                                                    :strict="false" :columns="{ sm: { container: 6 } }" />

                                                <TextElement name="hostname" label="Hostname" :floating="false"
                                                    :columns="{ sm: { container: 6 } }" />

                                                <GroupElement name="advanced_button_container" />

                                                <ButtonElement name="advanced_submit" button-label="Save" :submits="true"
                                                    align="right" />

                                                <StaticElement name="acl_header" tag="h4" content="Provider IPs"
                                                    description="Enter the IP addresses or CIDR ranges this provider sends traffic from." />

                                                <ListElement name="gateway_acl_cidrs" :sort="true" size="sm"
                                                    :initial="0"
                                                    :controls="{ add: true, remove: true, sort: true }"
                                                    :add-classes="{ ListElement: { listItem: 'bg-white p-4 mb-4 rounded-lg shadow-md' } }">
                                                    <template #default="{ index }">
                                                        <ObjectElement :name="index">
                                                            <TextElement name="node_cidr" label="IP / CIDR"
                                                                autocomplete="off"
                                                                placeholder="203.0.113.10 or 198.51.100.0/24"
                                                                :floating="false"
                                                                :columns="{ sm: { container: 12 } }" />
                                                        </ObjectElement>
                                                    </template>
                                                </ListElement>

                                                <GroupElement name="acl_button_container" />

                                                <ButtonElement name="acl_submit" button-label="Save" :submits="true"
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
import { computed, ref } from "vue";
import { Dialog, DialogPanel, DialogTitle, TransitionChild, TransitionRoot } from "@headlessui/vue";
import { ClipboardDocumentIcon } from "@heroicons/vue/24/outline";
import { XMarkIcon } from "@heroicons/vue/24/solid";

const props = defineProps({
    show: Boolean,
    options: Object,
    permissions: {
        type: Object,
        default: () => ({}),
    },
    loading: Boolean,
    header: {
        type: String,
        default: "Gateway",
    },
    mode: {
        type: String,
        default: "create",
    },
});

const emit = defineEmits(["close", "error", "success", "refresh-data"]);

const form$ = ref(null);

const gatewayFields = [
    "gateway_uuid",
    "domain_uuid",
    "gateway",
    "username",
    "password",
    "distinct_to",
    "auth_username",
    "realm",
    "from_user",
    "from_domain",
    "proxy",
    "register_proxy",
    "outbound_proxy",
    "expire_seconds",
    "register",
    "register_transport",
    "contact_params",
    "retry_seconds",
    "extension",
    "ping",
    "ping_min",
    "ping_max",
    "contact_in_ping",
    "channels",
    "caller_id_in_from",
    "supress_cng",
    "sip_cid_type",
    "codec_prefs",
    "extension_in_contact",
    "context",
    "profile",
    "hostname",
    "enabled",
    "description",
    "gateway_acl_cidrs",
];

const defaultValues = computed(() => {
    const item = props.options?.item ?? {};
    const defaults = {};

    gatewayFields.forEach((field) => {
        defaults[field] = item[field] ?? null;
    });

    defaults.expire_seconds = item.expire_seconds ?? "800";
    defaults.register = item.register ?? "true";
    defaults.retry_seconds = item.retry_seconds ?? "30";
    defaults.channels = item.channels ?? 0;
    defaults.context = item.context ?? "public";
    defaults.enabled = item.enabled ?? "true";
    defaults.domain_uuid = item.domain_uuid ?? null;
    defaults.gateway_acl_cidrs = normalizeAclCidrs(item.gateway_acl_cidrs);

    return defaults;
});

const normalizeAclCidrs = (value) => {
    if (Array.isArray(value)) {
        return value
            .map((item) => ({
                node_cidr: typeof item === "string" ? item : item?.node_cidr,
            }))
            .filter((item) => item.node_cidr);
    }

    return String(value ?? "")
        .split(/[\r\n,]+/)
        .map((cidr) => cidr.trim())
        .filter(Boolean)
        .map((cidr) => ({ node_cidr: cidr }));
};

const profileOptions = computed(() => props.options?.profile_options ?? []);
const domainOptions = computed(() => props.options?.domain_options ?? []);

const booleanOptions = [
    { value: "true", label: "True" },
    { value: "false", label: "False" },
];

const emptyBooleanOptions = [
    { value: null, label: "" },
    ...booleanOptions,
];

const transportOptions = [
    { value: null, label: "" },
    { value: "udp", label: "UDP" },
    { value: "tcp", label: "TCP" },
    { value: "tls", label: "TLS" },
];

const sipCidOptions = [
    { value: null, label: "" },
    { value: "none", label: "None" },
    { value: "pid", label: "PID" },
    { value: "rpid", label: "RPID" },
];

const handleCopyToClipboard = (text) => {
    navigator.clipboard.writeText(text).then(() => {
        emit("success", "success", { message: ["Copied to clipboard."] });
    }).catch(() => {
        emit("error", { response: { data: { errors: { request: ["Failed to copy to clipboard."] } } } });
    });
};

const submitForm = async (FormData, form$) => {
    const requestData = form$.requestData;
    const route = props.mode === "create"
        ? props.options.routes.store_route
        : props.options.routes.update_route;

    if (props.mode === "create") {
        return await form$.$vueform.services.axios.post(route, requestData);
    }

    return await form$.$vueform.services.axios.put(route, requestData);
};

function clearErrorsRecursive(el$) {
    el$.messageBag?.clear();

    if (el$.children$) {
        Object.values(el$.children$).forEach((childEl$) => {
            clearErrorsRecursive(childEl$);
        });
    }
}

const handleResponse = (response, form$) => {
    Object.values(form$.elements$).forEach((el$) => {
        clearErrorsRecursive(el$);
    });

    if (response.data.errors) {
        Object.keys(response.data.errors).forEach((elName) => {
            if (form$.el$(elName)) {
                form$.el$(elName).messageBag.append(response.data.errors[elName][0]);
            }
        });
    }
};

const handleSuccess = (response) => {
    emit("success", "success", response.data.messages);
    emit("refresh-data");
    emit("close");
};

const handleError = (error, details, form$) => {
    form$.messageBag.clear();

    if (details.type === "submit") {
        emit("error", error);
        return;
    }

    form$.messageBag.append("Could not submit form");
};
</script>
