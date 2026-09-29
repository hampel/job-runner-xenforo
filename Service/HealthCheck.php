<?php namespace Hampel\JobRunner\Service;

use Hampel\JobRunner\Cli\Command\Legacy\RunJobs as LegacyRunJobs;
use XF\Service\AbstractService;

/**
 * Checks whether the job queue and cron are running, and why not when they are not.
 *
 * Read-only unless fixCronJob() is called. Each check returns a result with a status, a short
 * label and a detail line; the worst status decides the command's exit code.
 */
class HealthCheck extends AbstractService
{
	const OK = 'ok';
	const WARNING = 'warning';
	const PROBLEM = 'problem';

	/** a due job waiting longer than this, in seconds, suggests the runner is late */
	const JOB_WARNING_AGE = 300;

	/** longer than this, and the runner has stopped - XenForo's own admin warning uses an hour */
	const JOB_PROBLEM_AGE = 3600;

	/** a cron entry this far past its next run, in seconds, is not being scheduled */
	const CRON_PROBLEM_AGE = 3600;

	/** a job that failed within this long, in seconds, is reported */
	const FAILED_RECENT = 86400;

	/** hg:run-jobs used within this long means a crontab still calls it */
	const LEGACY_RECENT = 7200;

	/**
	 * @return array[] each ['check' => key, 'label' => text, 'status' => OK|WARNING|PROBLEM, 'detail' => text]
	 */
	public function run()
	{
		return [
			$this->checkTrigger(),
			$this->checkCronJobQueued(),
			$this->checkOverdueJobs(),
			$this->checkOverdueCronEntries(),
			$this->checkAdminWarning(),
			$this->checkManualJobs(),
			$this->checkFailedJobs(),
			$this->checkLegacyCrontab(),
		];
	}

	/**
	 * The worst status among the results.
	 */
	public function worstStatus(array $results)
	{
		$statuses = array_column($results, 'status');

		if (in_array(self::PROBLEM, $statuses, true))
		{
			return self::PROBLEM;
		}

		return in_array(self::WARNING, $statuses, true) ? self::WARNING : self::OK;
	}

	public function checkTrigger()
	{
		$trigger = $this->app->options()->jobRunTrigger ?? 'activity';

		if ($trigger == 'server')
		{
			return $this->result('trigger', 'Job trigger', self::OK,
				'Server based: jobs run only from cron, which should run xf:run-jobs every minute.');
		}

		return $this->result('trigger', 'Job trigger', self::OK,
			'Activity based: jobs run from page views, so a quiet forum runs them late.');
	}

	public function checkCronJobQueued()
	{
		if (!$this->activeCronEntryCount())
		{
			return $this->result('cron_job', 'Cron job queued', self::OK, 'No active cron entries.');
		}

		if ($this->cronJob())
		{
			return $this->result('cron_job', 'Cron job queued', self::OK, 'The cron job is in the queue.');
		}

		return $this->result('cron_job', 'Cron job queued', self::PROBLEM,
			'The cron job is missing from the queue, so no scheduled task will run until a cron entry '
			. 'is next saved. Run with --fix to queue it again.');
	}

	/**
	 * Queue the cron job again, the way XenForo does when a cron entry is saved.
	 *
	 * @return bool whether it is queued now
	 */
	public function fixCronJob()
	{
		/** @var \XF\Service\CronEntry\CalculateNextRun $service */
		$service = $this->service('XF:CronEntry\CalculateNextRun');
		$service->updateMinimumNextRunTime();

		return (bool) $this->cronJob();
	}

