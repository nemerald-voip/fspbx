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
                            class="relative transform rounded-lg bg-white px-4 pb-4 pt-5 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-7xl sm:p-6">
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

                            <div v-if="loading" class="w-full py-10">
                                <div class="flex items-center justify-center space-x-3">
                                    <svg class="h-10 w-10 animate-spin text-blue-600" xmlns="http://www.w3.org/2000/svg"
                                        fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                            stroke-width="4" />
                                        <path class="opacity-75" fill="currentColor"
                                            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                                    </svg>
                                    <div class="text-lg text-blue-600">Loading...</div>
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
                                                    'dialplan_uuid',
                                                    'dialplan_uuid_clean',
                                                    'settings_header',
                                                    'dialplan_name',
                                                    'dialplan_enabled',
                                                    'dialplan_number',
                                                    'domain_uuid',
                                                    'dialplan_context',
                                                    'hostname',
                                                    'dialplan_order',
                                                    'dialplan_continue',
                                                    'dialplan_destination',
                                                    'description_container',
                                                    'dialplan_description',
                                                    'settings_button_container',
                                                    'settings_submit',
                                                ]" />
                                                <FormTab name="rules" label="Rules" :elements="[
                                                    'rules_header',
                                                    'dialplan_rule_groups',
                                                    'rules_button_container',
                                                    'rules_submit',
                                                ]" />
                                                <FormTab name="xml" label="XML Editor" :elements="[
                                                    'advanced_header',
                                                    'xml_editor',
                                                    'advanced_button_container',
                                                    'advanced_submit',
                                                ]" />
                                            </FormTabs>
                                        </div>

                                        <div
                                            class="sm:px-6 lg:col-span-9 shadow sm:rounded-md space-y-6 text-gray-600 bg-gray-50 px-4 py-6 sm:p-6">
                                            <FormElements>
                                                <HiddenElement name="dialplan_uuid" :meta="true" />

                                                <StaticElement name="settings_header" tag="h4"
                                                    content="Dialplan Settings"
                                                    description="Configure the extension metadata FreeSWITCH uses to order and match this dialplan." />

                                                <StaticElement name="dialplan_uuid_clean"
                                                    :conditions="[() => props.options?.item?.dialplan_uuid]">
                                                    <div class="mb-1">
                                                        <div class="mb-1 text-sm font-medium text-gray-600">Unique ID
                                                        </div>
                                                        <div class="flex items-center group">
                                                            <span class="select-all text-sm font-normal text-gray-900">
                                                                {{ props.options?.item?.dialplan_uuid }}
                                                            </span>
                                                            <button type="button"
                                                                @click="handleCopyToClipboard(props.options?.item?.dialplan_uuid)"
                                                                class="ml-2 rounded-full p-1 text-gray-400 transition-colors hover:bg-blue-50 hover:text-blue-600 focus:outline-none focus:ring-2 focus:ring-offset-2"
                                                                title="Copy to clipboard">
                                                                <ClipboardDocumentIcon
                                                                    class="h-4 w-4 cursor-pointer text-gray-500 hover:text-gray-900" />
                                                            </button>
                                                        </div>
                                                    </div>
                                                </StaticElement>

                                                <TextElement name="dialplan_name" label="Name"
                                                    placeholder="example_route" :floating="false"
                                                    :columns="{ sm: { container: 6 } }" />

                                                <ToggleElement name="dialplan_enabled" text="Dialplan Enabled"
                                                    true-value="true" false-value="false"
                                                    :labels="{ on: 'On', off: 'Off' }"
                                                    :columns="{ sm: { container: 6 } }" label="&nbsp;" />

                                                <TextElement name="dialplan_number" label="Number"
                                                    placeholder="Extension or pattern label" :floating="false"
                                                    :columns="{ sm: { container: 6 } }" />

                                                <SelectElement name="domain_uuid" :items="domainOptions" :search="true"
                                                    :native="false" label="Domain" input-type="search"
                                                    autocomplete="off" placeholder="Select domain" :floating="false"
                                                    :strict="false" :columns="{ sm: { container: 6 } }"
                                                    :conditions="[() => domainOptions.length > 0]" />

                                                <SelectElement name="dialplan_context" :items="contextOptions"
                                                    :search="true" :native="false" label="Context" input-type="search"
                                                    allow-absent autocomplete="off" :strict="false"
                                                    placeholder="Select or enter context" :floating="false"
                                                    :columns="{ sm: { container: 6 } }" />

                                                <TextElement name="hostname" label="Hostname" :floating="false"
                                                    :columns="{ sm: { container: 6 } }" />

                                                <TextElement name="dialplan_order" input-type="number" label="Order"
                                                    :floating="false" :columns="{ sm: { container: 4 } }" />

                                                <SelectElement name="dialplan_continue" :items="booleanOptions"
                                                    :native="false" label="Continue" :floating="false" :strict="true"
                                                    :columns="{ sm: { container: 4 } }" />

                                                <SelectElement name="dialplan_destination" :items="booleanOptions"
                                                    :native="false" label="Destination" :floating="false" :strict="true"
                                                    :columns="{ sm: { container: 4 } }" />

                                                <GroupElement name="description_container" />

                                                <TextareaElement name="dialplan_description" label="Description"
                                                    :rows="2" />

                                                <GroupElement name="settings_button_container" />

                                                <ButtonElement name="settings_submit" button-label="Save"
                                                    :submits="true" align="right" @click="submitMode = 'builder'" />

                                                <StaticElement name="rules_header" tag="h4" content="Rules"
                                                    description="Build this dialplan as rule cards with conditions, actions, and optional otherwise actions." />

                                                <ListElement name="dialplan_rule_groups" :sort="true" size="sm"
                                                    :initial="0" :controls="{ add: true, remove: true, sort: true }"
                                                    :add-classes="{ ListElement: { listItem: 'bg-white p-5 mb-6 rounded-lg shadow-sm border-l-4 border-l-blue-500 border border-gray-200' } }">
                                                    <template #default="{ index }">
                                                        <ObjectElement :name="index">
                                                            <StaticElement name="rule_card_header"
                                                                :columns="{ sm: { container: 12 } }">
                                                                <div>
                                                                    <div class="text-lg font-semibold text-gray-900">
                                                                        Rule {{ index + 1 }}
                                                                    </div>
                                                                </div>
                                                            </StaticElement>

                                                            <StaticElement name="rule_card_divider"
                                                                :columns="{ container: 12 }">
                                                                <div class="mb-5 border-b border-gray-200"></div>
                                                            </StaticElement>

                                                            <StaticElement name="conditions_header">
                                                                <div class="mb-4 mt-4">
                                                                    <div class="text-lg font-semibold text-gray-900">
                                                                        When
                                                                    </div>
                                                                    <div class="mt-1 text-sm text-gray-500">
                                                                        All listed conditions must match before actions run.
                                                                    </div>
                                                                </div>
                                                            </StaticElement>

                                                            <ListElement name="conditions" :sort="true" size="sm"
                                                                :initial="0"
                                                                :controls="{ add: true, remove: true, sort: true }"
                                                                :add-classes="{ ListElement: { listItem: 'bg-blue-100/40 p-2 mb-2 rounded-md border border-blue-150' } }">
                                                                <template #default="{ index: conditionIndex }">
                                                                    <ObjectElement :name="conditionIndex">
                                                                        <SelectElement name="tag"
                                                                            :items="conditionTagOptions" :native="false"
                                                                            label="Type" :floating="false"
                                                                            :strict="true"
                                                                            :columns="{ default: { container: 12 }, sm: { wrapper:4, container: 12 }, xl: { wrapper:12, container: 2 } }" />

                                                                        <SelectElement name="field"
                                                                            :items="conditionOptions" :search="true"
                                                                            :create="true" :native="false" label="Field"
                                                                            input-type="search" allow-absent
                                                                            autocomplete="off" :strict="false"
                                                                            :floating="false"
                                                                            :columns="{ default: { container: 12 }, sm: { container: 4 }, xl: { container: 3 } }" />

                                                                        <TextElement name="expression"
                                                                            label="Pattern"
                                                                            placeholder="Regular expression or value"
                                                                            :floating="false"
                                                                            :columns="{ default: { container: 12 }, sm: { container: 8 }, xl: { container: 4 } }" />

                                                                        <SelectElement name="break"
                                                                            :items="breakOptions" :native="false"
                                                                            label="Break" :floating="false"
                                                                            :strict="false"
                                                                            :columns="{ default: { container: 12 }, sm: { container: 4 }, xl: { container: 2 } }" />

                                                                        <ToggleElement name="enabled" label="On"
                                                                            true-value="true" false-value="false"
                                                                            default="true"
                                                                            :columns="{ default: { container: 12 }, sm: { container: 6 }, xl: { container: 1 } }"
                                                                            :add-class="{ wrapper: 'pt-1' }" />
                                                                    </ObjectElement>
                                                                </template>
                                                            </ListElement>

                                                            <StaticElement name="actions_header">
                                                                <div class="mb-4 mt-8 border-t border-gray-200 pt-6">
                                                                    <div class="text-lg font-semibold text-gray-900">
                                                                        Then
                                                                    </div>
                                                                    <div class="mt-1 text-sm text-gray-500">
                                                                        Run these actions when the conditions match.
                                                                    </div>
                                                                </div>
                                                            </StaticElement>

                                                            <ListElement name="actions" :sort="true" size="sm"
                                                                :initial="0"
                                                                :controls="{ add: true, remove: true, sort: true }"
                                                                :add-classes="{ ListElement: { listItem: 'bg-emerald-100/40 p-2 mb-2 rounded-md border border-emerald-150' } }">

                                                                <template #default="{ index: actionIndex }">
                                                                    <ObjectElement :name="actionIndex">
                                                                        <SelectElement name="application"
                                                                            :items="applicationOptions" :search="true"
                                                                            :native="false" label="Do"
                                                                            input-type="search" allow-absent
                                                                            autocomplete="off" :strict="false"
                                                                            :floating="false"
                                                                            :columns="{ default: { container: 12 }, sm: { container: 3 }, xl: { container: 3 } }" />

                                                                        <TextElement name="data" label="Arguments"
                                                                            placeholder="Application data"
                                                                            :floating="false"
                                                                            :columns="{ default: { container: 12 }, sm: { container: 9 }, xl: { container: 7 } }" />

                                                                        <ToggleElement name="inline" label="Inline"
                                                                            :columns="{ default: { container: 6 }, sm: { container: 2 }, xl: { container: 1 } }"
                                                                            :add-class="{ wrapper: 'pt-1' }" />

                                                                        <ToggleElement name="enabled" label="On"
                                                                            true-value="true" false-value="false"
                                                                            default="true"
                                                                            :columns="{ default: { container: 6 }, sm: { container: 2 }, xl: { container: 1 } }"
                                                                            :add-class="{ wrapper: 'pt-1' }" />
                                                                    </ObjectElement>
                                                                </template>
                                                            </ListElement>

                                                            <StaticElement name="anti_actions_header">
                                                                <div class="mb-4 mt-8 border-t border-gray-200 pt-6">
                                                                    <div class="text-lg font-semibold text-gray-900">
                                                                        Otherwise
                                                                    </div>
                                                                    <div class="mt-1 text-sm text-gray-500">
                                                                        Run these actions when the conditions do not match.
                                                                    </div>
                                                                </div>
                                                            </StaticElement>

                                                            <ListElement name="anti_actions" :sort="true" size="sm"
                                                                :initial="0"
                                                                :controls="{ add: true, remove: true, sort: true }"
                                                                :add-classes="{ ListElement: { listItem: 'bg-amber-100/40 p-2 mb-2 rounded-md border border-amber-150' } }">

                                                                <template #default="{ index: antiActionIndex }">
                                                                    <ObjectElement :name="antiActionIndex">
                                                                        <SelectElement name="application"
                                                                            :items="applicationOptions" :search="true"
                                                                            :native="false" label="Do"
                                                                            input-type="search" allow-absent
                                                                            autocomplete="off" :strict="false"
                                                                            :floating="false"
                                                                            :columns="{ default: { container: 12 }, sm: { container: 3 }, xl: { container: 3 } }" />

                                                                        <TextElement name="data" label="Arguments"
                                                                            placeholder="Application data"
                                                                            :floating="false"
                                                                            :columns="{ default: { container: 12 }, sm: { container: 9 }, xl: { container: 7 } }" />

                                                                        <ToggleElement name="inline" label="Inline"
                                                                            :columns="{ default: { container: 12 }, sm: { container: 6 }, xl: { container: 1 } }"
                                                                            :add-class="{ wrapper: 'pt-1' }" />

                                                                        <ToggleElement name="enabled" label="On"
                                                                            true-value="true" false-value="false"
                                                                            default="true"
                                                                            :columns="{ default: { container: 12 }, sm: { container: 6 }, xl: { container: 1 } }"
                                                                            :add-class="{ wrapper: 'pt-1' }" />
                                                                    </ObjectElement>
                                                                </template>
                                                            </ListElement>
                                                        </ObjectElement>
                                                    </template>
                                                </ListElement>

                                                <GroupElement name="rules_button_container" />

                                                <ButtonElement name="rules_submit" button-label="Save" :submits="true"
                                                    align="right" @click="submitMode = 'builder'" />

                                                <StaticElement name="advanced_header" tag="h4" content="XML Editor"
                                                    description="Edit the raw FreeSWITCH XML for this dialplan. Saving here updates the XML directly." />

                                                <StaticElement name="xml_editor">
                                                    <div>
                                                        <div class="mb-2 flex items-center justify-between gap-3">
                                                            <div class="text-sm font-medium text-gray-700">Dialplan XML
                                                            </div>
                                                            <select v-model="xmlEditorTheme"
                                                                class="rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                                                <option value="chrome">Light</option>
                                                                <option value="one_dark">Dark</option>
                                                            </select>
                                                        </div>

                                                        <div
                                                            class="overflow-hidden rounded-lg border border-gray-200 shadow-sm">
                                                            <AceEditor v-model="xmlEditorContent" lang="xml"
                                                                :theme="xmlEditorTheme"
                                                                :options="{ fontSize: 12, tabSize: 4, showPrintMargin: false }"
                                                                height="60vh" />
                                                        </div>

                                                        <div v-if="xmlEditorError" class="mt-2 text-sm text-red-600">
                                                            {{ xmlEditorError }}
                                                        </div>
                                                    </div>
                                                </StaticElement>

                                                <GroupElement name="advanced_button_container" />

                                                <ButtonElement name="advanced_submit" button-label="Save"
                                                    :submits="true" align="right" @click="submitMode = 'xml'" />
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
import { computed, ref, watch } from "vue";
import { Dialog, DialogPanel, DialogTitle, TransitionChild, TransitionRoot } from "@headlessui/vue";
import { ClipboardDocumentIcon } from "@heroicons/vue/24/outline";
import { XMarkIcon } from "@heroicons/vue/24/solid";
import AceEditor from "@generalComponents/AceEditor.vue";

