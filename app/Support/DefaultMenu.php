<?php

namespace App\Support;

class DefaultMenu
{
    // Literal translation calls keep these labels discoverable by lang:sync.
    // The installer uses English; the GUI uses the requested menu language.
    public static function categories(string $locale = 'en-us'): array
    {
        return [
            // NOTE: The legacy "Home" menu (Dashboard + Logout) has been removed.
            // The dashboard is reachable via the logo, and Logout now lives in the
            // top-right user menu (see resources/js/Pages/components/Menu.vue).
            [
                'title' => __('Accounts', [], $locale),
                'link' => null,
                'groups' => ['superadmin', 'admin'],
                'subcategories' => [
                    ['title' => __('Devices', [], $locale), 'link' => '/devices','groups' => ['superadmin', 'admin']],
                    ['title' => __('Extensions', [], $locale), 'link' => '/extensions', 'groups' => ['superadmin', 'admin']],
                    ['title' => __('Gateways', [], $locale), 'link' => '/gateways', 'groups' => ['superadmin']],
                    ['title' => __('Users', [], $locale), 'link' => '/users', 'groups' => ['superadmin', 'admin']],
                ],
            ],
            [
                'title' => __('Dialplan', [], $locale),
                'link' => null,
                'groups' => ['superadmin', 'admin'],
                'subcategories' => [
                    ['title' => __('Dialplan Manager', [], $locale), 'link' => '/dialplans', 'groups' => ['superadmin', 'admin']],
                    ['title' => __('Phone Numbers', [], $locale), 'link' => '/phone-numbers','groups' => ['superadmin', 'admin']],
                    ['title' => __('Inbound Routes', [], $locale), 'link' => '/dialplans?category=inbound', 'groups' => ['superadmin']],
                    ['title' => __('Outbound Routes', [], $locale), 'link' => '/dialplans?category=outbound', 'groups' => ['superadmin']],
                ],
            ],
            [
                'title' => __('Applications', [], $locale),
                'link' => null,
                'groups' => ['superadmin', 'admin', 'user', 'fax', 'agent'],
                'subcategories' => [
                    ['title' => __('Basic Dialer', [], $locale), 'link' => '/basic-dialer', 'groups' => ['superadmin', 'admin']],
                    ['title' => __('Basic Queues', [], $locale), 'link' => '/basic-queues', 'groups' => ['superadmin', 'admin']],
                    ['title' => __('Bridges', [], $locale), 'link' => '/bridges', 'groups' => ['superadmin']],
                    ['title' => __('Call Block', [], $locale), 'link' => '/call-blocks', 'groups' => ['superadmin', 'admin', 'user']],
                    ['title' => __('Call History', [], $locale), 'link' => '/call-detail-records', 'groups' => ['superadmin', 'admin', 'user']],
                    ['title' => __('Call Flows', [], $locale), 'link' => '/call-flows', 'groups' => ['superadmin', 'admin']],
                    ['title' => __('Dynamic Routes', [], $locale), 'link' => '/dynamic-routes', 'groups' => ['superadmin', 'admin']],
                    ['title' => __('Conference Centers', [], $locale), 'link' => '/conference-centers', 'groups' => ['superadmin', 'admin']],
                    ['title' => __('Conferences', [], $locale), 'link' => '/conferences', 'groups' => ['superadmin', 'admin']],
                    ['title' => __('Faxes', [], $locale), 'link' => '/faxes', 'groups' => ['superadmin', 'admin', 'fax', 'user']],
                    ['title' => __('Virtual Receptionists', [], $locale), 'link' => '/virtual-receptionists', 'groups' => ['superadmin', 'admin']],
                    ['title' => __('Messages', [], $locale), 'link' => '/messages', 'groups' => ['superadmin']],
                    ['title' => __('Music on Hold', [], $locale), 'link' => '/music-on-hold','groups' => ['superadmin']],
                    ['title' => __('Recordings Manager', [], $locale), 'link' => '/recordings-manager', 'groups' => ['superadmin', 'admin']],
                    ['title' => __('Ring Groups', [], $locale), 'link' => '/ring-groups', 'groups' => ['superadmin', 'admin']],
                    ['title' => __('Streams', [], $locale), 'link' => '/streams', 'groups' => ['superadmin']],
                    ['title' => __('Business Hours', [], $locale), 'link' => '/business-hours', 'groups' => ['superadmin', 'admin']],
                    ['title' => __('Voicemails', [], $locale), 'link' => '/voicemails', 'groups' => ['superadmin', 'admin']],
                    ['title' => __('Wakeup Calls', [], $locale), 'link' => '/wakeup-calls', 'groups' => ['superadmin', 'admin']],
                    ['title' => __('Scheduled Announcements', [], $locale), 'link' => '/scheduled-announcements', 'groups' => ['superadmin', 'admin']],
                ],
            ],
            [
                'title' => __('Status', [], $locale),
                'link' => null,
                'groups' => ['superadmin', 'admin'],
                'subcategories' => [
                    ['title' => __('Active Calls', [], $locale), 'link' => '/active-calls', 'groups' => ['superadmin', 'admin']],
                    ['title' => __('Active Basic Queues', [], $locale), 'link' => '/active-basic-queues', 'groups' => ['superadmin', 'admin']],
                    ['title' => __('Active Conferences', [], $locale), 'link' => '/active-conferences', 'groups' => ['superadmin', 'admin']],
                    ['title' => __('Extension Statistics', [], $locale), 'link' => '/extension-statistics', 'groups' => ['superadmin', 'admin']],
                    ['title' => __('Firewall', [], $locale), 'link' => '/firewall', 'groups' => ['superadmin']],
                    ['title' => __('Logs', [], $locale), 'link' => '/logs', 'groups' => ['superadmin']],
                    ['title' => __('Registrations', [], $locale), 'link' => '/registrations', 'groups' => ['superadmin', 'admin']],
                    ['title' => __('SIP Status', [], $locale), 'link' => '/sip-status', 'groups' => ['superadmin']],
                    ['title' => __('System Status', [], $locale), 'link' => '/system', 'groups' => ['superadmin']],
                    ['title' => __('User Logs', [], $locale), 'link' => '/user-logs', 'groups' => ['superadmin']],
                ],
            ],
            [
                'title' => __('Advanced', [], $locale),
                'link' => null,
                'groups' => ['superadmin'],
                'subcategories' => [
                    ['title' => __('Access Control', [], $locale), 'link' => '/access-controls', 'groups' => ['superadmin']],
                    ['title' => __('Default Settings', [], $locale), 'link' => '/default-settings', 'groups' => ['superadmin']],
                    ['title' => __('Domains', [], $locale), 'link' => '/domains', 'groups' => ['superadmin']],
                    ['title' => __('Email templates', [], $locale), 'link' => '/email-templates', 'groups' => ['superadmin']],
                    ['title' => __('Group Manager', [], $locale), 'link' => '/groups', 'groups' => ['superadmin']],
                    ['title' => __('Legacy Provision Templates', [], $locale), 'link' => '/legacy-provision-templates', 'groups' => ['superadmin']],
                    ['title' => __('Menu Manager', [], $locale), 'link' => '/menus', 'groups' => ['superadmin']],
                    ['title' => __('Message Settings', [], $locale), 'link' => '/message-settings', 'groups' => ['superadmin']],
                    ['title' => __('Modules', [], $locale), 'link' => '/modules', 'groups' => ['superadmin']],
                    ['title' => __('Pro Features', [], $locale), 'link' => '/pro-features', 'groups' => ['superadmin']],
                    ['title' => __('Ringotel App Settings', [], $locale), 'link' => '/apps', 'groups' => ['superadmin', 'admin']],
                    ['title' => __('SIP Profiles', [], $locale), 'link' => '/sip-profiles', 'groups' => ['superadmin']],
                    ['title' => __('System Settings', [], $locale), 'link' => '/system-settings', 'groups' => ['superadmin']],
                    ['title' => __('Transactions', [], $locale), 'link' => '/database-transactions', 'groups' => ['superadmin']],
                    ['title' => __('Variables', [], $locale), 'link' => '/vars', 'groups' => ['superadmin']],
                ],
            ],
        ];

    }
}
