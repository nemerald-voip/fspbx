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
                                    <span class="sr-only">{{ $t('Close') }}</span>
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
                                    <div class="text-lg text-blue-600">{{ $t('Loading...') }}</div>
                                </div>
                            </div>

                            <Vueform v-else :endpoint="submitForm" :default="defaultValues" :display-errors="false"
                                @success="handleSuccess" @error="handleError" @response="handleResponse">
                                <template #empty>
                                    <div class="lg:grid lg:grid-cols-12 lg:gap-x-5">
                                        <div class="px-2 py-6 sm:px-6 lg:col-span-3 lg:px-0 lg:py-0">
                                            <FormTabs view="vertical">
                                                <FormTab name="settings" :label="$t('Settings')" :elements="[
                                                    'settings_header',
                                                    'name',
                                                    'enabled',
                                                    'extension',
                                                    'source',
                                                    'description',
                                                    'rules_header',
                                                    'rules',
                                                    'fallback_header',
                                                    'default_destination_type',
                                                    'default_destination_target',
                                                    'settings_submit',
                                                ]" />
                                            </FormTabs>
                                        </div>

                                        <div
                                            class="space-y-6 bg-gray-50 px-4 py-6 text-gray-600 shadow sm:rounded-md sm:px-6 sm:p-6 lg:col-span-9">
                                            <FormElements>
                                                <StaticElement name="settings_header" tag="h4" :content="$t('Route Settings')"
                                                    :description="$t('Calls transfer to this extension, then match the original DID against the ordered rules below.')" />
                                                <TextElement name="name" :label="$t('Name')" :floating="false"
                                                    :columns="{ sm: { container: 6 } }" />
                                                <ToggleElement name="enabled" :text="$t('Dynamic Route Enabled')"
                                                    label="&nbsp;" :columns="{ sm: { container: 6 } }" />
                                                <TextElement name="extension" :label="$t('Extension')" :floating="false"
                                                    :columns="{ sm: { container: 6 } }" />
                                                <SelectElement name="source" :items="sourceOptions" :label="$t('Lookup Source')"
                                                    :native="false" :floating="false" :columns="{ sm: { container: 6 } }" />
                                                <TextareaElement name="description" :label="$t('Description')" :rows="2" />

                                                <StaticElement name="rules_header" tag="h4" :content="$t('Match Rules')"
                                                    :description="$t('Rules are checked from top to bottom. Match values are exact, including any leading plus sign.')" />
                                                <ListElement name="rules" :sort="true" :initial="1" size="sm"
                                                    :controls="{ add: true, remove: true, sort: true }"
                                                    :add-classes="{ ListElement: { listItem: 'bg-white p-4 mb-4 rounded-lg shadow-md' } }">
                                                    <template #default="{ index }">
                                                        <ObjectElement :name="index">
                                                            <TextElement name="match_value" :label="$t('Match Value')" :floating="false"
                                                                :columns="{ sm: { container: 4 } }" />
                                                            <SelectElement name="destination_type" :items="routingTypes"
                                                                label-prop="name" :label="$t('Destination Type')" :native="false"
                                                                :search="true" :floating="false" :columns="{ sm: { container: 4 } }"
                                                                @change="(value, oldValue, el$) => {
                                                                    const target = el$.form$.el$(`rules.${index}.destination_target`);
                                                                    if (oldValue !== null && oldValue !== undefined) target.clear();
                                                                    target.updateItems();
                                                                }" />
                                                            <SelectElement name="destination_target"
                                                                :items="(query, input) => fetchTargets(input, `rules.${index}.destination_type`)"
                                                                value-prop="extension" label-prop="name" :label="$t('Destination')"
                                                                :native="false" :search="true" allow-absent :strict="false"
                                                                :floating="false" :conditions="[
                                                                    [`rules.${index}.destination_type`, 'not_empty'],
                                                                    [`rules.${index}.destination_type`, 'not_in', destinationTypesWithoutTarget],
                                                                ]" :columns="{ sm: { container: 4 } }" />
                                                        </ObjectElement>
                                                    </template>
                                                </ListElement>

                                                <StaticElement name="fallback_header" tag="h4"
                                                    :content="$t('Fallback Destination')"
                                                    :description="$t('Calls use this destination when no rule matches.')" />
                                                <SelectElement name="default_destination_type" :items="routingTypes"
                                                    label-prop="name" :label="$t('Destination Type')" :native="false"
                                                    :search="true" :floating="false" :columns="{ sm: { container: 6 } }"
                                                    @change="(value, oldValue, el$) => {
                                                        const target = el$.form$.el$('default_destination_target');
                                                        if (oldValue !== null && oldValue !== undefined) target.clear();
                                                        target.updateItems();
                                                    }" />
                                                <SelectElement name="default_destination_target"
                                                    :items="(query, input) => fetchTargets(input, 'default_destination_type')"
                                                    value-prop="extension" label-prop="name" :label="$t('Destination')"
                                                    :native="false" :search="true" allow-absent :strict="false"
                                                    :floating="false" :conditions="[
                                                        ['default_destination_type', 'not_empty'],
                                                        ['default_destination_type', 'not_in', destinationTypesWithoutTarget],
                                                    ]" :columns="{ sm: { container: 6 } }" />
                                                <ButtonElement name="settings_submit" :button-label="$t('Save')" :submits="true"
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
import { computed } from 'vue';
import { Dialog, DialogPanel, DialogTitle, TransitionChild, TransitionRoot } from '@headlessui/vue';
import { XMarkIcon } from '@heroicons/vue/24/solid';

