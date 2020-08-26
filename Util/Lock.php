<?php namespace Hampel\JobRunner\Util;

use League\Flysystem\FileNotFoundException;
use Symfony\Component\Console\Output\OutputInterface;

class Lock
{
	public static $lockFile = 'internal-data://run-jobs-lock.txt';

	public static function get($maxRunTime)
	{
		if (self::expired())
		{
			return self::write($maxRunTime);
		}

		return false;
	}

	public static function write($maxRunTime)
	{
		// if not deleted, lock will expire 30 seconds after $maxRunTime seconds has passed
		$lockUntil = \XF::$time + $maxRunTime + 30;
		if (self::exists())
		{
			self::log("updating lock", ['lockUntil' => $lockUntil, 'lockUntil_formatted' => date("Y-m-d H:i:s T", $lockUntil)]);
			return \XF::fs()->update(self::$lockFile, $lockUntil);
		}
		else
		{
			self::log("writing lock", ['lockUntil' => $lockUntil, 'lockUntil_formatted' => date("Y-m-d H:i:s T", $lockUntil)]);
			return \XF::fs()->write(self::$lockFile, $lockUntil);
		}
	}

	public static function read()
	{
		try
		{
			return intval(\XF::fs()->read(self::$lockFile));
		}
		catch (FileNotFoundException $e)
		{
			return 0;
		}
	}

	public static function remove()
	{
		self::log("removing lock");
		\XF::fs()->delete(self::$lockFile);
	}

	public static function exists()
	{
		try
		{
			// If this path doesn't exist, then this will throw an exception. We need to handle this elsewhere.
			return \XF::fs()->has(self::$lockFile);
		}
		catch (\Exception $e)
		{
			return false;
		}
	}

	public static function expired()
	{
		if (!self::exists())
		{
			return true;
		}

		$runUntil = self::read();

		return $runUntil < \XF::$time;
	}

	protected static function log($message, array $context = [])
	{
		$app = \XF::app();

		// check to see if we actually have a logger available and abort if not
		if (!isset($app['cli.logger'])) return;

		/** @var Logger $logger */
		$logger = $app['cli.logger'];
		$logger->log(self::class, $message, $context, [], OutputInterface::VERBOSITY_DEBUG);
	}
}
