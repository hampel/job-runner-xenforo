<?php namespace Hampel\JobRunner\Util;

use Hampel\JobRunner\Cli\Logger;
use League\Flysystem\FileNotFoundException;
use League\Flysystem\FilesystemInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Lock
{
	/** @var string */
	protected $lockFile;

	/** @var FilesystemInterface */
	protected $fs;

	/** @var Logger */
	protected $logger;

	public function __construct($lockFile, FilesystemInterface $fs, Logger $logger = null)
	{
		$this->lockFile = $lockFile;
		$this->fs = $fs;
		$this->logger = $logger;
	}

	public function get($timeNow, $expiry)
	{
		if ($this->expired($timeNow))
		{
			return $this->write($timeNow, $expiry);
		}

		return false;
	}

	public function write($timeNow, $expiry)
	{
		// if not deleted, lock will expire 30 seconds after 'runtime.limit' seconds has passed (ie 630 seconds)
		$expiryTime = $timeNow + $expiry + 30;

		$lockInfo = ['expiryTime' => $expiryTime, 'expiryTime_formatted' => date("Y-m-d H:i:s T", $expiryTime)];

		if ($this->exists())
		{
			$this->log("updating lock", $lockInfo);
			return $this->fs->update($this->lockFile, $expiryTime);
		}
		else
		{
			$this->log("writing lock", $lockInfo);
			return $this->fs->write($this->lockFile, $expiryTime);
		}
	}

	public function read()
	{
		try
		{
			return intval($this->fs->read($this->lockFile));
		}
		catch (FileNotFoundException $e)
		{
			$this->log("read: could not find lockfile", ['lockFile' => $this->lockFile], OutputInterface::VERBOSITY_NORMAL);
			return 0;
		}
	}

	public function remove()
	{
		$this->log("removing lock");
		try
		{
			$this->fs->delete($this->lockFile);
		}
		catch (FileNotFoundException $e)
		{
			$this->log("remove: could not find lockfile", ['lockFile' => $this->lockFile], OutputInterface::VERBOSITY_NORMAL);
			throw $e;
		}
	}

	public function exists()
	{
		try
		{
			// If this path doesn't exist, then this will throw an exception. We need to handle this elsewhere.
			return $this->fs->has($this->lockFile);
		}
		catch (\Exception $e)
		{
			return false;
		}
	}

	public function expired($timenow)
	{
		if (!$this->exists())
		{
			return true;
		}

		$expiryTime = $this->read();

		return $expiryTime < $timenow;
	}

	protected function log($message, array $context = [], $verbosity = OutputInterface::VERBOSITY_DEBUG)
	{
		if (!isset($this->logger)) return;

		$this->logger->log(self::class, $message, $context, [], $verbosity);
	}
}
