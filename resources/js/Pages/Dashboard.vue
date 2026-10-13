<template>
    <MainLayout>
        <TopBanner :show="showTopBanner" @close="showTopBanner = false" color="bg-rose-600" :text="topBannerText"
            :link-href="company_data.billing_pay_url" :link-text="$t('Pay now')" />

        <main class="bg-slate-50/60">
            <div class="mx-auto max-w-none px-4 py-8 sm:px-6 lg:px-8">
                <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <p class="text-sm font-medium text-cyan-700">{{ $t('Account dashboard') }}</p>
                        <h1 class="mt-1 text-2xl font-semibold tracking-tight text-gray-950 sm:text-3xl">{{
                            company_data.company_name }}</h1>
                    </div>

                    <a v-if="permissions.account_settings_index" type="button" :href="routes.account_settings_page"
                        class="inline-flex w-fit items-center justify-center gap-x-1.5 rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 ring-1 ring-inset ring-gray-300 transition hover:bg-gray-50 hover:ring-gray-400">
                        <CogIcon class="-ml-0.5 size-5 text-gray-400" aria-hidden="true" />
                        {{ $t('Settings') }}
                    </a>
                </div>

                <div
                    class="mx-auto grid max-w-2xl grid-cols-1 grid-rows-1 items-start gap-6 lg:mx-0 lg:max-w-none lg:grid-cols-3">
                    <!-- Right column: Account summary + My Extension -->
                    <div class="space-y-6 lg:col-start-3 lg:row-end-1">
                        <!-- Account summary -->
                        <section v-if="permissions.extension_view"
                            class="overflow-hidden rounded-lg bg-white shadow-sm ring-1 ring-gray-200">
                            <div class="flex items-baseline justify-between gap-3 px-5 pb-3 pt-4">
                                <h2 class="truncate text-base font-semibold text-gray-950">{{ company_data.company_name }}</h2>
                                <span class="flex-none text-xs text-gray-500">{{ accountClock }}</span>
                            </div>
                            <div v-if="!countsLoaded" class="border-t border-gray-100 px-5 py-4">
                                <SkeletonRows :rows="3" />
                            </div>
                            <template v-else>
                                <div v-if="counts.extensions !== undefined && counts.extensions >= 0"
                                    class="border-t border-gray-100 px-5 py-3.5">
                                    <div class="flex items-baseline justify-between gap-3 text-sm">
                                        <span class="text-gray-700">{{ $t('Extensions online') }}</span>
                                        <span class="font-semibold tabular-nums text-gray-950">{{ onlineExtensions }}
                                            <span class="font-normal text-gray-500">/ {{ counts.extensions }}</span></span>
                                    </div>
                                    <div :class="['mt-2 h-1.5 overflow-hidden rounded-full', counts.extensions ? 'bg-rose-100' : 'bg-gray-100']">
                                        <div class="h-full rounded-full bg-emerald-500" :style="{ width: registrationPercent + '%' }"></div>
                                    </div>
                                </div>
                                <dl class="border-t border-gray-100 px-5 py-1.5 text-sm">
                                    <div v-if="counts.phone_numbers !== undefined && counts.phone_numbers >= 0"
                                        class="flex justify-between gap-3 py-2">
                                        <dt class="text-gray-700">{{ $t('Phone Numbers') }}</dt>
                                        <dd class="font-semibold tabular-nums text-gray-950">{{ counts.phone_numbers }}</dd>
                                    </div>
                                    <div v-if="counts.faxes !== undefined && counts.faxes >= 0" class="flex justify-between gap-3 py-2">
                                        <dt class="text-gray-700">{{ $t('Virtual Faxes') }}</dt>
                                        <dd class="font-semibold tabular-nums text-gray-950">{{ counts.faxes }}</dd>
                                    </div>
                                </dl>
                            </template>
                        </section>

                        <!-- Billing for this account (Billing module), when it is billed to a customer -->
                        <section v-if="billing"
                            :class="['overflow-hidden rounded-lg bg-white shadow-sm ring-1', billing.past_due ? 'ring-rose-200' : 'ring-gray-200']">
                            <div class="flex items-center justify-between gap-3 border-b border-gray-100 px-5 py-3.5">
                                <div class="flex min-w-0 items-center gap-x-3">
                                    <span class="flex h-8 w-8 flex-none items-center justify-center rounded-lg bg-amber-50 text-amber-700">
                                        <CreditCardIcon class="h-4 w-4" aria-hidden="true" />
                                    </span>
                                    <div class="min-w-0">
                                        <h2 class="text-sm font-semibold text-gray-950">{{ $t('Billing') }}</h2>
                                        <p class="truncate text-xs text-gray-500">{{ billing.customer }}</p>
                                    </div>
                                </div>
                                <span v-if="billingBadge"
                                    :class="['flex-none rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset', billingBadge.classes]">{{
                                    billingBadge.label }}</span>
                            </div>

                            <!-- Shown only while the top suspension banner is showing -->
                            <div v-if="showTopBanner" class="mx-5 mt-4 flex gap-x-2.5 rounded-md bg-rose-50 px-3 py-2.5 text-sm text-rose-800">
                                <ExclamationTriangleIcon class="mt-0.5 h-4 w-4 flex-none" aria-hidden="true" />
                                <span>{{ $t('Service is suspended until past-due invoices are paid.') }}</span>
                            </div>

                            <div class="flex items-end justify-between gap-3 px-5 pb-1 pt-4">
                                <div>
                                    <p :class="['text-xs', billing.past_due ? 'text-rose-700' : 'text-gray-500']">{{
                                        billing.past_due ? $t('Past due') : $t('Amount due') }}</p>
                                    <p
                                        :class="['mt-1 text-3xl font-semibold leading-none tracking-tight tabular-nums', billing.past_due ? 'text-rose-700' : 'text-gray-950']">
                                        {{ billing.past_due ? billing.past_due_amount : billing.amount_due }}</p>
                                </div>
                                <p v-if="billing.past_due && billing.amount_due !== billing.past_due_amount" class="text-xs text-gray-500">
                                    {{ $t(':amount due in total', { amount: billing.amount_due }) }}</p>
                                <p v-else-if="!billing.past_due && billing.open_count" class="text-xs text-gray-500">
                                    {{ $tChoice(':count open invoice|:count open invoices', billing.open_count) }}</p>
                            </div>

                            <dl class="divide-y divide-gray-100 px-5 pt-2 text-sm">
                                <div v-if="billing.past_due && billing.oldest_unpaid" class="flex justify-between gap-3 py-2.5">
                                    <dt class="text-gray-500">{{ $t('Oldest unpaid') }}</dt>
                                    <dd class="text-right font-semibold text-rose-700">{{ $t('Due :date', { date: billing.oldest_unpaid.due }) }} ·
                                        {{ $tChoice(':count day late|:count days late', billing.oldest_unpaid.days_late) }}</dd>
                                </div>
                                <div v-if="!billing.past_due && billing.next_due" class="flex justify-between gap-3 py-2.5">
                                    <dt class="text-gray-500">{{ $t('Next invoice due') }}</dt>
                                    <dd class="font-semibold text-gray-900">{{ billing.next_due }}</dd>
                                </div>
                                <div v-if="billing.past_due && billing.failed_attempt" class="flex justify-between gap-3 py-2.5">
                                    <dt class="text-gray-500">{{ $t('Last payment attempt') }}</dt>
                                    <dd class="text-right text-gray-900">{{ $t('Failed :date', { date: billing.failed_attempt.date }) }}
                                        <span v-if="billing.failed_attempt.reason" class="block text-xs text-gray-500">{{
                                            billing.failed_attempt.reason }}</span></dd>
                                </div>
                                <div v-else-if="billing.last_payment" class="flex justify-between gap-3 py-2.5">
                                    <dt class="text-gray-500">{{ $t('Last payment') }}</dt>
                                    <dd class="text-right text-gray-900"><span class="font-semibold">{{ billing.last_payment.amount }}</span> ·
                                        {{ billing.last_payment.date }}
                                        <span v-if="billing.last_payment.method" class="block text-xs text-gray-500">{{
                                            billing.last_payment.method }}</span></dd>
                                </div>
                            </dl>

                            <div v-if="!billing.past_due && billing.recent_invoices.length"
                                class="mx-5 mt-2 rounded-md ring-1 ring-inset ring-gray-100">
                                <p class="px-3 pb-1 pt-2.5 text-[11px] font-semibold uppercase tracking-wide text-gray-500">{{
                                    $t('Recent invoices') }}</p>
                                <ul class="divide-y divide-gray-50">
                                    <li v-for="invoice in billing.recent_invoices" :key="invoice.number"
                                        class="flex items-center gap-x-3 px-3 py-2 text-sm">
                                        <span class="min-w-0 flex-1 truncate text-gray-900">{{ invoice.number || '—' }}
                                            <span class="text-gray-500">· {{ invoice.date }}</span></span>
                                        <span class="font-semibold tabular-nums text-gray-900">{{ invoice.amount }}</span>
                                        <span
                                            :class="['min-w-[4.5rem] rounded-full px-2 py-0.5 text-center text-[11px] font-medium', invoiceStatusClasses(invoice.status)]">{{
                                            invoiceStatusLabel(invoice.status) }}</span>
                                    </li>
                                </ul>
                            </div>

                            <div :class="['px-5 pb-5 pt-4', billing.past_due && billing.pay_url ? 'grid grid-cols-2 gap-2' : '']">
                                <a v-if="billing.past_due && billing.pay_url" :href="billing.pay_url" target="_blank" rel="noopener"
                                    class="flex min-h-10 items-center justify-center rounded-md bg-rose-600 px-3 text-sm font-semibold text-white shadow-sm hover:bg-rose-500">
                                    {{ $t('Pay now') }}
                                </a>
                                <a :href="billing.url"
                                    :class="['flex min-h-10 items-center justify-center gap-x-1.5 rounded-md px-3 text-sm font-semibold', billing.past_due && billing.pay_url
                                        ? 'bg-white text-gray-900 ring-1 ring-inset ring-gray-300 hover:bg-gray-50'
                                        : 'bg-indigo-600 text-white shadow-sm hover:bg-indigo-500']">
                                    {{ $t('View billing') }}
                                    <ArrowRightIcon v-if="!(billing.past_due && billing.pay_url)" class="h-4 w-4" aria-hidden="true" />
                                </a>
                            </div>
                        </section>

                        <!-- Today's inbound, outbound, and local calls by hour, loaded with Global Info after the rest of the page -->
                        <section v-if="permissions.cdr_view"
                            class="overflow-hidden rounded-lg bg-white shadow-sm ring-1 ring-gray-200">
                            <div class="flex items-center justify-between gap-3 border-b border-gray-100 px-5 py-3">
                                <h2 class="text-sm font-semibold text-gray-950">{{ $t('Calls today') }}</h2>
                                <a :href="routes.cdrs_page" class="text-sm font-semibold text-cyan-700 hover:text-cyan-900">{{
                                    $t('Call history') }}</a>
                            </div>
                            <div v-if="!callStats" class="px-5 py-4">
                                <SkeletonRows :rows="3" />
                            </div>
                            <p v-else-if="callStats.failed" class="px-5 py-6 text-sm text-gray-500">{{ $t("Could not load today's calls.") }}</p>
                            <template v-else>
                                <div class="grid grid-cols-3 border-b border-gray-100">
                                    <div class="px-5 py-3">
                                        <p class="flex items-center gap-x-1.5 text-xs text-gray-500">
                                            <span class="h-2 w-2 rounded-sm bg-cyan-600" aria-hidden="true"></span>{{ $t('Inbound') }}
                                        </p>
                                        <p class="mt-1 text-2xl font-semibold tabular-nums text-gray-950">{{ callStats.inbound }}</p>
                                    </div>
                                    <div class="border-l border-gray-100 px-5 py-3">
                                        <p class="flex items-center gap-x-1.5 text-xs text-gray-500">
                                            <span class="h-2 w-2 rounded-sm bg-indigo-300" aria-hidden="true"></span>{{ $t('Outbound') }}
                                        </p>
                                        <p class="mt-1 text-2xl font-semibold tabular-nums text-gray-950">{{ callStats.outbound }}</p>
                                    </div>
                                    <div class="border-l border-gray-100 px-5 py-3">
                                        <p class="flex items-center gap-x-1.5 text-xs text-gray-500">
                                            <span class="h-2 w-2 rounded-sm bg-fuchsia-400" aria-hidden="true"></span>{{ $t('Local') }}
                                        </p>
                                        <p class="mt-1 text-2xl font-semibold tabular-nums text-gray-950">{{ callStats.local }}</p>
                                    </div>
                                </div>
                                <div class="px-5 pb-4 pt-4">
                                    <p v-if="!callStatsMax" class="flex h-24 items-center justify-center text-sm text-gray-500">{{
                                        $t('No calls yet today') }}</p>
                                    <template v-else>
                                        <div class="flex h-24 items-end gap-0.5 border-b border-gray-200" role="img"
                                            :aria-label="$t(':inbound inbound, :outbound outbound and :local local calls today', { inbound: callStats.inbound, outbound: callStats.outbound, local: callStats.local })">
                                            <div v-for="hour in callStats.hours" :key="hour.hour" class="flex h-full flex-1 flex-col justify-end"
                                                :title="$t(':hour — :inbound inbound, :outbound outbound, :local local', { hour: hour.label, inbound: hour.inbound, outbound: hour.outbound, local: hour.local })">
                                                <div class="flex flex-col overflow-hidden rounded-t-sm" :style="{ height: callBarHeight(hour) }">
                                                    <div class="bg-indigo-300" :style="{ height: callShare(hour, 'outbound') }"></div>
                                                    <div class="bg-fuchsia-400" :style="{ height: callShare(hour, 'local') }"></div>
                                                    <div class="flex-1 bg-cyan-600"></div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="mt-1 grid grid-cols-4 text-[11px] text-gray-500">
                                            <span v-for="tick in [0, 6, 12, 18]" :key="tick">{{ callStats.hours[tick]?.label }}</span>
                                        </div>
                                    </template>
                                </div>
                            </template>
                        </section>

                        <section v-if="customerNotes.visible" role="button" tabindex="0" @click="showCustomerNotesModal = true"
                            @keydown.enter="showCustomerNotesModal = true"
                            @keydown.space.prevent="showCustomerNotesModal = true"
                            :class="[
                                'group relative w-full overflow-hidden rounded-lg p-5 text-left shadow-sm ring-1 transition hover:-translate-y-0.5 hover:shadow-md focus:outline-none focus-visible:ring-2 focus-visible:ring-cyan-500 focus-visible:ring-offset-2',
                                hasVisibleCustomerNotesContent
                                    ? 'border border-amber-200 bg-amber-100/90 ring-amber-300/30'
                                    : 'border border-gray-200 bg-white ring-gray-200',
                            ]">
                            <div class="relative flex items-start justify-between gap-4">
                                <div>
                                    <p :class="[
                                        'text-xs font-semibold uppercase tracking-wide',
                                        hasVisibleCustomerNotesContent ? 'text-amber-800' : 'text-cyan-700',
                                    ]">{{ $t('Customer Notes') }}</p>
                                    <h3 :class="[
                                        'mt-1 text-base font-semibold',
                                        hasVisibleCustomerNotesContent ? 'text-amber-950' : 'text-gray-950',
                                    ]">{{ $t('Technician notes') }}</h3>
                                </div>
                            </div>

                            <div class="relative mt-4 space-y-3">
                                <div v-for="note in visibleCustomerNotes" :key="note.key"
                                    :class="[
                                        'rounded-md px-3 py-2 ring-1 ring-inset',
                                        hasVisibleCustomerNotesContent ? 'bg-white/55 ring-amber-700/10' : 'bg-gray-50 ring-gray-200',
                                        note.borderClass,
                                    ]">
                                    <div class="mb-1 flex items-center gap-2 text-xs font-semibold uppercase tracking-wide"
                                        :class="note.labelClass">
                                        <span :class="['h-2 w-2 rounded-full', note.dotClass]"></span>
                                        {{ note.label }}
                                    </div>
                                    <div v-if="note.content" class="relative">
                                        <div :ref="(el) => setCustomerNotesPreviewRef(note.key, el)"
                                            class="customer-notes-preview max-h-28 overflow-hidden text-sm leading-5 text-amber-950"
                                            v-html="note.content"></div>
                                        <div v-if="overflowingCustomerNotes[note.key]"
                                            :class="[
                                                'pointer-events-none absolute inset-x-0 bottom-0 flex h-10 items-end justify-center bg-gradient-to-t pb-0.5 text-lg font-semibold leading-none',
                                                hasVisibleCustomerNotesContent
                                                    ? 'from-amber-50 via-amber-50/95 text-amber-900'
                                                    : 'from-gray-50 via-gray-50/95 text-gray-700',
                                            ]">
                                            ...
                                        </div>
                                    </div>
                                    <p v-else class="text-sm italic leading-5 text-amber-900/75">{{ $t('No notes yet.') }}</p>
                                </div>
                            </div>
                        </section>

                        <div v-if="my_extension_status"
                            class="rounded-lg bg-white ring-1 ring-gray-200">
                            <div class="flex flex-col gap-4 border-b border-gray-100 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <p class="text-xs font-medium uppercase tracking-wide text-cyan-700">{{ $t('My Extension') }}</p>
                                    <h3 class="mt-1 text-sm font-semibold text-gray-950">{{ my_extension_status.name }}</h3>
                                </div>

                                <button type="button" @click="openExtensionModal(my_extension_status.extension_uuid)"
                                    class="inline-flex w-fit items-center justify-center gap-x-1.5 rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 ring-1 ring-inset ring-gray-300 transition hover:bg-gray-50 hover:ring-gray-400">
                                    <CogIcon class="-ml-0.5 size-5 text-gray-400" aria-hidden="true" />
                                    {{ $t('Manage') }}
                                </button>
                            </div>

                            <div class="px-5 py-4">
                                <p class="text-sm font-medium text-gray-700">{{ $t('Active call handling') }}</p>
                                <div class="mt-3 flex flex-wrap gap-2">
                                    <span v-if="my_extension_status.do_not_disturb"
                                        class="inline-flex items-center rounded-full bg-rose-100 px-2.5 py-1 text-xs font-medium text-rose-800 ring-1 ring-inset ring-rose-400/20">
                                        {{ $t('DND') }}
                                    </span>
                                    <span v-for="forward in activeForwarding" :key="forward.key"
                                        class="inline-flex max-w-full items-center rounded-full bg-blue-100 px-2.5 py-1 text-xs font-medium text-blue-800 ring-1 ring-inset ring-blue-400/20">
                                        <span>{{ forward.badge }}</span>
                                        <span v-if="forward.target" class="ml-1 max-w-48 truncate text-blue-700">- {{ forward.target }}</span>
                                    </span>
                                    <span v-if="my_extension_status.call_sequence_enabled"
                                        class="inline-flex items-center rounded-full bg-blue-100 px-2.5 py-1 text-xs font-medium text-blue-800 ring-1 ring-inset ring-blue-400/20">
                                        {{ $t('Sequence') }}
                                    </span>
                                    <span v-if="!hasActiveCallHandling"
                                        class="inline-flex items-center rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-700 ring-1 ring-inset ring-emerald-600/20">
                                        {{ $t('Normal routing') }}
                                    </span>
                                </div>
                            </div>

                            <div v-if="my_extension_status.agent && routes.agent_status_update"
                                class="border-t border-gray-100 px-5 py-4">
                                <div class="flex items-center justify-between gap-4">
                                    <div class="flex min-w-0 items-center gap-3">
                                        <SupportAgentIcon class="h-5 w-5 flex-none text-gray-400" aria-hidden="true" />
                                        <div class="min-w-0">
                                            <p class="truncate text-sm font-medium text-gray-700">{{ $t('Contact Center') }}</p>
                                            <p class="text-xs text-gray-500">{{ $t('Status') }}</p>
                                        </div>
                                    </div>

                                    <Menu as="div" class="relative flex-none">
                                        <MenuButton type="button" :disabled="isAgentStatusUpdating"
                                            class="inline-flex items-center rounded-md px-2.5 py-1.5 text-sm font-medium ring-1 ring-inset transition focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2 disabled:cursor-wait disabled:opacity-60"
                                            :class="agentStatusStyles.button">
                                            <span :class="[agentStatusStyles.dot, 'mr-2 h-2 w-2 rounded-full']"
                                                aria-hidden="true"></span>
                                            {{ agentStatusLabel(my_extension_status.agent.status) }}
                                            <Spinner v-if="isAgentStatusUpdating" class="ml-2" :show="true" />
                                            <ChevronDownIcon v-else class="ml-2 h-4 w-4" aria-hidden="true" />
                                        </MenuButton>

                                        <transition enter-active-class="transition ease-out duration-100"
                                            enter-from-class="transform opacity-0 scale-95"
                                            enter-to-class="transform opacity-100 scale-100"
                                            leave-active-class="transition ease-in duration-75"
                                            leave-from-class="transform opacity-100 scale-100"
                                            leave-to-class="transform opacity-0 scale-95">
                                            <MenuItems
                                                class="absolute right-0 z-20 mt-2 w-40 origin-top-right rounded-md bg-white py-1 shadow-lg ring-1 ring-black/5 focus:outline-none">
                                                <MenuItem v-for="option in agentStatusOptions" :key="option" v-slot="{ active }">
                                                    <button type="button"
                                                        class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-gray-700"
                                                        :class="active ? 'bg-gray-50' : ''"
                                                        @click="updateAgentStatus(option)">
                                                        <span :class="[agentStatusStyle(option).dot, 'h-2 w-2 rounded-full']"
                                                            aria-hidden="true"></span>
                                                        {{ agentStatusLabel(option) }}
                                                    </button>
                                                </MenuItem>
                                            </MenuItems>
                                        </transition>
                                    </Menu>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Access -->
                    <div class="lg:col-span-2 lg:row-span-2 lg:row-end-2 space-y-6">
                      <div>
                        <div class="flex items-center justify-between">
                            <h2 class="text-base font-semibold leading-6 text-gray-950">{{ $t('Quick Access') }}</h2>
                            <span v-if="cards.length" class="text-sm text-gray-500">
                                {{ $t(':count shortcut(s)', { count: cards.length }) }}
                            </span>
                        </div>

                        <div v-if="cards.length"
                            class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                            <div v-for="card in cards" :key="card.slug" class="h-full">
                                <DashboardTile :card="card" :count="counts[card.slug]" @card-action="handleCardAction" />
                            </div>
                        </div>

                        <div v-else
                            class="mt-4 rounded-lg border-2 border-dashed border-gray-200 bg-white p-8 text-center">
                            <p class="text-sm font-medium text-gray-700">{{ $t('No shortcuts available') }}</p>
                            <p class="mt-1 text-sm text-gray-500">{{ $t('Shortcuts will appear here once you have access to features.') }}</p>
                        </div>
                      </div>

                      <GlobalInfoPanel v-if="data.superadmin" :data="data" :counts="counts" />
                    </div>
                </div>
            </div>
        </main>
    </MainLayout>

    <UpdateExtensionForm :show="showExtensionModal" :options="extensionItemOptions" :loading="isExtensionModalLoading"
        :header="$t('Update Extension - :name', { name: extensionItemOptions?.item?.name_formatted ?? 'loading' })"
        @close="showExtensionModal = false" @error="handleErrorResponse" @success="showNotification"
        @refresh-data="getCounts" />

    <CustomerNotesModal :show="showCustomerNotesModal" :customer-notes="customerNotes"
        :can-edit="permissions.customer_notes_edit" :route="routes.customer_notes_route"
        @close="showCustomerNotesModal = false"
        @error="handleErrorResponse" @success="handleCustomerNotesSuccess"
        @updated="handleCustomerNotesUpdated" />

    <Notification :show="notificationShow" :type="notificationType" :messages="notificationMessages"
        @update:show="hideNotification" />
