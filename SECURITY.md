# Security Policy

## Supported Versions

Laragraph follows semantic versioning. Security fixes are backported only to
the latest minor of the current major release; older majors are unsupported.

| Version | Supported          |
| ------- | ------------------ |
| 4.x     | :white_check_mark: |
| 3.x     | :x:                |
| < 3.0   | :x:                |

If you're on an unsupported version, upgrade before reporting — see
[docs/16-upgrading.md](docs/16-upgrading.md).

## Reporting a Vulnerability

Please **do not** open a public GitHub issue for a suspected vulnerability.

1. Preferred: use [GitHub Security Advisories](https://github.com/ayimdomnic/laragraph/security/advisories/new)
   to report privately.
2. Alternative: email **ayimdomnic@gmail.com** with a description, affected
   version(s), and, if possible, a minimal reproduction.

You should get an acknowledgement within 5 business days. We aim to ship a
fix or mitigation within 90 days of a confirmed report, and will credit you
in the release notes unless you ask not to be named.

## Scope

In scope: the `ayimdomnic/laragraph` package itself (`src/`).

Out of scope: the [`example/`](example/) application (a demo, not hardened
for production) and misconfiguration of a consuming app (e.g. leaving
`app.debug` on in production — Laragraph's own defaults already guard
against the consequences of that, see below).

## What Laragraph does by default

Laragraph ships secure defaults and documents every hardening knob in
[docs/10-security.md](docs/10-security.md): introspection disabled outside
`app.debug`, no mutations over GET, internal exception messages hidden from
clients, `hash_equals()` for persisted-query hash comparisons, regex-validated
multipart file-map paths, per-user response-cache partitioning, and
configurable query depth/complexity/alias limits. Read that document before
deploying to production — this file only covers *reporting* a vulnerability,
not the hardening checklist.
