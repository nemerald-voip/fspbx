<template>
    <PageWithSideMenu title="System Settings" :navigation="navigation" :pages="pages" :header-icon="Cog6ToothIcon"
        :initial-menu-option="initialMenuOption" @update-selected-menu-option="handleUpdateSelectedMenuOption">

        <template #default="{ selectedMenuOption }">
            <!-- GENERAL: system-wide default settings (default_settings) -->
            <section v-show="selectedMenuOption === 'general'">
                <Vueform ref="generalForm$" :endpoint="submitForm" @success="handleSuccess" @error="handleError"
                    @response="handleResponse" :display-errors="false">
                    <template #empty>
                        <div class="lg:grid lg:grid-cols-12 lg:gap-x-5">
                            <div class="lg:col-span-12">
                                <FormElements>
                                    <StaticElement name="general_tab_label" tag="h4" content="General"
                                        description="System-wide defaults. Every account inherits these unless it sets its own." />

                                    <!-- Schema-driven defaults, grouped. Add a field in
                                         SystemSettingsSchema and it renders here. -->
                                    <template v-for="group in settingGroups" :key="group">
                                        <StaticElement :name="`settings_group_${group}`" tag="h4" :content="group" />
                                        <template v-for="field in settingsByGroup[group]" :key="field.key">
                                            <SelectElement v-if="field.type === 'select'" :name="field.key"
                                                :label="field.label" :items="settings_options[field.options] ?? []"
                                                :groups="!!field.grouped" :search="!!field.searchable" :native="false"
                                                input-type="search" autocomplete="off" :placeholder="field.placeholder"
                                                :floating="false" :strict="false" :info="field.info || undefined"
                                                :disabled="!permissions?.default_setting_edit"
                                                :columns="{ sm: { container: 6 } }" />
                                            <TextElement v-else-if="field.type === 'text'" :name="field.key"
                                                :label="field.label" :placeholder="field.placeholder" :floating="false"
                                                :info="field.info || undefined"
                                                :disabled="!permissions?.default_setting_edit"
                                                :columns="{ sm: { container: 6 } }" />
                                            <ToggleElement v-else-if="field.type === 'toggle'" :name="field.key"
                                                :text="field.label" :disabled="!permissions?.default_setting_edit" />
                                        </template>
                                    </template>

                                    <ButtonElement v-if="permissions?.default_setting_edit" name="general_submit"
                                        button-label="Save" :submits="true" align="right" />
                                </FormElements>
                            </div>
                        </div>
                    </template>
                </Vueform>
            </section>

            <section v-show="selectedMenuOption === 'payment_gateways'">
                <Vueform ref="form$" :endpoint="submitForm" @success="handleSuccess" @error="handleError"
                    @response="handleResponse" :display-errors="false">
                    <template #empty>
                        <div class="lg:grid lg:grid-cols-12 lg:gap-x-5">
                            <div class="lg:col-span-12">
                                <FormElements>
                                    <StaticElement name="payment_gateways_tab_label" tag="h4" content="Payment Gateways"
                                        description="Manage Payment Providers" />
                                    <ListElement name="gateways" :controls="{ add: false, remove: false }"
                                        :add-classes="{ ListElement: { listItem: 'bg-white p-4 mb-4 rounded-lg shadow-md' } }">
                                        <template #default="{ index }">
                                            <ObjectElement :name="index">
                                                <StaticElement name="name" tag="p" :content="(el$) => {
                                                    return el$.form$.el$('gateways').value[index].name
                                                }" :columns="{ container: 6, }"
                                                    :attrs="{ class: 'text-base font-semibold' }">
                                                    <template #after="{ el$ }">
                                                        <Badge v-if="el$.form$.el$('gateways').value[index].is_enabled"
                                                            class="mt-1" :text="'Activated'"
                                                            :backgroundColor="'bg-green-50'"
                                                            :textColor="'text-green-700'"
                                                            :ringColor="'ring-green-600/20'" />
                                                        <Badge v-else class="mt-1" :text="'Disabled'"
                                                            :backgroundColor="'bg-rose-50'" :textColor="'text-rose-700'"
                                                            :ringColor="'ring-rose-600/20'" />
                                                    </template>
                                                </StaticElement>
                                                <HiddenElement name="is_enabled" :meta="true" />
                                                <ButtonElement name="gateway_activate" button-label="Configure"
                                                    @click="handlePaymentGatewaySettingsClick(index)" :columns="{
                                                        container: 6,
                                                    }" align="right" :conditions="[
                                                        ['gateways.*.is_enabled', false]
                                                    ]" />
                                                <ButtonElement name="gwateway_deactivate" button-label="Deactivate"
                                                    @click="handlePaymentGatewayDeactivateClick(index)"
                                                    :secondary="true" :columns="{
                                                        container: 6,
                                                    }" align="right" :conditions="[
                                                        ['gateways.*.is_enabled', true]
                                                    ]" />
                                            </ObjectElement>
                                        </template>
                                    </ListElement>
                                </FormElements>
                            </div>
                        </div>
                    </template>
                </Vueform>
            </section>

            <section v-if="selectedMenuOption === 'sip_capture'">
                <SipCaptureSettingsForm :route="routes.sip_capture_update" :settings="sip_capture"
                    :can-edit="permissions?.sip_capture_edit" @error="handleErrorResponse"
                    @success="showNotification" />
            </section>

            <!--  Transcription - General Settings -->
            <section v-if="selectedMenuOption === 'transcription_options'">
                <CallTranscriptionOptionsForm :routes="routes" @error="handleErrorResponse" @success="showNotification"/>
            </section>

            <!--  ASSEMBLY AI -->
            <section v-if="selectedMenuOption === 'assemblyai'">
                <AssemblyAiForm :routes="routes" @error="handleErrorResponse" @success="showNotification"/>

            </section>
        </template>

        <template #overlays>
            <Notification :show="notificationShow" :type="notificationType" :messages="notificationMessages"
                @update:show="hideNotification" />

            <UpdateStripeSettingsModal :settings="gatewaySettings" :uuid="gatewayUuid" :is-enabled="gatewayEnabled"
                :show="showStripeSettingsModal" :route="routes.payment_gateway_update" :test-route="routes.payment_gateway_test"
                @refresh-data="getPaymentGatewaysData" @close="showStripeSettingsModal = false" />

        </template>

    </PageWithSideMenu>


