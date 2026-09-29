<?php namespace Tests\Feature;

use Hampel\JobRunner\Cli\Command\ShowFailedJobs;
use Hampel\JobRunner\Cron\FailedJobs;
use Hampel\JobRunner\Service\HealthCheck;
use Hampel\Testing\Concerns\UsesDatabaseTransactions;
use Tests\Fixtures\Job\FailingJob;
use Tests\TestCase;

/**
 * Failed jobs: recorded outside debug mode, shown by job-monitor:show-failed-jobs, pruned daily.
 * XenForo 2.3 only - 2.2 has no failed-job state.
 */
class FailedJobsTest extends TestCase
{
	use UsesDatabaseTransactions;

	/** far enough from zero that a date weeks earlier still fits the unsigned fail_date column */
	const NOW = 10000000;

	protected function setUp(): void
	{
		parent::setUp();

		if (\XF::$versionId < 2030000)
		{
			$this->markTestSkipped('XenForo 2.2 does not record failed jobs.');
		}

		$this->fakesErrors();
		$this->fakesRegistry();
		$this->setTestTime(self::NOW);

		// start from an empty table, whatever the forum has recorded
		$this->app()->db()->delete('xf_failed_job', '1=1');
	}

	/**
	 * Call the Manager extension's logFailedJob() as core's runJobEntry() does for a failed job.
	 *
	 * Deliberately not by running a failing job through the manager: core's runJobInternal() calls
	 * rollbackAll() when a job throws, which also ends this test's wrapping transaction, and every
	 * write after it would then be committed to the forum's database for real.
	 */
	protected function logFailure()
	{
		$method = new \ReflectionMethod($this->app()->jobManager(), 'logFailedJob');
		$method->setAccessible(true);

		return $method->invoke($this->app()->jobManager(), [
			'execute_class' => FailingJob::class,
			'execute_data' => serialize(['key' => 'value']),
		], new \RuntimeException('failing job for tests'));
	}

	protected function insertFailedJob($class, $failDate, $exception = 'RuntimeException: failed')
	{
		$this->app()->db()->insert('xf_failed_job', [
			'execute_class' => $class,
			'execute_data' => serialize(['key' => 'value']),
			'exception' => $exception,
			'fail_date' => $failDate,
		]);

		return intval($this->app()->db()->lastInsertId());
	}

	public function test_a_failure_is_recorded_outside_debug_mode()
	{
		$this->setStaticProperty(\XF::class, 'debugMode', false);

		$this->assertTrue($this->logFailure());

		$this->assertDatabaseHas('xf_failed_job', ['execute_class' => FailingJob::class]);
		$this->assertDatabaseCount('xf_failed_job', 1);
	}

	public function test_a_failure_in_debug_mode_is_recorded_once()
	{
		// core writes the row itself in debug mode; the extension must not write a second
		$this->setStaticProperty(\XF::class, 'debugMode', true);

		$this->assertTrue($this->logFailure());

		$this->assertDatabaseCount('xf_failed_job', 1);
	}

	public function test_prune_deletes_records_older_than_thirty_days()
	{
		$old = $this->insertFailedJob('Old\Job', self::NOW - (FailedJobs::RETAIN_DAYS + 1) * 86400);
		$recent = $this->insertFailedJob('Recent\Job', self::NOW - 86400);

		FailedJobs::prune();

		$this->assertDatabaseMissing('xf_failed_job', ['failed_job_id' => $old]);
		$this->assertDatabaseHas('xf_failed_job', ['failed_job_id' => $recent]);
	}

	public function test_the_list_shows_failures_newest_first()
	{
		$this->insertFailedJob('Older\Job', self::NOW - 7200);
		$this->insertFailedJob('Newer\Job', self::NOW - 60, "LogicException: went wrong\nStack trace:\n#0 ...");

		$tester = $this->runConsoleCommand(new ShowFailedJobs());

		$display = $tester->getDisplay();
		$this->assertSame(0, $tester->getStatusCode());
		$this->assertStringContainsString('2 failed jobs recorded', $display);
		$this->assertStringContainsString('LogicException: went wrong', $display);
		$this->assertStringNotContainsString('Stack trace', $display);
		$this->assertLessThan(strpos($display, 'Older\Job'), strpos($display, 'Newer\Job'));
	}

	public function test_the_list_honours_the_limit()
	{
		$this->insertFailedJob('First\Job', self::NOW - 300);
		$this->insertFailedJob('Second\Job', self::NOW - 200);
		$this->insertFailedJob('Third\Job', self::NOW - 100);

		$display = $this->runConsoleCommand(new ShowFailedJobs(), ['--limit' => 1])->getDisplay();

		$this->assertStringContainsString('3 failed jobs recorded, showing the newest 1', $display);
		$this->assertStringContainsString('Third\Job', $display);
		$this->assertStringNotContainsString('First\Job', $display);
	}

	public function test_one_failure_in_full()
	{
		$id = $this->insertFailedJob('Some\Job', self::NOW - 60, "LogicException: went wrong\nStack trace:\n#0 frame");

		$display = $this->runConsoleCommand(new ShowFailedJobs(), ['--id' => $id])->getDisplay();

		$this->assertStringContainsString("Failed job {$id}", $display);
		$this->assertStringContainsString('{"key":"value"}', $display);
		$this->assertStringContainsString('#0 frame', $display);
	}

	public function test_an_unknown_id_is_refused()
	{
		$tester = $this->runConsoleCommand(new ShowFailedJobs(), ['--id' => 999999]);

		$this->assertSame(1, $tester->getStatusCode());
		$this->assertStringContainsString('No failed job with id 999999', $tester->getDisplay());
	}

	public function test_an_empty_table_says_so()
	{
		$this->assertStringContainsString(
			'No failed jobs recorded.',
			$this->runConsoleCommand(new ShowFailedJobs())->getDisplay()
		);
	}

	public function test_the_health_check_warns_about_a_recent_failure()
	{
		$this->insertFailedJob('Recent\Job', self::NOW - 3600);

		$result = $this->app()->service(HealthCheck::class)->checkFailedJobs();

		$this->assertSame(HealthCheck::WARNING, $result['status']);
		$this->assertStringContainsString('Recent\Job (1)', $result['detail']);
	}

	public function test_the_health_check_ignores_an_old_failure()
	{
		$this->insertFailedJob('Old\Job', self::NOW - 2 * 86400);

		$this->assertSame(HealthCheck::OK, $this->app()->service(HealthCheck::class)->checkFailedJobs()['status']);
	}
}
