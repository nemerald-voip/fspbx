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

<script>
import { defineComponent } from 'vue';

defineComponent({
    name: 'DataTable',
});
</script>