<?php namespace Hampel\JobRunner;

use Hampel\JobRunner\Cli\Logger;
use XF\Container;

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

		$container['cli.output'] = null; // we'll need to set this as to our OutputInterface from our Cli command before we call our logger

		$container['cli.logger'] = function (Container $c) {
			return new Logger($c['cli.output']);
		};
	}
}