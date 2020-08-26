<?php namespace Hampel\JobRunner\Cli\Command;

use Hampel\JobRunner\Cli\LoggerTrait;
use Hampel\JobRunner\Util\Lock;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use XF\Cli\Command\CustomAppCommandInterface;

class RunJobs extends Command implements CustomAppCommandInterface
{
	use LoggerTrait;

	public static function getCustomAppClass()
	{
		return 'Hampel\JobRunner\App';
	}

	protected function configure()
	{
		$this
			->setName('hg:run-jobs')
			->setDescription('Runs any outstanding jobs with debug logging support.')
			->addOption(
				'manual-only',
				null,
				InputOption::VALUE_NONE,
				'Ensures that only manually triggered jobs are run'
			)
			->addOption(
				'time',
				't',
				InputOption::VALUE_OPTIONAL,
				"Time in seconds to limit job runner execution to (max: 900)",
				30
			);
	}

	protected function execute(InputInterface $input, OutputInterface $output)
	{
        if (\XF::$versionId > 2020000)
        {
        	$message = 'This version of Hampel/JobRunner is not compatible with XenForo v2.2 - please upgrade to v2.x of the JobRunner addon';
        	\XF::logError($message);
        	$output->writeln("<error>{$message}</error>");
        	return 1;
        }

		$app = \XF::app();
		$jobManager = $app->jobManager();

		if (!$jobManager->canRunJobs())
		{
			$output->writeln('<error>Jobs cannot be run at this time.</error>');
			return 1;
		}

		$app['cli.output'] = $output;

		$maxRunTime = $app->config('jobMaxRunTime'); // maximum time for a single job to execute
		$maxQueueRunTime = intval($input->getOption('time')); // maximum time for the job runner to run jobs
		$manualOnly = $input->getOption('manual-only');

		if ($maxQueueRunTime > 600)
		{
			// limit queue run time to 10 minutes
			$maxQueueRunTime = 600;
		}

		if (!$manualOnly && !Lock::get($maxQueueRunTime))
		{
			$output->writeln('<error>JobRunner already running.</error>');
			return 2;
		}

		$time = time();
		$this->log("Run Jobs starting", compact('maxRunTime', 'maxQueueRunTime', 'manualOnly', 'time'));

		$start = microtime(true);
		$more = false;

		$jobManager->setAllowCron(true);

		do
		{
			$queueRunTime = microtime(true) - $start; // how long we've been processing jobs
			$remaining = $maxQueueRunTime - $queueRunTime; // how long we've got left to process jobs

			$this->logVeryVerbose(sprintf("Remaining time: %01.2f seconds", $remaining));

			if ($remaining < 1) break; // stop if we're out of time

			$jobManager->runQueue($manualOnly, min($remaining, $maxRunTime));

			// keep the memory limit down on long running jobs
			$app->em()->clearEntityCache();

			$more = $jobManager->queuePending($manualOnly);
		}
		while ($more);

		if (!$manualOnly)
		{
			Lock::remove(); // remove the lock now that we're done
		}

		if ($more)
		{
			$output->writeln("<info>Maximum runtime ({$maxQueueRunTime} seconds) expired with more runnable jobs pending.</info>", OutputInterface::VERBOSITY_VERBOSE);
		}
		else
		{
			$output->writeln(sprintf("<info>Total execution time: %01.2f seconds</info>", microtime(true) - $start), OutputInterface::VERBOSITY_VERBOSE);
			$output->writeln("<info>All outstanding jobs have run.</info>");
		}

		return 0;
	}
}