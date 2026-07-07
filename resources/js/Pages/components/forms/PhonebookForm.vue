<template>
    <TransitionRoot as="div" :show="show">
        <Dialog as="div" class="relative z-10" @close="emit('close')">
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
                            class="relative transform rounded-lg bg-white px-4 pb-4 pt-5 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-2xl sm:p-6">
                            <DialogTitle as="h3" class="mb-1 pr-8 text-base font-semibold leading-6 text-gray-900">
                                {{ header }}
                            </DialogTitle>
                            <p class="mb-4 text-sm text-gray-500">
                                A phonebook is a directory your phones download. Include your internal extensions,
                                add your own contacts, or both.
                            </p>

                            <div class="absolute right-0 top-0 pr-4 pt-4 sm:block">
                                <button type="button"
                                    class="rounded-md bg-white text-gray-400 hover:text-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                                    @click="emit('close')">
                                    <span class="sr-only">Close</span>
                                    <XMarkIcon class="h-6 w-6" aria-hidden="true" />
                                </button>
                            </div>

                            <div v-if="loading" class="w-full py-10">
                                <div class="flex justify-center items-center space-x-3">
                                    <svg class="animate-spin h-10 w-10 text-blue-600" xmlns="http://www.w3.org/2000/svg"
                                        fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
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
                                    <FormElements>
                                        <TextElement name="name" label="Name" placeholder="e.g. Company Directory"
                                            :floating="false" :columns="{ sm: { container: 12 } }" />

                                        <ToggleElement name="enabled" text="Enabled" :labels="{ on: 'On', off: 'Off' }"
                                            label="&nbsp;" :columns="{ sm: { container: 6 } }" />

                                        <ToggleElement name="is_default" text="Account default"
                                            :labels="{ on: 'On', off: 'Off' }"
                                            description="Pushed to devices set to “Use account default”."
                                            label="&nbsp;" :columns="{ sm: { container: 6 } }" />

                                        <!-- ============ Directory contents ============ -->
                                        <StaticElement name="contents_header" tag="h4" content="What's in this directory?"
                                            description="Choose any combination — extensions, contacts, or both." />

                                        <!-- Internal extensions -->
                                        <ToggleElement name="include_extensions" text="Internal extensions"
                                            :labels="{ on: 'On', off: 'Off' }" :description="extensionsDescription"
                                            label="&nbsp;" :columns="{ sm: { container: 12 } }" />

                                        <!-- Contacts (this phonebook's own) -->
                                        <StaticElement name="contacts_manager">
                                            <div class="rounded-md ring-1 ring-gray-200 bg-white p-3">
                                                <div class="flex items-center justify-between">
                                                    <div>
                                                        <h5 class="text-sm font-semibold text-gray-900">
                                                            Contacts <span class="text-gray-400 font-normal">({{ contactList.length }})</span>
                                                        </h5>
                                                        <p class="text-xs text-gray-500">Numbers specific to this phonebook.</p>
                                                    </div>
                                                    <button type="button" @click="startAdd"
                                                        class="shrink-0 rounded-md bg-indigo-600 px-2.5 py-1.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">
                                                        + Add contact
                                                    </button>
                                                </div>

                                                <div v-if="contactForm" class="mt-3 rounded-md ring-1 ring-gray-200 bg-gray-50 p-3 space-y-2">
                                                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                                                        <input v-model="contactForm.first_name" placeholder="First name"
                                                            autocomplete="off" data-lpignore="true" data-1p-ignore @keyup.enter="commitContact"
                                                            class="block w-full rounded-md border-0 py-1.5 text-sm text-gray-900 ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600" />
                                                        <input v-model="contactForm.last_name" placeholder="Last name"
                                                            autocomplete="off" data-lpignore="true" data-1p-ignore @keyup.enter="commitContact"
                                                            class="block w-full rounded-md border-0 py-1.5 text-sm text-gray-900 ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600" />
                                                        <input v-model="contactForm.phone_number" placeholder="Phone number"
                                                            autocomplete="off" data-lpignore="true" data-1p-ignore @keyup.enter="commitContact"
                                                            class="block w-full rounded-md border-0 py-1.5 text-sm text-gray-900 ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600" />
                                                    </div>
                                                    <div class="flex justify-end gap-2">
                                                        <button type="button" @click="cancelContact"
                                                            class="rounded-md bg-white px-2.5 py-1.5 text-sm font-semibold text-gray-900 ring-1 ring-inset ring-gray-300 hover:bg-gray-50">Cancel</button>
                                                        <button type="button" @click="commitContact"
                                                            class="rounded-md bg-indigo-600 px-2.5 py-1.5 text-sm font-semibold text-white hover:bg-indigo-500">
                                                            {{ editIndex === null ? 'Add' : 'Update' }}
                                                        </button>
                                                    </div>
                                                </div>

                                                <ul v-if="contactList.length" class="mt-3 divide-y divide-gray-100 rounded-md ring-1 ring-gray-200 max-h-64 overflow-y-auto">
                                                    <li v-for="(c, i) in contactList" :key="i" class="flex items-center justify-between px-3 py-2">
                                                        <div class="min-w-0">
                                                            <div class="text-sm text-gray-800 truncate">{{ contactName(c) }}</div>
                                                            <div class="text-xs text-gray-400 truncate">{{ c.phone_number }}</div>
                                                        </div>
                                                        <div class="flex items-center gap-1 shrink-0">
                                                            <PencilSquareIcon @click="startEdit(i)"
                                                                class="h-8 w-8 py-1.5 rounded-full text-gray-400 hover:bg-gray-100 hover:text-gray-600 cursor-pointer" title="Edit" />
                                                            <TrashIcon @click="removeContact(i)"
                                                                class="h-8 w-8 py-1.5 rounded-full text-gray-400 hover:bg-gray-100 hover:text-gray-600 cursor-pointer" title="Remove" />
                                                        </div>
                                                    </li>
                                                </ul>
                                                <p v-else class="mt-3 text-sm text-gray-400">No contacts yet.</p>
                                            </div>
                                        </StaticElement>

                                        <TextareaElement name="description" label="Description" :rows="2" :floating="false" />

                                        <ButtonElement name="submit" button-label="Save" :submits="true" align="right" />
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
import { computed, ref, watch } from "vue";
import { Dialog, DialogPanel, DialogTitle, TransitionChild, TransitionRoot } from "@headlessui/vue";
import { XMarkIcon, PencilSquareIcon, TrashIcon } from "@heroicons/vue/24/solid";

const props = defineProps({
    show: Boolean,
    options: Object,
    loading: Boolean,
    header: String,
    mode: { type: String, default: "create" },
});

const emit = defineEmits(["close", "error", "success", "refresh-data"]);
const form$ = ref(null);

const extensionsCount = computed(() => props.options?.extensions_count ?? 0);
const extensionsDescription = computed(() =>
    `Adds all ${extensionsCount.value} enabled extension${extensionsCount.value === 1 ? "" : "s"} in this account (name + extension number).`
);

const defaultValues = computed(() => ({
    name: props.options?.item?.name ?? null,
    enabled: props.options?.item?.enabled ?? true,
    is_default: props.options?.item?.is_default ?? false,
    include_extensions: props.options?.item?.include_extensions ?? true,
    description: props.options?.item?.description ?? null,
}));

// ---- This phonebook's own contacts (local until Save) ----
const contactList = ref([]);
const contactForm = ref(null);
const editIndex = ref(null);

const contactName = (c) => (`${c.first_name ?? ""} ${c.last_name ?? ""}`).trim() || "(no name)";

const startAdd = () => {
    contactForm.value = { first_name: "", last_name: "", phone_number: "" };
    editIndex.value = null;
};

const startEdit = (index) => {
    contactForm.value = { ...contactList.value[index] };
    editIndex.value = index;
};

const cancelContact = () => {
    contactForm.value = null;
    editIndex.value = null;
};

const commitContact = () => {
    const form = contactForm.value;
    if (!form?.phone_number?.trim()) {
        emit("error", { response: { data: { errors: { contact: ["A phone number is required."] } } } });
        return;
    }

    const entry = {
        first_name: (form.first_name ?? "").trim(),
        last_name: (form.last_name ?? "").trim(),
        phone_number: form.phone_number.trim(),
    };

    if (editIndex.value === null) {
        contactList.value.push(entry);
    } else {
        contactList.value.splice(editIndex.value, 1, entry);
    }

    cancelContact();
};

const removeContact = (index) => {
    contactList.value.splice(index, 1);
};

// Initialise the local contact list whenever the loaded options arrive.
// (Watching `show` fires before item-options finish loading, which would open
// the form empty and let a Save wipe the phonebook's contacts.)
watch(() => props.options, () => {
    cancelContact();
    contactList.value = (props.options?.item?.contacts ?? []).map((c) => ({
        first_name: c.first_name ?? "",
        last_name: c.last_name ?? "",
        phone_number: c.phone_number ?? "",
    }));
}, { immediate: true });

const submitForm = async (FormData, form) => {
    const data = { ...form.data, contacts: contactList.value };

    if (props.mode === "create") {
        return await form.$vueform.services.axios.post(props.options.routes.store_route, data);
    }

    return await form.$vueform.services.axios.put(props.options.routes.update_route, data);
};

const handleResponse = (response, form) => {
    if (response.data.errors) {
        Object.keys(response.data.errors).forEach((elName) => {
            if (form.el$(elName)) {
                form.el$(elName).messageBag.append(response.data.errors[elName][0]);
            }
        });
    }
};

const handleSuccess = (response) => {
    emit("success", "success", response.data.messages);
    emit("refresh-data");
    emit("close");
};

const handleError = (error, details, form) => {
    form.messageBag.clear();

    if (details.type === "submit") {
        emit("error", error);
        return;
    }

    form.messageBag.append("Could not submit form");
};
</script>