</template>

<script setup>
import { ref, computed, markRaw, onMounted } from 'vue'
import PageWithSideMenu from '../Layouts/PageWithSideMenu.vue'
import Notification from "./components/notifications/Notification.vue";
import UpdateStripeSettingsModal from "./components/modal/UpdateStripeSettingsModal.vue";
import Badge from "@generalComponents/Badge.vue";
import { CreditCardIcon, Cog6ToothIcon } from '@heroicons/vue/24/outline'
import GraphicEqIcon from "@icons/GraphicEqIcon.vue"
import AssemblyAiForm from "./components/forms/AssemblyAiForm.vue"
import CallTranscriptionOptionsForm from "./components/forms/CallTranscriptionOptionsForm.vue"
import SipCaptureSettingsForm from "./components/forms/SipCaptureSettingsForm.vue"
import { AdjustmentsVerticalIcon, SignalIcon } from "@heroicons/vue/24/outline";


const props = defineProps({
    routes: Object,
    permissions: Object,
    // Declarative system-default fields (SystemSettingsSchema), the option
    // lists they reference, and the current global default values. The
    // General tab renders and saves from these.
    settings_schema: {
        type: Array,
        default: () => [],
    },
    settings_options: {
        type: Object,
        default: () => ({}),
    },
    settings_values: {
        type: Object,
        default: () => ({}),
    },
    sip_capture: {
        type: Object,
        default: () => ({}),
    },
})

const form$ = ref(null)
const generalForm$ = ref(null)
const showStripeSettingsModal = ref(false);
const gatewaySettings = ref(null);
const gatewayUuid = ref(null);
const gatewayEnabled = ref(null);
const navigation = ref([])
const initialMenuOption = ref(null)

// Group the schema fields for grouped rendering in the General tab.
const settingsByGroup = computed(() => {
    const map = {}
    ;(props.settings_schema ?? []).forEach((field) => {
        ;(map[field.group] ??= []).push(field)
    })
    return map
})
const settingGroups = computed(() => Object.keys(settingsByGroup.value))

const pages = [
    { name: 'Dashboard', href: props.routes.dashboard_route, current: true },
    { name: 'System Settings', href: '#', current: true },
]

const handleUpdateSelectedMenuOption = (key) => {
    if (key === 'payment_gateways') getPaymentGatewaysData()
}

const notificationType = ref(null);
const notificationShow = ref(null);
const notificationMessages = ref(null);

onMounted(() => {
    navigation.value = [] // reset if needed

    if (props.permissions?.default_setting_view) {
        navigation.value.push({ key: 'general', name: 'General', icon: Cog6ToothIcon })
    }

    if (props.permissions?.payment_gateways_view) {
        navigation.value.push({ key: 'payment_gateways', name: 'Payment Gateways', icon: CreditCardIcon })
    }

    if (props.permissions?.sip_capture_view) {
        navigation.value.push({ key: 'sip_capture', name: 'SIP Capture', icon: SignalIcon })
    }

    if (props.permissions?.call_transcription_settings_view) {
        navigation.value.push({
            key: 'call_transcription',
            name: 'Call Transcription',
            icon: markRaw(GraphicEqIcon),
            children: [
                { key: 'transcription_options', name: 'Options', icon: markRaw(AdjustmentsVerticalIcon) },
                { key: 'assemblyai', name: 'AssemblyAI', icon: markRaw(GraphicEqIcon) }
            ],
        })
    }

    if (navigation.value.length) {
        initialMenuOption.value = navigation.value[0].key
        // handleUpdateSelectedMenuOption(navigation.value[0].key)
    }

    // Populate the General tab with the current global default values.
    if (generalForm$.value) {
        const settingValues = Object.fromEntries(
            (props.settings_schema ?? []).map((field) => [field.key, props.settings_values?.[field.key] ?? null])
        )
        generalForm$.value.update(settingValues)
        generalForm$.value.clean()
    }
})


