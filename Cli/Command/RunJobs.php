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
			$maxJobRunTime = intval($app->config('jobMaxRunTime')); // maximum time for a single job to execute
			$maxQueueRunTime = intval($input->getOption('time')); // maximum time for the job runner to run jobs

			do
			{
				$jobManager->runQueue(false, $maxJobRunTime);
				$more = $jobManager->queuePending(false);

			} while ($more && (microtime(true) - $start < $maxQueueRunTime));

			if ($more)
			{
				$output->writeln("<info>Maximum runtime ({$maxQueueRunTime} seconds) expired with more runnable jobs pending</info>");
			}
			else
			{
				$output->writeln("<info>No more runnable jobs pending</info>");
			}
		}
		else
		{
			$output->writeln("<error>Version mismatch - upgrade pending?</error>");
		}

		return 1;
	}
}