<?php namespace Hampel\JobRunner\Cli\Command;

use Hampel\JobRunner\Service\HealthCheck as HealthCheckService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Report whether the job queue and cron are running.
 *
 * Exit code: 1 if any check found a problem, 0 otherwise - a warning does not fail, so a deploy or
 * a cron job can depend on it. --strict makes a warning exit 2, for a monitor that wants to hear of
 * one; it changes nothing else. This is the contract every <addon>:validate shares, and it is not
 * the monitoring-plugin numbering: 1 always means a problem.
 */
class Validate extends Command
{
	const STYLES = [
		HealthCheckService::OK => 'info',
		HealthCheckService::WARNING => 'comment',
		HealthCheckService::PROBLEM => 'error',
	];

	protected function configure()
	{
		$this
			->setName('job-monitor:validate')
			->setDescription('Checks that the job queue and cron are running. Exits 1 on a problem; --strict exits 2 on a warning.')
			->addOption(
				'strict',
				null,
				InputOption::VALUE_NONE,
				'Exit 2 when there are warnings and no problems, instead of 0'
			)
			->addOption(
				'fix',
				null,
				InputOption::VALUE_NONE,
				'Queue the cron job again if it is missing - the only repair this makes'
			)
			->addOption(
				'json',
				null,
				InputOption::VALUE_NONE,
				'Print the result as one JSON document, for monitoring'
			);
	}

	protected function execute(InputInterface $input, OutputInterface $output)
	{
		$health = $this->healthCheck();
		$results = $health->run();
		$json = $input->getOption('json');
		$strict = (bool) $input->getOption('strict');

		// null: no repair was needed or asked for
		$fixed = null;
		if ($input->getOption('fix') && $this->hasProblem($results, 'cron_job'))
		{
			$fixed = $health->fixCronJob();
			$results = $health->run();

			if (!$json)
			{
				$output->writeln($fixed
					? '<info>Queued the cron job again.</info>'
					: '<error>Could not queue the cron job.</error>');
			}
		}

		$status = $health->worstStatus($results);
		$exitCode = $this->checkExitCode($status, $strict);

		if ($json)
		{
			$output->writeln(json_encode([
				'status' => $status,
				'exit_code' => $exitCode,
				'strict' => $strict,
				'checked_at' => \XF::$time,
				'fixed_cron_job' => $fixed,
				'checks' => $results,
			], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), OutputInterface::OUTPUT_RAW);

			return $exitCode;
		}

		foreach ($results AS $result)
		{
			$style = self::STYLES[$result['status']];
			$output->writeln(sprintf(
				'<%s>%-7s</%s> %-19s %s',
				$style, strtoupper($result['status']), $style, $result['label'], $result['detail']
			));
		}

		return $exitCode;
	}

	/**
	 * A problem is 1 whatever the flag; a warning is 2 only under --strict.
	 */
	protected function checkExitCode($status, $strict)
	{
		if ($status == HealthCheckService::PROBLEM)
		{
			return 1;
		}

		return ($strict && $status == HealthCheckService::WARNING) ? 2 : 0;
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
