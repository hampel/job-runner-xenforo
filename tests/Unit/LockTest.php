<?php namespace Tests\Unit;

use Hampel\JobRunner\Cli\Logger;
use Hampel\JobRunner\Util\Lock;
use League\Flysystem\FileNotFoundException;
use Mockery as m;
use Tests\TestCase;

class LockTest extends TestCase
{
	protected $lockFile = 'internal-data://foo.lock';

	/** @var Lock */
	protected $lock;

	/** @var Logger */
	protected $logger;

	public function setUp() : void
	{
		parent::setUp();

		$this->swapFs('internal-data');

		$this->logger = m::mock(Logger::class);

		$this->lock = new Lock($this->lockFile, $this->app()->fs(), $this->logger);
	}

	public function test_write_writes_file()
	{
		$this->assertFsHasNot($this->lockFile);

		$this->logger->expects()->log('Hampel\JobRunner\Util\Lock', 'writing lock', m::any(), [], m::any())->once();

		$this->lock->write(100000, 600);
		$this->assertFsHas($this->lockFile);
		$this->assertEquals(100630, \XF::fs()->read($this->lockFile));
	}

	public function test_write_updates_file_when_lock_exists()
	{
		$this->logger->expects()->log('Hampel\JobRunner\Util\Lock', 'updating lock', m::any(), [], m::any())->once();

		$this->assertFsHasNot($this->lockFile);
		\XF::fs()->write($this->lockFile, 'foo');
		$this->assertFsHas($this->lockFile);

		$this->lock->write(100000, 600);
		$this->assertFsHas($this->lockFile);
		$this->assertEquals(100630, \XF::fs()->read($this->lockFile));
	}

	public function test_read_returns_time()
	{
		$this->logger->expects()->log('Hampel\JobRunner\Util\Lock', 'writing lock', m::any(), [], m::any())->once();

		$this->lock->write(100000, 500);
		$this->assertEquals(100530, $this->lock->read());
	}

	public function test_read_returns_zero_for_bad_data()
	{
		$this->app()->fs()->write($this->lockFile, 'foo');

		$this->assertEquals(0, $this->lock->read());
	}

	public function test_read_returns_zero_when_file_not_found()
	{
		$this->logger->expects()->log('Hampel\JobRunner\Util\Lock', 'read: could not find lockfile', ['lockFile' => 'internal-data://foo.lock'], [], m::any())->once();

		$this->assertFsHasNot($this->lockFile);
		$this->assertEquals(0,  $this->lock->read());
	}

	public function test_remove_removes_file()
	{
		$this->logger->expects()->log('Hampel\JobRunner\Util\Lock', 'removing lock', [], [], m::any())->once();

		$this->app()->fs()->write($this->lockFile, 'foo');
		$this->assertFsHas($this->lockFile);
		$this->lock->remove();
		$this->assertFsHasNot($this->lockFile);
	}

	public function test_remove_throws_exception_when_file_not_found()
	{
		$this->logger->expects()->log('Hampel\JobRunner\Util\Lock', 'removing lock', [], [], m::any())->once();
		$this->logger->expects()->log('Hampel\JobRunner\Util\Lock', 'remove: could not find lockfile', ['lockFile' => 'internal-data://foo.lock'], [], m::any())->once();

		$this->assertFsHasNot($this->lockFile);

		$this->expectException(FileNotFoundException::class);
		$this->lock->remove();
	}

	public function test_exists_returns_false_when_no_file_exists()
	{
		$this->assertFsHasNot($this->lockFile);

		$this->assertFalse($this->lock->exists());
	}

	public function test_exists_returns_true_when_file_exists()
	{
		$this->app()->fs()->write($this->lockFile, 'foo');
		$this->assertFsHas($this->lockFile);

		$this->assertTrue($this->lock->exists());
	}

	public function test_expired_returns_true_file_does_not_exist()
	{
		$this->assertFsHasNot($this->lockFile);
		$this->assertTrue($this->lock->expired(100000));
	}

	public function test_expired_returns_false_when_max_time_not_expired()
	{
		$this->logger->expects()->log('Hampel\JobRunner\Util\Lock', 'writing lock', m::any(), [], m::any())->once();

		$this->lock->write(100000, 400);

		// check file still has original time
		$this->assertEquals(100430, $this->lock->read());
		$this->assertFalse($this->lock->expired(100300));
	}

	public function test_expired_returns_true_when_max_time_has_expired()
	{
		$this->logger->expects()->log('Hampel\JobRunner\Util\Lock', 'writing lock', m::any(), [], m::any())->once();

		$this->lock->write(100000, 300);

		// check file still has original time
		$this->assertEquals(100330, $this->lock->read());
		$this->assertTrue($this->lock->expired(100700));
	}

}
