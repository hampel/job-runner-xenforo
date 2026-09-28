<?php namespace Tests\Unit;

use Hampel\JobRunner\Listener;
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

		$this->swapFs('internal-data');

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

	public function test_the_lock_is_the_run_jobs_lock_file()
	{
		$this->assertFalse($this->jobRunner->lockExists());

		$this->assertTrue($this->jobRunner->getLock());

		$this->assertFsHas('internal-data://run-jobs-lock.txt');
		$this->assertTrue($this->jobRunner->lockExists());
	}

	public function test_the_lock_expires_thirty_seconds_after_the_runtime_limit()
	{
		$this->setTestTime(100000);

		$this->jobRunner->getLock();

		$this->assertEquals(100630, $this->jobRunner->lock()->read());
	}

	public function test_a_second_runner_cannot_take_the_lock()
	{
		$this->assertTrue($this->jobRunner->getLock());
		$this->assertFalse($this->jobRunner->getLock());
	}

	public function test_removing_the_lock_lets_the_next_runner_in()
	{
		$this->jobRunner->getLock();
		$this->jobRunner->removeLock();

		$this->assertFalse($this->jobRunner->lockExists());
		$this->assertTrue($this->jobRunner->getLock());
	}

	public function test_the_listener_registers_the_sub_container()
	{
		$container = $this->app()->container();
		$this->assertFalse(isset($container['job.hg.runner']));

		Listener::appCliSetup($this->app());

		$this->assertInstanceOf(JobRunner::class, $container['job.hg.runner']);
		$this->assertSame($container['job.hg.runner'], $container['job.hg.runner']);
	}
}
