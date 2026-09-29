<?php namespace Hampel\JobRunner\Cron;

class FailedJobs
{
	/** failed-job records older than this many days are deleted */
	const RETAIN_DAYS = 30;

	/**
	 * Delete old failed-job records.
	 *
	 * XenForo never prunes xf_failed_job, and the add-on records every failure rather than only in
	 * debug mode, so a job failing every few minutes would otherwise grow the table without limit.
	 * XenForo 2.2 has no such table.
	 */
	public static function prune()
	{
		if (\XF::$versionId < 2030000)
		{
			return;
		}

		\XF::db()->delete('xf_failed_job', 'fail_date < ?', \XF::$time - self::RETAIN_DAYS * 86400);
	}
}
