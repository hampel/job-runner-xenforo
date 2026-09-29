<?php namespace Hampel\JobRunner\Cron;

class Prune
{
	/** failed-job records older than this many days are deleted */
	const FAILED_JOB_DAYS = 30;

	/** hourly job and cron statistics older than this many days are deleted */
	const STATS_DAYS = 90;

	/**
	 * Daily: delete old failed-job records and statistics, which would otherwise grow without limit.
	 */
	public static function prune()
	{
		static::pruneFailedJobs();
		static::pruneStats();
	}

	/**
	 * XenForo never prunes xf_failed_job, and the add-on records every failure rather than only in
	 * debug mode. XenForo 2.2 has no such table.
	 */
	public static function pruneFailedJobs()
	{
		if (\XF::$versionId < 2030000)
		{
			return;
		}

		\XF::db()->delete('xf_failed_job', 'fail_date < ?', \XF::$time - self::FAILED_JOB_DAYS * 86400);
	}

	public static function pruneStats()
	{
		\XF::db()->delete('xf_hampel_jobrunner_stat', 'period_start < ?', \XF::$time - self::STATS_DAYS * 86400);
	}
}
