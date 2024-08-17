<template>

<div class="lg:grid lg:grid-cols-12 lg:gap-x-5">
    <aside class="px-2 py-6 sm:px-6 lg:col-span-3 lg:px-0 lg:py-0">
      <nav class="space-y-1">
        <a v-for="item in navigation" :key="item.name" :href="item.href" :class="[item.current ? 'bg-gray-200 text-indigo-700 hover:bg-white hover:text-indigo-700' : 'text-gray-900 hover:bg-gray-200 hover:text-gray-900', 'group flex items-center rounded-md px-3 py-2 text-sm font-medium']" :aria-current="item.current ? 'page' : undefined">
          <component :is="item.icon" :class="[item.current ? 'text-indigo-500 group-hover:text-indigo-500' : 'text-gray-400 group-hover:text-gray-500', '-ml-1 mr-3 h-6 w-6 flex-shrink-0']" aria-hidden="true" />
          <span class="truncate">{{ item.name }}</span>
        </a>
      </nav>
    </aside>

    <div class="space-y-6 sm:px-6 lg:col-span-9 lg:px-0">
      <form action="#" method="POST">
        <div class="shadow sm:overflow-hidden sm:rounded-md">
          <div class="space-y-6 bg-white px-4 py-6 sm:p-6">
            <div>
              <h3 class="text-base font-semibold leading-6 text-gray-900">Profile</h3>
              <p class="mt-1 text-sm text-gray-500">This information will be displayed publicly so be careful what you share.</p>
            </div>

            <div class="grid grid-cols-3 gap-6">
              <div class="col-span-3 sm:col-span-2">
                <label for="company-website" class="block text-sm font-medium leading-6 text-gray-900">Username</label>
                <div class="mt-2 flex rounded-md shadow-sm">
                  <span class="inline-flex items-center rounded-l-md border border-r-0 border-gray-300 px-3 text-gray-500 sm:text-sm">workcation.com/</span>
                  <input type="text" name="username" id="username" autocomplete="username" class="block w-full min-w-0 flex-grow rounded-none rounded-r-md border-0 py-1.5 text-gray-900 ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6" />
                </div>
              </div>

              <div class="col-span-3">
                <label for="about" class="block text-sm font-medium leading-6 text-gray-900">About</label>
                <div class="mt-2">
                  <textarea id="about" name="about" rows="3" class="mt-1 block w-full rounded-md border-0 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6" placeholder="you@example.com" />
                </div>
                <p class="mt-2 text-sm text-gray-500">Brief description for your profile. URLs are hyperlinked.</p>
              </div>

              <div class="col-span-3">
                <label class="block text-sm font-medium leading-6 text-gray-900">Photo</label>
                <div class="mt-2 flex items-center">
                  <span class="inline-block h-12 w-12 overflow-hidden rounded-full bg-gray-100">
                    <svg class="h-full w-full text-gray-300" fill="currentColor" viewBox="0 0 24 24">
                      <path d="M24 20.993V24H0v-2.996A14.977 14.977 0 0112.004 15c4.904 0 9.26 2.354 11.996 5.993zM16.002 8.999a4 4 0 11-8 0 4 4 0 018 0z" />
                    </svg>
                  </span>
                  <button type="button" class="ml-5 rounded-md bg-white px-2.5 py-1.5 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50">Change</button>
                </div>
              </div>

              <div class="col-span-3">
                <label class="block text-sm font-medium leading-6 text-gray-900">Cover photo</label>
                <div class="mt-2 flex justify-center rounded-md border-2 border-dashed border-gray-300 px-6 pb-6 pt-5">
                  <div class="space-y-1 text-center">
                    <svg class="mx-auto h-12 w-12 text-gray-400" stroke="currentColor" fill="none" viewBox="0 0 48 48" aria-hidden="true">
                      <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                    <div class="flex text-sm text-gray-600">
                      <label for="file-upload" class="relative cursor-pointer rounded-md bg-white font-medium text-indigo-600 focus-within:outline-none focus-within:ring-2 focus-within:ring-indigo-500 focus-within:ring-offset-2 hover:text-indigo-500">
                        <span>Upload a file</span>
                        <input id="file-upload" name="file-upload" type="file" class="sr-only" />
                      </label>
                      <p class="pl-1">or drag and drop</p>
                    </div>
                    <p class="text-xs text-gray-500">PNG, JPG, GIF up to 10MB</p>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div class="bg-gray-50 px-4 py-3 text-right sm:px-6">
            <button type="submit" class="inline-flex justify-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-600 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">Save</button>
          </div>
        </div>
      </form>

      <form action="#" method="POST">
        <div class="shadow sm:overflow-hidden sm:rounded-md">
          <div class="space-y-6 bg-white px-4 py-6 sm:p-6">
            <div>
              <h3 class="text-base font-semibold leading-6 text-gray-900">Personal Information</h3>
              <p class="mt-1 text-sm text-gray-500">Use a permanent address where you can recieve mail.</p>
            </div>

            <div class="grid grid-cols-6 gap-6">
              <div class="col-span-6 sm:col-span-3">
                <label for="first-name" class="block text-sm font-medium leading-6 text-gray-900">First name</label>
                <input type="text" name="first-name" id="first-name" autocomplete="given-name" class="mt-2 block w-full rounded-md border-0 px-3 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6" />
              </div>

              <div class="col-span-6 sm:col-span-3">
                <label for="last-name" class="block text-sm font-medium leading-6 text-gray-900">Last name</label>
                <input type="text" name="last-name" id="last-name" autocomplete="family-name" class="mt-2 block w-full rounded-md border-0 px-3 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6" />
              </div>

              <div class="col-span-6 sm:col-span-4">
                <label for="email-address" class="block text-sm font-medium leading-6 text-gray-900">Email address</label>
                <input type="text" name="email-address" id="email-address" autocomplete="email" class="mt-2 block w-full rounded-md border-0 px-3 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6" />
              </div>

              <div class="col-span-6 sm:col-span-3">
                <label for="country" class="block text-sm font-medium leading-6 text-gray-900">Country</label>
                <select id="country" name="country" autocomplete="country-name" class="mt-2 block w-full rounded-md border-0 bg-white py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6">
                  <option>United States</option>
                  <option>Canada</option>
                  <option>Mexico</option>
                </select>
              </div>

              <div class="col-span-6">
                <label for="street-address" class="block text-sm font-medium leading-6 text-gray-900">Street address</label>
                <input type="text" name="street-address" id="street-address" autocomplete="street-address" class="mt-2 block w-full rounded-md border-0 px-3 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6" />
              </div>

              <div class="col-span-6 sm:col-span-6 lg:col-span-2">
                <label for="city" class="block text-sm font-medium leading-6 text-gray-900">City</label>
                <input type="text" name="city" id="city" autocomplete="address-level2" class="mt-2 block w-full rounded-md border-0 px-3 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6" />
              </div>

              <div class="col-span-6 sm:col-span-3 lg:col-span-2">
                <label for="region" class="block text-sm font-medium leading-6 text-gray-900">State / Province</label>
                <input type="text" name="region" id="region" autocomplete="address-level1" class="mt-2 block w-full rounded-md border-0 px-3 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6" />
              </div>

              <div class="col-span-6 sm:col-span-3 lg:col-span-2">
                <label for="postal-code" class="block text-sm font-medium leading-6 text-gray-900">ZIP / Postal code</label>
                <input type="text" name="postal-code" id="postal-code" autocomplete="postal-code" class="mt-2 block w-full rounded-md border-0 px-3 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6" />
              </div>
            </div>
          </div>
          <div class="bg-gray-50 px-4 py-3 text-right sm:px-6">
            <button type="submit" class="inline-flex justify-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">Save</button>
          </div>
        </div>
      </form>

      <form action="#" method="POST">
        <div class="shadow sm:overflow-hidden sm:rounded-md">
          <div class="space-y-6 bg-white px-4 py-6 sm:p-6">
            <div>
              <h3 class="text-base font-semibold leading-6 text-gray-900">Notifications</h3>
              <p class="mt-1 text-sm text-gray-500">Provide basic information about the job. Be specific with the job title.</p>
            </div>

            <fieldset>
              <legend class="text-sm font-semibold leading-6 text-gray-900">By Email</legend>
              <div class="mt-4 space-y-4">
                <div class="flex items-start">
                  <div class="flex h-6 items-center">
                    <input id="comments" name="comments" type="checkbox" class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                  </div>
                  <div class="ml-3 text-sm leading-6">
                    <label for="comments" class="font-medium text-gray-900">Comments</label>
                    <p class="text-gray-500">Get notified when someones posts a comment on a posting.</p>
                  </div>
                </div>
                <div>
                  <div class="flex items-start">
                    <div class="flex h-6 items-center">
                      <input id="candidates" name="candidates" type="checkbox" class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                    </div>
                    <div class="ml-3 text-sm leading-6">
                      <label for="candidates" class="font-medium text-gray-900">Candidates</label>
                      <p class="text-gray-500">Get notified when a candidate applies for a job.</p>
                    </div>
                  </div>
                </div>
                <div>
                  <div class="flex items-start">
                    <div class="flex h-6 items-center">
                      <input id="offers" name="offers" type="checkbox" class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                    </div>
                    <div class="ml-3 text-sm leading-6">
                      <label for="offers" class="font-medium text-gray-900">Offers</label>
                      <p class="text-gray-500">Get notified when a candidate accepts or rejects an offer.</p>
                    </div>
                  </div>
                </div>
              </div>
            </fieldset>
            <fieldset class="mt-6">
              <legend class="text-sm font-semibold leading-6 text-gray-900">Push Notifications</legend>
              <p class="text-sm text-gray-500">These are delivered via SMS to your mobile phone.</p>
              <div class="mt-4 space-y-4">
                <div class="flex items-center">
                  <input id="push-everything" name="push-notifications" type="radio" class="h-4 w-4 border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                  <label for="push-everything" class="ml-3">
                    <span class="block text-sm font-medium leading-6 text-gray-900">Everything</span>
                  </label>
                </div>
                <div class="flex items-center">
                  <input id="push-email" name="push-notifications" type="radio" class="h-4 w-4 border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                  <label for="push-email" class="ml-3">
                    <span class="block text-sm font-medium leading-6 text-gray-900">Same as email</span>
                  </label>
                </div>
                <div class="flex items-center">
                  <input id="push-nothing" name="push-notifications" type="radio" class="h-4 w-4 border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                  <label for="push-nothing" class="ml-3">
                    <span class="block text-sm font-medium leading-6 text-gray-900">No push notifications</span>
                  </label>
                </div>
              </div>
            </fieldset>
          </div>
          <div class="bg-gray-50 px-4 py-3 text-right sm:px-6">
            <button type="submit" class="inline-flex justify-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">Save</button>
          </div>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup>
