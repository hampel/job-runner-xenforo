<?php namespace Tests\Feature;

use Hampel\JobRunner\Cli\Command\RunJobs;
use Hampel\JobRunner\Cli\Logger;
use Mockery as m;
use Symfony\Component\Console\Output\OutputInterface;
use Tests\TestCase;

/**
 * job-monitor:run-jobs, the run-by-hand diagnostic. The job manager is a mock, so these tests cover
 * the command's own logic and run no real jobs.
 */
class RunJobsCommandTest extends TestCase
{
	/** @var \Mockery\MockInterface */
	protected $jobManager;

	protected function setUp(): void
	{
		parent::setUp();

		$this->swapFs('internal-data');

		$this->jobManager = m::mock();
		$this->jobManager->allows()->canRunJobs()->andReturns(true)->byDefault();
		$this->jobManager->allows()->queuePending(m::any())->andReturns(false)->byDefault();

		$this->swap('job.manager', function () {
			return $this->jobManager;
		});

		// what Hampel\JobRunner\App registers - a test cannot boot a second application class
		$container = $this->app()->container();
		$container['cli.output'] = null;
		$container['cli.logger'] = function ($c) {
			return new Logger($c['cli.output']);
		};
	}

	public function test_runs_the_queue()
	{
		$this->jobManager->expects()->runQueue(false, m::type('numeric'))->once();

		$tester = $this->runConsoleCommand(new RunJobs());

		$this->assertSame(0, $tester->getStatusCode());
		$this->assertStringContainsString('All outstanding jobs have run.', $tester->getDisplay());
	}

	public function test_keeps_running_while_jobs_are_pending()
	{
		$this->jobManager->expects()->queuePending(false)->andReturns(true, true, false)->times(3);
		$this->jobManager->expects()->runQueue(false, m::type('numeric'))->times(3);

		$this->assertSame(0, $this->runConsoleCommand(new RunJobs())->getStatusCode());
	}

	public function test_zero_runs_until_the_queue_is_empty()
	{
		// with a time limit this many passes would stop early; with 0 it runs them all
		$this->jobManager->expects()->queuePending(false)->andReturns(true, true, true, true, false)->times(5);
		$this->jobManager->expects()->runQueue(false, m::type('numeric'))->times(5);

		$tester = $this->runConsoleCommand(new RunJobs(), ['--max-execution-time' => 0]);

		$this->assertStringContainsString('All outstanding jobs have run.', $tester->getDisplay());
	}

	public function test_a_job_that_throws_propagates()
	{
		$this->jobManager->expects()->runQueue(false, m::type('numeric'))
			->andThrows(new \RuntimeException('job exploded'));

		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('job exploded');

		$this->runConsoleCommand(new RunJobs());
	}

	public function test_takes_no_lock()
	{
		$this->jobManager->expects()->runQueue(false, m::type('numeric'))->once();

		$this->runConsoleCommand(new RunJobs());

		$this->assertFsHasNot('internal-data://run-jobs-lock.txt');
	}

	public function test_manual_only_runs_only_manual_jobs()
	{
		$this->jobManager->expects()->queuePending(true)->andReturns(false);
		$this->jobManager->expects()->runQueue(true, m::type('numeric'))->once();

		$this->assertSame(0, $this->runConsoleCommand(new RunJobs(), ['--manual-only' => true])->getStatusCode());
	}

	public function test_exits_when_jobs_cannot_run()
	{
		$this->jobManager->expects()->canRunJobs()->andReturns(false);
		$this->jobManager->expects()->runQueue(m::any(), m::any())->never();

		$tester = $this->runConsoleCommand(new RunJobs());

		$this->assertSame(1, $tester->getStatusCode());
		$this->assertStringContainsString('Jobs cannot be run at this time.', $tester->getDisplay());
	}

	public function test_no_single_run_is_given_more_than_the_configured_job_time()
	{
		$this->setConfig('jobMaxRunTime', 3);
		$this->jobManager->expects()->runQueue(false, m::on(function ($maxRunTime) {
			return $maxRunTime <= 3;
		}))->once();

		$this->runConsoleCommand(new RunJobs(), ['--max-execution-time' => 600]);
	}

	public function test_logs_without_being_asked()
	{
		$this->jobManager->allows()->runQueue(m::any(), m::any());

		// run by hand, the log is the point: normal verbosity shows what -v used to
		$tester = $this->runConsoleCommand(new RunJobs(), [], ['verbosity' => OutputInterface::VERBOSITY_NORMAL]);

		$this->assertStringContainsString('Run Jobs starting', $tester->getDisplay());
		$this->assertStringContainsString('Total execution time', $tester->getDisplay());
	}

	public function test_quiet_stays_quiet()
	{
		$this->jobManager->allows()->runQueue(m::any(), m::any());

		$tester = $this->runConsoleCommand(new RunJobs(), [], ['verbosity' => OutputInterface::VERBOSITY_QUIET]);

		$this->assertSame('', $tester->getDisplay());
	}
}
