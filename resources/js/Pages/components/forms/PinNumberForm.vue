<template>
    <AddEditItemModal :show="show" :header="header" :loading="loading" @close="emit('close')">
        <template #modal-body>
            <Vueform
                ref="form$"
                :endpoint="submitForm"
                :display-errors="false"
                :default="defaultValues"
                @success="handleSuccess"
                @error="handleError"
                @response="handleResponse"
            >
                <TextElement
                    name="pin_number"
                    label="PIN Number"
                    placeholder="Enter PIN number"
                    :floating="false"
                    :rules="['required', 'max:255']"
                />

                <TextElement
                    name="accountcode"
                    label="Account Code"
                    placeholder="Optional account code"
                    :floating="false"
                    :rules="['max:255']"
                />

                <ToggleElement
                    name="enabled"
                    text="Enabled"
                    true-value="true"
                    false-value="false"
                    :labels="{ on: 'On', off: 'Off' }"
                    label="&nbsp;"
                />

                <TextareaElement
                    name="description"
                    label="Description"
                    :floating="false"
                    :rows="3"
                    :rules="['max:255']"
                />

                <GroupElement name="button_container" />

                <ButtonElement name="submit" button-label="Save" :submits="true" align="right" />
            </Vueform>
        </template>
    </AddEditItemModal>
</template>

<script setup>
import { computed, ref } from "vue";
import AddEditItemModal from "../modal/AddEditItemModal.vue";

const props = defineProps({
    show: Boolean,
    options: Object,
    loading: Boolean,
    header: {
        type: String,
        default: "PIN Number",
    },
    mode: {
        type: String,
        default: "create",
    },
});

const emit = defineEmits(["close", "error", "success", "refresh-data"]);

const form$ = ref(null);

const defaultValues = computed(() => ({
    pin_number: props.options?.item?.pin_number ?? null,
    accountcode: props.options?.item?.accountcode ?? null,
    enabled: props.options?.item?.enabled ?? "true",
    description: props.options?.item?.description ?? null,
}));

const submitForm = async (FormData, form$) => {
    const route = props.mode === "create"
        ? props.options.routes.store_route
        : props.options.routes.update_route;

    if (props.mode === "create") {
        return await form$.$vueform.services.axios.post(route, form$.requestData);
    }

    return await form$.$vueform.services.axios.put(route, form$.requestData);
};

function clearErrorsRecursive(el$) {
    el$.messageBag?.clear();

    if (el$.children$) {
        Object.values(el$.children$).forEach((childEl$) => {
            clearErrorsRecursive(childEl$);
        });
    }
}

const handleResponse = (response, form$) => {
    Object.values(form$.elements$).forEach((el$) => {
        clearErrorsRecursive(el$);
    });

    if (response.data.errors) {
        Object.keys(response.data.errors).forEach((elName) => {
            if (form$.el$(elName)) {
                form$.el$(elName).messageBag.append(response.data.errors[elName][0]);
            }
        });
    }
};

const handleSuccess = (response) => {
    emit("success", "success", response.data.messages);
    emit("refresh-data");
    emit("close");
};

const handleError = (error, details, form$) => {
    form$.messageBag.clear();

    if (details.type === "submit") {
        emit("error", error);
        return;
    }

    form$.messageBag.append("Could not submit form");
};
</script>
