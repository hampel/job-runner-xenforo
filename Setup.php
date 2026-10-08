<?php namespace Hampel\JobRunner;

use XF\AddOn\AbstractSetup;

class Setup extends AbstractSetup
{
	/** hourly job and cron statistics - see Repository\Stats */
	const STATS_TABLE = 'xf_hampel_jobrunner_stat';

	/**
	 * Files earlier versions shipped and this one does not, relative to the add-on's directory.
	 * XenForo 2.2 never deletes a file on upgrade, and one left in Cli/Command is still a command.
	 */
	const REMOVED_FILES = [
		'Cli/Command/HealthCheck.php',
		'Cli/Command/TestJobs.php',
		'Listener.php',
		'SubContainer/JobRunner.php',
		'Util/Lock.php',
		'XF/Job/Cron.php',
	];

	public function install(array $stepParams = [])
	{
		$this->createStatsTable();
	}

	public function upgrade(array $stepParams = [])
	{
		$this->createStatsTable();
		$this->removeLegacyLockFile();
		$this->removeFilesFromEarlierVersions();
	}

    public function postUpgrade($previousVersion, array &$stateChanges)
    {
        if (\XF::$versionId >= 2030000) { // XF 2.3+
            $this->enqueuePostUpgradeCleanUp();
        }
    }

	public function uninstall(array $stepParams = [])
	{
		$this->app->db()->getSchemaManager()->dropTable(self::STATS_TABLE);
		$this->removeLegacyLockFile();
	}

	/**
	 * One row per job class or cron entry per hour. Created on install, and on every upgrade when
	 * missing, so an upgrade from any earlier version gets it and a repeated upgrade is harmless.
	 */
	protected function createStatsTable()
	{
		$schema = $this->app->db()->getSchemaManager();

		if ($schema->tableExists(self::STATS_TABLE))
		{
			return;
		}

		$schema->createTable(self::STATS_TABLE, function (\XF\Db\Schema\Create $table)
		{
			$table->addColumn('type', 'varbinary', 10);
			$table->addColumn('name', 'varchar', 100);
			$table->addColumn('period_start', 'int')->unsigned();
			$table->addColumn('runs', 'int')->unsigned()->setDefault(0);
			$table->addColumn('total_ms', 'int')->unsigned()->setDefault(0);
			$table->addColumn('max_ms', 'int')->unsigned()->setDefault(0);
			$table->addColumn('failures', 'int')->unsigned()->setDefault(0);
			$table->addPrimaryKey(['type', 'name', 'period_start']);
			$table->addKey('period_start');
		});
	}

	/**
	 * Versions before 3.0.0 locked hg:run-jobs with this file, which a crashed run could leave behind.
	 * Nothing reads it any more. Safe to call on every upgrade.
	 */
	protected function removeLegacyLockFile()
	{
		$lockFile = 'internal-data://run-jobs-lock.txt';

		try
		{
			$fs = $this->app->fs();
			if ($fs->has($lockFile))
			{
				$fs->delete($lockFile);
			}
		}
		catch (\Throwable $e)
		{
			// a leftover file is harmless - never fail an upgrade over it
		}
	}

	/**
	 * Delete the files in REMOVED_FILES. XenForo 2.3 clears them with its own clean-up job after an
	 * upgrade; 2.2 has none, so a command class dropped from a release stays in the command list
	 * there, running the old code. Safe to call on every upgrade: a development checkout has none
	 * of them.
	 *
	 * @param string|null $directory the add-on's directory, when it is not this file's
	 */
	protected function removeFilesFromEarlierVersions($directory = null)
	{
		$directory = rtrim($directory ?: __DIR__, '/\\');

		foreach (self::REMOVED_FILES AS $file)
		{
			$path = $directory . '/' . $file;

			try
			{
				if (is_file($path))
				{
					unlink($path);
				}
			}
			catch (\Throwable $e)
			{
				// a leftover file is not worth failing an upgrade over
			}
		}
	}

	/**
	 * Perform additional requirement checks.
	 *
	 * @param array $errors Errors will block the setup from continuing
	 * @param array $warnings Warnings will be displayed but allow the user to continue setup
	 *
	 * @return void
	 */
	public function checkRequirements(&$errors = [], &$warnings = [])
	{
		// Note: need to include this file even though we don't do anything otherwise upgrade from earlier version breaks

		// Nothing to do
	}
}