</template>

<script setup>
import { computed, ref, onBeforeUnmount, onMounted, nextTick } from 'vue'
import axios from 'axios';
import { trans } from '@i18n';
import MainLayout from '../Layouts/MainLayout.vue'
import DashboardTile from './components/general/DashboardTile.vue'
import GlobalInfoPanel from './components/general/GlobalInfoPanel.vue'
import SkeletonRows from './components/general/Skeleton.vue'
import UpdateExtensionForm from './components/forms/UpdateExtensionForm.vue'
import CustomerNotesModal from './components/modal/CustomerNotesModal.vue'
import Notification from './components/notifications/Notification.vue'
import { ArrowRightIcon, ChevronDownIcon, CreditCardIcon, ExclamationTriangleIcon } from '@heroicons/vue/20/solid'
import { CogIcon } from '@heroicons/vue/24/outline'
import { Menu, MenuButton, MenuItem, MenuItems } from '@headlessui/vue'
import TopBanner from './components/notifications/TopBanner.vue';
import SupportAgentIcon from './components/icons/SupportAgent.vue'
import Spinner from '@generalComponents/Spinner.vue'


const props = defineProps({
    data: {
        type: Object,
        default: () => ({})
    },
    company_data: Object,
    cards: Array,
    counts: {
        type: Object,
        default: () => ({})
    },
    my_extension_status: {
        type: Object,
        default: null,
    },
    customer_notes: {
        type: Object,
        default: () => ({ visible: false, levels: [], notes: {} }),
    },
    permissions: {
        type: Object,
        default: () => ({})
    },
    routes: Object,
})

