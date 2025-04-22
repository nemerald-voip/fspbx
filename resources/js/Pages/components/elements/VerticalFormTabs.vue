<template>
    <ul :class="classes.container" role="tablist">
        <slot>
            <FormTab v-for="(tab, name, i) in tabs" v-bind="tab" :name="name" :index="i" :key="name" />
        </slot>
    </ul>
</template>
  
<script>
export default {
    name: 'VerticalFormTabs',
    data() {
        return {
            merge: false,
            defaultClasses: {
                container: 'flex flex-col',
                container_sm: 'form-mb-gutter form-text-sm',
                container_md: 'form-mb-gutter-lg form-text',
                container_lg: 'form-mb-gutter-lg form-text-lg',
                $container: (classes, { Size }) => ([
                    classes.container,
                    classes[`container_${Size}`],
                ]),
            }
        }
    },
}
</script>
  
<style lang="scss"></style>