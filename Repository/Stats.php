<?php namespace Hampel\JobRunner\Repository;

use Hampel\JobRunner\Setup;
use XF\Mvc\Entity\Repository;

/**
 * Hourly statistics for every job and cron entry that runs, whichever runner runs it.
 *
 * One row per job class or cron entry per hour, upserted on each run. Recording must never break
 * the job being measured, so every write swallows its own failure - including the table not
 * existing yet, which is the state during an upgrade from before 3.0.0, when the new code runs
 * before Setup has created it.
 */
class Stats extends Repository
{
	const TYPE_JOB = 'job';
	const TYPE_CRON = 'cron';

	public function recordRun($type, $name, $milliseconds)
	{
		$milliseconds = max(0, intval(round($milliseconds)));

		$this->write("
			INSERT INTO " . Setup::STATS_TABLE . "
				(type, name, period_start, runs, total_ms, max_ms, failures)
			VALUES
				(?, ?, ?, 1, ?, ?, 0)
			ON DUPLICATE KEY UPDATE
				runs = runs + 1,
				total_ms = total_ms + VALUES(total_ms),
				max_ms = GREATEST(max_ms, VALUES(max_ms))
		", [$type, $this->name($name), $this->period(), $milliseconds, $milliseconds]);
	}

	public function recordFailure($type, $name)
	{
		$this->write("
			INSERT INTO " . Setup::STATS_TABLE . "
				(type, name, period_start, runs, total_ms, max_ms, failures)
			VALUES
				(?, ?, ?, 0, 0, 0, 1)
			ON DUPLICATE KEY UPDATE
				failures = failures + 1
		", [$type, $this->name($name), $this->period()]);
	}

	/**
	 * Totals per job class or cron entry since a time, busiest first by default.
	 *
	 * @param string|null $type TYPE_JOB, TYPE_CRON or null for both
	 * @param string $sort 'time' (total), 'runs', 'max' or 'avg'
	 */
	public function summary($since, $type = null, $sort = 'time', $limit = 20)
	{
		$orders = [
			'time' => 'total_ms DESC',
			'runs' => 'runs DESC',
			'max' => 'max_ms DESC',
			'avg' => 'avg_ms DESC',
		];
		$order = $orders[$sort] ?? $orders['time'];

		$params = [$since];
		$typeClause = '';
		if ($type)
		{
			$typeClause = 'AND type = ?';
			$params[] = $type;
		}

		return $this->db()->fetchAll("
			SELECT type, name,
				SUM(runs) AS runs,
				SUM(total_ms) AS total_ms,
				MAX(max_ms) AS max_ms,
				SUM(failures) AS failures,
				IF(SUM(runs) > 0, SUM(total_ms) / SUM(runs), 0) AS avg_ms
			FROM " . Setup::STATS_TABLE . "
			WHERE period_start >= ?
				{$typeClause}
			GROUP BY type, name
			ORDER BY {$order}, name
			LIMIT " . max(1, intval($limit)),
			$params
		);
	}

	/**
	 * One job class or cron entry, hour by hour, oldest first.
	 */
	public function history($type, $name, $since)
	{
		return $this->db()->fetchAll("
			SELECT period_start, runs, total_ms, max_ms, failures
			FROM " . Setup::STATS_TABLE . "
			WHERE type = ?
				AND name = ?
				AND period_start >= ?
			ORDER BY period_start
		", [$type, $this->name($name), $since]);
	}

	/**
	 * The start of the current hour.
	 */
	public function period($time = null)
	{
		$time = $time === null ? \XF::$time : $time;

		return $time - ($time % 3600);
	}

	protected function name($name)
	{
		return substr((string) $name, 0, 100);
	}

	protected function write($sql, array $params)
	{
		try
		{
			$this->db()->query($sql, $params);
		}
		catch (\Throwable $e)
		{
			// never let measuring a job break it
		}
	}
}
