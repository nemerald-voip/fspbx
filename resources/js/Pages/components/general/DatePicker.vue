<template>
    <VueDatePicker v-model="dateRange" :range="true" :multi-calendars="{ static: false }" :preset-dates="presetDates"
        :enable-time-picker="false" auto-apply @update:model-value="handleDate">
        <template #preset-date-range-button="{ label, value, presetDate }">
            <span role="button" :tabindex="0" @click="presetDate(value)" @keyup.enter.prevent="presetDate(value)"
                @keyup.space.prevent="presetDate(value)">
                {{ label }}
            </span>
        </template>
    </VueDatePicker>
</template>

<script setup>
import { ref } from 'vue';
import VueDatePicker from '@vuepic/vue-datepicker';
import moment from 'moment-timezone';

import {
    startOfDay, endOfDay,
    startOfWeek, endOfWeek,
    subDays,
    startOfMonth, endOfMonth,
    subMonths
} from 'date-fns';

const props = defineProps({
    dateRange: Array,
    timezone: String,
});

// Initial date range
const dateRange = ref();
dateRange.value = props.dateRange;


// const today = moment().tz(props.filterData['timezone']).toDate();
// console.log(dateRange.value[0]);
const today = new Date();
// console.log(today);
// console.log(startOfDay(today));

const presetDates = ref([
    { label: 'Today', value: [startOfDay(today), endOfDay(today)] },
    { label: 'This Week', value: [startOfWeek(startOfDay(today)), endOfWeek(endOfDay(today))] },
    { label: 'Past 7 Days', value: [subDays(startOfDay(today), 6), endOfDay(today)] },
    { label: 'Past 30 Days', value: [subDays(startOfDay(today), 29), endOfDay(today)] },
    { label: 'This Month', value: [startOfMonth(startOfDay(today)), endOfMonth(endOfDay(today))] },
    { label: 'Last Month', value: [startOfMonth(subMonths(startOfDay(today), 1)), endOfMonth(subMonths(endOfDay(today), 1))] }
]);

const emit = defineEmits(['update:dateRange']);

const convertToNewTimezoneAndKeepTime = (date) => {
    let localTime = moment(date);
    let convertedDate = moment.tz(localTime.format('YYYY-MM-DDTHH:mm:ss'), props.timezone);
    return convertedDate.format();
}

const handleDate = (modelData) => {
    dateRange.value = [convertToNewTimezoneAndKeepTime(dateRange.value[0]), convertToNewTimezoneAndKeepTime(dateRange.value[1])], 
    emit('update:dateRange', dateRange.value);
}

</script>

<style>
@import '@vuepic/vue-datepicker/dist/main.css';
</style>