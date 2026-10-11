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
                            class="relative transform rounded-lg bg-white px-4 pb-4 pt-5 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-5xl sm:p-6">
                            <DialogTitle as="h3" class="pr-8 text-base font-semibold leading-6 text-gray-900">
                                {{ $t('Captured Calls - :name', { name: session?.name ?? '' }) }}
                            </DialogTitle>
                            <p class="mt-1 mb-4 text-sm text-gray-500">
                                {{ $t('A call appears here about a minute after it ends.') }}
                            </p>

                            <div class="absolute right-0 top-0 pr-4 pt-4 flex items-center gap-2">
                                <button type="button" class="rounded-md bg-white text-gray-400 hover:text-gray-500"
                                    :title="$t('Refresh')" @click="load(page)">
                                    <ArrowPathIcon class="h-5 w-5" :class="{ 'animate-spin': loading }" />
                                </button>
                                <button type="button"
                                    class="rounded-md bg-white text-gray-400 hover:text-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                                    @click="emit('close')">
                                    <span class="sr-only">{{ $t('Close') }}</span>
                                    <XMarkIcon class="h-6 w-6" aria-hidden="true" />
                                </button>
                            </div>

                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-300">
                                    <thead>
                                        <tr>
                                            <th class="px-2 py-3 text-left text-sm font-semibold text-gray-900">{{ $t('Date') }}</th>
                                            <th class="px-2 py-3 text-left text-sm font-semibold text-gray-900">{{ $t('Type') }}</th>
                                            <th class="px-2 py-3 text-left text-sm font-semibold text-gray-900">{{ $t('From') }}</th>
                                            <th class="px-2 py-3 text-left text-sm font-semibold text-gray-900">{{ $t('To') }}</th>
                                            <th class="px-2 py-3 text-left text-sm font-semibold text-gray-900">{{ $t('Duration') }}</th>
                                            <th class="px-2 py-3 text-left text-sm font-semibold text-gray-900">{{ $t('Size') }}</th>
                                            <th class="px-2 py-3" />
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-200">
                                        <tr v-for="row in data.data" :key="row.uuid">
                                            <td class="whitespace-nowrap px-2 py-2 text-sm text-gray-500">{{ row.started_at }}</td>
                                            <td class="whitespace-nowrap px-2 py-2 text-sm text-gray-500">{{ directionLabel(row.direction) }}</td>
                                            <td class="whitespace-nowrap px-2 py-2 text-sm text-gray-500">
                                                <div>{{ row.caller_id_number }}</div>
                                                <div v-if="row.caller_id_name && row.caller_id_name !== row.caller_id_number"
                                                    class="text-xs text-gray-400">{{ row.caller_id_name }}</div>
                                            </td>
                                            <td class="whitespace-nowrap px-2 py-2 text-sm text-gray-500">{{ row.destination_number }}</td>
                                            <td class="whitespace-nowrap px-2 py-2 text-sm text-gray-500">{{ row.duration }}</td>
                                            <td class="whitespace-nowrap px-2 py-2 text-sm text-gray-500">{{ row.file_size }}</td>
                                            <td class="whitespace-nowrap px-2 py-2 text-right">
                                                <a v-if="row.download_url" :href="row.download_url"
                                                    class="inline-flex items-center gap-1 rounded-md bg-white px-2.5 py-1.5 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50">
                                                    <ArrowDownTrayIcon class="h-4 w-4" />
                                                    {{ $t('PCAP') }}
                                                </a>
                                            </td>
                                        </tr>
                                        <tr v-if="!loading && data.data.length === 0">
                                            <td colspan="7" class="py-8 text-center text-sm text-gray-500">
                                                {{ $t('No calls captured yet.') }}
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <div v-if="data.last_page > 1" class="mt-4 flex items-center justify-between text-sm text-gray-600">
                                <span>{{ $t('Showing :from to :to of :total calls', { from: data.from, to: data.to, total: data.total }) }}</span>
                                <div class="flex gap-2">
                                    <button type="button" :disabled="!data.prev_page_url"
                                        class="rounded-md px-3 py-1 ring-1 ring-gray-300 disabled:opacity-40"
                                        @click="load(page - 1)">{{ $t('Previous') }}</button>
                                    <button type="button" :disabled="!data.next_page_url"
                                        class="rounded-md px-3 py-1 ring-1 ring-gray-300 disabled:opacity-40"
                                        @click="load(page + 1)">{{ $t('Next') }}</button>
                                </div>
                            </div>
                        </DialogPanel>
                    </TransitionChild>
                </div>
            </div>
        </Dialog>
    </TransitionRoot>
</template>

<script setup>
import { ref, watch } from "vue";
import axios from "axios";
import { Dialog, DialogPanel, DialogTitle, TransitionChild, TransitionRoot } from "@headlessui/vue";
import { ArrowDownTrayIcon, ArrowPathIcon, XMarkIcon } from "@heroicons/vue/24/solid";
import { trans } from "@i18n";

const props = defineProps({
    show: Boolean,
    session: Object,
    route: String,
});

const emit = defineEmits(["close", "error"]);

const loading = ref(false);
const page = ref(1);
const emptyData = () => ({ data: [], from: 0, to: 0, total: 0, last_page: 1, prev_page_url: null, next_page_url: null });
const data = ref(emptyData());

const directionLabel = (direction) => ({
    inbound: trans("Incoming"),
    outbound: trans("Outgoing"),
    local: trans("Internal"),
}[direction] ?? direction);

const load = (requestedPage = 1) => {
    if (!props.session || !props.route) return;

    loading.value = true;
    axios.get(props.route.replace("__SESSION__", props.session.uuid), { params: { page: requestedPage } })
        .then((response) => {
            data.value = response.data;
            page.value = response.data.current_page ?? requestedPage;
        })
        .catch((error) => emit("error", error))
        .finally(() => {
            loading.value = false;
        });
};

watch(() => [props.show, props.session?.uuid], ([show]) => {
    if (show) {
        data.value = emptyData();
        load(1);
    }
});
</script>
