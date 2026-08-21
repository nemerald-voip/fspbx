<template>
    <Vueform
        ref="form$"
        :endpoint="submitForm"
        :default="defaultValues"
        :display-errors="false"
        :float-placeholders="false"
        @success="handleSuccess"
        @error="handleError"
        @response="handleResponse"
    >
        <HiddenElement name="menu_uuid" />
        <HiddenElement name="domain_uuid" />
        <HiddenElement v-if="mode === 'update'" name="option_uuid" />
        <HiddenElement name="extension" />

        <ToggleElement
            name="status"
            :text="$t('Enabled')"
            :true-value="true"
            :false-value="false"
            :labels="{ on: $t('On'), off: $t('Off') }"
            label="&nbsp;"
        />

        <TextElement
            name="key"
            :label="$t('Key')"
            :placeholder="$t('Enter one or more digits (e.g., 1, 12, 123)')"
            :rules="['required', 'max:11']"
            autocomplete="off"
        />

        <SelectElement
            name="action"
            :items="routingTypes"
            value-prop="value"
            label-prop="name"
            :label="$t('Action')"
            :placeholder="$t('Choose Action')"
            :search="true"
            :native="false"
            :strict="false"
            :rules="['required']"
            input-type="search"
            autocomplete="off"
            @change="handleActionChange"
        />

        <SelectElement
            name="target"
            :items="fetchRoutingTargets"
            value-prop="value"
            label-prop="name"
            :label="$t('Target')"
            :placeholder="$t('Choose Target')"
            :search="true"
            :native="false"
            :strict="false"
            :object="true"
            :format-data="formatTarget"
            :rules="['required']"
            input-type="search"
            autocomplete="off"
            allow-absent
            :conditions="[
                ['action', 'not_empty'],
                ['action', 'not_in', destinationTypesWithoutTarget],
            ]"
            @change="handleTargetChange"
        />

        <TextElement
            name="description"
            :label="$t('Description')"
            :placeholder="$t('Enter description')"
            :rules="['max:255']"
            autocomplete="off"
        />

        <GroupElement name="button_container" />
        <ButtonElement name="submit" :button-label="$t('Save')" :submits="true" align="right" />
    </Vueform>
</template>

<script setup>
import { computed, ref } from "vue";
import { trans } from "@i18n";

const props = defineProps({
    options: {
        type: Object,
        required: true,
    },
    selectedKey: {
        type: Object,
        default: null,
    },
    mode: {
        type: String,
        default: "create",
    },
});

const emit = defineEmits(["close", "error", "success", "saved"]);
const form$ = ref(null);

const destinationTypesWithoutTarget = ["check_voicemail", "company_directory", "hangup"];

const routingTypes = computed(() => props.options?.routing_types ?? []);

const defaultTarget = computed(() => {
    const value = props.selectedKey?.key_uuid ?? null;

    if (!value) {
        return null;
    }

    return {
        value,
        extension: props.selectedKey?.key_extension ?? null,
        name: props.selectedKey?.key_name
            ?? props.selectedKey?.key_extension
            ?? value,
    };
});

const defaultValues = computed(() => ({
    option_uuid: props.selectedKey?.ivr_menu_option_uuid ?? null,
    menu_uuid: props.selectedKey?.ivr_menu_uuid ?? props.options?.item?.ivr_menu_uuid ?? null,
    domain_uuid: props.selectedKey?.domain_uuid ?? props.options?.item?.domain_uuid ?? null,
    status: props.selectedKey
        ? props.selectedKey.ivr_menu_option_enabled === true
            || props.selectedKey.ivr_menu_option_enabled === "true"
        : true,
    key: props.selectedKey?.ivr_menu_option_digits ?? null,
    action: props.selectedKey?.key_type ?? null,
    target: defaultTarget.value,
    extension: props.selectedKey?.key_extension ?? null,
    description: props.selectedKey?.ivr_menu_option_description ?? null,
}));

const fetchRoutingTargets = async (query, input) => {
    const action = input.$parent.el$.form$.el$("action");
    const route = props.options?.routes?.get_routing_options;

    if (!route || !action?.value || destinationTypesWithoutTarget.includes(action.value)) {
        return [];
    }

    try {
        const response = await action.$vueform.services.axios.post(route, {
            category: action.value,
        });

        return response.data.options;
    } catch (error) {
        emit("error", error);
        return [];
    }
};

const handleActionChange = (newValue, oldValue, el$) => {
    if (newValue === oldValue) {
        return;
    }

    const target = el$.form$.el$("target");

    if (oldValue !== null && oldValue !== undefined) {
        target?.clear();
        el$.form$.el$("extension")?.update(null);
    }

    target?.updateItems();
};

const handleTargetChange = (value, oldValue, el$) => {
    el$.form$.el$("extension")?.update(value?.extension ?? null);
};

const formatTarget = (name, value) => ({
    [name]: value?.value ?? value?.bridge_uuid ?? value ?? null,
});

const submitForm = async (FormData, form$) => {
    const route = props.mode === "update"
        ? props.options.routes.update_key_route
        : props.options.routes.create_key_route;

    if (props.mode === "update") {
        return await form$.$vueform.services.axios.put(route, form$.requestData);
    }

    return await form$.$vueform.services.axios.post(route, form$.requestData);
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
        Object.keys(response.data.errors).forEach((elementName) => {
            form$.el$(elementName)?.messageBag.append(response.data.errors[elementName][0]);
        });
    }
};

const handleSuccess = (response) => {
    emit("success", response.data.messages);
    emit("saved");
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
