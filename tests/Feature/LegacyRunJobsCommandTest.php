<?php namespace Tests\Feature;

use Hampel\JobRunner\Cli\Command\Legacy\RunJobs as LegacyRunJobs;
use Mockery as m;
use Tests\TestCase;

/**
 * hg:run-jobs, kept for pre-3.0.0 crontab entries. It must behave exactly as xf:run-jobs.
 */
class LegacyRunJobsCommandTest extends TestCase
{
	/** @var \Mockery\MockInterface */
	protected $jobManager;

	protected function setUp(): void
	{
		parent::setUp();

		$this->fakesSimpleCache();
		$this->swapFs('internal-data');

		$this->jobManager = m::mock();
		$this->jobManager->allows()->canRunJobs()->andReturns(true)->byDefault();
		$this->jobManager->allows()->queuePending(m::any())->andReturns(false)->byDefault();
		$this->jobManager->allows()->runQueue(m::any(), m::any())->byDefault();

		$this->swap('job.manager', function () {
			return $this->jobManager;
		});
	}

	public function test_output_is_exactly_xf_run_jobs()
	{
		// cron mails any output, so nothing of the add-on's own may appear
		$legacy = $this->runConsoleCommand(new LegacyRunJobs());
		$core = $this->runConsoleCommand(new \XF\Cli\Command\RunJobs());

		$this->assertSame($core->getDisplay(), $legacy->getDisplay());
		$this->assertSame($core->getStatusCode(), $legacy->getStatusCode());
	}

	public function test_manual_only_passes_through()
	{
		$this->jobManager->expects()->queuePending(true)->andReturns(false);
		$this->jobManager->expects()->runQueue(true, m::any())->once();

		$this->runConsoleCommand(new LegacyRunJobs(), ['--manual-only' => true]);
	}

	public function test_old_options_are_still_accepted()
	{
		$tester = $this->runConsoleCommand(new LegacyRunJobs(), ['-t' => 30, '--reset' => true]);

		$this->assertSame(0, $tester->getStatusCode());
	}

	public function test_a_refusal_keeps_core_exit_code()
	{
		$this->jobManager->allows()->canRunJobs()->andReturns(false);

		$this->assertSame(1, $this->runConsoleCommand(new LegacyRunJobs())->getStatusCode());
	}

	public function test_takes_no_lock()
	{
		$this->runConsoleCommand(new LegacyRunJobs());

		$this->assertFsHasNot('internal-data://run-jobs-lock.txt');
	}

	public function test_records_that_it_was_used()
	{
		$this->setTestTime(100000);

		$this->runConsoleCommand(new LegacyRunJobs());

		$this->assertSimpleCacheEquals(100000, 'Hampel/JobRunner', LegacyRunJobs::LAST_USED_KEY);
	}

	public function test_records_use_at_most_hourly()
	{
		$this->setTestTime(100000);
		$this->runConsoleCommand(new LegacyRunJobs());

		$this->setTestTime(100000 + 1800);
		$this->runConsoleCommand(new LegacyRunJobs());

		$this->assertSimpleCacheEquals(100000, 'Hampel/JobRunner', LegacyRunJobs::LAST_USED_KEY);
	}
}
