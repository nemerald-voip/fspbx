<template>
    <Vueform ref="form$" :endpoint="false" @submit="submitForm" :display-errors="false">
        <SelectElement name="carrier" :items="options.carrier" :label="$t('Message Provider')"
            :search="true" :native="false" :floating="false" />
        <ToggleElement name="replace_extensions" :text="$t('Replace allowed extensions')" />
        <TagsElement name="allowed_extension_uuids" :items="options.extensions" :close-on-select="false"
            :conditions="[['replace_extensions', true]]" :search="true" :native="false" :floating="false"
            :label="$t('Allowed extensions')"
            :description="$t('Replace the extension list for every selected number. An empty list removes all extension access. Choose numbers from the current account.')"
            :default="[]" />
        <TextElement name="email" :label="$t('Email')" :floating="false" />
        <TextareaElement name="description" :label="$t('Description')" :rows="2" />
        <StaticElement name="errors" v-if="errors"><p class="text-red-600">{{ errorText }}</p></StaticElement>
        <ButtonElement name="save" :button-label="$t('Save')" :submits="true" :disabled="isSubmitting" align="right" />
    </Vueform>
</template>

<script setup>
import { ref, computed } from 'vue';
const props = defineProps({ items: Array, options: Object, isSubmitting: Boolean, errors: Object });
const emits = defineEmits(['submit', 'cancel']);
const form$ = ref(null);
const errorText = computed(() => Object.values(props.errors || {}).flat().join(' '));
function submitForm() {
    const data = { ...form$.value.requestData, items: props.items };
    if (!data.replace_extensions) delete data.allowed_extension_uuids;
    delete data.replace_extensions;
    for (const field of ['carrier', 'email', 'description']) {
        if (data[field] === '' || data[field] == null) delete data[field];
    }
    emits('submit', data);
}
</script>
