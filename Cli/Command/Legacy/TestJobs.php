<?php namespace Hampel\JobRunner\Cli\Command\Legacy;

/**
 * hg:test-jobs, the pre-3.0.0 name of job-monitor:queue-test-job. Hidden from the command list;
 * removed in a later major version.
 */
class TestJobs extends \Hampel\JobRunner\Cli\Command\QueueTestJob
{
	protected function configure()
	{
		parent::configure();

		$this
			->setName('hg:test-jobs')
			->setDescription('Deprecated: use job-monitor:queue-test-job.')
			->setHidden(true);
	}
}
