<template>
    <div class="p-4">
      <h2 class="text-xl font-bold mb-4">Emergency Call Groups</h2>
  
      <table class="min-w-full border rounded shadow text-sm">
        <thead class="bg-gray-100">
          <tr>
            <th class="text-left px-4 py-2">Number</th>
            <th class="text-left px-4 py-2">Description</th>
            <th class="text-left px-4 py-2">Members</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="call in emergencyCalls" :key="call.id" class="border-t">
            <td class="px-4 py-2">{{ call.emergency_number }}</td>
            <td class="px-4 py-2">{{ call.description || '-' }}</td>
            <td class="px-4 py-2">{{ call.members.length }}</td>
          </tr>
        </tbody>
      </table>
  
      <div v-if="loading" class="mt-4 text-gray-500">Loading...</div>
      <div v-if="error" class="mt-4 text-red-500">Error: {{ error }}</div>
    </div>
  </template>

<script setup>
import { ref, onMounted } from 'vue';

const props = defineProps({
    routes: Object,
})

const emergencyCalls = ref([]);
const loading = ref(false);
const error = ref(null);

onMounted(async () => {
  loading.value = true;
  try {
    const response = await axios.get(props.routes.emergency_calls);
    emergencyCalls.value = response.data;
  } catch (err) {
    error.value = err.response?.data?.message || err.message;
  } finally {
    loading.value = false;
  }
});

</script>