const data = ref(props.data ?? {});
const counts = ref(props.counts ?? {});
const my_extension_status = ref(props.my_extension_status ?? null);
const customerNotes = ref(props.customer_notes ?? { visible: false, levels: [], notes: {} });
const customerNotesPreviewRefs = ref({});
const overflowingCustomerNotes = ref({});
const showExtensionModal = ref(false);
const showCustomerNotesModal = ref(false);
const isExtensionModalLoading = ref(false);
const extensionItemOptions = ref({});
const notificationType = ref(null);
const notificationMessages = ref(null);
const notificationShow = ref(false);
const isAgentStatusUpdating = ref(false);

const showTopBanner = ref(Boolean(props.company_data.billing_suspension));
const topBannerText = computed(() => trans('Your account has been suspended. Reactivation requires payment for past-due invoice(s).'));

const countsLoaded = computed(() => Object.keys(counts.value).length !== 0);

const onlineExtensions = computed(() => Number(counts.value.local_reg_count || 0));
const offlineExtensions = computed(() => Math.max((counts.value.extensions || 0) - onlineExtensions.value, 0));
const registrationPercent = computed(() => {
    const totalExtensions = Number(counts.value.extensions || 0);
    if (!totalExtensions) return 0;
    return Math.min(Math.round((onlineExtensions.value / totalExtensions) * 100), 100);
});

