CLI Job Runner for XenForo 2.1
==============================

This XenForo 2.1 addon disables the browser triggered job runner and implements a CLI triggered job runner for use with 
Unix cron.

By [Simon Hampel](https://twitter.com/SimonHampel).

Requirements
------------

This addon requires PHP 7.0.0 or higher and has been tested on XenForo 2.1.10 

Installation
------------

Install as per normal addon installation.

Note: once this addon is installed and activated, scheduled tasks will no longer run - so completing the remaining 
installation steps is critical to ensure your forum continues to function normally.

First, you should test that your job runner is functioning - execute the following command from your CLI:

	:::bash
	$ php <path to your forum root>/cmd.php hg:run-jobs

For example, if your forum root is `/srv/www/xenforo/community`, then the job runner command would be:

	:::bash
	$ php /srv/www/xenforo/community/cmd.php hg:run-jobs

Running this command will execute any outstanding jobs and then finish with a message about whether there are more jobs
waiting to be executed or not. When executing this command from cron, it is recommended that you use the `--quiet` 
(or `-q`) flag to suppress output. 

Once you are happy that the job runner functions correctly, you will need to create your own cron task to run it on a
schedule of your choosing.

__Approach #1 using crontab:__

It is highly recommended that you have your cron task run as the web server user to prevent potential permission 
problems.

For example, on Ubuntu with a web server user of www-data, install a cron task by running the following command:

    :::bash
    $ sudo crontab -u www-data -e
    
Edit the crontab file and add:

    :::bash
    *       *       *       *       *       php /path/to/your/forum/root/cmd.php --quiet hg:run-jobs
   
Save the crontab.

__Approach #2 using cron.d:__

Instead of using a crontab, some Linux distributions create a well-known directory which is automatically checked for 
cron tasks to execute. In the case of Ubuntu, you can create files in `/etc/cron.d/` where you specify the schedule, the
user to execute the command as, and the command itself.

Create a file in `/etc/cron.d/` with the following contents:

	:::bash
	* * * * * webserver-user php /path/to/your/forum/root/cmd.php --quiet hg:run-jobs

... where `webserver-user` is changed to the name of the user your web server runs as and change the path to your forum 
root.  

Again, using our previous example where web server user is `www-data` and our forum root is 
`/srv/www/xenforo/community`, I would execute the following command to create the cron file: 

	:::bash
	echo "* * * * * www-data php /srv/www/xenforo/community/cmd.php --quiet hg:run-jobs" | sudo tee -a /etc/cron.d/xenforo

Both options (crontab and cron.d) will execute the job runner every minute, checking for outstanding jobs to be run.

By default, the job runner will run for a maximum of 30 seconds, executing any outstanding jobs until there are no more
runnable jobs in the queue.

Configuration
-------------

You may adjust the maximum execution time of the job runner by specifying the `--time=[TIME]` option on the command 
line.

For example, to allow the job runner to execute for a maximum of 45 seconds:

	:::bash
	$ php <path to your forum root>/cmd.php --time=45 hg:run-jobs

It is not recommended that you allow the job runner to run for longer than the period between cron triggers. For
example, the above cron task example will execute the job runner every minute, so setting the maximum run time to more
than 60 is generally a bad idea.  

If you prefer to not run the cron task as frequently as once per minute, you can adjust the cron job as 
required and if you do, you may also want to allow the job runner task to run for longer than the default 30 seconds to
ensure that all outstanding work is completed.

For example, to run the cron task every 5 minutes, allowing the job runner to execute for a maximum of 4 minutes, use
the following cron command:

    :::bash
    */5       *       *       *       *       php <path to your forum root>/cmd.php --quiet --time=240 hg:run-jobs

For further customisation of your job execution, you may also adjust the maximum time that each job is permitted to run.
This is configured via a [XenForo config.php Option](https://xenforo.com/xf2-docs/manual/config/#other-variables):
 
	:::php
	$config[jobMaxRunTime'] = 8;

The `jobMaxRunTime` option configures the amount of time in seconds that processing jobs will be allowed to run before 
they are suspended for further processing on another go-around, if possible. The default setting is optimised for the 
browser-triggered job runner and so to allow jobs to execute longer in a CLI environment, you may want to adjust this
to a higher value. 

You should not set `jobMaxRunTime` to anything higher than 30 seconds, or the time specified by the --time option. In 
general it is suggested that this setting be kept to a relatively small value to avoid the situation where a single very
long job may prevent other jobs from executing in a timely manner. Some experimentation may be required to find the 
optimal value for your server load and forum size.

 Usage
 -----
 
 The `run-jobs` command should be executed automatically using a cron task as per the instructions above.
 
 **xf:show-jobs**
 
 The `xf:show-jobs` command outputs a list of all the currently pending jobs, so you can see how full the jobs queue is.
 
 By default only the next scheduled 100 jobs will be shown, you may use the `--all` option to show a complete list of 
 all pending jobs.
 
 There should always be at least one job (the main Cron job) in the list. For XF 2.1 you'll also see the upgrade check 
 job.
 
 Debugging Jobs
 --------------
 
 v1.3 adds new debugging tools to help identify issues with Jobs and Cron tasks.
 
 To run in debug mode, first disable the Unix cron which runs jobs automatically and then use the verbosity options 
(Verbose: `-v`, Very verbose:`-vv` or Debug: `-vvv`) for the `hg:run-jobs` command to specify the level of output to 
show on the console.

Output is to the console and is in a format similar to that used by the Monolog library (although we do not use Monolog
to generate the output).
 
For example, Verbose option `-v`:
 
 ```bash
$ php cmd.php hg:run-jobs -v
[2019-11-27 23:53:09] XF\Job\Cron: Cron entry XF\Cron\CleanUp::runUserDowngrade executed in 0.01 seconds
[2019-11-27 23:53:09] XF\Job\Cron: Cron entry Hampel\LogDigest\Cron\SendLogs::serverError executed in 0.00 seconds
[2019-11-27 23:53:09] XF\Job\Cron: Cron entry XF\Cron\MemberStats::rebuildMemberStatsCache executed in 0.00 seconds
[2019-11-27 23:53:09] XF\Job\Cron: Cron entry Hampel\Slack\Cron\NotifyLogs::notify executed in 0.03 seconds
[2019-11-27 23:53:09] XF\Job\Cron: Cron entry XF\Cron\Feeder::importFeeds executed in 0.01 seconds
[2019-11-27 23:53:09] XF\Job\Cron: Cron entry XFMG\Cron\RandomCache::generateRandomMediaCache executed in 0.07 seconds
[2019-11-27 23:53:09] XF\Job\Cron: Cron entry XF\Cron\EmailBounce::process executed in 0.00 seconds
[2019-11-27 23:53:09] XF\Job\Cron: Cron entry XF\Cron\Counters::rebuildForumStatistics executed in 0.02 seconds
[2019-11-27 23:53:09] XF\Job\Cron: Job executed in 0.20 seconds
No more runnable jobs pending
```

The Very Verbose option `-vv` adds context, typically about the `JobResult`:

```bash
$ php cmd.php hg:run-jobs -vv
[2019-11-27 23:49:49] XF\Job\Cron: Cron entry Hampel\Slack\Cron\NotifyLogs::notify executed in 0.01 seconds {"entry_id":"slackNotifyServerErrors","cron_class":"Hampel\\Slack\\Cron\\NotifyLogs","cron_method":"notify","run_rules":{"day_type":"dom","dom":{"0":-1},"hours":{"0":-1},"minutes":{"0":-1}},"active":true,"next_run":1574898543,"addon_id":"Hampel\/Slack"}

[2019-11-27 23:49:49] XF\Job\Cron: Cron entry Hampel\SparkPost\Cron\MessageEvents::fetchMessageEvents executed in 0.00 seconds {"entry_id":"sparkpostMessageEvents","cron_class":"Hampel\\SparkPost\\Cron\\MessageEvents","cron_method":"fetchMessageEvents","run_rules":{"day_type":"dom","dom":{"0":-1},"hours":{"0":-1},"minutes":{"0":19,"1":49}},"active":true,"next_run":1574898543,"addon_id":"Hampel\/SparkPost"}

[2019-11-27 23:49:49] XF\Job\Cron: Job executed in 0.03 seconds {"completed":false,"jobId":2,"continueDate":1574898603,"continueDate_formatted":"2019-11-27 23:50:03 UTC","statusMessage":"Running... Cron entries"}

[2019-11-27 23:49:49] Hampel\SparkPost:MessageEvent: Job executed in 0.83 seconds {"completed":true,"jobId":12,"continueDate":null,"continueDate_formatted":"","statusMessage":""}

No more runnable jobs pending
```

And finally the Debug option `-vvv` adds extra information about the job:

```bash
$ php cmd.php hg:run-jobs -vvv
[2019-11-27 23:48:03] XF\Job\Cron: Cron entry XF\Cron\Feeder::importFeeds executed in 0.01 seconds {"entry_id":"feeder","cron_class":"XF\\Cron\\Feeder","cron_method":"importFeeds","run_rules":{"day_type":"dom","dom":{"0":-1},"hours":{"0":-1},"minutes":{"0":2,"1":12,"2":22,"3":32,"4":42,"5":52}},"active":true,"next_run":1574879524,"addon_id":"XF"} {}

[2019-11-27 23:48:03] XF\Job\Cron: Cron entry XF\Cron\Counters::rebuildForumStatistics executed in 0.02 seconds {"entry_id":"forumStatistics","cron_class":"XF\\Cron\\Counters","cron_method":"rebuildForumStatistics","run_rules":{"day_type":"dom","dom":{"0":-1},"hours":{"0":-1},"minutes":{"0":3,"1":13,"2":23,"3":33,"4":43,"5":53}},"active":true,"next_run":1574879584,"addon_id":"XF"} {}

[2019-11-27 23:48:03] XF\Job\Cron: Cron entry XF\Cron\MemberStats::rebuildMemberStatsCache executed in 0.03 seconds {"entry_id":"memberStatsCache","cron_class":"XF\\Cron\\MemberStats","cron_method":"rebuildMemberStatsCache","run_rules":{"day_type":"dom","dom":{"0":-1},"hours":{"0":-1},"minutes":{"0":0,"1":10,"2":20,"3":30,"4":40,"5":50}},"active":true,"next_run":1574880004,"addon_id":"XF"} {}

[2019-11-27 23:48:03] XF\Job\Cron: Cron entry XF\Cron\Trophy::runTrophyCheck executed in 0.00 seconds {"entry_id":"trophy","cron_class":"XF\\Cron\\Trophy","cron_method":"runTrophyCheck","run_rules":{"day_type":"dom","dom":{"0":-1},"hours":{"0":-1},"minutes":{"0":40}},"active":true,"next_run":1574880004,"addon_id":"XF"} {}

[2019-11-27 23:48:03] XF\Job\Cron: Cron entry XFMG\Cron\Statistics::cacheGalleryStatistics executed in 0.01 seconds {"entry_id":"xfmgCacheStats","cron_class":"XFMG\\Cron\\Statistics","cron_method":"cacheGalleryStatistics","run_rules":{"day_type":"dom","dom":{"0":-1},"hours":{"0":-1},"minutes":{"0":10,"1":40}},"active":true,"next_run":1574880004,"addon_id":"XFMG"} {}

[2019-11-27 23:48:03] XF\Job\Cron: Cron entry XF\Cron\CleanUp::expireTempUserChanges executed in 0.00 seconds {"entry_id":"expireTempUserChanges","cron_class":"XF\\Cron\\CleanUp","cron_method":"expireTempUserChanges","run_rules":{"day_type":"dom","dom":{"0":-1},"hours":{"0":-1},"minutes":{"0":42}},"active":true,"next_run":1574880124,"addon_id":"XF"} {}

[2019-11-27 23:48:03] XF\Job\Cron: Cron entry XFMG\Cron\RandomCache::generateRandomAlbumCache executed in 0.02 seconds {"entry_id":"xfmgGenerateRandomAlbum","cron_class":"XFMG\\Cron\\RandomCache","cron_method":"generateRandomAlbumCache","run_rules":{"day_type":"dom","dom":{"0":-1},"hours":{"0":-1},"minutes":{"0":12,"1":42}},"active":true,"next_run":1574880124,"addon_id":"XFMG"} {}

[2019-11-27 23:48:03] XF\Job\Cron: Cron entry XF\Cron\EmailUnsubscribe::process executed in 0.00 seconds {"entry_id":"emailUnsubscribe","cron_class":"XF\\Cron\\EmailUnsubscribe","cron_method":"process","run_rules":{"day_type":"dom","dom":{"0":-1},"hours":{"0":-1},"minutes":{"0":13,"1":43}},"active":true,"next_run":1574880184,"addon_id":"XF"} {}

[2019-11-27 23:48:03] XF\Job\Cron: Cron entry XF\Cron\Ban::deleteExpiredBans executed in 0.01 seconds {"entry_id":"deleteExpiredBans","cron_class":"XF\\Cron\\Ban","cron_method":"deleteExpiredBans","run_rules":{"day_type":"dom","dom":{"0":-1},"hours":{"0":-1},"minutes":{"0":45}},"active":true,"next_run":1574880304,"addon_id":"XF"} {}

[2019-11-27 23:48:03] XF\Job\Cron: Job executed in 0.32 seconds {"completed":false,"jobId":2,"continueDate":1574898543,"continueDate_formatted":"2019-11-27 23:49:03 UTC","statusMessage":"Running... Cron entries"} {"job_id":2,"unique_key":"cron","execute_class":"XF\\Job\\Cron","execute_data":{},"manual_execute":0,"trigger_date":1574879464,"last_run_date":1574879405,"trigger_date_formatted":"2019-11-27 18:31:04 UTC","last_run_date_formatted":"2019-11-27 18:30:05 UTC"}

[2019-11-27 23:48:03] Hampel\SparkPost:MessageEvent: Job executed in 0.90 seconds {"completed":true,"jobId":10,"continueDate":null,"continueDate_formatted":"","statusMessage":""} {"job_id":10,"unique_key":"SparkPostMessageEvents","execute_class":"Hampel\\SparkPost:MessageEvent","execute_data":{},"manual_execute":0,"trigger_date":1574898483,"last_run_date":null,"trigger_date_formatted":"2019-11-27 23:48:03 UTC","last_run_date_formatted":""}

[2019-11-27 23:48:03] Hampel\SparkPost:EmailBounce: Job executed in 0.02 seconds {"completed":true,"jobId":11,"continueDate":null,"continueDate_formatted":"","statusMessage":""} {"job_id":11,"unique_key":"SparkPostEmailBounce","execute_class":"Hampel\\SparkPost:EmailBounce","execute_data":{},"manual_execute":0,"trigger_date":1574898483,"last_run_date":null,"trigger_date_formatted":"2019-11-27 23:48:03 UTC","last_run_date_formatted":""}

No more runnable jobs pending
```

Custom job debugging
--------------------

You can add additional debugging to your custom jobs.

Add the following function to your job class to call the `logJobProgress` function of our Logger class:

```php
	protected function log($message, array $context = [])
	{
		// check to see if we actually have a logger available and abort if not
		if (!isset($this->app['cli.logger'])) return;

		/** @var Logger $logger */
		$logger = $this->app['cli.logger'];
		$logger->logJobProgress($message, $context, $this);
	}
```

Then you can call the `log` function in your job code to send information to the console when the Job Runner is executed
in verbose mode.

For example - see the test job included in this addon `Hampel\JobRunner\Job\TestJob`:

```php
	public function run($maxRunTime)
	{
		$this->log("About to start test job", $this->data);

		$mail = $this->app->mailer()->newMail();
		$mail->setTo($this->data['email']);
		$mail->setContent(
			"Test job",
			"This is an email sent from a test job"
		);
		$sent = $mail->send();

		$this->log("Sent mail", ['sent' => $sent]);

		return $this->complete();
	}
```

The above code will generate the following output when the Job Runner is in debug mode:

```bash
$ php cmd.php hg:run-jobs -vvv
[2019-11-28 00:26:21] Hampel\JobRunner:TestJob: About to start test job {"email":"foo@example.com"} {"job_id":17,"class":"Hampel\\JobRunner\\Job\\TestJob","status_message":"Testing jobs","data":{"email":"foo@example.com"},"execution_time":"0.00"}

[2019-11-28 00:26:21] Hampel\JobRunner:TestJob: Sent mail {"sent":1} {"job_id":17,"class":"Hampel\\JobRunner\\Job\\TestJob","status_message":"Testing jobs","data":{"email":"foo@example.com"},"execution_time":"0.95"}

[2019-11-28 00:26:21] Hampel\JobRunner:TestJob: Job executed in 0.95 seconds {"completed":true,"jobId":17,"continueDate":null,"continueDate_formatted":"","statusMessage":""} {"job_id":17,"unique_key":null,"execute_class":"Hampel\\JobRunner:TestJob","execute_data":{"email":"foo@example.com"},"manual_execute":0,"trigger_date":1574900777,"last_run_date":null,"trigger_date_formatted":"2019-11-28 00:26:17 UTC","last_run_date_formatted":""}

No more runnable jobs pending
```

No output will be shown when run in quiet mode - and more importantly, if this addon is disabled the logging code
will not need to be removed. The important part is the `if (!isset(\XF::app['cli.logger'])) return;` line, which will
abort if our Logger is not available.

Custom Cron task debugging
--------------------------

Using a similar mechanism, we can add debugging code to our custom Cron tasks too:

Add a slightly different function to your Cron tasks to call the `log` function of our Logger class:

```php
	protected static function log($message, array $context = [])
	{
		// check to see if we actually have a logger available and abort if not
		if (!isset(\XF::app['cli.logger'])) return;

		/** @var Logger $logger */
		$logger = \XF::app['cli.logger'];
		$logger->log("XF\Job\Cron", $message, $context);
	}
```

Then, simply call something like: `self::log("some message about something happening", ['key' => 'value'])` within your
code to output information to the console when the Job Runner is executed in verbose mode.
