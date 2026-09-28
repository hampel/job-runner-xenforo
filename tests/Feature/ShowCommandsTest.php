<?php namespace Tests\Feature;

use Hampel\JobRunner\Cli\Command\ShowCrons;
use Hampel\JobRunner\Cli\Command\ShowJobs;
use Hampel\Testing\Concerns\UsesDatabaseTransactions;
use Tests\TestCase;

/**
 * hg:show-crons and hg:show-jobs.
 */
class ShowCommandsTest extends TestCase
{
	use UsesDatabaseTransactions;

	/** @var string */
	protected $timezone;

	protected function setUp(): void
	{
		parent::setUp();

		// both commands switch to the guest time zone while rendering; they must switch back
		$this->timezone = date_default_timezone_get();
		date_default_timezone_set('UTC');
		$this->setOption('guestTimeZone', 'Australia/Sydney');
	}

	protected function tearDown(): void
	{
		date_default_timezone_set($this->timezone);

		parent::tearDown();
	}

	public function test_show_crons_lists_active_entries_and_their_addon()
	{
		$this->insertCronEntry('jrTestListed', 'record', 5000);
		$this->insertCronEntry('jrTestHidden', 'record', 5000, 0);

		$tester = $this->runConsoleCommand(new ShowCrons());

		$display = $tester->getDisplay();
		$this->assertSame(0, $tester->getStatusCode());
		$this->assertMatchesRegularExpression('/\d+ active cron entries found/', $display);
		$this->assertStringContainsString('jrTestListed', $display);
		$this->assertStringNotContainsString('jrTestHidden', $display);
		$this->assertStringNotContainsString('Tests\Fixtures\CronCallbacks::record', $display);
		$this->assertSame('UTC', date_default_timezone_get());
	}

	public function test_show_crons_all_includes_inactive_entries_and_methods()
	{
		$this->insertCronEntry('jrTestHidden', 'record', 5000, 0);

		$tester = $this->runConsoleCommand(new ShowCrons(), ['--all' => true, '--method' => true]);

		$display = $tester->getDisplay();
		$this->assertMatchesRegularExpression('/\d+ cron entries found/', $display);
		$this->assertStringContainsString('jrTestHidden', $display);
		$this->assertStringContainsString('Tests\Fixtures\CronCallbacks::record', $display);
	}

	public function test_show_crons_shows_times_in_the_guest_time_zone()
	{
		$this->insertCronEntry('jrTestZoned', 'record', 1767225600); // 2026-01-01 00:00 UTC

		$display = $this->runConsoleCommand(new ShowCrons(), ['--sort' => 'id'])->getDisplay();

		$this->assertStringContainsString('01-Jan-2026 11:00', $display); // AEDT, UTC+11
		$this->assertSame('UTC', date_default_timezone_get());
	}

	public function test_show_jobs_lists_pending_jobs()
	{
		$this->insertJob('jrTestPending', 'Hampel\JobRunner:TestJob', 1767225600);

		$tester = $this->runConsoleCommand(new ShowJobs());

		$display = $tester->getDisplay();
		$this->assertSame(0, $tester->getStatusCode());
		$this->assertMatchesRegularExpression('/\d+ pending jobs found/', $display);
		$this->assertStringContainsString('jrTestPending', $display);
		$this->assertStringContainsString('01-Jan-2026 11:00:00', $display);
		$this->assertSame('UTC', date_default_timezone_get());
	}

	public function test_show_jobs_says_so_when_the_queue_is_empty()
	{
		$this->app()->db()->delete('xf_job', '1=1');

		$tester = $this->runConsoleCommand(new ShowJobs());

		$this->assertSame(0, $tester->getStatusCode());
		$this->assertStringContainsString('No pending jobs found', $tester->getDisplay());
		$this->assertSame('UTC', date_default_timezone_get());
	}
}
