<?php namespace Hampel\JobRunner\Cli\Command\Legacy;

/**
 * hg:show-crons, the pre-3.0.0 name of job-monitor:show-crons. Hidden from the command list;
 * removed in a later major version.
 */
class ShowCrons extends \Hampel\JobRunner\Cli\Command\ShowCrons
{
	protected function configure()
	{
		parent::configure();

		$this
			->setName('hg:show-crons')
			->setDescription('Deprecated: use job-monitor:show-crons.')
			->setHidden(true);
	}
}
