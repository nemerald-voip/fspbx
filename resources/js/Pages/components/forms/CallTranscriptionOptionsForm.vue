<template>
    <Skeleton v-if="isFormLoading" />

    <div v-show="!isFormLoading" class="flex flex-col xl:flex-row">
        <div class="basis-3/4">
            <Vueform ref="form$" :endpoint="submitForm" @success="handleSuccess" @error="handleError"
                @response="handleResponse" :display-errors="false">

                <template #empty>
                    <div class="space-y-6 text-gray-600 bg-gray-50 px-4 py-6 sm:p-6">
                        <FormElements>

                            <HiddenElement name="domain_uuid" :meta="true" />

                            <!-- Header -->
                            <StaticElement name="header" tag="h4" :content="'Call Transcription Options'" />

                            <StaticElement v-if="isInheriting" name="inherited_notice" tag="div" :add-classes="{
                                StaticElement: { container: 'rounded-md border border-yellow-200 bg-yellow-50 p-3' }
                            }" :columns="{ lg: { container: 5 } }">
                                <template #default>
                                    <div class="flex items-start gap-3" role="status" aria-live="polite">
                                        <ExclamationTriangleIcon class="size-5 text-yellow-500 shrink-0"
                                            aria-hidden="true" />
                                        <div class="text-sm text-yellow-900">
                                            <p class="font-medium">No custom options set. Your account is using the
                                                system defaults.</p>

                                        </div>

                                    </div>
                                </template>
                            </StaticElement>

                            <GroupElement name="container2" />

                            <!-- Enabled -->
                            <ToggleElement name="enabled" text="Enable call transcriptions" :true-value="true"
                                :false-value="false" :disabled="disableOptions" />

                            <!-- Auto-transcribe -->
                            <ToggleElement name="auto_transcribe" text="Automatically transcribe new calls"
                                :true-value="true" :false-value="false" :disabled="disableOptions" />

                            <!-- Email Transcription -->
                            <ToggleElement name="email_transcription" text="Automatically email call transcripts"
                                :true-value="true" :false-value="false" :disabled="disableOptions" />

                            <TextElement name="email" label="Email"
                                :columns="{ lg: { wrapper: 5 } }" :conditions="[
                                    ['email_transcription', '==', true]
                                ]" />

                            <!-- Provider -->
                            <SelectElement v-if="!domain_uuid" name="provider_uuid" label="Provider" :search="true"
                                :items="providers" :floating="false" placeholder="Select Provider"
                                :loading="isProvidersLoading" :native="false" input-type="search" autocomplete="off"
                                :clearable="true" :columns="{ lg: { wrapper: 5 } }"
                                description="Choose the default call transcription provider." />

                            <!-- <GroupElement name="container" /> -->

                            <!-- <ButtonElement v-if="isDomainInheriting && !isOverride" name="showOptionsButton"
                                :secondary="true" button-label="Override Defaults" @click="startOverride" /> -->



                            <!-- Submit -->
                            <!-- <ButtonElement v-if="!domain_uuid || isOverride || !isDomainInheriting" name="save"
                                button-label="Save" :submits="true" /> -->


                            <!-- Actions row -->
                            <StaticElement name="actions_row" tag="div" :add-classes="{
                                ElementLayout: { outerWrapper: 'col-span-12 !mb-0' },
                                StaticElement: { container: 'mt-4' }
                            }">
                                <template #default>
                                    <div class="flex justify-start gap-3">

                                        <!-- Override (only when inheriting & not already overriding) -->
                                        <ButtonElement v-if="showOverrideBtn" name="overrideDefaults" :secondary="true"
                                            button-label="Override Defaults" @click="startOverride" />

                                        <!-- Save (system scope OR domain edit OR started override) -->
                                        <ButtonElement v-if="showSaveBtn" name="save" button-label="Save"
                                            :submits="true" />

                                        <!-- Revert to Defaults (only when a saved domain override exists) -->
                                        <ButtonElement v-if="showRevertBtn" name="revertDefaults" :secondary="true"
                                            button-label="Revert to Defaults" @click="revertToDefaults" />

                                        <!-- Cancel (only when user just started override but hasn’t saved yet) -->
                                        <ButtonElement v-if="showCancelBtn" name="cancelOverride" :secondary="true"
                                            button-label="Cancel" @click="cancelOverride" />
                                    </div>
                                </template>
                            </StaticElement>



                        </FormElements>
                    </div>
                </template>
            </Vueform>
        </div>

        <!-- Right rail for help, previews, or saved presets -->
        <div class="basis-1/4 xl:pl-6 mt-8 xl:mt-0">
            <!-- (Optional) You can add contextual help or a live JSON preview here -->
        </div>
    </div>
