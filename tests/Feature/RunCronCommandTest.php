<?php namespace Tests\Feature;

use Hampel\JobRunner\Cli\Command\RunCron;
use Hampel\Testing\Concerns\UsesDatabaseTransactions;
use Tests\Fixtures\CronCallbacks;
use Tests\Fixtures\ExtendedCronCallbacks;
use Tests\TestCase;

/**
 * job-monitor:run-cron - run one cron entry on demand, the way the scheduler would.
 */
class RunCronCommandTest extends TestCase
{
	use UsesDatabaseTransactions;

	public function test_runs_the_named_entry()
	{
		$this->insertCronEntry('jrTestManual', 'record', 5000);

		$tester = $this->runConsoleCommand(new RunCron(), ['id' => 'jrTestManual']);

		$this->assertSame(0, $tester->getStatusCode());
		$this->assertSame(['jrTestManual'], CronCallbacks::$calls);
	}

	public function test_does_not_touch_next_run()
	{
		$this->insertCronEntry('jrTestNextRun', 'record', 5000);

		$this->runConsoleCommand(new RunCron(), ['id' => 'jrTestNextRun']);

		$this->assertSame(5000, $this->cronNextRun('jrTestNextRun'));
	}

	public function test_runs_the_class_as_extended_by_other_addons()
	{
		// as another add-on's class extension would, so the command debugs what the scheduler runs
		$this->app()->extension()->addClassExtension(CronCallbacks::class, ExtendedCronCallbacks::class);
		$this->insertCronEntry('jrTestExtended', 'record', 5000);

		$tester = $this->runConsoleCommand(new RunCron(), ['id' => 'jrTestExtended']);

		$this->assertSame(0, $tester->getStatusCode());
		$this->assertSame(['extended:jrTestExtended'], CronCallbacks::$calls);
	}

	public function test_an_error_is_logged_and_reported()
	{
		$this->fakesErrors();
		$this->insertCronEntry('jrTestBroken', 'throwError', 5000);

		$tester = $this->runConsoleCommand(new RunCron(), ['id' => 'jrTestBroken']);

		$this->assertSame(1, $tester->getStatusCode());
		$this->assertStringContainsString(
			'Exception running cron: cron callback failed for [jrTestBroken]',
			$tester->getDisplay()
		);
		$this->assertExceptionLogged(\TypeError::class);
	}

	public function test_refuses_an_unknown_entry()
	{
		$tester = $this->runConsoleCommand(new RunCron(), ['id' => 'jrTestNoSuchEntry']);

		$this->assertSame(1, $tester->getStatusCode());
		$this->assertStringContainsString('Could not find active cron entry', $tester->getDisplay());
	}

	public function test_refuses_an_inactive_entry_without_force()
	{
		$this->insertCronEntry('jrTestInactive', 'record', 5000, 0);

		$tester = $this->runConsoleCommand(new RunCron(), ['id' => 'jrTestInactive']);

		$this->assertSame(1, $tester->getStatusCode());
		$this->assertSame([], CronCallbacks::$calls);
	}

	public function test_force_runs_an_inactive_entry()
	{
		$this->insertCronEntry('jrTestForced', 'record', 5000, 0);

		$tester = $this->runConsoleCommand(new RunCron(), ['id' => 'jrTestForced', '--force' => true]);

		$this->assertSame(0, $tester->getStatusCode());
		$this->assertSame(['jrTestForced'], CronCallbacks::$calls);
	}

	public function test_refuses_an_entry_with_no_callback()
	{
		$this->insertCronEntry('jrTestNoMethod', 'noSuchMethod', 5000);

		$tester = $this->runConsoleCommand(new RunCron(), ['id' => 'jrTestNoMethod']);

		$this->assertSame(1, $tester->getStatusCode());
		$this->assertStringContainsString('Cron entry does not have a callback.', $tester->getDisplay());
	}
}
