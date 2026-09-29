<?php namespace Tests\Feature;

use Hampel\JobRunner\Setup;
use Tests\TestCase;

class SetupTest extends TestCase
{
	protected function makeSetup(): Setup
	{
		$addOn = $this->app()->addOnManager()->getById('Hampel/JobRunner');

		return new Setup($addOn, $this->app());
	}

	public function test_an_upgrade_on_xenforo_2_3_queues_the_file_clean_up()
	{
		if (\XF::$versionId < 2030000)
		{
			$this->markTestSkipped('Needs XenForo 2.3 or later.');
		}

		$this->fakesJobs();
		$state = [];

		$this->makeSetup()->postUpgrade(2010170, $state);

		$this->assertJobQueued(\XF\Job\FileCleanUp::class, function ($job) {
			return $job['execute_data']['addon_id'] == 'Hampel/JobRunner';
		});
	}

	public function test_an_upgrade_on_xenforo_2_2_queues_nothing()
	{
		// enqueuePostUpgradeCleanUp() does not exist before 2.3, so calling it there is a fatal
		$this->fakesJobs();
		$state = [];
		$this->setStaticProperty(\XF::class, 'versionId', 2021970);

		$this->makeSetup()->postUpgrade(2010170, $state);

		$this->assertNoJobsQueued();
	}

	/**
	 * Call Setup's lock-file clean-up directly. install(), upgrade() and uninstall() also create or
	 * drop the statistics table, and DDL cannot be rolled back, so no test calls them - they would
	 * change the forum's real schema. The sandbox upgrade test covers that path.
	 */
	protected function removeLegacyLockFile()
	{
		$setup = $this->makeSetup();
		$method = new \ReflectionMethod($setup, 'removeLegacyLockFile');
		$method->setAccessible(true);
		$method->invoke($setup);
	}

	public function test_the_lock_file_older_versions_left_is_removed()
	{
		$this->swapFs('internal-data');
		$this->app()->fs()->write('internal-data://run-jobs-lock.txt', '1790000000');

		$this->removeLegacyLockFile();

		$this->assertFsHasNot('internal-data://run-jobs-lock.txt');
	}

	public function test_no_lock_file_is_fine()
	{
		$this->swapFs('internal-data');

		$this->removeLegacyLockFile();

		$this->assertFsHasNot('internal-data://run-jobs-lock.txt');
	}

	public function test_upgrade_and_uninstall_both_remove_the_lock_file()
	{
		// read from the source rather than called: both also run DDL, see removeLegacyLockFile()
		$source = file_get_contents(__DIR__ . '/../../Setup.php');

		foreach (['upgrade', 'uninstall'] AS $method)
		{
			$this->assertMatchesRegularExpression(
				'/function ' . $method . '\(.*?\{[^}]*removeLegacyLockFile\(\)/s',
				$source,
				"{$method}() should remove the legacy lock file"
			);
		}
	}
}
