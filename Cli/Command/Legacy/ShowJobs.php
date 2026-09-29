<?php namespace Hampel\JobRunner\Cli\Command\Legacy;

/**
 * hg:show-jobs, the pre-3.0.0 name of job-monitor:show-jobs. Hidden from the command list; removed
 * in a later major version.
 */
class ShowJobs extends \Hampel\JobRunner\Cli\Command\ShowJobs
{
	protected function configure()
	{
		parent::configure();

		$this
			->setName('hg:show-jobs')
			->setDescription('Deprecated: use job-monitor:show-jobs.')
			->setHidden(true);
	}
}
