<template>
    <main class="grid min-h-full place-items-center bg-surface px-6 py-24 sm:py-32 lg:px-8">
      <div class="text-center">
        <p class="text-base font-semibold text-accent-fg">{{ status }}</p>
        <h1 class="mt-4 text-3xl font-bold tracking-tight text-heading sm:text-5xl">{{ error }}</h1>
        <p class="mt-6 text-base leading-7 text-body">{{ message }}</p>
        <div class="mt-10 flex items-center justify-center gap-x-6">
          <a href="/" class="rounded-md bg-accent px-3.5 py-2.5 text-sm font-semibold text-on-accent shadow-sm hover:bg-accent-hover focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent">Go back home</a>
          <!-- <a href="/contact-support" class="text-sm font-semibold text-heading">Contact support <span aria-hidden="true">&rarr;</span></a> -->
        </div>
      </div>
    </main>
  </template>
  
  <script setup>
  const props = defineProps({
    status: Number,
    error: String,
    message: String,
  });
  </script>
  