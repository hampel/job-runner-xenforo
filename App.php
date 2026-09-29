<?php namespace Hampel\JobRunner;

use Hampel\JobRunner\Cli\Logger;
use XF\Container;

class App extends \XF\Cli\App
{
	public function initializeExtra()
	{
		parent::initializeExtra();

		$container = $this->container;

		$container['cli.output'] = null; // we'll need to set this as to our OutputInterface from our Cli command before we call our logger

		$container['cli.logger'] = function (Container $c) {
			return new Logger($c['cli.output']);
		};
	}
}