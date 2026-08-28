<template>
    <Vueform ref="form$" :endpoint="submitForm" @success="handleSuccess" @error="handleError"
        @response="handleResponse" :display-errors="false">
        <template #empty>
            <div class="space-y-6 bg-gray-50 px-4 py-6 text-gray-600 sm:p-6">
                <FormElements>
                    <StaticElement name="sip_capture_heading" tag="h4" :content="$t('SIP Capture')"
                        :description="$t('Send a copy of SIP signaling to a HOMER-compatible HEP collector for troubleshooting and call-flow analysis.')" />

                    <StaticElement name="sip_capture_notice" tag="div" :add-classes="{
                        StaticElement: { container: 'rounded-md border border-blue-200 bg-blue-50 p-4' },
                    }">
                        <template #default>
                            <div class="flex items-start gap-3 text-sm text-blue-950">
                                <InformationCircleIcon class="mt-0.5 size-5 shrink-0 text-blue-600" aria-hidden="true" />
                                <div>
                                    <p class="font-medium">{{ $t('Live application does not restart Sofia profiles.') }}</p>
                                    <p class="mt-1">{{ $t('Saving rescans the profiles and applies these capture settings without dropping active calls or registrations. The collector remains outside the live call path.') }}</p>
                                    <p class="mt-1">{{ $t('On redundant systems, open this page and save once on each PBX. Each server receives its own Capture ID and applies its local profiles.') }}</p>
                                </div>
                            </div>
                        </template>
                    </StaticElement>

                    <ToggleElement name="enabled" :text="$t('Enable SIP capture')" :true-value="true"
                        :false-value="false" :disabled="!canEdit"
                        :description="$t('Capture is disabled until this setting is saved with at least one SIP profile selected.')" />

                    <SelectElement name="transport" :label="$t('Transport')" :items="transportOptions"
                        :native="false" :search="false" :floating="false" :strict="true" :disabled="!canEdit"
                        :columns="{ sm: { container: 4 } }"
                        :description="$t('UDP is the normal choice for a collector on a trusted monitoring network.')" />

                    <TextElement name="collector_host" :label="$t('Collector host')" input-type="search"
                        autocomplete="off" :floating="false" :disabled="!canEdit"
                        :placeholder="$t('IP address or hostname')" :columns="{ sm: { container: 8 } }"
                        :description="$t('The HOMER or HEP collector address. Do not include a protocol or port.')" />

                    <TextElement name="collector_port" :label="$t('Collector port')" inputmode="numeric"
                        :floating="false" :disabled="!canEdit" :columns="{ sm: { container: 4 } }"
                        :description="$t('HEP commonly listens on port 9060.')" />

                    <StaticElement name="server_hostname" tag="div" :columns="{ sm: { container: 4 } }">
                        <template #default>
                            <div class="text-sm">
                                <p class="font-medium text-gray-700">{{ $t('Current server') }}</p>
                                <p class="mt-2 break-all font-mono text-base text-gray-900">{{ settings.server_hostname }}</p>
                                <p class="mt-1 text-gray-500">{{ $t('The FreeSWITCH hostname used for this server-specific setting.') }}</p>
                            </div>
                        </template>
                    </StaticElement>

                    <StaticElement name="capture_id_display" tag="div" :columns="{ sm: { container: 4 } }">
                        <template #default>
                            <div class="text-sm">
                                <p class="font-medium text-gray-700">{{ $t('Capture ID') }}</p>
                                <p class="mt-2 text-base text-gray-900">
                                    {{ captureId ?? $t('Assigned when saved') }}
                                </p>
                                <p class="mt-1 text-gray-500">
                                    {{ captureIdConfigured
                                        ? $t("This server's stable numeric identifier at the HEP collector.")
                                        : $t('A random numeric ID will be assigned to this server when you save.') }}
                                </p>
                            </div>
                        </template>
                    </StaticElement>

                    <StaticElement name="hep_version" tag="div" :columns="{ sm: { container: 4 } }">
                        <template #default>
                            <div class="text-sm">
                                <p class="font-medium text-gray-700">{{ $t('HEP version') }}</p>
                                <p class="mt-2 text-base text-gray-900">3</p>
                                <p class="mt-1 text-gray-500">{{ $t('FS PBX uses HEP 3 for this integration.') }}</p>
                            </div>
                        </template>
                    </StaticElement>

                    <TagsElement name="profile_uuids" :label="$t('SIP profiles')" :items="settings.profiles ?? []"
                        :close-on-select="false" :search="true" input-type="search" autocomplete="off"
                        :floating="false" :disabled="!canEdit"
                        :description="$t('Select every profile whose SIP messages should be sent to the collector, usually the internal and external profiles.')" />

                    <ButtonElement v-if="canEdit" name="sip_capture_save" :button-label="$t('Save')"
                        :submits="true" align="right" />
                </FormElements>
            </div>
        </template>
    </Vueform>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import { InformationCircleIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
    route: {
        type: String,
        required: true,
    },
    settings: {
        type: Object,
        default: () => ({}),
    },
    canEdit: {
        type: Boolean,
        default: false,
    },
})

const emit = defineEmits(['error', 'success'])
const form$ = ref(null)
const captureId = ref(props.settings.capture_id ?? null)
const captureIdConfigured = ref(props.settings.capture_id_configured ?? false)
const transportOptions = [
    { value: 'udp', label: 'UDP' },
    { value: 'tcp', label: 'TCP' },
]

onMounted(() => {
    form$.value.update({
        enabled: props.settings.enabled ?? false,
        transport: props.settings.transport ?? 'udp',
        collector_host: props.settings.collector_host ?? '',
        collector_port: props.settings.collector_port ?? 9060,
        profile_uuids: props.settings.profile_uuids ?? [],
    })
    form$.value.clean()
})

const submitForm = async (FormData, form) => {
    return await form.$vueform.services.axios.put(props.route, form.requestData)
}

const handleResponse = (response, form) => {
    Object.values(form.elements$).forEach((element) => element.messageBag?.clear())

    Object.entries(response?.data?.errors ?? {}).forEach(([name, messages]) => {
        const field = name.startsWith('profile_uuids.') ? 'profile_uuids' : name
        form.el$(field)?.messageBag.append(messages[0])
    })
}

const handleSuccess = (response, form) => {
    form.clean()
    captureId.value = response.data.capture_id ?? captureId.value
    captureIdConfigured.value = true
    const type = response.data.runtime_synchronized === false ? 'error' : 'success'
    emit('success', type, response.data.messages)
}

const handleError = (error, details, form) => {
    form.messageBag.clear()

    if (details.type === 'submit') {
        emit('error', error)
        return
    }

    form.messageBag.append('Could not submit the SIP capture settings.')
}
</script>
