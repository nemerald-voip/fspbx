<template>
    <Teleport to="body">
        <TransitionRoot as="div" :show="show">
            <Dialog as="div" class="relative z-50" @close="emit('close')">
                <TransitionChild as="div" enter="ease-out duration-300" enter-from="opacity-0" enter-to="opacity-100"
                    leave="ease-in duration-200" leave-from="opacity-100" leave-to="opacity-0">
                    <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" />
                </TransitionChild>

                <div class="fixed inset-0 z-50 w-screen overflow-y-auto">
                    <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
                        <TransitionChild as="div" enter="ease-out duration-300"
                            enter-from="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                            enter-to="opacity-100 translate-y-0 sm:scale-100" leave="ease-in duration-200"
                            leave-from="opacity-100 translate-y-0 sm:scale-100"
                            leave-to="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95">
                            <DialogPanel
                                class="relative transform overflow-hidden rounded-lg bg-white px-6 pb-6 pt-6 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-xl sm:p-8">
                                <div>
                                    <DialogTitle as="h3" class="text-base font-semibold leading-6 text-gray-900">
                                        New Group
                                    </DialogTitle>
                                    <p class="mt-1 text-sm text-gray-500">
                                        Create a reusable group for related call flows.
                                    </p>
                                </div>

                                <Vueform ref="form$" :endpoint="false" :display-errors="false">
                                    <template #empty>
                                        <FormElements>

                                            <GroupElement name="name_container" />

                                            <TextElement name="group_name" label="Group Name"
                                                placeholder="Enter group name" :floating="false"
                                                :columns="{ container: 12 }" />

                                            <StaticElement v-if="error" name="group_error">
                                                <p class="text-sm text-red-600">{{ error }}</p>
                                            </StaticElement>

                                            <GroupElement name="button_container" />

                                            <ButtonElement name="cancel" button-label="Cancel" :secondary="true"
                                                :submits="false" :columns="{ sm: { container: 6 } }"
                                                @click="emit('close')" />

                                            <ButtonElement name="save" button-label="Save" :submits="false"
                                                :loading="loading" align="right"
                                                :columns="{ sm: { container: 6 } }"
                                                @click="saveGroup" />
                                        </FormElements>
                                    </template>
                                </Vueform>
                            </DialogPanel>
                        </TransitionChild>
                    </div>
                </div>
            </Dialog>
        </TransitionRoot>
    </Teleport>
</template>

<script setup>
import { ref, watch } from "vue";
import { Dialog, DialogPanel, DialogTitle, TransitionChild, TransitionRoot } from "@headlessui/vue";

const emit = defineEmits(["close", "confirm"]);

const props = defineProps({
    show: Boolean,
    loading: {
        type: Boolean,
        default: false,
    },
    error: {
        type: String,
        default: null,
    },
});

const form$ = ref(null);

watch(() => props.show, (show) => {
    if (show) {
        form$.value?.reset();
    }
});

const saveGroup = () => {
    const field = form$.value?.el$("group_name");
    const name = String(field?.value ?? "").trim();

    field?.messageBag?.clear();

    if (!name) {
        field?.messageBag?.append("Enter a group name.");
        return;
    }

    emit("confirm", name);
    emit("close");
};
</script>
