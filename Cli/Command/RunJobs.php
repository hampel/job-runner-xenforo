<?php namespace Hampel\JobRunner\Cli\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use XF\Cli\Command\CustomAppCommandInterface;

class RunJobs extends Command implements CustomAppCommandInterface
{
	public static function getCustomAppClass()
	{
		return 'Hampel\JobRunner\App';
	}

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
		$app['cli.output'] = $output;

		$start = microtime(true);

		if (\XF::$versionId == $app->options()->currentVersionId || !\XF::config('checkVersion'))
		{
			$jobManager = $app->jobManager();
			$maxJobRunTime = intval($app->config('jobMaxRunTime')); // maximum time for a single job to execute
			$maxQueueRunTime = intval($input->getOption('time')); // maximum time for the job runner to run jobs

			$jobManager->setAllowCron(true);

			do
			{
				$jobManager->runQueue(false, $maxJobRunTime);
				$more = $jobManager->queuePending(false);

				// limit overall memory usage by cleaning up cached entities
				$app->em()->clearEntityCache();

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

		return 0;
	}
}