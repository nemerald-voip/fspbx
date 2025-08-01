import {themes as prismThemes} from 'prism-react-renderer';
import type {Config} from '@docusaurus/types';
import type * as Preset from '@docusaurus/preset-classic';

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
        // {to: '/blog', label: 'Blog', position: 'left'},
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
              label: 'You Tube',
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
  } satisfies Preset.ThemeConfig,
  plugins: ["./src/plugins/tailwind-config.js"],
};

export default config;