const forwardBadgeLabels = computed(() => ({
    forward_all: trans('FWD All'),
    forward_busy: trans('FWD Busy'),
    forward_no_answer: trans('FWD no Ans'),
    forward_user_not_registered: trans('FWD no Reg'),
}));

const activeForwarding = computed(() => {
    return (my_extension_status.value?.forwarding || [])
        .filter((forward) => forward.enabled)
        .map((forward) => ({
            ...forward,
            badge: forwardBadgeLabels.value[forward.key] || forward.label,
        }));
});

const hasActiveCallHandling = computed(() => {
    return Boolean(
        my_extension_status.value?.do_not_disturb
        || my_extension_status.value?.call_sequence_enabled
        || activeForwarding.value.length
    );
});

const agentStatusOptions = ['Available', 'On Break', 'Logged Out'];
const agentStatusLabels = computed(() => ({
    'Available': trans('Available'),
    'On Break': trans('On Break'),
    'Logged Out': trans('Logged Out'),
}));
const agentStatusStyleMap = {
    'Available': {
        button: 'bg-emerald-50 text-emerald-700 ring-emerald-600/20 hover:bg-emerald-100',
        dot: 'bg-emerald-500',
    },
    'On Break': {
        button: 'bg-amber-50 text-amber-700 ring-amber-600/20 hover:bg-amber-100',
        dot: 'bg-amber-500',
    },
    'Logged Out': {
        button: 'bg-gray-50 text-gray-700 ring-gray-600/20 hover:bg-gray-100',
        dot: 'bg-gray-400',
    },
};
const fallbackAgentStatusStyle = {
    button: 'bg-gray-50 text-gray-700 ring-gray-600/20 hover:bg-gray-100',
    dot: 'bg-gray-400',
};

