<?php namespace Hampel\JobRunner\Cli\Command\Legacy;

/**
 * hg:run-cron, the pre-3.0.0 name of job-monitor:run-cron. Hidden from the command list; removed
 * in a later major version.
 */
class RunCron extends \Hampel\JobRunner\Cli\Command\RunCron
{
	protected function configure()
	{
		parent::configure();

		$this
			->setName('hg:run-cron')
			->setDescription('Deprecated: use job-monitor:run-cron.')
			->setHidden(true);
	}
}
