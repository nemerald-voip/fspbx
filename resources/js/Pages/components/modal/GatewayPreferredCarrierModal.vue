<template>
    <AddEditItemModal :show="show" header="Create Gateway" @close="handleClose">
        <template #modal-body>
            <Vueform ref="form$" :display-errors="false" :default="defaultValues">
                <template #empty>
                    <FormElements>
                        <SelectElement name="carrier" label="Preferred carrier" :items="carrierOptions"
                            :native="false" :search="true" input-type="search" autocomplete="off"
                            placeholder="Select carrier" :strict="false" :floating="false" />

                        <StaticElement name="voxutel_details" :conditions="[['carrier', 'voxutel']]">
                            <div class="rounded-md bg-gray-50 p-4 text-sm text-gray-600 ring-1 ring-inset ring-gray-200">
                                <p class="font-medium text-gray-900">Voxutel</p>
                                <p class="mt-1">Choose this option to connect FS PBX to Voxutel. Need an account? Click Sign up. Already signed up? Click Create Gateway &amp; OB Route.</p>
                            </div>
                        </StaticElement>

                        <StaticElement name="custom_details" :conditions="[['carrier', 'custom']]">
                            <div class="rounded-md bg-gray-50 p-4 text-sm text-gray-600 ring-1 ring-inset ring-gray-200">
                                <p class="font-medium text-gray-900">Custom selected</p>
                                <p class="mt-1">Continue to open the native gateway setup form.</p>
                            </div>
                        </StaticElement>

                        <GroupElement name="button_container" />

                        <StaticElement name="voxutel_actions" :conditions="[['carrier', 'voxutel']]">
                            <div class="flex flex-wrap justify-end gap-3">
                                <button type="button"
                                    class="rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50"
                                    @click="showSignup = true">
                                    Sign up
                                </button>
                                <button type="button"
                                    class="rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 disabled:cursor-not-allowed disabled:opacity-60"
                                    :disabled="props.creating" @click="submitCarrier('voxutel')">
                                    Create Gateway &amp; OB Route
                                </button>
                            </div>
                        </StaticElement>
                        <StaticElement name="custom_actions" :conditions="[['carrier', 'custom']]">
                            <div class="flex justify-end">
                                <button type="button"
                                    class="rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 disabled:cursor-not-allowed disabled:opacity-60"
                                    :disabled="props.creating" @click="submitCarrier('custom')">
                                    Continue
                                </button>
                            </div>
                        </StaticElement>
                    </FormElements>
                </template>
            </Vueform>
        </template>
    </AddEditItemModal>

    <AddEditItemModal :show="showSignup" header="Voxutel Sign Up" custom-class="sm:max-w-5xl"
        body-class="pb-0" @close="showSignup = false">
        <template #modal-body>
            <div class="overflow-hidden rounded-md ring-1 ring-inset ring-gray-200">
                <div class="flex flex-wrap items-center justify-between gap-3 bg-gray-50 px-3 py-2 text-sm">
                    <p class="font-semibold text-gray-900">New Voxutel accounts are typically approved within one business day. Once your account is approved, return here to create the gateway.</p>
                    <button type="button"
                        class="rounded-md bg-white px-2.5 py-1.5 text-xs font-semibold text-gray-700 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50"
                        @click="openSignupPage">
                        Open signup page
                    </button>
                </div>
                <iframe :src="signupUrl" title="Voxutel signup page" class="h-[70vh] min-h-96 w-full border-0 bg-white" />
            </div>
            <div class="mt-4 flex flex-wrap justify-end gap-3">
                <button type="button"
                    class="rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50"
                    @click="showSignup = false">
                    Back
                </button>
                <button type="button"
                    class="rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500"
                    @click="submitCarrier('voxutel')">
                    Create Gateway &amp; OB Route
                </button>
            </div>
        </template>
    </AddEditItemModal>
</template>

<script setup>
import { ref } from "vue";
import AddEditItemModal from "./AddEditItemModal.vue";

const props = defineProps({
    show: Boolean,
    creating: Boolean,
});

const emit = defineEmits(["close", "create-carrier", "open-native"]);

const form$ = ref(null);
const showSignup = ref(false);
const signupUrl = "https://www.voxutel.com/signup.php?ref=fspbx";

const defaultValues = {
    carrier: "voxutel",
};

const carrierOptions = [
    { value: "voxutel", label: "Voxutel" },
    { value: "custom", label: "Custom" },
];

const submitCarrier = (carrier) => {
    if (carrier === "custom") {
        emit("open-native");
        return;
    }

    showSignup.value = false;
    emit("create-carrier", carrier);
};

const openSignupPage = () => {
    window.open(signupUrl, "_blank", "noopener,noreferrer");
};

const handleClose = () => {
    showSignup.value = false;
    emit("close");
};
</script>
