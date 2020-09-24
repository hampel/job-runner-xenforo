<?php namespace Tests\Unit;

use Hampel\JobRunner\SubContainer\JobRunner;
use Hampel\JobRunner\Util\Lock;
use Tests\TestCase;

class SubContainerTest extends TestCase
{
	/** @var JobRunner */
	protected $jobRunner;

	public function setUp() : void
	{
		parent::setUp();

		$this->jobRunner = new JobRunner($this->app()->container(), $this->app());
	}

	public function test_initialisation()
	{
		$this->assertEquals(600, $this->jobRunner->getRuntimeLimit());
		$this->assertInstanceOf(Lock::class, $this->jobRunner->lock());
	}

	public function test_getMaxQueueRunTime()
	{
		$this->assertEquals(100, $this->jobRunner->getMaxQueueRunTime(100));
		$this->assertEquals(600, $this->jobRunner->getMaxQueueRunTime(600));
		$this->assertEquals(600, $this->jobRunner->getMaxQueueRunTime(700));
	}
}