	public function checkOverdueJobs()
	{
		$oldest = intval($this->db()->fetchOne("
			SELECT MIN(trigger_date)
			FROM xf_job
			WHERE manual_execute = 0
				AND trigger_date <= ?
		", \XF::$time));

		if (!$oldest)
		{
			return $this->result('overdue_jobs', 'Overdue jobs', self::OK, 'No job is waiting to run.');
		}

		$age = \XF::$time - $oldest;
		$detail = sprintf('The oldest due job has waited %s.', $this->duration($age));

		if ($age > self::JOB_PROBLEM_AGE)
		{
			return $this->result('overdue_jobs', 'Overdue jobs', self::PROBLEM,
				$detail . ' The job runner does not appear to be running.');
		}

		if ($age > self::JOB_WARNING_AGE)
		{
			return $this->result('overdue_jobs', 'Overdue jobs', self::WARNING,
				$detail . ' The job runner is running late.');
		}

		return $this->result('overdue_jobs', 'Overdue jobs', self::OK, $detail);
	}

	public function checkOverdueCronEntries()
	{
		$overdue = $this->db()->fetchAllColumn("
			SELECT entry.entry_id
			FROM xf_cron_entry AS entry
			LEFT JOIN xf_addon AS addon ON (entry.addon_id = addon.addon_id)
			WHERE entry.active = 1
				AND (addon.addon_id IS NULL OR addon.active = 1)
				AND entry.next_run < ?
			ORDER BY entry.next_run
		", \XF::$time - self::CRON_PROBLEM_AGE);

		if (!$overdue)
		{
			return $this->result('overdue_crons', 'Overdue cron tasks', self::OK,
				'No cron task is more than an hour late.');
		}

		return $this->result('overdue_crons', 'Overdue cron tasks', self::PROBLEM, sprintf(
			'%d cron %s more than an hour late, so scheduled tasks are not running: %s.',
			count($overdue),
			count($overdue) == 1 ? 'task is' : 'tasks are',
			implode(', ', array_slice($overdue, 0, 5)) . (count($overdue) > 5 ? ', ...' : '')
		));
	}

	public function checkAdminWarning()
	{
		if ($this->app->jobManager()->hasStoppedJobs())
		{
			return $this->result('admin_warning', 'Admin warning', self::WARNING,
				'The admin control panel is showing "There are scheduled jobs outstanding which have '
				. 'not run".');
		}

		return $this->result('admin_warning', 'Admin warning', self::OK,
			'The admin control panel is not reporting stopped jobs.');
	}

	public function checkManualJobs()
	{
		if ($this->app->jobManager()->hasStoppedManualJobs())
		{
			return $this->result('manual_jobs', 'Manual jobs', self::WARNING,
				'A manually started job has not run for three minutes, such as a rebuild left '
				. 'unfinished in a browser.');
		}

		return $this->result('manual_jobs', 'Manual jobs', self::OK, 'No manually started job is stuck.');
	}

	public function checkFailedJobs()
	{
		if (\XF::$versionId < 2030000)
		{
			return $this->result('failed_jobs', 'Failed jobs', self::OK,
				'XenForo 2.2 does not record failed jobs; check the server error log.');
		}

		$classes = $this->db()->fetchPairs("
			SELECT execute_class, COUNT(*)
			FROM xf_failed_job
			WHERE fail_date >= ?
			GROUP BY execute_class
			ORDER BY COUNT(*) DESC
		", \XF::$time - self::FAILED_RECENT);

		if (!$classes)
		{
			return $this->result('failed_jobs', 'Failed jobs', self::OK, 'No job has failed in the last day.');
		}

		$described = [];
		foreach (array_slice($classes, 0, 3, true) AS $class => $count)
		{
			$described[] = "{$class} ({$count})";
		}

		return $this->result('failed_jobs', 'Failed jobs', self::WARNING, sprintf(
			'%d %s failed in the last day: %s%s. See job-monitor:show-failed-jobs.',
			array_sum($classes),
			array_sum($classes) == 1 ? 'job' : 'jobs',
			implode(', ', $described),
			count($classes) > 3 ? ', ...' : ''
		));
	}

	public function checkLegacyCrontab()
	{
		$lastUsed = intval($this->app->simpleCache()->getValue('Hampel/JobRunner', LegacyRunJobs::LAST_USED_KEY));

		if ($lastUsed && $lastUsed > \XF::$time - self::LEGACY_RECENT)
		{
			return $this->result('legacy_crontab', 'Crontab', self::WARNING,
				'hg:run-jobs ran recently. It still works, but will be removed in a later version: '
				. 'change the crontab to run xf:run-jobs.');
		}

		return $this->result('legacy_crontab', 'Crontab', self::OK, 'hg:run-jobs has not been used recently.');
	}

	protected function activeCronEntryCount()
	{
		return intval($this->db()->fetchOne("
			SELECT COUNT(*)
			FROM xf_cron_entry AS entry
			LEFT JOIN xf_addon AS addon ON (entry.addon_id = addon.addon_id)
			WHERE entry.active = 1
				AND (addon.addon_id IS NULL OR addon.active = 1)
		"));
	}

	protected function cronJob()
	{
		return $this->db()->fetchRow("SELECT * FROM xf_job WHERE unique_key = 'cron'");
	}

	protected function duration($seconds)
	{
		if ($seconds < 120)
		{
			return "{$seconds} seconds";
		}

		if ($seconds < 7200)
		{
			return floor($seconds / 60) . ' minutes';
		}

		return floor($seconds / 3600) . ' hours';
	}

	protected function result($check, $label, $status, $detail)
	{
		return ['check' => $check, 'label' => $label, 'status' => $status, 'detail' => $detail];
	}
}
