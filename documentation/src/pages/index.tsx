import type { ReactNode } from 'react';
import { useState } from "react";
import clsx from 'clsx';
import Link from '@docusaurus/Link';
import useDocusaurusContext from '@docusaurus/useDocusaurusContext';
import Layout from '@theme/Layout';
import { Carousel } from 'react-responsive-carousel';
import 'react-responsive-carousel/lib/styles/carousel.min.css';
import { Disclosure, DisclosureButton, DisclosurePanel } from '@headlessui/react'
import { LockClosedIcon, CheckIcon } from '@heroicons/react/20/solid'
import { ComputerDesktopIcon, BoltIcon, CpuChipIcon, PuzzlePieceIcon, ServerStackIcon, PlusIcon, MinusIcon } from "@heroicons/react/24/outline";
import ContactForm from '@site/src/components/ContactForm';
import Modal from "@site/src/components/Modal";
import Head from '@docusaurus/Head';

import styles from './index.module.css';


export default function Home(): ReactNode {
    const { siteConfig } = useDocusaurusContext();
    const [modalOpen, setModalOpen] = useState(false);
    const [stripeModal, setStripeModal] = useState<{
        open: boolean;
        title: string;
        description: string;
        pricingTableId: string;
    }>({
        open: false,
        title: '',
        description: '',
        pricingTableId: '',
    });

    const openStripePricingModal = ({
        title,
        description,
        pricingTableId,
    }: {
        title: string;
        description: string;
        pricingTableId: string;
    }) => {
        setStripeModal({
            open: true,
            title,
            description,
            pricingTableId,
        });
    };
    const features = [
        {
            name: 'Modern Web Interface.',
            description: 'Rebuilt from the ground up with Laravel (backend) and Vue.js (frontend) for a responsive, snappy UI. The interface is clean and intuitive, avoiding the clutter of older GUIs. Users and admins can navigate with ease, making changes without confusion. Tailwind CSS is used for stylish, mobile-friendly design',
            icon: ComputerDesktopIcon,
        },
        {
            name: 'Robust Performance.',
            description: 'Engineered for efficiency and scale. FS PBX addresses performance bottlenecks found in FusionPBX – for example, it avoids FusionPBX’s heavy memory usage from loading thousands of variables into PHP. The Laravel framework also brings optimizations that improve overall stability and speed.',
            icon: BoltIcon,
        },
        {
            name: 'Full FreeSWITCH Power.',
            description: 'Behind the scenes, FS PBX leverages the full power of FreeSWITCH, delivering a robust, multi-platform VoIP core. It supports everything from SIP trunk management and IVRs to conferencing, voicemail, and much more—all with proven scalability and reliability.',
            icon: CpuChipIcon,
        },
        {
            name: 'Extensible & Modular',
            description: 'FS PBX offers premium add-on modules like a Contact Center Dashboard for advanced call queue management and real-time wallboard stats. as well as a STIR/SHAKEN module to sign calls with full Attestation A for caller ID authentication.',
            icon: PuzzlePieceIcon,
        },
        {
            name: 'Secure & Up-to-Date.',
            description: 'By leveraging the Laravel framework (a widely adopted, well-supported platform), FS PBX benefits from regular security updates and best practices. This enhances security compared to a custom PHP codebase.',

            icon: LockClosedIcon,
        },
        {
            name: 'High Availability & Redundancy',
            description: 'FS PBX supports robust deployments with master-to-master (bi-directional) PostgreSQL database replication and file synchronization between servers. This ensures zero downtime, automatic failover, and seamless continuity even if one node goes offline. Perfect for VoIP providers who require maximum reliability and business continuity.',
            icon: ServerStackIcon,
        }

    ]

    const faqs = [
        {
            question: "Modern User Experience",
            answer:
                "FusionPBX’s interface can be convoluted and confusing for end-users and even admins, with an outdated design. FS PBX replaces that with a streamlined, modern GUI that simplifies management for users who just need to make simple changes. The interface is more polished and visually appealing, built for usability from the ground up.",
        },
        {
            question: 'Improved Performance',
            answer:
                'FusionPBX is known to hit performance limits under load, partly due to legacy PHP design (e.g. loading excessive variables into memory). FS PBX’s Laravel backend and optimized codebase eliminate many of these bottlenecks. The developers have introduced real performance enhancements, so the system runs faster and handles high call volumes with greater stability. In practice, FS PBX has been tested with thousands of endpoints and remains rock-solid.',
        },
        {
            question: 'Enhanced Security',
            answer:
                'Security in telecom is paramount. While FusionPBX relies on a custom PHP framework, FS PBX leverages Laravel’s mature ecosystem with built-in hardened security features and regular patches. This means fewer vulnerabilities and a quicker response to any issues (thanks to Laravel’s large community). You get enterprise-grade security without the worry that typically comes with smaller projects.',
        },
        {
            question: 'More Functionality Out-of-the-Box',
            answer:
                "FS PBX extends FusionPBX by adding new features and modules that are not available by default in FusionPBX. For example, FS PBX includes an optional Contact Center module with an elegant live dashboard for call queues, and support for STIR/SHAKEN call authentication standards. These enhancements make FS PBX a more feature-rich solution ready for modern VoIP challenges. It takes the best of FusionPBX and builds from there.",
        },
        {
            question: "Better Multi-Tenancy & White-Label Support",
            answer:
                'Both FusionPBX and FS PBX are multi-tenant, but FS PBX has reimagined how multi-tenant is managed. FS PBX uses one central login for all tenants (no need for separate subdomain per tenant) and still ensures complete separation of domains/tenants internally. Resellers can view and jump between their customer domains easily with FS PBX’s admin group controls – a level of reseller-friendly design FusionPBX lacks. Branding is easier as well, since FS PBX was built with white-label in mind from the start.',
        },
        {
            question: 'Active Development & Community',
            answer:
                "FS PBX is a younger project but is under active development. The goal is to eventually eliminate any legacy FusionPBX code that isn’t needed, achieving a fully standalone platform with no compromise. By contrast, FusionPBX’s development, while active, can be slower to adopt modern frameworks. FS PBX’s openness to community contributions and modern coding standards means it can evolve faster in today’s tech landscape.",
        },
    ]

    const tiers = [
        {
            name: 'Open Source',
            id: 'tier-open-source',
            href: 'https://github.com/nemerald-voip/fspbx',
            priceMonthly: '$0',
            description: "FS PBX is and will always be open source. As part of our commitment to the community, we provide a free support tier available to everyone. Community support is available through forums and social media",
            features: ['Community support', 'Free bug reports on GitHUb', 'Free forever', 'Premuim modules available *'],
            featured: false,
            mostPopular: false,
        },
        {
            name: 'Basic',
            id: 'tier-basic',
            href: '#',
            priceMonthly: '$299',
            description: "This plan is ideal for small providers or businesses that need occasional help with installation, configuration, or troubleshooting. Get direct access to FS PBX experts for guidance and issue resolution.",
            features: ['3 hours of support', 'Phone or email', 'Feature development', 'Premuim modules available *'],
            featured: false,
            mostPopular: false,
        },
        {
            name: 'Standard',
            id: 'tier-standard',
            href: '#',
            priceMonthly: '$599',
            description: 'This plan is perfect for larger deployments or mission-critical systems that require more frequent assistance. It covers help with customizations, advanced configurations, and priority troubleshooting to ensure your PBX runs smoothly.',
            features: [
                '6 hours of support',
                'Priority Phone and Email Queue',
                'Feature development',
                'All premium modules included',
                'Custom integrations',
            ],
            featured: true,
            mostPopular: true,
        },
    ]

    function classNames(...classes) {
        return classes.filter(Boolean).join(' ')
    }

    return (
        <Layout
            title={`${siteConfig.title}`}
            description="FS PBX: World’s Best Open-Source PBX">
            {/* <HomepageHeader /> */}

            <Head>
                <script async src="https://js.stripe.com/v3/pricing-table.js"></script>
            </Head>

            <div id="tw-scope">
                <div className="relative pt-14">
                    <div
                        aria-hidden="true"
                        className="absolute inset-x-0 -top-40 -z-10 transform-gpu overflow-hidden blur-3xl sm:-top-80"
                    >
                        <div
                            style={{
                                clipPath:
                                    'polygon(74.1% 44.1%, 100% 61.6%, 97.5% 26.9%, 85.5% 0.1%, 80.7% 2%, 72.5% 32.5%, 60.2% 62.4%, 52.4% 68.1%, 47.5% 58.3%, 45.2% 34.5%, 27.5% 76.7%, 0.1% 64.9%, 17.9% 100%, 27.6% 76.8%, 76.1% 97.7%, 74.1% 44.1%)',
                            }}
                            className="relative left-[calc(50%-11rem)] aspect-[1155/678] w-[36.125rem] -translate-x-1/2 rotate-[30deg] bg-gradient-to-tr from-[#ff80b5] to-[#9089fc] opacity-30 sm:left-[calc(50%-30rem)] sm:w-[72.1875rem]"
                        />
                    </div>
                    <div className="py-16 sm:py-24 lg:pb-40">
                        <div className="mx-auto max-w-7xl px-6 lg:px-8">
                            <div className="mx-auto max-w-2xl text-center">
                                <h1 className="text-balance !text-5xl font-semibold tracking-tight text-gray-900 sm:text-7xl">
                                    FS PBX: World’s Best Open-Source PBX
                                </h1>
                                <p className="mt-8 text-pretty text-lg font-medium text-gray-500 sm:text-xl/8">
                                    FS PBX is an advanced open-source PBX platform built on the FreeSWITCH telephony engine. It started as a fork of FusionPBX but has been extensively redesigned to deliver a more modern, powerful, and user-friendly experience
                                </p>
                                <div className="mt-10 flex items-center justify-center gap-x-6">
                                    <a
                                        href="https://github.com/nemerald-voip/fspbx"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        className="rounded-md bg-indigo-600 px-3.5 py-2.5 text-sm font-semibold !text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600"
                                    >
                                        Download
                                    </a>
                                    <a href="#" className="text-sm/6 font-semibold text-gray-900">
                                        Learn more <span aria-hidden="true">→</span>
                                    </a>
                                </div>
                            </div>
                            <div className="mt-16 flow-root sm:mt-24">
                                <div className="-m-2 rounded-xl bg-gray-900/5 p-2 ring-1 ring-inset ring-gray-900/10 lg:-m-4 lg:rounded-2xl lg:p-4">

                                    <Carousel
                                        showThumbs={false}
                                        showStatus={false}
                                        infiniteLoop
                                        autoPlay
                                        interval={5000}
                                        className="rounded-md shadow-2xl ring-1 ring-gray-900/10"
                                    >

                                        <div>
                                            <img src="/img/screenshots/dashboard.png" alt="Dashboard screenshot" />
                                        </div>
                                        <div>
                                            <img src="/img/screenshots/extensions.png" alt="Extensions screenshot" />
                                        </div>
                                        <div>
                                            <img src="/img/screenshots/extension-edit.png" alt="Extension Edit Screenshot" />
                                        </div>
                                        <div>
                                            <img src="/img/screenshots/extension-forward.png" alt="Extension Forward Screenshot" />
                                        </div>
                                        <div>
                                            <img src="/img/screenshots/extension-voicemail.png" alt="Extension Voicemail Screenshot" />
                                        </div>
                                        <div>
                                            <img src="/img/screenshots/extension-device.png" alt="Extension Voicemail Screenshot" />
                                        </div>
                                        <div>
                                            <img src="/img/screenshots/extension-mobile-app.png" alt="Extension Voicemail Screenshot" />
                                        </div>
                                        <div>
                                            <img src="/img/screenshots/ring-groups.png" alt="Ring Groups Screenshot" />
                                        </div>
                                        <div>
                                            <img src="/img/screenshots/ring-group-edit.png" alt="Ring Group Edit Screenshot" />
                                        </div>
                                        <div>
                                            <img src="/img/screenshots/ring-group-members.png" alt="Ring Group Members Screenshot" />
                                        </div>
                                        <div>
                                            <img src="/img/screenshots/phone-numbers-edit.png" alt="Phone Numbers Edit Screenshot" />
                                        </div>
                                        <div>
                                            <img src="/img/screenshots/business-hours-edit.png" alt="Business Hours Edit Screenshot" />
                                        </div>
                                        <div>
                                            <img src="/img/screenshots/contact-center.png" alt="Contact Center Dashboard Screenshot" />
                                        </div>
                                    </Carousel>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div
                        aria-hidden="true"
                        className="absolute inset-x-0 top-[calc(100%-13rem)] -z-10 transform-gpu overflow-hidden blur-3xl sm:top-[calc(100%-30rem)]"
                    >
                        <div
                            style={{
                                clipPath:
                                    'polygon(74.1% 44.1%, 100% 61.6%, 97.5% 26.9%, 85.5% 0.1%, 80.7% 2%, 72.5% 32.5%, 60.2% 62.4%, 52.4% 68.1%, 47.5% 58.3%, 45.2% 34.5%, 27.5% 76.7%, 0.1% 64.9%, 17.9% 100%, 27.6% 76.8%, 76.1% 97.7%, 74.1% 44.1%)',
                            }}
                            className="relative left-[calc(50%+3rem)] aspect-[1155/678] w-[36.125rem] -translate-x-1/2 bg-gradient-to-tr from-[#ff80b5] to-[#9089fc] opacity-30 sm:left-[calc(50%+36rem)] sm:w-[72.1875rem]"
                        />
                    </div>
                </div>
                <main>
                    {/* <HomepageFeatures /> */}

                    <div className="mx-auto mt-8 max-w-7xl px-6 sm:mt-20 md:mt-16 lg:px-8">
                        <dl className="mx-auto grid max-w-2xl grid-cols-1 gap-x-6 gap-y-10 text-base/7 text-gray-600 dark:text-gray-300 sm:grid-cols-2 lg:mx-0 lg:max-w-none lg:grid-cols-3 lg:gap-x-8 lg:gap-y-16">
                            {features.map((feature) => (
                                <div
                                    key={feature.name}
                                    className="relative pl-9 bg-white dark:bg-gray-900/80 rounded-xl p-4 shadow-sm transition-colors"
                                >
                                    <dt className="inline font-semibold text-gray-900 dark:text-white">
                                        <feature.icon aria-hidden="true" className="absolute left-1 top-1 size-5 text-indigo-600 dark:text-indigo-400" />
                                        {feature.name}
                                    </dt>{' '}
                                    <dd className="inline">{feature.description}</dd>
                                </div>
                            ))}
                        </dl>
                    </div>

                    {/* Alternating Feature Sections */}
                    <div className="relative overflow-hidden pb-16 pt-24">
                        <div className="mx-auto max-w-7xl px-6 lg:px-8 mb-16">
                            <div className="mx-auto max-w-2xl sm:text-center">
                                <h2 className="text-base/7 font-semibold !text-indigo-600 dark:!text-indigo-400">
                                    Everything you need
                                </h2>
                                <p className="mt-2 text-pretty text-4xl font-semibold tracking-tight text-gray-900 dark:text-white sm:text-balance sm:text-5xl">
                                    Built for VoIP Providers.
                                </p>
                                <p className="mt-6 text-lg/8 text-gray-600 dark:text-gray-300">
                                    White Label Ready
                                </p>
                            </div>
                        </div>

                        <div aria-hidden="true" className="absolute inset-x-0 top-0 h-48 " />
                        <div className="relative">
                            <div className="lg:mx-auto lg:grid lg:max-w-7xl lg:grid-flow-col-dense lg:grid-cols-2 lg:gap-24 lg:px-8">
                                <div className="mx-auto max-w-xl px-6 lg:mx-0 lg:max-w-none lg:px-0 lg:py-16">
                                    <div>
                                        {/* <div>
                                        <span className="flex size-12 items-center justify-center rounded-md bg-gradient-to-r from-purple-600 to-indigo-600">
                                            <InboxIcon aria-hidden="true" className="size-6 text-white" />
                                        </span>
                                    </div> */}
                                        <div className="mt-6">
                                            <h2 className="text-3xl font-bold tracking-tight text-gray-900 dark:text-white">Designed with simplicity in mind</h2>
                                            <p className="mt-4 text-lg text-gray-500 dark:text-gray-300">
                                                FS PBX is designed with VoIP service providers in mind, particularly those needing a white-label PBX solution for their customers. It supports full multi-tenancy, allowing you to host multiple client
                                                PBX instances on a single system – each client (tenant) is completely separated in the database and dial-plan.
                                                Unlike FusionPBX which requires separate domain URLs or awkward username formats for each tenant, FS PBX uses a unified login portal for all tenants.
                                                Users simply log in with their email, and FS PBX intelligently routes them to their correct tenant space
                                            </p>
                                            <div className="mt-6">
                                                <a
                                                    href="https://github.com/nemerald-voip/fspbx"
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    className="rounded-md bg-indigo-600 px-3.5 py-2.5 text-sm font-semibold !text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600"
                                                >
                                                    Get started
                                                </a>
                                            </div>
                                        </div>
                                    </div>

                                </div>
                                <div className="mt-12 sm:mt-16 lg:mt-0">
                                    <div className="-mr-48 pl-6 md:-mr-16 lg:relative lg:m-0 lg:h-full lg:px-0">
                                        <img
                                            alt="Login Page"
                                            src="/img/screenshots/login-page.png"
                                            className="w-full rounded-xl shadow-xl ring-1 ring-black/5 lg:absolute lg:left-0 lg:h-full lg:w-auto lg:max-w-none"
                                        />
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div className="mt-24">
                            <div className="lg:mx-auto lg:grid lg:max-w-7xl lg:grid-flow-col-dense lg:grid-cols-2 lg:gap-24 lg:px-8">
                                <div className="mx-auto max-w-xl px-6 lg:col-start-2 lg:mx-0 lg:max-w-none lg:px-0 lg:py-32">
                                    <div>

                                        <div className="mt-6">
                                            <h2 className="text-3xl font-bold tracking-tight text-gray-900 dark:text-white">
                                                Reseller access
                                            </h2>
                                            <p className="mt-4 text-lg text-gray-500 dark:text-gray-300">
                                                For resellers, FS PBX provides a multi-site admin console: admins can effortlessly switch between different customer domains through a redesigned domain selector.
                                                You can grant resellers access to manage their client accounts all from one master dashboard – no re-login to different URLs required. This architecture makes maintenance easier for providers and gives end-users a smoother experience
                                                (for example, password resets are simpler since users just know their email)
                                            </p>
                                            <div className="mt-6">
                                                <a
                                                    href="https://github.com/nemerald-voip/fspbx"
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    className="rounded-md bg-indigo-600 px-3.5 py-2.5 text-sm font-semibold !text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600"
                                                >
                                                    Get started
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div className="mt-12 sm:mt-16 lg:col-start-1 lg:mt-0">
                                    <div className="-ml-48 pr-6 md:-ml-16 lg:relative lg:m-0 lg:h-full lg:px-0">
                                        <img
                                            alt="Customer profile user interface"
                                            src="/img/screenshots/domain-selector.png"
                                            className="w-full rounded-xl shadow-xl ring-1 ring-black/5 lg:absolute lg:right-0 lg:h-full lg:w-auto lg:max-w-none"
                                        />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>


                    {/* FAQ section */}
                    <div className="mx-auto mt-16 max-w-7xl px-6 sm:mt-24 lg:px-8">
                        <div className="mx-auto max-w-4xl">
                            <h2 className="!text-4xl font-semibold tracking-tight text-gray-900 dark:text-white sm:text-5xl">
                                FS PBX vs FusionPBX – Why Upgrade?
                            </h2>
                            <p className="mt-4 text-gray-700 dark:text-gray-300">
                                FS PBX began as a FusionPBX fork, but it has rapidly evolved into a superior platform. Here are some key reasons why FS PBX outperforms FusionPBX:
                            </p>
                            <dl className="mt-16 divide-y divide-gray-900/10 dark:divide-gray-700 mb-10">
                                {faqs.map((faq) => (
                                    <Disclosure key={faq.question} as="div" className="py-6 first:pt-0 last:pb-0">
                                        <dt>
                                            <DisclosureButton className="group flex w-full items-start justify-between text-left text-gray-900 dark:text-white">
                                                <span className="text-base/7 font-semibold">{faq.question}</span>
                                                <span className="ml-6 flex h-7 items-center">
                                                    <PlusIcon aria-hidden="true" className="size-6 group-data-[open]:hidden" />
                                                    <MinusIcon aria-hidden="true" className="size-6 group-[:not([data-open])]:hidden" />
                                                </span>
                                            </DisclosureButton>
                                        </dt>
                                        <DisclosurePanel as="dd" className="mt-2 pr-12">
                                            <p className="text-base/7 text-gray-600 dark:text-gray-300">{faq.answer}</p>
                                        </DisclosurePanel>
                                    </Disclosure>
                                ))}
                            </dl>
                            <p className="text-gray-700 dark:text-gray-300">
                                In summary, if you’re currently using FusionPBX or evaluating open-source PBX systems, FS PBX offers a compelling upgrade: you keep the reliability of FreeSWITCH and the familiarity of FusionPBX’s features, but gain a faster, easier, and more powerful system. As one industry expert noted, “Everyone needs to take a careful look at this platform... The interface is downright magical.”
                            </p>
                        </div>
                    </div>



                    <div className="py-24 sm:pt-48">
                        <div className="mx-auto max-w-7xl px-6 lg:px-8">
                            <div className="mx-auto max-w-2xl lg:text-center">
                                <p className="mt-2 text-pretty text-4xl font-semibold tracking-tight text-gray-900 dark:text-white sm:text-5xl lg:text-balance">
                                    Professional Support Plans
                                </p>
                                <p className="mt-6 text-pretty text-lg/8 text-gray-600 dark:text-gray-300">
                                    While FS PBX is free to download and use, professional support is available for organizations that want expert help or guaranteed assistance. The maintainers of FS PBX offer paid support options to help you deploy and maintain your PBX with confidence.
                                </p>
                            </div>

                            <div className="isolate mx-auto mt-16 grid max-w-md grid-cols-1 gap-y-8 sm:mt-20 lg:mx-0 lg:max-w-none lg:grid-cols-3">
                                {tiers.map((tier, tierIdx) => (
                                    <div
                                        key={tier.id}
                                        className={classNames(
                                            tier.mostPopular ? 'lg:z-10 rounded-b-none rounded-br-3xl' : 'lg:mt-8',
                                            tierIdx === 0 ? 'lg:rounded-r-none' : '',
                                            tierIdx === 1 ? 'lg:rounded-none' : '',
                                            // Dark mode tweaks:
                                            'flex flex-col justify-between rounded-3xl bg-white dark:bg-gray-900/80 p-8 ring-1 ring-gray-200 dark:ring-gray-700 xl:p-10 transition-colors'
                                        )}
                                    >
                                        <div>
                                            <div className="flex items-center justify-between gap-x-4">
                                                <h3
                                                    id={tier.id}
                                                    className={classNames(
                                                        tier.mostPopular
                                                            ? '!text-indigo-600 dark:!text-indigo-400'
                                                            : '!text-gray-900 dark:!text-white',
                                                        'text-lg/8 font-semibold'
                                                    )}
                                                >
                                                    {tier.name}
                                                </h3>
                                                {tier.mostPopular ? (
                                                    <p className="rounded-full !bg-indigo-600/10 dark:!bg-indigo-400/20 px-2.5 py-1 text-xs/5 font-semibold text-indigo-600 dark:text-indigo-200">
                                                        Most popular
                                                    </p>
                                                ) : null}
                                            </div>
                                            <p className="mt-4 text-sm/6 text-gray-600 dark:text-gray-300">{tier.description}</p>
                                            <p className="mt-6 flex items-baseline gap-x-1">
                                                <span className="text-4xl font-semibold tracking-tight text-gray-900 dark:text-white">{tier.priceMonthly}</span>
                                                <span className="text-sm/6 font-semibold text-gray-600 dark:text-gray-400">/month</span>
                                            </p>
                                            <ul role="list" className="mt-8 space-y-3 text-sm/6 text-gray-600 dark:text-gray-300">
                                                {tier.features.map((feature) => (
                                                    <li key={feature} className="flex gap-x-3">
                                                        <CheckIcon aria-hidden="true" className="h-6 w-5 flex-none text-indigo-600 dark:text-indigo-400" />
                                                        {feature}
                                                    </li>
                                                ))}
                                            </ul>
                                        </div>
                                        {tier.id === 'tier-open-source' ? (
                                            <a
                                                href={tier.href}
                                                aria-describedby={tier.id}
                                                className={classNames(
                                                    tier.mostPopular
                                                        ? 'bg-indigo-600 !text-white shadow-sm hover:bg-indigo-500 dark:bg-indigo-500 dark:hover:bg-indigo-400'
                                                        : 'text-indigo-600 dark:text-indigo-400 ring-1 ring-inset ring-indigo-200 dark:ring-indigo-400 hover:ring-indigo-300 dark:hover:ring-indigo-200',
                                                    'mt-8 block rounded-md px-3 py-2 text-center text-sm/6 font-semibold focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600'
                                                )}
                                                target="_blank"
                                                rel="noopener noreferrer"
                                            >
                                                Download
                                            </a>
                                        ) : (
                                            <button
                                                type="button"
                                                aria-describedby={tier.id}
                                                onClick={() => setModalOpen(true)}
                                                className={classNames(
                                                    tier.mostPopular
                                                        ? 'bg-indigo-600 !text-white shadow-sm hover:bg-indigo-500 dark:bg-indigo-500 dark:hover:bg-indigo-400'
                                                        : 'text-indigo-600 dark:text-indigo-400 ring-1 ring-inset ring-indigo-200 dark:ring-indigo-400 hover:ring-indigo-300 dark:hover:ring-indigo-200',
                                                    'mt-8 block rounded-md px-3 py-2 text-center text-sm/6 font-semibold focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600',
                                                    'cursor-pointer hover:underline'
                                                )}
                                            >
                                                Get Started
                                            </button>
                                        )}

                                    </div>
                                ))}
                            </div>


                            <div className="mt-2"><p className="text-sm text-gray-600"> * Requires a subscription</p></div>

                            <div className="mt-16 flex flex-col items-start gap-x-8 gap-y-6 rounded-3xl p-8 ring-1 ring-gray-900/10 dark:ring-gray-700 bg-white dark:bg-gray-900/80 sm:gap-y-10 sm:p-10 lg:col-span-2 lg:flex-row lg:items-center transition-colors">
                                <div className="lg:min-w-0 lg:flex-1">
                                    <h3 className="text-base/7 font-semibold text-indigo-600 dark:text-indigo-400">
                                        Contact Center Module (Available as an Add-on)
                                    </h3>
                                    <p className="mt-1 text-base/7 text-gray-600 dark:text-gray-300">
                                        Supercharge your FS PBX deployment with our premium Contact Center module—available for just $99/month.
                                    </p>
                                </div>

                                <button
                                    type="button"
                                    onClick={() =>
                                        openStripePricingModal({
                                            title: 'Contact Center Module',
                                            description:
                                                'Start your 15-day free trial. Your card is securely collected by Stripe and billing begins automatically after the trial ends.',
                                            pricingTableId: 'prctbl_1T4cMKFbm6VTWy92vs6KK9QF',
                                        })
                                    }
                                    className="rounded-md px-3.5 py-2 text-sm/6 font-semibold text-indigo-600 dark:text-indigo-400 ring-1 ring-inset ring-indigo-200 dark:ring-indigo-400 hover:ring-indigo-300 dark:hover:ring-indigo-200 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 hover:underline cursor-pointer"
                                >
                                    Request trial <span aria-hidden="true">&rarr;</span>
                                </button>
                            </div>

                            <div className="mt-16 flex flex-col items-start gap-x-8 gap-y-6 rounded-3xl p-8 ring-1 ring-gray-900/10 dark:ring-gray-700 bg-white dark:bg-gray-900/80 sm:gap-y-10 sm:p-10 lg:col-span-2 lg:flex-row lg:items-center transition-colors">
                                <div className="lg:min-w-0 lg:flex-1">
                                    <h3 className="text-base/7 font-semibold text-indigo-600 dark:text-indigo-400">
                                        STIR/SHAKEN Module (Available as an Add-on)
                                    </h3>
                                    <p className="mt-1 text-base/7 text-gray-600 dark:text-gray-300">
                                        Enhance the security and credibility of your outbound calls with the FS PBX STIR/SHAKEN module, available as an add-on for just $99/month.
                                        The STIR/SHAKEN module enables full caller ID authentication and call signing, helping you comply with industry regulations and combat caller ID spoofing. Calls are signed with Attestation A, giving recipients confidence that calls from your network are legitimate and trustworthy.
                                    </p>
                                </div>
                                <button
                                    type="button"
                                    onClick={() =>
                                        openStripePricingModal({
                                            title: 'STIR/SHAKEN Module',
                                            description:
                                                'Start your 15-day free trial for the STIR/SHAKEN module. Your card is securely collected by Stripe and billing begins automatically after the trial ends.',
                                            pricingTableId: 'prctbl_1T4czQFbm6VTWy92mf1RdFkH',
                                        })
                                    }
                                    className="rounded-md px-3.5 py-2 text-sm/6 font-semibold text-indigo-600 dark:text-indigo-400 ring-1 ring-inset ring-indigo-200 dark:ring-indigo-400 hover:ring-indigo-300 dark:hover:ring-indigo-200 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 hover:underline cursor-pointer"
                                >
                                    Request trial <span aria-hidden="true">&rarr;</span>
                                </button>
                            </div>

                        </div>
                    </div>


                    <div className="bg-white dark:bg-gray-900/80 py-16 sm:py-24 transition-colors">
                        <div className="mx-auto max-w-7xl px-6 lg:px-8">
                            <div className="mx-auto max-w-2xl lg:mx-0 lg:max-w-none">
                                <h1 className="mt-2 text-pretty text-4xl font-semibold tracking-tight text-gray-900 dark:text-white sm:text-5xl">
                                    Open-Source Community and Resources
                                </h1>
                                <div className="mt-10 grid max-w-xl grid-cols-1 gap-8 text-base/7 text-gray-700 dark:text-gray-300 lg:max-w-none lg:grid-cols-2">
                                    <div>
                                        <p>
                                            FS PBX is not just a product, it’s a community-driven open-source project. Licensed under the Apache 2.0 License,
                                            it is free to use, modify, and distribute. We welcome contributions from developers and VoIP enthusiasts around the world.
                                            If you want to report issues or contribute code, visit our GitHub repository and join the discussion.
                                            Every bit of feedback and contribution helps make FS PBX better!
                                        </p>
                                        <p className="mt-8">
                                            By choosing FS PBX, you’re not only getting a superior PBX platform, you’re also joining a community that believes in open source and collaboration. We are committed to transparency and innovation.
                                            Our goal is to make FS PBX the #1 choice for open-source PBX for businesses of all sizes – and with your support, we’re well on our way!
                                        </p>
                                    </div>
                                    <div>
                                        <p>
                                            Community support is available through forums and social media:
                                        </p>
                                        <p className="mt-8">
                                            <span className="font-bold">Discussion Forums:</span> Join the conversation on forums like PBXForums and VoIP-Info, where FS PBX developers and users actively discuss features, share tips, and help each other.
                                            Many early adopters have shared success stories (such as deploying FS PBX for thousands of endpoints with great results)
                                        </p>
                                        <p className="mt-8">
                                            <span className="font-bold">YouTube Tutorials:</span> As mentioned, a growing library of video tutorials is available, which is great for visual learners.
                                        </p>
                                    </div>
                                </div>
                                <div className="mt-10 flex">
                                    <a
                                        href="https://github.com/nemerald-voip/fspbx"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        className="rounded-md bg-indigo-600 px-3.5 py-2.5 text-sm font-semibold !text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600"
                                    >
                                        Download
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <Modal open={modalOpen} onClose={() => setModalOpen(false)}>
                        <ContactForm
                            workerEndpoint="https://fspbx-contact-form.dexter-2ef.workers.dev"
                            turnstileSiteKey="0x4AAAAAABnG91y8BuU-YHNz"
                        />
                    </Modal>

                    <Modal open={stripeModal.open} onClose={() => setStripeModal((prev) => ({ ...prev, open: false }))}>
                        <div className="w-full max-w-4xl">
                            <div className="mb-4">
                                <h3 className="text-xl font-semibold text-gray-900 dark:text-white">
                                    {stripeModal.title}
                                </h3>
                                <p className="mt-1 text-sm text-gray-600 dark:text-gray-300">
                                    {stripeModal.description}
                                </p>
                            </div>

                            {stripeModal.open && stripeModal.pricingTableId ? (
                                <stripe-pricing-table
                                    pricing-table-id={stripeModal.pricingTableId}
                                    publishable-key="pk_live_51Q8bWOFbm6VTWy92Dq9vPUveCfv06Xk4eAxG3yMhIjasaN10VZKsf4EnTVYbgqaMYxARPEUWcYBi6shvMTtJmRSs00Hk7qgOgN"
                                ></stripe-pricing-table>
                            ) : null}
                        </div>
                    </Modal>
                </main >
            </div>
        </Layout >
    );
}
