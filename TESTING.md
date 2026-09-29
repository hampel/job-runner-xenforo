# Testing CLI Job Runner

What this add-on touches, what breaks quietly, what the automated suite settles, and what still
needs a person with a forum and a crontab.

## Surfaces

### Class extensions

| extends | with | why |
|---|---|---|
| `XF\Job\Cron` | `Hampel\JobRunner\XF\Job\Cron` | replaces `run()` wholesale to log each cron entry and its timing |
| `XF\Job\Manager` | `Hampel\JobRunner\XF\Job\Manager` | keeps the cron job out of the queue unless `hg:run-jobs` lets it in; skips the next-run-time calculation; logs each job |
| `XF\Service\CronEntry\CalculateNextRun` | `Hampel\JobRunner\XF\Service\CronEntry\CalculateNextRun` | logs each claimed entry and the next one due |

All three register on their XenForo 2.2 class names, which 2.3 resolves through its aliases.

### Code event listener

- `app_cli_setup` — `Listener::appCliSetup()` registers the `job.hg.runner` sub-container, which
  owns the lock and the 600-second runtime ceiling. It exists on CLI requests only.

### Console commands

| command | does |
|---|---|
| `hg:run-jobs` | runs the queue under a lock, cron tasks included; boots `Hampel\JobRunner\App` |
| `hg:run-cron <id>` | runs one cron entry now, as the scheduler would |
| `hg:show-jobs` | lists the job queue |
| `hg:show-crons` | lists cron entries and when each is next due |
| `hg:test-jobs <email>` | queues a job that sends one email to that address |

### Everything else

- A lock file, `internal-data://run-jobs-lock.txt`, holding a Unix expiry time.
- No options, phrases, templates, routes, permissions or database tables. It changes behaviour
  only when the forum's **Job run trigger** option is set to server-based; under activity-based
  triggering every overridden method defers to XenForo.

## Fragile points

- **Three methods are copies of XenForo's, not wrappers**, and a XenForo release can change the
  original without the copy noticing: `Cron::run()`, `CalculateNextRun::updateCronRunTimeAtomic()`,
  and the query in `Manager::getRunnable()`, which has one branch per XenForo line because 2.3
  added priority ordering. Diff each against the new release's core before declaring it supported.
- **The catch around each cron callback must be `\Throwable`.** On XenForo 2.3 an `Error` escaping
  `Cron::run()` fails the job, and core then removes the cron job from the queue until a cron entry
  is next saved — every scheduled task on the forum stops. On 2.2 the `Error` escapes `hg:run-jobs`
  altogether, skipping the rest of the pass.
- **`hg:run-jobs` must release its lock in a `finally`.** Any exception out of the queue otherwise
  leaves the lock for its full 630 seconds, and every run in that window reports
  `JobRunner already running.` instead of the failure.
- **The lock's expiry is derived from the runtime ceiling:** 600 seconds, plus 30. Change one
  without the other and two runners can overlap.
- **Upgrading on XenForo 2.3 queues XenForo's file clean-up**, which deletes every file in the
  add-on's directory that the release's `hashes.json` does not list. Harmless on an installed copy;
  never upgrade from a release zip over a development checkout.
- **An upgrade can start from the XenForo 1 add-on `DisableRunDeferred`**, through
  `legacy_addon_id`. `Setup.php` has no upgrade steps, so nothing is converted, but that path has
  not been exercised.

## Automated

From the add-on directory, against a working XenForo install and its database:

```bash
composer install
vendor/bin/phpunit
vendor/bin/phpunit --order-by=random     # the suite must not depend on test order
vendor/bin/phpunit --order-by=reverse
```

It covers the three extensions, all five commands, the logger, the lock, the listener, the test
job and `Setup`. Cron entries and jobs are inserted inside a rolled-back transaction, with the test
clock far in the past so only those rows are due; mail, the job queue, the error log and the
registry are faked; `hg:run-jobs` runs against a mocked job manager. **Nothing on the forum runs or
changes.** A run leaves the error log, the job queue and the registry exactly as it found them —
worth confirming by counting them either side when the suite changes.

The regression tests for the 2.1.3 fixes were each confirmed to fail with their fix reverted.

## Needs a human

- **Real cron on a real forum.** Set **Job run trigger** to server-based, add
  `* * * * * php /path/to/cmd.php hg:run-jobs` to the web user's crontab, and confirm over a few
  minutes that queued jobs complete and cron entries' next-run times advance (`hg:show-crons`). Run
  once by hand with `-vv` and read the log.
- **Two runners at once.** Start `hg:run-jobs -t 55` in two terminals: the second must print
  `JobRunner already running.` and exit 2. The suite covers the lock in one process only.
- **The custom application class.** `hg:run-jobs` boots `Hampel\JobRunner\App`, which pushes
  `job.runTime` 30 minutes ahead so no page load triggers jobs. A test cannot boot a second
  application class; confirm that browsing the forum under server-based triggering does not
  call `job.php`.
- **Upgrade from the last published release** on XenForo 2.2 and 2.3, in a throwaway install. Then
  run the job queue and check the server error log — the file clean-up runs after the upgrade
  reports success.
- **The PHP floor.** The suite needs PHP 8.3 for its own tooling; the add-on promises 7.1. Run
  `hg:run-jobs -vv` once on the oldest PHP a supported XenForo install allows.
- **`hg:test-jobs` sends a real email** through the forum's mail transport. Use an address you
  control, on a forum whose mail goes somewhere safe.
