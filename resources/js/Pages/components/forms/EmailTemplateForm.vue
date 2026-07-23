<template>
    <AddEditItemModal
        :show="show"
        :header="header"
        :loading="loading"
        custom-class="h-[92vh] max-h-[92vh] flex flex-col overflow-hidden sm:max-w-8xl"
        content-class="flex min-h-0 flex-1 flex-col"
        body-class="flex min-h-0 flex-1 flex-col overflow-y-auto"
        @close="emit('close')"
    >
        <template #modal-body>
            <div class="flex min-h-0 flex-1 flex-col">
                <Vueform
                    ref="form$"
                    :endpoint="submitForm"
                    :default="defaultValues"
                    :display-errors="false"
                    class="flex min-h-0 flex-1 flex-col"
                    @success="handleSuccess"
                    @error="handleError"
                    @response="handleResponse"
                >
                    <template #empty>
                        <div class="flex min-h-0 flex-1 flex-col">
                            <div
                                v-if="locked"
                                class="mb-4 rounded-md bg-gray-50 px-4 py-3 text-sm text-gray-700 ring-1 ring-inset ring-gray-200"
                            >
                                <template v-if="options?.item?.template_type === 'default'">
                                    Default template v{{ options?.item?.version }}. FS PBX updates manage this template. Create a custom override to make account-specific changes.
                                </template>
                                <template v-else>
                                    This global custom template is read-only with your current permissions.
                                </template>
                            </div>
                            <div class="max-w-4xl">
                            <FormElements>
                                <!-- Template identity -->
                                <SelectElement
                                    v-if="mode === 'create'"
                                    name="base_template_uuid"
                                    label="Template to override"
                                    :items="defaultTemplateItems"
                                    :native="false"
                                    :search="true"
                                    :strict="false"
                                    :floating="false"
                                    input-type="search"
                                    autocomplete="off"
                                    placeholder="Select the template to override"
                                    :columns="baseColumns"
                                    rules="required"
                                    @change="handleBaseChange"
                                />
                                <SelectElement
                                    v-if="mode !== 'create'"
                                    name="template_category"
                                    label="Category"
                                    :items="categoryOptions"
                                    :native="false"
                                    :search="true"
                                    allow-absent
                                    :strict="false"
                                    :floating="false"
                                    input-type="search"
                                    autocomplete="off"
                                    disabled
                                    :columns="primaryColumns"
                                />
                                <TextElement
                                    v-if="mode !== 'create'"
                                    name="template_subcategory"
                                    label="Subcategory"
                                    disabled
                                    :floating="false"
                                    :columns="primaryColumns"
                                />
                                <SelectElement
                                    name="template_language"
                                    label="Language"
                                    :items="languageOptions"
                                    :native="false"
                                    :search="true"
                                    :create="true"
                                    allow-absent
                                    :strict="false"
                                    :floating="false"
                                    input-type="search"
                                    autocomplete="off"
                                    :disabled="locked"
                                    :columns="languageColumns"
                                    rules="required"
                                />

                                <!-- Options -->
                                <ToggleElement
                                    name="template_enabled"
                                    label="Status"
                                    text="Enabled"
                                    :true-value="true"
                                    :false-value="false"
                                    :disabled="locked"
                                    :labels="{ on: 'On', off: 'Off' }"
                                    :columns="toggleColumns"
                                />
                                <ToggleElement
                                    v-if="canShareAcrossAccounts"
                                    name="share_across_accounts"
                                    label="Visibility"
                                    text="Share across accounts"
                                    :true-value="true"
                                    :false-value="false"
                                    :disabled="locked"
                                    :labels="{ on: 'On', off: 'Off' }"
                                    :columns="shareColumns"
                                />

                                <!-- Subject + description -->
                                <TextElement
                                    name="template_subject"
                                    label="Subject"
                                    placeholder="Email subject"
                                    :disabled="locked"
                                    :floating="false"
                                    rules="required"
                                    :columns="subjectColumns"
                                />
                                <TextElement
                                    name="template_description"
                                    label="Description"
                                    placeholder="Optional internal note"
                                    :disabled="locked"
                                    :floating="false"
                                    :columns="descriptionColumns"
                                />
                            </FormElements>
                            </div>

                            <div class="mt-4 flex min-h-[28rem] flex-1 flex-col overflow-hidden rounded-lg border border-gray-200 bg-white">
                                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 bg-gray-50 px-3 py-2">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <div class="flex items-center gap-1 rounded-md bg-gray-200/70 p-1" role="group" aria-label="Template format">
                                            <button
                                                type="button"
                                                :aria-pressed="activeBody === 'html'"
                                                :class="viewButtonClass(activeBody === 'html')"
                                                @click="activeBody = 'html'"
                                            >
                                                HTML
                                            </button>
                                            <button
                                                type="button"
                                                :aria-pressed="activeBody === 'text'"
                                                :class="viewButtonClass(activeBody === 'text')"
                                                @click="activeBody = 'text'"
                                            >
                                                Plain text
                                            </button>
                                        </div>
                                        <div class="flex items-center gap-1 rounded-md bg-gray-200/70 p-1" role="group" aria-label="Template view">
                                            <button
                                                type="button"
                                                :aria-pressed="activeView === 'editor'"
                                                :class="viewButtonClass(activeView === 'editor')"
                                                @click="activeView = 'editor'"
                                            >
                                                Editor
                                            </button>
                                            <button
                                                type="button"
                                                :aria-pressed="activeView === 'preview'"
                                                :class="viewButtonClass(activeView === 'preview')"
                                                @click="renderPreview"
                                            >
                                                Preview
                                            </button>
                                        </div>
                                    </div>
                                    <p class="text-xs text-gray-500">
                                        HTML and plain text are required. Blade directives are supported; <code class="rounded bg-gray-200 px-1 py-0.5 text-gray-700">@php</code>, raw PHP tags, and scripts are rejected.
                                    </p>
                                </div>

                                <div class="min-h-0 flex-1">
                                    <AceEditor
                                        v-show="activeView === 'editor'"
                                        v-model="activeContent"
                                        lang="php_laravel_blade"
                                        theme="chrome"
                                        :options="{ fontSize: 14, tabSize: 2, wrap: true, readOnly: locked }"
                                        height="100%"
                                    />
                                    <div
                                        v-show="activeView === 'preview'"
                                        class="flex h-full min-h-[24rem] flex-col bg-gray-100 p-3"
                                        aria-live="polite"
                                    >
                                        <div
                                            v-if="previewLoading"
                                            class="flex min-h-[22rem] flex-1 items-center justify-center gap-2 text-sm text-gray-600"
                                        >
                                            <Spinner :show="true" />
                                            Rendering preview
                                        </div>
                                        <div
                                            v-else-if="previewError"
                                            class="flex min-h-[22rem] flex-1 flex-col items-center justify-center px-6 text-center"
                                            role="alert"
                                        >
                                            <p class="max-w-2xl text-sm text-red-700">{{ previewError }}</p>
                                            <button
                                                type="button"
                                                class="mt-4 rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600"
                                                @click="renderPreview"
                                            >
                                                Try again
                                            </button>
                                        </div>
                                        <template v-else>
                                            <div class="flex flex-none items-start justify-between gap-4 rounded-md border border-gray-200 bg-white px-4 py-3">
                                                <p class="min-w-0 text-sm text-gray-800">
                                                    <span class="mr-2 font-semibold text-gray-500">Subject</span>
                                                    {{ previewSubject }}
                                                </p>
                                                <button
                                                    type="button"
                                                    class="flex-none text-sm font-semibold text-indigo-600 hover:text-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600"
                                                    @click="renderPreview"
                                                >
                                                    Refresh
                                                </button>
                                            </div>
                                            <div class="mt-3 min-h-0 flex-1">
                                                <iframe
                                                    v-if="activeBody === 'html'"
                                                    :srcdoc="previewHtml"
                                                    class="h-full min-h-[19rem] w-full rounded-md border border-gray-200 bg-white"
                                                    sandbox
                                                    referrerpolicy="no-referrer"
                                                    title="Rendered HTML email preview"
                                                />
                                                <pre
                                                    v-else
                                                    class="h-full min-h-[19rem] overflow-auto whitespace-pre-wrap rounded-md border border-gray-200 bg-white p-5 text-sm leading-6 text-gray-800"
                                                >{{ previewText }}</pre>
                                            </div>
                                        </template>
                                    </div>
                                </div>
                            </div>

                            <p v-if="bodyError" class="mt-2 text-sm text-red-600">{{ bodyError }}</p>

                            <div class="mt-4 flex flex-none flex-col-reverse gap-3 border-t border-gray-200 pt-4 sm:flex-row sm:justify-end">
                                <button
                                    type="button"
                                    class="inline-flex justify-center rounded-md bg-white px-4 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600"
                                    @click="emit('close')"
                                >
                                    {{ locked ? "Close" : "Cancel" }}
                                </button>
                                <button
                                    v-if="!locked"
                                    type="button"
                                    :disabled="isSubmitting"
                                    class="inline-flex min-w-24 items-center justify-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 disabled:cursor-not-allowed disabled:opacity-60"
                                    @click="form$?.submit()"
                                >
                                    <Spinner :show="isSubmitting" />
                                    Save
                                </button>
                            </div>
                        </div>
                    </template>
                </Vueform>
            </div>
        </template>
    </AddEditItemModal>
