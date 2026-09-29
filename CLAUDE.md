# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Scope

This is one XenForo add-on — `Hampel/JobRunner`, "Job & Cron Monitor" (titled "CLI Job Runner"
before 3.0.0; the id kept its old name so existing installs upgrade). It sits at
`src/addons/Hampel/JobRunner/` inside a XenForo install and is its own git repository; the install
around it is not, and any sibling add-ons beside it are other repositories.

Where the install has an `AGENTS.md` at its root, read it first — it carries the XF conventions,
the `_output`/`_data` boundary, the `cmd.php` command signatures and the per-add-on git layout.
This file covers only what is specific to this add-on.

## What this add-on is

**Command-line tools for watching and diagnosing the job queue and cron — not a job runner.** Cron
runs XenForo's own `xf:run-jobs`; this add-on is what a person, or a monitoring system, runs to see
what it is doing. Its commands are `job-monitor:health-check` (is the queue and cron running; exits
0, 1 or 2), `job-monitor:run-jobs` (run the queue and log each job and cron entry),
`job-monitor:show-jobs`, `job-monitor:show-failed-jobs`, `job-monitor:show-crons`,
`job-monitor:run-cron <id>` and `job-monitor:queue-test-job <email>`. There are no options,
templates, routes or listeners — `_output/` holds two class extensions, and the daily cron entry
that prunes failed jobs with its title phrase.

**On XenForo 2.3 the add-on records every failed job, not only in debug mode** — the `Manager`
extension's `logFailedJob()` writes the `xf_failed_job` row when core declines — and
`Cron\FailedJobs::prune()` deletes rows older than 30 days, since core never does.

**`hg:run-jobs` is a compatibility shim, not a command of the add-on's own.** Before 3.0.0 the
add-on *was* the runner and people put `hg:run-jobs` in their crontab, so it survives as a wrapper
that runs `xf:run-jobs` with the same options. **It must stay silent** — cron mails any output — and
records its use in the simpleCache instead, which `job-monitor:health-check` reports. The other
`hg:` names are Symfony aliases of the `job-monitor:` commands, for the 3.x line only.

## How a run is wired together

The pieces are spread across files that never reference each other directly, so this is the map.

- **`job-monitor:run-jobs` boots its own app class.** `Cli/Command/RunJobs` implements
  `CustomAppCommandInterface`, so XenForo starts `Hampel\JobRunner\App` instead of `XF\Cli\App`.
  That class registers `cli.output` (null until the command sets it) and `cli.logger`, and nothing
  else.
- **Every other command runs under the stock CLI app, so `cli.logger` does not exist there** and
  every log call in the extensions is a silent no-op. That is what keeps `xf:run-jobs`, and so cron,
  exactly core's behaviour. `LoggerTrait` and `TestJob::log()` both check for the logger before
  using it; keep that check in anything new.
- **The add-on does not change which jobs run, and must not start again.** Every `Manager`
  override calls the parent and only adds logging, so the `cron` job is runnable from any runner.
  Until 2.2.0 `getRunnable()` held the `cron` job back for the add-on's own runner, which silently
  stopped `xf:run-jobs` running scheduled tasks on any forum with the add-on installed.
  `JobManagerTest` pins it.
- **Do not override `Manager::updateNextRunTime()`.** Core writes the next due time to the
  `autoJobRun` registry entry after every job and on every enqueue, and the admin index's
  `hasStoppedJobs()` check reads it. Until 2.1.4 the add-on skipped that write under server
  triggering, the registry went stale, and admins saw "There are scheduled jobs outstanding which
  have not run" whenever a job happened to be due. `JobManagerTest` pins it.
- **There is no lock.** Versions before 3.0.0 locked their runner with
  `internal-data://run-jobs-lock.txt`; it only ever guarded that runner against itself, and XenForo
  already claims each job atomically, so running `job-monitor:run-jobs` beside cron's `xf:run-jobs`
  is safe. `Setup` deletes the old file on upgrade and uninstall.

### Logging verbosity

`job-monitor:run-jobs` raises the requested verbosity one level before logging, so the log shows
without asking. In the logger's own terms:

| requested | logger sees | adds |
|---|---|---|
| none | `-v` | job start and completion with timings, each cron entry executed |
| `-v` | `-vv` | the queue loop, the JSON context on every line, runnable job lists, next cron entry |
| `-vv` | `-vvv` | method-entry `debug()` lines and the `extra` payload |

**`Cli\Logger::log()` prints nothing below verbose**, so `logQuiet()` and `logNormal()` only ever
appear from `-v` up, whatever their names say. Every command that logs raises verbosity or asks
for it; a new one that relies on `logNormal()` at default verbosity will print nothing.

`LoggerTrait`'s methods are `private` and named `log()` and `debug()`, so a class that already has
methods of those names cannot use it.

## Every extension calls the parent — there is no copy of core

**Keep it that way.** Until 2.2.0 the add-on replaced `XF\Job\Cron::run()` wholesale to log each
cron entry, and the copy drifted: it caught `\Exception` where XF 2.3's core had moved to
`\Throwable`, so on 2.3 one broken cron task from any add-on made core delete the `cron` job and
every scheduled task stopped (fixed in 2.1.3). `Manager::getRunnable()` and
`CalculateNextRun::updateCronRunTimeAtomic()` were copies too. **New overrides call the parent
first, then add** — never re-implement a core method to get at its internals.

