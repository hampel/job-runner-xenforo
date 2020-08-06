<?php namespace Hampel\JobRunner\Cli;

use Symfony\Component\Console\Output\OutputInterface;
use XF\Entity\CronEntry;
use XF\Job\AbstractJob;
use XF\Job\JobResult;

class Logger
{
	/** @var OutputInterface $output */
	protected $output;

	/** @var float $startTime */
	protected $startTime;

	public function __construct(OutputInterface $output)
	{
		$this->output = $output;
	}

	/**
	 * @param array job - the job we're starting
	 * @param float|null $startTime - pass `microtime(true)` at start of execution
	 */
	public function logJobStart($class, array $job, $startTime = null)
	{
		$this->startTime = $startTime ?: microtime(true);

		if (!$this->output || !$this->output->isVerbose()) return;

		if (!empty($job))
		{
			$job['execute_data'] = unserialize($job['execute_data']);
			$job['trigger_date_formatted'] = date("Y-m-d H:i:s T", $job['trigger_date']);
			$job['last_run_date_formatted'] = $job['last_run_date'] ? date("Y-m-d H:i:s T", $job['last_run_date']) : "";

			$this->log($class, "Starting Job [{$job['execute_class']}]", $job);
		}
	}

	public function logJobProgress($message, array $context = [], AbstractJob $job, $verbosity = OutputInterface::VERBOSITY_DEBUG)
	{
		if (!$this->output || !$this->output->isVerbose()) return;

		$executionTime = $this->startTime ? microtime(true) - $this->startTime : 0;

		$jobClass = get_class($job);

		if (empty($message))
		{
			if ($executionTime > 0)
			{
				$message = sprintf("Job progress %01.2f seconds elapsed", $executionTime);
			}
			else
			{
				$message = "Job progress";
			}
		}

		$extra = [
			'job_id' => $job->getJobId(),
			'class' => $jobClass,
			'status_message' => $job->getStatusMessage(),
			'data' => $job->getData(),
		];

		if ($executionTime > 0)
		{
			$extra['execution_time'] = number_format($executionTime, 2);
		}

		$this->log(
			$this->classToString($jobClass, 'Job'),
			$message,
			$context,
			$extra,
			$verbosity
		);
	}

	public function logJobCompletion($class, $jobClass, JobResult $jobResult)
	{
		if (!$this->output || !$this->output->isVerbose()) return;

		$executionTime = $this->startTime ? microtime(true) - $this->startTime : 0;

		$message = sprintf("Job [{$jobClass}] executed in %01.2f seconds", $executionTime);

		$context = [
			'completed' => $jobResult->completed,
			'jobId' => $jobResult->jobId,
			'continueDate' => $jobResult->continueDate,
			'continueDate_formatted' => $jobResult->continueDate ? date("Y-m-d H:i:s T", $jobResult->continueDate) : "",
			'statusMessage' => $jobResult->statusMessage,
		];

		$this->log($class, $message, $context);
	}

	public function logCronStart($class, CronEntry $cronEntry)
	{
		if (!$this->output || !$this->output->isVerbose()) return;

		$context = $cronEntry->toArray();
		$context['next_run_formatted'] = date("Y-m-d H:i:s T", $context['next_run']);
		$this->log($class, "Executing cron entry [{$cronEntry['entry_id']}]", $context);
	}

	public function log($class, $message = '', array $context = [], array $extra = [], $verbosity = OutputInterface::VERBOSITY_VERBOSE)
	{
		if (!$this->output || !$this->output->isVerbose()) return;

		$date = date("[Y-m-d H:i:s]");
		$context = json_encode($context, JSON_FORCE_OBJECT);
		$extra = json_encode($extra, JSON_FORCE_OBJECT);

		$log = "{$date} {$class}: {$message}";

		if ($this->output->isVeryVerbose())
		{
			$log .= " {$context}";
		}
		if ($this->output->isDebug())
		{
			$log .= " {$extra}";
		}

		$this->output->writeln($log, $verbosity);
		$this->output->writeln('', $verbosity);
	}

	public function debug($class, $message = '', array $context = [])
	{
		$this->log($class, $message, $context, [], OutputInterface::VERBOSITY_DEBUG);
	}

	public function isVeryVerbose()
	{
		return $this->output && $this->output->isVeryVerbose();
	}

	protected function classToString($class, $type)
	{
		$parts = explode("\\{$type}\\", $class);
		if (count($parts) != 2)
		{
			// already a class
			return $class;
		}

		return "{$parts[0]}:{$parts[1]}";
	}
}