</template>

<script setup>
import axios from "axios";
import { computed, ref, watch } from "vue";
import AddEditItemModal from "../modal/AddEditItemModal.vue";
import AceEditor from "../general/AceEditor.vue";
import Spinner from "../general/Spinner.vue";

const props = defineProps({
    show: Boolean,
    options: { type: Object, default: () => ({}) },
    mode: { type: String, default: "create" },
    loading: Boolean,
    header: String,
});

const emit = defineEmits(["close", "error", "success", "refresh-data"]);

const form$ = ref(null);
const htmlValue = ref("");
const textValue = ref("");
const activeBody = ref("html");
const activeView = ref("editor");
const isSubmitting = ref(false);
const bodyError = ref(null);
const savedUpdateRoute = ref(null);
const previewHtml = ref("");
const previewText = ref("");
const previewSubject = ref("");
const previewLoading = ref(false);
const previewError = ref(null);
const selectedBase = ref(null);
let previewRequestId = 0;

const categoryOptions = computed(() => props.options?.categories ?? []);
const languageOptions = computed(() => props.options?.languages ?? []);
const domainOptions = computed(() => props.options?.domains ?? []);
// Global/shared scope is only offered when the account list exposes the
// "__global__" option, i.e. the user can manage global templates.
const canShareAcrossAccounts = computed(() =>
    domainOptions.value.some((option) => option.value === "__global__"),
);
const currentDomainOption = computed(() =>
    domainOptions.value.find((option) => option.value !== "__global__" && option.value !== "__default__"),
);
const defaultTemplateItems = computed(() =>
    (props.options?.defaults ?? []).map((item) => ({ value: item.value, label: item.label })),
);
const defaultTemplateMap = computed(() => {
    const map = {};
    (props.options?.defaults ?? []).forEach((item) => {
        map[item.value] = item;
    });
    return map;
});

