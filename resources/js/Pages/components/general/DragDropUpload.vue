<template>
    <!-- Drag and drop area -->
    <div 
      class="border-2 border-dashed rounded-lg p-6 min-h-32 flex flex-col items-center justify-center text-center cursor-pointer"
      :class="isDragging ? 'border-blue-500 bg-blue-50' : 'border-gray-300'"
      @click="browseFile"
      @dragenter.prevent="isDragging = true"
      @dragover.prevent
      @dragleave="isDragging = false"
      @drop="dropFile($event)"
    >
      <!-- Hidden file input for selecting files -->
      <input 
        ref="fileInput" 
        type="file" 
        class="hidden" 
        accept=".xlsx, .xls, .csv" 
        @change="handleFileSelect"
      />
      <!-- Prompt text when no file is selected -->
      <p v-if="!fileName" class="text-gray-600">
        <span class="font-semibold text-gray-700">Drag & drop</span> an Excel/CSV file here, or <span class="text-blue-600 underline">browse</span>
      </p>
      <!-- Display selected file name -->
      <p v-else class="text-gray-700">
        Selected file: <span class="font-medium">{{ fileName }}</span>
      </p>
    </div>
    <!-- Error message display -->
    <p v-if="error" class="mt-2 text-red-600 text-sm">{{ error }}</p>
  </template>
  
  <script setup>
  import { ref } from 'vue'
  
  // Define an emitter to send the valid file to the parent
  const emit = defineEmits(['file-selected'])
  
  const fileInput = ref(null)
  const fileName = ref('')
  const error = ref('')
  const isDragging = ref(false)
  
  // Trigger file input dialog
  function browseFile() {
    error.value = ''  // reset any previous error
    fileInput.value?.click()
  }
  
  // Handle file selection via dialog
  function handleFileSelect(event) {
    const files = event.target.files
    if (!files || !files.length) return
    const file = files[0]
    processFile(file)
    // Reset file input value to allow re-selecting the same file if needed
    event.target.value = ''
  }
  
  // Handle file drop
  function dropFile(event) {
    event.preventDefault()
    isDragging.value = false
    if (event.dataTransfer?.files && event.dataTransfer.files.length > 0) {
      const file = event.dataTransfer.files[0]
      processFile(file)
      event.dataTransfer.clearData()
    }
  }
  
  // Validate the file and emit the event if valid
  function processFile(file) {
    error.value = ''
    
    // Validate file type by extension
    const allowedExtensions = ['xlsx', 'xls', 'csv']
    const fileExt = file.name.split('.').pop().toLowerCase()
    if (!allowedExtensions.includes(fileExt)) {
      error.value = 'Invalid file type. Only .xlsx, .xls, and .csv files are allowed.'
      return
    }
    
    // Validate file size (max 5MB)
    const maxSize = 5 * 1024 * 1024  // 5MB in bytes
    if (file.size > maxSize) {
      error.value = 'File size exceeds the 5MB limit.'
      return
    }
    
    // File is valid – update the file name and emit the file to the parent
    fileName.value = file.name
    emit('file-selected', file)
  }
  </script>
  
  <style scoped>
  /* No additional CSS is needed since Tailwind CSS is used */
  </style>
  