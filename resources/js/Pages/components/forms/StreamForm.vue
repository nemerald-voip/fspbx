<template>
    <AddEditItemModal :show="show" :loading="loading" :header="header" custom-class="sm:max-w-2xl" @close="emit('close')">
        <template #modal-body>
            <Vueform @mounted="(form) => form.disableValidation()" @submit="clearServerFormErrors" v-if="!loading" :key="options.item?.stream_uuid || 'create'" :default="defaults"
                :endpoint="submit" @success="success" @response="response" @error="error" :display-errors="false">
                <template #empty>
                    <FormElements>
                        <TextElement name="stream_name" :label="$t('Name')" :floating="false" />
                        <TextElement name="stream_location" :label="$t('Location')" :floating="false"
                            placeholder="shouts://radio.example.com/live.mp3"
                            :description="$t('Enter the direct MP3 audio endpoint using shout:// for HTTP or shouts:// for HTTPS.')" />
                        <StaticElement name="location_help">
                            <details class="text-sm text-gray-600">
                                <summary class="cursor-pointer font-semibold text-gray-900">{{ $t('How to enter a playable location') }}</summary>
                                <ol class="mt-3 list-decimal space-y-2 pl-5">
                                    <li>{{ $t('Get the direct MP3 stream URL from the provider. A radio website, embedded player, playlist (.m3u/.pls), HLS (.m3u8), AAC, or Ogg stream will not work here. If you have a playlist, open it as text and use its MP3 stream URL.') }}</li>
                                    <li>{{ $t('Replace only the scheme. Keep the hostname, port, path, and query string exactly as supplied. Encode spaces as %20. Do not put http:// or https:// after shout://.') }}
                                        <div class="mt-2 space-y-2 break-all font-mono text-xs">
                                            <p>http://radio.example.com:8000/live.mp3<br />→ shout://radio.example.com:8000/live.mp3</p>
                                            <p>https://radio.example.com/live<br />→ shouts://radio.example.com/live</p>
                                        </div>
                                    </li>
                                    <li>{{ $t('The endpoint must return MP3 audio directly, even if its path has no .mp3 extension. Use the final audio URL rather than a redirect or a page requiring browser cookies or sign-in.') }}</li>
                                    <li>{{ $t('An administrator must start mod_shout on the FreeSWITCH Modules page and enable it for automatic loading after restart. The FreeSWITCH server must be able to resolve and reach the stream host and port.') }}</li>
                                    <li>{{ $t('Save with Enabled on, then select this stream in the destination’s Music on Hold field and save that destination. Make a test call and place it on hold to verify audio from FreeSWITCH.') }}</li>
                                </ol>
                                <p class="mt-3">{{ $t('Stream locations are copied into destination settings. Changing or deleting this entry does not replace locations already saved elsewhere; reselect and save the stream in each destination that uses it. Disabling hides it from new selections.') }}</p>
                            </details>
                        </StaticElement>
                        <SelectElement v-if="options.domains?.length" name="domain_uuid" :label="$t('Account')"
                            :items="options.domains" :native="false" :floating="false" :can-clear="false"
                            :description="$t('Global streams are available to every account.')" />
                        <ToggleElement name="stream_enabled" :text="$t('Enabled')" true-value="true" false-value="false" />
                        <TextareaElement name="stream_description" :label="$t('Description')" :floating="false" />
                        <ButtonElement name="submit" :button-label="$t('Save')" :submits="true" align="right" />
                    </FormElements>
                </template>
            </Vueform>
        </template>
    </AddEditItemModal>
</template>

<script setup>
import { clearServerFormErrors } from '../../../composables/serverFormErrors.js';
import { computed } from 'vue';
import AddEditItemModal from '../modal/AddEditItemModal.vue';

const props = defineProps({ show: Boolean, loading: Boolean, header: String, options: Object });
const emit = defineEmits(['close', 'success', 'error', 'refresh-data']);
const defaults = computed(() => ({
    ...props.options.item,
    domain_uuid: props.options.item?.domain_uuid ?? '__global__',
}));
const submit = async (_, form) => {
    const values = { ...form.requestData };
    if (props.options.domains?.length) {
        values.domain_uuid = values.domain_uuid === '__global__' ? null : values.domain_uuid;
    } else {
        delete values.domain_uuid;
    }
    const routes = props.options.routes;
    return routes.update_route
        ? form.$vueform.services.axios.put(routes.update_route, values)
        : form.$vueform.services.axios.post(routes.store_route, values);
};
const response = (res, form) => {
    Object.entries(res.data.errors || {}).forEach(([name, messages]) => {
        form.el$(name)?.messageBag.append(messages[0]);
    });
};
const success = (res) => {
    emit('success', 'success', res.data.messages);
    emit('refresh-data');
    emit('close');
};
const error = (err) => emit('error', err);
</script>