// The metadata fields sit in a capped-width block (max-w-4xl) so they cluster
// tightly on the left instead of stretching across the very wide editor modal.
// Each field is sized to its content and packs left, keeping the toggles adjacent.
const baseColumns = { sm: { container: 6 }, lg: { container: 4 } };
const primaryColumns = { sm: { container: 6 }, lg: { container: 3 } };
const languageColumns = { sm: { container: 4 }, lg: { container: 2 } };
const toggleColumns = { sm: { container: 4 }, lg: { container: 2 } };
const shareColumns = { sm: { container: 6 }, lg: { container: 3 } };
const subjectColumns = { sm: { container: 12 }, lg: { container: 5 } };
const descriptionColumns = { sm: { container: 12 }, lg: { container: 4 } };
const locked = computed(() => Boolean(props.options?.locked));
const activeContent = computed({
    get: () => activeBody.value === "html" ? htmlValue.value : textValue.value,
    set: (value) => {
        if (activeBody.value === "html") {
            htmlValue.value = value;
        } else {
            textValue.value = value;
        }
    },
});

const defaultValues = computed(() => {
    const item = props.options?.item ?? {};

    return {
        template_language: item.template_language ?? "en-us",
        base_template_uuid: item.base_template_uuid ?? null,
        template_category: item.template_category ?? null,
        template_subcategory: item.template_subcategory ?? null,
        share_across_accounts: item.email_template_uuid ? item.domain_uuid === null : false,
        template_enabled: item.template_enabled ?? true,
        template_subject: item.template_subject ?? null,
        template_description: item.template_description ?? null,
    };
});

watch(
    () => props.options?.item,
    (item) => {
        htmlValue.value = item?.template_html ?? "";
        textValue.value = item?.template_text ?? "";
        activeBody.value = "html";
        activeView.value = "editor";
        bodyError.value = null;
        savedUpdateRoute.value = null;
        previewHtml.value = "";
        previewText.value = "";
        previewSubject.value = "";
        previewLoading.value = false;
        previewError.value = null;
        selectedBase.value = null;
        previewRequestId++;
    },
    { immediate: true },
);

