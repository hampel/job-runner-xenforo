<?php namespace Tests\Feature;

use Hampel\Testing\Concerns\UsesDatabaseTransactions;
use Tests\TestCase;

/**
 * XF\Job\Manager as extended by this add-on - which jobs are runnable, and when cron may run.
 */
class JobManagerTest extends TestCase
{
	use UsesDatabaseTransactions;

	protected function setUp(): void
	{
		parent::setUp();

		// every real job is due later than this, so only jobs these tests insert are runnable
		$this->setTestTime(1000);

		// the add-on only changes anything under server-based triggering; XenForo's default is activity
		$this->setOption('jobRunTrigger', 'server');

		// the cron job's unique key is taken on any forum; replace it for the test
		$this->app()->db()->delete('xf_job', 'unique_key = ?', 'cron');
	}

	/**
	 * @return \Hampel\JobRunner\XF\Job\Manager
	 */
	protected function manager()
	{
		return $this->app()->jobManager();
	}

	protected function runnableKeys($manual = false): array
	{
		$keys = array_column($this->manager()->getRunnable($manual), 'unique_key');

		return array_values(array_filter($keys, function ($key) {
			return $key === 'cron' || strpos((string) $key, 'jrTest') === 0;
		}));
	}

	public function test_the_extension_is_applied()
	{
		$this->assertInstanceOf(\Hampel\JobRunner\XF\Job\Manager::class, $this->manager());
	}

	public function test_the_cron_job_is_held_back_by_default()
	{
		$this->insertJob('cron', 'XF:Cron', 500);
		$this->insertJob('jrTestJob', 'Hampel\JobRunner:TestJob', 500);

		$this->assertSame(['jrTestJob'], $this->runnableKeys());
	}

	public function test_the_runner_lets_the_cron_job_through()
	{
		$this->insertJob('cron', 'XF:Cron', 500);
		$this->insertJob('jrTestJob', 'Hampel\JobRunner:TestJob', 600);

		$this->manager()->setAllowCron(true);

		$this->assertSame(['cron', 'jrTestJob'], $this->runnableKeys());
	}

	public function test_activity_triggering_leaves_the_queue_to_xenforo()
	{
		$this->setOption('jobRunTrigger', 'activity');
		$this->insertJob('cron', 'XF:Cron', 500);

		$this->assertSame(['cron'], $this->runnableKeys());
	}

	public function test_manual_jobs_are_left_to_xenforo()
	{
		$this->insertJob('jrTestManual', 'Hampel\JobRunner:TestJob', 500, 1);
		$this->insertJob('jrTestAuto', 'Hampel\JobRunner:TestJob', 500, 0);

		$this->assertSame(['jrTestManual'], $this->runnableKeys(true));
	}

	public function test_jobs_not_yet_due_are_not_runnable()
	{
		$this->insertJob('jrTestLater', 'Hampel\JobRunner:TestJob', 5000);

		$this->assertSame([], $this->runnableKeys());
	}

	public function test_runnable_order_matches_xenforo()
	{
		$this->insertJob('jrTestLate', 'Hampel\JobRunner:TestJob', 700);
		$this->insertJob('jrTestEarly', 'Hampel\JobRunner:TestJob', 500);

		if (\XF::$versionId >= 2030000)
		{
			// XF 2.3 orders by priority first; the held-back query must too
			$urgent = $this->insertJob('jrTestUrgent', 'Hampel\JobRunner:TestJob', 900);
			$this->app()->db()->update('xf_job', ['priority' => 500], 'job_id = ?', $urgent);

			$this->assertSame(['jrTestUrgent', 'jrTestEarly', 'jrTestLate'], $this->runnableKeys());
		}
		else
		{
			$this->assertSame(['jrTestEarly', 'jrTestLate'], $this->runnableKeys());
		}
	}

	public function test_server_triggering_skips_the_next_run_time_calculation()
	{
		$this->fakesRegistry();
		$before = $this->app()->registry()->get('autoJobRun');
		$this->insertJob('jrTestNext', 'Hampel\JobRunner:TestJob', 700);

		$this->assertSame(1000, $this->manager()->updateNextRunTime());
		$this->assertSame($before, $this->app()->registry()->get('autoJobRun'));
	}

	public function test_activity_triggering_leaves_the_next_run_time_to_xenforo()
	{
		$this->fakesRegistry();
		$this->setOption('jobRunTrigger', 'activity');
		$this->insertJob('jrTestNext', 'Hampel\JobRunner:TestJob', 700);

		// XenForo's own answer is the next job's trigger date, not the current time, and it is stored
		$this->assertSame(700, intval($this->manager()->updateNextRunTime()));
		$this->assertSame(700, intval($this->app()->registry()->get('autoJobRun')));
	}
}