const getPaymentGatewaysData = async () => {
    try {
        const response = await form$.value.$vueform.services.axios.get(props.routes.payment_gateways)
        form$.value.update({
            gateways: response.data
        })
        form$.value.clean()
    }
    catch (err) {
        console.error('Failed to load gateways:', err)
        return []                    // return an empty array on error
    }
}


const handlePaymentGatewaySettingsClick = (index) => {
    if (form$.value.el$('gateways').value[index].slug == 'stripe') {
        showStripeSettingsModal.value = true;
        gatewaySettings.value = form$.value.el$('gateways').value[index].settings
        gatewayUuid.value = form$.value.el$('gateways').value[index].uuid
        gatewayEnabled.value = form$.value.el$('gateways').value[index].is_enabled
    }
}

const handlePaymentGatewayDeactivateClick = (index) => {

    axios.post(props.routes.payment_gateway_deactivate,
        { 'uuid': form$.value.el$('gateways').value[index].uuid },
    )
        .then((response) => {
            showNotification('success', response.data.messages);
        }).catch((error) => {
            handleErrorResponse(error);
        }).finally(() => {
            getPaymentGatewaysData()
        });
}

const submitForm = async (FormData, form$) => {
    // form$.requestData EXCLUDES conditional elements and submits as JSON.
    const requestData = form$.requestData

    // Collect the schema fields into a clean {key: value} map. The backend
    // (SystemSettingsController::applyDefaults) maps each key to its
    // default_settings row via the schema and updates it in place.
    const settings = {}
    ;(props.settings_schema ?? []).forEach((field) => {
        settings[field.key] = requestData[field.key] ?? null
    })

    return await form$.$vueform.services.axios.put(props.routes.settings_update, { settings })
};

function clearErrorsRecursive(el$) {
    // clear this element’s errors
    el$.messageBag?.clear()

    // if it has child elements, recurse into each
    if (el$.children$) {
        Object.values(el$.children$).forEach(childEl$ => {
            clearErrorsRecursive(childEl$)
        })
    }
}

const handleResponse = (response, form$) => {
    // Clear form including nested elements 
    Object.values(form$.elements$).forEach(el$ => {
        clearErrorsRecursive(el$)
    })

    // Display custom errors for elements
    if (response?.data?.errors) {
        Object.keys(response.data.errors).forEach((elName) => {
            if (form$.el$(elName)) {
                form$.el$(elName).messageBag.append(response.data.errors[elName][0])
            }
        })
    }
}

const handleSuccess = (response, form$) => {
    // console.log(response) // axios response
    // console.log(response.status) // HTTP status code
    // console.log(response.data) // response data

    showNotification('success', response.data.messages);

}

const handleError = (error, details, form$) => {
    form$.messageBag.clear() // clear message bag

    switch (details.type) {
        // Error occured while preparing elements (no submit happened)
        case 'prepare':
            console.log(error) // Error object

            form$.messageBag.append('Could not prepare form')
            break

        // Error occured because response status is outside of 2xx
        case 'submit':
            handleErrorResponse(error);
            console.log(error) // AxiosError object
            // console.log(error.response) // axios response
            // console.log(error.response.status) // HTTP status code
            // console.log(error.response.data) // response data

            // console.log(error.response.data.errors)


            break

        // Request cancelled (no response object)
        case 'cancel':
            console.log(error) // Error object

            form$.messageBag.append('Request cancelled')
            break

        // Some other errors happened (no response object)
        case 'other':
            console.log(error) // Error object

            form$.messageBag.append('Couldn\'t submit form')
            break
    }
}


const handleErrorResponse = (error) => {
    if (error.response) {
        // The request was made and the server responded with a status code
        // that falls out of the range of 2xx
        // console.log(error.response.data);
        showNotification('error', error.response.data.errors || { request: [error.message] });
    } else if (error.request) {
        // The request was made but no response was received
        // `error.request` is an instance of XMLHttpRequest in the browser and an instance of
        // http.ClientRequest in node.js
        showNotification('error', { request: [error.request] });
        console.log(error.request);
    } else {
        // Something happened in setting up the request that triggered an Error
        showNotification('error', { request: [error.message] });
        console.log(error.message);
    }
}

const hideNotification = () => {
    notificationShow.value = false;
    notificationType.value = null;
    notificationMessages.value = null;
}

const showNotification = (type, messages = null) => {
    notificationType.value = type;
    notificationMessages.value = messages;
    notificationShow.value = true;
}


</script>
