<template>
    <MainLayout />

    <div class="m-3 space-y-6">
        <DataTable @search-action="handleSearch" @reset-filters="resetFilters">
            <template #title>{{ $t('SIP Status') }}</template>
            <template #subtitle>
                {{ $t('Current Sofia profiles, gateways, aliases, profile details, and switch status.') }}
                <span v-if="statusData.generated_at" class="ml-2 text-gray-500">
                    {{ $t('Updated') }} {{ formatDate(statusData.generated_at) }}
                </span>
            </template>

            <template #filters>
                <div class="relative min-w-64 focus-within:z-10 mb-2 sm:mr-4">
                    <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                        <MagnifyingGlassIcon class="h-5 w-5 text-gray-400" aria-hidden="true" />
                    </div>
                    <input
                        v-model="filterData.search"
                        type="text"
                        class="block w-full rounded-md border-0 py-1.5 pl-10 text-sm text-gray-900 ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-blue-600"
                        :placeholder="$t('Search')"
                        @keydown.enter="handleSearch"
                    />
                </div>
            </template>

            <template #action>
                <div class="flex flex-wrap items-center justify-end gap-2">
                    <button
                        v-if="permissions.can_run_commands"
                        type="button"
                        class="inline-flex items-center gap-1.5 rounded-md bg-white px-2.5 py-1.5 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 disabled:opacity-50"
                        :disabled="actionLoading"
                        @click="submitAction('cache-flush')"
                    >
                        <ArchiveBoxXMarkIcon class="h-4 w-4 text-gray-500" />
                        {{ $t('Flush Cache') }}
                    </button>
                    <button
                        v-if="permissions.can_run_commands"
                        type="button"
                        class="inline-flex items-center gap-1.5 rounded-md bg-white px-2.5 py-1.5 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 disabled:opacity-50"
                        :disabled="actionLoading"
                        @click="submitAction('reloadacl')"
                    >
                        <ShieldCheckIcon class="h-4 w-4 text-gray-500" />
                        {{ $t('Reload ACL') }}
                    </button>
                    <button
                        v-if="permissions.can_run_commands"
                        type="button"
                        class="inline-flex items-center gap-1.5 rounded-md bg-white px-2.5 py-1.5 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 disabled:opacity-50"
                        :disabled="actionLoading"
                        @click="submitAction('reloadxml')"
                    >
                        <CodeBracketIcon class="h-4 w-4 text-gray-500" />
                        {{ $t('Reload XML') }}
                    </button>
                    <button
                        type="button"
                        class="inline-flex items-center gap-1.5 rounded-md bg-indigo-600 px-2.5 py-1.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 disabled:opacity-50"
                        :disabled="loading"
                        @click="fetchData"
                    >
                        <ArrowPathIcon class="h-4 w-4" :class="{ 'animate-spin': loading }" />
                        {{ $t('Refresh') }}
                    </button>
                </div>
            </template>

            <template #table-header>
                <TableColumnHeader class="px-4 py-3.5 text-left text-sm font-semibold text-gray-900">
                    {{ $t('Name') }}
                </TableColumnHeader>
                <TableColumnHeader :header="$t('Type')" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />
                <TableColumnHeader :header="$t('Data')" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />
                <TableColumnHeader :header="$t('State')" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />
                <TableColumnHeader header="" class="px-2 py-3.5 text-right text-sm font-semibold text-gray-900" />
            </template>

            <template #table-body>
                <tr v-for="row in filteredSummary" :key="row.id">
                    <TableField class="px-4 py-2 text-sm text-gray-500">
                        <a
                            v-if="row.edit_url"
                            :href="row.edit_url"
                            class="font-medium text-gray-900 hover:text-blue-600"
                        >
                            {{ row.name }}
                        </a>
                        <span v-else class="font-medium text-gray-900">{{ row.name }}</span>
                    </TableField>
                    <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500" :text="sipTypeLabel(row.type)" />
                    <TableField class="px-2 py-2 text-sm text-gray-500">
                        <span class="break-all">{{ row.data || '-' }}</span>
                    </TableField>
                    <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500">
                        <Badge
                            :text="row.state || '-'"
                            :backgroundColor="statusColor(row.state).backgroundColor"
                            :textColor="statusColor(row.state).textColor"
                            :ringColor="statusColor(row.state).ringColor"
                        />
                    </TableField>
                    <TableField class="whitespace-nowrap px-2 py-1 text-sm text-gray-500">
                        <template #action-buttons>
                            <button
                                v-if="row.action && row.action.gateway"
                                type="button"
                                class="rounded-md bg-white px-2 py-1 text-xs font-semibold text-gray-700 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 disabled:opacity-50"
                                :disabled="actionLoading"
                                @click="submitAction(row.action.action, { profile: row.action.profile, gateway: row.action.gateway })"
                            >
                                {{ row.action.label }}
                            </button>
                        </template>
                    </TableField>
                </tr>
            </template>

            <template #empty>
                <div v-if="!loading && filteredSummary.length === 0" class="my-5 text-center">
                    <ServerStackIcon class="mx-auto h-12 w-12 text-gray-400" />
                    <h3 class="mt-2 text-sm font-semibold text-gray-900">{{ $t('No SIP status rows found') }}</h3>
                    <p class="mt-1 text-sm text-gray-500">{{ $t('Refresh the page or adjust your search.') }}</p>
                </div>
            </template>

            <template #loading>
                <Loading :show="loading" />
            </template>
        </DataTable>

        <section v-if="permissions.system_status_sofia_status_profile" class="px-4 sm:px-6 lg:px-8">
            <div class="mb-3 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-lg font-semibold leading-6 text-gray-600">{{ $t('Sofia Status Profiles') }}</h2>
                </div>
                <div class="flex gap-2">
                    <button
                        type="button"
                        class="rounded-md bg-white px-2.5 py-1.5 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50"
                        @click="expandAllProfiles"
                    >
                        {{ $t('Expand') }}
                    </button>
                    <button
                        type="button"
                        class="rounded-md bg-white px-2.5 py-1.5 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50"
                        @click="collapseAllProfiles"
                    >
                        {{ $t('Collapse') }}
                    </button>
                </div>
            </div>

            <div class="space-y-3">
                <div
                    v-for="profile in statusData.profiles"
                    :key="profile.sip_profile_uuid"
                    class="overflow-hidden rounded-lg bg-white shadow ring-1 ring-black ring-opacity-5"
                >
                    <div class="flex flex-col gap-3 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                        <button
                            type="button"
                            class="flex min-w-0 items-center gap-2 text-left text-sm font-semibold text-gray-900"
                            @click="toggleProfile(profile.sip_profile_name)"
                        >
                            <ChevronDownIcon v-if="isProfileOpen(profile.sip_profile_name)" class="h-5 w-5 flex-none text-gray-500" />
                            <ChevronRightIcon v-else class="h-5 w-5 flex-none text-gray-500" />
                            <span class="truncate">{{ profile.sip_profile_name }}</span>
                            <Badge
                                :text="profile.state === 'running' ? $t('Running') : $t('Stopped')"
                                :backgroundColor="statusColor(profile.state).backgroundColor"
                                :textColor="statusColor(profile.state).textColor"
                                :ringColor="statusColor(profile.state).ringColor"
                            />
                        </button>

                        <div class="flex flex-wrap items-center gap-2">
                            <button
                                v-if="permissions.can_run_commands"
                                type="button"
                                class="rounded-md bg-white px-2 py-1 text-xs font-semibold text-gray-700 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 disabled:opacity-50"
                                :disabled="actionLoading"
                                @click="submitAction('flush_inbound_reg', { profile: profile.sip_profile_name })"
                            >
                                {{ $t('Flush Registrations') }}
                            </button>
                            <a
                                :href="profile.registrations_url"
                                class="rounded-md bg-white px-2 py-1 text-xs font-semibold text-gray-700 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50"
                            >
                                {{ $t('Registrations (:count)', { count: profile.registration_count }) }}
                            </a>
                            <button
                                v-if="permissions.can_run_commands && profile.state === 'stopped'"
                                type="button"
                                class="rounded-md bg-white px-2 py-1 text-xs font-semibold text-gray-700 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 disabled:opacity-50"
                                :disabled="actionLoading"
                                @click="submitAction('start', { profile: profile.sip_profile_name })"
                            >
                                {{ $t('Start') }}
                            </button>
                            <button
                                v-if="permissions.can_run_commands && profile.state === 'running'"
                                type="button"
                                class="rounded-md bg-white px-2 py-1 text-xs font-semibold text-gray-700 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 disabled:opacity-50"
                                :disabled="actionLoading"
                                @click="submitAction('stop', { profile: profile.sip_profile_name })"
                            >
                                {{ $t('Stop') }}
                            </button>
                            <button
                                v-if="permissions.can_run_commands"
                                type="button"
                                class="rounded-md bg-white px-2 py-1 text-xs font-semibold text-gray-700 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 disabled:opacity-50"
                                :disabled="actionLoading"
                                @click="submitAction('restart', { profile: profile.sip_profile_name })"
                            >
                                {{ $t('Restart') }}
                            </button>
                            <button
                                v-if="permissions.can_run_commands"
                                type="button"
                                class="rounded-md bg-white px-2 py-1 text-xs font-semibold text-gray-700 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 disabled:opacity-50"
                                :disabled="actionLoading"
                                @click="submitAction('rescan', { profile: profile.sip_profile_name })"
                            >
                                {{ $t('Rescan') }}
                            </button>
                        </div>
                    </div>

                    <div v-if="isProfileOpen(profile.sip_profile_name)" class="border-t border-gray-200">
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <tbody class="divide-y divide-gray-100">
                                    <tr v-for="detail in profile.details" :key="detail.label">
                                        <td class="w-64 whitespace-nowrap bg-gray-50 px-4 py-2 text-sm font-medium text-gray-700">
                                            {{ detail.label }}
                                        </td>
                                        <td class="px-4 py-2 text-sm text-gray-600">
                                            <span class="break-all">{{ detail.value || '-' }}</span>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section v-if="permissions.sip_status_switch_status" class="px-4 sm:px-6 lg:px-8">
            <div class="overflow-hidden rounded-lg bg-white shadow ring-1 ring-black ring-opacity-5">
                <button
                    type="button"
                    class="flex w-full items-center justify-between px-4 py-3 text-left text-lg font-semibold leading-6 text-gray-600"
                    @click="showSwitchStatus = !showSwitchStatus"
                >
                    <span>{{ $t('Status') }}</span>
                    <ChevronDownIcon v-if="showSwitchStatus" class="h-5 w-5 text-gray-500" />
                    <ChevronRightIcon v-else class="h-5 w-5 text-gray-500" />
                </button>
                <div v-if="showSwitchStatus" class="border-t border-gray-200 bg-gray-950 px-4 py-3">
                    <pre class="max-h-[36rem] overflow-auto whitespace-pre-wrap text-xs leading-5 text-gray-100">{{ statusData.switch_status || '-' }}</pre>
                </div>
            </div>
        </section>

        <section v-if="permissions.can_manage_tls" class="px-4 sm:px-6 lg:px-8">
            <div class="overflow-hidden rounded-lg bg-white shadow ring-1 ring-black ring-opacity-5">
                <div class="border-b border-gray-200 px-4 py-3">
                    <h2 class="text-lg font-semibold leading-6 text-gray-600">{{ $t('TLS Certificate (Let\'s Encrypt)') }}</h2>
                    <p class="mt-1 text-sm text-gray-500">
                        {{ $t('Issues a free Let\'s Encrypt certificate for FreeSWITCH (SIP-TLS / WSS), installs it to :path, and reloads it with :command without restarting FreeSWITCH.', { path: '/etc/freeswitch/tls/all.pem', command: 'reloadcert' }) }}
                    </p>
                    <ul class="mt-2 list-disc space-y-0.5 pl-5 text-xs text-gray-500">
                        <li><strong>{{ $t('Validation:') }}</strong> {{ $t('HTTP-01 — a token is served on port 80 from the webroot below. Multiple hostnames (SANs) are supported for failover / dual-registration setups.') }}</li>
                        <li><strong>{{ $t('Phone trust:') }}</strong> {{ $t('The issuing root CA is automatically pushed to Polycom phones (:setting) so they trust the new certificate after provisioning.', { setting: 'customCaCert2' }) }}</li>
                        <li><strong>{{ $t('Renewal:') }}</strong> {{ $t('auto-renews when under 30 days remain and emails the ACME account address on success and failure.') }}</li>
                        <li><strong>{{ $t('Multi-node:') }}</strong> {{ $t('list the failover hostname first, then each node\'s direct hostname. The node the failover currently points to renews and replicates the cert to the other nodes (peers are auto-detected from the hostnames; each node skips itself). A failed replication fails the renewal so it retries — nodes never diverge.') }}</li>
                    </ul>
                </div>

                <div class="space-y-5 px-4 py-4">
                    <!-- Current certificate status -->
                    <div class="rounded-md bg-gray-50 p-3">
                        <div class="flex flex-wrap items-center gap-x-6 gap-y-1 text-sm">
                            <div class="flex items-center gap-2">
                                <span class="font-medium text-gray-700">{{ $t('Status:') }}</span>
                                <Badge
                                    :text="tlsBadge.text"
                                    :backgroundColor="tlsBadge.backgroundColor"
                                    :textColor="tlsBadge.textColor"
                                    :ringColor="tlsBadge.ringColor"
                                />
                            </div>
                            <div v-if="tlsCert.installed">
                                <span class="font-medium text-gray-700">{{ $t('Issuer:') }}</span>
                                <span class="text-gray-600">{{ tlsCert.issuer || '-' }}</span>
                            </div>
                            <div v-if="tlsCert.installed">
                                <span class="font-medium text-gray-700">{{ $t('Expires:') }}</span>
                                <span class="text-gray-600">{{ formatDate(tlsCert.valid_to) }}</span>
                                <span v-if="tlsCert.days_remaining !== null" class="text-gray-500">
                                    ({{ $tChoice(':count day remaining|:count days remaining', tlsCert.days_remaining) }})
                                </span>
                            </div>
                            <div v-if="tlsCert.domains && tlsCert.domains.length">
                                <span class="font-medium text-gray-700">{{ $t('Domains:') }}</span>
                                <span class="text-gray-600">{{ tlsCert.domains.join(', ') }}</span>
                            </div>
                            <div v-if="tlsCert.installed && tlsCert.serial">
                                <span class="font-medium text-gray-700">{{ $t('Serial:') }}</span>
                                <span class="break-all font-mono text-xs text-gray-600">{{ tlsCert.serial }}</span>
                            </div>
                        </div>

                        <!-- On-disk install integrity (all.pem + FreeSWITCH symlinks) -->
                        <div class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs">
                            <span class="font-medium text-gray-700">{{ $t('Files:') }}</span>
                            <span :class="tlsFiles.all_pem ? 'text-emerald-700' : 'text-red-600'">
                                all.pem {{ tlsFiles.all_pem ? '✓' : $t('✗ missing') }}
                            </span>
                            <span :class="tlsFiles.links_ok ? 'text-emerald-700' : 'text-red-600'">
                                {{ $t('symlinks') }} {{ tlsFiles.links_ok ? '✓' : '✗' }}
                            </span>
                            <span v-if="!tlsFiles.links_ok" class="text-gray-500">
                                ({{ brokenLinks.join(', ') || $t('check /etc/freeswitch/tls') }})
                            </span>
                            <span class="text-gray-500">{{ $t('Verify:') }} <code class="rounded bg-gray-100 px-1">openssl x509 -in /etc/freeswitch/tls/all.pem -noout -issuer -serial -dates</code></span>
                        </div>

                        <p v-if="tlsConfig.last_issued" class="mt-2 text-xs text-gray-500">
                            {{ $t('Last issued by FS PBX:') }} {{ formatDate(tlsConfig.last_issued) }}
                        </p>
                        <p v-if="tlsConfig.last_revoked" class="mt-1 text-xs text-gray-500">
                            {{ $t('Last revoked:') }} {{ formatDate(tlsConfig.last_revoked) }}
                        </p>
                        <p v-if="tlsConfig.last_error" class="mt-2 text-xs text-red-600">
                            {{ $t('Last error:') }} {{ tlsConfig.last_error }}
                        </p>
                    </div>

                    <!-- Configuration -->
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <LabelInputRequired target="tls_domain" :label="$t('Hostnames (SANs)')" />
                            <div class="mt-1">
                                <InputField v-model="tlsConfig.domain" type="text" name="tls_domain"
                                    placeholder="pbx.example.com pbx01.example.com"
                                    autocomplete="off" :error="!!tlsErrors.domain" />
                            </div>
                            <p v-if="tlsErrors.domain" class="mt-1 text-xs text-red-600">{{ tlsErrors.domain[0] }}</p>
                            <p v-else class="mt-1 text-xs text-gray-500">{{ $t('Separate hostnames with spaces or commas. For a cluster, list the failover/proxy hostname first to select the active node, then each node\'s direct hostname. Defaults to this server\'s app URL host.') }}</p>
                        </div>
                        <div>
                            <LabelInputRequired target="tls_email" :label="$t('ACME account email')" />
                            <div class="mt-1">
                                <InputField v-model="tlsConfig.account_email" type="email" name="tls_email"
                                    placeholder="admin@example.com" autocomplete="off" :error="!!tlsErrors.account_email" />
                            </div>
                            <p v-if="tlsErrors.account_email" class="mt-1 text-xs text-red-600">{{ tlsErrors.account_email[0] }}</p>
                            <p v-else class="mt-1 text-xs text-gray-500">{{ $t('Let\'s Encrypt account — also where renewal alert emails are sent.') }}</p>
                        </div>
                        <div>
                            <LabelInputRequired target="tls_webroot" :label="$t('ACME challenge webroot')" />
                            <div class="mt-1">
                                <InputField v-model="tlsConfig.webroot" type="text" name="tls_webroot"
                                    placeholder="/var/www/fspbx/public" autocomplete="off" :error="!!tlsErrors.webroot" />
                            </div>
                            <p v-if="tlsErrors.webroot" class="mt-1 text-xs text-red-600">{{ tlsErrors.webroot[0] }}</p>
                            <p v-else class="mt-1 text-xs text-gray-500">{{ $t('Document root served on port 80. Challenge tokens are written under :path. Defaults to the app\'s public directory.', { path: '/.well-known/acme-challenge/' }) }}</p>
                        </div>
                        <div class="flex flex-col justify-center gap-3">
                            <Toggle v-model="tlsStaging" :label="$t('Use staging (test) directory')" :description="$t('Avoid Let\'s Encrypt rate limits while testing. Staging certs are not trusted by clients.')" />
                            <Toggle v-model="tlsAutoRenew" :label="$t('Auto-renew')" :description="$t('Renew daily when under 30 days remain.')" />
                        </div>
                        <div>
                            <LabelInputOptional target="tls_secret" :label="$t('Peer push secret')" />
                            <div class="mt-1 flex items-stretch gap-2">
                                <div class="flex-1">
                                    <InputField v-model="tlsConfig.push_secret" :type="showSecret ? 'text' : 'password'" name="tls_secret"
                                        :placeholder="$t('shared key for node-to-node cert push')" autocomplete="new-password" :error="!!tlsErrors.push_secret" />
                                </div>
                                <button type="button" @click="showSecret = !showSecret"
                                    class="inline-flex items-center rounded-md bg-white px-2 text-gray-500 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50"
                                    :title="showSecret ? $t('Hide') : $t('Reveal')">
                                    <EyeSlashIcon v-if="showSecret" class="h-4 w-4" />
                                    <EyeIcon v-else class="h-4 w-4" />
                                </button>
                                <button type="button" @click="rotateSecret" :disabled="tlsLoading"
                                    class="inline-flex items-center gap-1 rounded-md bg-white px-2.5 text-xs font-semibold text-gray-700 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 disabled:opacity-50"
                                    :title="$t('Generate a new secret')">
                                    <ArrowPathIcon class="h-4 w-4" />
                                    {{ $t('Rotate') }}
                                </button>
                            </div>
                            <p v-if="tlsErrors.push_secret" class="mt-1 text-xs text-red-600">{{ tlsErrors.push_secret[0] }}</p>
                            <p v-else class="mt-1 text-xs text-gray-500">{{ $t('Required for multi-node — authorizes cert replication between nodes. Click Rotate to generate one.') }}</p>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <!-- Revoke (only meaningful for an installed Let's Encrypt cert) -->
                        <div v-if="tlsCert.installed && tlsCert.is_lets_encrypt" class="flex items-center gap-2">
                            <template v-if="!confirmingRevoke">
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-1.5 rounded-md bg-white px-2.5 py-1.5 text-sm font-semibold text-red-600 shadow-sm ring-1 ring-inset ring-red-300 hover:bg-red-50 disabled:opacity-50"
                                    :disabled="tlsLoading"
                                    @click="confirmingRevoke = true"
                                >
                                    <ShieldExclamationIcon class="h-4 w-4" />
                                    {{ $t('Revoke') }}
                                </button>
                            </template>
                            <template v-else>
                                <span class="text-sm text-gray-600">{{ $t('Revoke & replace with self-signed?') }}</span>
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-1.5 rounded-md bg-red-600 px-2.5 py-1.5 text-sm font-semibold text-white shadow-sm hover:bg-red-500 disabled:opacity-50"
                                    :disabled="tlsLoading"
                                    @click="revokeTls"
                                >
                                    <ArrowPathIcon v-if="tlsLoading" class="h-4 w-4 animate-spin" />
                                    {{ $t('Yes, revoke') }}
                                </button>
                                <button
                                    type="button"
                                    class="inline-flex items-center rounded-md bg-white px-2.5 py-1.5 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 disabled:opacity-50"
                                    :disabled="tlsLoading"
                                    @click="confirmingRevoke = false"
                                >
                                    {{ $t('Cancel') }}
                                </button>
                            </template>
                        </div>
                        <div v-else></div>

                        <div class="flex flex-wrap items-center gap-2">
                            <button
                                type="button"
                                class="inline-flex items-center gap-1.5 rounded-md bg-white px-2.5 py-1.5 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 disabled:opacity-50"
                                :disabled="tlsLoading"
                                @click="saveTlsConfig"
                            >
                                {{ $t('Save settings') }}
                            </button>
                            <button
                                type="button"
                                class="inline-flex items-center gap-1.5 rounded-md bg-indigo-600 px-2.5 py-1.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 disabled:opacity-50"
                                :disabled="tlsLoading"
                                @click="issueTls"
                            >
                                <ArrowPathIcon class="h-4 w-4" :class="{ 'animate-spin': tlsLoading }" />
                                {{ tlsCert.installed && tlsCert.is_lets_encrypt ? $t('Renew now') : $t('Issue certificate') }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>

    <Notification :show="notificationShow" :type="notificationType" :messages="notificationMessages" @update:show="hideNotification" />
</template>

<script setup>
import { trans, transChoice } from '@i18n';
import { computed, onMounted, ref } from "vue";
import axios from "axios";
import MainLayout from "../Layouts/MainLayout.vue";
import DataTable from "./components/general/DataTable.vue";
import TableColumnHeader from "./components/general/TableColumnHeader.vue";
import TableField from "./components/general/TableField.vue";
import Badge from "./components/general/Badge.vue";
import Loading from "./components/general/Loading.vue";
import Toggle from "./components/general/Toggle.vue";
import InputField from "./components/general/InputField.vue";
import LabelInputRequired from "./components/general/LabelInputRequired.vue";
import LabelInputOptional from "./components/general/LabelInputOptional.vue";
import { EyeIcon, EyeSlashIcon } from "@heroicons/vue/24/outline";
import Notification from "./components/notifications/Notification.vue";
import {
    ArchiveBoxXMarkIcon,
    ArrowPathIcon,
    ChevronDownIcon,
    ChevronRightIcon,
    CodeBracketIcon,
    MagnifyingGlassIcon,
    ServerStackIcon,
    ShieldCheckIcon,
    ShieldExclamationIcon,
} from "@heroicons/vue/24/solid";

const props = defineProps({
    routes: Object,
    permissions: Object,
});

const statusData = ref({
    connected: false,
    generated_at: null,
    summary: [],
    profiles: [],
    switch_status: null,
});
const loading = ref(false);
const actionLoading = ref(false);
const notificationShow = ref(false);
const notificationType = ref(null);
const notificationMessages = ref(null);
const filterData = ref({ search: "" });
const activeSearch = ref("");
const openProfiles = ref([]);
const showSwitchStatus = ref(true);

const tlsConfig = ref({
    domain: "",
    account_email: "",
    webroot: "",
    push_secret: "",
    last_issued: null,
    last_revoked: null,
    last_error: null,
});
const tlsErrors = ref({});
const showSecret = ref(false);
const confirmingRevoke = ref(false);
const tlsCert = ref({
    installed: false,
    domains: [],
    issuer: null,
    is_self_signed: false,
    serial: null,
    is_staging: false,
    valid_to: null,
    days_remaining: null,
    is_lets_encrypt: false,
});
const tlsFiles = ref({ all_pem: false, links_ok: false, links: {} });
const tlsStaging = ref(true);
const tlsAutoRenew = ref(false);
const tlsLoading = ref(false);

const permissions = computed(() => props.permissions ?? {});

const sipTypeLabel = (type) => ({
    profile: trans('Profile'),
    gateway: trans('Gateway'),
    alias: trans('Alias'),
})[String(type).toLowerCase()] ?? type;

const tlsBadge = computed(() => {
    if (!tlsCert.value.installed) {
        return { text: trans('No certificate'), ...statusColor("stopped") };
    }

    const days = tlsCert.value.days_remaining;

    if (days !== null && days <= 0) {
        return { text: trans('Expired'), ...statusColor("fail") };
    }

    if (days !== null && days <= 14) {
        return { text: transChoice('Expiring (:count day)|Expiring (:count days)', days), ...statusColor("warn") };
    }

    if (tlsCert.value.is_lets_encrypt) {
        return tlsCert.value.is_staging
            ? { text: trans('Active (Let\'s Encrypt staging)'), ...statusColor("warn") }
            : { text: trans('Active (Let\'s Encrypt)'), ...statusColor("up") };
    }

    if (tlsCert.value.is_self_signed) {
        return { text: trans('Self-signed'), ...statusColor("warn") };
    }

    return { text: trans('Active'), ...statusColor("up") };
});

const filteredSummary = computed(() => {
    const needle = activeSearch.value.trim().toLowerCase();

    if (!needle) {
        return statusData.value.summary;
    }

    return statusData.value.summary.filter((row) => {
        return [row.name, row.type, row.data, row.state]
            .filter(Boolean)
            .some((value) => String(value).toLowerCase().includes(needle));
    });
});

onMounted(() => {
    fetchData();

    if (permissions.value.can_manage_tls && props.routes.tls_status) {
        fetchTls();
    }
});

const applyTlsStatus = (data) => {
    if (data.config) {
        tlsConfig.value = {
            domain: data.config.domain || "",
            account_email: data.config.account_email || "",
            webroot: data.config.webroot || "",
            push_secret: data.config.push_secret || "",
            last_issued: data.config.last_issued || null,
            last_revoked: data.config.last_revoked || null,
            last_error: data.config.last_error || null,
        };
        tlsStaging.value = data.config.staging !== "false";
        tlsAutoRenew.value = data.config.auto_renew === "true";
    }

    if (data.certificate) {
        tlsCert.value = data.certificate;
    }

    if (data.files) {
        tlsFiles.value = data.files;
    }
};

const brokenLinks = computed(() =>
    Object.entries(tlsFiles.value.links || {})
        .filter(([, state]) => state !== "symlink")
        .map(([name, state]) => `${name}: ${state}`)
);

const fetchTls = () => {
    tlsLoading.value = true;

    axios.get(props.routes.tls_status)
        .then((response) => applyTlsStatus(response.data))
        .catch(handleError)
        .finally(() => {
            tlsLoading.value = false;
        });
};

const tlsSettingsPayload = () => ({
    domain: tlsConfig.value.domain,
    account_email: tlsConfig.value.account_email,
    webroot: tlsConfig.value.webroot,
    staging: tlsStaging.value,
    auto_renew: tlsAutoRenew.value,
    push_secret: tlsConfig.value.push_secret,
});

const saveTlsConfig = () => {
    tlsLoading.value = true;
    tlsErrors.value = {};

    axios.post(props.routes.tls_config, tlsSettingsPayload())
        .then((response) => {
            if (response.data.status) {
                applyTlsStatus(response.data.status);
            }
            showNotification("success", response.data.messages || { success: [trans('Settings saved.')] });
        })
        .catch((error) => {
            if (error?.response?.status === 422 && error.response.data?.errors) {
                tlsErrors.value = error.response.data.errors;
            } else {
                handleError(error);
            }
        })
        .finally(() => {
            tlsLoading.value = false;
        });
};

const rotateSecret = () => {
    tlsLoading.value = true;

    axios.post(props.routes.tls_generate_secret, {})
        .then((response) => {
            tlsConfig.value.push_secret = response.data.secret;
            showSecret.value = true;
            showNotification("success", response.data.messages || { success: [trans('Peer push secret rotated and saved.')] });
        })
        .catch(handleError)
        .finally(() => {
            tlsLoading.value = false;
        });
};

const issueTls = () => {
    tlsLoading.value = true;
    tlsErrors.value = {};

    axios.post(props.routes.tls_issue, tlsSettingsPayload())
        .then((response) => {
            if (response.data.status) {
                applyTlsStatus(response.data.status);
            }
            showNotification("success", response.data.messages || { success: [trans('Certificate issued.')] });
        })
        .catch((error) => {
            if (error?.response?.status === 422 && error.response.data?.errors) {
                tlsErrors.value = error.response.data.errors;
            } else {
                handleError(error);
            }
        })
        .finally(() => {
            tlsLoading.value = false;
        });
};

const revokeTls = () => {
    tlsLoading.value = true;

    axios.post(props.routes.tls_revoke, {})
        .then((response) => {
            confirmingRevoke.value = false;
            if (response.data.status) {
                applyTlsStatus(response.data.status);
            }
            showNotification("success", response.data.messages || { success: [trans('Certificate revoked.')] });
        })
        .catch(handleError)
        .finally(() => {
            tlsLoading.value = false;
        });
};

const fetchData = () => {
    loading.value = true;

    axios.get(props.routes.data_route)
        .then((response) => {
            statusData.value = {
                connected: response.data.connected,
                generated_at: response.data.generated_at,
                summary: response.data.summary || [],
                profiles: response.data.profiles || [],
                switch_status: response.data.switch_status,
            };
        })
        .catch(handleError)
        .finally(() => {
            loading.value = false;
        });
};

const submitAction = (action, payload = {}) => {
    actionLoading.value = true;

    axios.post(props.routes.action, { action, ...payload })
        .then((response) => {
            showNotification("success", response.data.messages || { success: [trans('Request successfully processed.')] });
            fetchData();
        })
        .catch(handleError)
        .finally(() => {
            actionLoading.value = false;
        });
};

const handleSearch = () => {
    activeSearch.value = filterData.value.search || "";
};

const resetFilters = () => {
    filterData.value.search = "";
    activeSearch.value = "";
};

const toggleProfile = (profileName) => {
    if (isProfileOpen(profileName)) {
        openProfiles.value = openProfiles.value.filter((name) => name !== profileName);
        return;
    }

    openProfiles.value = [...openProfiles.value, profileName];
};

const isProfileOpen = (profileName) => openProfiles.value.includes(profileName);

const expandAllProfiles = () => {
    openProfiles.value = statusData.value.profiles.map((profile) => profile.sip_profile_name);
};

const collapseAllProfiles = () => {
    openProfiles.value = [];
};

const statusColor = (status) => {
    const normalized = String(status || "").toLowerCase();

    if (normalized.includes("running") || normalized.includes("reged") || normalized.includes("up")) {
        return {
            backgroundColor: "bg-emerald-50",
            textColor: "text-emerald-700",
            ringColor: "ring-emerald-600/20",
        };
    }

    if (normalized.includes("down") || normalized.includes("fail") || normalized.includes("stopped")) {
        return {
            backgroundColor: "bg-red-50",
            textColor: "text-red-700",
            ringColor: "ring-red-600/20",
        };
    }

    if (normalized.includes("warn") || normalized.includes("expir")) {
        return {
            backgroundColor: "bg-amber-50",
            textColor: "text-amber-700",
            ringColor: "ring-amber-600/20",
        };
    }

    return {
        backgroundColor: "bg-gray-50",
        textColor: "text-gray-700",
        ringColor: "ring-gray-600/20",
    };
};

const showNotification = (type, messages) => {
    notificationType.value = type;
    notificationMessages.value = messages;
    notificationShow.value = true;
};

const hideNotification = () => {
    notificationShow.value = false;
    notificationType.value = null;
    notificationMessages.value = null;
};

const handleError = (error) => {
    notificationType.value = "error";
    notificationMessages.value = normalizeMessages(error);
    notificationShow.value = true;
};

const normalizeMessages = (error) => {
    const payload = error?.response?.data;

    if (payload?.errors) {
        return payload.errors;
    }

    if (payload?.messages) {
        return payload.messages;
    }

    if (payload?.message) {
        return { request: [payload.message] };
    }

    if (error?.message) {
        return { request: [error.message] };
    }

    return { request: [trans('An unexpected error occurred.')] };
};

const formatDate = (value) => {
    if (!value) {
        return "-";
    }

    const date = new Date(value);
    return Number.isNaN(date.getTime()) ? value : date.toLocaleString();
};
</script>
