# Contributing to FS PBX

Thank you for your interest in contributing to FS PBX.

FS PBX is an open-source PBX management platform built around FreeSWITCH, Laravel, Vue, Inertia, Tailwind CSS, and PostgreSQL. Contributions that improve reliability, usability, documentation, translations, testing, and PBX functionality are welcome.

## Ways to Contribute

You can help by:

- Reporting reproducible bugs
- Fixing bugs
- Improving documentation
- Adding or improving translations
- Testing new releases and upgrade paths
- Improving the user interface
- Improving FreeSWITCH and telephony integrations
- Proposing new features
- Submitting pull requests

For larger features or architectural changes, please open an issue first so the approach can be discussed before significant development work begins.

## Before Opening an Issue

Please search the existing issues first to avoid duplicates:

https://github.com/nemerald-voip/fspbx/issues

For bug reports, include as much of the following as possible:

- FS PBX version or commit
- Debian version
- FreeSWITCH version, when relevant
- Browser and version for UI issues
- Clear steps to reproduce the problem
- Expected behavior
- Actual behavior
- Relevant application, FreeSWITCH, browser-console, or system logs
- Screenshots when they help explain the issue

### Protect Sensitive Information

FS PBX is commonly used in production telephony environments. Before posting logs, configuration files, screenshots, SIP traces, packet captures, or database output, remove sensitive information such as:

- SIP passwords and authentication credentials
- API keys and access tokens
- Database credentials
- Private keys and certificates
- Provisioning credentials
- Personally identifiable customer information
- Information you are not authorized to publish

Phone numbers, IP addresses, domain names, call IDs, and SIP headers can also contain customer or infrastructure information. Redact them when appropriate.

## Development Environment

The production installation currently targets:

- Debian 12 or Debian 13
- PHP 8.1 or newer as permitted by the project's Composer dependencies
- PostgreSQL
- Node.js / npm for frontend development
- FreeSWITCH for telephony integration

A complete PBX development environment may require FreeSWITCH and supporting system services. Small frontend, documentation, translation, and isolated application changes may not require a full telephony stack.

## Getting the Source

Fork the repository on GitHub and clone your fork:

```bash
git clone https://github.com/YOUR-USERNAME/fspbx.git
cd fspbx
```

Add the upstream repository:

```bash
git remote add upstream https://github.com/nemerald-voip/fspbx.git
```

Before starting new work, update your local `main` branch:

```bash
git checkout main
git fetch upstream
git pull --ff-only upstream main
```

Create a focused branch for your change:

```bash
git checkout -b fix/short-description
```

Use a descriptive branch name such as:

- `fix/voicemail-filter`
- `feature/ring-group-option`
- `docs/install-guide`
- `i18n/update-spanish`

## Backend Setup

Install PHP dependencies:

```bash
composer install
```

For a full local application setup, configure the environment for your development system and follow the same application requirements used by FS PBX. Do not commit your local `.env` file or credentials.

When your change modifies database structure, create a migration instead of making undocumented manual database changes.

## Frontend Setup

Install frontend dependencies:

```bash
npm install
```

Run the Vite development server:

```bash
npm run dev
```

Build production assets:

```bash
npm run build
```

## Testing

Run the PHP test suite when your change affects application behavior:

```bash
php artisan test
```

You can also run PHPUnit directly:

```bash
./vendor/bin/phpunit
```

For translation and Vue i18n changes, run:

```bash
npm run test:i18n
```

For frontend changes, verify that the production build completes successfully:

```bash
npm run build
```

Not every FreeSWITCH or SIP behavior can be covered by unit tests. When a change affects call handling, routing, registration, media, provisioning, queues, voicemail, or other telephony behavior, describe the manual test scenario in the pull request.

Useful telephony test details include:

- Call direction
- Relevant extension or destination type
- Expected SIP behavior
- Expected FreeSWITCH behavior
- Result before the change
- Result after the change

## Code Guidelines

FS PBX contains both newer Laravel/Vue code and functionality inherited from its FusionPBX history. When making changes:

- Follow the conventions used by the surrounding code
- Keep changes focused on the problem being solved
- Avoid unrelated formatting or refactoring in the same pull request
- Prefer readable code over clever code
- Reuse existing services, helpers, components, and patterns where practical
- Validate user input
- Preserve multi-tenant/domain isolation
- Avoid introducing tenant-specific assumptions into shared code
- Never hard-code credentials, customer data, domains, IP addresses, or production-specific values
- Add comments where telephony behavior or non-obvious logic needs explanation

### Database Changes

For schema changes:

- Use Laravel migrations
- Consider upgrades from existing FS PBX installations
- Avoid destructive changes unless they are necessary and clearly documented
- Preserve existing data whenever possible

### Telephony Changes

Changes involving FreeSWITCH, Sofia/SIP, dialplans, event socket behavior, provisioning, media, or routing can have system-wide effects.

Please consider:

- Inbound and outbound call behavior
- Multi-tenant isolation
- Existing extensions and devices
- NAT scenarios
- SIP transports where applicable
- Call forwarding and voicemail behavior
- Failure and timeout paths
- Upgrade compatibility

Do not include real customer credentials or production secrets in fixtures, examples, or tests.

## Translations

FS PBX translations are stored in:

```text
resources/lang/{locale}.json
```

Use `resources/lang/en-us.json` as the source for translation keys.

When contributing a translation:

- Translate values, not the source keys
- Keep placeholders and variables intact
- Preserve valid JSON formatting
- Avoid changing unrelated languages in the same pull request
- Run `npm run test:i18n` before submitting

Translation documentation is available at:

https://www.fspbx.com/docs/additional-information/translations/

## Documentation

Documentation improvements are welcome.

When documenting commands or configuration:

- Verify commands before submitting them
- Clearly distinguish required and optional steps
- Avoid examples containing real credentials
- Prefer copy-and-paste-safe examples
- Note version-specific behavior when relevant

## Pull Requests

Before opening a pull request:

1. Update your branch from the latest upstream `main`.
2. Review your own diff for accidental changes or sensitive information.
3. Run the tests relevant to your change.
4. Confirm frontend assets build successfully when frontend code changed.
5. Test affected PBX behavior when telephony functionality changed.

A good pull request should:

- Have a clear title
- Explain the problem being solved
- Describe the implementation
- Include testing performed
- Include screenshots for meaningful UI changes
- Reference related issues where applicable
- Avoid bundling unrelated changes

Small, focused pull requests are generally easier to review and merge.

## Commit Messages

Use short, descriptive commit messages.

Examples:

```text
fix: preserve ring group member timeout
feat: add voicemail status filter
docs: clarify Debian installation requirements
i18n: update French translations
```

Perfect commit history is less important than making the intent of the change easy to understand.

## Security Issues

Please do not publish an exploitable security vulnerability, authentication bypass, exposed credential, or similar security issue in a public GitHub issue before maintainers have had an opportunity to address it.

If GitHub provides the option to privately report a security vulnerability for this repository, use that mechanism. Otherwise, contact the FS PBX maintainers through the support/contact options at:

https://www.fspbx.com/

Include enough information to reproduce and assess the issue, but do not include unrelated customer or production data.

## License

By contributing to FS PBX, you agree that your contribution may be distributed under the repository's Apache License 2.0.

## Thank You

FS PBX improves through real-world use, testing, bug reports, translations, documentation, and code contributions.

Thank you for helping make the project better.
