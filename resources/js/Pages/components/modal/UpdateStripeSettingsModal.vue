<template>
    <TransitionRoot as="div" :show="show">
        <Dialog as="div" class="relative z-10" @close="emit('close')">
            <TransitionChild as="div" enter="ease-out duration-300" enter-from="opacity-0" enter-to="opacity-100"
                leave="ease-in duration-200" leave-from="opacity-100" leave-to="opacity-0">
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" />
            </TransitionChild>
            <div class="fixed inset-0 z-10 w-screen overflow-y-auto">
                <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
                    <TransitionChild as="div" enter="ease-out duration-300"
                        enter-from="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                        enter-to="opacity-100 translate-y-0 sm:scale-100" leave="ease-in duration-200"
                        leave-from="opacity-100 translate-y-0 sm:scale-100"
                        leave-to="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95">

                        <DialogPanel
                            class="relative transform overflow-hidden rounded-lg bg-white text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-xl">

                            <!-- header -->
                            <div class="flex items-start justify-between border-b border-gray-100 px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <span class="flex h-9 w-9 items-center justify-center rounded-md bg-[#635bff]/10">
                                        <CreditCardIcon class="h-5 w-5 text-[#635bff]" />
                                    </span>
                                    <div>
                                        <h3 class="text-base font-semibold text-gray-900">Stripe settings</h3>
                                        <p class="text-xs text-gray-500">Connect your Stripe account for billing &amp; quotes.</p>
                                    </div>
                                </div>
                                <button type="button" class="rounded-md p-1 text-gray-400 hover:bg-gray-100" @click="emit('close')">
                                    <XMarkIcon class="h-5 w-5" />
                                </button>
                            </div>

                            <Vueform ref="form$" :endpoint="submitForm" @success="handleSuccess" @error="handleError"
                                @response="handleResponse" :display-errors="false" class="px-6 py-5" :default="defaults">

                                <HiddenElement name="uuid" :meta="true" />

                                <!-- enable + mode -->
                                <ToggleElement name="status" text="Gateway enabled" true-value="true" false-value="false" />
                                <RadiogroupElement name="sandbox" label="Mode" view="tabs" :items="{ true: 'Test', false: 'Live' }"
                                    description="Test uses sandbox keys and never charges a real card." :rules="['required']" />

                                <!-- show secrets toggle -->
                                <StaticElement name="reveal_secrets">
                                    <label class="flex cursor-pointer items-center gap-2 text-xs font-medium text-gray-500">
                                        <input type="checkbox" v-model="showSecrets" class="h-3.5 w-3.5 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                                        Show secret values
                                    </label>
                                </StaticElement>

                                <!-- TEST keys -->
                                <StaticElement name="test_keys_heading" tag="p"
                                    content="Test API keys" :conditions="[['sandbox', 'true']]"
                                    class="text-xs font-semibold uppercase tracking-wide text-gray-400" />
                                <TextElement name="sandbox_secret_key" label="Secret key"
                                    :input-type="showSecrets ? 'text' : 'password'" autocomplete="off" :floating="false"
                                    placeholder="sk_test_…" :description="hint('sandbox_secret_key', 'sk_test_…')"
                                    :conditions="[['sandbox', 'true']]" />
                                <TextElement name="sandbox_publishable_key" label="Publishable key (optional)"
                                    :floating="false" placeholder="pk_test_…" :conditions="[['sandbox', 'true']]" />

                                <!-- LIVE keys -->
                                <StaticElement name="live_keys_heading" tag="p"
                                    content="Live API keys" :conditions="[['sandbox', 'false']]"
                                    class="text-xs font-semibold uppercase tracking-wide text-gray-400" />
                                <TextElement name="live_mode_secret_key" label="Secret key"
                                    :input-type="showSecrets ? 'text' : 'password'" autocomplete="off" :floating="false"
                                    placeholder="sk_live_…" :description="hint('live_mode_secret_key', 'sk_live_…')"
                                    :conditions="[['sandbox', 'false']]" />
                                <TextElement name="live_mode_publishable_key" label="Publishable key (optional)"
                                    :floating="false" placeholder="pk_live_…" :conditions="[['sandbox', 'false']]" />

                                <!-- test connection -->
                                <StaticElement name="test_connection">
                                    <div class="space-y-2 rounded-md bg-gray-50 p-3 ring-1 ring-gray-200">
                                        <div class="flex items-center gap-3">
                                            <button type="button" :disabled="testing"
                                                class="inline-flex shrink-0 items-center gap-1.5 rounded-md bg-white px-3 py-1.5 text-sm font-semibold text-gray-700 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 disabled:opacity-50"
                                                @click="runTest">
                                                <ArrowPathIcon v-if="testing" class="h-4 w-4 animate-spin" />
                                                <BoltIcon v-else class="h-4 w-4" />
                                                {{ testing ? 'Testing…' : 'Test connection' }}
                                            </button>
                                            <span v-if="!testResult" class="text-xs text-gray-400">Verifies the key against Stripe before you save.</span>
                                        </div>
                                        <div v-if="testResult" :class="['flex items-start gap-1.5 text-sm font-medium', testResult.ok ? 'text-green-600' : 'text-red-600']">
                                            <CheckCircleIcon v-if="testResult.ok" class="mt-0.5 h-4 w-4 shrink-0" />
                                            <XCircleIcon v-else class="mt-0.5 h-4 w-4 shrink-0" />
                                            <span class="min-w-0 break-all">{{ testResult.message }}</span>
                                        </div>
                                    </div>
                                </StaticElement>

                                <!-- webhook -->
                                <StaticElement name="webhook_heading" tag="p" content="Webhook"
                                    class="text-xs font-semibold uppercase tracking-wide text-gray-400" />
                                <StaticElement name="webhook_endpoint">
                                    <div class="text-xs text-gray-500">
                                        Add this endpoint in Stripe → Developers → Webhooks:
                                        <div class="mt-1 flex items-center gap-2">
                                            <code class="flex-1 truncate rounded bg-gray-100 px-2 py-1 text-gray-700">{{ webhookUrl }}</code>
                                            <button type="button" class="rounded-md px-2 py-1 text-xs font-semibold text-indigo-600 ring-1 ring-inset ring-indigo-200 hover:bg-indigo-50" @click="copyWebhook">
                                                {{ copied ? 'Copied' : 'Copy' }}
                                            </button>
                                        </div>
                                    </div>
                                </StaticElement>
                                <TextElement name="webhook_secret" label="Secret"
                                    :input-type="'text'" autocomplete="off" :floating="false"
                                    placeholder="whsec_…" description="Used to verify incoming Stripe webhooks." />

                                <!-- actions -->
                                <ButtonElement @click="emit('close')" name="cancel" button-label="Cancel" :secondary="true"
                                    :columns="{ container: 6 }" :full="true" />
                                <ButtonElement name="submit" button-label="Save settings" :submits="true" :full="true"
                                    align="center" :columns="{ container: 6 }" />
                            </Vueform>
                        </DialogPanel>
                    </TransitionChild>
                </div>
            </div>
        </Dialog>
    </TransitionRoot>
</template>

<script setup>
import { computed, ref } from "vue";
import { Dialog, DialogPanel, TransitionChild, TransitionRoot } from '@headlessui/vue'
import { XMarkIcon } from '@heroicons/vue/20/solid'
import { CreditCardIcon, BoltIcon, ArrowPathIcon, CheckCircleIcon, XCircleIcon } from '@heroicons/vue/24/outline'

const emit = defineEmits(['close', 'confirm', 'success', 'error', 'refresh-data'])

const form$ = ref(null)
const showSecrets = ref(false)
const testing = ref(false)
const testResult = ref(null)
const copied = ref(false)

const props = defineProps({
    show: Boolean,
    settings: Object,
    uuid: String,
    isEnabled: Boolean,
    route: String,
    testRoute: String,
});

// API secret keys are never pre-filled — blank means "keep the saved value".
// The webhook signing secret IS shown so it can be reviewed/copied.
const defaults = computed(() => ({
    uuid: props.uuid ?? null,
    status: props.isEnabled ? 'true' : 'false',
    sandbox: String(props.settings?.sandbox ?? 'true'),
    sandbox_publishable_key: props.settings?.sandbox_publishable_key ?? null,
    live_mode_publishable_key: props.settings?.live_mode_publishable_key ?? null,
    webhook_secret: props.settings?.webhook_secret ?? null,
}));

const webhookUrl = `${window.location.origin}/webhook/stripe`;

const maskTail = (v) => (v && v.length > 4 ? '••••' + v.slice(-4) : (v ? '••••' : null));
const hint = (key, example) => {
    const saved = maskTail(props.settings?.[key]);
    return saved ? `Saved: ${saved} — leave blank to keep it` : `Enter your ${example} key`;
};

const copyWebhook = async () => {
    try { await navigator.clipboard.writeText(webhookUrl); copied.value = true; setTimeout(() => (copied.value = false), 1500); } catch (e) { /* ignore */ }
};

const runTest = async () => {
    testing.value = true;
    testResult.value = null;
    try {
        const f = form$.value;
        const mode = (f?.el$('sandbox')?.value === 'true') ? 'test' : 'live';
        const secretEl = mode === 'test' ? f?.el$('sandbox_secret_key') : f?.el$('live_mode_secret_key');
        const secret_key = secretEl?.value || null;
        const { data } = await axios.post(props.testRoute, { uuid: props.uuid || null, mode, secret_key });
        testResult.value = data;
    } catch (e) {
        testResult.value = { ok: false, message: e?.response?.data?.message || 'Test failed.' };
    } finally {
        testing.value = false;
    }
};

const submitForm = async (FormData, form$) => {
    // form$.requestData excludes conditional (hidden-mode) elements; the
    // backend preserves any saved secret left blank.
    return await form$.$vueform.services.axios.put(props.route, form$.requestData);
};

function clearErrorsRecursive(el$) {
    el$.messageBag?.clear()
    if (el$.children$) {
        Object.values(el$.children$).forEach(childEl$ => clearErrorsRecursive(childEl$))
    }
}

const handleResponse = (response, form$) => {
    Object.values(form$.elements$).forEach(el$ => clearErrorsRecursive(el$))
    if (response?.data?.errors) {
        Object.keys(response.data.errors).forEach((elName) => {
            if (form$.el$(elName)) {
                form$.el$(elName).messageBag.append(response.data.errors[elName][0])
            }
        })
    }
}

const handleSuccess = (response) => {
    emit('success', response.data.messages);
    emit('close');
    emit('refresh-data');
}

const handleError = (error, details, form$) => {
    form$.messageBag.clear()
    switch (details.type) {
        case 'prepare':
            form$.messageBag.append('Could not prepare form')
            break
        case 'submit':
            emit('error', error);
            break
        case 'cancel':
            form$.messageBag.append('Request cancelled')
            break
        case 'other':
            form$.messageBag.append('Couldn\'t submit form')
            break
    }
}
</script>

<style scoped>
:global(div[data-lastpass-icon-root]),
:global(div[data-lastpass-root]) {
    overflow: hidden !important;
    opacity: 0 !important;
    pointer-events: none !important;
}
div[data-lastpass-icon-root] {
    display: none !important
}

div[data-lastpass-root] {
    display: none !important
}
</style>