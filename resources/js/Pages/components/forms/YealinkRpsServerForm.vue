<template>
    <TransitionRoot as="div" :show="show">
        <Dialog as="div" class="relative z-20">
            <TransitionChild as="div" enter="ease-out duration-300" enter-from="opacity-0" enter-to="opacity-100"
                leave="ease-in duration-200" leave-from="opacity-100" leave-to="opacity-0">
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" />
            </TransitionChild>

            <div class="fixed inset-0 z-20 w-screen overflow-y-auto">
                <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
                    <TransitionChild as="template" enter="ease-out duration-300"
                        enter-from="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                        enter-to="opacity-100 translate-y-0 sm:scale-100" leave="ease-in duration-200"
                        leave-from="opacity-100 translate-y-0 sm:scale-100"
                        leave-to="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95">
                        <DialogPanel
                            class="relative transform rounded-lg bg-white px-4 pb-4 pt-5 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-2xl sm:p-6">
                            <DialogTitle as="h3" class="mb-4 pr-8 text-base font-semibold leading-6 text-gray-900">
                                {{ header }}
                            </DialogTitle>

                            <div class="absolute right-0 top-0 pr-4 pt-4">
                                <button type="button"
                                    class="rounded-md bg-white text-gray-400 hover:text-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                                    @click="emit('close')">
                                    <span class="sr-only">{{ $t('Close') }}</span>
                                    <XMarkIcon class="h-6 w-6" aria-hidden="true" />
                                </button>
                            </div>

                            <div v-if="loading" class="space-y-4 py-6">
                                <div class="h-2 animate-pulse rounded bg-slate-200" />
                                <div class="h-2 animate-pulse rounded bg-slate-200" />
                                <div class="h-2 animate-pulse rounded bg-slate-200" />
                            </div>

                            <Vueform v-else :endpoint="submitForm" :display-errors="false"
                                :default="defaults" @success="handleSuccess" @error="handleError"
                                @response="handleResponse">
                                <StaticElement name="intro">
                                    <p class="max-w-2xl text-sm text-gray-500">
                                        {{ $t('Yealink RPS redirects phones to this FS PBX provisioning server.') }}
                                    </p>
                                </StaticElement>

                                <HiddenElement name="provider" :meta="true" />
                                <HiddenElement v-if="isUpdate" name="organization_id" :meta="true" />

                                <TextElement name="name" :label="$t('Server Name')"
                                    :rules="['required', 'max:20']" />

                                <TextElement name="address" :label="$t('Provisioning URL')"
                                    :rules="['required', 'url', 'max:512']" />

                                <TextElement name="prov_un" :label="$t('Provisioning Username')"
                                    :rules="['required', 'max:32']" />

                                <TextElement name="prov_pw" :label="$t('Provisioning Password')"
                                    :attrs="{ type: 'password', autocomplete: 'new-password' }"
                                    :rules="['required', 'max:32']" />

                                <StaticElement name="credential_note">
                                    <p class="text-sm text-gray-500">
                                        {{ $t('These are the HTTP credentials the phone uses when it contacts FS PBX.') }}
                                    </p>
                                </StaticElement>

                                <GroupElement name="buttons" />

                                <ButtonElement name="cancel" :button-label="$t('Cancel')" :secondary="true"
                                    :submits="false" @click="emit('close')" :columns="{ container: 6 }" />

                                <ButtonElement name="submit" :button-label="$t('Save')" :submits="true"
                                    align="right" :columns="{ container: 6 }" />
                            </Vueform>
                        </DialogPanel>
                    </TransitionChild>
                </div>
            </div>
        </Dialog>
    </TransitionRoot>
</template>

<script setup>
import { computed } from 'vue'
import { Dialog, DialogPanel, DialogTitle, TransitionChild, TransitionRoot } from '@headlessui/vue'
import { XMarkIcon } from '@heroicons/vue/24/solid'
import { trans } from 'laravel-vue-i18n'

const props = defineProps({
    show: Boolean,
    loading: Boolean,
    header: String,
    options: Object,
    mode: {
        type: String,
        default: 'create',
    },
})

const emit = defineEmits(['close', 'error', 'success', 'refresh-data'])

const isUpdate = computed(() => props.mode === 'update')

const defaults = computed(() => ({
    provider: 'yealink',
    organization_id: props.options?.organization_id,
    name: props.options?.organization?.name ?? '',
    address: props.options?.organization?.template?.provisioning?.server?.address
        ?? props.options?.provider_settings?.yealink_provision_url
        ?? '',
    prov_un: props.options?.organization?.template?.provisioning?.server?.username
        ?? props.options?.provider_settings?.http_auth_username
        ?? '',
    prov_pw: props.options?.provider_settings?.http_auth_password ?? '',
}))

const submitForm = async (FormData, form$) => {
    const route = isUpdate.value
        ? props.options.routes.cloud_provisioning_update_organization
        : props.options.routes.cloud_provisioning_create_organization

    const method = isUpdate.value ? 'put' : 'post'

    return await form$.$vueform.services.axios[method](route, form$.requestData)
}

const handleResponse = (response, form$) => {
    Object.values(form$.elements$).forEach((element) => element.messageBag?.clear())

    Object.entries(response.data.errors ?? {}).forEach(([name, messages]) => {
        form$.el$(name)?.messageBag.append(messages[0])
    })
}

const handleSuccess = (response) => {
    emit('success', response.data.messages)
    emit('close')
    emit('refresh-data', 'yealink')
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
