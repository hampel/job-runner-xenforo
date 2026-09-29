<?php namespace Tests\Unit;

use Hampel\JobRunner\Cli\Logger;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Tests\TestCase;
use XF\Job\JobResult;

class LoggerTest extends TestCase
{
	protected function logger($verbosity): array
	{
		$output = new BufferedOutput($verbosity);

		return [new Logger($output), $output];
	}

	protected function testJob()
	{
		return $this->app()->job('Hampel\JobRunner:TestJob', 7, ['email' => 'someone@example.com']);
	}

	protected function cronEntry()
	{
		$entry = $this->makeEntity('XF:CronEntry', [
			'cron_class' => 'XF\Cron\CleanUp',
			'cron_method' => 'runHourlyCleanUp',
			'next_run' => 1000,
		]);
		$entry->setTrusted('entry_id', 'jrTestLogged');

		return $entry;
	}

	protected function jobRow(): array
	{
		return [
			'job_id' => 7,
			'execute_class' => 'Hampel\JobRunner:TestJob',
			'execute_data' => serialize(['email' => 'someone@example.com']),
			'trigger_date' => 1000,
			'last_run_date' => null,
		];
	}

	public function test_a_logger_without_output_does_nothing()
	{
		// cli.output is null until job-monitor:run-jobs sets it, so this has to be a no-op, not a TypeError
		$logger = new Logger(null);

		$logger->log('Foo', 'message', ['a' => 1]);
		$logger->debug('Foo', 'message');
		$logger->logJobStart('Foo', $this->jobRow());
		$logger->logJobProgress($this->testJob(), 'progress');
		$logger->logJobCompletion('Foo', 'Hampel\JobRunner:TestJob', JobResult::newComplete(7));
		$logger->logCronStart('Foo', $this->cronEntry());

		$this->assertFalse($logger->isVeryVerbose());
	}

	public function test_nothing_is_written_at_normal_verbosity()
	{
		[$logger, $output] = $this->logger(OutputInterface::VERBOSITY_NORMAL);

		$logger->log('Foo', 'hello');
		$logger->logJobStart('Foo', $this->jobRow());
		$logger->logCronStart('Foo', $this->cronEntry());

		$this->assertSame('', $output->fetch());
	}

	public function test_verbose_writes_the_message_without_its_context()
	{
		[$logger, $output] = $this->logger(OutputInterface::VERBOSITY_VERBOSE);

		$logger->log('Foo', 'hello', ['secret' => 'context']);

		$log = $output->fetch();
		$this->assertMatchesRegularExpression('/^\[\d{4}-\d\d-\d\d \d\d:\d\d:\d\d\] Foo: hello$/m', $log);
		$this->assertStringNotContainsString('context', $log);
	}

	public function test_very_verbose_adds_the_context_as_json()
	{
		[$logger, $output] = $this->logger(OutputInterface::VERBOSITY_VERY_VERBOSE);

		$logger->log('Foo', 'hello', ['key' => 'value'], ['extra' => 'hidden']);

		$log = $output->fetch();
		$this->assertStringContainsString('Foo: hello {"key":"value"}', $log);
		$this->assertStringNotContainsString('hidden', $log);
		$this->assertTrue($logger->isVeryVerbose());
	}

	public function test_debug_adds_the_extra_payload()
	{
		[$logger, $output] = $this->logger(OutputInterface::VERBOSITY_DEBUG);

		$logger->log('Foo', 'hello', ['key' => 'value'], ['extra' => 'shown']);

		$this->assertStringContainsString('Foo: hello {"key":"value"} {"extra":"shown"}', $output->fetch());
	}

	/**
	 * Literal OutputInterface::VERBOSITY_* values: a data provider runs before XenForo boots, and
	 * Symfony Console is XenForo's dependency, so not even a class constant of it can be read here.
	 */
	public static function debugVerbosities(): array
	{
		return [
			'verbose (64)' => [64, false],
			'very verbose (128)' => [128, false],
			'debug (256)' => [256, true],
		];
	}

	#[DataProvider('debugVerbosities')]
	public function test_debug_messages_need_debug_verbosity($verbosity, $shown)
	{
		[$logger, $output] = $this->logger($verbosity);

		$logger->debug('Foo', 'debug line');

		$this->assertSame($shown, strpos($output->fetch(), 'debug line') !== false);
	}

	public function test_job_start_logs_the_unserialized_job_data()
	{
		[$logger, $output] = $this->logger(OutputInterface::VERBOSITY_VERY_VERBOSE);

		$logger->logJobStart('Foo', $this->jobRow());

		$log = $output->fetch();
		$this->assertStringContainsString('Starting Job [Hampel\JobRunner:TestJob]', $log);
		$this->assertStringContainsString('"execute_data":{"email":"someone@example.com"}', $log);
	}

	public function test_job_progress_names_the_job_by_its_short_name()
	{
		[$logger, $output] = $this->logger(OutputInterface::VERBOSITY_DEBUG);

		$logger->logJobProgress($this->testJob(), 'halfway');

		$log = $output->fetch();
		$this->assertStringContainsString('Hampel\JobRunner:TestJob: halfway', $log);
		$this->assertStringContainsString('"job_id":7', $log);
	}

	public function test_job_progress_writes_nothing_below_debug_by_default()
	{
		[$logger, $output] = $this->logger(OutputInterface::VERBOSITY_VERY_VERBOSE);

		$logger->logJobProgress($this->testJob(), 'halfway');

		$this->assertSame('', $output->fetch());
	}

	public function test_job_completion_reports_the_execution_time()
	{
		[$logger, $output] = $this->logger(OutputInterface::VERBOSITY_VERBOSE);

		$logger->logJobStart('Foo', $this->jobRow());
		$logger->logJobCompletion('Foo', 'Hampel\JobRunner:TestJob', JobResult::newComplete(7));

		$this->assertMatchesRegularExpression(
			'/Job \[Hampel\\\\JobRunner:TestJob\] executed in [0-9.]+ seconds/',
			$output->fetch()
		);
	}

	public function test_cron_start_names_the_entry()
	{
		[$logger, $output] = $this->logger(OutputInterface::VERBOSITY_VERBOSE);

		$logger->logCronStart('Foo', $this->cronEntry());

		$this->assertStringContainsString('Executing cron entry [jrTestLogged]', $output->fetch());
	}
}
