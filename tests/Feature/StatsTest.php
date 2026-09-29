<?php namespace Tests\Feature;

use Hampel\JobRunner\Cli\Command\ShowStats;
use Hampel\JobRunner\Cron\Prune;
use Hampel\JobRunner\Repository\Stats;
use Hampel\Testing\Concerns\UsesDatabaseTransactions;
use Tests\Fixtures\Job\QuickJob;
use Tests\TestCase;

/**
 * Hourly job and cron statistics: recorded under any runner, pruned, shown by job-monitor:show-stats.
 */
class StatsTest extends TestCase
{
	use UsesDatabaseTransactions;

	/** an hour boundary, far enough from zero that 90 days earlier still fits unsigned columns */
	const NOW = 10000800;

	protected function setUp(): void
	{
		parent::setUp();

		$this->fakesRegistry();
		$this->setTestTime(self::NOW);

		// start from an empty table, whatever the forum has recorded
		$this->app()->db()->delete('xf_hampel_jobrunner_stat', '1=1');
	}

	protected function stats(): Stats
	{
		return $this->app()->repository(Stats::class);
	}

	protected function row($type, $name, $period = self::NOW)
	{
		return $this->app()->db()->fetchRow(
			'SELECT * FROM xf_hampel_jobrunner_stat WHERE type = ? AND name = ? AND period_start = ?',
			[$type, $name, $period]
		);
	}

	public function test_runs_in_one_hour_add_up()
	{
		$this->stats()->recordRun(Stats::TYPE_JOB, 'Some\Job', 100);
		$this->stats()->recordRun(Stats::TYPE_JOB, 'Some\Job', 300);

		$row = $this->row(Stats::TYPE_JOB, 'Some\Job');
		$this->assertEquals(2, $row['runs']);
		$this->assertEquals(400, $row['total_ms']);
		$this->assertEquals(300, $row['max_ms']);
	}

	public function test_each_hour_is_its_own_row()
	{
		$this->stats()->recordRun(Stats::TYPE_JOB, 'Some\Job', 100);
		$this->setTestTime(self::NOW + 3600);
		$this->stats()->recordRun(Stats::TYPE_JOB, 'Some\Job', 100);

		$this->assertEquals(1, $this->row(Stats::TYPE_JOB, 'Some\Job')['runs']);
		$this->assertEquals(1, $this->row(Stats::TYPE_JOB, 'Some\Job', self::NOW + 3600)['runs']);
	}

	public function test_a_run_mid_hour_counts_towards_that_hour()
	{
		$this->setTestTime(self::NOW + 1799);

		$this->stats()->recordRun(Stats::TYPE_CRON, 'someCron', 50);

		$this->assertNotEmpty($this->row(Stats::TYPE_CRON, 'someCron', self::NOW));
	}

	public function test_a_failure_is_counted_without_a_run()
	{
		$this->stats()->recordFailure(Stats::TYPE_JOB, 'Some\Job');

		$row = $this->row(Stats::TYPE_JOB, 'Some\Job');
		$this->assertEquals(1, $row['failures']);
		$this->assertEquals(0, $row['runs']);
	}

	public function test_a_job_run_by_the_manager_is_recorded()
	{
		// a completing job: a failing one would make core roll back this test's transaction
		$jobId = $this->insertJob('jrTestQuick', QuickJob::class, self::NOW - 1);
		$job = $this->app()->db()->fetchRow('SELECT * FROM xf_job WHERE job_id = ?', $jobId);

		$this->app()->jobManager()->runJobEntry($job, 30);

		$this->assertEquals(1, $this->row(Stats::TYPE_JOB, QuickJob::class)['runs']);
	}

	public function test_cron_entries_run_in_a_pass_are_recorded()
	{
		$this->insertCronEntry('jrTestStatFirst', 'record', self::NOW - 200);
		$this->insertCronEntry('jrTestStatSecond', 'record', self::NOW - 100);

		// no logger - the statistics must not depend on one
		$this->app()->job('XF:Cron', null, [])->run(30);

		$this->assertEquals(1, $this->row(Stats::TYPE_CRON, 'jrTestStatFirst')['runs']);
		$this->assertEquals(1, $this->row(Stats::TYPE_CRON, 'jrTestStatSecond')['runs']);
	}

	public function test_prune_keeps_ninety_days()
	{
		$this->stats()->recordRun(Stats::TYPE_JOB, 'Recent\Job', 10);
		$this->setTestTime(self::NOW - (Prune::STATS_DAYS + 1) * 86400);
		$this->stats()->recordRun(Stats::TYPE_JOB, 'Old\Job', 10);
		$this->setTestTime(self::NOW);

		Prune::pruneStats();

		$this->assertDatabaseHas('xf_hampel_jobrunner_stat', ['name' => 'Recent\Job']);
		$this->assertDatabaseMissing('xf_hampel_jobrunner_stat', ['name' => 'Old\Job']);
	}

	public function test_summary_sorts_and_filters()
	{
		$this->stats()->recordRun(Stats::TYPE_JOB, 'Busy\Job', 10);
		$this->stats()->recordRun(Stats::TYPE_JOB, 'Busy\Job', 10);
		$this->stats()->recordRun(Stats::TYPE_JOB, 'Busy\Job', 10);
		$this->stats()->recordRun(Stats::TYPE_JOB, 'Slow\Job', 5000);
		$this->stats()->recordRun(Stats::TYPE_CRON, 'someCron', 1);

		$since = self::NOW - 3600;
		$this->assertSame('Slow\Job', $this->stats()->summary($since)[0]['name']);
		$this->assertSame('Busy\Job', $this->stats()->summary($since, null, 'runs')[0]['name']);
		$this->assertSame(['someCron'], array_column($this->stats()->summary($since, Stats::TYPE_CRON), 'name'));
	}

	public function test_the_command_shows_the_summary()
	{
		$this->stats()->recordRun(Stats::TYPE_JOB, 'Slow\Job', 5000);
		$this->stats()->recordRun(Stats::TYPE_CRON, 'someCron', 250);

		$tester = $this->runConsoleCommand(new ShowStats());

		$display = $tester->getDisplay();
		$this->assertSame(0, $tester->getStatusCode());
		$this->assertStringContainsString('Slow\Job', $display);
		$this->assertStringContainsString('5.00s', $display);
		$this->assertStringContainsString('someCron', $display);
		$this->assertStringContainsString('250ms', $display);
		$this->assertLessThan(strpos($display, 'someCron'), strpos($display, 'Slow\Job'));
	}

	public function test_the_command_shows_one_hour_by_hour()
	{
		$this->setTestTime(self::NOW - 3600);
		$this->stats()->recordRun(Stats::TYPE_CRON, 'someCron', 100);
		$this->setTestTime(self::NOW);
		$this->stats()->recordRun(Stats::TYPE_CRON, 'someCron', 300);

		$display = $this->runConsoleCommand(new ShowStats(), ['--name' => 'someCron'])->getDisplay();

		$this->assertStringContainsString('cron [someCron]', $display);
		$this->assertSame(2, substr_count($display, ':00 '));
	}

	public function test_the_command_says_when_nothing_ran()
	{
		$this->assertStringContainsString(
			'Nothing has run in the last 24 hours.',
			$this->runConsoleCommand(new ShowStats())->getDisplay()
		);
	}

	public function test_the_command_refuses_an_unknown_type()
	{
		$this->assertSame(1, $this->runConsoleCommand(new ShowStats(), ['--type' => 'bad'])->getStatusCode());
	}
}