const props = defineProps({
    show: Boolean,
    options: Object,
    loading: Boolean,
    header: {
        type: String,
        default: "Dialplan",
    },
    mode: {
        type: String,
        default: "create",
    },
});

const emit = defineEmits(["close", "error", "success", "refresh-data", "saved"]);

const form$ = ref(null);
const submitMode = ref("builder");
const xmlEditorTheme = ref("chrome");
const xmlEditorContent = ref("");
const xmlEditorError = ref(null);

const fields = [
    "dialplan_uuid",
    "domain_uuid",
    "hostname",
    "dialplan_name",
    "dialplan_number",
    "dialplan_destination",
    "dialplan_context",
    "dialplan_continue",
    "dialplan_order",
    "dialplan_enabled",
    "dialplan_description",
    "dialplan_xml",
    "dialplan_rule_groups",
];

const defaultValues = computed(() => {
    const item = props.options?.item ?? {};
    const defaults = {};

    fields.forEach((field) => {
        defaults[field] = item[field] ?? null;
    });

    defaults.domain_uuid = item.domain_uuid ?? "";
    defaults.dialplan_destination = item.dialplan_destination ?? "false";
    defaults.dialplan_continue = item.dialplan_continue ?? "false";
    defaults.dialplan_order = item.dialplan_order ?? "200";
    defaults.dialplan_enabled = item.dialplan_enabled ?? "true";
    defaults.dialplan_context = item.dialplan_context ?? null;
    defaults.dialplan_rule_groups = detailsToRuleGroups(item.dialplan_details);

    return defaults;
});

