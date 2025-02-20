<template>
    <MainLayout>

        <main class="mx-auto max-w-full pb-10 lg:px-8 lg:py-12">
            <div class=" rounded-lg bg-white px-4 pb-4 pt-10 text-left shadow-xl lg:grid lg:grid-cols-12 lg:gap-x-5">
                <aside class="px-2 py-6 sm:px-6 lg:col-span-2 lg:px-0 lg:py-0">
                    <nav class="space-y-1">
                        <a v-for="item in navigation" :key="item.name" href="#"
                            :class="[activeTab === item.slug ? 'bg-gray-200 text-indigo-600 hover:bg-gray-100' : 'text-gray-900 hover:bg-gray-200 hover:text-gray-900', 'group flex items-center rounded-md px-3 py-2 text-sm font-medium']"
                            @click.prevent="setActiveTab(item.slug)"
                            :aria-current="activeTab === item.slug ? 'page' : undefined">
                            <component :is="iconComponents[item.icon]"
                                :class="[activeTab === item.slug ? 'text-indigo-500' : 'text-gray-400  group-hover:text-gray-500', '-ml-1 mr-3 size-6 shrink-0']"
                                aria-hidden="true" />
                            <span class="truncate">{{ item.name }}</span>
                            <ExclamationCircleIcon v-if="((errors?.voicemail_id || errors?.voicemail_password) && item.slug === 'settings') ||
                                (errors?.voicemail_alternate_greet_id && item.slug === 'advanced')"
                                class="ml-2 h-5 w-5 text-red-500" aria-hidden="true" />
                        </a>
                    </nav>
                </aside>

                <div v-if="activeTab === 'settings'" class="space-y-6 sm:px-6 lg:col-span-10 lg:px-0">
                    <section aria-labelledby="settings-heading">
                        <div class="shadow sm:overflow-hidden sm:rounded-md">

                            <div class="space-y-6 bg-gray-100 px-4 py-6 sm:p-6">
                                <div class="flex justify-between items-center">
                                    <h3 id="settings-heading" class="text-base font-semibold leading-6 text-gray-900">
                                        Settings</h3>

                                    <Toggle label="Status" v-model="data.domain_enabled" />

                                    <!-- <p class="mt-1 text-sm text-gray-500"></p> -->
                                </div>

                                <div class="grid grid-cols-12 gap-6">
                                    <div class="col-span-6">
                                        <LabelInputOptional :target="'domain_description'" :label="'Account Name'" />
                                        <div class="mt-2">
                                            <InputField v-model="localData.domain_description" type="text"
                                                id="domain_description" name="domain_description"
                                                placeholder="Enter caller prefix"
                                                :error="errors?.domain_description && errors.domain_description.length > 0" />
                                        </div>
                                    </div>
                                    <div class="col-span-6">
                                        <LabelInputOptional :target="'domain_name'" :label="'Domain'" />
                                        <div class="mt-2">
                                            <InputField v-model="localData.domain_name" type="text" :disabled="true"
                                                id="domain_name" name="domain_name" placeholder="Enter caller prefix"
                                                :error="errors?.domain_name && errors.domain_name.length > 0" />
                                        </div>
                                    </div>


                                </div>

                            </div>
                            <div class="bg-gray-100 px-4 py-3 text-right sm:px-6">

                                <button @click.prevent="saveSettings"
                                    class="inline-flex justify-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 sm:col-start-2"
                                    :disabled="isSubmitting">
                                    <Spinner :show="isSubmitting" />
                                    Save
                                </button>
                            </div>
                        </div>
                    </section>
                </div>

                <div v-if="activeTab === 'billing'" class="space-y-6 sm:px-6 lg:col-span-10 lg:px-0">
                    <section aria-labelledby="payment-details-heading">
                        <form action="#" method="POST">
                            <div class="shadow sm:overflow-hidden sm:rounded-md">
                                <div class="bg-gray-100 px-4 py-6 sm:p-6">
                                    <div>
                                        <h2 id="payment-details-heading" class="text-lg/6 font-medium text-gray-900">
                                            Payment
                                            details</h2>
                                        <p class="mt-1 text-sm text-gray-500">Update your billing information. Please
                                            note
                                            that updating your location could affect your tax rates.</p>
                                    </div>

                                    <div class="mt-6 grid grid-cols-4 gap-6">
                                        <div class="col-span-4 sm:col-span-2">
                                            <label for="first-name" class="block text-sm/6 font-medium text-gray-900">First
                                                name</label>
                                            <input type="text" name="first-name" id="first-name"
                                                autocomplete="cc-given-name"
                                                class="mt-2 block w-full rounded-md bg-white px-3 py-1.5 text-base text-gray-900 outline outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-400 focus:outline focus:outline-2 focus:-outline-offset-2 focus:outline-gray-900 sm:text-sm/6" />
                                        </div>

                                        <div class="col-span-4 sm:col-span-2">
                                            <label for="last-name" class="block text-sm/6 font-medium text-gray-900">Last
                                                name</label>
                                            <input type="text" name="last-name" id="last-name" autocomplete="cc-family-name"
                                                class="mt-2 block w-full rounded-md bg-white px-3 py-1.5 text-base text-gray-900 outline outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-400 focus:outline focus:outline-2 focus:-outline-offset-2 focus:outline-gray-900 sm:text-sm/6" />
                                        </div>

                                        <div class="col-span-4 sm:col-span-2">
                                            <label for="email-address"
                                                class="block text-sm/6 font-medium text-gray-900">Email address</label>
                                            <input type="text" name="email-address" id="email-address" autocomplete="email"
                                                class="mt-2 block w-full rounded-md bg-white px-3 py-1.5 text-base text-gray-900 outline outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-400 focus:outline focus:outline-2 focus:-outline-offset-2 focus:outline-gray-900 sm:text-sm/6" />
                                        </div>

                                        <div class="col-span-4 sm:col-span-1">
                                            <label for="expiration-date"
                                                class="block text-sm/6 font-medium text-gray-900">Expration date</label>
                                            <input type="text" name="expiration-date" id="expiration-date"
                                                autocomplete="cc-exp"
                                                class="mt-2 block w-full rounded-md bg-white px-3 py-1.5 text-base text-gray-900 outline outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-400 focus:outline focus:outline-2 focus:-outline-offset-2 focus:outline-gray-900 sm:text-sm/6"
                                                placeholder="MM / YY" />
                                        </div>

                                        <div class="col-span-4 sm:col-span-1">
                                            <label for="security-code"
                                                class="flex items-center text-sm/6 font-medium text-gray-900">
                                                <span>Security code</span>
                                                <QuestionMarkCircleIcon class="ml-1 size-5 shrink-0 text-gray-300"
                                                    aria-hidden="true" />
                                            </label>
                                            <input type="text" name="security-code" id="security-code" autocomplete="cc-csc"
                                                class="mt-2 block w-full rounded-md bg-white px-3 py-1.5 text-base text-gray-900 outline outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-400 focus:outline focus:outline-2 focus:-outline-offset-2 focus:outline-gray-900 sm:text-sm/6" />
                                        </div>

                                        <div class="col-span-4 sm:col-span-2">
                                            <label for="country"
                                                class="block text-sm/6 font-medium text-gray-900">Country</label>
                                            <div class="mt-2 grid grid-cols-1">
                                                <select id="country" name="country" autocomplete="country-name"
                                                    class="col-start-1 row-start-1 w-full appearance-none rounded-md bg-white py-1.5 pl-3 pr-8 text-base text-gray-900 outline outline-1 -outline-offset-1 outline-gray-300 focus:outline focus:outline-2 focus:-outline-offset-2 focus:outline-gray-900 sm:text-sm/6">
                                                    <option>United States</option>
                                                    <option>Canada</option>
                                                    <option>Mexico</option>
                                                </select>
                                                <ChevronDownIcon
                                                    class="pointer-events-none col-start-1 row-start-1 mr-2 size-5 self-center justify-self-end text-gray-500 sm:size-4"
                                                    aria-hidden="true" />
                                            </div>
                                        </div>

                                        <div class="col-span-4 sm:col-span-2">
                                            <label for="postal-code" class="block text-sm/6 font-medium text-gray-900">ZIP /
                                                Postal code</label>
                                            <input type="text" name="postal-code" id="postal-code"
                                                autocomplete="postal-code"
                                                class="mt-2 block w-full rounded-md bg-white px-3 py-1.5 text-base text-gray-900 outline outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-400 focus:outline focus:outline-2 focus:-outline-offset-2 focus:outline-gray-900 sm:text-sm/6" />
                                        </div>
                                    </div>
                                </div>
                                <div class="bg-gray-50 px-4 py-3 text-right sm:px-6">
                                    <button type="submit"
                                        class="inline-flex justify-center rounded-md bg-gray-900 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-gray-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-gray-900">Save</button>
                                </div>
                            </div>
                        </form>
                    </section>

                    <!-- Plan -->
                    <section aria-labelledby="plan-heading">
                        <form action="#" method="POST">
                            <div class="shadow sm:overflow-hidden sm:rounded-md">
                                <div class="space-y-6 bg-white px-4 py-6 sm:p-6">
                                    <div>
                                        <h2 id="plan-heading" class="text-lg/6 font-medium text-gray-900">Plan</h2>
                                    </div>

                                    <fieldset aria-label="Pricing plans" class="relative -space-y-px rounded-md bg-white">
                                        <label v-for="plan in plans" :key="plan.name" :aria-label="plan.name"
                                            :aria-description="`${plan.priceMonthly} per month, ${plan.priceYearly} per year, ${plan.limit}`"
                                            class="group flex cursor-pointer flex-col border border-gray-200 p-4 first:rounded-tl-md first:rounded-tr-md last:rounded-bl-md last:rounded-br-md focus:outline-none has-[:checked]:relative has-[:checked]:border-orange-200 has-[:checked]:bg-orange-50 md:grid md:grid-cols-3 md:pl-4 md:pr-6">
                                            <span class="flex items-center gap-3 text-sm">
                                                <input name="pricing-plan" :value="plan.name" type="radio"
                                                    :checked="plan.selected"
                                                    class="relative size-4 appearance-none rounded-full border border-gray-300 bg-white before:absolute before:inset-1 before:rounded-full before:bg-white checked:border-orange-600 checked:bg-orange-600 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-orange-600 disabled:border-gray-300 disabled:bg-gray-100 disabled:before:bg-gray-400 forced-colors:appearance-auto forced-colors:before:hidden [&:not(:checked)]:before:hidden" />
                                                <span
                                                    class="font-medium text-gray-900 group-has-[:checked]:text-orange-900">{{
                                                        plan.name }}</span>
                                            </span>
                                            <span class="ml-6 pl-1 text-sm md:ml-0 md:pl-0 md:text-center">
                                                <span
                                                    class="font-medium text-gray-900 group-has-[:checked]:text-orange-900">{{
                                                        plan.priceMonthly }} / mo</span>
                                                {{ ' ' }}
                                                <span class="text-gray-500 group-has-[:checked]:text-orange-700">({{
                                                    plan.priceYearly }} / yr)</span>
                                            </span>
                                            <span
                                                class="ml-6 pl-1 text-sm text-gray-500 group-has-[:checked]:text-orange-700 md:ml-0 md:pl-0 md:text-right">{{
                                                    plan.limit }}</span>
                                        </label>
                                    </fieldset>


                                </div>
                                <div class="bg-gray-50 px-4 py-3 text-right sm:px-6">
                                    <button type="submit"
                                        class="inline-flex justify-center rounded-md bg-gray-900 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-gray-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-gray-900">Save</button>
                                </div>
                            </div>
                        </form>
                    </section>

                    <!-- Billing history -->
                    <section aria-labelledby="billing-history-heading">
                        <div class="bg-white pt-6 shadow sm:overflow-hidden sm:rounded-md">
                            <div class="px-4 sm:px-6">
                                <h2 id="billing-history-heading" class="text-lg/6 font-medium text-gray-900">Billing
                                    history
                                </h2>
                            </div>
                            <div class="mt-6 flex flex-col">
                                <div class="-my-2 overflow-x-auto sm:-mx-6 lg:-mx-8">
                                    <div class="inline-block min-w-full py-2 align-middle sm:px-6 lg:px-8">
                                        <div class="overflow-hidden border-t border-gray-200">
                                            <table class="min-w-full divide-y divide-gray-200">
                                                <thead class="bg-gray-50">
                                                    <tr>
                                                        <th scope="col"
                                                            class="px-6 py-3 text-left text-sm font-semibold text-gray-900">
                                                            Date</th>
                                                        <th scope="col"
                                                            class="px-6 py-3 text-left text-sm font-semibold text-gray-900">
                                                            Description</th>
                                                        <th scope="col"
                                                            class="px-6 py-3 text-left text-sm font-semibold text-gray-900">
                                                            Amount</th>
                                                        <!--
                              `relative` is added here due to a weird bug in Safari that causes `sr-only` headings to introduce overflow on the body on mobile.
                            -->
                                                        <th scope="col"
                                                            class="relative px-6 py-3 text-left text-sm font-medium text-gray-500">
                                                            <span class="sr-only">View receipt</span>
                                                        </th>
                                                    </tr>
                                                </thead>
                                                <tbody class="divide-y divide-gray-200 bg-white">
                                                    <tr v-for="payment in payments" :key="payment.id">
                                                        <td
                                                            class="whitespace-nowrap px-6 py-4 text-sm font-medium text-gray-900">
                                                            <time :datetime="payment.datetime">{{ payment.date }}</time>
                                                        </td>
                                                        <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500">{{
                                                            payment.description }}</td>
                                                        <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500">{{
                                                            payment.amount }}</td>
                                                        <td
                                                            class="whitespace-nowrap px-6 py-4 text-right text-sm font-medium">
                                                            <a :href="payment.href"
                                                                class="text-orange-600 hover:text-orange-900">View
                                                                receipt</a>
                                                        </td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>
                </div>


            </div>
        </main>

    </MainLayout>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue'