const viewButtonClass = (active) => [
    "rounded px-3 py-1 text-sm font-medium transition-colors focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600",
    active
        ? "bg-white text-gray-900 shadow-sm"
        : "text-gray-600 hover:text-gray-900",
];

const handleBaseChange = (value) => {
    const base = defaultTemplateMap.value[value];
    if (!base) {
        selectedBase.value = null;
        return;
    }

    selectedBase.value = base;
    form$.value?.el$("template_language")?.update(base.language ?? "en-us");
    form$.value?.el$("template_subject")?.update(base.subject ?? "");
    htmlValue.value = base.html ?? "";
    textValue.value = base.text ?? "";
    activeBody.value = "html";
    activeView.value = "editor";
};

const renderPreview = () => {
    activeView.value = "preview";
    previewLoading.value = true;
    previewError.value = null;

    const requestId = ++previewRequestId;
    const item = props.options?.item ?? {};
    const requestData = form$.value?.requestData ?? {};
    const route = props.options?.routes?.preview_route;

    if (!route) {
        previewLoading.value = false;
        previewError.value = "The preview route is unavailable.";
        return;
    }

    const base = selectedBase.value;

    axios.post(route, {
        email_template_uuid: item.email_template_uuid ?? null,
        template_category: requestData.template_category ?? base?.category ?? item.template_category,
        template_subcategory: requestData.template_subcategory ?? base?.subcategory ?? item.template_subcategory,
        template_layout: base?.layout ?? item.template_layout ?? "standard",
        template_subject: requestData.template_subject ?? item.template_subject,
        template_html: htmlValue.value,
        template_text: textValue.value,
    }).then((response) => {
        if (requestId !== previewRequestId) {
            return;
        }

        previewSubject.value = response.data.subject ?? "";
        previewHtml.value = response.data.html ?? "";
        previewText.value = response.data.text ?? "";
    }).catch((error) => {
        if (requestId !== previewRequestId) {
            return;
        }

        const errors = error?.response?.data?.errors ?? {};
        previewError.value = errors.preview?.[0]
            ?? Object.values(errors)?.[0]?.[0]
            ?? "The template preview could not be rendered.";
    }).finally(() => {
        if (requestId === previewRequestId) {
            previewLoading.value = false;
        }
    });
};

const submitForm = async (FormData, form) => {
    isSubmitting.value = true;
    bodyError.value = null;

    const data = {
        ...form.requestData,
        template_html: htmlValue.value,
        template_text: textValue.value || null,
    };

    // Translate the scope toggle into the account the backend expects:
    // "__global__" for a shared override, otherwise the current account.
    data.domain_uuid = (canShareAcrossAccounts.value && data.share_across_accounts)
        ? "__global__"
        : (currentDomainOption.value?.value ?? null);
    delete data.share_across_accounts;

    const route = savedUpdateRoute.value
        ?? (props.mode === "update" ? props.options?.routes?.update_route : props.options?.routes?.store_route);

    if (props.mode === "update" || savedUpdateRoute.value) {
        return await form.$vueform.services.axios.put(route, data);
    }

    return await form.$vueform.services.axios.post(route, data);
};

const clearErrorsRecursive = (element) => {
    element.messageBag?.clear();
    if (element.children$) {
        Object.values(element.children$).forEach(clearErrorsRecursive);
    }
};

const handleResponse = (response, form) => {
    isSubmitting.value = false;
    Object.values(form.elements$).forEach(clearErrorsRecursive);

    const errors = response?.data?.errors ?? {};
    bodyError.value = errors.template_html?.[0] ?? errors.template_text?.[0] ?? null;

    Object.entries(errors).forEach(([name, messages]) => {
        if (!["template_html", "template_text"].includes(name) && form.el$(name)) {
            form.el$(name).messageBag.append(messages[0]);
        }
    });
};

const handleSuccess = (response) => {
    isSubmitting.value = false;
    savedUpdateRoute.value = response?.data?.routes?.update_route ?? savedUpdateRoute.value;
    emit("success", "success", response?.data?.messages);
    emit("refresh-data");
};

const handleError = (error, details, form) => {
    isSubmitting.value = false;

    if (details?.type === "submit") {
        emit("error", error);
        return;
    }

    form.messageBag.append("Could not save the email template.");
};
</script>
