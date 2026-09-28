# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Scope

This is one XenForo add-on — `Hampel/JobRunner`, "CLI Job Runner". It sits at
`src/addons/Hampel/JobRunner/` inside a XenForo install and is its own git repository; the install
around it is not, and any sibling add-ons beside it are other repositories.

Where the install has an `AGENTS.md` at its root, read it first — it carries the XF conventions,
the `_output`/`_data` boundary, the `cmd.php` command signatures and the per-add-on git layout.
This file covers only what is specific to this add-on.

## What this add-on is

**A replacement for core's `xf:run-jobs`, meant to be driven by Unix cron, with verbosity-graded
logging of every job and cron entry it runs.** Its commands are `hg:run-jobs` (the runner),
`hg:run-cron <id>` (one cron entry, on demand), `hg:show-jobs`, `hg:show-crons`, and
`hg:test-jobs <email>`. There are no options, templates, phrases or routes — `_output/` holds only
three class extensions and one `app_cli_setup` listener.

**It only does anything when the forum's job trigger is set to server-based.** With XenForo's
`jobRunTrigger` option at `activity`, every overridden method falls straight through to the parent.

## How a run is wired together

The pieces are spread across files that never reference each other directly, so this is the map.

- **`hg:run-jobs` boots its own app class.** `Cli/Command/RunJobs` implements
  `CustomAppCommandInterface`, so XenForo starts `Hampel\JobRunner\App` instead of `XF\Cli\App`.
  That class pushes `job.runTime` 30 minutes into the future, so nothing ever triggers jobs
  automatically, and registers `cli.output` (null until the command sets it) and `cli.logger`.
- **The other `hg:*` commands run under the stock CLI app, so `cli.logger` does not exist there**
  and every log call in the extensions is a silent no-op. That is by design: `LoggerTrait` and
  `TestJob::log()` both check for the logger before using it. Keep that check in anything new.
- **`Listener::appCliSetup` registers `job.hg.runner`** — the `SubContainer\JobRunner`, which owns
  the lock and the 600-second runtime ceiling. It exists only on CLI requests.
- **Cron entries are fenced off unless the runner lets them in.** `XF\Job\Manager::getRunnable()`
  excludes the job with `unique_key = 'cron'` unless `setAllowCron(true)` has been called, and only
  `hg:run-jobs` calls it. So with server-based triggering, cron tasks run through this command and
  nowhere else. The exclusion is raw SQL with **two branches, 2.2 and 2.3**, because 2.3 added
  `priority` ordering to the job queue; a change to one almost certainly belongs in both.
- **`Manager::updateNextRunTime()` returns `\XF::$time` without querying**, to avoid the
  `autoJobRun` registry write core does after every queue run.

### The lock

`internal-data://run-jobs-lock.txt` holds a Unix expiry time of **start + 600 + 30 seconds**. A
second `hg:run-jobs` that finds an unexpired lock exits with code 2; `--reset` deletes the lock
first, for a runner that crashed without removing it. `--manual-only` takes no lock at all.

**`--max-execution-time` says "max: 900" in its help text, but the real ceiling is 600** —
`JobRunner::getMaxQueueRunTime()` clamps to `runtime.limit`, and the lock expiry is derived from
the same value. Raise one without the other and two runners can overlap.

### Logging verbosity

| flag | adds |
|---|---|
| `-v` | job start and completion with timings, each cron entry executed |
| `-vv` | the queue loop, the JSON context on every line, runnable job lists, next cron entry |
| `-vvv` | method-entry `debug()` lines and the `extra` payload, including lock writes |

`LoggerTrait`'s methods are `private` and named `log()` and `debug()`, so a class that already has
methods of those names cannot use it.

## `XF/Job/Cron::run()` is a copy of core, not a wrapper

**It replaces the parent method wholesale** to interleave logging, and never calls
`parent::run()` except when no logger is present. Every XenForo upgrade can change the core
original without this copy noticing. The add-on's lines are marked `// JobRunner addon`, which is
what makes a diff against core readable.

**The catch around each cron callback must stay `\Throwable`.** On XF 2.3 an `Error` escaping
`run()` fails the job, and core then deletes the `cron` job from the queue and re-creates it only
when a cron entry is next saved — so one broken cron task from any add-on stops every scheduled
task on the forum. XF 2.2's core catches only `\Exception`; this copy is deliberately wider.

Remaining drift, checked on 2026-09-28 against XF 2.3: core resolves the service and finder by
class name rather than short name, which 2.2 cannot do.

**`Cli/Command/RunCron` must invoke a callback exactly as `run()` does** — through `extendClass()`,
inside a `\Throwable` catch — or `hg:run-cron` debugs different code from what runs on schedule.

