<template>
    <div>
        <label :for="inputId" class="block text-sm font-medium text-gray-900">{{ $t('Signing secret') }}</label>
        <div class="mt-1 flex items-center gap-2">
            <input :id="inputId" ref="input$" readonly autocomplete="off" :type="revealed ? 'text' : 'password'"
                :value="secret || '••••••••••••••••••••••••••••••••'"
                class="min-w-0 flex-1 rounded-md border-gray-300 bg-gray-50 font-mono text-sm text-gray-900" />
            <button type="button" :class="buttonClass" :disabled="loading" :aria-pressed="revealed" @click="toggle">
                {{ revealed ? $t('Hide') : $t('Show') }}
            </button>
            <button type="button" :class="buttonClass" :disabled="loading" @click="copy">
                {{ copied ? $t('Copied') : $t('Copy') }}
            </button>
        </div>
        <p v-if="error" class="mt-1 text-sm text-red-600" role="alert">{{ error }}</p>
        <p v-else class="mt-1 text-sm text-gray-600">
            {{ $t('Use it to verify the Signature header. All numbers in this account share it.') }}
        </p>
    </div>
</template>

<script setup>
import { ref, watch } from 'vue';
import axios from 'axios';
import { trans } from '@i18n';

const props = defineProps({
    route: { type: String, required: true },
    domainUuid: { type: String, default: null },
    initialSecret: { type: String, default: null },
});

const buttonClass = 'rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 disabled:opacity-50';
const inputId = `webhook-secret-${Math.random().toString(36).slice(2)}`;
const input$ = ref(null);
const secret = ref(props.initialSecret);
const revealed = ref(false);
const loading = ref(false);
const copied = ref(false);
const error = ref(null);

watch(() => props.initialSecret, value => { if (value) secret.value = value; });

// The server creates the account secret on first use, so nobody has to save settings first.
const load = async () => {
    if (secret.value) return true;
    loading.value = true;
    error.value = null;
    try {
        const { data } = await axios.post(props.route, props.domainUuid ? { domain_uuid: props.domainUuid } : {});
        secret.value = data.signing_secret;
        return true;
    } catch (e) {
        error.value = e.response?.data?.message || trans('Could not load the signing secret.');
        return false;
    } finally {
        loading.value = false;
    }
};

const toggle = async () => {
    if (revealed.value) {
        revealed.value = false;
    } else if (await load()) {
        revealed.value = true;
    }
};

const copy = async () => {
    if (!(await load())) return;
    try {
        await navigator.clipboard.writeText(secret.value);
        copied.value = true;
        setTimeout(() => { copied.value = false; }, 2000);
    } catch {
        // Clipboard access can be blocked; reveal and select the text so it can be copied by hand.
        revealed.value = true;
        setTimeout(() => input$.value?.select());
    }
};
</script>
