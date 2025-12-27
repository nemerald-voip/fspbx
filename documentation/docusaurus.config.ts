import { themes as prismThemes } from 'prism-react-renderer';
import type { Config } from '@docusaurus/types';
import type * as Preset from '@docusaurus/preset-classic';
import type * as OpenApiPlugin from "docusaurus-plugin-openapi-docs";

// This runs in Node.js - Don't use client-side code here (browser APIs, JSX...)

const config: Config = {
    title: 'FS PBX – Open Source PBX Dashboard for VoIP Providers',
    tagline: 'FS PBX is an open-source FreeSWITCH PBX with a sleek white‑label VoIP dashboard – a FusionPBX alternative for telecom providers, resellers, and enthusiasts.',
    favicon: 'img/favicon.ico',

    // Future flags, see https://docusaurus.io/docs/api/docusaurus-config#future
    future: {
        v4: false, // Improve compatibility with the upcoming Docusaurus v4
    },

    // Set the production url of your site here
    url: 'https://www.fspbx.com/',
    // Set the /<baseUrl>/ pathname under which your site is served
    // For GitHub pages deployment, it is often '/<projectName>/'
    baseUrl: '/',
    trailingSlash: true,

    // GitHub pages deployment config.
    // If you aren't using GitHub pages, you don't need these.
    organizationName: 'nemerald-voip', // Usually your GitHub org/user name.
    projectName: 'fspbx', // Usually your repo name.

    onBrokenLinks: 'throw',
    onBrokenMarkdownLinks: 'throw',

    // Even if you don't use internationalization, you can use this field to set
    // useful metadata like html lang. For example, if your site is Chinese, you
    // may want to replace "en" with "zh-Hans".
    i18n: {
        defaultLocale: 'en',
        locales: ['en'],
    },

    presets: [
        [
            'classic',
            {
                docs: {
                    sidebarPath: './sidebars.ts',
                    docItemComponent: "@theme/ApiItem",
                    // Please change this to your repo.
                    // Remove this to remove the "edit this page" links.
                    editUrl:
                        'https://github.com/facebook/docusaurus/tree/main/packages/create-docusaurus/templates/shared/',
                },
                blog: {
                    showReadingTime: true,
                    feedOptions: {
                        type: ['rss', 'atom'],
                        xslt: true,
                    },
                    // Please change this to your repo.
                    // Remove this to remove the "edit this page" links.
                    editUrl:
                        'https://github.com/facebook/docusaurus/tree/main/packages/create-docusaurus/templates/shared/',
                    // Useful options to enforce blogging best practices
                    onInlineTags: 'warn',
                    onInlineAuthors: 'warn',
                    onUntruncatedBlogPosts: 'warn',
                },
                theme: {
                    customCss: './src/css/custom.css',
                },
                sitemap: {
                    lastmod: 'date',
                    changefreq: 'weekly',
                    priority: 0.5,
                    ignorePatterns: ['/tags/**'],
                    filename: 'sitemap.xml',
                    createSitemapItems: async (params) => {
                        const { defaultCreateSitemapItems, ...rest } = params;
                        const items = await defaultCreateSitemapItems(rest);
                        return items.filter((item) => !item.url.includes('/page/'));
                    },
                },
            } satisfies Preset.Options,
        ],
    ],

    themeConfig: {
        metadata: [
            { name: 'description', content: 'FS PBX is an open-source FreeSWITCH PBX with a sleek white‑label VoIP dashboard – a FusionPBX alternative for telecom providers, resellers, and enthusiasts.' },
            { name: 'keywords', content: 'open source PBX, FS PBX, FusionPBX, FusionPBX alternative, FreeSWITCH, VoIP, VoIP dashboard, white label VoIP, telecom, telecom provider' },
        ],
        // Replace with your project's social card
        image: 'img/fspbx-social-card.png',
        navbar: {
            title: '',
            logo: {
                alt: 'FS PBX Logo',
                src: 'img/logo.png',
            },
            items: [
                {
                    type: 'docSidebar',
                    sidebarId: 'tutorialSidebar',
                    position: 'left',
                    label: 'Docs',
                },
                { to: '/blog', label: 'Blog', position: 'left' },
                {
                    type: "docSidebar",
                    sidebarId: "apiSidebar",
                    label: "API",
                    position: "left",
                },
                {
                    href: 'https://github.com/nemerald-voip/fspbx',
                    label: 'GitHub',
                    position: 'right',
                },
            ],
        },
        footer: {
            style: 'dark',
            links: [
                {
                    title: 'Docs',
                    items: [
                        {
                            label: 'Docs',
                            to: '/docs/intro',
                        },
                    ],
                },
                {
                    title: 'Community',
                    items: [
                        {
                            label: 'YouTube',
                            href: 'https://www.youtube.com/channel/UCrWZVlRdLe_X2f_rRqLmfvw',
                        },
                        {
                            label: 'Reddit',
                            href: 'https://www.reddit.com/r/FSPBX/',
                        },
                    ],
                },
                {
                    title: 'More',
                    items: [
                        {
                            label: 'Blog',
                            to: '/blog',
                        },
                        {
                            label: 'GitHub',
                            href: 'https://github.com/nemerald-voip/fspbx',
                        },
                    ],
                },
            ],
            copyright: `Copyright © ${new Date().getFullYear()} FS PBX.`,
        },
        prism: {
            theme: prismThemes.github,
            darkTheme: prismThemes.dracula,
        },
        algolia: {
            // The application ID provided by Algolia
            appId: 'YQ5EHUW4KL',

            // Public API key: it is safe to commit it
            apiKey: 'e1a4e92ec4048f2297c96d7cf229d6fa',

            indexName: 'FS PBX Docs',

            // Optional: see doc section below
            contextualSearch: true,

            // Optional: Specify domains where the navigation should occur through window.location instead on history.push. Useful when our Algolia config crawls multiple documentation sites and we want to navigate with window.location.href to them.
            externalUrlRegex: 'external\\.com|domain\\.com',

            // Optional: Replace parts of the item URLs from Algolia. Useful when using the same search index for multiple deployments using a different baseUrl. You can use regexp or string in the `from` param. For example: localhost:3000 vs myCompany.com/docs
            // replaceSearchResultPathname: {
            //   from: '/docs/', // or as RegExp: /\/docs\//
            //   to: '/',
            // },

            // Optional: Algolia search parameters
            searchParameters: {},

            // Optional: path for search page that enabled by default (`false` to disable it)
            searchPagePath: 'search',

            // Optional: whether the insights feature is enabled or not on Docsearch (`false` by default)
            insights: false,

            // Optional: whether you want to use the new Ask AI feature (undefined by default)
            // askAi: 'YOUR_ALGOLIA_ASK_AI_ASSISTANT_ID',

            //... other Algolia params
        },

    } satisfies Preset.ThemeConfig,
    plugins: [
        "./src/plugins/tailwind-config.js",

        [
            "docusaurus-plugin-openapi-docs",
            {
                id: "api",
                docsPluginId: "classic",
                config: {
                    fspbx_v1: {
                        // This is relative to the Docusaurus site dir (documentation/)
                        specPath: "static/openapi/openapi.yaml",
                        outputDir: "docs/api/v1",
                        sidebarOptions: {
                            groupPathsBy: "tag",
                        },
                        hideSendButton: true,
                        infoTemplate: "static/openapi/templates/fspbx-info.mdx",

                    } satisfies OpenApiPlugin.Options,
                },
            },
        ],
    ],
    themes: ["docusaurus-theme-openapi-docs"],
};

export default config;
