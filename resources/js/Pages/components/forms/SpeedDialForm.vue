<template>
    <Vueform ref="form$" :endpoint="false" :default="defaultValues" :display-errors="false"
        @mounted="(form) => form.disableValidation()" @submit="submitForm">
        <template #empty>
            <div class="lg:grid lg:grid-cols-12 lg:gap-x-5">
                <div class="px-2 py-6 sm:px-6 lg:col-span-3 lg:px-0 lg:py-0">
                    <FormTabs view="vertical">
                        <FormTab name="general" :label="$t('General')" :elements="[
                            'general_header', 'contact_organization', 'destination_number',
                            'phone_speed_dial', 'general_submit',
                        ]" />
                        <FormTab name="advanced" :label="$t('Advanced')" :elements="[
                            'advanced_header', 'speed_dial_users', 'advanced_submit',
                        ]" />
                    </FormTabs>
                </div>

                <div class="sm:px-6 lg:col-span-9 sm:rounded-md text-gray-600 bg-gray-50 px-4 py-6 sm:p-6">
                    <FormElements>
                        <StaticElement name="general_header" tag="h4" :content="$t('General')"
                            :description="$t('Update speed dial details.')" />

                        <TextElement name="contact_organization" :label="$t('Speed Dial Name')"
                            :floating="false" :columns="{ sm: { container: 6 } }" />
                        <TextElement name="destination_number" :label="$t('Destination Number')"
                            input-type="tel" :floating="false" :columns="{ sm: { container: 6 } }" />
                        <TextElement name="phone_speed_dial" :label="$t('Speed Dial Code')"
                            :floating="false" :columns="{ sm: { container: 6 } }" />

                        <ButtonElement name="general_submit" :button-label="$t('Save')" :submits="true"
                            :disabled="isSubmitting" :loading="isSubmitting" align="right" />

                        <StaticElement name="advanced_header" tag="h4" :content="$t('Advanced Settings')" />
                        <TagsElement name="speed_dial_users" :label="$t('Assigned Users')" :items="userOptions"
                            :search="true" :close-on-select="false" :floating="false" input-type="search"
                            :placeholder="$t('Choose Assigned User(s)')"
                            :description="$t('This parameter enables the automatic provisioning of speed dial entries on phones that are assigned to the same users')" />

                        <ButtonElement name="advanced_submit" :button-label="$t('Save')" :submits="true"
                            :disabled="isSubmitting" :loading="isSubmitting" align="right" />
                    </FormElements>
                </div>
            </div>
        </template>
    </Vueform>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { clearServerFormErrors, showServerFormErrors } from '../../../composables/serverFormErrors.js';

const props = defineProps({
    options: { type: Object, required: true },
    isSubmitting: Boolean,
    errors: Object,
});

const emit = defineEmits(['submit']);
const page = usePage();
const form$ = ref(null);

const userOptions = computed(() => (props.options.users ?? []).map((user) => ({
    value: user.value,
    label: user.name,
})));

const defaultValues = computed(() => ({
    contact_organization: props.options.contact?.contact_organization ?? null,
    destination_number: props.options.contact?.primary_phone?.phone_number ?? null,
    phone_speed_dial: props.options.contact?.primary_phone?.phone_speed_dial ?? null,
    speed_dial_users: (props.options.speed_dial_users ?? [])
        .filter((user) => userOptions.value.some((option) => option.value === user.value))
        .map((user) => user.value),
}));

watch(() => props.errors, (errors) => {
    if (!form$.value) return;

    showServerFormErrors({ data: { errors } }, form$.value);

    const firstField = Object.keys(errors ?? {})[0];
    if (firstField === 'speed_dial_users' || firstField?.startsWith('speed_dial_users.')) {
        form$.value.tabs$?.goTo('advanced');
    } else if (['contact_organization', 'destination_number', 'phone_speed_dial'].includes(firstField)) {
        form$.value.tabs$?.goTo('general');
    }
});

function submitForm(form) {
    if (props.isSubmitting) return;

    clearServerFormErrors(form);
    const data = form.requestData;

    emit('submit', {
        contact_organization: data.contact_organization,
        destination_number: data.destination_number,
        phone_speed_dial: data.phone_speed_dial,
        speed_dial_users: (data.speed_dial_users ?? []).map((value) => ({ value })),
        _token: page.props.csrf_token,
    });
}
</script>