const normalizeDetails = (details) => {
    if (!Array.isArray(details)) {
        return [];
    }

    return details.map((detail, index) => ({
        dialplan_detail_uuid: detail.dialplan_detail_uuid ?? null,
        dialplan_detail_tag: detail.dialplan_detail_tag ?? "action",
        dialplan_detail_type: detail.dialplan_detail_type ?? null,
        dialplan_detail_data: detail.dialplan_detail_data ?? null,
        dialplan_detail_break: detail.dialplan_detail_break ?? null,
        dialplan_detail_inline: detail.dialplan_detail_inline ?? null,
        dialplan_detail_group: detail.dialplan_detail_group ?? 0,
        dialplan_detail_order: detail.dialplan_detail_order ?? ((index + 1) * 10),
        dialplan_detail_enabled: detail.dialplan_detail_enabled ?? "true",
    }));
};

const domainOptions = computed(() => props.options?.domain_options ?? []);
const contextOptions = computed(() => props.options?.context_options ?? []);
const conditionOptions = computed(() => props.options?.condition_options ?? []);
const applicationOptions = computed(() => props.options?.application_options ?? []);

const booleanOptions = [
    { value: "true", label: "True" },
    { value: "false", label: "False" },
];

const conditionTagOptions = [
    { value: "condition", label: "Condition" },
    { value: "regex", label: "Regex" },
];

