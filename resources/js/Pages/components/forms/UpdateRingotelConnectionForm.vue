<template>
    <div class="lg:grid lg:grid-cols-12 lg:gap-x-5">
        <aside class="px-2 py-6 sm:px-6 lg:col-span-3 lg:px-0 lg:py-0">
            <nav class="space-y-1">
                <a v-for="item in options.conn_navigation" :key="item.name" href="#"
                    :class="[activeTab === item.slug ? 'bg-gray-200 text-indigo-700 hover:bg-gray-100 hover:text-indigo-700' : 'text-gray-900 hover:bg-gray-200 hover:text-gray-900', 'group flex items-center rounded-md px-3 py-2 text-sm font-medium']"
                    @click.prevent="setActiveTab(item.slug)" :aria-current="item.current ? 'page' : undefined">
                    <component :is="iconComponents[item.icon]"
                        :class="[item.current ? 'text-indigo-500 group-hover:text-indigo-500' : 'text-gray-400 group-hover:text-gray-500', '-ml-1 mr-3 h-6 w-6 flex-shrink-0']"
                        aria-hidden="true" />
                    <span class="truncate">{{ item.name }}</span>
                    <ExclamationCircleIcon
                        v-if="((errors?.connection_name || errors?.domain || errors?.registration_ttl || errors?.max_registrations) && item.slug === 'settings')"
                        class="ml-2 h-5 w-5 text-red-500" aria-hidden="true" />

                </a>
            </nav>
        </aside>

        <div class="space-y-6 sm:px-6 lg:col-span-9 lg:px-0">
            <form @submit.prevent="submitForm">
                <div v-if="activeTab === 'settings'">
                    <div class="shadow sm:rounded-md">
                        <div class="space-y-6 bg-gray-100 px-4 py-6 sm:p-6">
                            <div class="flex justify-between items-center">
                                <h3 class="text-base font-semibold leading-6 text-gray-900">Connection Details</h3>

                                <!-- <Toggle label="Status" v-model="" /> -->

                                <!-- <p class="mt-1 text-sm text-gray-500"></p> -->
                            </div>

                            <div class="grid grid-cols-6 gap-6">
                                <div class="col-span-6 sm:col-span-3">
                                    <LabelInputRequired target="connection_name" label="Connection Name"
                                        class="truncate mb-1" />
                                    <InputField v-model="form.connection_name" type="text" name="connection_name"
                                        id="connection_name" class="mt-1" :error="!!errors?.connection_name"
                                        :placeholder="'Enter connection name'" />
                                    <div v-if="errors?.connection_name" class="mt-2 text-xs text-red-600">
                                        {{ errors.connection_name[0] }}
                                    </div>
                                </div>



                                <div class="col-span-6 sm:col-span-3">
                                    <LabelInputRequired label="Protocol" class="truncate mb-1" />
                                    <ComboBox :options="options.protocols" :search="true" :placeholder="'Select protocol'"
                                        :error="errors?.protocol && errors.protocol.length > 0"
                                        :selectedItem="props.selectedConnection.provision.protocol"
                                        @update:model-value="handleUpdateProtocolField" />
                                    <div v-if="errors?.protocol" class="mt-2 text-xs text-red-600">
                                        {{ errors.protocol[0] }}
                                    </div>

                                </div>

                                <div class="col-span-6 sm:col-span-3">
                                    <LabelInputRequired target="domain" label="Domain or IP Address"
                                        class="truncate mb-1" />
                                    <InputField v-model="form.domain" type="text" name="domain"
                                        :placeholder="'Enter domain or IP'" id="domain" class="mt-1"
                                        :error="!!errors?.domain" />
                                    <div v-if="errors?.domain" class="mt-2 text-xs text-red-600">
                                        {{ errors.domain[0] }}
                                    </div>
                                </div>

                                <div class="col-span-6 sm:col-span-3">
                                    <LabelInputOptional target="domain" label="Port" class="truncate mb-1" />
                                    <InputField v-model="form.port" type="text" name="port" :placeholder="'Enter port'"
                                        id="port" class="mt-1" :error="!!errors?.port" />
                                    <div v-if="errors?.port" class="mt-2 text-xs text-red-600">
                                        {{ errors.port[0] }}
                                    </div>
                                </div>

                                <div class="divide-y divide-gray-200 col-span-6 ">

                                    <Toggle label="Do not verify server certificate" description=""
                                        v-model="form.dont_verify_server_certificate" customClass="py-4" />

                                    <Toggle label="Disable SRTP" description="" v-model="form.disable_srtp"
                                        customClass="py-4" />

                                </div>
                            </div>


                            <div class="w-full border-t border-gray-300" />

                            <div class="flex justify-between items-center">
                                <h3 class="text-base font-semibold leading-6 text-gray-900">Outbound Proxy Settings</h3>
                            </div>


                            <div class="grid grid-cols-6 gap-6">
                                <div class="col-span-6">
                                    <LabelInputOptional target="proxy" label="Address" class="truncate mb-1" />
                                    <InputField v-model="form.proxy" type="text" name="proxy" id="proxy" class="mt-1"
                                        :error="!!errors?.proxy" :placeholder="'Enter proxy address'" />
                                    <div v-if="errors?.proxy" class="mt-2 text-xs text-red-600">
                                        {{ errors.proxy[0] }}
                                    </div>
                                </div>

                            </div>

                            <div class="w-full border-t border-gray-300" />

                            <div class="flex justify-between items-center">
                                <h3 class="text-base font-semibold leading-6 text-gray-900">Audio Codecs</h3>
                            </div>


                            <div class="grid grid-cols-6 gap-6">
                                <div class="col-span-6">
                                    <fieldset>
                                        <div class="space-y-5">
                                            <div class="flex gap-3">
                                                <div class="flex h-6 shrink-0 items-center">
                                                    <div class="group grid size-4 grid-cols-1">
                                                        <input v-model="form.g711u_enabled" type="checkbox"
                                                            class="col-start-1 row-start-1 appearance-none rounded border border-gray-300 bg-white checked:border-indigo-600 checked:bg-indigo-600 indeterminate:border-indigo-600 indeterminate:bg-indigo-600 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 disabled:border-gray-300 disabled:bg-gray-100 disabled:checked:bg-gray-100 forced-colors:appearance-auto" />
                                                        <svg class="pointer-events-none col-start-1 row-start-1 size-3.5 self-center justify-self-center stroke-white group-has-[:disabled]:stroke-gray-950/25"
                                                            viewBox="0 0 14 14" fill="none">
                                                            <path class="opacity-0 group-has-[:checked]:opacity-100"
                                                                d="M3 8L6 11L11 3.5" stroke-width="2" stroke-linecap="round"
                                                                stroke-linejoin="round" />
                                                            <path class="opacity-0 group-has-[:indeterminate]:opacity-100"
                                                                d="M3 7H11" stroke-width="2" stroke-linecap="round"
                                                                stroke-linejoin="round" />
                                                        </svg>
                                                    </div>
                                                </div>
                                                <div class="text-sm/6">
                                                    <label for="comments" class="font-medium text-gray-900">G.711
                                                        Ulaw</label>

                                                </div>
                                            </div>
                                            <div class="flex gap-3">
                                                <div class="flex h-6 shrink-0 items-center">
                                                    <div class="group grid size-4 grid-cols-1">
                                                        <input v-model="form.g711a_enabled" type="checkbox"
                                                            class="col-start-1 row-start-1 appearance-none rounded border border-gray-300 bg-white checked:border-indigo-600 checked:bg-indigo-600 indeterminate:border-indigo-600 indeterminate:bg-indigo-600 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 disabled:border-gray-300 disabled:bg-gray-100 disabled:checked:bg-gray-100 forced-colors:appearance-auto" />
                                                        <svg class="pointer-events-none col-start-1 row-start-1 size-3.5 self-center justify-self-center stroke-white group-has-[:disabled]:stroke-gray-950/25"
                                                            viewBox="0 0 14 14" fill="none">
                                                            <path class="opacity-0 group-has-[:checked]:opacity-100"
                                                                d="M3 8L6 11L11 3.5" stroke-width="2" stroke-linecap="round"
                                                                stroke-linejoin="round" />
                                                            <path class="opacity-0 group-has-[:indeterminate]:opacity-100"
                                                                d="M3 7H11" stroke-width="2" stroke-linecap="round"
                                                                stroke-linejoin="round" />
                                                        </svg>
                                                    </div>
                                                </div>
                                                <div class="text-sm/6">
                                                    <label for="candidates" class="font-medium text-gray-900">G.711
                                                        Alaw</label>

                                                </div>
                                            </div>
                                            <div class="flex gap-3">
                                                <div class="flex h-6 shrink-0 items-center">
                                                    <div class="group grid size-4 grid-cols-1">
                                                        <input v-model="form.g729_enabled" type="checkbox"
                                                            class="col-start-1 row-start-1 appearance-none rounded border border-gray-300 bg-white checked:border-indigo-600 checked:bg-indigo-600 indeterminate:border-indigo-600 indeterminate:bg-indigo-600 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 disabled:border-gray-300 disabled:bg-gray-100 disabled:checked:bg-gray-100 forced-colors:appearance-auto" />
                                                        <svg class="pointer-events-none col-start-1 row-start-1 size-3.5 self-center justify-self-center stroke-white group-has-[:disabled]:stroke-gray-950/25"
                                                            viewBox="0 0 14 14" fill="none">
                                                            <path class="opacity-0 group-has-[:checked]:opacity-100"
                                                                d="M3 8L6 11L11 3.5" stroke-width="2" stroke-linecap="round"
                                                                stroke-linejoin="round" />
                                                            <path class="opacity-0 group-has-[:indeterminate]:opacity-100"
                                                                d="M3 7H11" stroke-width="2" stroke-linecap="round"
                                                                stroke-linejoin="round" />
                                                        </svg>
                                                    </div>
                                                </div>
                                                <div class="text-sm/6">
                                                    <label for="offers" class="font-medium text-gray-900">G.729</label>

                                                </div>
                                            </div>
                                            <div class="flex gap-3">
                                                <div class="flex h-6 shrink-0 items-center">
                                                    <div class="group grid size-4 grid-cols-1">
                                                        <input v-model="form.opus_enabled" type="checkbox"
                                                            class="col-start-1 row-start-1 appearance-none rounded border border-gray-300 bg-white checked:border-indigo-600 checked:bg-indigo-600 indeterminate:border-indigo-600 indeterminate:bg-indigo-600 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 disabled:border-gray-300 disabled:bg-gray-100 disabled:checked:bg-gray-100 forced-colors:appearance-auto" />
                                                        <svg class="pointer-events-none col-start-1 row-start-1 size-3.5 self-center justify-self-center stroke-white group-has-[:disabled]:stroke-gray-950/25"
                                                            viewBox="0 0 14 14" fill="none">
                                                            <path class="opacity-0 group-has-[:checked]:opacity-100"
                                                                d="M3 8L6 11L11 3.5" stroke-width="2" stroke-linecap="round"
                                                                stroke-linejoin="round" />
                                                            <path class="opacity-0 group-has-[:indeterminate]:opacity-100"
                                                                d="M3 7H11" stroke-width="2" stroke-linecap="round"
                                                                stroke-linejoin="round" />
                                                        </svg>
                                                    </div>
                                                </div>
                                                <div class="text-sm/6">
                                                    <label for="offers" class="font-medium text-gray-900">Opus</label>

                                                </div>
                                            </div>
                                        </div>
                                    </fieldset>

                                </div>

                            </div>

                            <div class="w-full border-t border-gray-300" />

                            <div class="flex justify-between items-center">
                                <h3 class="text-base font-semibold leading-6 text-gray-900">Miscellaneous</h3>
                            </div>


                            <div class="grid grid-cols-6 gap-6">
                                <div class="col-span-6 sm:col-span-3">
                                    <LabelInputRequired target="registration_ttl" label="Registration TTL"
                                        class="truncate mb-1" />
                                    <InputField v-model="form.registration_ttl" type="text" name="registration_ttl"
                                        id="registration_ttl" class="mt-1" :error="!!errors?.registration_ttl"
                                        :placeholder="''" />
                                    <div v-if="errors?.registration_ttl" class="mt-2 text-xs text-red-600">
                                        {{ errors.registration_ttl[0] }}
                                    </div>
                                </div>

                                <div class="col-span-6 sm:col-span-3">
                                    <LabelInputRequired target="max_registrations" label="Max. registrations per user"
                                        class="truncate mb-1" />
                                    <InputField v-model="form.max_registrations" type="text" name="max_registrations"
                                        id="max_registrations" class="mt-1" :error="!!errors?.max_registrations"
                                        :placeholder="''" />
                                    <div v-if="errors?.max_registrations" class="mt-2 text-xs text-red-600">
                                        {{ errors.max_registrations[0] }}
                                    </div>
                                </div>

                                <div class="divide-y divide-gray-200 col-span-6">

                                    <Toggle label="Use OPUS audio codec"
                                        description="Enabling the OPUS audio codec between the softphone apps and a softphone server improves call quality on low bandwidth/congested networks, but may cause small audio delays."
                                        v-model="form.app_opus_codec" customClass="py-4" />
                                    <Toggle label="Send one push notification"
                                        description="This option can be useful for Queues or Ring groups with sequential ring strategy. It doesn't try to send second push notification in the case of the user's mobile app was not waked up by the first one."
                                        v-model="form.one_push" customClass="py-4" />

                                </div>



                            </div>




                            <div class="mt-5 sm:mt-4 sm:flex sm:flex-row-reverse">
                                <button type="sumbit" :disabled="isSubmitting"
                                    class="inline-flex w-full justify-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 sm:ml-3 sm:w-auto"
                                    @click="open = false">
                                    <Spinner :show="isSubmitting" />
                                    Save
                                </button>
                                <button type="button" @click="emits('cancel')"
                                    class="mt-3 inline-flex w-full justify-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 sm:mt-0 sm:w-auto">Cancel</button>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-if="activeTab === 'features'">
                    <div class="shadow sm:rounded-md">
                        <div class="space-y-6 bg-gray-100 px-4 py-6 sm:p-6">
                            <div class="flex justify-between items-center">
                                <h3 class="text-base font-semibold leading-6 text-gray-900">Features</h3>

                                <!-- <Toggle label="Status" v-model="" /> -->

                                <!-- <p class="mt-1 text-sm text-gray-500"></p> -->
                            </div>



                            <div class="grid grid-cols-6 gap-6">


                                <div class="divide-y divide-gray-200 col-span-6">

                                    <Toggle label="Show call settings"
                                        description="Allow users to configure call settings from within the app, such as call forwarding, voicemail, call waiting."
                                        v-model="form.show_call_settings" customClass="py-4" />

                                    <Toggle label="Allow call recording"
                                        description="Allow users to record calls. IMPORTANT: You are responsible for your compliance with call recording laws. We do not indemnify against legal claims that may arise from the use of this feature."
                                        v-model="form.allow_call_recording" customClass="py-4" />

                                    <Toggle label="Allow state change"
                                        description="Allow users to change their state from the app, such as Online/DND/At the desk."
                                        v-model="form.allow_state_change" customClass="py-4" />

                                    <Toggle label="Allow video calls" description="Allow users to make 1-on-1 video calls."
                                        v-model="form.allow_video_calls" customClass="py-4" />

                                    <Toggle label="Allow internal chat"
                                        description="Allow users to use internal chat feature and create new chats."
                                        v-model="form.allow_internal_chat" customClass="py-4" />

                                    <Toggle label="Disable call history syncing in iPhone Recents "
                                        description="If enabled, this option disables call history syncing in iPhone Recents and hides the 'Show calls in iPhone Recents' option from the app's settings."
                                        v-model="form.disable_iphone_recents" customClass="py-4" />

                                </div>

                            </div>

                            <div class="grid grid-cols-6 gap-6">

                                <div class="col-span-5">
                                    <div class="flex items-center">
                                        <LabelInputRequired target="call_delay"
                                            label="Call Delay for 'At the Desk' Status (Seconds)"
                                            class="mb-1 mr-1 flex-initial w-72" />
                                        <InputField v-model="form.call_delay" type="text" name="call_delay" id="call_delay"
                                            class="flex-none w-14" :error="!!errors?.call_delay" :placeholder="''" />
                                    </div>

                                    <div v-if="errors?.call_delay" class="mt-2 text-xs text-red-600">
                                        {{ errors.call_delay[0] }}
                                    </div>

                                </div>

                                <div class="divide-y divide-gray-200 col-span-6">

                                    <Toggle label="Delay incoming calls to the desktop app" description=""
                                        v-model="form.desktop_app_delay" customClass="py-4" />


                                </div>

                            </div>





                            <div class="mt-5 sm:mt-4 sm:flex sm:flex-row-reverse">
                                <button type="sumbit" :disabled="isSubmitting"
                                    class="inline-flex w-full justify-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 sm:ml-3 sm:w-auto"
                                    @click="open = false">
                                    <Spinner :show="isSubmitting" />
                                    Save
                                </button>
                                <button type="button" @click="emits('cancel')"
                                    class="mt-3 inline-flex w-full justify-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 sm:mt-0 sm:w-auto">Cancel</button>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-if="activeTab === 'pbx_features'">
                    <div class="shadow sm:rounded-md">
                        <div class="space-y-6 bg-gray-100 px-4 py-6 sm:p-6">
                            <div>
                                <h3 class="text-base font-semibold leading-6 text-gray-900">PBX Features</h3>
                                <p class="mt-1 text-sm text-gray-500">Provide feature codes configured on your PBX to handle
                                    them on your server.</p>
                            </div>



                            <div class="grid grid-cols-6 gap-6">
                                <div class="divide-y divide-gray-200 col-span-6">

                                    <Toggle label="Enable PBX features"
                                        description="Handle features on the PBX. NOTE: Please ensure you provide shortcodes for the PBX features."
                                        v-model="form.pbx_features" customClass="py-4" />

                                </div>
                            </div>

                            <div v-if="form.pbx_features">
                                <div>
                                    <h3 class="text-base font-semibold leading-6 text-gray-900">Voicemail</h3>
                                </div>

                                <div class="grid grid-cols-6 gap-6 mt-1">
                                    <div class="col-span-6 sm:col-span-3">
                                        <LabelInputOptional target="voicemail_extension" label="Voicemail code"
                                            class="truncate mb-1" />
                                        <InputField v-model="form.voicemail_extension" type="text"
                                            name="voicemail_extension" id="voicemail_extension" class="mt-1"
                                            :error="!!errors?.voicemail_extension" :placeholder="''" />
                                        <div v-if="errors?.voicemail_extension" class="mt-2 text-xs text-red-600">
                                            {{ errors.voicemail_extension[0] }}
                                        </div>
                                    </div>
                                </div>

                            </div>

                            <div v-if="form.pbx_features">
                                <div>
                                    <h3 class="text-base font-semibold leading-6 text-gray-900">Do Not Disturb</h3>
                                </div>

                                <div class="grid grid-cols-6 gap-6 mt-1">
                                    <div class="col-span-6 sm:col-span-3">
                                        <LabelInputOptional target="dnd_on_code" label="Activate DND"
                                            class="truncate mb-1" />
                                        <InputField v-model="form.dnd_on_code" type="text"
                                            name="dnd_on_code" id="dnd_on_code" class="mt-1"
                                            :error="!!errors?.dnd_on_code" :placeholder="''" />
                                        <div v-if="errors?.dnd_on_code" class="mt-2 text-xs text-red-600">
                                            {{ errors.dnd_on_code[0] }}
                                        </div>
                                    </div>
                                    <div class="col-span-6 sm:col-span-3">
                                        <LabelInputOptional target="dnd_off_code" label="Deactivate DND"
                                            class="truncate mb-1" />
                                        <InputField v-model="form.dnd_off_code" type="text"
                                            name="dnd_off_code" id="dnd_off_code" class="mt-1"
                                            :error="!!errors?.dnd_off_code" :placeholder="''" />
                                        <div v-if="errors?.dnd_off_code" class="mt-2 text-xs text-red-600">
                                            {{ errors.dnd_off_code[0] }}
                                        </div>
                                    </div>
                                </div>

                            </div>

                            <div v-if="form.pbx_features">
                                <div>
                                    <h3 class="text-base font-semibold leading-6 text-gray-900">Call Forwarding</h3>
                                </div>

                                <div class="grid grid-cols-6 gap-6 mt-1">
                                    <div class="col-span-6 sm:col-span-3">
                                        <LabelInputOptional target="cf_on_code" label="Activate Call Forwarding"
                                            class="truncate mb-1" />
                                        <InputField v-model="form.cf_on_code" type="text"
                                            name="cf_on_code" id="cf_on_code" class="mt-1"
                                            :error="!!errors?.cf_on_code" :placeholder="''" />
                                        <div v-if="errors?.cf_on_code" class="mt-2 text-xs text-red-600">
                                            {{ errors.cf_on_code[0] }}
                                        </div>
                                    </div>
                                    <div class="col-span-6 sm:col-span-3">
                                        <LabelInputOptional target="cf_off_code" label="Deactivate Call Forwarding"
                                            class="truncate mb-1" />
                                        <InputField v-model="form.cf_off_code" type="text"
                                            name="cf_off_code" id="cf_off_code" class="mt-1"
                                            :error="!!errors?.cf_off_code" :placeholder="''" />
                                        <div v-if="errors?.cf_off_code" class="mt-2 text-xs text-red-600">
                                            {{ errors.cf_off_code[0] }}
                                        </div>
                                    </div>
                                </div>

                            </div>


                            <div class="mt-5 sm:mt-4 sm:flex sm:flex-row-reverse">
                                <button type="sumbit" :disabled="isSubmitting"
                                    class="inline-flex w-full justify-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 sm:ml-3 sm:w-auto"
                                    @click="open = false">
                                    <Spinner :show="isSubmitting" />
                                    Save
                                </button>
                                <button type="button" @click="emits('cancel')"
                                    class="mt-3 inline-flex w-full justify-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 sm:mt-0 sm:w-auto">Cancel</button>
                            </div>
                        </div>
                    </div>
                </div>

            </form>
        </div>
    </div>
