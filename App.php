<?php namespace Hampel\JobRunner;


class App extends \XF\Cli\App
{
	public function initializeExtra()
	{
		parent::initializeExtra();

		$container = $this->container;

		/**
		 * Make sure we never run jobs automatically - make next job.runTime greater than the current time
		 *
		 * This also avoids having the autoJobRun registry lookup fail, causing database queries when running
		 * \XF\Job\Manager::updateNextRunTime
		 */
		$container['job.runTime'] = \XF::$time + (10*60); // make sure we never run jobs automatically - add 10 minutes to next run time!
	}
}