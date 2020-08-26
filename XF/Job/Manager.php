<?php namespace Hampel\JobRunner\XF\Job;

use Hampel\JobRunner\Cli\LoggerTrait;
use XF\Job\JobResult;

class Manager extends XFCP_Manager
{
	use LoggerTrait;

	protected $allowCron = false;

	public function setAllowCron($value)
	{
		/**
		 * Set this to true to allow cron tasks to be included in getRunnable
		 */
		$this->allowCron = $value;
	}

	public function canRunJobs() : bool
	{
		return (\XF::$versionId == $this->app->options()->currentVersionId || !$this->app->config('checkVersion'));
	}

	public function runQueue($manual, $maxRunTime)
	{
		$this->logVeryVerbose("Running job queue", ['maxRunTime' => $maxRunTime]);

		return parent::runQueue($manual, $maxRunTime);
	}

	public function getRunnable($manual)
	{
		if ($manual || (!empty($this->app->options()->hgJobRunTrigger) && $this->app->options()->hgJobRunTrigger == 'activity'))
		{
			return parent::getRunnable($manual);
		}

		// Make sure Crons only run if we're running from CLI job runner
		if ($this->allowCron)
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

		// get Runnable jobs excluding cron jobs
		return $this->db->fetchAll("
			SELECT *
			FROM xf_job
			WHERE trigger_date <= ?
				AND manual_execute = ?
				AND unique_key != 'cron'
			ORDER BY trigger_date
			LIMIT 1000
		", [\XF::$time, $manual ? 1 : 0]);
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

	/**
	 * Over-ride default functionality for setting autoJobRun to avoid database queries
	 */
	public function updateNextRunTime()
	{
		$this->debug("updateNextRunTime");

		if (!empty($this->app->options()->hgJobRunTrigger) && $this->app->options()->hgJobRunTrigger == 'activity')
		{
			return parent::updateNextRunTime();
		}

		/**
		 * if we're doing server based job triggers - just return the current time
		 *
		 * we want this to be a valid response so things don't fall over unexpectedly, but we don't want to do any
		 * database queries
		 */
		return \XF::$time;
	}
}
