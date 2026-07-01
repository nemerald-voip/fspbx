<template>
    <TransitionRoot as="div" :show="show">
        <Dialog as="div" class="relative z-10" @close="emit('close')">
            <TransitionChild as="div" enter="ease-out duration-300" enter-from="opacity-0" enter-to="opacity-100"
                leave="ease-in duration-200" leave-from="opacity-100" leave-to="opacity-0">
                <div class="fixed inset-0 bg-gray-500 dark:bg-gray-900 bg-opacity-75 transition-opacity" />
            </TransitionChild>

            <div class="fixed inset-0 z-10 w-screen overflow-y-auto">
                <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
                    <TransitionChild as="template" enter="ease-out duration-300"
                        enter-from="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                        enter-to="opacity-100 translate-y-0 sm:scale-100" leave="ease-in duration-200"
                        leave-from="opacity-100 translate-y-0 sm:scale-100"
                        leave-to="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95">
                        <DialogPanel
                            class="relative w-full max-w-3xl transform overflow-hidden rounded-lg bg-surface px-4 pb-4 pt-5 text-left shadow-xl transition-all sm:my-8 sm:p-6">
                            <DialogTitle as="h3" class="mb-1 pr-10 text-base font-semibold leading-6 text-heading">
                                {{ header }}
                            </DialogTitle>
                            <p class="mb-5 pr-10 text-sm text-muted">
                                A reusable list of phone numbers that campaigns dial. Upload a CSV or paste lines below.
                            </p>

                            <div class="absolute right-0 top-0 pr-4 pt-4 sm:block">
                                <button type="button"
                                    class="rounded-md bg-surface text-subtle hover:text-muted focus:outline-none focus:ring-2 focus:ring-focus focus:ring-offset-2"
                                    @click="emit('close')">
                                    <span class="sr-only">Close</span>
                                    <XMarkIcon class="h-6 w-6" aria-hidden="true" />
                                </button>
                            </div>

                            <div v-if="loading" class="w-full py-10">
                                <div class="flex items-center justify-center space-x-3">
                                    <svg class="h-10 w-10 animate-spin text-info"
                                        xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                            stroke-width="4" />
                                        <path class="opacity-75" fill="currentColor"
                                            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                                    </svg>
                                    <div class="text-lg text-info">Loading...</div>
                                </div>
                            </div>

                            <Vueform v-if="!loading" ref="form$" :endpoint="submitForm" @success="handleSuccess"
                                @error="handleError" @response="handleResponse" :display-errors="false"
                                :default="defaultValues">
                                <template #empty>
                                    <FormElements>
                                        <HiddenElement name="basic_dialer_contact_list_uuid" :meta="true" />

                                        <StaticElement name="details_header" tag="h4" content="Details" />

                                        <TextElement name="name" label="Name" :floating="false"
                                            :rules="['required']" :columns="{ sm: { container: 9 } }" />

                                        <ToggleElement name="enabled" text="Enabled" :labels="{ on: 'On', off: 'Off' }"
                                            :columns="{ sm: { container: 3 } }" label="&nbsp;" />

                                        <TextareaElement name="description" label="Description" :rows="2"
                                            :floating="false" />

                                        <StaticElement name="contacts_header">
                                            <div class="flex flex-wrap items-start justify-between gap-2 border-t border-default pt-5">
                                                <div>
                                                    <h4 class="text-sm font-semibold text-heading">Add Contacts</h4>
                                                    <p class="mt-0.5 text-xs text-muted">
                                                        Upload a CSV or paste rows. Columns: phone number, name (optional), company (optional).
                                                    </p>
                                                </div>
                                                <button type="button" @click.prevent="downloadSampleCsv"
                                                    class="inline-flex shrink-0 items-center gap-x-1.5 text-sm font-medium text-accent-fg hover:text-accent-fg">
                                                    <DocumentArrowDownIcon class="h-4 w-4" aria-hidden="true" />
                                                    Download sample CSV
                                                </button>
                                            </div>
                                        </StaticElement>

                                        <FileElement name="csv_file" label=""
                                            accept=".csv,text/csv,text/plain"
                                            :upload-temp-endpoint="false" :remove-temp-endpoint="false"
                                            :remove-endpoint="false" :drop="true"
                                            @change="handleVueformFile" />

                                        <TextareaElement name="contacts" label="Or paste rows" :rows="6"
                                            :floating="false"
                                            placeholder="15551234567, Jane Smith, Acme&#10;15557654321, Bob Jones, Acme" />

                                        <StaticElement name="existing_contacts_section"
                                            :conditions="[() => isEditMode]">
                                            <div class="mt-2 space-y-3">
                                                <div class="flex flex-wrap items-center justify-between gap-2">
                                                    <h4 class="text-sm font-semibold text-body">
                                                        Existing contacts
                                                        <span class="font-normal text-subtle">({{ contactsData.total ?? 0 }})</span>
                                                    </h4>
                                                    <div class="relative w-full sm:w-64">
                                                        <MagnifyingGlassIcon
                                                            class="pointer-events-none absolute left-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-subtle" />
                                                        <input v-model="contactsSearch" type="text" placeholder="Search..."
                                                            class="block w-full rounded-md border-0 py-1.5 pl-8 pr-2 text-sm text-heading ring-1 ring-inset ring-strong focus:ring-2 focus:ring-inset focus:ring-focus"
                                                            @keydown.enter.prevent="fetchContacts(1)"
                                                            @input="onSearchInput" />
                                                    </div>
                                                </div>

                                                <div v-if="contactsLoading"
                                                    class="rounded-md border border-default py-6 text-center text-xs text-muted">
                                                    Loading contacts...
                                                </div>

                                                <div v-else-if="(contactsData.data ?? []).length > 0"
                                                    class="overflow-hidden rounded-md border border-default">
                                                    <table class="min-w-full divide-y divide-default text-sm">
                                                        <thead class="bg-surface-2 text-left text-xs font-semibold uppercase tracking-wide text-muted">
                                                            <tr>
                                                                <th class="px-3 py-2">Phone</th>
                                                                <th class="px-3 py-2">Name</th>
                                                                <th class="px-3 py-2">Company</th>
                                                                <th class="w-10 px-3 py-2"></th>
                                                            </tr>
                                                        </thead>
                                                        <tbody class="divide-y divide-default bg-surface">
                                                            <tr v-for="contact in contactsData.data"
                                                                :key="contact.basic_dialer_contact_uuid"
                                                                class="hover:bg-surface-2">
                                                                <td class="whitespace-nowrap px-3 py-2 text-heading">
                                                                    {{ contact.phone_number }}
                                                                </td>
                                                                <td class="whitespace-nowrap px-3 py-2 text-muted">
                                                                    {{ contact.contact_name || "-" }}
                                                                </td>
                                                                <td class="whitespace-nowrap px-3 py-2 text-muted">
                                                                    {{ contact.company || "-" }}
                                                                </td>
                                                                <td class="px-3 py-2 text-right">
                                                                    <button type="button"
                                                                        @click.prevent="deleteContactRow(contact)"
                                                                        class="rounded-full p-1.5 text-subtle hover:bg-danger-subtle hover:text-danger focus:outline-none focus:ring-2 focus:ring-focus"
                                                                        title="Delete contact">
                                                                        <TrashIcon class="h-4 w-4" />
                                                                    </button>
                                                                </td>
                                                            </tr>
                                                        </tbody>
                                                    </table>
                                                </div>

                                                <div v-else
                                                    class="rounded-md border border-default py-6 text-center text-xs text-muted">
                                                    {{ contactsSearch ? "No contacts match your search." : "No contacts yet." }}
                                                </div>

                                                <div v-if="(contactsData.total ?? 0) > 0"
                                                    class="flex items-center justify-between text-xs text-muted">
                                                    <span>
                                                        Showing {{ contactsData.from ?? 0 }}–{{ contactsData.to ?? 0 }} of {{ contactsData.total }}
                                                        <span v-if="(contactsData.last_page ?? 1) > 1" class="text-subtle">&middot;</span>
                                                        <span v-if="(contactsData.last_page ?? 1) > 1">
                                                            page {{ contactsData.current_page }} of {{ contactsData.last_page }}
                                                        </span>
                                                    </span>
                                                    <div class="flex gap-1">
                                                        <button type="button"
                                                            :disabled="(contactsData.current_page ?? 1) <= 1"
                                                            class="rounded-md bg-surface px-2.5 py-1 font-medium text-body ring-1 ring-inset ring-strong hover:bg-surface-2 disabled:cursor-not-allowed disabled:opacity-50"
                                                            @click.prevent="fetchContacts((contactsData.current_page ?? 1) - 1)">
                                                            Previous
                                                        </button>
                                                        <button type="button"
                                                            :disabled="(contactsData.current_page ?? 1) >= (contactsData.last_page ?? 1)"
                                                            class="rounded-md bg-surface px-2.5 py-1 font-medium text-body ring-1 ring-inset ring-strong hover:bg-surface-2 disabled:cursor-not-allowed disabled:opacity-50"
                                                            @click.prevent="fetchContacts((contactsData.current_page ?? 1) + 1)">
                                                            Next
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </StaticElement>

                                        <ButtonElement name="submit" button-label="Save Contact List" :submits="true"
                                            align="right" />
                                    </FormElements>
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
import { computed, onMounted, ref, watch } from "vue";
import axios from "axios";
import { Dialog, DialogPanel, DialogTitle, TransitionChild, TransitionRoot } from "@headlessui/vue";
import { MagnifyingGlassIcon, TrashIcon, XMarkIcon } from "@heroicons/vue/24/solid";
import { DocumentArrowDownIcon } from "@heroicons/vue/24/outline";

