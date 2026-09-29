# Job & Cron Monitor for XenForo

Command-line tools for watching, inspecting and diagnosing XenForo's job queue and cron tasks. Run
the queue by hand and see each job and cron task as it runs, with its execution time; list what is
queued and when each cron task is next due; run a single cron task on demand.

Cron keeps running XenForo's own `xf:run-jobs`. This add-on is for the person trying to find out
what it is doing.

By [Simon Hampel](https://xenforo.com/community/members/sim.4264/).

## Requirements

- XenForo 2.2 or later
- PHP 7.1 or later
- Shell access to the server

## Installation

Install the add-on as usual, from the admin control panel or with
`php cmd.php xf:addon-install Hampel/JobRunner`. It changes nothing about how jobs run.

## Running jobs from cron

This is XenForo's own setup; the add-on does not need it, but is most useful on a forum that runs
it.

1. In the admin control panel, open the **System and performance** options, turn on **Show
   advanced options**, and set **Job run trigger** to **Server based trigger**. Under the default
   activity-based trigger, XenForo runs jobs from page views and needs no cron entry.
2. Add a cron entry for the user your web server runs PHP as, running XenForo's job runner every
   minute:

   ```text
   * * * * * php /path/to/xenforo/cmd.php xf:run-jobs
   ```

Under server-based triggering nothing runs from page views, so if the cron entry stops, so do
XenForo's scheduled tasks. `job-monitor:show-crons` shows when each is next due.

## Commands

| command | does |
|---|---|
| `job-monitor:health-check` | check that the job queue and cron are running; `--fix` queues a missing cron job again |
| `job-monitor:run-jobs` | run outstanding jobs and due cron tasks by hand, logging each as it runs |
| `job-monitor:show-jobs` | list the job queue; `--all` for more than 100 |
| `job-monitor:show-failed-jobs` | list jobs that failed, newest first; `--id` shows one in full. XenForo 2.3 or later |
| `job-monitor:show-stats` | how often each job and cron task ran and how long it took; `--name` shows one hour by hour |
| `job-monitor:show-crons` | list cron tasks and when each is next due; `--all` includes inactive ones, `--method` shows each callback, `--sort=id\|addon\|date` |
| `job-monitor:run-cron <id>` | run one cron task now, as the scheduler would; `--force` runs a disabled one |
| `job-monitor:queue-test-job <email>` | queue a job that sends one test email to that address |

`job-monitor:run-jobs` takes:

- `-t`, `--max-execution-time` — how long to keep running jobs, in seconds. Default 55; `0` runs
  until the queue is empty.
- `--manual-only` — run only manually triggered jobs, such as rebuilds started from the admin
  control panel.

It exits 0 when it has run and 1 when XenForo cannot run jobs at all. `job-monitor:run-cron` exits
1 when the task is not found, has no callback, or throws.

It is safe to run while cron is running `xf:run-jobs`: XenForo claims each job before running it,
so the two never run the same job.

## Health check

`job-monitor:health-check` reports whether jobs and cron are actually running, one line per check:

| check | a problem when |
|---|---|
| Job trigger | never — says which trigger the forum uses |
| Cron job queued | XenForo's `cron` job is missing from the queue, so no scheduled task will run until a cron entry is next saved |
| Overdue jobs | the oldest due job has waited more than 5 minutes (a warning) or an hour (a problem) |
| Overdue cron tasks | a cron task is more than an hour past its next run |
| Admin warning | the admin control panel is showing "There are scheduled jobs outstanding which have not run" (a warning) |
| Manual jobs | a manually started job has stopped, such as a rebuild left unfinished in a browser (a warning) |
| Failed jobs | any job has failed in the last day (a warning; XenForo 2.3 or later) |
| Crontab | `hg:run-jobs` has run in the last two hours, so a crontab still needs changing (a warning) |

It changes nothing unless you pass `--fix`, which does the one repair it knows: queue a missing
`cron` job again, as XenForo does when a cron entry is saved.

It exits 0 when everything is fine, 1 on a warning and 2 on a problem, so a monitoring system can
run it as it stands:

```bash
php cmd.php job-monitor:health-check || echo "jobs or cron need attention"
```

## Failed jobs

On XenForo 2.3, a job that throws and cannot be retried is removed from the queue and — in debug
mode only — recorded. With the add-on installed **every** failure is recorded, debug mode or not,
so `job-monitor:show-failed-jobs` can show what failed, when, with what data, and why:

```bash
php cmd.php job-monitor:show-failed-jobs            # the newest 20
php cmd.php job-monitor:show-failed-jobs --id=24    # one in full, with its stack trace
```

A daily cron task deletes records older than 30 days, including those XenForo wrote in debug mode,
which it otherwise keeps forever. The health check warns when any job has failed in the last day.

## Statistics

Every job and cron task that runs is timed — whichever command runs it, cron's `xf:run-jobs`
included, and on page views under activity-based triggering — and kept hour by hour for 90 days:

```bash
php cmd.php job-monitor:show-stats                        # the last 24 hours, by total time
php cmd.php job-monitor:show-stats --sort=max             # the slowest single runs
php cmd.php job-monitor:show-stats --type=cron --hours=168
php cmd.php job-monitor:show-stats --name='XF\Job\Cron'   # one, hour by hour
```

`--sort` takes `time` (the default), `runs`, `max` or `avg`; `--type` takes `job` or `cron`;
`--limit` sets how many rows. Failures are counted alongside runs on XenForo 2.3.

XenForo 2.2 has no record of failed jobs: a job that throws is logged to the server error log and
treated as complete.

## Logging

`job-monitor:run-jobs` shows each job and cron task as it starts and finishes, with its execution
time, without being asked. Add verbosity for more:

| flag | adds |
|---|---|
| `-v` | the context of each — job data, cron entry, next run time — and the queue loop itself |
| `-vv` | every internal step |
| `-q` | nothing at all |

```bash
php cmd.php job-monitor:run-jobs -v
```

A job of your own can write to the same log while `job-monitor:run-jobs` is running it, through the
`cli.logger` container key — `Job/TestJob.php` shows how.

## Upgrading from 2.x

Version 3.0.0 renamed the add-on from **CLI Job Runner** and changed what it is for: it no longer
runs your cron jobs, XenForo does.

- **Change your crontab from `hg:run-jobs` to `xf:run-jobs`.** `hg:run-jobs` still works — it now
  runs `xf:run-jobs` with the same options, so nothing breaks — but it will be removed in a later
  major version.
- The other commands have new names. Their old `hg:` names still work in 3.x.

| was | now |
|---|---|
| `hg:run-jobs` in cron | `xf:run-jobs` |
| `hg:run-jobs` by hand | `job-monitor:run-jobs` |
| `hg:show-jobs` | `job-monitor:show-jobs` |
| `hg:show-crons` | `job-monitor:show-crons` |
| `hg:run-cron` | `job-monitor:run-cron` |
| `hg:test-jobs` | `job-monitor:queue-test-job` |

- There is no lock any more. `--reset` is accepted by `hg:run-jobs` and does nothing.

## Support

- [Job & Cron Monitor on xenforo.com](https://xenforo.com/community/resources/cli-job-cron-runner.7931/)
- [Discussion and support](https://xenforo.com/community/threads/cli-job-cron-runner.183700/)
