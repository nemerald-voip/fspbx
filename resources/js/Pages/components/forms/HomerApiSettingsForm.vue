<template>
    <Vueform ref="form$" :endpoint="submitForm" :display-errors="false"
        @mounted="(form) => form.disableValidation()" @submit="clearServerFormErrors"
        @response="handleResponse" @success="handleSuccess" @error="handleError">
        <template #empty>
            <div class="mt-6 space-y-6 bg-gray-50 px-4 py-6 text-gray-600 sm:p-6">
                <FormElements>
                    <StaticElement name="heading" tag="h4" :content="$t('Remote HOMER API')"
                        :description="$t('Connect to a remote HOMER server to view SIP traces and download PCAP files from Call History.')" />
                    <ToggleElement name="enabled" :text="$t('Enable Homer API Integration')" :true-value="true" :false-value="false" />
                    <TextElement name="base_url" :label="$t('HOMER API base URL')" :floating="false"
                        placeholder="https://homer.example.com" autocomplete="off"
                        :description="$t('Use HTTPS with a trusted certificate. Do not append /api/v4. Private monitoring addresses are supported.')" />
                    <TextElement name="api_token" :label="$t('API token')" input-type="password" autocomplete="new-password" :floating="false"
                        :description="hasToken ? $t('Leave blank to keep the saved token.') : $t('The API token is encrypted at rest and is never returned to the browser.')" />
                    <StaticElement name="test_action">
                        <button type="button" :disabled="testing" @click="testConnection"
                            class="rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-gray-300 disabled:opacity-50">
                            {{ testing ? $t('Testing…') : $t('Test Connection') }}
                        </button>
                    </StaticElement>
                </FormElements>
            </div>
        </template>
    </Vueform>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import axios from 'axios';
import { trans } from '@i18n';
import { clearServerFormErrors } from '../../../composables/serverFormErrors.js';

const props = defineProps({ settings: Object, saveRoute: String, testRoute: String });
const emit = defineEmits(['success', 'error']);
const form$ = ref(null);
const testing = ref(false);
const hasToken = ref(props.settings?.has_api_token ?? false);

onMounted(() => {
    form$.value.update({ enabled: false, base_url: '', ...props.settings, api_token: '' });
    form$.value.clean();
});

const submitForm = (data, form) => axios.put(props.saveRoute, form.requestData);
const handleResponse = (response, form) => {
    Object.entries(response?.data?.errors ?? {}).forEach(([name, messages]) => {
        form.el$(name)?.messageBag.append(messages[0]);
    });
};
const handleSuccess = (response, form, notify = true) => {
    hasToken.value = response.data.settings.has_api_token;
    form.update({ api_token: '' });
    form.clean();
    if (notify) emit('success', 'success', response.data.messages);
};
const handleError = (error) => emit('error', error);
const testConnection = async () => {
    if (testing.value) return;
    testing.value = true;
    clearServerFormErrors(form$.value);
    try {
        const response = await axios.post(props.testRoute, form$.value.requestData);
        emit('success', 'success', response.data.messages);
    } catch (error) {
        handleResponse(error.response, form$.value);
        emit('error', error.response ? error : { response: { data: { errors: { connection: [trans('Unable to connect to HOMER.')] } } } });
    } finally {
        testing.value = false;
    }
};
const save = async () => {
    const form = form$.value;
    clearServerFormErrors(form);
    try {
        const response = await submitForm(null, form);
        handleSuccess(response, form, false);
        return response.data;
    } catch (error) {
        handleResponse(error.response, form);
        throw error;
    }
};

defineExpose({ save });

</script>