</template>

<script setup>
import { reactive, ref } from "vue";
import { usePage } from '@inertiajs/vue3';


import ComboBox from "../general/ComboBox.vue";
import InputField from "../general/InputField.vue";
import LabelInputOptional from "../general/LabelInputOptional.vue";
import LabelInputRequired from "../general/LabelInputRequired.vue";
import Spinner from "../general/Spinner.vue";
import { Cog6ToothIcon, AdjustmentsHorizontalIcon } from '@heroicons/vue/24/outline';
import { ExclamationCircleIcon } from '@heroicons/vue/20/solid'
import Toggle from "@generalComponents/Toggle.vue";
import SettingsApplications from "@icons/SettingsApplications.vue"


const props = defineProps({
    selectedConnection: Object,
    options: Object,
    isSubmitting: Boolean,
    errors: Object,
});

const page = usePage();

const form = reactive({
    org_id: props.options?.orgId ?? null,
    conn_id: props.selectedConnection?.id ?? null,
    connection_name: props.selectedConnection?.name ?? '',
    protocol: props.selectedConnection?.provision?.protocol ?? '',
    domain: props.selectedConnection?.address?.split(':')[0] ?? '', // Extract domain
    port: props.selectedConnection?.address?.split(':')[1] ?? '',   // Extract port
    dont_verify_server_certificate: props.selectedConnection?.provision?.noverify ?? false,
    disable_srtp: props.selectedConnection?.provision?.nosrtp ?? false,
    proxy: props.selectedConnection?.provision?.proxy?.paddr ?? '',
    multitenant: props.options?.settings?.multitenant_mode === "true",
    g711u_enabled: props.selectedConnection?.provision?.codecs?.some(codec => codec.codec === "G.711 Ulaw") ?? false,
    g711a_enabled: props.selectedConnection?.provision?.codecs?.some(codec => codec.codec === "G.711 Alaw") ?? false,
    g729_enabled: props.selectedConnection?.provision?.codecs?.some(codec => codec.codec === "G.729") ?? false,
    opus_enabled: props.selectedConnection?.provision?.codecs?.some(codec => codec.codec === "Opus") ?? false,
    registration_ttl: String(props.selectedConnection?.provision?.regexpires ?? ''),
    max_registrations: String(props.selectedConnection?.provision?.maxregs ?? ''),
    app_opus_codec: !props.selectedConnection?.provision?.app?.g711 ?? false,
    one_push: props.selectedConnection?.provision?.["1push"] ?? false,
    show_call_settings: props.selectedConnection?.provision?.nostates === false, // Assuming inverse of `nostates`
    allow_call_recording: !props.selectedConnection?.provision?.norec ?? false, // Inverse of `norec`
    allow_state_change: props.selectedConnection?.provision?.nostates === false, // Assuming `nostates` indicates restriction
    allow_video_calls: !props.selectedConnection?.provision?.novideo ?? false, // Inverse of `novideo`
    allow_internal_chat: !props.selectedConnection?.provision?.nochats ?? false, // Inverse of `nochats`
    disable_iphone_recents: props.selectedConnection?.provision?.norecents ?? false, // Assuming `norecents` indicates disabling
    call_delay: props.selectedConnection?.provision?.calldelay ?? 0,
    desktop_app_delay: props.selectedConnection?.provision?.pcdelay ?? 0, // Assuming `pcdelay` represents desktop app delay
    pbx_features: props.selectedConnection?.provision?.features === "pbx",
    voicemail_extension: props.selectedConnection?.provision?.vmail?.ext ?? '', // Ensure `vmail.ext` exists
    dnd_on_code: props.selectedConnection?.provision?.dnd?.on ?? '',
    dnd_off_code: props.selectedConnection?.provision?.dnd?.off ?? '',
    cf_on_code: props.selectedConnection?.provision?.forwarding?.cfon ?? '',
    cf_off_code: props.selectedConnection?.provision?.forwarding?.cfoff ?? '',
    _token: page.props.csrf_token,
});


const emits = defineEmits(['submit', 'cancel']);


// Initialize activeTab with the currently active tab from props
const activeTab = ref(props.options.conn_navigation.find(item => item.slug)?.slug || props.options.conn_navigation[0].slug);


const submitForm = () => {
    // console.log(form);
    emits('submit', form); // Emit the event with the form data
}

const handleUpdateProtocolField = (selected) => {
    form.protocol = selected.value;
}


const iconComponents = {
    'Cog6ToothIcon': Cog6ToothIcon,
    'SettingsApplications': SettingsApplications,
    'AdjustmentsHorizontalIcon': AdjustmentsHorizontalIcon,
};

const setActiveTab = (tabSlug) => {
    activeTab.value = tabSlug;
};



</script>