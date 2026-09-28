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
		$versionId = \XF::$versionId;

		// setStaticProperty() does not restore the value after the test, so restore it by hand
		$this->setStaticProperty(\XF::class, 'versionId', 2021970);
		try
		{
			$this->makeSetup()->postUpgrade(2010170, $state);
		}
		finally
		{
			$this->setStaticProperty(\XF::class, 'versionId', $versionId);
		}

		$this->assertNoJobsQueued();
	}

	public function test_install_and_uninstall_change_nothing()
	{
		$this->fakesJobs();

		$this->makeSetup()->install();
		$this->makeSetup()->uninstall();

		$this->assertNoJobsQueued();
	}
}
