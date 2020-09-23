<?php namespace Hampel\JobRunner;

use Hampel\JobRunner\SubContainer\JobRunner;
use XF\App;
use XF\Container;

class Listener
{
	public static function appSetup(App $app)
	{
		$container = $app->container();

		$container['job.hg.runner'] = function(Container $c) use ($app)
		{
			$class = $app->extendClass(JobRunner::class);
			return new $class($c, $app);
		};
	}
}