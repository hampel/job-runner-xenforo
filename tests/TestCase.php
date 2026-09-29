<?php namespace Tests;

use Hampel\JobRunner\Cli\Logger;
use Hampel\Testing\TestCase as BaseTestCase;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Tests\Fixtures\CronCallbacks;

abstract class TestCase extends BaseTestCase
{
	/**
	 * @var string $rootDir path to your XenForo root directory, relative to the addon path
	 *
	 * Set $rootDir to '../../../..' if you use a vendor in your addon id (ie <Vendor/AddonId>)
	 * Otherwise, set this to '../../..' for no vendor
	 *
	 * No trailing slash!
	 */
	protected $rootDir = '../../../..';

	/**
	 * @var array $addonsToLoad an array of XenForo addon ids to load
	 *
	 * Only these addons are loaded, which keeps other addons' class extensions, code event listeners
	 * and Composer autoloading out of the tests - including any copy of PHPUnit another addon vendors.
	 */
	protected $addonsToLoad = ['Hampel/JobRunner'];

	protected function setUp(): void
	{
		parent::setUp();

		CronCallbacks::$calls = [];

	}

	protected function getMockData($file)
	{
		return file_get_contents(__DIR__ . '/mock/' . $file);
	}

	/**
	 * Register a cli.logger the way Hampel\JobRunner\App and job-monitor:run-jobs do between them, writing to
	 * a buffer the test can read back.
	 */
	protected function useCliLogger($verbosity = OutputInterface::VERBOSITY_DEBUG): BufferedOutput
	{
		$output = new BufferedOutput($verbosity);

		$container = $this->app()->container();
		$container['cli.output'] = $output;
		$container['cli.logger'] = new Logger($output);

		return $output;
	}

	/**
	 * Insert a cron entry directly, bypassing the entity so no dev output is written and no cron job
	 * is queued. Use with UsesDatabaseTransactions.
	 *
	 * Keep next_run below the test time: real entries on the forum are due in the future, so with
	 * setTestTime() well in the past only entries inserted by the test are due.
	 */
	protected function insertCronEntry($entryId, $method, $nextRun, $active = 1, $class = CronCallbacks::class)
	{
		$this->app()->db()->insert('xf_cron_entry', [
			'entry_id' => $entryId,
			'cron_class' => $class,
			'cron_method' => $method,
			'run_rules' => json_encode(['day_type' => 'dom', 'dom' => [-1], 'hours' => [-1], 'minutes' => [0]]),
			'active' => $active,
			'next_run' => $nextRun,
			'addon_id' => '',
		]);
	}

	protected function cronNextRun($entryId): int
	{
		return intval($this->app()->db()->fetchOne(
			'SELECT next_run FROM xf_cron_entry WHERE entry_id = ?', $entryId
		));
	}

	/**
	 * Insert a job directly. Use with UsesDatabaseTransactions.
	 */
	protected function insertJob($uniqueKey, $executeClass, $triggerDate, $manual = 0): int
	{
		$this->app()->db()->insert('xf_job', [
			'unique_key' => $uniqueKey,
			'execute_class' => $executeClass,
			'execute_data' => serialize([]),
			'manual_execute' => $manual,
			'trigger_date' => $triggerDate,
		]);

		return intval($this->app()->db()->lastInsertId());
	}
}
