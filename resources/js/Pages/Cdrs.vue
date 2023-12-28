<template>
    <main>
        <h1>Playground</h1>
        <Button label="Primary" />
        <Button label="Secondary" severity="secondary" rounded/>
        <Button label="Success" severity="success" rounded/>
        <Button label="Info" severity="info" text/>
        <Button label="Warning" severity="warning" text/>
        <Button label="Help" severity="help" />
        <Button label="Danger" severity="danger" />

        <Button label="Submit" icon="pi pi-check" />

        <MultiSelect v-model="selectedCities" display="chip" :options="cities" placeholder="Select Cities"
    :maxSelectedLabels="3" class="w-full md:w-20rem" />
    </main>
</template>


<script setup>
import { ref } from 'vue';
import Button from 'primevue/button';
import MultiSelect from 'primevue/multiselect';

const cities = ref([
    'London',
    'New York',
    'Amsterdam'
])
const selectedCities = ref();

const msg = ref('');
</script>