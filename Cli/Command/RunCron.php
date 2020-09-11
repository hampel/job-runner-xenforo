<?php namespace Hampel\JobRunner\Cli\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class RunCron extends Command
{
	protected function configure()
	{
		$this
			->setName('hg:run-cron')
			->setDescription('Execute a cron task')
			->addArgument(
				'id',
				InputArgument::REQUIRED,
				'ID of cron entry to execute'
			)
			->addOption(
				'force',
				'f',
				InputOption::VALUE_NONE,
				'Force run a disabled cron task'
			);
	}

	protected function execute(InputInterface $input, OutputInterface $output)
	{
		$start = microtime(true);

		$id = $input->getArgument('id');
		$force = $input->getOption('force');

		$finder = \XF::finder('XF:CronEntry')
		            ->whereAddOnActive()
		            ->where('entry_id', $id);

		if (!$force)
		{
			$finder = $finder->where('active', 1);
		}

		$entry = $finder->fetchOne();

		if (!$entry)
		{
			$active = $force ? "" : " active";

			$output->writeln("<error>Could not find{$active} cron entry with that id.</error>");
			return 1;
		}

		if (!$entry->hasCallback())
		{
			$output->writeln("<error>Cron entry does not have a callback.</error>");
			return 1;
		}

		try
		{
			call_user_func(
				[$entry['cron_class'], $entry['cron_method']],
				$entry
			);
		}
		catch (\Exception $e)
		{
			$this->app->logException($e, true);

			$output->writeln("<error>Exception running cron: " . $e->getMessage() . "</error>");
			return 1;
		}

		$output->writeln(sprintf("<info>Done - execution time: %01.2f seconds</info>", microtime(true) - $start), OutputInterface::VERBOSITY_VERBOSE);

		return 0;
	}
}