const breakOptions = [
    { value: null, label: "" },
    { value: "on-true", label: "On True" },
    { value: "on-false", label: "On False" },
    { value: "always", label: "Always" },
    { value: "never", label: "Never" },
];

const defaultXmlTemplate = (item = {}) => {
    const name = item.dialplan_name ?? "";
    const continueValue = item.dialplan_continue ?? "false";
    const uuid = item.dialplan_uuid ?? "";

    return `<extension name="${name}" continue="${continueValue}" uuid="${uuid}">\n</extension>`;
};

watch(() => props.options?.item, (item) => {
    xmlEditorContent.value = item?.dialplan_xml || defaultXmlTemplate(item);
}, { immediate: true });

const toggleIsEnabled = (value) => value === true || value === "true" || value === 1 || value === "1";

const detailsToRuleGroups = (details) => {
    const normalized = normalizeDetails(details);

    if (!normalized.length) {
        return [
            {
                conditions: [
                    {
                        tag: "condition",
                        field: "destination_number",
                        expression: null,
                        break: null,
                        enabled: "true",
                    },
                ],
                actions: [
                    {
                        application: "transfer",
                        data: null,
                        inline: false,
                        enabled: "true",
                    },
                ],
                anti_actions: [],
            },
        ];
    }

    const groups = new Map();

    normalized.forEach((detail) => {
        const groupKey = Number(detail.dialplan_detail_group ?? 0);

        if (!groups.has(groupKey)) {
            groups.set(groupKey, {
                sort_order: groupKey,
                conditions: [],
                actions: [],
                anti_actions: [],
            });
        }

        const group = groups.get(groupKey);

        if (["condition", "regex"].includes(detail.dialplan_detail_tag)) {
            group.conditions.push({
                tag: detail.dialplan_detail_tag,
                field: detail.dialplan_detail_type,
                expression: detail.dialplan_detail_data,
                break: detail.dialplan_detail_break,
                enabled: toggleIsEnabled(detail.dialplan_detail_enabled) ? "true" : "false",
            });
        }

        if (detail.dialplan_detail_tag === "action") {
            group.actions.push({
                application: detail.dialplan_detail_type,
                data: detail.dialplan_detail_data,
                inline: detail.dialplan_detail_inline === "true",
                enabled: toggleIsEnabled(detail.dialplan_detail_enabled) ? "true" : "false",
            });
        }

        if (detail.dialplan_detail_tag === "anti-action") {
            group.anti_actions.push({
                application: detail.dialplan_detail_type,
                data: detail.dialplan_detail_data,
                inline: detail.dialplan_detail_inline === "true",
                enabled: toggleIsEnabled(detail.dialplan_detail_enabled) ? "true" : "false",
            });
        }
    });

    return Array.from(groups.values())
        .sort((a, b) => Number(a.sort_order) - Number(b.sort_order))
        .map((group) => ({
            ...group,
            conditions: group.conditions.length ? group.conditions : [],
            actions: group.actions.length ? group.actions : [],
            anti_actions: group.anti_actions.length ? group.anti_actions : [],
        }));
};

