<template>
    <Popover class="relative">
        <PopoverButton class="inline-flex items-center gap-x-1 text-sm font-semibold leading-6 text-gray-900">
            <slot name="popover-button"></slot>
        </PopoverButton>

        <transition enter-active-class="transition ease-out duration-200" enter-from-class="opacity-0 translate-y-1"
            enter-to-class="opacity-100 translate-y-0" leave-active-class="transition ease-in duration-150"
            leave-from-class="opacity-100 translate-y-0" leave-to-class="opacity-0 translate-y-1">
            <PopoverPanel class="absolute left-1/2 z-10 mt-3 flex w-screen max-w-max -translate-x-1/2 px-4">
                <div
                    class="w-screen max-w-64 flex-auto rounded-xl bg-white text-sm leading-6 shadow-xl ring-2 ring-gray-900/5">
                    <div class="relative rounded-lg p-3 hover:bg-gray-50">
                        <slot name="popover-panel"></slot>
                    </div>
                </div>
            </PopoverPanel>
        </transition>
    </Popover>

</template>
  
<script setup>
import { Popover, PopoverButton, PopoverPanel } from '@headlessui/vue'


// const props = defineProps({
//     text: {
//         type: String,
//         default: ''
//     },
//     style: {
//         type: Object,
//         default: () => ({})
//     },
//     class: {
//         type: String,
//         default: ''
//     }
// });
</script>
  