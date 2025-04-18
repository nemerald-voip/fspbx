<template>
    <li v-show="visible" :class="classes.container">
        <div :class="classes.wrapper" tabindex="0" role="tab" :aria-selected="active" @click.prevent="select"
            @keypress.enter.space.prevent="select">
            <slot>
                <!-- If label is a component -->
                <span v-if="isLabelComponent">
                    <component :is="tabLabel" :form$="form$" />
                </span>

                <!-- If label is HTML -->
                <span v-else v-html="tabLabel"></span>
            </slot>
        </div>
    </li>
</template>
  
<script>
export default {
    name: 'FormTab',
    data() {
        return {
            merge: false,
            defaultClasses: {
                container: 'cursor-pointer',
                wrapper: 'mb-1 hover:bg-gray-200 hover:text-gray-900 group flex items-center rounded-md px-3 py-2',
                wrapper_active: 'bg-gray-200 text-indigo-700 hover:bg-indigo-100 hover:text-indigo-700',
                wrapper_inactive: 'border-transparent',
                wrapper_valid: '',
                wrapper_invalid: 'form-color-danger form-border-color-danger',
                wrapper_sm: 'py-1.5 px-3.5',
                wrapper_md: 'py-2 px-4',
                wrapper_lg: 'py-2 px-4',
                $container: (classes, { }) => ([
                    classes.container,
                ]),
                $wrapper: (classes, { active, invalid, Size }) => ([
                    classes.wrapper,
                    classes[`wrapper_${Size}`],
                    active ? classes.wrapper_active : classes.wrapper_inactive,
                    invalid ? classes.wrapper_invalid : classes.wrapper_valid,
                ]),
            },
        }
    }
}
</script>
  
<style lang="scss"></style>