const ruleGroupsToDetails = (ruleGroups) => {
    if (!Array.isArray(ruleGroups)) {
        return [];
    }

    const details = [];

    ruleGroups.forEach((group, groupIndex) => {
        const groupNumber = groupIndex * 10;
        let order = 10;
        let conditionsCount = 0;
        const actions = group?.actions ?? [];
        const antiActions = group?.anti_actions ?? [];

        (group?.conditions ?? []).forEach((condition) => {
            if (!condition?.field && !condition?.expression) {
                return;
            }

            details.push({
                dialplan_detail_tag: condition.tag || "condition",
                dialplan_detail_type: condition.field,
                dialplan_detail_data: condition.expression,
                dialplan_detail_break: condition.break,
                dialplan_detail_inline: null,
                dialplan_detail_group: groupNumber,
                dialplan_detail_order: order,
                dialplan_detail_enabled: toggleIsEnabled(condition.enabled ?? true) ? "true" : "false",
            });
            conditionsCount += 1;
            order += 10;
        });

        if (conditionsCount === 0 && [...actions, ...antiActions].some((action) => action?.application || action?.data)) {
            details.push({
                dialplan_detail_tag: "condition",
                dialplan_detail_type: null,
                dialplan_detail_data: null,
                dialplan_detail_break: null,
                dialplan_detail_inline: null,
                dialplan_detail_group: groupNumber,
                dialplan_detail_order: order,
                dialplan_detail_enabled: "true",
            });
            order += 10;
        }

        actions.forEach((action) => {
            if (!action?.application && !action?.data) {
                return;
            }

            details.push({
                dialplan_detail_tag: "action",
                dialplan_detail_type: action.application,
                dialplan_detail_data: action.data,
                dialplan_detail_break: null,
                dialplan_detail_inline: toggleIsEnabled(action.inline) ? "true" : null,
                dialplan_detail_group: groupNumber,
                dialplan_detail_order: order,
                dialplan_detail_enabled: toggleIsEnabled(action.enabled ?? true) ? "true" : "false",
            });
            order += 10;
        });

        antiActions.forEach((action) => {
            if (!action?.application && !action?.data) {
                return;
            }

            details.push({
                dialplan_detail_tag: "anti-action",
                dialplan_detail_type: action.application,
                dialplan_detail_data: action.data,
                dialplan_detail_break: null,
                dialplan_detail_inline: toggleIsEnabled(action.inline) ? "true" : null,
                dialplan_detail_group: groupNumber,
                dialplan_detail_order: order,
                dialplan_detail_enabled: toggleIsEnabled(action.enabled ?? true) ? "true" : "false",
            });
            order += 10;
        });
    });

    return details;
};

