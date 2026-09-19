<template>
    <AddEditItemModal :show="show" :header="item?.module_uuid ? $t('Edit Module') : $t('New Module')"
        :loading="loading" custom-class="sm:max-w-2xl" @close="close">
        <template #modal-body>
            <Vueform v-if="show && !loading" ref="form$" :default="item" :endpoint="submitForm" :validate-on="''"
                :display-errors="false" @success="handleSuccess" @response="handleResponse" @error="handleError">
                <template #empty>
                    <FormElements>
                        <TextElement name="module_label" :label="$t('Label')" :floating="false"
                            :columns="{ sm: { container: 6 } }" />
                        <TextElement name="module_name" :label="$t('Module name')" :floating="false"
                            placeholder="mod_example" :columns="{ sm: { container: 6 } }"
                            :description="$t('Use the installed module name, such as mod_shout, without a file extension.')" />
                        <SelectElement name="module_category" :label="$t('Category')" :items="categories"
                            :create="true" allow-absent :native="false" input-type="search" autocomplete="off"
                            :search="true" :strict="false" :floating="false" :columns="{ sm: { container: 6 } }" />
                        <TextElement name="module_order" :label="$t('Order')" input-type="number"
                            :floating="false" :columns="{ sm: { container: 6 } }" />
                        <ToggleElement name="module_enabled" :text="$t('Autoload enabled')"
                            true-value="true" false-value="false"
                            :description="$t('Load this module when FreeSWITCH starts. Use Start or Stop on the modules page to change its current runtime status.')" />
                        <ToggleElement name="module_default_enabled" :text="$t('Default autoload enabled')"
                            true-value="true" false-value="false" />
                        <TextareaElement name="module_description" :label="$t('Description')" :rows="2" :floating="false" />
                        <ButtonElement name="submit" :button-label="$t('Save')" :submits="true" align="right" />
                    </FormElements>
                </template>
            </Vueform>
        </template>
    </AddEditItemModal>
</template>

<script setup>
import { ref, watch } from 'vue';
import AddEditItemModal from './AddEditItemModal.vue';

const props = defineProps({
    show: Boolean,
    loading: Boolean,
    item: { type: Object, default: () => ({}) },
    categories: { type: Array, default: () => [] },
    route: String,
});
const emit = defineEmits(['close', 'saved', 'error']);
const form$ = ref(null);
const saving = ref(false);
watch(form$, (form) => form?.disableValidation());

const close = () => {
    if (!saving.value) emit('close');
};

const submitForm = async (_, form) => {
    form.messageBag.clear();
    Object.values(form.elements$).forEach((element) => element.messageBag?.clear());
    saving.value = true;

    try {
        const method = props.item?.module_uuid ? 'put' : 'post';
        return await form.$vueform.services.axios[method](props.route, form.requestData);
    } finally {
        saving.value = false;
    }
};

const handleResponse = (response, form) => {
    Object.entries(response.data?.errors || {}).forEach(([name, messages]) => {
        messages.forEach((message) => form.el$(name)?.messageBag.append(message));
    });
};

const handleSuccess = (response) => {
    emit('saved', response.data);
    emit('close');
};

const handleError = (error) => emit('error', error);
</script>