const props = defineProps({
    show: Boolean,
    options: Object,
    loading: Boolean,
    header: {
        type: String,
        default: "Contact List",
    },
    mode: {
        type: String,
        default: "create",
    },
});

const emit = defineEmits(["close", "error", "success", "refresh-data"]);

const form$ = ref(null);

const isEditMode = computed(() => props.mode === "update");
const contactsRoute = computed(() => props.options?.routes?.contacts_route ?? null);
const contactDeleteRouteTemplate = computed(() => props.options?.routes?.contact_delete_route ?? null);

const contactsData = ref({
    data: [],
    total: 0,
    current_page: 1,
    last_page: 1,
    from: 0,
    to: 0,
});
const contactsLoading = ref(false);
const contactsSearch = ref("");
let searchDebounce = null;

const SAMPLE_CSV = `phone_number,contact_name,company
15551234567,Jane Smith,Acme
15557654321,Bob Jones,Acme
`;

function handleVueformFile(file) {
    if (!file) return;
    const rawFile = file instanceof File ? file : (file?.file || file?.original);
    if (!(rawFile instanceof File)) return;

    const reader = new FileReader();
    reader.onload = (e) => {
        const raw = String(e.target?.result ?? "");
        const lines = raw.split(/\r\n|\r|\n/).map((l) => l.trim()).filter(Boolean);
        if (lines.length === 0) return;

        if (looksLikeHeaderRow(lines[0])) lines.shift();

        const contactsText = lines.join("\n");
        const el = form$.value?.el$("contacts");
        if (el) {
            const existing = String(el.value ?? "").trim();
            el.update(existing ? `${existing}\n${contactsText}` : contactsText);
        }
    };
    reader.readAsText(rawFile);
}

