<template>
    <div class="px-4 sm:px-6 lg:px-8">
        <div class="sm:flex sm:items-center">
            <div class="sm:flex-auto">
                <div v-if="$slots.title">
                    <div class="text-lg font-semibold leading-6 text-gray-600">
                        <slot name="title"></slot>
                    </div>
                </div>

                <div v-if="$slots.subtitle">
                    <p class="mt-2 text-sm text-gray-700">
                        <slot name="subtitle"></slot>
                    </p>
                </div>
            </div>
            <div v-if="$slots.action">
                <div class="mt-4 sm:ml-16 sm:mt-0 sm:flex-none">
                    <slot name="action">
                        <!-- Default Action Button -->
                        <button type="button"
                            class="block rounded-md bg-indigo-600 px-3 py-2 text-center text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">Add
                            user</button>
                    </slot>
                </div>
            </div>
        </div>

        <div class="mt-3 sm:ml-4">
            <label for="mobile-search-candidate" class="sr-only">Search</label>
            <label for="desktop-search-candidate" class="sr-only">Search</label>
            <div class="flex flex-col sm:flex-row">
                <div class="relative focus-within:z-10 mb-2 sm:mb-0">
                    <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                        <MagnifyingGlassIcon class="h-5 w-5 text-gray-400" aria-hidden="true" />
                    </div>
                    <input type="text" v-model="searchQuery" name="mobile-search-candidate" id="mobile-search-candidate"
                        class="block w-full rounded-md border-0 py-1.5 pl-10 text-gray-900 ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:hidden"
                        placeholder="Search" />
                    <input type="text" v-model="searchQuery" name="desktop-search-candidate" id="desktop-search-candidate"
                        class="hidden w-full rounded-md border-0 py-1.5 pl-10 text-sm leading-6 text-gray-900 ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:block"
                        placeholder="Search calls" />
                </div>


                <div class="relative -mt-0.5 mb-2 sm:mb-0 sm:ml-4 shrink-0">
                    <VueDatePicker v-model="dateRange" :range="true" :multi-calendars="{ static: false }"
                        :preset-dates="presetDates" :enable-time-picker="false" auto-apply>
                        <template #preset-date-range-button="{ label, value, presetDate }">
                            <span role="button" :tabindex="0" @click="presetDate(value)"
                                @keyup.enter.prevent="presetDate(value)" @keyup.space.prevent="presetDate(value)">
                                {{ label }}
                            </span>
                        </template>
                    </VueDatePicker>
                </div>

                <div class="relative mb-2 sm:mb-0 sm:ml-4">
                    <div class="flex justify-between">

                        <button type="button" @click.prevent="onSearchClick"
                            class="rounded-md bg-indigo-600 px-2.5 py-1.5 text-sm sm:ml-4 font-semibold text-white shadow-sm hover:bg-indigo-500 
                    focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                            Search
                        </button>

                        <button type="button" @click.prevent="onResetClick"
                            class="rounded-md bg-white px-2.5 py-1.5 ml-2  sm:ml-4 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50">
                            Reset
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Table -->

        <div class="mt-8 flow-root">

            <div class="-mx-4 -my-2 overflow-x-auto sm:-mx-6 lg:-mx-8">
                <div class="inline-block min-w-full py-2 align-middle sm:px-6 lg:px-8">
                    <div class="relative overflow-hidden shadow ring-1 ring-black ring-opacity-5 sm:rounded-lg">
                        <slot name="navigation"></slot>
                        <slot name="loading"></slot>
                        <!-- <div class="absolute w-full h-full bg-gray-500 bg-opacity-30"></div> -->
                        <table class="min-w-full divide-y divide-gray-300">
                            <thead class="bg-gray-50">
                                <tr>
                                    <slot name="table-header"></slot>

                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 bg-white">
                                <slot name="table-body"></slot>
                            </tbody>
                        </table>
                        <slot name="empty"></slot>

                        <slot name="footer" />

                    </div>
                </div>
            </div>
        </div>


        <!-- <div v-if="$slots.header" :class="cx('header')" v-bind="ptm('header')">
            <slot name="header"></slot>
        </div>
        <div :class="cx('body')" v-bind="ptm('body')">
            <div v-if="$slots.title" :class="cx('title')" v-bind="ptm('title')">
                <slot name="title"></slot>
            </div>
            <div v-if="$slots.subtitle" :class="cx('subtitle')" v-bind="ptm('subtitle')">
                <slot name="subtitle"></slot>
            </div>
            <div :class="cx('content')" v-bind="ptm('content')">
                <slot name="content"></slot>
            </div>
            <div v-if="$slots.footer" :class="cx('footer')" v-bind="ptm('footer')">
                <slot name="footer"></slot>
            </div>
        </div> -->
    </div>
</template>

<script setup>

import { ref } from 'vue';
import { MagnifyingGlassIcon } from '@heroicons/vue/20/solid'
import VueDatePicker from '@vuepic/vue-datepicker';
import '@vuepic/vue-datepicker/dist/main.css';
import moment from 'moment-timezone';

import {
    startOfDay, endOfDay,
    startOfWeek, endOfWeek,
    subDays,
    startOfMonth, endOfMonth,
    subMonths
} from 'date-fns';

const props = defineProps({
    filterData: Object // 
});

// Initial date range
const dateRange = ref();
dateRange.value = props.filterData.dateRange;

// Initial search
const searchQuery = ref();
searchQuery.value = props.filterData.search;


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


// console.log(props.filterData['timezone']);

const emit = defineEmits(['update:dateRange', 'search-action']);

// Method to handle date changes
// const onDateChange = (newDateRange) => {
// };

const convertToNewTimezoneAndKeepTime = (date) => {
    let localTime = moment(date);
    let convertedDate = moment.tz(localTime.format('YYYY-MM-DDTHH:mm:ss'), props.filterData['timezone']);
    return convertedDate.format();
}

// Method to handle search button click
const onSearchClick = () => {
    const searchData = {
        // dateRange: dateRange.value,
        dateRange: [convertToNewTimezoneAndKeepTime(dateRange.value[0]), convertToNewTimezoneAndKeepTime(dateRange.value[1])],
        searchQuery: searchQuery.value
    };
    emit('search-action', searchData);
};

// Method to handle reset button click
const onResetClick = () => {
    dateRange.value = [startOfDay(today), endOfDay(today)];
    searchQuery.value = null;
    const searchData = {
        dateRange: [startOfDay(today), endOfDay(today)],
        searchQuery: null
    };
    emit('search-action', searchData);
};



</script>