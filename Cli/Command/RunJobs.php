<?php namespace Hampel\JobRunner\Cli\Command;

use Hampel\JobRunner\Cli\LoggerTrait;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use XF\Cli\Command\CustomAppCommandInterface;

/**
 * Run the job queue by hand and watch it work.
 *
 * Runs the same queue as xf:run-jobs, which is what cron should run. This command exists for a
 * person diagnosing the queue, so it logs by default: each job and cron entry as it runs, with its
 * execution time. -v adds the context of each, -vv everything.
 */
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
			->setName('job-monitor:run-jobs')
			->setDescription('Runs outstanding jobs and due cron tasks by hand, logging each as it runs.')
			->addOption(
				'max-execution-time',
				't',
				InputOption::VALUE_REQUIRED,
				'Sets a max execution time in seconds. Use 0 to run until all jobs have completed.',
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
		$app = \XF::app();
		$jobManager = $app->jobManager();

		if (!$jobManager->canRunJobs())
		{
			$output->writeln('<error>Jobs cannot be run at this time.</error>');
			return 1;
		}

		$this->raiseVerbosity($output);
		$app['cli.output'] = $output;

		$maxRunTime = $app->config('jobMaxRunTime'); // maximum time for a single job to execute
		$maxExecutionTime = max(0, intval($input->getOption('max-execution-time'))); // 0 = until empty
		$manualOnly = $input->getOption('manual-only');

		$this->log("Run Jobs starting", compact('maxRunTime', 'maxExecutionTime', 'manualOnly'));

		$start = microtime(true);
		$more = false;

		do
		{
			$maxThisRun = $maxRunTime;

			if ($maxExecutionTime)
			{
				$remaining = $maxExecutionTime - (microtime(true) - $start);

				$this->logVeryVerbose(sprintf("Remaining time: %01.2f seconds", $remaining));

				if ($remaining < 1) break; // stop if we're out of time

				$maxThisRun = min($remaining, $maxRunTime);
			}

			$jobManager->runQueue($manualOnly, $maxThisRun);

			// keep the memory limit down on long running jobs
			$app->em()->clearEntityCache();
			\XF::updateTime();

			$more = $jobManager->queuePending($manualOnly);
		}
		while ($more);

		if ($more)
		{
			$output->writeln("<info>Maximum runtime ({$maxExecutionTime} seconds) expired with more runnable jobs pending.</info>");
		}
		else
		{
			$output->writeln(sprintf("<info>Total execution time: %01.2f seconds</info>", microtime(true) - $start), OutputInterface::VERBOSITY_VERBOSE);
			$output->writeln("<info>All outstanding jobs have run.</info>");
		}

		return 0;
	}

	/**
	 * Shift the requested verbosity up one level, so the job and cron log shows without -v. Quiet
	 * stays quiet.
	 */
	protected function raiseVerbosity(OutputInterface $output)
	{
		$raised = [
			OutputInterface::VERBOSITY_NORMAL => OutputInterface::VERBOSITY_VERBOSE,
			OutputInterface::VERBOSITY_VERBOSE => OutputInterface::VERBOSITY_VERY_VERBOSE,
			OutputInterface::VERBOSITY_VERY_VERBOSE => OutputInterface::VERBOSITY_DEBUG,
		];

		$verbosity = $output->getVerbosity();
		if (isset($raised[$verbosity]))
		{
			$output->setVerbosity($raised[$verbosity]);
		}
	}
}
