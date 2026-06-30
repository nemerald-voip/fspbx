<template>
    <TransitionRoot as="template" :show="show">
        <Dialog as="div" class="relative z-10" @close="emit('close')">
            <TransitionChild
                as="template"
                enter="ease-in-out duration-300"
                enter-from="opacity-0"
                enter-to="opacity-100"
                leave="ease-in-out duration-300"
                leave-from="opacity-100"
                leave-to="opacity-0"
            >
                <div class="fixed inset-0 bg-gray-500 dark:bg-gray-900 bg-opacity-75 transition-opacity" />
            </TransitionChild>

            <div class="fixed inset-0 overflow-hidden">
                <div class="absolute inset-0 overflow-hidden">
                    <div class="pointer-events-none fixed inset-y-0 right-0 flex max-w-full pl-10">
                        <TransitionChild
                            as="template"
                            enter="transform transition ease-in-out duration-300 sm:duration-500"
                            enter-from="translate-x-full"
                            enter-to="translate-x-0"
                            leave="transform transition ease-in-out duration-300 sm:duration-500"
                            leave-from="translate-x-0"
                            leave-to="translate-x-full"
                        >
                            <DialogPanel class="pointer-events-auto w-screen max-w-2xl">
                                <div class="flex h-full flex-col bg-surface shadow-xl">
                                    <div class="border-b border-default px-6 py-4">
                                        <div class="flex items-start justify-between gap-3">
                                            <div class="min-w-0 flex-1">
                                                <DialogTitle class="truncate text-lg font-semibold text-heading" :title="stream?.music_on_hold_name">
                                                    {{ stream?.music_on_hold_name || "Music on Hold" }}
                                                </DialogTitle>
                                                <p v-if="stream?.domain_label" class="mt-0.5 text-xs text-muted">
                                                    {{ stream.domain_label }}
                                                </p>
                                                <p v-if="permissions.view_path && stream?.music_on_hold_path" class="mt-1 truncate text-xs text-subtle" :title="stream.music_on_hold_path">
                                                    {{ stream.music_on_hold_path }}
                                                </p>
                                            </div>
                                            <div class="flex shrink-0 items-center gap-1">
                                                <button
                                                    v-if="permissions.update && stream?.can_modify"
                                                    type="button"
                                                    class="rounded-full p-2 text-subtle transition hover:bg-surface-3 hover:text-body"
                                                    title="Edit stream"
                                                    @click="emit('edit')"
                                                >
                                                    <PencilSquareIcon class="h-5 w-5" />
                                                </button>
                                                <button
                                                    v-if="permissions.destroy && stream?.can_modify"
                                                    type="button"
                                                    class="rounded-full p-2 text-subtle transition hover:bg-surface-3 hover:text-body"
                                                    title="Delete stream"
                                                    @click="emit('delete')"
                                                >
                                                    <TrashIcon class="h-5 w-5" />
                                                </button>
                                                <button
                                                    type="button"
                                                    class="rounded-full p-2 text-subtle transition hover:bg-surface-3 hover:text-body"
                                                    title="Close"
                                                    @click="emit('close')"
                                                >
                                                    <XMarkIcon class="h-5 w-5" />
                                                </button>
                                            </div>
                                        </div>

                                        <div class="mt-3 flex flex-wrap gap-1">
                                            <Badge v-if="stream?.rate_label" :text="stream.rate_label" v-bind="grayBadge" />
                                            <Badge :text="stream?.music_on_hold_channels === '2' ? 'Stereo' : 'Mono'" v-bind="grayBadge" />
                                            <Badge
                                                :text="stream?.music_on_hold_shuffle === 'true' ? 'Shuffle' : 'Ordered'"
                                                v-bind="stream?.music_on_hold_shuffle === 'true' ? blueBadge : grayBadge"
                                            />
                                            <Badge v-if="stream?.music_on_hold_chime_list" text="Chime" v-bind="amberBadge" />
                                        </div>
                                    </div>

                                    <div v-if="activeFile" class="border-b border-default bg-surface-2 px-6 py-4">
                                        <p class="mb-2 truncate text-sm font-medium text-body" :title="activeFile.name">
                                            <MusicalNoteIcon class="mr-1 inline-block h-4 w-4 align-text-bottom text-accent-fg" />
                                            {{ activeFile.name }}
                                        </p>
                                        <AudioPlayer
                                            :key="activeFile.download_url"
                                            :url="activeFile.download_url"
                                            :download-url="activeFile.download_url"
                                            :file-name="activeFile.name"
                                        />
                                    </div>

                                    <div class="flex-1 overflow-y-auto px-6 py-4">
                                        <div class="mb-2 flex items-center justify-between">
                                            <h4 class="text-xs font-semibold uppercase tracking-wide text-muted">
                                                Files<span v-if="stream?.files?.length"> ({{ stream.files.length }})</span>
                                            </h4>
                                        </div>

                                        <ul v-if="stream?.files?.length" class="-mx-2 divide-y divide-default">
                                            <li
                                                v-for="file in stream.files"
                                                :key="file.name"
                                                :class="[
                                                    'group flex items-center gap-2 rounded px-2 py-2 transition',
                                                    activeFile?.name === file.name ? 'bg-accent-subtle' : 'hover:bg-surface-2',
                                                ]"
                                            >
                                                <button
                                                    type="button"
                                                    :class="[
                                                        'rounded-full p-1.5 transition',
                                                        activeFile?.name === file.name
                                                            ? 'bg-accent text-on-accent hover:bg-accent-hover'
                                                            : 'text-accent-fg hover:bg-accent-subtle hover:text-accent-fg',
                                                    ]"
                                                    title="Select file"
                                                    @click="setActiveFile(file)"
                                                >
                                                    <MusicalNoteIcon class="h-4 w-4" />
                                                </button>
                                                <button
                                                    type="button"
                                                    class="min-w-0 flex-1 text-left"
                                                    @click="setActiveFile(file)"
                                                >
                                                    <p class="truncate text-sm text-heading" :title="file.name">{{ file.name }}</p>
                                                    <p class="text-xs text-muted">
                                                        {{ file.size_label }}<span v-if="file.modified_at"> &middot; {{ formatDate(file.modified_at) }}</span>
                                                    </p>
                                                </button>
                                                <button
                                                    type="button"
                                                    class="rounded-full p-1.5 text-subtle transition hover:bg-surface-3 hover:text-body"
                                                    title="Download"
                                                    @click="emit('download', file.download_url)"
                                                >
                                                    <ArrowDownTrayIcon class="h-4 w-4" />
                                                </button>
                                                <button
                                                    v-if="permissions.destroy && stream?.can_modify"
                                                    type="button"
                                                    class="rounded-full p-1.5 text-subtle transition hover:bg-surface-3 hover:text-body"
                                                    title="Delete"
                                                    @click="emit('delete-file', file)"
                                                >
                                                    <TrashIcon class="h-4 w-4" />
                                                </button>
                                            </li>
                                        </ul>

                                        <div v-else class="mt-6 text-center">
                                            <MusicalNoteIcon class="mx-auto h-10 w-10 text-subtle" />
                                            <p class="mt-2 text-sm text-muted">No audio files in this stream yet.</p>
                                        </div>
                                    </div>

                                    <div v-if="permissions.create && stream?.can_modify" class="border-t border-default bg-surface px-6 py-4">
                                        <button
                                            type="button"
                                            class="flex w-full items-center justify-center gap-2 rounded-md bg-surface px-3 py-2 text-sm font-semibold text-heading shadow-sm ring-1 ring-inset ring-strong hover:bg-surface-2"
                                            @click="emit('upload')"
                                        >
                                            <ArrowUpTrayIcon class="h-4 w-4" />
                                            Upload to this stream
                                        </button>
                                    </div>
                                </div>
                            </DialogPanel>
                        </TransitionChild>
                    </div>
                </div>
            </div>
        </Dialog>
    </TransitionRoot>