import { router } from "@inertiajs/vue3";
import { usePage } from '@inertiajs/vue3'
import MainLayout from '../Layouts/MainLayout.vue'
import DashboardTile from './components/general/DashboardTile.vue'
import ContactPhoneIcon from "./components/icons/ContactPhoneIcon.vue"
import DialpadIcon from "./components/icons/DialpadIcon.vue"
import FaxIcon from "./components/icons/FaxIcon.vue"
import {
    ClockIcon,
} from '@heroicons/vue/20/solid'

import { TransitionRoot } from '@headlessui/vue'
import { XMarkIcon, CogIcon } from '@heroicons/vue/24/outline'
import TopBanner from './components/notifications/TopBanner.vue';
import { Cog6ToothIcon, AdjustmentsHorizontalIcon } from '@heroicons/vue/24/outline';
import LabelInputOptional from "@generalComponents/LabelInputOptional.vue";
import InputField from "@generalComponents/InputField.vue";
import Toggle from "@generalComponents/Toggle.vue";


import {
    Disclosure,
    DisclosureButton,
    DisclosurePanel,
    Menu,
    MenuButton,
    MenuItem,
    MenuItems,
    Switch,
    SwitchGroup,
    SwitchLabel,
} from '@headlessui/vue'
import { MagnifyingGlassIcon, QuestionMarkCircleIcon, ExclamationCircleIcon } from '@heroicons/vue/20/solid'
import {
    Bars3Icon,
    BellIcon,
    CreditCardIcon,
    KeyIcon,
    SquaresPlusIcon,
    UserCircleIcon,
} from '@heroicons/vue/24/outline'
import { ChevronDownIcon } from '@heroicons/vue/16/solid'


