<?php namespace Hampel\JobRunner\Cli\Command;

use Hampel\JobRunner\Cron\Prune;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ShowFailedJobs extends Command
{
	protected function configure()
	{
		$this
			->setName('job-monitor:show-failed-jobs')
			->setDescription('Shows jobs that failed, newest first. XenForo 2.3 or later.')
			->addOption(
				'limit',
				'l',
				InputOption::VALUE_REQUIRED,
				'How many to show',
				20
			)
			->addOption(
				'id',
				null,
				InputOption::VALUE_REQUIRED,
				'Show one failed job in full: its exception and the data it ran with'
			);
	}

	protected function execute(InputInterface $input, OutputInterface $output)
	{
		if (\XF::$versionId < 2030000)
		{
			$output->writeln('<comment>XenForo 2.2 does not record failed jobs: a job that throws is logged to the server error log and treated as complete.</comment>');
			return 0;
		}

		$db = \XF::db();

		$tz = date_default_timezone_get();
		date_default_timezone_set(\XF::options()->guestTimeZone);

		try
		{
			if ($id = intval($input->getOption('id')))
			{
				return $this->showOne($output, $db->fetchRow('SELECT * FROM xf_failed_job WHERE failed_job_id = ?', $id), $id);
			}

			return $this->showList($output, max(1, intval($input->getOption('limit'))));
		}
		finally
		{
			date_default_timezone_set($tz);
		}
	}

	protected function showList(OutputInterface $output, $limit)
	{
		$db = \XF::db();
		$total = intval($db->fetchOne('SELECT COUNT(*) FROM xf_failed_job'));

		if (!$total)
		{
			$output->writeln('<info>No failed jobs recorded.</info>');
			return 0;
		}

		$rows = $db->fetchAll("
			SELECT failed_job_id, execute_class, exception, fail_date
			FROM xf_failed_job
			ORDER BY failed_job_id DESC
			LIMIT " . intval($limit)
		);

		$tableRows = [];
		foreach ($rows AS $row)
		{
			$tableRows[] = [
				$row['failed_job_id'],
				date('d-M-Y H:i:s', $row['fail_date']),
				$row['execute_class'],
				$this->firstLine($row['exception']),
			];
		}

		$output->writeln('');
		$output->writeln(sprintf(
			'<info>%d failed %s recorded, showing the newest %d</info>',
			$total, $total == 1 ? 'job' : 'jobs', count($rows)
		));
		$output->writeln('');

		$table = new Table($output);
		$table->setHeaders(['ID', 'Failed ' . date('(\U\T\CP)', \XF::$time), 'Job', 'Exception'])
			->setRows($tableRows)
			->render();

		$output->writeln('');
		$output->writeln(sprintf(
			'Use --id to see one in full. Records older than %d days are pruned daily.',
			Prune::FAILED_JOB_DAYS
		));
		$output->writeln('');

		return 0;
	}

	protected function showOne(OutputInterface $output, $row, $id)
	{
		if (!$row)
		{
			$output->writeln("<error>No failed job with id {$id}.</error>");
			return 1;
		}

		$data = @unserialize($row['execute_data']);

		$output->writeln('');
		$output->writeln("<info>Failed job {$row['failed_job_id']}</info>");
		$output->writeln('Job:    ' . $row['execute_class']);
		$output->writeln('Failed: ' . date('d-M-Y H:i:s (\U\T\CP)', $row['fail_date']));
		$output->writeln('Data:   ' . json_encode($data === false ? $row['execute_data'] : $data, JSON_UNESCAPED_SLASHES));
		$output->writeln('');
		$output->writeln($row['exception']);
		$output->writeln('');

		return 0;
	}

	protected function firstLine($text)
	{
		$line = strtok((string) $text, "\n");

		return strlen($line) > 70 ? substr($line, 0, 67) . "..." : $line;
	}
}
