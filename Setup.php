<?php namespace Hampel\JobRunner;

use XF\AddOn\AbstractSetup;

class Setup extends AbstractSetup
{
	/** hourly job and cron statistics - see Repository\Stats */
	const STATS_TABLE = 'xf_hampel_jobrunner_stat';

	public function install(array $stepParams = [])
	{
		$this->createStatsTable();
	}

	public function upgrade(array $stepParams = [])
	{
		$this->createStatsTable();
		$this->removeLegacyLockFile();
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