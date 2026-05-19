<template>
    <ListElement :name="name" :sort="true" size="sm"
        :controls="{ add: true, remove: true, sort: true }"
        :add-classes="{ ListElement: { listItem: 'bg-white p-4 mb-4 rounded-lg shadow-md' } }">
        <template #default="{ index }">
            <ObjectElement :name="index">
                <HiddenElement name="key_area" :meta="true" :default="area" />
                <HiddenElement name="key_uuid" :meta="true" :default="Math.random().toString(36).slice(2)" />
                <HiddenElement name="_generated_label" :meta="true" :default="null" />

                <TextElement name="key_index" label="Key" :rules="['nullable', 'numeric']"
                    autocomplete="off" :columns="{ sm: { container: 1 } }"
                    :default="getNextKeyNumber(name)" />

                <SelectElement name="key_type" label="Type" :items="keyTypes" :search="true"
                    label-prop="name" :native="false" input-type="search" autocomplete="off"
                    :columns="{ sm: { container: 3 } }" placeholder="Choose Function" :floating="false"
                    @change="(newValue, oldValue, el$) => handleTypeChange(oldValue, el$, index)" />

                <SelectElement name="key_value_select" label="Value" label-prop="name" value-prop="extension"
                    :search="true" :native="false" :submit="false" allow-absent
                    :create="['blf', 'speed_dial', 'park'].includes(formData?.[name]?.[index]?.key_type)"
                    :append-new-option="false" input-type="search" autocomplete="off"
                    :columns="{ sm: { container: 4 } }" placeholder="Choose Ext/Number" :floating="false"
                    :items="(query, input) => getKeyValueSelectItems(query, input, index, name)"
                    @change="(newValue, oldValue, el$) => updateLabel(newValue, oldValue, el$, index, name)"
                    :conditions="[[name + '.*.key_type', ['line', 'check_voicemail', 'blf', 'speed_dial', 'park']]]" />

                <TextElement name="key_value_text" label="Value" :columns="{ sm: { container: 4 } }"
                    placeholder="Enter Value" :floating="false" :disabled="[[name + '.*.key_type', '']]"
                    :conditions="[[name + '.*.key_type', '!=', ['line', 'check_voicemail', 'blf', 'speed_dial', 'park']]]" />

                <HiddenElement name="key_value" :meta="true" :default="null" />

                <TextElement name="key_label" label="Label"
                    :columns="{ default: { container: 10 }, sm: { container: 3 } }"
                    :placeholder="formData?.[name]?.[index]?._generated_label ?? 'Enter Value'"
                    :floating="false" :disabled="[[name + '.*.key_type', ['', 'line']]]" />
            </ObjectElement>
        </template>
    </ListElement>
</template>

<script setup>
const props = defineProps({
    name: String,
    area: String,
    keyTypes: Array,
    formData: Object,
    getNextKeyNumber: Function,
    getKeyValueSelectItems: Function,
    updateLabel: Function,
});

const handleTypeChange = (oldValue, el$, index) => {
    const keyValueSelect = el$.form$.el$(props.name + "." + index + ".key_value_select");

    if (oldValue !== null && oldValue !== undefined) {
        keyValueSelect.clear();
    }

    keyValueSelect.updateItems();
};
</script>
