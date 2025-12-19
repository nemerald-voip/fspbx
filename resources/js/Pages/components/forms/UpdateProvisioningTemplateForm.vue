<template>
    <div class="bg-white px-4 py-6 sm:px-6 lg:px-8">



        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-x-6">
            <div>
                <LabelInputRequired target="name" label="Template Name" />
                <div class="mt-2">
                    <InputField v-model="form.name" type="text" name="name" placeholder="Enter template name"
                        :disabled="readOnly" />
                </div>
                <div v-if="errors?.name" class="mt-2 text-xs text-red-600">
                    {{ errors.name[0] }}
                </div>
            </div>

            <div v-if="form.global == false || form.type == 'custom'">
                <LabelInputOptional target="members" label="Base Template" />
                <div class="mt-2 relative">
                    <Multiselect v-model="base_template" :options="options.default_templates" :multiple="false"
                        :close-on-select="true" :clear-on-select="false" :preserve-search="true"
                        placeholder="Choose Base Template" label="name" track-by="value" :searchable="true"
                        @select="loadBaseTemplate" />
                </div>

                <div v-if="errors?.members" class="mt-2 text-xs text-red-600">
                    {{ errors.members[0] }}
                </div>
            </div>

            <div>
                <LabelInputOptional target="vendors" label="Vendor" />
                <div class="mt-2 relative">
                    <Multiselect v-model="vendor" :options="options.vendors" :multiple="false"
                        :close-on-select="true" :clear-on-select="false" :preserve-search="true"
                        placeholder="Choose Vendor" label="name" track-by="value" :searchable="true"
                        />
                </div>
            </div>

            <div v-if="errors?.vendors" class="mt-2 text-xs text-red-600">
                {{ errors.vendors[0] }}
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-x-6">
            <div v-if="form.global == false || form.type == 'custom'" class="mt-2">
                <Toggle label="Share across accounts" description="Let other accounts view and use this template."
                    v-model="form.global" customClass="py-4" />
            </div>
        </div>


        <div class="mt-4">
            <!-- Toolbar -->
            <div class="flex items-center justify-between mb-2">
                <div class="flex gap-2">
                    <!-- Language selector -->
                    <select v-model="editorLang"
                        class="rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="php_laravel_blade">Blade</option>
                        <option value="xml">XML</option>
                        <option value="yaml">YAML</option>
                        <option value="lua">Lua</option>
                        <option value="php">PHP</option>
                    </select>

                    <!-- Theme selector -->
                    <select v-model="editorTheme"
                        class="rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="chrome">Light</option>
                        <option value="one_dark">Dark</option>
                    </select>
                </div>
            </div>

            <!-- Editor wrapper -->
            <div class="editor-wrapper relative rounded-lg shadow-lg border border-gray-200 overflow-hidden">
                <!-- Loading overlay -->
                <div v-if="isLoadingTemplate"
                    class="absolute inset-0 z-10 bg-white/60 backdrop-blur-[1px] flex items-center justify-center">
                    <Spinner :show="true" />
                    <span class="ml-2 text-sm text-gray-600">Loading template…</span>
                </div>

                <AceEditor v-model="form.content" :lang="editorLang" :theme="editorTheme"
                    :options="{ fontSize: 16, tabSize: 4, readOnly: isLoadingTemplate || readOnly }" :height="'80vh'"
                    class="editor_wrap" />
            </div>
        </div>


        <div class="col-span-3 border-t mt-4">
            <div class="mt-4 sm:grid sm:grid-flow-row-dense sm:grid-cols-2 sm:gap-3">
                <button @click.prevent="submitForm" v-if="form.global == false || form.type == 'custom'"
                    class="inline-flex w-full justify-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 sm:col-start-2"
                    :disabled="isSubmitting">
                    <Spinner :show="isSubmitting" />
                    Save
                </button>
                <button type="button"
                    class="mt-3 inline-flex w-full justify-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 sm:col-start-1 sm:mt-0"
                    @click="emits('cancel')">Cancel
                </button>
            </div>
        </div>
    </div>
    <!-- </div> -->
</template>

<script setup>
import { reactive, ref, watch } from "vue";
import Multiselect from 'vue-multiselect'
import 'vue-multiselect/dist/vue-multiselect.css'
import InputField from "../general/InputField.vue";
import LabelInputOptional from "../general/LabelInputOptional.vue";
import LabelInputRequired from "../general/LabelInputRequired.vue";
import Spinner from "../general/Spinner.vue";
import AceEditor from '@generalComponents/AceEditor.vue'
import Toggle from "@generalComponents/Toggle.vue";

const props = defineProps({
    options: Object,
    isSubmitting: Boolean,
    errors: Object,
    readOnly: { type: Boolean, default: false },
});

console.log(props.options)

const isLoadingTemplate = ref(false)
const base_template = ref(null);
const vendor = ref(null);

const defaults = {
    vendor: null,
    name: null,
    content: '',
    base_template: null,
    base_version: null,
    type: 'custom',
    global: false,
}

const form = reactive({ ...defaults })

function hydrate(item) {
    // don't overwrite `global` from defaults loop
    for (const k in defaults) {
        if (k === 'global') continue
        form[k] = item?.[k] ?? defaults[k]
    }

    // derive global from domain_uuid presence
    form.global = !!item && item.domain_uuid === null
}

// set once and whenever the selected item object changes
watch(() => props.options?.item, (item) => {
    hydrate(item)
    const opts = props.options?.default_templates ?? []
    base_template.value = opts.find(o => o.name === form.base_template) || null

    const vendors = props.options?.vendors ?? []
    vendor.value = vendors.find(o => o.name === form.vendor) || null
}, { immediate: true })


const emits = defineEmits(['submit', 'cancel', 'error', 'clear-errors']);

const editorLang = ref('php_laravel_blade');
const editorTheme = ref('chrome');

const loadBaseTemplate = (selectedOption, id) => {
    isLoadingTemplate.value = true
    form.base_template = selectedOption.name

    axios.post(props.options.routes.template_content, {
        template_uuid: selectedOption.value,
    })
        .then((response) => {
            form.content = response.data?.item?.content ?? ''
            form.vendor = response.data?.item?.vendor ?? ''
            form.base_version = response.data?.item?.version ?? ''
            // console.log(response.data);

        }).catch((error) => {
            handleErrorResponse(error)
        }).finally(() => {
            isLoadingTemplate.value = false
        });
}


const submitForm = () => {
    // console.log(form)
    emits('submit', form); // Emit the event with the form data
}


</script>
