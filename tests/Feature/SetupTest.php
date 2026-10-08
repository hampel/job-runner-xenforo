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

	public function test_an_upgrade_removes_the_files_earlier_versions_shipped()
	{
		$dir = sys_get_temp_dir() . '/jobrunner-setup-' . uniqid();
		mkdir($dir . '/Cli/Command', 0777, true);
		file_put_contents($dir . '/Cli/Command/HealthCheck.php', '<?php // the 3.0.x command');
		file_put_contents($dir . '/Cli/Command/Validate.php', '<?php // the current command');

		$setup = $this->makeSetup();
		$method = new \ReflectionMethod($setup, 'removeFilesFromEarlierVersions');
		$method->setAccessible(true);
		$method->invoke($setup, $dir);

		$this->assertFileDoesNotExist($dir . '/Cli/Command/HealthCheck.php');
		$this->assertFileExists($dir . '/Cli/Command/Validate.php', 'only the listed files may go');

		unlink($dir . '/Cli/Command/Validate.php');
		rmdir($dir . '/Cli/Command');
		rmdir($dir . '/Cli');
		rmdir($dir);
	}

	public function test_no_file_the_release_ships_is_on_the_removal_list()
	{
		foreach (\Hampel\JobRunner\Setup::REMOVED_FILES AS $file)
		{
			$this->assertFileDoesNotExist(dirname(__DIR__, 2) . '/' . $file, "{$file} is listed for removal but exists");
		}
	}

	public function test_upgrade_removes_those_files()
	{
		$source = file_get_contents(dirname(__DIR__, 2) . '/Setup.php');
		preg_match('/public function upgrade\(.*?\n\t}/s', $source, $upgrade);

		$this->assertStringContainsString('removeFilesFromEarlierVersions()', $upgrade[0]);
	}
}
