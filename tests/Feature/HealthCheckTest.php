<?php namespace Tests\Feature;

use Hampel\JobRunner\Cli\Command\HealthCheck as HealthCheckCommand;
use Hampel\JobRunner\Cli\Command\Legacy\RunJobs as LegacyRunJobs;
use Hampel\JobRunner\Service\HealthCheck;
use Hampel\Testing\Concerns\UsesDatabaseTransactions;
use Tests\TestCase;

/**
 * job-monitor:health-check and the service behind it.
 */
class HealthCheckTest extends TestCase
{
	use UsesDatabaseTransactions;

	protected function setUp(): void
	{
		parent::setUp();

		$this->fakesRegistry();
		$this->fakesSimpleCache();
		$this->setOption('jobRunTrigger', 'server');

		// every real job and cron entry is due later than this, so only rows a test inserts are due
		$this->setTestTime(100000);

		// the forum's real failed jobs are dated after this test clock, so they would count as recent
		if (\XF::$versionId >= 2030000)
		{
			$this->app()->db()->delete('xf_failed_job', '1=1');
		}

		// the next-run time the admin index reads, as a healthy forum would have it
		$this->app()->registry()->set('autoJobRun', 100000 + 60);
		$this->app()->container()['job.runTime'] = 100000 + 60;
	}

	protected function health(): HealthCheck
	{
		return $this->app()->service(HealthCheck::class);
	}

	protected function statusOf($check)
	{
		foreach ($this->health()->run() AS $result)
		{
			if ($result['check'] == $check)
			{
				return $result['status'];
			}
		}

		$this->fail("No result for check [{$check}]");
	}

	protected function removeCronJob()
	{
		$this->app()->db()->delete('xf_job', 'unique_key = ?', 'cron');
	}

	public function test_a_healthy_forum_is_all_ok()
	{
		$results = $this->health()->run();

		$this->assertSame(HealthCheck::OK, $this->health()->worstStatus($results), json_encode($results));
		$this->assertCount(8, $results);
	}

	public function test_a_missing_cron_job_is_a_problem()
	{
		$this->removeCronJob();

		$this->assertSame(HealthCheck::PROBLEM, $this->statusOf('cron_job'));
	}

	public function test_a_missing_cron_job_does_not_matter_without_cron_entries()
	{
		$this->removeCronJob();
		$this->app()->db()->update('xf_cron_entry', ['active' => 0], '1=1');

		$this->assertSame(HealthCheck::OK, $this->statusOf('cron_job'));
	}

	public function test_fix_queues_the_cron_job_again()
	{
		$this->removeCronJob();

		$this->assertTrue($this->health()->fixCronJob());
		$this->assertDatabaseHas('xf_job', ['unique_key' => 'cron']);
		$this->assertSame(HealthCheck::OK, $this->statusOf('cron_job'));
	}

	public function test_a_job_due_a_minute_ago_is_fine()
	{
		$this->insertJob('jrTestDue', 'Hampel\JobRunner:TestJob', 100000 - 60);

		$this->assertSame(HealthCheck::OK, $this->statusOf('overdue_jobs'));
	}

	public function test_a_job_ten_minutes_late_is_a_warning()
	{
		$this->insertJob('jrTestLate', 'Hampel\JobRunner:TestJob', 100000 - 600);

		$this->assertSame(HealthCheck::WARNING, $this->statusOf('overdue_jobs'));
	}

	public function test_a_job_two_hours_late_is_a_problem()
	{
		$this->insertJob('jrTestStuck', 'Hampel\JobRunner:TestJob', 100000 - 7200);

		$this->assertSame(HealthCheck::PROBLEM, $this->statusOf('overdue_jobs'));
	}

	public function test_a_late_manual_job_is_not_an_overdue_automatic_one()
	{
		$this->insertJob('jrTestManual', 'Hampel\JobRunner:TestJob', 100000 - 7200, 1);

		$this->assertSame(HealthCheck::OK, $this->statusOf('overdue_jobs'));
		$this->assertSame(HealthCheck::WARNING, $this->statusOf('manual_jobs'));
	}

	public function test_a_cron_entry_hours_late_is_a_problem()
	{
		$this->insertCronEntry('jrTestLateCron', 'record', 100000 - 7200);

		$results = $this->health()->run();
		$overdue = array_values(array_filter($results, function ($r) { return $r['check'] == 'overdue_crons'; }))[0];

		$this->assertSame(HealthCheck::PROBLEM, $overdue['status']);
		$this->assertStringContainsString('jrTestLateCron', $overdue['detail']);
	}

