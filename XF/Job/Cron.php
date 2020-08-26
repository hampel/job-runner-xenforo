<?php namespace Hampel\JobRunner\XF\Job;

use Hampel\JobRunner\Cli\LoggerTrait;

class Cron extends XFCP_Cron
{
	use LoggerTrait;

	public function run($maxRunTime)
	{
		// JobRunner addon
		// if we don't have our logger available, something went wrong - just run the parent version of the Cron job
		if (!$logger = $this->getLogger()) return parent::run($maxRunTime);
		$this->debug("running Cron Job");
		// end JobRunner addon

		$start = microtime(true);

		/** @var \XF\Service\CronEntry\CalculateNextRun $cronService */
		$cronService = $this->app->service('XF:CronEntry\CalculateNextRun');

		$entries = $this->app->finder('XF:CronEntry')
			->whereAddOnActive()
			->where('active', 1)
			->where('next_run', '<=', \XF::$time) // core bugfix '<' should be '<='
			->order('next_run')
			->fetch();

		// JobRunner addon
		if ($entries->count() == 0)
		{
			$this->log("No cron entries found");
		}
		// end JobRunner addon

		foreach ($entries AS $entry)
		{
			// JobRunner addon
			$taskStart = microtime(true);

			$logger->logCronStart(self::class, $entry);
			// end JobRunner addon

			$hasCallback = $entry->hasCallback();

			if (!$cronService->updateCronRunTimeAtomic($entry))
			{
				// JobRunner addon
				$this->logNormal("Could not update run time for cron [{$entry['entry_id']}], skipping");
				// end JobRunner addon

				continue;
			}

			try
			{
				if ($hasCallback)
				{
					call_user_func(
						[$entry['cron_class'], $entry['cron_method']],
						$entry
					);
				}
			}
			catch (\Exception $e)
			{
				// suppress so we don't get stuck -- make sure we rollback though as don't know the state
				$this->app->logException($e, true);
			}

			// JobRunner addon
			$this->log(sprintf("Cron entry [%s] executed in %01.2f seconds", $entry['entry_id'], microtime(true) - $taskStart));
			// end JobRunner addon

			if (microtime(true) - $start >= $maxRunTime)
			{
				break;
			}
		}

		$result = $this->resume();

		// JobRunner addon
		$this->logVeryVerbose("Finished running all Cron tasks - now setting continue date");
		// end JobRunner addon

		$result->continueDate = $cronService->getMinimumNextRunTime();
		return $result;
	}
}