import { reactive } from "vue";
import { usePage } from '@inertiajs/vue3';


import SelectBox from "../general/SelectBox.vue";
import ComboBox from "../general/ComboBox.vue";
import InputField from "../general/InputField.vue";
import LabelInputOptional from "../general/LabelInputOptional.vue";
import LabelInputRequired from "../general/LabelInputRequired.vue";
import Spinner from "../general/Spinner.vue";

//Delete next line
import { CreditCardIcon, KeyIcon, SquaresPlusIcon, UserCircleIcon, UserGroupIcon } from '@heroicons/vue/24/outline'


const props = defineProps({
    options: Object,
    isSubmitting: Boolean,
    errors: Object,
});

const navigation = [
  { name: 'Account', href: '#', icon: UserCircleIcon, current: true },
  { name: 'Password', href: '#', icon: KeyIcon, current: false },
  { name: 'Plan & Billing', href: '#', icon: CreditCardIcon, current: false },
  { name: 'Team', href: '#', icon: UserGroupIcon, current: false },
  { name: 'Integrations', href: '#', icon: SquaresPlusIcon, current: false },
]

const page = usePage();

const form = reactive({
    device_address: null,
    device_template: null,
    device_profile_uuid: null,
    extension: null,
    _token: page.props.csrf_token,
})

const emits = defineEmits(['submit', 'cancel']);

const submitForm = () => {
    emits('submit', form); // Emit the event with the form data
}

const handleTemplateUpdate = (newSelectedItem) => {
    form.device_template = newSelectedItem.value
}

const handleProfileUpdate = (newSelectedItem) => {
    form.device_profile_uuid = newSelectedItem.value
}

const handleExtensionUpdate = (newSelectedItem) => {
    form.extension = newSelectedItem.value
}

</script>