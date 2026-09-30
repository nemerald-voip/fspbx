# Website

This website is built using [Docusaurus](https://docusaurus.io/), a modern static website generator.

## Installation

```bash
yarn
```

## Local Development

```bash
yarn start
```

This command starts a local development server and opens up a browser window. Most changes are reflected live without having to restart the server.

## Build

```bash
npm run build
```

This fetches published stable releases from `nemerald-voip/fspbx`, generates their
blog posts, and builds the website into `build/`. Set `GITHUB_TOKEN` when building
repeatedly to avoid GitHub's anonymous API rate limit. API errors fail the build
so a deployment cannot silently lose release history.

## Release notes

Write and correct release notes in GitHub Releases. Publishing or editing a
release requests the **Deploy to GitHub Pages** workflow on `main`. The same
workflow runs for pushes to `main` and can be started with **Run workflow**.
It generates the release pages, builds Docusaurus, and deploys the resulting
artifact. Release promotion, unpublishing, and deletion also request a rebuild.

The release workflow dispatches to `main` because the `github-pages` environment
allows that branch, not release tags. It uses the built-in `GITHUB_TOKEN`; no
personal token or generated commits are needed. The workflow files must be
present in the ref GitHub uses for the event; for older release tags without
these workflows, run the deployment manually on `main` to refresh the notes.

`scripts/sync-releases.mjs` regenerates all stable releases on every build,
including historical releases. Drafts and prereleases are excluded. Files in
`blog/releases/` are generated and ignored by Git; edit the original GitHub
release to change them. Existing manually written posts are preserved, including
older announcements that may discuss the same version. The generated posts are
available in the blog and under its **Release Notes** tag.

Release listings show a short plain-text preview from the opening paragraph, or
the first three changes when the notes start with a list, capped at 300 characters.
The full release page keeps the complete notes and the link to GitHub.

For a local preview, run `npm run releases:sync` followed by `npm start`.
Run `npm run test:releases` for the generator's offline tests. The pull-request
website check also runs these tests and builds with the actual release history.

## Deployment

Using SSH:

```bash
USE_SSH=true yarn deploy
```

Not using SSH:

```bash
GIT_USER=<Your GitHub username> yarn deploy
```

If you are using GitHub pages for hosting, this command is a convenient way to build the website and push to the `gh-pages` branch.
