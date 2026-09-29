# CLI Job Runner for XenForo

Runs XenForo's job queue and cron tasks from Unix cron instead of from page views, with detailed
logging of every job and cron task it runs. A replacement for the core `xf:run-jobs` command that
can run for up to ten minutes at a time, and never lets two runs overlap.

By [Simon Hampel](https://xenforo.com/community/members/sim.4264/).

## Requirements

- XenForo 2.2 or later
- PHP 7.1 or later
- Shell access to the server, to add a cron entry

## Installation

Install the add-on as usual, from the admin control panel or with
`php cmd.php xf:addon-install Hampel/JobRunner`. Then set up cron, below — **the add-on changes
nothing until you do.**

## Setting up cron

1. In the admin control panel, open the **System and performance** options, turn on **Show
   advanced options**, and set **Job run trigger** to **Server based trigger**. Under the default
   activity-based trigger, XenForo runs jobs from page views and needs no cron entry.
2. Add a cron entry for the user your web server runs PHP as, running the job runner every minute:

   ```text
   * * * * * php /path/to/xenforo/cmd.php hg:run-jobs
   ```

Under server-based triggering nothing runs from page views, so if the cron entry stops, so do
XenForo's scheduled tasks. `hg:show-crons` shows when each is next due. XenForo's own
`xf:run-jobs` also runs everything, cron tasks included, with the add-on installed —
`hg:run-jobs` adds the lock and the logging.

## Commands

| command | does |
|---|---|
| `hg:run-jobs` | run outstanding jobs and due cron tasks |
| `hg:run-cron <id>` | run one cron task now, as the scheduler would |
| `hg:show-jobs` | list the job queue; `--all` for more than 100 |
| `hg:show-crons` | list cron tasks and when each is next due; `--all` includes inactive ones, `--method` shows each callback, `--sort=id\|addon\|date` |
| `hg:test-jobs <email>` | queue a job that sends one test email to that address |

`hg:run-jobs` takes:

- `-t`, `--max-execution-time` — how long to keep running jobs, in seconds. Default 55, maximum
  600.
- `--manual-only` — run only manually triggered jobs, such as rebuilds started from the admin
  control panel. Takes no lock, so it can run beside a scheduled run.
- `--reset` — delete an existing lock first. See below.

It exits 0 when it has run, 1 when XenForo cannot run jobs at all, and 2 when another run holds the
lock. `hg:run-cron` exits 1 when the task is not found, has no callback, or throws; `--force` runs
a disabled task.

## Logging

Add verbosity to any run to see what it does:

| flag | shows |
|---|---|
| `-v` | each job and cron task as it starts and finishes, with its execution time |
| `-vv` | the context of each — job data, cron entry, next run time — and the queue loop itself |
| `-vvv` | everything, including the lock and each internal step |

```bash
php cmd.php hg:run-jobs -vv
```

A job of your own can write to the same log while `hg:run-jobs` is running it, through the
`cli.logger` container key — `Job/TestJob.php` shows how.

## The lock

`hg:run-jobs` takes a lock at `internal-data://run-jobs-lock.txt` before running anything, so a run
still going when cron starts the next one is left to finish: the second prints
`JobRunner already running.` and exits.

The lock expires 630 seconds after it was taken — the ten-minute maximum plus a margin — and is
removed when the run ends, including when a job throws. If a run was killed outright and you do not
want to wait out the expiry, `--reset` removes the lock before starting.

## Support

- [CLI Job Runner on xenforo.com](https://xenforo.com/community/resources/cli-job-cron-runner.7931/)
- [Discussion and support](https://xenforo.com/community/threads/cli-job-cron-runner.183700/)