**Per-entry cron logging comes from two points core already calls.** `Cron::run()` builds one
`CalculateNextRun` service per pass, calls `updateCronRunTimeAtomic()` immediately before each
entry's callback, and `getMinimumNextRunTime()` once the loop ends. So an entry's time runs from
its claim to the next claim, or to the end of the pass, and the extension keeps the running entry
on the service instance. If core ever stops sharing one service across a pass, or calls either
method at a different point, the timings go wrong without anything failing —
`CronJobTest::test_each_entry_is_logged_when_verbose` is the check.

**An `Error` from a cron callback is core's to handle, and the two lines differ.** XF 2.3's
`Cron::run()` catches `\Throwable`, so the rest of the pass runs. XF 2.2's catches only
`\Exception`, so an `Error` ends that run, under `xf:run-jobs` and `job-monitor:run-jobs` alike: the
broken entry's next-run time has already moved on, and the other due entries run on the next pass.
Accepted in 2.2.0 as the cost of carrying no copy (up to a minute's delay, on 2.2 only).

**`Cli/Command/RunCron` must invoke a callback as core's `Cron::run()` does** — through
`extendClass()` — or `job-monitor:run-cron` debugs different code from what runs on schedule. It
catches `\Throwable` on both lines, so it reports an `Error` rather than dying with a PHP fatal.

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
vendor/bin/phpunit --filter LoggerTest                            # one class
php ../../../../cmd.php xf-dev:import --addon=Hampel/JobRunner    # after editing _output/
php ../../../../cmd.php xf-addon:sync-json Hampel/JobRunner       # after editing addon.json
php ../../../../cmd.php job-monitor:run-jobs -v                   # run the queue and read the log
php ../../../../cmd.php hg:run-jobs                               # the shim: must print what xf:run-jobs does
php ../../../../cmd.php xf-addon:build-release Hampel/JobRunner   # release only; writes _releases/
```

**Never run `xf-dev:export`.** It writes the database over `_output/`, and omitting `--addon`
exports every add-on in the install. `xf-addon:build-release` calls the scoped `xf-addon:export`.

**`job-monitor:queue-test-job <email>` sends a real email** through the forum's configured mail
transport the next time the queue runs. Point it at an address you control, on an install whose
mail goes to a sink.

## Tests

The suite is on `hampel/xenforo-test-framework` `^5.11` and PHPUnit `^12.0`. The framework boots the
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

### How the tests reach real state without leaving any

- **Cron entries and jobs are inserted directly, inside `UsesDatabaseTransactions`**, through
  `insertCronEntry()` and `insertJob()` in `tests/TestCase.php`. Going through the entities would
  write dev output and queue the cron job.
- **`setTestTime(1000)` is what isolates them.** Every real entry and job on a forum is due in the
  future, so with the clock far in the past only the rows a test inserted are due. A test that
  drops it would run the forum's real cron tasks.
- **`jobRunTrigger` is set to `server` wherever it matters.** XenForo's default is `activity`,
  under which every overridden `Manager` method defers to core — so a test relying on the forum's
  own setting passes or fails depending on the forum.
- **The extensions only log when `cli.logger` exists**, and a test app has none. `useCliLogger()`
  registers one over a `BufferedOutput`; without it the extensions add nothing and core runs alone.
- **Both run commands are tested against a mocked job manager**, so they run no real jobs.
  `LegacyRunJobsCommandTest` runs the `hg:run-jobs` shim and XenForo's `xf:run-jobs` against the
  same mock and requires identical output.
- **`tests/Fixtures/` holds the cron callbacks**, recording each call by entry id, and
  `Fixtures/Job/FailingJob` — XenForo only accepts a job class inside a `Job` namespace.

### Never run a failing job through the job manager in a test

**Core's `runJobInternal()` calls `$db->rollbackAll()` when a job throws, which also ends the test's
`UsesDatabaseTransactions` wrapper — every write after it is committed to the forum for real.** On
2026-09-29 a test that ran a failing job through `runJobEntry()` left two rows in the development
forum's `xf_failed_job`; they were found by counting the table either side and deleted. So
`FailedJobsTest` calls `logFailedJob()` through reflection instead. Count any table a new test
writes before and after the suite. Reported to the test framework, which does not detect it.

### A class can be extended in only one test per run

`RunCronCommandTest` extends `CronCallbacks` with `tests/Fixtures/ExtendedCronCallbacks.php`, and
the framework forgets the extension when that test ends. **A second test extending the same class
fails**: the `XFCP_` proxy is a `class_alias()`, which PHP cannot declare twice in one process. A
second extension test needs a base class of its own.

A data provider runs before XenForo boots, so it cannot name a Symfony Console constant;
`LoggerTest` uses the literal verbosity values.

Not covered: `Hampel\JobRunner\App::initializeExtra()`, since a test cannot boot a second
application class — `RunJobsCommandTest` registers the logger the way it does instead.

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
