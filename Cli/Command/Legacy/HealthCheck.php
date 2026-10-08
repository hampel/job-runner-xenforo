<?php namespace Hampel\JobRunner\Cli\Command\Legacy;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * job-monitor:health-check, the 3.0.x name of job-monitor:validate. It does not run the checks: the
 * two commands give exit codes 1 and 2 opposite meanings, so anything still calling this one would
 * misread the answer. It fails instead, and says where to go.
 *
 * It exists because XenForo exits 0 for a command that is not defined, which a monitor reads as
 * healthy. Hidden from the command list; removed in a later major version.
 */
class HealthCheck extends Command
{
	protected function configure()
	{
		$this
			->setName('job-monitor:health-check')
			->setDescription('Removed: use job-monitor:validate.')
			->setHidden(true)
			->addOption('fix', null, InputOption::VALUE_NONE, 'Ignored')
			->addOption('json', null, InputOption::VALUE_NONE, 'Ignored');
	}

	protected function execute(InputInterface $input, OutputInterface $output)
	{
		$output->writeln(
			'<error>job-monitor:health-check has been replaced by job-monitor:validate, whose exit '
			. 'codes differ: 1 is a problem, and --strict makes a warning 2. Nothing was checked.</error>'
		);

		return 1;
	}
}
