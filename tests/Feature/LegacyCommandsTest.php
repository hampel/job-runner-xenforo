<?php namespace Tests\Feature;

use Hampel\JobRunner\Cli\Command;
use Hampel\JobRunner\Cli\Command\Legacy;
use Hampel\Testing\Concerns\UsesDatabaseTransactions;
use Tests\TestCase;

/**
 * The pre-3.0.0 hg: names: still runnable for the 3.x line, but hidden from the command list and
 * never shown against the new commands.
 */
class LegacyCommandsTest extends TestCase
{
	use UsesDatabaseTransactions;

	public static function legacyNames(): array
	{
		return [
			'hg:show-jobs' => [Legacy\ShowJobs::class, Command\ShowJobs::class, 'hg:show-jobs'],
			'hg:show-crons' => [Legacy\ShowCrons::class, Command\ShowCrons::class, 'hg:show-crons'],
			'hg:run-cron' => [Legacy\RunCron::class, Command\RunCron::class, 'hg:run-cron'],
			'hg:test-jobs' => [Legacy\TestJobs::class, Command\QueueTestJob::class, 'hg:test-jobs'],
		];
	}

	#[\PHPUnit\Framework\Attributes\DataProvider('legacyNames')]
	public function test_the_old_name_is_a_hidden_copy_of_the_new_command($legacy, $current, $name)
	{
		$command = new $legacy();

		$this->assertInstanceOf($current, $command);
		$this->assertSame($name, $command->getName());
		$this->assertTrue($command->isHidden());
	}

	#[\PHPUnit\Framework\Attributes\DataProvider('legacyNames')]
	public function test_the_new_command_carries_no_old_name($legacy, $current, $name)
	{
		// an alias is listed in front of the new command's description - which is what this removed
		$this->assertSame([], (new $current())->getAliases());
	}

	public function test_an_old_name_still_runs()
	{
		$tester = $this->runConsoleCommand(new Legacy\ShowCrons());

		$this->assertSame(0, $tester->getStatusCode());
		$this->assertMatchesRegularExpression('/\d+ active cron entries found/', $tester->getDisplay());
	}
}
