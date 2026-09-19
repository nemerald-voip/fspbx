# FS PBX

**Modern open-source PBX software built on FreeSWITCH, Laravel, and Vue.**

FS PBX is a redesigned PBX management platform for teams that want the flexibility of FreeSWITCH with a modern web interface and maintainable application stack. It began as a fork of FusionPBX and has since been extensively redeveloped around Laravel, Vue, Tailwind CSS, and a modular architecture.

If you are evaluating **FreePBX, FusionPBX, 3CX, Asterisk-based platforms, or other VoIP/PBX systems**, FS PBX is an open-source option worth exploring.

> If FS PBX is useful to you, please consider giving the project a ⭐ on GitHub. It helps more people discover the project.

## Links

- **Website:** https://www.fspbx.com/
- **Documentation:** https://www.fspbx.com/docs/
- **Issues / Bug reports:** https://github.com/nemerald-voip/fspbx/issues
- **GitHub repository:** https://github.com/nemerald-voip/fspbx

## Why FS PBX?

FS PBX combines FreeSWITCH with a modern application stack designed to make day-to-day PBX administration easier to use, extend, and maintain.

### Core platform

- **FreeSWITCH-based telephony**
- **Laravel backend**
- **Vue.js frontend**
- **Tailwind CSS**
- **PostgreSQL**
- **FusionPBX interoperability** for functionality that has not yet been replaced
- **Modular architecture** designed for continued development and extension

### PBX and communications features

FS PBX includes management interfaces and workflows for common PBX functions such as:

- Extensions and devices
- Call detail records
- Voicemail
- Ring groups
- Virtual receptionists / IVRs
- Time-based routing
- Contact center functionality
- Fax
- Firewall management
- Provisioning and device management
- Multi-language UI support

The project continues to evolve as more FusionPBX-era functionality is redesigned and moved into the FS PBX application.

## Screenshots

<img width="1780" height="940" alt="FS PBX dashboard" src="https://github.com/user-attachments/assets/55df5cfd-8884-4eab-82a5-e37ed08482c8" />

<img width="1743" height="788" alt="FS PBX interface" src="https://github.com/user-attachments/assets/58fccf2e-fa37-48cd-8fba-95638cdce509" />

<img width="1799" height="949" alt="FS PBX interface" src="https://github.com/user-attachments/assets/6b386d8a-8d03-49ea-bb13-13aa7ed70bba" />

<img width="1803" height="948" alt="FS PBX interface" src="https://github.com/user-attachments/assets/0033182b-cefe-4ed3-8202-78f1d93c16f6" />

<img width="1754" height="813" alt="FS PBX interface" src="https://github.com/user-attachments/assets/be79b843-44c6-4e8f-bacb-1457c103d24d" />

<img width="1793" height="947" alt="FS PBX interface" src="https://github.com/user-attachments/assets/a354035a-6a90-4c6d-a32a-bc0ff301f416" />

![FS PBX interface](https://github.com/user-attachments/assets/507dfa97-0264-480d-a186-767f1bcf7da8)

![FS PBX interface](https://github.com/user-attachments/assets/c1dcb6da-1a17-44b5-8bc3-cde49faeca07)

<img width="2417" alt="FS PBX interface" src="https://github.com/user-attachments/assets/5c885878-053c-4e4d-800f-7ad4d919894d">

## Installation

### Requirements

Before installing FS PBX, make sure the server meets these minimum requirements:

- **Debian 12 or Debian 13**
- **4 GB RAM**
- **30 GB disk space**

For production systems, additional RAM and NVMe storage are recommended based on workload, retention requirements, and call volume.

### Install FS PBX

Run the installation script:

```bash
wget -O- https://raw.githubusercontent.com/nemerald-voip/fspbx/main/install/install-fspbx.sh | bash
```

After installation, open the server in your browser and complete the configuration.

### 10-minute installation walkthrough

[Watch the FS PBX installation video on YouTube](https://www.youtube.com/watch?v=go6dUce0Nis)

[![FS PBX installation walkthrough](https://img.youtube.com/vi/go6dUce0Nis/0.jpg)](https://www.youtube.com/watch?v=go6dUce0Nis)

### Configure HTTPS

A custom domain and Let's Encrypt certificate are optional but recommended for production deployments.

See:

[How to Secure FS PBX with a Let's Encrypt SSL Certificate](https://github.com/nemerald-voip/fspbx/wiki/How-to-Secure-FS-PBX-with-a-Let%E2%80%99s-Encrypt-SSL-Certificate)

### Troubleshooting Error 419

If you receive a **419 Page Expired** response, see:

[Troubleshooting Error 419 (Page Expired)](https://github.com/nemerald-voip/fspbx/wiki/Troubleshooting-Error-419-(Page-Expired))

## Updating

From the FS PBX installation directory:

```bash
cd /var/www/fspbx
git pull
php artisan app:update
```

Check for pending database migrations:

```bash
php artisan migrate:status
```

If migrations are pending:

```bash
php artisan migrate
```

## Premium Modules

FS PBX also offers optional premium modules for organizations that need additional functionality.

### Contact Center

The Contact Center module adds tools for managing contact-center operations, including live visibility and queue-management workflows.

### STIR/SHAKEN

The STIR/SHAKEN module provides functionality for signing outbound calls using your own certificate and attestation configuration.

For current module information, visit the [FS PBX website](https://www.fspbx.com/).

## Translations

FS PBX translations are maintained by the community through GitHub.

Translation files live in:

```text
resources/lang/{locale}.json
```

Use `resources/lang/en-us.json` as the source language and edit the translated values for your locale.

Regional variants such as Spanish and Spanish (Mexico) can inherit from the base language, so only regional differences need to be translated separately.

See the [Translations guide](https://www.fspbx.com/docs/additional-information/translations/) for details.

## Contributing

Contributions are welcome.

Useful ways to help include:

- Reporting reproducible bugs
- Improving documentation
- Submitting translations
- Testing new releases
- Proposing enhancements
- Opening pull requests

Before starting a larger change, consider opening an issue first so the implementation can be discussed.

[View open issues](https://github.com/nemerald-voip/fspbx/issues)

## Support the Project

If FS PBX saves you time or helps you run your communications infrastructure:

- ⭐ **Star the repository** so more people can discover it
- 🍴 Fork it and experiment
- 🐛 Report issues with reproducible details
- 🌎 Contribute translations
- 📣 Share FS PBX with other FreeSWITCH, VoIP, telecom, and MSP professionals

Every contribution helps the project grow.

## License

FS PBX is licensed under the **Apache License 2.0**.

See the repository license for the full terms.

## Contact

For product information, documentation, support, and updates, visit:

**https://www.fspbx.com/**
