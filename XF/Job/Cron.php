<?php namespace Hampel\JobRunner\XF\Job;

class Cron extends XFCP_Cron
{
	public function run($maxRunTime)
	{
		// JobRunner addon
		if (!isset($this->app['cli.logger'])) return parent::run($maxRunTime);
		// end JobRunner addon

		$start = microtime(true);

		/** @var \XF\Service\CronEntry\CalculateNextRun $cronService */
		$cronService = $this->app->service('XF:CronEntry\CalculateNextRun');

		$entries = $this->app->finder('XF:CronEntry')
			->whereAddOnActive()
			->where('active', 1)
			->where('next_run', '<', \XF::$time)
			->order('next_run');

		foreach ($entries->fetch() AS $entry)
		{
			$hasCallback = $entry->hasCallback();

			if (!$cronService->updateCronRunTimeAtomic($entry))
			{
				continue;
			}

			// JobRunner addon
			$taskStart = microtime(true);
			// end JobRunner addon

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
			$execution_time = number_format(microtime(true) - $taskStart, 2);
			$this->log("Cron entry {$entry['cron_class']}::{$entry['cron_method']} executed in {$execution_time} seconds", $entry->toArray());
			// end JobRunner addon

			if (microtime(true) - $start >= $maxRunTime)
			{
				break;
			}
		}

		$result = $this->resume();
		$result->continueDate = $cronService->getMinimumNextRunTime();
		return $result;
	}

	protected function log($message, array $context = [])
	{
		// check to see if we actually have a logger available and abort if not
		if (!isset($this->app['cli.logger'])) return;

		/** @var Logger $logger */
		$logger = $this->app['cli.logger'];
		$logger->log("XF\Job\Cron", $message, $context);
	}
}
