<?php namespace Tests\Feature;

use Hampel\Testing\Concerns\UsesDatabaseTransactions;
use Symfony\Component\Console\Output\OutputInterface;
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

	public function test_the_cron_job_is_runnable_like_any_other_job()
	{
		// since 2.2.0 the add-on no longer holds the cron job back for hg:run-jobs, so XenForo's own
		// xf:run-jobs runs cron tasks with the add-on installed
		$this->insertJob('cron', 'XF:Cron', 500);
		$this->insertJob('jrTestJob', 'Hampel\JobRunner:TestJob', 600);

		$this->assertSame(['cron', 'jrTestJob'], $this->runnableKeys());
	}

	public function test_runnable_jobs_are_logged_when_very_verbose()
	{
		$output = $this->useCliLogger(OutputInterface::VERBOSITY_VERY_VERBOSE);
		$this->insertJob('jrTestLogged', 'Hampel\JobRunner:TestJob', 500);

		$this->manager()->getRunnable(false);

		$log = $output->fetch();
		$this->assertStringContainsString('getRunnable: Getting list of runnable jobs', $log);
		$this->assertStringContainsString('jrTestLogged', $log);
	}

	public function test_an_empty_queue_is_logged_as_such()
	{
		$output = $this->useCliLogger(OutputInterface::VERBOSITY_VERY_VERBOSE);

		// the forum's own jobs are all due later than the test time
		$this->manager()->getRunnable(false);

		$this->assertStringContainsString('getRunnable: No runnable jobs found', $output->fetch());
	}

	public function test_server_triggering_still_records_the_next_run_time()
	{
		$this->fakesRegistry();
		$this->insertJob('jrTestNext', 'Hampel\JobRunner:TestJob', 700);

		// the admin control panel's "scheduled jobs outstanding" check reads this - left stale, it
		// reports stopped jobs whenever one is due
		$this->assertSame(700, intval($this->manager()->updateNextRunTime()));
		$this->assertSame(700, intval($this->app()->registry()->get('autoJobRun')));
	}

	/**
	 * What the admin index's "scheduled jobs outstanding" check reports on the next request, which
	 * builds job.runTime fresh from the autoJobRun registry entry.
	 */
	protected function adminSeesStoppedJobs(): bool
	{
		$this->app()->container()['job.runTime'] = $this->app()->registry()->get('autoJobRun');

		return $this->manager()->hasStoppedJobs();
	}

	public function test_a_healthy_queue_is_not_reported_as_stopped()
	{
		$this->fakesRegistry();

		// a next-run time recorded long ago, as the add-on used to leave it under server triggering
		$this->app()->registry()->set('autoJobRun', 500);
		$this->setTestTime(10000);
		$this->insertJob('jrTestDue', 'Hampel\JobRunner:TestJob', 10000);

		// the precondition: with the stale time, a due job is reported as stopped
		$this->assertTrue($this->adminSeesStoppedJobs());

		// a job queued or run records the new next-run time, as it does in core
		$this->manager()->updateNextRunTime();

		$this->assertFalse($this->adminSeesStoppedJobs());
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