</template>

<script setup>
import { ref, watch } from "vue";
import { Dialog, DialogPanel, DialogTitle, TransitionChild, TransitionRoot } from "@headlessui/vue";
import {
    ArrowDownTrayIcon,
    ArrowUpTrayIcon,
    MusicalNoteIcon,
    PencilSquareIcon,
    TrashIcon,
    XMarkIcon,
} from "@heroicons/vue/24/solid";
import AudioPlayer from "./general/AudioPlayer.vue";
import Badge from "./general/Badge.vue";

const props = defineProps({
    show: { type: Boolean, default: false },
    stream: { type: Object, default: null },
    permissions: { type: Object, default: () => ({}) },
});

const emit = defineEmits(["close", "edit", "delete", "delete-file", "upload", "download"]);

const grayBadge = { backgroundColor: "bg-surface-2", textColor: "text-body", ringColor: "ring-strong/20" };
const blueBadge = { backgroundColor: "bg-info-subtle", textColor: "text-info", ringColor: "ring-info/20" };
const amberBadge = { backgroundColor: "bg-warning-subtle", textColor: "text-warning", ringColor: "ring-warning/20" };

const activeFile = ref(null);

watch(
    () => [props.stream?.music_on_hold_uuid, props.stream?.files],
    () => {
        const files = props.stream?.files;
        if (!files?.length) {
            activeFile.value = null;
            return;
        }

        const current = activeFile.value;
        if (!current || !files.some((f) => f.name === current.name)) {
            activeFile.value = files[0];
            return;
        }

        activeFile.value = files.find((f) => f.name === current.name) ?? files[0];
    },
    { immediate: true }
);

const setActiveFile = (file) => {
    activeFile.value = file;
};

const formatDate = (value) => {
    if (!value) return "";
    const date = new Date(value);
    return Number.isNaN(date.getTime()) ? value : date.toLocaleDateString();
};
</script>
