<template>
    <AddEditItemModal
        :show="show"
        :loading="loading"
        header="Send welcome email"
        custom-class="sm:max-w-2xl"
        @close="emit('close')"
    >
        <template #modal-body>
            <div v-if="options" class="space-y-5">
                <div v-if="singleMode && firstItem">
                    <label for="welcome_email_recipient" class="block text-sm font-medium text-gray-700">
                        Send to
                    </label>
                    <input
                        id="welcome_email_recipient"
                        v-model.trim="recipient"
                        type="email"
                        autocomplete="email"
                        class="mt-1 block w-full rounded-md border-0 py-2 text-sm text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600"
                        placeholder="name@example.com"
                    />
                    <p v-if="recipientError" class="mt-2 text-sm text-red-600">{{ recipientError }}</p>
                </div>

                <div
                    v-if="singleMode && firstItem"
                    class="overflow-hidden rounded-md border border-gray-200"
                >
                    <dl class="divide-y divide-gray-100">
                        <div class="grid grid-cols-3 gap-4 px-4 py-3 text-sm">
                            <dt class="font-medium text-gray-600">Extension</dt>
                            <dd class="col-span-2 text-gray-900">
                                {{ firstItem.name }} ({{ firstItem.extension }})
                            </dd>
                        </div>
                        <div class="grid grid-cols-3 gap-4 px-4 py-3 text-sm">
                            <dt class="font-medium text-gray-600">Voicemail</dt>
                            <dd class="col-span-2 text-gray-900">{{ firstItem.voicemail_id || "Not configured" }}</dd>
                        </div>
                        <div class="grid grid-cols-3 gap-4 px-4 py-3 text-sm">
                            <dt class="font-medium text-gray-600">Voicemail PIN</dt>
                            <dd class="col-span-2 font-mono text-gray-900">{{ firstItem.voicemail_pin || "Not configured" }}</dd>
                        </div>
                        <div class="grid grid-cols-3 gap-4 px-4 py-3 text-sm">
                            <dt class="font-medium text-gray-600">Direct numbers</dt>
                            <dd class="col-span-2 text-gray-900">
                                {{ firstItem.direct_numbers?.length ? firstItem.direct_numbers.join(", ") : "None" }}
                            </dd>
                        </div>
                    </dl>
                </div>

                <div v-else-if="!singleMode">
                    <div class="flex flex-wrap gap-x-6 gap-y-2 text-sm">
                        <p><span class="font-semibold text-gray-900">{{ options.summary.selected }}</span> selected</p>
                        <p><span class="font-semibold text-green-700">{{ options.summary.eligible }}</span> ready</p>
                        <p v-if="options.summary.skipped">
                            <span class="font-semibold text-amber-700">{{ options.summary.skipped }}</span> skipped
                        </p>
                    </div>

                    <div class="mt-3 max-h-64 overflow-y-auto rounded-md border border-gray-200">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="sticky top-0 bg-gray-50">
                                <tr>
                                    <th class="px-4 py-2 text-left font-semibold text-gray-700">Extension</th>
                                    <th class="px-4 py-2 text-left font-semibold text-gray-700">Recipient</th>
                                    <th class="px-4 py-2 text-left font-semibold text-gray-700">PIN</th>
                                    <th class="px-4 py-2 text-left font-semibold text-gray-700">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 bg-white">
                                <tr v-for="item in options.items" :key="item.extension_uuid">
                                    <td class="whitespace-nowrap px-4 py-2 text-gray-900">
                                        {{ item.extension || "Unavailable" }}
                                    </td>
                                    <td class="px-4 py-2 text-gray-600">{{ item.recipient || "No email" }}</td>
                                    <td class="px-4 py-2 font-mono text-gray-700">{{ item.voicemail_pin || "None" }}</td>
                                    <td class="px-4 py-2">
                                        <span v-if="item.eligible" class="font-medium text-green-700">Ready</span>
                                        <span v-else class="text-amber-700">{{ item.reason }}</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div
                    v-if="blockingReason"
                    class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"
                    role="alert"
                >
                    {{ blockingReason }}
                </div>

                <div
                    v-if="error"
                    class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"
                    role="alert"
                >
                    {{ error }}
                </div>

                <div class="flex flex-col-reverse gap-3 border-t border-gray-200 pt-4 sm:flex-row sm:justify-end">
                    <button
                        type="button"
                        class="inline-flex justify-center rounded-md bg-white px-4 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600"
                        @click="emit('close')"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        :disabled="!canSend || submitting"
                        class="inline-flex justify-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 disabled:cursor-not-allowed disabled:bg-gray-300"
                        @click="submit"
                    >
                        {{ sendLabel }}
                        <Spinner class="ml-2" :show="submitting" />
                    </button>
                </div>
            </div>
        </template>
    </AddEditItemModal>
</template>

<script setup>
import { computed, ref, watch } from "vue";
import AddEditItemModal from "./AddEditItemModal.vue";
import Spinner from "@generalComponents/Spinner.vue";

const props = defineProps({
    show: Boolean,
    loading: Boolean,
    submitting: Boolean,
    options: Object,
    error: String,
});

const emit = defineEmits(["close", "send"]);
const recipient = ref("");

const singleMode = computed(() => Number(props.options?.summary?.selected ?? 0) === 1);
const firstItem = computed(() => props.options?.items?.[0] ?? null);

watch(
    () => [props.show, firstItem.value?.recipient],
    ([show]) => {
        if (show) {
            recipient.value = firstItem.value?.recipient ?? "";
        }
    },
    { immediate: true }
);

const recipientValid = computed(() => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(recipient.value));
const recipientError = computed(() => {
    if (!singleMode.value || recipientValid.value) {
        return null;
    }

    return recipient.value === "" ? "Email address is required." : "Enter a valid email address.";
});

const blockingReason = computed(() => {
    if (!singleMode.value) {
        return Number(props.options?.summary?.eligible ?? 0) > 0
            ? null
            : "None of the selected extensions can receive a welcome email.";
    }

    const reason = firstItem.value?.reason;

    return reason && reason !== "A valid voicemail email is required." ? reason : null;
});

const canSend = computed(() => {
    if (singleMode.value) {
        return recipientValid.value && !blockingReason.value;
    }

    return Number(props.options?.summary?.eligible ?? 0) > 0;
});

const sendLabel = computed(() => {
    if (singleMode.value) {
        return "Send email";
    }

    const count = Number(props.options?.summary?.eligible ?? 0);
    return `Queue ${count} email${count === 1 ? "" : "s"}`;
});

const submit = () => {
    if (!canSend.value) {
        return;
    }

    emit("send", singleMode.value ? recipient.value : null);
};
</script>
