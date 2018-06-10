<?php namespace JobRunner\Cli\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class RunJobs extends Command
{
	protected function configure()
	{
		$this
			->setName('xf:run-jobs')
			->setDescription('Run pending jobs in the job queue')
			->addOption(
				'time',
				null,
				InputOption::VALUE_OPTIONAL,
				"Time in seconds to limit job runner execution to",
				30
			);
	}

	protected function execute(InputInterface $input, OutputInterface $output)
	{
		$app = \XF::app();
		$start = microtime(true);

		if (\XF::$versionId == $app->options()->currentVersionId)
		{
			$jobManager = $app->jobManager();
			$maxRunTime = $app->config('jobMaxRunTime');

			do
			{
				$jobManager->runQueue(false, $maxRunTime);
				$more = $jobManager->queuePending(false);

			} while ($more && (microtime(true) - $start < intval($input->getOption('time'))));

			if ($more)
			{
				$output->writeln("<info>More jobs pending</info>");
			}
			else
			{
				$output->writeln("<info>No more jobs pending</info>");
			}
		}
		else
		{
			$output->writeln("<error>Version mismatch - upgrade pending?</error>");
		}

		return 1;
	}
}