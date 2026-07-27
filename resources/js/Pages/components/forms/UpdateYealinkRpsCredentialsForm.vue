<template>
    <Vueform :endpoint="submitForm" :display-errors="false" :default="{
        provider: 'yealink',
        access_key_id: credentials?.access_key_id,
        access_key_secret: credentials?.access_key_secret,
    }" @success="handleSuccess" @error="handleError" @response="handleResponse">
        <StaticElement name="intro">
            <p class="text-sm text-gray-500">
                {{ $t('Enter the AccessKey credentials issued by Yealink RPS Enterprise.') }}
            </p>
        </StaticElement>

        <HiddenElement name="provider" :meta="true" />

        <TextElement name="access_key_id" :label="$t('AccessKey ID')" :rules="['required']"
            :attrs="{ autocomplete: 'off' }" />

        <TextElement name="access_key_secret" :label="$t('AccessKey Secret')" :rules="['required']"
            :attrs="{ type: 'password', autocomplete: 'new-password' }" />

        <GroupElement name="buttons" />

        <ButtonElement name="cancel" :button-label="$t('Cancel')" :secondary="true" :submits="false"
            @click="emit('cancel')" :columns="{ container: 6 }" />

        <ButtonElement name="submit" :button-label="$t('Save')" :submits="true" align="right"
            :columns="{ container: 6 }" />
    </Vueform>
</template>

<script setup>
import { trans } from 'laravel-vue-i18n'

const props = defineProps({
    credentials: Object,
    route: String,
})

const emit = defineEmits(['cancel', 'error', 'success'])

const submitForm = async (FormData, form$) => {
    return await form$.$vueform.services.axios.post(props.route, form$.requestData)
}

const handleResponse = (response, form$) => {
    Object.values(form$.elements$).forEach((element) => element.messageBag?.clear())

    Object.entries(response.data.errors ?? {}).forEach(([name, messages]) => {
        form$.el$(name)?.messageBag.append(messages[0])
    })
}

const handleSuccess = (response) => {
    emit('success', response.data.messages)
}

const handleError = (error, details, form$) => {
    form$.messageBag.clear()

    if (details.type === 'submit') {
        emit('error', error)
        return
    }

    form$.messageBag.append(trans('Could not submit the form.'))
}
</script>
