import { defineConfig } from 'vitepress';

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
      { text: 'GitHub', link: 'https://github.com/ayimdomnic/laragraph' },
      { text: 'Packagist', link: 'https://packagist.org/packages/ayimdomnic/laragraph' },
    ],

    sidebar: [
      {
        text: 'Start here',
        items: [
          { text: '1. Getting started', link: '/01-getting-started' },
          { text: '2. Types', link: '/02-types' },
          { text: '3. Queries & mutations', link: '/03-queries-and-mutations' },
        ],
      },
      {
        text: 'Building a real API',
        items: [
          { text: '4. Authentication & authorization', link: '/04-authentication-and-authorization' },
          { text: '5. Relations & DataLoaders', link: '/05-relations-and-dataloaders' },
          { text: '6. Pagination', link: '/06-pagination' },
          { text: '7. Subscriptions', link: '/07-subscriptions' },
          { text: '8. The HTTP API', link: '/08-http-api' },
          { text: '9. Multiple schemas', link: '/09-multiple-schemas' },
        ],
      },
      {
        text: 'Running it in production',
        items: [
          { text: '10. Security', link: '/10-security' },
          { text: '11. Performance & caching', link: '/11-performance-and-caching' },
          { text: '12. Observability', link: '/12-observability' },
          { text: '13. Testing', link: '/13-testing' },
          { text: '14. Deployment', link: '/14-deployment' },
        ],
      },
      {
        text: 'Reference',
        items: [
          { text: '15. Configuration reference', link: '/15-configuration' },
          { text: '16. Upgrading', link: '/16-upgrading' },
          { text: '17. Error handling & localization', link: '/17-error-handling-and-localization' },
        ],
      },
    ],

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
