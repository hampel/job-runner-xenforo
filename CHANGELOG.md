CHANGELOG
=========

1.4.2 (2020-08-29)
------------------

* prevent JobRunner 1.x from being installed on XenForo 2.2

1.4.1 (2020-08-26)
------------------

* renamed the Job run trigger option to avoid issues after upgrading to XF v2.2

1.4.0 (2020-08-26)
------------------

* major update: backporting code from v2.0 of the addon to mimic functionality added for XF v2.2
* job runner command changed from `xf:run-jobs` to `hg:run-jobs` to avoid being clash with core XF v2.2 command
* now uses lock file to permit jobs to run for up to 10 minutes based on `--time` parameter
* admin config option to disable Activity based trigger added (similar to what is coming in XF v2.2)
* minimum PHP version now 7.0.0
* tested on XF v2.1.10 - no longer supporting XF v2.0.x (it might work, but we're not testing it)
* shows an error message if run on 

1.3.1 (2019-11-28)
------------------

* bugfix: don't try executing logger unless we already have it set up - especially during addon installation/upgrade!

1.3.0 (2019-11-28)
------------------

* implemented debug logger for Job execution time tracking
* extend the XF\Job\Cron class with new run function which logs execution time for cron tasks
* added new Cli command and test job for testing purposes

1.2.0 (2019-11-17)
------------------

Thanks to [@Xon](https://xenforo.com/community/members/xon.71874/) for identifying the bug fix where AJAX JSON responses
would still trigger job.php, and for making suggestions on how to avoid unnecessary database queries and avoid running
cron jobs unless triggered via our CLI job runner

* changes: disable calculations for job auto run time to avoid unnecessary database queries
* bug fix: disable AJAX auto job runner from triggering from AJAX JSON responses
* changes: don't allow cron tasks to execute unless triggered via the CLI job runner

1.1.0 (2019-04-11)
------------------

* changes: new icon
* bug fix: should return 0 for successful execution
* new feature: show-jobs command

1.0.1 (2018-12-06)
------------------

* we still want to run jobs if the user has disabled version checking in config
* clear entity cache each time we run a job, to keep memory usage under control

1.0.0a (2018-08-12)
-------------------

* first working version (re-release, new addon_id)

1.0.0 (2018-06-09)
------------------

* first working version
