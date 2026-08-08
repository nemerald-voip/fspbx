<template>
    <Vueform :endpoint="submitForm" :display-errors="false" :default="{
        provider: 'yealink',
        access_key_id: credentials?.access_key_id,
        access_key_secret: credentials?.access_key_secret,
        api_url: credentials?.api_url ?? 'https://us-api.ymcs.yealink.com',
    }" @success="handleSuccess" @error="handleError" @response="handleResponse">
        <StaticElement name="intro">
            <p class="text-sm text-gray-500">
                {{ $t('In Yealink RPS Service, go to System > Integration > API and copy the Domain and AccessKey credentials from Authentication Info.') }}
            </p>
        </StaticElement>

        <HiddenElement name="provider" :meta="true" />

        <SelectElement name="api_url" :label="$t('API Domain')" :items="apiDomains"
            label-prop="label" value-prop="value" :native="false" :search="false" />

        <TextElement name="access_key_id" :label="$t('AccessKey ID')"
            :attrs="{ autocomplete: 'off' }" />

        <TextElement name="access_key_secret" :label="$t('AccessKey Secret')"
            :attrs="{ type: 'password', autocomplete: 'new-password' }" />

        <GroupElement name="buttons" />

        <ButtonElement name="cancel" :button-label="$t('Cancel')" :secondary="true" :submits="false"
            @click="emit('cancel')" :columns="{ container: 6 }" />

        <ButtonElement name="submit" :button-label="$t('Save')" :submits="true" align="right"
            :columns="{ container: 6 }" />
    </Vueform>
</template>

<script setup>
import { trans } from '@i18n'

const props = defineProps({
    credentials: Object,
    route: String,
})

const emit = defineEmits(['cancel', 'error', 'success'])

const apiDomains = [
    { value: 'https://us-api.ymcs.yealink.com', label: 'US — us-api.ymcs.yealink.com' },
    { value: 'https://eu-api.ymcs.yealink.com', label: 'EU — eu-api.ymcs.yealink.com' },
    { value: 'https://au-api.ymcs.yealink.com', label: 'AU — au-api.ymcs.yealink.com' },
]

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
