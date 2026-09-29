<?php namespace Tests\Fixtures\Job;

use XF\Job\AbstractJob;

/**
 * A job that always throws, for testing how failures are recorded.
 */
class FailingJob extends AbstractJob
{
	public function run($maxRunTime)
	{
		throw new \RuntimeException('failing job for tests');
	}

	public function getStatusMessage()
	{
		return 'Failing';
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
