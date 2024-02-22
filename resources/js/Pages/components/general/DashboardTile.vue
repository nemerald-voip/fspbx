<template>
<div  class="overflow-hidden rounded-lg bg-white shadow">
    <div class="p-5">
      <div class="flex items-center">
        <div class="flex-shrink-0">
          <component :is="card.icon" class="h-6 w-6 text-gray-400" aria-hidden="true" />
        </div>
        <div class="ml-5 w-0 flex-1">
          <dl>
            <dt class="truncate text-lg font-medium text-gray-500">{{ card.name }}</dt>
            <dd>
              <div class="text-md font-medium text-gray-900">{{ card.amount }}</div>
            </dd>
          </dl>
        </div>
      </div>
    </div>
    <div class="bg-gray-50 px-5 py-3">
      <div class="text-sm">
        <a :href="card.href" class="font-medium text-cyan-700 hover:text-cyan-900">View all</a>
      </div>
    </div>
</div>
</template>

<script setup>

const props = defineProps({
    card: Array,
})

</script>