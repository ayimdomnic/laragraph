# Performance testing

Two complementary layers guard Laragraph's performance:

| | Performance budgets | Benchmarks |
|---|---|---|
| **Measures** | *Work*: SQL queries, classes built, memory kept | *Time* and peak memory |
| **Where** | [`tests/Performance`](../tests/Performance) (PHPUnit) | [`benchmarks/`](.) (PHPBench) |
| **Deterministic** | Yes, so they never flake | No; results vary with the machine |
| **Runs** | With every `composer test` | On demand, compared against a baseline |
| **Catches** | N+1 queries, eager schema building, memory leaks, unbounded caches | Slowdowns in any code path |

Both use the same fixtures: a small blog domain (organizations → authors → posts, on SQLite) in
[`tests/Support/Blog`](../tests/Support/Blog), and a generated 300-type schema,
[`tests/Support/SyntheticSchema.php`](../tests/Support/SyntheticSchema.php).

## Performance budgets

```bash
composer test:performance
```

| Test | Budget |
|---|---|
| `DatabaseBudgetTest` | A 50-item page runs 2 queries. Four levels of nested relations run 5 queries, the same for 2 organizations or 30. A validated mutation runs 2. |
| `SchemaBudgetTest` | Building the 300-type schema builds no types. A request builds only the types it reaches (plus the types their fields name). Only introspection builds everything. |
| `MemoryBudgetTest` | 200 repeated batched operations, 1,000 new documents and 100 large documents each retain less than 512 KB once the caches are full. |

Each budget was checked against a real regression before it was committed. The eager schema of
earlier versions fails `SchemaBudgetTest`. Resolving relations without batching fails
`DatabaseBudgetTest`. Not releasing DataLoaders after a request (127 MB retained) and an unbounded
parse cache (16 MB retained) fail `MemoryBudgetTest`.

**When a budget fails**, the change made Laragraph do more work. Fix the regression. Change the
budget only if the extra work is intended, and say why in the commit.

## Benchmarks

```bash
composer bench             # run every benchmark and print a table
composer bench -- --group=execution     # one group: schema, execution or documents
composer bench -- --filter=benchNested  # one subject
```

| Benchmark | Subject | What it measures |
|---|---|---|
| `SchemaBench` | `benchBuildSchema` | Creating the 300-type schema (lazy, so it should be cheap) |
| | `benchColdRequest` | A PHP-FPM request: a fresh schema plus one small operation |
| | `benchFullTypeMap` | Building every type (introspection, `laragraph:validate`) |
| `ExecutionBench` | `benchTinyQuery` | The fixed cost of an operation |
| | `benchPaginatedList` | 50 Eloquent models through a Relay connection |
| | `benchNestedBatchedRelations` | 20 → 100 → 400 rows through `batchRelation()` and a DataLoader |
| | `benchValidatedMutation` | A mutation with validation rules |
| | `benchHttpRequest` | The list through the HTTP kernel: routing, parsing, JSON |
| `DocumentBench` | `benchRepeatedDocument` | A large document the worker has seen before |
| | `benchNewDocument` | A large document parsed and validated from scratch |

A full run takes about a minute.

### Checking a change for regressions

Record a baseline on the base branch, then compare your branch against it:

```bash
git checkout master
composer bench:baseline      # stores the results in .phpbench/ (git-ignored) under the tag "baseline"

git checkout my-branch
composer bench:compare       # exits non-zero when any subject is >15% slower or uses >10% more memory
```

`bench:compare` prints each subject's change next to its result:

```
| benchTinyQuery | 200 | 5 | 19.138mb +0.43% | 613.220μs +352.80% | ±3.64% |
```

Timings depend on the machine, so compare only runs made on the same machine, in the same
conditions: plugged in, nothing heavy running, and the same PHP version and extensions. If a
subject is noisy (a high `rstdev`), run it alone with more iterations before drawing conclusions:

```bash
vendor/bin/phpbench run --filter=benchPaginatedList --iterations=20 --ref=baseline --report=laragraph
```

### Adding a benchmark

Add a `bench*` method to the class for its area, or a new `*Bench` class extending `BenchCase`,
which boots the application and seeds the fixtures. Keep each revolution under about 30 ms, so the
suite stays quick. Every operation a benchmark runs must succeed: `BenchCase::execute()` throws
on GraphQL errors, so a broken benchmark can't pass by measuring an error path.

When a benchmark exposes a *work* regression (more queries, more classes built, memory kept),
also add a budget to `tests/Performance`. Budgets are deterministic, so they catch the
regression on every test run.
