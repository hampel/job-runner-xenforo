<?php namespace Hampel\JobRunner\Cli;

use Symfony\Component\Console\Output\OutputInterface;
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
	 * @param float|null $startTime - pass `microtime(true)` at start of execution
	 */
	public function setJobStartTime($startTime = null)
	{
		$this->startTime = $startTime ?: microtime(true);
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

	public function logJobCompletion(JobResult $jobResult, array $job = [])
	{
		if (!$this->output || !$this->output->isVerbose()) return;

		$executionTime = $this->startTime ? microtime(true) - $this->startTime : 0;

		$message = sprintf("Job executed in %01.2f seconds", $executionTime);

		$context = [
			'completed' => $jobResult->completed,
			'jobId' => $jobResult->jobId,
			'continueDate' => $jobResult->continueDate,
			'continueDate_formatted' => $jobResult->continueDate ? date("Y-m-d H:i:s T", $jobResult->continueDate) : "",
			'statusMessage' => $jobResult->statusMessage,
		];

		if (!empty($job))
		{
			$job['execute_data'] = unserialize($job['execute_data']);
			$job['trigger_date_formatted'] = date("Y-m-d H:i:s T", $job['trigger_date']);
			$job['last_run_date_formatted'] = $job['last_run_date'] ? date("Y-m-d H:i:s T", $job['last_run_date']) : "";
		}

		$this->log($job['execute_class'], $message, $context, $job);
	}

	public function log($class, $message = '', array $context = [], array $extra = [], $verbosity = OutputInterface::VERBOSITY_VERBOSE)
	{
		if (!$this->output || !$this->output->isVerbose()) return;

		$date = date("[Y-m-d H:i:s]", \XF::$time);
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
		$this->output->writeln('', OutputInterface::VERBOSITY_VERY_VERBOSE);
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