const props = defineProps({
    data: {
        type: Object,
        default: () => ({}) // Providing an empty object as default
    },
    navigation: Object,
    routes: Object,
    errors: Object,

})

const localData = ref(null);
const localSettings = ref([]);


onMounted(() => {
    localData.value = props.data; // Deep copy to prevent modifying props directly
    console.log(localData);
    localSettings.value = props.data.settings.map(setting => ({
        uuid: setting.domain_setting_uuid,
        subcategory: setting.domain_setting_subcategory,
        value: setting.domain_setting_value
    }));
});


const getSetting = (subcategory) => {
    return localSettings.value.find(setting => setting.subcategory === subcategory) || { uuid: null, value: '' };
};

const page = usePage()


const user = {
    name: 'Lisa Marie',
    email: 'lisamarie@example.com',
    imageUrl:
        'https://images.unsplash.com/photo-1517365830460-955ce3ccd263?ixlib=rb-1.2.1&ixid=eyJhcHBfaWQiOjEyMDd9&auto=format&fit=facearea&facepad=4&w=256&h=256&q=80',
}

const userNavigation = [
    { name: 'Your Profile', href: '#' },
    { name: 'Settings', href: '#' },
    { name: 'Sign out', href: '#' },
]
const subNavigation = [
    { name: 'Profile', href: '#', icon: UserCircleIcon, current: false },
    { name: 'Account', href: '#', icon: CogIcon, current: false },
    { name: 'Password', href: '#', icon: KeyIcon, current: false },
    { name: 'Notifications', href: '#', icon: BellIcon, current: false },
    { name: 'Plan & Billing', href: '#', icon: CreditCardIcon, current: true },
    { name: 'Integrations', href: '#', icon: SquaresPlusIcon, current: false },
]
const plans = [
    { name: 'Startup', priceMonthly: '$29', priceYearly: '$290', limit: 'Up to 5 active job postings', selected: true },
    {
        name: 'Business',
        priceMonthly: '$99',
        priceYearly: '$990',
        limit: 'Up to 25 active job postings',
        selected: false,
    },
    {
        name: 'Enterprise',
        priceMonthly: '$249',
        priceYearly: '$2490',
        limit: 'Unlimited active job postings',
        selected: false,
    },
]
const payments = [
    {
        id: 1,
        date: '1/1/2020',
        datetime: '2020-01-01',
        description: 'Business Plan - Annual Billing',
        amount: 'CA$109.00',
        href: '#',
    },
    // More payments...
]





const activeTab = ref(props.navigation.find(item => item.slug)?.slug || props.navigation[0].slug);

// Map icon names to their respective components
const iconComponents = {
    'Cog6ToothIcon': Cog6ToothIcon,
    'CreditCardIcon': CreditCardIcon,
};

onMounted(() => {
    //request list of entities
    // getCounts();
})

const getData = () => {
    router.visit("/dashboard", {
        preserveScroll: true,
        preserveState: true,
        data: {
        },
        only: ["data"],
        onSuccess: (page) => {
            if (props.data.superadmin) {
                open.value = true;
            }
            // console.log(props.data);
            // console.log(props.data.billing_suspension);
            // if (props.data.billing_suspension) {
            //     showTopBanner.value = true;
            // }

        }

    });

}

const setActiveTab = (tabSlug) => {
    activeTab.value = tabSlug;
};


</script>