	public function test_a_cron_entry_just_due_is_fine()
	{
		$this->insertCronEntry('jrTestDueCron', 'record', 100000 - 60);

		$this->assertSame(HealthCheck::OK, $this->statusOf('overdue_crons'));
	}

	public function test_a_stale_next_run_time_is_reported_as_the_admin_warning()
	{
		$this->app()->container()['job.runTime'] = 100000 - 7200;
		$this->insertJob('jrTestDue', 'Hampel\JobRunner:TestJob', 100000 - 60);

		$this->assertSame(HealthCheck::WARNING, $this->statusOf('admin_warning'));
	}

	public function test_recent_use_of_hg_run_jobs_is_a_warning()
	{
		$this->app()->simpleCache()->setValue('Hampel/JobRunner', LegacyRunJobs::LAST_USED_KEY, 100000 - 60);

		$this->assertSame(HealthCheck::WARNING, $this->statusOf('legacy_crontab'));
	}

	public function test_long_ago_use_of_hg_run_jobs_is_fine()
	{
		$this->app()->simpleCache()->setValue('Hampel/JobRunner', LegacyRunJobs::LAST_USED_KEY, 100000 - 86400);

		$this->assertSame(HealthCheck::OK, $this->statusOf('legacy_crontab'));
	}

	public function test_the_worst_status_wins()
	{
		$health = $this->health();

		$this->assertSame(HealthCheck::PROBLEM, $health->worstStatus([
			['status' => HealthCheck::OK], ['status' => HealthCheck::PROBLEM], ['status' => HealthCheck::WARNING],
		]));
		$this->assertSame(HealthCheck::WARNING, $health->worstStatus([
			['status' => HealthCheck::OK], ['status' => HealthCheck::WARNING],
		]));
	}

	public function test_the_command_exits_0_when_healthy()
	{
		$tester = $this->runConsoleCommand(new HealthCheckCommand());

		$this->assertSame(0, $tester->getStatusCode(), $tester->getDisplay());
		$this->assertStringNotContainsString('WARNING', $tester->getDisplay());
		$this->assertStringContainsString('Cron job queued', $tester->getDisplay());
	}

	public function test_the_command_exits_1_on_a_warning()
	{
		$this->insertJob('jrTestLate', 'Hampel\JobRunner:TestJob', 100000 - 600);

		$this->assertSame(1, $this->runConsoleCommand(new HealthCheckCommand())->getStatusCode());
	}

	public function test_the_command_exits_2_on_a_problem_and_changes_nothing()
	{
		$this->removeCronJob();

		$tester = $this->runConsoleCommand(new HealthCheckCommand());

		$this->assertSame(2, $tester->getStatusCode());
		$this->assertStringContainsString('--fix', $tester->getDisplay());
		$this->assertDatabaseMissing('xf_job', ['unique_key' => 'cron']);
	}

	public function test_the_command_fixes_a_missing_cron_job_when_asked()
	{
		$this->removeCronJob();

		$tester = $this->runConsoleCommand(new HealthCheckCommand(), ['--fix' => true]);

		$this->assertSame(0, $tester->getStatusCode(), $tester->getDisplay());
		$this->assertStringContainsString('Queued the cron job again.', $tester->getDisplay());
		$this->assertDatabaseHas('xf_job', ['unique_key' => 'cron']);
	}

	public function test_json_output_carries_every_check_and_the_exit_code()
	{
		$this->insertJob('jrTestLate', 'Hampel\JobRunner:TestJob', 100000 - 600);

		$tester = $this->runConsoleCommand(new HealthCheckCommand(), ['--json' => true]);
		$result = json_decode($tester->getDisplay(), true);

		$this->assertIsArray($result, 'the output must be a single JSON document');
		$this->assertSame(1, $tester->getStatusCode());
		$this->assertSame('warning', $result['status']);
		$this->assertSame(1, $result['exit_code']);
		$this->assertSame(100000, $result['checked_at']);
		$this->assertNull($result['fixed_cron_job']);
		$this->assertCount(8, $result['checks']);
		$this->assertSame(['check', 'label', 'status', 'detail'], array_keys($result['checks'][0]));
	}

	public function test_json_output_reports_a_fix()
	{
		$this->removeCronJob();

		$tester = $this->runConsoleCommand(new HealthCheckCommand(), ['--json' => true, '--fix' => true]);
		$result = json_decode($tester->getDisplay(), true);

		$this->assertIsArray($result, 'the fix message must not break the JSON');
		$this->assertTrue($result['fixed_cron_job']);
		$this->assertSame(0, $tester->getStatusCode());
	}
}
