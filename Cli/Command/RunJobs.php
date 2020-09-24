<?php namespace Hampel\JobRunner\Cli\Command;

use Hampel\JobRunner\Cli\LoggerTrait;
use Hampel\JobRunner\SubContainer\JobRunner;
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
				'time',
				't',
				InputOption::VALUE_REQUIRED,
				'Sets a max execution time in seconds (max: 900)',
				55
			)
			->addOption(
				'manual-only',
				null,
				InputOption::VALUE_NONE,
				'Ensures that only manually triggered jobs are run'
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
		$jobRunner = $this->getJobRunner();

		if (!$jobManager->canRunJobs())
		{
			$output->writeln('<error>Jobs cannot be run at this time.</error>');
			return 1;
		}

		$app['cli.output'] = $output;

		$maxRunTime = $app->config('jobMaxRunTime'); // maximum time for a single job to execute
		$maxQueueRunTime = $jobRunner->getMaxQueueRunTime(intval($input->getOption('time'))); // maximum time for the job runner to run jobs
		$manualOnly = $input->getOption('manual-only');

		if (!$manualOnly && !$jobRunner->getLock())
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
			$jobRunner->removeLock(); // remove the lock now that we're done
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

	/**
	 * @return JobRunner
	 */
	protected function getJobRunner()
	{
		return \XF::app()->get('job.hg.runner');
	}
}