const agentStatusLabel = (status) => agentStatusLabels.value[status] || status;
const agentStatusStyle = (status) => agentStatusStyleMap[status] || fallbackAgentStatusStyle;
const agentStatusStyles = computed(() => agentStatusStyle(my_extension_status.value?.agent?.status));

const customerNoteLayers = computed(() => [
    {
        key: 'level_1',
        level: 1,
        label: trans('Level 1'),
        borderClass: 'border-l-4 border-l-amber-600',
        labelClass: 'text-amber-800',
        dotClass: 'bg-amber-600',
    },
    {
        key: 'level_2',
        level: 2,
        label: trans('Level 2'),
        borderClass: 'border-l-4 border-l-sky-600',
        labelClass: 'text-sky-800',
        dotClass: 'bg-sky-600',
    },
    {
        key: 'level_3',
        level: 3,
        label: trans('Level 3'),
        borderClass: 'border-l-4 border-l-rose-600',
        labelClass: 'text-rose-800',
        dotClass: 'bg-rose-600',
    },
]);

const visibleCustomerNotes = computed(() => {
    const levels = customerNotes.value?.levels || [];
    const notes = customerNotes.value?.notes || {};

    return customerNoteLayers.value
        .filter((layer) => levels.includes(layer.level))
        .map((layer) => ({
            ...layer,
            content: notes[layer.key] || null,
        }));
});

