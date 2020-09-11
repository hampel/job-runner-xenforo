<?php namespace Hampel\JobRunner\Cli\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ShowCrons extends Command
{
	protected function configure()
	{
		$this
			->setName('hg:show-crons')
			->setDescription('Show all cron entries')
			->addOption(
				'all',
				'a',
				InputOption::VALUE_NONE,
				"Include inactive cron entries"
			)
			->addOption(
				'method',
				'm',
				InputOption::VALUE_NONE,
				"Show cron method"
			)
			->addOption(
				'sort',
				's',
				InputOption::VALUE_REQUIRED,
				"Sort method (date, id, addon)"
			);
	}

	protected function execute(InputInterface $input, OutputInterface $output)
	{
		$all = $input->getOption('all');
		$showMethod = $input->getOption('method');
		$sort = $input->getOption('sort') ?? 'date';

		$finder = \XF::finder('XF:CronEntry')
		             ->with('AddOn')
		             ->whereAddOnActive();

		switch($sort) {
			case 'id':
				$finder->order(['entry_id']);
				break;
			case 'addon':
				$finder->order(['addon_id', 'entry_id']);
				break;
			default:
				$finder->order(['next_run']);
				break;
		}

		if (!$all)
		{
			$finder = $finder->where('active', 1);
		}

		$crons = $finder->fetch();
		$count = $crons->count();

		$active = $all ? "" : " active";

		$output->writeln('');
		$output->writeln("<info>{$count}{$active} cron entries found</info>");
		$output->writeln('');

		$tz = date_default_timezone_get();
		date_default_timezone_set(\XF::options()->guestTimeZone);

		$cronData = [];
		foreach ($crons as $cron)
		{
			$data = [];
			$data[] = $cron->entry_id;

			if ($showMethod)
			{
				$data[] = "{$cron->cron_class}::{$cron->cron_method}";
			}

			$data[] = $cron->next_run > 0 ? date('d-M-Y H:i', $cron->next_run) : '';
			$data[] = $cron->addon_id;

			$cronData[] = $data;
		}

		$headers = [];
		$headers[] = 'ID';
		if ($showMethod)
		{
			$headers[] = 'Method';
		}
		$headers[] = 'Next Run ' . date('(\U\T\CP)', \XF::$time);
		$headers[] = 'Addon';

		$table = new Table($output);
		$table->setHeaders($headers)->setRows($cronData)->render();

		$output->writeln('');
		$output->writeln("The current time is: " . date('d-M-Y H:i:s (\U\T\CP)', \XF::$time));
		$output->writeln('');

		date_default_timezone_set($tz);

		return 0;
	}

	/**
	 * @return \XF\Repository\CronEntry
	 */
	protected function getCronRepo()
	{
		return \XF::repository('XF:CronEntry');
	}
}