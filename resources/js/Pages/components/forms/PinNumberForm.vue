<template>
    <AddEditItemModal :show="show" :header="header || $t('PIN Number')" :loading="loading" @close="emit('close')">
        <template #modal-body>
            <Vueform :locale="formLocale" @mounted="form => form.disableValidation()"
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
                    :label="$t(&quot;PIN Number&quot;)"
                    :placeholder="$t(&quot;Enter PIN number&quot;)"
                    :floating="false"
                />

                <TextElement
                    name="accountcode"
                    :label="$t(&quot;Account Code&quot;)"
                    :placeholder="$t(&quot;Optional account code&quot;)"
                    :floating="false"
                />

                <ToggleElement
                    name="enabled"
                    :text="$t(&quot;Enabled&quot;)"
                    true-value="true"
                    false-value="false"
                    :labels="{ on: $t(&quot;On&quot;), off: $t(&quot;Off&quot;) }"
                    label="&nbsp;"
                />

                <TextareaElement
                    name="description"
                    :label="$t(&quot;Description&quot;)"
                    :floating="false"
                    :rows="3"
                />

                <GroupElement name="button_container" />

                <ButtonElement name="submit" :button-label="$t(&quot;Save&quot;)" :submits="true" align="right" />
            </Vueform>
        </template>
    </AddEditItemModal>
</template>

<script setup>
import { useVueformLocale } from "../../../composables/useVueformLocale.js";
import { trans } from "@i18n";
import { computed, ref } from "vue";
import AddEditItemModal from "../modal/AddEditItemModal.vue";

const formLocale = useVueformLocale();

const props = defineProps({
    show: Boolean,
    options: Object,
    loading: Boolean,
    header: {
        type: String,
        default: "",
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
    form$.messageBag.clear();
    Object.values(form$.elements$).forEach(clearErrorsRecursive);
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

    form$.messageBag.append(trans("Could not submit form"));
};
</script>
