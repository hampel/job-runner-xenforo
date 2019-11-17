<?php namespace Hampel\JobRunner\XF\Job;

class Manager extends XFCP_Manager
{
	protected $allowCron = false;

	public function setAllowCron($value)
	{
		/**
		 * Set this to true to allow cron tasks to be included in getRunnable
		 */
		$this->allowCron = $value;
	}

	public function getRunnable($manual)
	{
		/**
		 * Don't allow cron tasks to execute under normal conditions - unless we explicitly allow them (ie via CLI job runner)
		 */
		if ($manual || $this->allowCron)
		{
			return parent::getRunnable($manual);
		}

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

	public function updateNextRunTime()
	{
		/**
		 * just return the current time, we're actively ignoring the next run time for jobs
		 *
		 * we want this to be a valid response so things don't fall over unexpectedly, but we don't want to do any
		 * database queries
		 */
		return \XF::$time;
	}
}
