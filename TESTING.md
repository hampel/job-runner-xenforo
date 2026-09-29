# Testing Job & Cron Monitor

What this add-on touches, what breaks quietly, what the automated suite settles, and what still
needs a person with a forum and a crontab.

## Surfaces

### Class extensions

| extends | with | why |
|---|---|---|
| `XF\Job\Manager` | `Hampel\JobRunner\XF\Job\Manager` | logs the runnable jobs and each job's timing; changes nothing about which jobs run |
| `XF\Service\CronEntry\CalculateNextRun` | `Hampel\JobRunner\XF\Service\CronEntry\CalculateNextRun` | logs each cron entry as it starts and how long it ran, and the next one due |

Both register on their XenForo 2.2 class names, which 2.3 resolves through its aliases. Both call
the parent in every method and only add logging, which happens only when `cli.logger` exists — so
under anything but `job-monitor:run-jobs` they add nothing.

### Console commands

| command | does |
|---|---|
| `job-monitor:run-jobs` | runs the queue by hand, logging each job and cron entry; boots `Hampel\JobRunner\App` for the logger |
| `job-monitor:show-jobs` | lists the job queue |
| `job-monitor:show-crons` | lists cron entries and when each is next due |
| `job-monitor:run-cron <id>` | runs one cron entry now, as the scheduler would |
| `job-monitor:queue-test-job <email>` | queues a job that sends one email to that address |
| `hg:run-jobs` | deprecated: runs XenForo's `xf:run-jobs` with the same options, for pre-3.0.0 crontabs |

The other `hg:` names are aliases of the `job-monitor:` commands for the 3.x line.

### Everything else

- A simpleCache value, `Hampel/JobRunner` / `legacyRunJobsLastUsed`: when `hg:run-jobs` last ran,
  written at most hourly.
- `Setup::upgrade()` and `uninstall()` delete `internal-data://run-jobs-lock.txt`, which versions
  before 3.0.0 could leave behind.
- No options, phrases, templates, routes, permissions, listeners or database tables.

## Fragile points

- **Per-entry cron timing depends on how core's `Cron::run()` calls `CalculateNextRun`**: one
  service per pass, `updateCronRunTimeAtomic()` just before each callback, `getMinimumNextRunTime()`
  after the loop. A XenForo release that changes that makes the timings wrong without an error;
  run `job-monitor:run-jobs` on it and read them.
- **The add-on must not change which jobs run.** Until 2.2.0 it held the `cron` job back for its
  own runner, which stopped XenForo's `xf:run-jobs` running scheduled tasks.
- **`hg:run-jobs` must stay silent.** Cron mails any output, so anything it printed would arrive
  as an email every minute. `LegacyRunJobsCommandTest` pins its output to `xf:run-jobs`'s.
- **An `Error` from a cron callback is handled by XenForo, differently per line.** 2.3 logs it and
  runs the rest of the pass; 2.2 catches only `\Exception`, so the `Error` ends that run and the
  other due entries wait for the next pass.
- **Upgrading on XenForo 2.3 queues XenForo's file clean-up**, which deletes every file in the
  add-on's directory that the release's `hashes.json` does not list. Harmless on an installed copy;
  never upgrade from a release zip over a development checkout. On 2.2 there is no clean-up, so
  files 3.0.0 removed stay on disk, unreferenced.
- **An upgrade can start from the XenForo 1 add-on `DisableRunDeferred`**, through
  `legacy_addon_id`. `Setup::upgrade()` converts nothing — it only deletes the old lock file — but
  that path has not been exercised.

## Automated

From the add-on directory, against a working XenForo install and its database:

```bash
composer install
vendor/bin/phpunit
vendor/bin/phpunit --order-by=random     # the suite must not depend on test order
vendor/bin/phpunit --order-by=reverse
```

It covers both extensions, every command including the `hg:run-jobs` shim, the logger, the test
job and `Setup`. Cron entries and jobs are inserted inside a rolled-back transaction, with the test
clock far in the past so only those rows are due; mail, the job queue, the error log, the registry
and the simpleCache are faked; the run commands run against a mocked job manager. **Nothing on the
forum runs or changes.** A run leaves the error log, the job queue and the registry exactly as it
found them — worth confirming by counting them either side when the suite changes.

Each regression test for a fix since 2.1.3 was confirmed to fail with its fix reverted.

## Needs a human

- **Real cron on a real forum.** Set **Job run trigger** to server-based, put
  `* * * * * php /path/to/cmd.php xf:run-jobs` in the web user's crontab, and confirm over a few
  minutes that queued jobs complete and cron entries' next-run times advance
  (`job-monitor:show-crons`).
- **An old crontab.** Leave `hg:run-jobs` in a crontab for a few minutes: jobs and cron must run,
  and cron must mail nothing new.
- **The custom application class.** `job-monitor:run-jobs` boots `Hampel\JobRunner\App`, which
  registers the logger. A test cannot boot a second application class; run it by hand and confirm
  it logs each job and cron entry with no flags.
- **Upgrade from the last published release** on XenForo 2.2 and 2.3, in a throwaway install. Then
  run the job queue and check the server error log — the file clean-up runs after the upgrade
  reports success.
- **The PHP floor.** The suite needs PHP 8.3 for its own tooling; the add-on promises 7.1. Run
  `job-monitor:run-jobs` once on the oldest PHP a supported XenForo install allows.
- **`job-monitor:queue-test-job` sends a real email** through the forum's mail transport. Use an
  address you control, on a forum whose mail goes somewhere safe.
