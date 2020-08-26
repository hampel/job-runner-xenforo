<?php namespace Hampel\JobRunner\XF\Service\CronEntry;

use Hampel\JobRunner\Cli\LoggerTrait;

class CalculateNextRun extends XFCP_CalculateNextRun
{
	use LoggerTrait;

	public function updateCronRunTimeAtomic(\XF\Entity\CronEntry $entry)
	{
		$this->debug("updateCronRunTimeAtomic");

		$runRules = $entry['run_rules'];
		$nextRun = $this->calculateNextRunTime($runRules);

		$updateResult = $this->db()->update(
			'xf_cron_entry',
			['next_run' => $nextRun],
			'entry_id = ? AND next_run = ?',
			[$entry['entry_id'], $entry['next_run']]
		);

		// JobRunner addon
		if ((bool)$updateResult)
		{
			$this->logVeryVerbose(
				"Cron entry [{$entry['entry_id']}] next run time updated",
				['nextRun' => $nextRun, 'nextRun_formatted' => date("Y-m-d H:i:s T", $nextRun)]
			);
		}
		// end JobRunner addon

		return (bool)$updateResult;
	}

	public function getMinimumNextRunTime()
	{
		$this->debug("getMinimumNextRunTime");

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
}
