# Contributing to Laragraph

Thanks for taking the time to contribute. This guide covers the local dev
loop, the quality gate every PR needs to pass, and where things live in the
codebase.

## Getting started

Requirements: PHP 8.2+ and Composer.

```bash
git clone https://github.com/ayimdomnic/laragraph.git
cd laragraph
composer install
```

The package itself has no app to run — `workbench/` and `example/` are the
two ways to exercise it against a real Laravel app:

```bash
composer workbench:build   # builds the Orchestra Workbench testbench app
composer serve             # serve it
composer tinker            # or drop into a REPL against it
```

`example/` is a separate, fuller Laravel application (with its own
`composer.json`) that exercises every documented feature — most new
functionality is easiest to try there.

## Before opening a PR

Run the full quality gate locally:

```bash
composer check   # lint (Pint) + refactor:check (Rector) + phpstan + test
```

This is the same gate CI runs. Note: CI on this repository has been broken
pre-existing for a while, unrelated to any specific change — don't let a red
CI status block a review; the local `composer check` is the real bar.

Individual pieces, if you want to iterate faster:

```bash
composer test              # full PHPUnit suite
composer test:unit         # tests/Unit only
composer test:feature      # tests/Feature only
composer test:performance  # tests/Performance (budget assertions)
composer phpstan           # static analysis, level 8
composer lint               # Pint, check-only
composer format              # Pint, auto-fix
composer refactor:check    # Rector, dry-run
```

## Adding tests

Test layout mirrors `src/`:

- `tests/Unit/**` — one collaborator in isolation, mocking its dependencies.
- `tests/Feature/**` — HTTP-level behavior (`postJson('/graphql', ...)`),
  exercising the real request/response contract.
- `tests/Performance/**` — hard budget assertions (memory, query count).

If you're adding a new interface under `src/Contracts/`, add a matching unit
test for its concrete implementation, following the existing `tests/Unit/**`
structure.

## Commit style

This repository uses [Conventional Commits](https://www.conventionalcommits.org/)
scoped to the touched subsystem, e.g. `feat(subscriptions): ...`,
`fix(scaffold): ...`, `perf(dataloader): ...`, `docs: ...`. Check `git log`
for precedent if you're unsure which scope to use.

## Adding a new feature category

If you're adding a new kind of discoverable class, extension point, or
Artisan generator, check the relevant docs chapter first
([docs/README.md](docs/README.md) has the full index) — most of these
follow an established convention (a base class + a `laragraph:make:*`
generator + a discovery entry in `config/laragraph.php`) that's worth
matching rather than reinventing.

## Security issues

Please don't open a public issue for a suspected vulnerability — see
[SECURITY.md](SECURITY.md) for the private reporting process.

## Code of Conduct

This project follows the [Contributor Covenant](CODE_OF_CONDUCT.md).