const hasVisibleCustomerNotesContent = computed(() => {
    return visibleCustomerNotes.value.some((note) => {
        const content = note.content || '';
        return content.replace(/<[^>]*>/g, '').trim() !== '';
    });
});

onMounted(() => {
    getCounts();
    window.addEventListener('resize', measureCustomerNotesPreviews);
    clockTimer = setInterval(() => { clockNow.value = new Date(); }, 30000);
})

onBeforeUnmount(() => {
    window.removeEventListener('resize', measureCustomerNotesPreviews);
    clearInterval(clockTimer);
})

const getCounts = () => {
    axios.get(props.routes.counts_route)
        .then((response) => {
            counts.value = response.data || {};
            return getMyExtensionStatus();
        })
        .then(() => {
            // Global Info and the calls chart load together, after the rest of the page.
            return Promise.all([getData(), getCallStats()]);
        })
        .then(() => {
            return getCustomerNotes();
        })
        .catch((error) => {
            handleErrorResponse(error);
        });
}

const handleCardAction = (card) => {
    if (card.action === 'open_extension_modal') {
        openExtensionModal(card.extension_uuid);
    }
}

const openExtensionModal = (extensionUuid) => {
    if (!extensionUuid) return;

    showExtensionModal.value = true;
    extensionItemOptions.value = {};
    isExtensionModalLoading.value = true;

    axios.post(props.routes.extension_item_options, { item_uuid: extensionUuid })
        .then((response) => {
            extensionItemOptions.value = response.data;
        })
        .catch((error) => {
            showExtensionModal.value = false;
            handleErrorResponse(error);
        })
        .finally(() => {
            isExtensionModalLoading.value = false;
        });
}

