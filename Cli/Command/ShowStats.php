<?php namespace Hampel\JobRunner\Cli\Command;

use Hampel\JobRunner\Cron\Prune;
use Hampel\JobRunner\Repository\Stats as StatsRepository;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ShowStats extends Command
{
	protected function configure()
	{
		$this
			->setName('job-monitor:show-stats')
			->setDescription('Shows how often each job and cron task ran and how long it took, or one hour by hour.')
			->addOption(
				'hours',
				null,
				InputOption::VALUE_REQUIRED,
				'How far back to look, in hours',
				24
			)
			->addOption(
				'type',
				null,
				InputOption::VALUE_REQUIRED,
				'Only "job" or only "cron"'
			)
			->addOption(
				'sort',
				's',
				InputOption::VALUE_REQUIRED,
				'Sort by total time, runs, max or avg',
				'time'
			)
			->addOption(
				'limit',
				'l',
				InputOption::VALUE_REQUIRED,
				'How many to show',
				20
			)
			->addOption(
				'name',
				null,
				InputOption::VALUE_REQUIRED,
				'Show one job class or cron entry id hour by hour'
			);
	}

	protected function execute(InputInterface $input, OutputInterface $output)
	{
		$type = $input->getOption('type');
		if ($type !== null && !in_array($type, [StatsRepository::TYPE_JOB, StatsRepository::TYPE_CRON], true))
		{
			$output->writeln('<error>--type must be "job" or "cron".</error>');
			return 1;
		}

		$hours = max(1, intval($input->getOption('hours')));
		$since = $this->statsRepo()->period(\XF::$time - $hours * 3600);

		$tz = date_default_timezone_get();
		date_default_timezone_set(\XF::options()->guestTimeZone);

		try
		{
			if ($name = $input->getOption('name'))
			{
				return $this->showHistory($output, $name, $type, $since, $hours);
			}

			return $this->showSummary(
				$output, $since, $hours, $type, $input->getOption('sort'), max(1, intval($input->getOption('limit')))
			);
		}
		finally
		{
			date_default_timezone_set($tz);
		}
	}

	protected function showSummary(OutputInterface $output, $since, $hours, $type, $sort, $limit)
	{
		$rows = $this->statsRepo()->summary($since, $type, $sort, $limit);

		if (!$rows)
		{
			$output->writeln("<info>Nothing has run in the last {$hours} hours.</info>");
			return 0;
		}

		$tableRows = [];
		foreach ($rows AS $row)
		{
			$tableRows[] = [
				$row['type'],
				$row['name'],
				$row['runs'],
				$this->duration($row['total_ms']),
				$this->duration($row['avg_ms']),
				$this->duration($row['max_ms']),
				$row['failures'] ?: '',
			];
		}

		$output->writeln('');
		$output->writeln("<info>The last {$hours} hours, by " . $this->sortLabel($sort) . "</info>");
		$output->writeln('');

		$table = new Table($output);
		$table->setHeaders(['Type', 'Name', 'Runs', 'Total', 'Avg', 'Max', 'Failed'])
			->setRows($tableRows)
			->render();

		$output->writeln('');
		$output->writeln(sprintf('Use --name for one hour by hour. Statistics are kept for %d days.', Prune::STATS_DAYS));
		$output->writeln('');

		return 0;
	}

	protected function showHistory(OutputInterface $output, $name, $type, $since, $hours)
	{
		$types = $type ? [$type] : [StatsRepository::TYPE_JOB, StatsRepository::TYPE_CRON];

		foreach ($types AS $candidate)
		{
			$rows = $this->statsRepo()->history($candidate, $name, $since);
			if ($rows)
			{
				break;
			}
		}

		if (!$rows)
		{
			$output->writeln("<info>[{$name}] has not run in the last {$hours} hours.</info>");
			return 0;
		}

		$tableRows = [];
		foreach ($rows AS $row)
		{
			$tableRows[] = [
				date('d-M-Y H:00', $row['period_start']),
				$row['runs'],
				$this->duration($row['total_ms']),
				$this->duration($row['runs'] ? $row['total_ms'] / $row['runs'] : 0),
				$this->duration($row['max_ms']),
				$row['failures'] ?: '',
			];
		}

		$output->writeln('');
		$output->writeln("<info>{$candidate} [{$name}], the last {$hours} hours</info>");
		$output->writeln('');

		$table = new Table($output);
		$table->setHeaders(['Hour ' . date('(\U\T\CP)', \XF::$time), 'Runs', 'Total', 'Avg', 'Max', 'Failed'])
			->setRows($tableRows)
			->render();

		$output->writeln('');

		return 0;
	}

	protected function duration($milliseconds)
	{
		$milliseconds = floatval($milliseconds);

		if ($milliseconds < 1000)
		{
			return sprintf('%dms', round($milliseconds));
		}

		if ($milliseconds < 120000)
		{
			return sprintf('%.2fs', $milliseconds / 1000);
		}

		return sprintf('%.1fm', $milliseconds / 60000);
	}

	protected function sortLabel($sort)
	{
		$labels = ['runs' => 'runs', 'max' => 'slowest run', 'avg' => 'average time'];

		return $labels[$sort] ?? 'total time';
	}

	/**
	 * @return StatsRepository
	 */
	protected function statsRepo()
	{
		return \XF::app()->repository(StatsRepository::class);
	}
}
