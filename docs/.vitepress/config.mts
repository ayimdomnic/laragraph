import { defineConfig } from 'vitepress';
import { existsSync, readdirSync } from 'node:fs';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

// ---------------------------------------------------------------------------
// Multi-version docs.
//
// The live docs/ tree is always the "latest" version, served at `/`. Each
// released version is frozen into docs/versions/<x.y.z>/ (see snapshot.mjs),
// served under /versions/<x.y.z>/ with its own sidebar. A single build covers
// every version, so this works with Vercel's default single-branch clone — no
// git branches or per-site `base` juggling required.
//
// Archived pages carry `search: false`, so the (local) search index stays
// scoped to the latest docs and never returns stale version pages.
// ---------------------------------------------------------------------------

const docsRoot = resolve(dirname(fileURLToPath(import.meta.url)), '..'); // docs/

// Curated page order + display text, grouped exactly as the sidebar sections.
const GROUPS: { text: string; pages: [string, string][] }[] = [
  {
    text: 'Start here',
    pages: [
      ['01-getting-started', '1. Getting started'],
      ['02-types', '2. Types'],
      ['03-queries-and-mutations', '3. Queries & mutations'],
    ],
  },
  {
    text: 'Building a real API',
    pages: [
      ['04-authentication-and-authorization', '4. Authentication & authorization'],
      ['05-relations-and-dataloaders', '5. Relations & DataLoaders'],
      ['06-pagination', '6. Pagination'],
      ['07-subscriptions', '7. Subscriptions'],
      ['08-http-api', '8. The HTTP API'],
      ['09-multiple-schemas', '9. Multiple schemas'],
    ],
  },
  {
    text: 'Running it in production',
    pages: [
      ['10-security', '10. Security'],
      ['11-performance-and-caching', '11. Performance & caching'],
      ['12-observability', '12. Observability'],
      ['13-testing', '13. Testing'],
      ['14-deployment', '14. Deployment'],
    ],
  },
  {
    text: 'Reference',
    pages: [
      ['15-configuration', '15. Configuration reference'],
      ['16-upgrading', '16. Upgrading'],
      ['17-error-handling-and-localization', '17. Error handling & localization'],
      ['18-example-application', '18. The example application'],
    ],
  },
];

// Newest first. Present in the dropdown and given a sidebar.
const archivedVersions = existsSync(join(docsRoot, 'versions'))
  ? readdirSync(join(docsRoot, 'versions'), { withFileTypes: true })
      .filter((d) => d.isDirectory() && /^\d+\.\d+\.\d+$/.test(d.name))
      .map((d) => d.name)
      .sort((a, b) => b.localeCompare(a, undefined, { numeric: true }))
  : [];

// Build a sidebar for a set of slugs, prefixing every link. `available` limits
// the list to pages that actually exist in that version (older releases may
// not have every page), so archived sidebars never contain dead links.
function sidebar(prefix: string, available: Set<string> | null) {
  return GROUPS.map((group) => ({
    text: group.text,
    items: group.pages
      .filter(([slug]) => available === null || available.has(slug))
      .map(([slug, text]) => ({ text, link: `${prefix}${slug}` })),
  })).filter((group) => group.items.length > 0);
}

function slugsIn(version: string): Set<string> {
  return new Set(
    readdirSync(join(docsRoot, 'versions', version))
      .filter((f) => /^\d{2}.*\.md$/.test(f))
      .map((f) => f.replace(/\.md$/, '')),
  );
}

// Path-keyed sidebars: archived versions first (more specific prefixes), then
// `/` as the fallback for the live/latest docs.
const sidebars: Record<string, ReturnType<typeof sidebar>> = {};
for (const version of archivedVersions) {
  sidebars[`/versions/${version}/`] = sidebar(`/versions/${version}/`, slugsIn(version));
}
sidebars['/'] = sidebar('/', null);

// Version dropdown — "latest" plus every archived version, each pointing at
// its landing page. `activeMatch` highlights whichever one you are reading.
const versionMenu = {
  text: 'Version',
  items: [
    { text: 'latest', link: '/01-getting-started', activeMatch: '^/(?!versions/)' },
    ...archivedVersions.map((version) => ({
      text: version,
      link: `/versions/${version}/01-getting-started`,
      activeMatch: `^/versions/${version.replace(/\./g, '\\.')}/`,
    })),
  ],
};

export default defineConfig({
  title: 'Laragraph',
  description: 'A modern, feature-rich GraphQL package for Laravel.',
  srcDir: '.',
  outDir: '.vitepress/dist',
  cleanUrls: true,
  // README.md is the natural entry point on GitHub — reuse it as the site's
  // homepage too, rather than maintaining a second, near-duplicate index.md.
  rewrites: {
    'README.md': 'index.md',
  },
  ignoreDeadLinks: [
    // Links out to the example app / repo root, outside docs/ — real files,
    // just not part of this VitePress build. VitePress normalizes these to
    // "./../..." before checking, so match "../" anywhere, not just at the
    // very start of the string.
    /\.\.\//,
  ],

  themeConfig: {
    nav: [
      { text: 'Guide', link: '/01-getting-started' },
      versionMenu,
      { text: 'GitHub', link: 'https://github.com/ayimdomnic/laragraph' },
      { text: 'Packagist', link: 'https://packagist.org/packages/ayimdomnic/laragraph' },
    ],

    sidebar: sidebars,

    socialLinks: [
      { icon: 'github', link: 'https://github.com/ayimdomnic/laragraph' },
    ],

    search: {
      provider: 'local',
    },

    editLink: {
      pattern: 'https://github.com/ayimdomnic/laragraph/edit/master/docs/:path',
      text: 'Edit this page on GitHub',
    },

    footer: {
      message: 'Released under the MIT License.',
      copyright: 'Copyright © Odhiambo Dormnic',
    },
  },
});