const handleErrorResponse = (error) => {
    if (error.response) {
        showNotification('error', error.response.data.errors || { request: [error.message] });
    } else if (error.request) {
        showNotification('error', { request: [error.request] });
    } else {
        showNotification('error', { request: [error.message] });
    }
}

const hideNotification = () => {
    notificationShow.value = false;
    notificationType.value = null;
    notificationMessages.value = null;
}

const showNotification = (type, messages = null) => {
    notificationType.value = type;
    notificationMessages.value = messages;
    notificationShow.value = true;
}

const handleCustomerNotesUpdated = (payload) => {
    customerNotes.value = payload || { visible: false, levels: [], notes: {} };
    measureCustomerNotesPreviews();
}

const handleCustomerNotesSuccess = (messages = null) => {
    showNotification('success', messages);
}

const setCustomerNotesPreviewRef = (key, el) => {
    if (el) {
        customerNotesPreviewRefs.value[key] = el;
    } else {
        delete customerNotesPreviewRefs.value[key];
    }

    measureCustomerNotesPreviews();
}

const measureCustomerNotesPreviews = async () => {
    await nextTick();

    const overflowState = {};
    Object.entries(customerNotesPreviewRefs.value).forEach(([key, el]) => {
        overflowState[key] = el.scrollHeight > el.clientHeight + 1;
    });

    if (JSON.stringify(overflowingCustomerNotes.value) !== JSON.stringify(overflowState)) {
        overflowingCustomerNotes.value = overflowState;
    }
}

const getCustomerNotes = () => {
    const hasCustomerNotesAccess = props.permissions.customer_notes_level_1
        || props.permissions.customer_notes_level_2
        || props.permissions.customer_notes_level_3;

    if (!hasCustomerNotesAccess || !props.routes.customer_notes_route) {
        customerNotes.value = { visible: false, levels: [], notes: {} };
        return Promise.resolve();
    }

    return axios.get(props.routes.customer_notes_route)
        .then((response) => {
            customerNotes.value = response.data || { visible: false, levels: [], notes: {} };
            measureCustomerNotesPreviews();
        });
}

