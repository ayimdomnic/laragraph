// Archive the *current* live docs into a frozen, read-only snapshot for a
// released version. Run this right before cutting a release so the previous
// version's documentation stays browsable after the live docs move on.
//
//   node docs/.vitepress/snapshot.mjs 4.4.0
//
// It copies every numbered guide page from docs/*.md into
// docs/versions/<version>/ and tags each with frontmatter that hides it from
// the (latest-only) local search index and from "last updated", so archived
// pages never pollute search or show a misleading git-mtime.
//
// The live docs/ tree is always "latest"; snapshots are pure additions and are
// picked up automatically by config.mts (which reads this directory), so no
// config edit is needed to publish a new version's archive.

import {
  readdirSync,
  readFileSync,
  writeFileSync,
  mkdirSync,
  existsSync,
  rmSync,
} from 'node:fs';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const here = dirname(fileURLToPath(import.meta.url));
const docsRoot = resolve(here, '..'); // docs/

const version = process.argv[2];
if (!version || !/^\d+\.\d+\.\d+$/.test(version)) {
  console.error('Usage: node docs/.vitepress/snapshot.mjs <x.y.z>');
  process.exit(1);
}

const dest = join(docsRoot, 'versions', version);

// Numbered guide pages only (01-... through NN-...). README.md is the latest
// homepage and is intentionally not archived; .vitepress holds the shared
// config, not per-version content.
const pages = readdirSync(docsRoot)
  .filter((f) => /^\d{2}.*\.md$/.test(f))
  .sort();

if (pages.length === 0) {
  console.error(`No numbered docs pages found in ${docsRoot}`);
  process.exit(1);
}

// Replace any existing snapshot for this version so re-running is idempotent.
if (existsSync(dest)) {
  rmSync(dest, { recursive: true, force: true });
}
mkdirSync(dest, { recursive: true });

for (const page of pages) {
  const original = readFileSync(join(docsRoot, page), 'utf8');
  writeFileSync(join(dest, page), withArchiveFrontmatter(original));
}

console.log(`Archived ${pages.length} docs pages -> docs/versions/${version}/`);

// Ensure the page carries our two archive flags, preserving any existing
// frontmatter (the current guide pages have none).
function withArchiveFrontmatter(markdown) {
  const flags = { search: 'false', lastUpdated: 'false' };

  if (!markdown.startsWith('---\n')) {
    return `---\nsearch: false\nlastUpdated: false\n---\n\n${markdown}`;
  }

  const end = markdown.indexOf('\n---', 4);
  const raw = markdown.slice(4, end + 1); // existing frontmatter body
  const lines = raw.split('\n');

  for (const [key, value] of Object.entries(flags)) {
    const idx = lines.findIndex((l) => new RegExp(`^${key}\\s*:`).test(l));
    if (idx === -1) {
      lines.push(`${key}: ${value}`);
    } else {
      lines[idx] = `${key}: ${value}`;
    }
  }

  return `---\n${lines.join('\n')}\n---${markdown.slice(end + 4)}`;
}
