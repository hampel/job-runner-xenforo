<?php namespace Tests\Feature;

use Hampel\JobRunner\Cli\Command\RunJobs;
use Hampel\JobRunner\Listener;
use Mockery as m;
use Tests\TestCase;

/**
 * hg:run-jobs. The job manager is a mock, so these tests cover the command's own logic - the lock,
 * its options, the queue loop - and run no real jobs.
 */
class RunJobsCommandTest extends TestCase
{
	protected $lockFile = 'internal-data://run-jobs-lock.txt';

	/** @var \Mockery\MockInterface */
	protected $jobManager;

	protected function setUp(): void
	{
		parent::setUp();

		// the add-on registers its sub-container from app_cli_setup, which a test app never fires
		Listener::appCliSetup($this->app());

		$this->swapFs('internal-data');

		$this->jobManager = m::mock();
		$this->jobManager->allows()->canRunJobs()->andReturns(true)->byDefault();
		$this->jobManager->allows()->setAllowCron(true)->byDefault();
		$this->jobManager->allows()->queuePending(m::any())->andReturns(false)->byDefault();

		$this->swap('job.manager', function () {
			return $this->jobManager;
		});
	}

	protected function writeLock($expiry)
	{
		$this->app()->fs()->write($this->lockFile, $expiry);
	}

	public function test_runs_the_queue_and_releases_the_lock()
	{
		$this->jobManager->expects()->runQueue(false, m::type('numeric'))->once();

		$tester = $this->runConsoleCommand(new RunJobs());

		$this->assertSame(0, $tester->getStatusCode());
		$this->assertStringContainsString('All outstanding jobs have run.', $tester->getDisplay());
		$this->assertFsHasNot($this->lockFile);
	}

	public function test_keeps_running_while_jobs_are_pending()
	{
		$this->jobManager->expects()->queuePending(false)->andReturns(true, true, false)->times(3);
		$this->jobManager->expects()->runQueue(false, m::type('numeric'))->times(3);

		$tester = $this->runConsoleCommand(new RunJobs());

		$this->assertSame(0, $tester->getStatusCode());
	}

	public function test_releases_the_lock_when_a_job_throws()
	{
		$this->jobManager->expects()->runQueue(false, m::type('numeric'))
			->andThrows(new \RuntimeException('job exploded'));

		try
		{
			$this->runConsoleCommand(new RunJobs());
			$this->fail('The exception from the job should propagate out of the command.');
		}
		catch (\RuntimeException $e)
		{
			$this->assertSame('job exploded', $e->getMessage());
		}

		// left in place, the lock would block every run for the next ten minutes
		$this->assertFsHasNot($this->lockFile);
	}

	public function test_refuses_to_run_while_another_runner_holds_the_lock()
	{
		$this->writeLock(\XF::$time + 300);
		$this->jobManager->expects()->runQueue(m::any(), m::any())->never();

		$tester = $this->runConsoleCommand(new RunJobs());

		$this->assertSame(2, $tester->getStatusCode());
		$this->assertStringContainsString('JobRunner already running.', $tester->getDisplay());
		$this->assertFsHas($this->lockFile);
	}

	public function test_takes_over_an_expired_lock()
	{
		$this->writeLock(\XF::$time - 1);
		$this->jobManager->expects()->runQueue(false, m::type('numeric'))->once();

		$tester = $this->runConsoleCommand(new RunJobs());

		$this->assertSame(0, $tester->getStatusCode());
		$this->assertFsHasNot($this->lockFile);
	}

	public function test_reset_clears_an_unexpired_lock_and_runs()
	{
		$this->writeLock(\XF::$time + 300);
		$this->jobManager->expects()->runQueue(false, m::type('numeric'))->once();

		$tester = $this->runConsoleCommand(new RunJobs(), ['--reset' => true]);

		$this->assertSame(0, $tester->getStatusCode());
		$this->assertFsHasNot($this->lockFile);
	}

	public function test_manual_only_ignores_the_lock_and_leaves_it_alone()
	{
		$this->writeLock(\XF::$time + 300);
		$this->jobManager->expects()->queuePending(true)->andReturns(false);
		$this->jobManager->expects()->runQueue(true, m::type('numeric'))->once();

		$tester = $this->runConsoleCommand(new RunJobs(), ['--manual-only' => true]);

		$this->assertSame(0, $tester->getStatusCode());
		$this->assertSame(\XF::$time + 300, intval($this->app()->fs()->read($this->lockFile)));
	}

	public function test_exits_when_jobs_cannot_run()
	{
		$this->jobManager->expects()->canRunJobs()->andReturns(false);
		$this->jobManager->expects()->runQueue(m::any(), m::any())->never();

		$tester = $this->runConsoleCommand(new RunJobs());

		$this->assertSame(1, $tester->getStatusCode());
		$this->assertStringContainsString('Jobs cannot be run at this time.', $tester->getDisplay());
		$this->assertFsHasNot($this->lockFile);
	}

	public function test_no_single_run_is_given_more_than_the_configured_job_time()
	{
		$this->setConfig('jobMaxRunTime', 3);
		$this->jobManager->expects()->runQueue(false, m::on(function ($maxRunTime) {
			return $maxRunTime <= 3;
		}))->once();

		$this->runConsoleCommand(new RunJobs(), ['--max-execution-time' => 600]);
	}

	public function test_lets_cron_tasks_into_the_queue()
	{
		$this->jobManager->expects()->setAllowCron(true)->once();
		$this->jobManager->allows()->runQueue(m::any(), m::any());

		$this->runConsoleCommand(new RunJobs());
	}
}
