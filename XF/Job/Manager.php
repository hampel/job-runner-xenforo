<?php namespace Hampel\JobRunner\XF\Job;

use Hampel\JobRunner\Cli\LoggerTrait;
use XF\Job\JobResult;

class Manager extends XFCP_Manager
{
	use LoggerTrait;

	public function runQueue($manual, $maxRunTime)
	{
		$this->logVeryVerbose("Running job queue", ['maxRunTime' => $maxRunTime]);

		return parent::runQueue($manual, $maxRunTime);
	}

	public function getRunnable($manual)
	{
		$runnable = parent::getRunnable($manual);

		if (empty($runnable))
		{
			$this->logVeryVerbose("getRunnable: No runnable jobs found");
		}
		else
		{
			$this->logVeryVerbose("getRunnable: Getting list of runnable jobs", array_slice($runnable, 0, 25));
		}

		return $runnable;
	}

	public function queuePending($manual)
	{
		$this->debug("queuePending - counting runnable jobs");

		return parent::queuePending($manual);
	}

	/**
	 * @param array $job
	 * @param int $maxRunTime
	 *
	 * @return JobResult
	 */
	public function runJobEntry(array $job, $maxRunTime)
	{
		$this->debug("runJobEntry");

		return parent::runJobEntry($job, $maxRunTime);
	}

	protected function runJobInternal(array $job, $maxRunTime)
	{
		$this->debug("runJobInternal");

		if (!$logger = $this->getLogger()) return parent::runJobInternal($job, $maxRunTime);
		$logger->logJobStart(self::class, $job);

		$jobResult = parent::runJobInternal($job, $maxRunTime);

		$logger->logJobCompletion(self::class, $job['execute_class'], $jobResult);

		return $jobResult;
	}
}
