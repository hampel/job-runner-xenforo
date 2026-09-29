<?php namespace Hampel\JobRunner;

use XF\AddOn\AbstractSetup;

class Setup extends AbstractSetup
{
	public function install(array $stepParams = [])
	{
		// Nothing to do
	}

	public function upgrade(array $stepParams = [])
	{
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
		$this->removeLegacyLockFile();
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