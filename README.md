CLI Job Runner for XenForo 2.0
==============================

This XenForo 2.0 addon disables the browser triggered job runner and implements a CLI triggered job runner for use with 
Unix cron.

By [Simon Hampel](https://twitter.com/SimonHampel).

Requirements
------------

This addon requires PHP 5.4 or higher and only works on XenForo 2.0.x 

Installation
------------

Install as per normal addon installation.

Note: once this addon is installed and activated, scheduled tasks will no longer run - so completing the remaining 
installation steps is critical to ensure your forum continues to function normally.

First, you should test that your job runner is functioning - execute the following command from your CLI:

	:::bash
	$ php <path to your forum root>/cmd.php xf:run-jobs

For example, if your forum root is `/srv/www/xenforo/community`, then the job runner command would be:

	:::bash
	$ php /srv/www/xenforo/community/cmd.php xf:run-jobs

Running this command will execute any outstanding jobs and then finish with a message about whether there are more jobs
waiting to be executed or not. When executing this command from cron, it is recommended that you use the `--quiet` 
(or `-q`) flag to suppress output. 

Once you are happy that the job runner functions, you will need to create your own cron task to run it on a schedule of 
your choosing.

It is highly recommended that you have your cron task run as the web server user to prevent potential permission 
problems.

For example, on Ubuntu with a web server user of www-data, install a cron task by running the following command:

    :::bash
    $ sudo crontab -u www-data -e
    
Edit the crontab file and add:

    :::bash
    *       *       *       *       *       php <path to your forum root>/cmd.php --quiet xf:run-jobs
   
Save the crontab.

This will execute the job runner every minute, checking for outstanding jobs to be run.

By default, the job runner will run for a maximum of 30 seconds, executing any outstanding jobs until there are no more
runnable jobs in the queue.

Configuration
-------------

You may adjust the maximum execution time of the job runner by specifying the `--time=[TIME]` option on the command 
line.

For example, to allow the job runner to execute for a maximum of 45 seconds:

	:::bash
	$ php <path to your forum root>/cmd.php --time=45 xf:run-jobs

It is not recommended that you allow the job runner to run for longer than the period between cron triggers. For
example, the above cron task example will execute the job runner every minute, so setting the maximum run time to more
then 60 is generally a bad idea.  

If you prefer to not run the cron task as frequently as once per minute, you can adjust the cron job as 
required and if you do, you may also want to allow the job runner task to run for longer than the default 30 seconds to
ensure that all outstanding work is completed.

For example, to run the cron task every 5 minutes, allowing the job runner to execute for a maximum of 4 minutes, use
the following cron command:

    :::bash
    */5       *       *       *       *       php <path to your forum root>/cmd.php --quiet --time=240 xf:run-jobs

For further customisation of your job execution, you may also adjust the maximum time that each job is permitted to run.
This is configured via a [XenForo Config Option](https://xenforo.com/xf2-docs/manual/config/#other-variables):
 
	:::php
	$config[jobMaxRunTime'] = 8;

The `jobMaxRunTime` option configures the amount of time in seconds that processing jobs will be allowed to run before 
they are suspended for further processing on another go-around, if possible. The default setting is optimised for the 
browser-triggered job runner and so to allow jobs to execute longer in a CLI environment, you may want to adjust this
to a higher value. 

You should not set jobMaxRunTime to anything higher than 30 seconds, or the time specified by the --time option - and
 in general it is suggested that this be kept to a relatively small value to prevent a single job from preventing other
 jobs from executing in a timely manner. Some experimentation may be required to find the optimal value for your server
 load.

 