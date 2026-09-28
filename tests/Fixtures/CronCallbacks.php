<?php namespace Tests\Fixtures;

use XF\Entity\CronEntry;

/**
 * Cron callbacks for tests. Each call is recorded in $calls, by entry id, so a test can tell which
 * entries ran and in what order.
 */
class CronCallbacks
{
	public static $calls = [];

	public static function record(CronEntry $entry)
	{
		static::$calls[] = $entry->entry_id;
	}

	public static function throwError(CronEntry $entry)
	{
		static::$calls[] = $entry->entry_id;

		// an \Error, not an \Exception - what a cron callback throws when it breaks on a newer PHP
		throw new \TypeError("cron callback failed for [{$entry->entry_id}]");
	}
}
