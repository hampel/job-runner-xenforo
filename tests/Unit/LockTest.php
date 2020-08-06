<?php namespace Tests\Unit;

use Hampel\JobRunner\Util\Lock;
use League\Flysystem\FileNotFoundException;
use Tests\TestCase;

class LockTest extends TestCase
{
	public function test_write_writes_file()
	{
		$this->swapFs('internal-data');
		$time = time();
		$this->setTestTime($time);

		$this->assertFsHasNot(Lock::$lockFile);

		Lock::write(10);
		$this->assertFsHas(Lock::$lockFile);
		$this->assertEquals($time + 10 + 30, \XF::fs()->read(Lock::$lockFile));
	}

	public function test_write_updates_file_when_lock_exists()
	{
		$this->swapFs('internal-data');
		$time = time();
		$this->setTestTime($time);

		$this->assertFsHasNot(Lock::$lockFile);
		\XF::fs()->write(Lock::$lockFile, 'foo');
		$this->assertFsHas(Lock::$lockFile);

		Lock::write(10);
		$this->assertFsHas(Lock::$lockFile);
		$this->assertEquals($time + 10 + 30, \XF::fs()->read(Lock::$lockFile));
	}

	public function test_read_returns_time()
	{
		$time = time();
		$this->setTestTime($time);
		$this->swapFs('internal-data');

		Lock::write(5);
		$this->assertEquals($time + 5 + 30, Lock::read());
	}

	public function test_read_returns_zero_for_bad_data()
	{
		$this->swapFs('internal-data');
		$this->app()->fs()->write(Lock::$lockFile, 'foo');

		$this->assertEquals(0, Lock::read());
	}

	public function test_read_returns_zero_when_file_not_found()
	{
		$this->swapFs('internal-data');

		$this->assertFsHasNot(Lock::$lockFile);
		$this->assertEquals(0, Lock::read());
	}

	public function test_remove_removes_file()
	{
		$this->swapFs('internal-data');
		Lock::write(0);
		$this->assertFsHas(Lock::$lockFile);
		Lock::remove();
		$this->assertFsHasNot(Lock::$lockFile);
	}

	public function test_remove_throws_exception_when_file_not_found()
	{
		$this->swapFs('internal-data');
		$this->assertFsHasNot(Lock::$lockFile);

		$this->expectException(FileNotFoundException::class);
		Lock::remove();
	}

	public function test_exists_returns_false_when_no_file_exists()
	{
		$this->swapFs('internal-data');
		$this->assertFsHasNot(Lock::$lockFile);

		$this->assertFalse(Lock::exists());
	}

	public function test_exists_returns_true_when_file_exists()
	{
		$this->swapFs('internal-data');

		Lock::write(0);
		$this->assertFsHas(Lock::$lockFile);

		$this->assertTrue(Lock::exists());
	}

	public function test_expired_returns_true_file_does_not_exist()
	{
		$this->swapFs('internal-data');

		$this->assertFsHasNot(Lock::$lockFile);
		$this->assertTrue(Lock::expired());
	}

	public function test_expired_returns_false_when_max_time_not_expired()
	{
		$this->swapFs('internal-data');

		$time = time();
		$this->setTestTime($time);

		Lock::write(10);

		$time2 = $time + 5;
		$this->setTestTime($time);

		// check file still has original time
		$this->assertEquals($time + 10 + 30, Lock::read());
		$this->assertFalse(Lock::expired());
	}

	public function test_expired_returns_true_when_max_time_has_expired()
	{
		$this->swapFs('internal-data');

		$time = time();
		$this->setTestTime($time);

		Lock::write(10);

		$time2 = $time + 50;
		$this->setTestTime($time2);

		// check file still has original time
		$this->assertEquals($time + 10 + 30, Lock::read());
		$this->assertTrue(Lock::expired());
	}

}
