<?php namespace Hampel\JobRunner;

use XF\AddOn\AbstractSetup;
use XF\Db\Schema\Create;

class Setup extends AbstractSetup
{
	public function install(array $stepParams = [])
	{
		// Nothing to do
	}

	public function upgrade(array $stepParams = [])
	{
		// Nothing to do
	}

	public function uninstall(array $stepParams = [])
	{
		// Nothing to do
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
		if (\XF::$versionId >= 2020000)
		{
			$errors[] = 'This version of Hampel/JobRunner is not compatible with XenForo v2.2 - please install v2.x of the JobRunner addon';
		}
	}
}