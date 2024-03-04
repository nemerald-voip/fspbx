<template>
    <MainLayout :menu-options="menus" :domain-select-permission="domainSelectPermission" :selected-domain="selectedDomain"
        :selected-domain-uuid="selectedDomainUuid" :domains="domains">


        <main>
            <!-- <header class="relative isolate">
      <div class="absolute inset-0 -z-10 overflow-hidden" aria-hidden="true">
        <div class="absolute left-16 top-full -mt-16 transform-gpu opacity-50 blur-3xl xl:left-1/2 xl:-ml-80">
          <div class="aspect-[1154/678] w-[72.125rem] bg-gradient-to-br from-[#FF80B5] to-[#9089FC]" style="clip-path: polygon(100% 38.5%, 82.6% 100%, 60.2% 37.7%, 52.4% 32.1%, 47.5% 41.8%, 45.2% 65.6%, 27.5% 23.4%, 0.1% 35.3%, 17.9% 0%, 27.7% 23.4%, 76.2% 2.5%, 74.2% 56%, 100% 38.5%)" />
        </div>
        <div class="absolute inset-x-0 bottom-0 h-px bg-gray-900/5" />
      </div>

      <div class="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8">
        <div class="mx-auto flex max-w-2xl items-center justify-between gap-x-8 lg:mx-0 lg:max-w-none">
          <div class="flex items-center gap-x-6">
            <img src="https://tailwindui.com/img/logos/48x48/tuple.svg" alt="" class="h-16 w-16 flex-none rounded-full ring-1 ring-gray-900/10" />
            <h1>
              <div class="text-sm leading-6 text-gray-500">Invoice <span class="text-gray-700">#00011</span></div>
              <div class="mt-1 text-base font-semibold leading-6 text-gray-900">Tuple, Inc</div>
            </h1>
          </div>
          <div class="flex items-center gap-x-4 sm:gap-x-6">
            <button type="button" class="hidden text-sm font-semibold leading-6 text-gray-900 sm:block">Copy URL</button>
            <a href="#" class="hidden text-sm font-semibold leading-6 text-gray-900 sm:block">Edit</a>
            <a href="#" class="rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">Send</a>

            <Menu as="div" class="relative sm:hidden">
              <MenuButton class="-m-3 block p-3">
                <span class="sr-only">More</span>
                <EllipsisVerticalIcon class="h-5 w-5 text-gray-500" aria-hidden="true" />
              </MenuButton>

              <transition enter-active-class="transition ease-out duration-100" enter-from-class="transform opacity-0 scale-95" enter-to-class="transform opacity-100 scale-100" leave-active-class="transition ease-in duration-75" leave-from-class="transform opacity-100 scale-100" leave-to-class="transform opacity-0 scale-95">
                <MenuItems class="absolute right-0 z-10 mt-0.5 w-32 origin-top-right rounded-md bg-white py-2 shadow-lg ring-1 ring-gray-900/5 focus:outline-none">
                  <MenuItem v-slot="{ active }">
                    <button type="button" :class="[active ? 'bg-gray-50' : '', 'block w-full px-3 py-1 text-left text-sm leading-6 text-gray-900']">Copy URL</button>
                  </MenuItem>
                  <MenuItem v-slot="{ active }">
                    <a href="#" :class="[active ? 'bg-gray-50' : '', 'block px-3 py-1 text-sm leading-6 text-gray-900']">Edit</a>
                  </MenuItem>
                </MenuItems>
              </transition>
            </Menu>
          </div>
        </div>
      </div>
    </header> -->

            <div class="mx-auto max-w-8xl px-4 py-10 sm:px-6 lg:px-8">
                <div
                    class="mx-auto grid max-w-2xl grid-cols-1 grid-rows-1 items-start gap-x-8 gap-y-8 lg:mx-0 lg:max-w-none lg:grid-cols-3">
                    <!-- Company summary -->
                    <div class="lg:col-start-3 lg:row-end-1">
                        <div class="rounded-lg bg-gray-50 shadow-sm ring-1 ring-gray-900/5">

                            <dl class="flex flex-wrap">
                                <div class="flex-auto pl-6 py-6 truncate border-b border-gray-900/5">
                                    <dt class="text-sm  leading-6 text-gray-600">Company name</dt>
                                    <div class="mt-1 text-lg font-semibold leading-6 text-gray-900">{{
                                        company_data.company_name }}</div>
                                </div>

                                <!-- <div class="flex-none self-end px-6 pt-4">
                                    <dt class="sr-only">Status</dt>
                                    <dd
                                        class="rounded-md bg-green-50 px-2 py-1 text-xs font-medium text-green-600 ring-1 ring-inset ring-green-600/20">
                                        Paid</dd>
                                </div> -->

                                <div v-if="Object.keys(data).length === 0" class="w-full">
                                    <div class="animate-pulse flex space-x-4 pt-6 pl-6 w-6/12">
                                    <div class="flex-1 space-y-6 py-1">
                                        <div class="h-2 bg-slate-300 rounded w-10/12"></div>
                                        <div class="grid grid-cols-3 gap-4">
                                            <div class="h-2 bg-slate-300 rounded col-span-2"></div>
                                            <div class="h-2 bg-slate-300 rounded col-span-1"></div>
                                        </div>
                                        <div class="grid grid-cols-3 gap-4 w-9/12">
                                            <div class="h-2 bg-slate-300 rounded col-span-1"></div>
                                            <div class="h-2 bg-slate-300 rounded col-span-2"></div>
                                        </div>

                                    </div>
                                </div>
                                </div>
                               

                                <div v-if="Object.keys(data).length !== 0 && data.extensions && data.extensions >= 0"
                                    class="mt-6 flex w-full flex-none gap-x-4 px-6">
                                    <dt class="flex-none">
                                        <ContactPhoneIcon class="h-6 w-5 text-gray-400" aria-hidden="true" />
                                    </dt>
                                    <dd class="text-sm leading-6 text-gray-500">
                                        <span class="pr-3">Extensions: {{ data.extensions }}</span>
                                        <span v-if="data.local_reg_count && data.local_reg_count >= 0"
                                            class="pr-3 text-green-600 text-nowrap">
                                            Online: {{ data.local_reg_count }}
                                        </span>
                                        <span v-if="data.local_reg_count && data.local_reg_count >= 0"
                                            class=" text-rose-600 text-nowrap">
                                            Offline: {{ data.extensions - data.local_reg_count }}
                                        </span>
                                    </dd>
                                </div>

                                <div v-if="Object.keys(data).length != 0  && data.phone_numbers && data.phone_numbers >= 0" 
                                    class="mt-4 flex w-full flex-none gap-x-4 px-6">
                                    <dt class="flex-none">
                                        <DialpadIcon class="h-6 w-5 text-gray-400" aria-hidden="true" />
                                    </dt>
                                    <dd class="text-sm leading-6 text-gray-500">Phone Numbers: {{ data.phone_numbers }}</dd>
                                </div>

                                <div v-if="Object.keys(data).length != 0  && data.faxes && data.faxes >= 0" 
                                    class="mt-4 flex w-full flex-none gap-x-4 px-6">
                                    <dt class="flex-none">
                                        <FaxIcon class="h-6 w-5 text-gray-400" aria-hidden="true" />
                                    </dt>
                                    <dd class="text-sm leading-6 text-gray-500">Virtual Faxes: {{ data.faxes }}</dd>
                                </div>

                                <div class="mt-4 flex w-full flex-none gap-x-4 px-6 pb-8">
                                    <dt class="flex-none">
                                        <ClockIcon class="h-6 w-5 text-gray-400" aria-hidden="true" />
                                    </dt>
                                    <dd class="text-sm font-medium leading-6 text-gray-500">Time Zone: {{
                                        company_data.time_zone }}</dd>
                                </div>
                            </dl>

                            <!-- <div class="mt-6 border-t border-gray-900/5 px-6 py-6">
                                <a href="#" class="text-sm font-semibold leading-6 text-gray-900">Download receipt <span
                                        aria-hidden="true">&rarr;</span></a>
                            </div> -->
                        </div>
                    </div>

                    <!-- Quick Access -->
                    <div
                        class="-mx-4 px-4 py-8 shadow-sm bg-gray-50 ring-1 ring-gray-900/5 sm:mx-0 sm:rounded-lg sm:px-8 sm:pb-14 lg:col-span-2 lg:row-span-2 lg:row-end-2 xl:px-12 xl:pb-16 xl:pt-12">

                        <h2 class="text-base font-semibold leading-6 text-gray-900">Quick Access</h2>
                        <div class="mt-6 grid grid-cols-1 gap-5 sm:grid-cols-2 xl:grid-cols-3">
                            <div v-for="card in cards" :key="card.name">
                                <DashboardTile :card="card" />
                            </div>
                        </div>

                    </div>

                    <!-- <div class="lg:col-start-3">

                        <h2 class="text-sm font-semibold leading-6 text-gray-900">Activity</h2>
                        <ul role="list" class="mt-6 space-y-6">
                            <li v-for="(activityItem, activityItemIdx) in activity" :key="activityItem.id"
                                class="relative flex gap-x-4">
                                <div
                                    :class="[activityItemIdx === activity.length - 1 ? 'h-6' : '-bottom-6', 'absolute left-0 top-0 flex w-6 justify-center']">
                                    <div class="w-px bg-gray-200" />
                                </div>
                                <template v-if="activityItem.type === 'commented'">
                                    <img :src="activityItem.person.imageUrl" alt=""
                                        class="relative mt-3 h-6 w-6 flex-none rounded-full bg-gray-50" />
                                    <div class="flex-auto rounded-md p-3 ring-1 ring-inset ring-gray-200">
                                        <div class="flex justify-between gap-x-4">
                                            <div class="py-0.5 text-xs leading-5 text-gray-500">
                                                <span class="font-medium text-gray-900">{{ activityItem.person.name
                                                }}</span> commented
                                            </div>
                                            <time :datetime="activityItem.dateTime"
                                                class="flex-none py-0.5 text-xs leading-5 text-gray-500">{{
                                                    activityItem.date }}</time>
                                        </div>
                                        <p class="text-sm leading-6 text-gray-500">{{ activityItem.comment }}</p>
                                    </div>
                                </template>
                                <template v-else>
                                    <div class="relative flex h-6 w-6 flex-none items-center justify-center bg-white">
                                        <CheckCircleIcon v-if="activityItem.type === 'paid'" class="h-6 w-6 text-indigo-600"
                                            aria-hidden="true" />
                                        <div v-else class="h-1.5 w-1.5 rounded-full bg-gray-100 ring-1 ring-gray-300" />
                                    </div>
                                    <p class="flex-auto py-0.5 text-xs leading-5 text-gray-500">
                                        <span class="font-medium text-gray-900">{{ activityItem.person.name }}</span> {{
                                            activityItem.type }} the invoice.
                                    </p>
                                    <time :datetime="activityItem.dateTime"
                                        class="flex-none py-0.5 text-xs leading-5 text-gray-500">{{ activityItem.date
                                        }}</time>
                                </template>
                            </li>
                        </ul>


                        <div class="mt-6 flex gap-x-3">
                            <img src="https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?ixlib=rb-1.2.1&ixid=eyJhcHBfaWQiOjEyMDd9&auto=format&fit=facearea&facepad=2&w=256&h=256&q=80"
                                alt="" class="h-6 w-6 flex-none rounded-full bg-gray-50" />
                            <form action="#" class="relative flex-auto">
                                <div
                                    class="overflow-hidden rounded-lg pb-12 shadow-sm ring-1 ring-inset ring-gray-300 focus-within:ring-2 focus-within:ring-indigo-600">
                                    <label for="comment" class="sr-only">Add your comment</label>
                                    <textarea rows="2" name="comment" id="comment"
                                        class="block w-full resize-none border-0 bg-transparent py-1.5 text-gray-900 placeholder:text-gray-400 focus:ring-0 sm:text-sm sm:leading-6"
                                        placeholder="Add your comment..." />
                                </div>

                                <div class="absolute inset-x-0 bottom-0 flex justify-between py-2 pl-3 pr-2">
                                    <div class="flex items-center space-x-5">
                                        <div class="flex items-center">
                                            <button type="button"
                                                class="-m-2.5 flex h-10 w-10 items-center justify-center rounded-full text-gray-400 hover:text-gray-500">
                                                <PaperClipIcon class="h-5 w-5" aria-hidden="true" />
                                                <span class="sr-only">Attach a file</span>
                                            </button>
                                        </div>
                                        <div class="flex items-center">
                                            <Listbox as="div" v-model="selected">
                                                <ListboxLabel class="sr-only">Your mood</ListboxLabel>
                                                <div class="relative">
                                                    <ListboxButton
                                                        class="relative -m-2.5 flex h-10 w-10 items-center justify-center rounded-full text-gray-400 hover:text-gray-500">
                                                        <span class="flex items-center justify-center">
                                                            <span v-if="selected.value === null">
                                                                <FaceSmileIcon class="h-5 w-5 flex-shrink-0"
                                                                    aria-hidden="true" />
                                                                <span class="sr-only">Add your mood</span>
                                                            </span>
                                                            <span v-if="!(selected.value === null)">
                                                                <span
                                                                    :class="[selected.bgColor, 'flex h-8 w-8 items-center justify-center rounded-full']">
                                                                    <component :is="selected.icon"
                                                                        class="h-5 w-5 flex-shrink-0 text-white"
                                                                        aria-hidden="true" />
                                                                </span>
                                                                <span class="sr-only">{{ selected.name }}</span>
                                                            </span>
                                                        </span>
                                                    </ListboxButton>

                                                    <transition leave-active-class="transition ease-in duration-100"
                                                        leave-from-class="opacity-100" leave-to-class="opacity-0">
                                                        <ListboxOptions
                                                            class="absolute z-10 -ml-6 mt-1 w-60 rounded-lg bg-white py-3 text-base shadow ring-1 ring-black ring-opacity-5 focus:outline-none sm:ml-auto sm:w-64 sm:text-sm">
                                                            <ListboxOption as="template" v-for="mood in moods"
                                                                :key="mood.value" :value="mood" v-slot="{ active }">
                                                                <li
                                                                    :class="[active ? 'bg-gray-100' : 'bg-white', 'relative cursor-default select-none px-3 py-2']">
                                                                    <div class="flex items-center">
                                                                        <div
                                                                            :class="[mood.bgColor, 'flex h-8 w-8 items-center justify-center rounded-full']">
                                                                            <component :is="mood.icon"
                                                                                :class="[mood.iconColor, 'h-5 w-5 flex-shrink-0']"
                                                                                aria-hidden="true" />
                                                                        </div>
                                                                        <span class="ml-3 block truncate font-medium">{{
                                                                            mood.name }}</span>
                                                                    </div>
                                                                </li>
                                                            </ListboxOption>
                                                        </ListboxOptions>
                                                    </transition>
                                                </div>
                                            </Listbox>
                                        </div>
                                    </div>
                                    <button type="submit"
                                        class="rounded-md bg-white px-2.5 py-1.5 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50">Comment</button>
                                </div>
                            </form>
                        </div>
                    </div> -->
                </div>
            </div>
        </main>
    </MainLayout>
</template>
  
<script setup>
import { onMounted } from 'vue'
import { router } from "@inertiajs/vue3";
import MainLayout from '../Layouts/MainLayout.vue'
import DashboardTile from './components/general/DashboardTile.vue'
import ContactPhoneIcon from "./components/icons/ContactPhoneIcon.vue"
import DialpadIcon from "./components/icons/DialpadIcon.vue"
import FaxIcon from "./components/icons/FaxIcon.vue"
import {
    CreditCardIcon,
    ClockIcon,
    XMarkIcon as XMarkIconMini,
} from '@heroicons/vue/20/solid'

onMounted(() => {
    //request list of entities
    getData();
})


const props = defineProps({
    data: {
        type: Object,
        default: () => ({}) // Providing an empty object as default
    },
    company_data: Object,
    cards: Array,
    menus: Array,
    domainSelectPermission: Boolean,
    selectedDomain: String,
    selectedDomainUuid: String,
    domains: Array,
})

const getData = () => {
    router.visit("/dashboard", {
        preserveScroll: true,
        preserveState: true,
        data: {

        },
        only: ["data"],
        onSuccess: (page) => {
            // filterData.value.entity = props.selectedEntity;
            console.log(props.data);
        }

    });

}


</script>