function looksLikeHeaderRow(line) {
    const lower = line.toLowerCase();
    return /^[a-z_ ]+[,;\t]/i.test(line) && (
        lower.includes("phone") || lower.includes("number") || lower.includes("name")
    );
}

function downloadSampleCsv() {
    const blob = new Blob([SAMPLE_CSV], { type: "text/csv" });
    const url = window.URL.createObjectURL(blob);
    const link = document.createElement("a");
    link.href = url;
    link.setAttribute("download", "contact-list-sample.csv");
    document.body.appendChild(link);
    link.click();
    link.remove();
    window.URL.revokeObjectURL(url);
}

function fetchContacts(page = 1) {
    if (!contactsRoute.value) return;
    contactsLoading.value = true;
    axios.get(contactsRoute.value, {
        params: {
            page,
            filter: { search: contactsSearch.value },
            per_page: 20,
        },
    })
        .then((response) => {
            contactsData.value = response.data;
        })
        .catch((error) => emit("error", error))
        .finally(() => {
            contactsLoading.value = false;
        });
}

function onSearchInput() {
    if (searchDebounce) clearTimeout(searchDebounce);
    searchDebounce = setTimeout(() => fetchContacts(1), 300);
}

function deleteContactRow(contact) {
    if (!contactDeleteRouteTemplate.value) return;
    if (!confirm(`Delete ${contact.phone_number}?`)) return;

    const url = contactDeleteRouteTemplate.value.replace(":contact", contact.basic_dialer_contact_uuid);
    axios.delete(url)
        .then((response) => {
            emit("success", "success", response.data.messages);
            const page = contactsData.value.data.length === 1 && contactsData.value.current_page > 1
                ? contactsData.value.current_page - 1
                : contactsData.value.current_page;
            fetchContacts(page);
        })
        .catch((error) => emit("error", error));
}

onMounted(() => {
    if (isEditMode.value && contactsRoute.value) {
        fetchContacts(1);
    }
});

watch(contactsRoute, (route) => {
    if (route && isEditMode.value) {
        fetchContacts(1);
    }
});

const defaultValues = computed(() => ({
    basic_dialer_contact_list_uuid: props.options?.item?.basic_dialer_contact_list_uuid ?? null,
    name: props.options?.item?.name ?? null,
    enabled: props.options?.item?.enabled ?? true,
    description: props.options?.item?.description ?? null,
    contacts: null,
}));

const submitForm = async (FormData, form$) => {
    const requestData = form$.requestData;
    const route = props.mode === "create"
        ? props.options.routes.store_route
        : props.options.routes.update_route;

    return props.mode === "create"
        ? await form$.$vueform.services.axios.post(route, requestData)
        : await form$.$vueform.services.axios.put(route, requestData);
};

function clearErrorsRecursive(el$) {
    el$.messageBag?.clear();
    if (el$.children$) {
        Object.values(el$.children$).forEach((childEl$) => clearErrorsRecursive(childEl$));
    }
}

const handleResponse = (response, form$) => {
    Object.values(form$.elements$).forEach((el$) => clearErrorsRecursive(el$));

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
