<?php namespace Tests\Feature;

use Hampel\Testing\Concerns\UsesDatabaseTransactions;
use Symfony\Component\Console\Output\OutputInterface;
use Tests\Fixtures\CronCallbacks;
use Tests\TestCase;
use XF\Job\JobResult;

/**
 * XF\Job\Cron as extended by this add-on - the pass that runs every due cron entry.
 */
class CronJobTest extends TestCase
{
	use UsesDatabaseTransactions;

	protected function setUp(): void
	{
		parent::setUp();

		// every real cron entry is due in the future, so only the entries these tests insert are due
		$this->setTestTime(1000);
	}

	protected function runCronJob(): JobResult
	{
		$job = $this->app()->job('XF:Cron', null, []);

		return $job->run(30);
	}

	public function test_the_extension_is_applied()
	{
		$this->assertInstanceOf(
			\Hampel\JobRunner\XF\Job\Cron::class,
			$this->app()->job('XF:Cron', null, [])
		);
	}

	public function test_due_entries_run_in_next_run_order()
	{
		$this->useCliLogger();
		$this->insertCronEntry('jrTestSecond', 'record', 200);
		$this->insertCronEntry('jrTestFirst', 'record', 100);
		$this->insertCronEntry('jrTestNotDue', 'record', 5000);

		$result = $this->runCronJob();

		$this->assertSame(['jrTestFirst', 'jrTestSecond'], CronCallbacks::$calls);
		$this->assertSame(JobResult::RESULT_RESUME, $result->result);
	}

	public function test_an_error_in_one_callback_does_not_stop_the_pass()
	{
		$this->fakesErrors();
		$this->useCliLogger();
		$this->insertCronEntry('jrTestBroken', 'throwError', 100);
		$this->insertCronEntry('jrTestAfter', 'record', 200);

		// an \Error escaping run() fails the job, and XF 2.3 then deletes the cron job from the
		// queue - so this must return normally rather than throw
		$result = $this->runCronJob();

		$this->assertSame(['jrTestBroken', 'jrTestAfter'], CronCallbacks::$calls);
		$this->assertSame(JobResult::RESULT_RESUME, $result->result);
		$this->assertExceptionLogged(\TypeError::class);
	}

	public function test_each_entry_run_moves_its_next_run_forward()
	{
		$this->useCliLogger();
		$this->insertCronEntry('jrTestAdvance', 'record', 100);

		$this->runCronJob();

		$this->assertGreaterThan(1000, $this->cronNextRun('jrTestAdvance'));
	}

	public function test_an_inactive_entry_does_not_run()
	{
		$this->useCliLogger();
		$this->insertCronEntry('jrTestInactive', 'record', 100, 0);

		$this->runCronJob();

		$this->assertSame([], CronCallbacks::$calls);
	}

	public function test_each_entry_is_logged_when_verbose()
	{
		$output = $this->useCliLogger(OutputInterface::VERBOSITY_VERBOSE);
		$this->insertCronEntry('jrTestLogged', 'record', 100);

		$this->runCronJob();

		$log = $output->fetch();
		$this->assertStringContainsString('Executing cron entry [jrTestLogged]', $log);
		$this->assertMatchesRegularExpression('/Cron entry \[jrTestLogged\] executed in [0-9.]+ seconds/', $log);
	}

	public function test_nothing_is_logged_at_normal_verbosity()
	{
		$output = $this->useCliLogger(OutputInterface::VERBOSITY_NORMAL);
		$this->insertCronEntry('jrTestQuiet', 'record', 100);

		$this->runCronJob();

		$this->assertSame(['jrTestQuiet'], CronCallbacks::$calls);
		$this->assertSame('', $output->fetch());
	}

	public function test_without_a_logger_the_core_pass_still_runs_the_entries()
	{
		// no cli.logger - outside hg:run-jobs the extension defers to XenForo's own run()
		$this->insertCronEntry('jrTestCore', 'record', 100);

		$this->runCronJob();

		$this->assertSame(['jrTestCore'], CronCallbacks::$calls);
	}
}