const props = defineProps({ show: Boolean, options: Object, loading: Boolean, header: String, mode: String });
const emit = defineEmits(['close', 'error', 'success', 'refresh-data']);
const destinationTypesWithoutTarget = ['check_voicemail', 'company_directory', 'hangup'];
const routingTypes = computed(() => props.options?.routing_types ?? []);
const sourceOptions = computed(() => props.options?.source_options ?? []);
const defaultValues = computed(() => ({
    name: props.options?.item?.name ?? null,
    extension: props.options?.item?.extension ?? null,
    source: props.options?.item?.source ?? 'caller_destination',
    enabled: props.options?.item?.enabled ?? true,
    description: props.options?.item?.description ?? null,
    default_destination_type: props.options?.item?.default_destination_type ?? 'hangup',
    default_destination_target: props.options?.item?.default_destination_value ?? null,
    rules: (props.options?.item?.rules ?? []).map((rule) => ({
        match_value: rule.match_value,
        destination_type: rule.destination_type,
        destination_target: rule.destination_value,
    })),
}));

const fetchTargets = async (input, typeElementName) => {
    const type = input.$parent.el$.form$.el$(typeElementName);
    if (!type?.value || destinationTypesWithoutTarget.includes(type.value)) return [];
    try {
        const response = await type.$vueform.services.axios.post(props.options.routes.get_routing_options, {
            category: type.value,
        });
        return response?.data?.options ?? [];
    } catch (error) {
        emit('error', error);
        return [];
    }
};

const submitForm = async (FormData, form$) => {
    const route = props.mode === 'create' ? props.options.routes.store_route : props.options.routes.update_route;
    return props.mode === 'create'
        ? form$.$vueform.services.axios.post(route, form$.requestData)
        : form$.$vueform.services.axios.put(route, form$.requestData);
};

const clearErrors = (element) => {
    element.messageBag?.clear();
    if (element.children$) Object.values(element.children$).forEach(clearErrors);
};

const handleResponse = (response, form$) => {
    Object.values(form$.elements$).forEach(clearErrors);
    Object.entries(response.data.errors ?? {}).forEach(([name, messages]) => {
        form$.el$(name)?.messageBag.append(messages[0]);
    });
};

const handleSuccess = (response) => {
    emit('success', 'success', response.data.messages);
    emit('refresh-data');
    emit('close');
};

const handleError = (error, details) => {
    if (details?.type === 'submit') emit('error', error);
};
</script>
