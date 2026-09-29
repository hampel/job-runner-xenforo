# Changelog

## 3.0.1 (2026-09-29)

* the `job-monitor:` commands are listed without their old `hg:` names beside them. `hg:show-jobs`,
  `hg:show-crons`, `hg:run-cron` and `hg:test-jobs` still work in 3.x, but no longer appear in
  `list`

## 3.0.0 (2026-09-29)

Renamed from **CLI Job Runner** to **Job & Cron Monitor**. The add-on no longer runs your jobs:
XenForo's own `xf:run-jobs` does, and this add-on is for watching and diagnosing it. See
"Upgrading from 2.x" in the README.

* **change your crontab from `hg:run-jobs` to `xf:run-jobs`.** `hg:run-jobs` still works - it now
  runs `xf:run-jobs` with the same options and prints nothing of its own - but will be removed in a
  later major version. `--reset` is accepted and ignored
* commands renamed: `job-monitor:run-jobs`, `job-monitor:show-jobs`, `job-monitor:show-crons`,
  `job-monitor:run-cron` and `job-monitor:queue-test-job`. The old `hg:` names still work in 3.x
* `job-monitor:run-jobs` logs each job and cron task without `-v`; `--max-execution-time=0` runs
  until the queue is empty
* new: `job-monitor:health-check` checks that jobs and cron are running - the cron job queued,
  overdue jobs and cron tasks, failed jobs, and an old crontab still calling `hg:run-jobs`. Exits 0,
  1 or 2; `--fix` re-queues a missing cron job; `--json` for monitoring
* new: on XenForo 2.3 every failed job is recorded, not only in debug mode, and
  `job-monitor:show-failed-jobs` lists them
* new: every job and cron task run is timed and kept hour by hour for 90 days;
  `job-monitor:show-stats` shows the busiest, slowest or one task's history
* new: a daily cron task prunes failed jobs after 30 days and statistics after 90
* XenForo's own `xf:run-jobs` now runs cron tasks with the add-on installed - the add-on used to
  hold them back for its own runner
* the lock is gone: XenForo already stops two runners running the same job. The old lock file is
  deleted on upgrade
* on XenForo 2.2, a cron task throwing an `Error` now ends that run, as it does in XenForo itself;
  the other due tasks run on the next pass

## 2.1.4 (2026-09-29)

* bugfix: under server-based job triggering, the admin control panel no longer reports "There are
  scheduled jobs outstanding which have not run" when jobs are running normally. The add-on had
  stopped XenForo recording when the next job was due, so the check read a time that never moved

## 2.1.3 (2026-09-29)

* now requires PHP 7.1 or later, up from 7.0 - XenForo 2.3 already requires 7.2, so this affects
  XenForo 2.2 forums only
* bugfix: a cron task throwing an `Error`, such as a `TypeError`, no longer stops the rest of the
  cron tasks due in that run. On XenForo 2.3 it also removed the `cron` job from the queue, which
  stopped every scheduled task on the forum until a cron entry was next saved; on XenForo 2.2 it
  crashed `hg:run-jobs`
* bugfix: `hg:run-jobs` releases its lock when a job throws, rather than leaving it in place and
  refusing every run for up to ten minutes with `JobRunner already running.`
* bugfix: `hg:run-cron` runs a cron task's class as extended by other add-ons, the same code the
  scheduler runs, and reports an `Error` from the task rather than failing with a PHP fatal error
* `hg:run-jobs --help` gives the real maximum for `--max-execution-time`: 600 seconds, not 900
* README rewritten: requirements, setting up cron and the **Job run trigger** option, and every
  command with its options

## 2.1.2 (2025-12-12)

* run `enqueuePostUpgradeCleanUp()` during upgrades if we're running XF2.3+

## 2.1.1 (2024-07-12)

* compatibilty with XF 2.3

## 2.1.0 (2021-06-11)

* had required parameters after optional parameters - re-arranging logJobProgress function signature to make it more 
  usable
* added new option --reset to delete any existing lockfile - useful in situations where a job has crashed without 
  removing the lock and you don't want to wait 10 minutes for the lock to expire

## 2.0.0 (2020-09-30)

* listen to app_cli_setup rather than app_setup
* bugfix: need to include Setup.php even though we don't do anything in it - otherwise upgrade from v1.5 fails

## 2.0.0b3 (2020-09-24)

* code cleanup: we don't need a custom app class to show jobs
* new CLI command: hg:show-crons
* new CLI command: hg:run-cron
* add logQuiet function to LoggerTrait
* XF2.2 updates the XF time after each queue run
* rebuilt Lock functionality to use a non-static class, managed by a SubContainer
* ensure lock expiry is at least 30 seconds after the max execution time (10 minutes)

## 2.0.0b2 (2020-08-07)

* remove redundant class extension thanks to XF 2.2b2

## 2.0.0b1 (2020-08-06)

* complete rewrite for XF v2.2 - new command names; lockfile based command protection; extensive logging support
* unit tests

## 1.5.0 (2020-09-24)

* code cleanup: we don't need a custom app class to show jobs
* new CLI command: hg:show-crons
* new CLI command: hg:run-cron
* add logQuiet function to LoggerTrait
* rebuilt Lock functionality to use a non-static class, managed by a SubContainer
* ensure lock expiry is at least 30 seconds after the max execution time (10 minutes)

## 1.4.2 (2020-08-29)

* prevent JobRunner 1.x from being installed on XenForo 2.2

## 1.4.1 (2020-08-26)

* renamed the Job run trigger option to avoid issues after upgrading to XF v2.2

## 1.4.0 (2020-08-26)

* major update: backporting code from v2.0 of the addon to mimic functionality added for XF v2.2
* job runner command changed from `xf:run-jobs` to `hg:run-jobs` to avoid being clash with core XF v2.2 command
* now uses lock file to permit jobs to run for up to 10 minutes based on `--time` parameter
* admin config option to disable Activity based trigger added (similar to what is coming in XF v2.2)
* minimum PHP version now 7.0.0
* tested on XF v2.1.10 - no longer supporting XF v2.0.x (it might work, but we're not testing it)
* shows an error message if run on XenForo 2.2

## 1.3.1 (2019-11-28)

* bugfix: don't try executing logger unless we already have it set up - especially during addon installation/upgrade!

## 1.3.0 (2019-11-28)

* implemented debug logger for Job execution time tracking
* extend the XF\Job\Cron class with new run function which logs execution time for cron tasks
* added new Cli command and test job for testing purposes

## 1.2.0 (2019-11-17)

Thanks to [@Xon](https://xenforo.com/community/members/xon.71874/) for identifying the bug fix where AJAX JSON responses
would still trigger job.php, and for making suggestions on how to avoid unnecessary database queries and avoid running
cron jobs unless triggered via our CLI job runner

* changes: disable calculations for job auto run time to avoid unnecessary database queries
* bug fix: disable AJAX auto job runner from triggering from AJAX JSON responses
* changes: don't allow cron tasks to execute unless triggered via the CLI job runner

## 1.1.0 (2019-04-11)

* changes: new icon
* bug fix: should return 0 for successful execution
* new feature: show-jobs command

## 1.0.1 (2018-12-06)

* we still want to run jobs if the user has disabled version checking in config
* clear entity cache each time we run a job, to keep memory usage under control

## 1.0.0a (2018-08-12)

* first working version (re-release, new addon_id)

## 1.0.0 (2018-06-09)

* first working version
