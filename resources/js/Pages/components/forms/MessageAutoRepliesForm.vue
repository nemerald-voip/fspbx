<template>
    <div class="mt-4 space-y-4">
        <p class="text-sm text-gray-600">{{ destination }}</p>
        <p v-if="loading" class="text-sm text-gray-500">{{ $t('Loading...') }}</p>
        <Vueform v-if="settings" ref="form$" :default="settings" :endpoint="false" :display-errors="false"
            @mounted="form => form.disableValidation()" @submit="save">
            <ToggleElement name="enabled" align="left" :label="$t('Enable auto-replies')"
                :true-value="true" :false-value="false"
                @change="(enabled, previous, el$) => {
                    const rules = el$.form$.el$('rules');
                    if (enabled === true && previous === false && rules && !rules.value.length) {
                        rules.add({ trigger: 'keyword', keywords: [], reply: '', enabled: true });
                    }
                }"
                :info="$t('One reply is sent per incoming message: keyword first, then first incoming message, then every incoming message. Opt-out confirmations take priority. Group replies go directly to the sender.')" />
            <ListElement name="rules" :initial="0" :add-text="$t('Add auto-reply')"
                :controls="{ add: true, remove: true, sort: false }"
                :add-classes="{ ListElement: { listItem: 'bg-gray-50 p-4 mb-3 rounded-md border border-gray-200' } }">
                <template #default="{ index }">
                    <ObjectElement :name="index">
                        <SelectElement name="trigger" :label="$t('Reply when')" :floating="false" :native="false"
                            :can-clear="false" default="keyword" :items="triggers" :columns="{ sm: { container: 8 } }" />
                        <ToggleElement name="enabled" :text="$t('Enabled')" label="&nbsp;" :default="true"
                            :true-value="true" :false-value="false" :columns="{ sm: { container: 4 } }" />
                        <TagsElement name="keywords" :label="$t('Keywords')" :floating="false" :items="[]"
                            :search="true" :create="true" :append-new-option="false" :add-option-on="['enter']"
                            :conditions="[[`rules.${index}.trigger`, 'keyword']]"
                            :description="$t('Type a keyword and press Enter. Matches the whole message, ignoring capitalization.')" />
                        <StaticElement name="first_help" :conditions="[[`rules.${index}.trigger`, 'first']]"
                            :content="$t('Replies to the sender’s first incoming message on record for this phone number. Deleted history can affect this check.')" />
                        <TextareaElement name="reply" :label="$t('Reply message')" :floating="false" :rows="3" />
                    </ObjectElement>
                </template>
            </ListElement>
            <ButtonElement name="save" :button-label="$t('Save')" :submits="true" :loading="saving" :disabled="saving" align="right" />
        </Vueform>
    </div>
</template>
<script setup>
import { onMounted, ref } from 'vue';
import axios from 'axios';
import { trans } from '@i18n';
import { clearServerFormErrors, showServerFormErrors } from '../../../composables/serverFormErrors.js';
const props = defineProps({ route: String, destination: String });
const emit = defineEmits(['success', 'error']);
const form$ = ref(null), settings = ref(null), loading = ref(true), saving = ref(false);
const triggers = [
    { value: 'keyword', label: trans('Keyword received') },
    { value: 'first', label: trans('First incoming message') },
    { value: 'every', label: trans('Every incoming message') },
];
const showError = error => {
    if (form$.value) showServerFormErrors(error.response, form$.value);
    emit('error', error.response?.data?.errors || { request: [error.response?.data?.message || error.message] });
};
onMounted(async () => {
    try { settings.value = (await axios.get(props.route)).data; }
    catch (error) { showError(error); }
    finally { loading.value = false; }
});
const save = async form => {
    if (saving.value) return;
    clearServerFormErrors(form);
    saving.value = true;
    try { const { data } = await axios.post(props.route, form.requestData); emit('success', data.messages); }
    catch (error) { showError(error); }
    finally { saving.value = false; }
};
</script>
