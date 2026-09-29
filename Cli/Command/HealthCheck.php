<?php namespace Hampel\JobRunner\Cli\Command;

use Hampel\JobRunner\Service\HealthCheck as HealthCheckService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Report whether the job queue and cron are running. Exits 0 when all is well, 1 on a warning and
 * 2 on a problem, so monitoring can run it as it stands.
 */
class HealthCheck extends Command
{
	const EXIT_CODES = [
		HealthCheckService::OK => 0,
		HealthCheckService::WARNING => 1,
		HealthCheckService::PROBLEM => 2,
	];

	const STYLES = [
		HealthCheckService::OK => 'info',
		HealthCheckService::WARNING => 'comment',
		HealthCheckService::PROBLEM => 'error',
	];

	protected function configure()
	{
		$this
			->setName('job-monitor:health-check')
			->setDescription('Checks that the job queue and cron are running. Exits 0 if healthy, 1 on a warning, 2 on a problem.')
			->addOption(
				'fix',
				null,
				InputOption::VALUE_NONE,
				'Queue the cron job again if it is missing - the only repair this makes'
			);
	}

	protected function execute(InputInterface $input, OutputInterface $output)
	{
		$health = $this->healthCheck();
		$results = $health->run();

		if ($input->getOption('fix') && $this->hasProblem($results, 'cron_job'))
		{
			$fixed = $health->fixCronJob();
			$output->writeln($fixed
				? '<info>Queued the cron job again.</info>'
				: '<error>Could not queue the cron job.</error>');

			$results = $health->run();
		}

		foreach ($results AS $result)
		{
			$style = self::STYLES[$result['status']];
			$output->writeln(sprintf(
				'<%s>%-7s</%s> %-19s %s',
				$style, strtoupper($result['status']), $style, $result['label'], $result['detail']
			));
		}

		return self::EXIT_CODES[$health->worstStatus($results)];
	}

	protected function hasProblem(array $results, $check)
	{
		foreach ($results AS $result)
		{
			if ($result['check'] == $check && $result['status'] == HealthCheckService::PROBLEM)
			{
				return true;
			}
		}

		return false;
	}

	/**
	 * @return HealthCheckService
	 */
	protected function healthCheck()
	{
		return \XF::app()->service(HealthCheckService::class);
	}
}