</template>



<script setup>
import { ref, onMounted, computed } from 'vue'
import Skeleton from "@generalComponents/Skeleton.vue";
import { ExclamationTriangleIcon } from '@heroicons/vue/20/solid'


const props = defineProps({
    domain_uuid: String,
    routes: Object,
})

const emit = defineEmits(['error', 'success']);

const form$ = ref(null)
const providers = ref([])
const policy = ref([])
const isProvidersLoading = ref(false)
const isFormLoading = ref(false)
const isOverride = ref(false)

onMounted(() => {

    getTranscriptionProviders()

    getPolicy()

    // console.log('general')
})

const isInheriting = computed(() =>
    policy.value?.scope === 'system' && !!policy.value?.domain_uuid
)
// “there is a saved domain override row”
const hasDomainOverride = computed(() =>
    policy.value?.scope === 'domain' && !!policy.value?.domain_uuid
)

// buttons logic
const showOverrideBtn = computed(() => isInheriting.value && !isOverride.value)
const showSaveBtn = computed(() =>
    // system page OR (editing domain) OR (started override)
    !props.domain_uuid || hasDomainOverride.value || isOverride.value
)
const showRevertBtn = computed(() => hasDomainOverride.value)              // API delete
const showCancelBtn = computed(() => isOverride.value && isInheriting.value) // UI cancel

const disableOptions = computed(() => {
    // System page: editable
    if (!props.domain_uuid) return false
    // Domain with saved override: editable
    if (hasDomainOverride.value) return false
    // Domain inheriting: disable until they click Override
    return !isOverride.value
})

function startOverride() {
    isOverride.value = true
}

async function revertToDefaults() {
    if (!props.domain_uuid) return
    await axios.delete(props.routes.transcription_policy_route, {
        data: { domain_uuid: props.domain_uuid }
    })
    isOverride.value = false
    await getPolicy()

}

function cancelOverride() {
    isOverride.value = false
    // reset the form to current effective values (still inheriting)
    form$.value.update({
        enabled: policy.value.enabled ?? false,
        provider_uuid: policy.value.provider_uuid ?? null,
        domain_uuid: props.domain_uuid ?? null,
    })
}


const getTranscriptionProviders = async () => {
    isProvidersLoading.value = true
    try {
        const { data } = await axios.get(props.routes.transcription_providers_route)
        providers.value = data
        return data
    } catch (err) {
        emit('error', err);
        providers.value = []
        return []
    } finally {
        isProvidersLoading.value = false
    }
}

const getPolicy = async () => {
    isFormLoading.value = true
    try {
        const { data } = await axios.get(
            props.routes.transcription_policy_route,
            { params: { domain_uuid: props.domain_uuid ?? null } }
        )
        policy.value = data
        form$.value.update(policy.value)
        return data
    } catch (err) {
        emit('error', err);
        policy.value = []
        return []
    } finally {
        isFormLoading.value = false
    }
}

const submitForm = async (FormData, form$) => {
    // Using form$.requestData will EXCLUDE conditional elements and it 
    // will submit the form as Content-Type: application/json . 
    const requestData = form$.requestData

    // console.log(requestData);
    return await form$.$vueform.services.axios.post(props.routes.transcription_policy_store_route, requestData)
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
    if (response.data.errors) {
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

    emit('success', 'success', response.data.messages);

    getPolicy()
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
            emit('error', error);
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


</script>