const handleCopyToClipboard = (text) => {
    navigator.clipboard.writeText(text).then(() => {
        emit("success", "success", { message: ["Copied to clipboard."] });
    }).catch(() => {
        emit("error", { response: { data: { errors: { request: ["Failed to copy to clipboard."] } } } });
    });
};

const submitForm = async (FormData, form$) => {
    const requestData = { ...form$.requestData };
    xmlEditorError.value = null;

    requestData.editor_mode = submitMode.value;
    requestData.dialplan_xml = xmlEditorContent.value;

    if (submitMode.value === "xml") {
        delete requestData.dialplan_rule_groups;
        delete requestData.dialplan_details;
    } else {
        requestData.dialplan_details = ruleGroupsToDetails(requestData.dialplan_rule_groups);
        delete requestData.dialplan_xml;
    }

    delete requestData.dialplan_rule_groups;

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
    xmlEditorError.value = null;

    Object.values(form$.elements$).forEach((el$) => {
        clearErrorsRecursive(el$);
    });

    if (response.data.errors) {
        Object.keys(response.data.errors).forEach((elName) => {
            const el$ = form$.el$(elName) || (elName === "dialplan_xml" ? form$.el$("xml_editor") : null);

            if (elName === "dialplan_xml") {
                xmlEditorError.value = response.data.errors[elName][0];
            }

            if (el$) {
                el$.messageBag.append(response.data.errors[elName][0]);
            }
        });
    }
};

const handleSuccess = (response) => {
    emit("success", "success", response.data.messages);
    emit("saved", response.data);
    emit("refresh-data");
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
