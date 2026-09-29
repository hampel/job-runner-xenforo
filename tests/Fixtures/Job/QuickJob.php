<?php namespace Tests\Fixtures\Job;

use XF\Job\AbstractJob;

/**
 * A job that completes at once. Safe to run through the job manager in a transactional test,
 * unlike FailingJob: only a job that throws makes core call rollbackAll().
 */
class QuickJob extends AbstractJob
{
	public function run($maxRunTime)
	{
		return $this->complete();
	}

	public function getStatusMessage()
	{
		return 'Quick';
	}

	public function canCancel()
	{
		return false;
	}

	public function canTriggerByChoice()
	{
		return false;
	}
}