// Account card clock: the account's local time, refreshed every 30 seconds.
const clockNow = ref(new Date());
let clockTimer = null;
const accountClock = computed(() => {
    const zone = props.company_data.time_zone;
    if (!zone) return '';
    try {
        const time = new Intl.DateTimeFormat(undefined, { hour: 'numeric', minute: '2-digit', timeZone: zone }).format(clockNow.value);
        return `${time} · ${zone}`;
    } catch (e) {
        return zone;
    }
});

// Billing card (Billing module); null when the account isn't billed to a customer.
const billing = computed(() => props.company_data.billing ?? null);

const billingBadge = computed(() => {
    const b = billing.value;
    if (!b) return null;
    if (b.past_due) return { label: trans('Past due'), classes: 'bg-rose-50 text-rose-700 ring-rose-600/20' };
    if (b.owing && b.next_due_short) return { label: trans('Due :date', { date: b.next_due_short }), classes: 'bg-blue-50 text-blue-700 ring-blue-700/10' };
    if (!b.owing) return { label: trans('Paid up'), classes: 'bg-emerald-50 text-emerald-700 ring-emerald-600/20' };
    return null;
});

const invoiceStatusLabels = computed(() => ({
    open: trans('Open'),
    processing: trans('Processing'),
    past_due: trans('Past due'),
    paid: trans('Paid'),
    void: trans('Void'),
    uncollectible: trans('Uncollectible'),
    refunded: trans('Refunded'),
    partially_refunded: trans('Partially refunded'),
}));

const invoiceStatusLabel = (status) => invoiceStatusLabels.value[status] ?? status;

const invoiceStatusClasses = (status) => ({
    paid: 'bg-emerald-50 text-emerald-700',
    open: 'bg-blue-50 text-blue-700',
    processing: 'bg-amber-50 text-amber-800',
    past_due: 'bg-rose-50 text-rose-700',
}[status] ?? 'bg-gray-100 text-gray-600');

// Calls today: inbound, outbound, and local by hour, stacked.
const callStats = ref(null);
const callTotal = (hour) => hour.inbound + hour.outbound + hour.local;
const callStatsMax = computed(() => Math.max(0, ...(callStats.value?.hours ?? []).map(callTotal)));
const callBarHeight = (hour) => `${callStatsMax.value ? (callTotal(hour) / callStatsMax.value) * 100 : 0}%`;
const callShare = (hour, direction) => {
    const total = callTotal(hour);
    return `${total ? (hour[direction] / total) * 100 : 0}%`;
};

const getCallStats = () => {
    if (!props.permissions.cdr_view || !props.routes.call_stats_route) {
        return Promise.resolve();
    }

    return axios.get(props.routes.call_stats_route)
        .then((response) => {
            callStats.value = response.data || { inbound: 0, outbound: 0, local: 0, hours: [] };
        })
        .catch(() => {
            callStats.value = { failed: true };
        });
};

const getData = () => {
    return axios.get(props.routes.data_route)
        .then((response) => {
            data.value = response.data || {};
        })
        .catch((error) => {
            handleErrorResponse(error);
        });
}

const getMyExtensionStatus = () => {
    if (!props.routes.my_extension_status_route) {
        return Promise.resolve();
    }

    return axios.get(props.routes.my_extension_status_route)
        .then((response) => {
            my_extension_status.value = response.data || null;
        });
};

const updateAgentStatus = async (status) => {
    const agent = my_extension_status.value?.agent;
    if (!agent || !props.routes.agent_status_update || status === agent.status || isAgentStatusUpdating.value) {
        return;
    }

    isAgentStatusUpdating.value = true;

    try {
        const response = await axios.post(props.routes.agent_status_update, {
            agentUuid: agent.call_center_agent_uuid,
            status,
        });

        agent.status = response.data.status || status;
        showNotification('success', response.data.messages || {
            success: [trans('Status updated successfully.')],
        });
    } catch (error) {
        handleErrorResponse(error);
    } finally {
        isAgentStatusUpdating.value = false;
    }
};
</script>

<style scoped>
.customer-notes-preview :deep(p),
.customer-notes-preview :deep(div),
.customer-notes-preview :deep(ul),
.customer-notes-preview :deep(ol),
.customer-notes-preview :deep(blockquote),
.customer-notes-preview :deep(pre) {
    margin-bottom: 0.5rem;
}

.customer-notes-preview :deep(ul),
.customer-notes-preview :deep(ol) {
    padding-left: 1.25rem;
}

.customer-notes-preview :deep(ul) {
    list-style: disc;
}

.customer-notes-preview :deep(ol) {
    list-style: decimal;
}

.customer-notes-preview :deep(a) {
    color: #0e7490;
    text-decoration: underline;
}
</style>
