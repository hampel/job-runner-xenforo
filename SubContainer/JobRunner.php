<?php namespace Hampel\JobRunner\SubContainer;

use Hampel\JobRunner\Util\Lock;
use XF\SubContainer\AbstractSubContainer;

class JobRunner extends AbstractSubContainer
{
	public function initialize()
	{
		$container = $this->container;

		$container['lock.file'] = 'internal-data://run-jobs-lock.txt';

		$container['lock'] = function($c)
		{
			return new Lock(
				$this->container['lock.file'],
				$this->app->fs(),
				$this->parent['cli.logger'] ?? null
			);
		};

		$container['runtime.limit'] = 600; // 10 minutes max runtime limit
	}

	public function getRuntimeLimit()
	{
		return $this->container['runtime.limit'];
	}

	public function getMaxQueueRunTime($maxExecutionTime)
	{
		$runtimeLimit = $this->getRuntimeLimit();

		return $maxExecutionTime > $runtimeLimit ? $runtimeLimit : $maxExecutionTime;
	}

	/**
	 * @return Lock
	 */
	public function lock()
	{
		return $this->container['lock'];
	}

	public function getLock()
	{
		return $this->lock()->get(\XF::$time, $this->getRuntimeLimit());
	}

	public function removeLock()
	{
		return $this->lock()->remove();
	}

	public function lockExists()
	{
		return $this->lock()->exists();
	}
}
