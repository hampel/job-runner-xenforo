<?php namespace Hampel\JobRunner\Cli\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ShowJobs extends Command
{
	protected function configure()
	{
		$this
			->setName('hg:show-jobs')
			->setDescription('Show pending jobs in the job queue')
			->addOption(
				'all',
				'a',
				InputOption::VALUE_NONE,
				"Show all pending jobs (default - show only 100)"
			);
	}

	protected function execute(InputInterface $input, OutputInterface $output)
	{
		$app = \XF::app();

		$all = $input->getOption('all');
		if ($all)
		{
			$limit = '';
		}
		else
		{
			$limit = "LIMIT 100";
		}

		$total = $app->db()->fetchRow("
			SELECT COUNT(job_id) AS job_count
			FROM xf_job
		");

		$count = $total['job_count'];

		if ($count == 0)
		{
			$output->writeln("<info>No pending jobs found</info>");
			return 0;
		}

		$output->writeln('');
		$output->writeln("<info>{$count} pending jobs found</info>");
		$output->writeln('');

		$tz = date_default_timezone_get();
		date_default_timezone_set(\XF::options()->guestTimeZone);

		$jobs = $app->db()->fetchAll("
			SELECT *
			FROM xf_job
			ORDER BY trigger_date
			{$limit}
		");

		$jobData = [];
		foreach ($jobs as $job)
		{
			$jobData[] = [
				$job['unique_key'],
				$job['execute_class'],
				$job['trigger_date'] > 0 ? date('d-M-Y H:i:s', $job['trigger_date']) : '',
				intval($job['last_run_date']) > 0 ? date('d-M-Y H:i:s', $job['last_run_date']) : ''
			];
		}

		$table = new Table($output);
		$table->setHeaders(['Key', 'Class', 'Next Run', 'Last Run'])
			->setRows($jobData);
		$table->render();

		$output->writeln('');
		$output->writeln("The current time is: " . date('d-M-Y H:i:s (\U\T\CP)', \XF::$time));
		$output->writeln(" autoJobRun time is: " . date('d-M-Y H:i:s (\U\T\CP)', $app['job.runTime']), OutputInterface::VERBOSITY_VERBOSE);
		$output->writeln('');

		if (!$all && $count > 100)
		{
			$output->writeln("<comment>Note: 100 jobs shown, use --all flag to show all pending jobs.</comment>");
			$output->writeln('');
		}

		date_default_timezone_set($tz);

		return 0;
	}
}