## Compatibility floors are load-bearing

- **PHP 7.1, declared in `addon.json` because it is above XF 2.2's own minimum of 7.0.** Nullable
  types and `void` returns are available; typed properties, arrow functions and anything later
  are not. The floor was raised from 7.0 in 2.1.3 to replace an implicit nullable parameter, which
  PHP 8.4 deprecates, with `?Logger`. XF 2.3 requires 7.2, so only 2.2 installs are affected.
- **XenForo 2.2.** `Setup::postUpgrade()` guards `enqueuePostUpgradeCleanUp()` behind
  `\XF::$versionId >= 2030000` because that method does not exist before 2.3; removing the guard
  makes every upgrade on 2.2 a fatal.
- **`Setup.php` must exist even though it does nothing.** Without it, upgrading from 1.5 fails
  (CHANGELOG, 2.0.0). `legacy_addon_id` is `DisableRunDeferred`, the add-on's original ID.

## Commands

Run from this directory — it is the git repo. `cmd.php` resolves the install from its own location
rather than the working directory, so the relative path works unchanged.

```bash
composer install                                                  # first time; vendor/ is gitignored
vendor/bin/phpunit                                                # whole suite
vendor/bin/phpunit --testsuite Unit                               # one suite
vendor/bin/phpunit --filter LockTest                              # one class
php ../../../../cmd.php xf-dev:import --addon=Hampel/JobRunner    # after editing _output/
php ../../../../cmd.php xf-addon:sync-json Hampel/JobRunner       # after editing addon.json
php ../../../../cmd.php hg:run-jobs -vv                           # exercise the runner
php ../../../../cmd.php hg:run-jobs --reset                       # clear a stale lock and run
php ../../../../cmd.php xf-addon:build-release Hampel/JobRunner   # release only; writes _releases/
```

**Never run `xf-dev:export`.** It writes the database over `_output/`, and omitting `--addon`
exports every add-on in the install. `xf-addon:build-release` calls the scoped `xf-addon:export`.

**`hg:test-jobs <email>` sends a real email** through the forum's configured mail transport the
next time the queue runs. Point it at an address you control, on an install whose mail goes to a
sink.

## Tests

The suite is on `hampel/xenforo-test-framework` `^5.9` and PHPUnit `^12.0`. The framework boots the
enclosing XenForo install (`tests/TestCase.php` sets `$rootDir` to four levels up), so it needs a
working install and database, not only `vendor/`.

**`$addonsToLoad` names this add-on, and the suite does not run without it.** Booting XenForo
registers the Composer autoloader of every active add-on that declares one, so on a forum where
another add-on vendors a different PHPUnit major, PHPUnit's own classes resolve out of that tree
and the run dies before the first test. Isolation also keeps other add-ons' class extensions and
listeners out of the tests.

**The PHP 8.3 the test framework needs is not the add-on's floor.** `composer.json` holds dev
dependencies only — `composer.lock`'s `packages` array is empty — so nothing from `vendor/` ships,
and the release still promises PHP 7.1.

**`league/flysystem-memory` stays on `^1.0`, and nothing in this repository imports it.** It is
there for the test framework's `swapFs()`, which builds its `MemoryAdapter`, and it has to match
the Flysystem 1.x that XenForo bundles. `composer outdated` will keep reporting a major version
behind; that is expected, not a bump to take.

`LockTest` swaps `internal-data` for an in-memory filesystem and
mocks the logger with exact-argument expectations — **a change to a log message in `Util\Lock` is
a test change too.** `tests/Feature/ExampleTest.php` is a placeholder, and `TESTING.md` is an empty
template.

Nothing tests the extensions, the commands or the lock under concurrency.

## Build and release

**`build.json`'s last step moves every root `*.md` to the zip root, so a dev-only document is kept
out only by an `rm -fv` that runs before it.** The exec steps delete Composer's files,
`phpunit.xml`, `tests/`, `vendor/`, both PHPUnit cache names, `TESTING.md` and both Claude files; a
new dev-only root document needs adding to that list, or it ships beside the README.

**That list covers the XenForo zip only.** GitHub's "Download ZIP" is `git archive`, which
ignores `build.json` and is governed by `export-ignore` in `.gitattributes`; a new dev-only file
needs adding in both places. Read the `xenforo-addon-audit` skill before changing packaging.

## Versioning

Open the development cycle **before** starting work on a released version: bump to an alpha of
the next patch (`2.1.2` → `2010311` / `"2.1.3a1"`) and commit that on its own. Until then, any zip
built from the tree claims to be the last release, and XenForo names the release zip from the
version string alone and overwrites what is already there.

`CHANGELOG.md` is stable-only, newest first, with an ISO date on each version.
