<?php namespace Hampel\JobRunner\XF\Service\CronEntry;

use Hampel\JobRunner\Cli\LoggerTrait;

/**
 * Per-entry cron logging, without copying XenForo's Cron::run().
 *
 * Cron::run() builds one of these services per pass, calls updateCronRunTimeAtomic() immediately
 * before each entry's callback, and getMinimumNextRunTime() once the loop ends. So an entry's run
 * lasts from its claim to the next claim, or to the end of the pass, and both ends pass through
 * here.
 */
class CalculateNextRun extends XFCP_CalculateNextRun
{
	use LoggerTrait;

	/** @var array|null entry_id and start time of the entry now running, when one is */
	protected $runningCronEntry = null;

	public function updateCronRunTimeAtomic(\XF\Entity\CronEntry $entry)
	{
		$this->debug("updateCronRunTimeAtomic");

		// claiming the next entry means the previous one has finished
		$this->logCronEntryFinished();

		$claimed = parent::updateCronRunTimeAtomic($entry);

		if (!$logger = $this->getLogger())
		{
			return $claimed;
		}

		if (!$claimed)
		{
			$this->logNormal("Could not update run time for cron [{$entry['entry_id']}], skipping");

			return $claimed;
		}

		$logger->logCronStart(self::class, $entry);
		$this->runningCronEntry = ['entry_id' => $entry['entry_id'], 'start' => microtime(true)];

		// read the new next run back only when it will be logged - at normal verbosity this is core's method
		if ($logger->isVeryVerbose())
		{
			$nextRun = intval($this->db()->fetchOne(
				'SELECT next_run FROM xf_cron_entry WHERE entry_id = ?', $entry['entry_id']
			));

			$this->logVeryVerbose(
				"Cron entry [{$entry['entry_id']}] next run time updated",
				['nextRun' => $nextRun, 'nextRun_formatted' => date("Y-m-d H:i:s T", $nextRun)]
			);
		}

		return $claimed;
	}

	public function getMinimumNextRunTime()
	{
		$this->debug("getMinimumNextRunTime");

		// the end of a Cron::run() pass - the last entry has finished
		if ($this->logCronEntryFinished())
		{
			$this->logVeryVerbose("Finished running all Cron tasks - now setting continue date");
		}

		if ($logger = $this->getLogger())
		{
			if ($logger->isVeryVerbose())
			{
				$nextCronEntry = $this->db()->fetchRow('
					SELECT entry.entry_id, entry.cron_class, entry.cron_method, entry.next_run, FROM_UNIXTIME(entry.next_run) AS next_run_formatted
					FROM xf_cron_entry AS entry
					LEFT JOIN xf_addon AS addon ON (entry.addon_id = addon.addon_id)
					WHERE entry.active = 1
						AND (addon.addon_id IS NULL OR addon.active = 1)
				    ORDER BY entry.next_run ASC
				');

				$this->logVeryVerbose("Next cron entry", $nextCronEntry);
			}
		}

		return parent::getMinimumNextRunTime();
	}

	/**
	 * Log the running entry's execution time, if there is one.
	 *
	 * @return bool whether an entry had been running
	 */
	protected function logCronEntryFinished()
	{
		if (!$this->runningCronEntry)
		{
			return false;
		}

		$this->log(sprintf(
			"Cron entry [%s] executed in %01.2f seconds",
			$this->runningCronEntry['entry_id'],
			microtime(true) - $this->runningCronEntry['start']
		));

		$this->runningCronEntry = null;

		return true;
	}
}
