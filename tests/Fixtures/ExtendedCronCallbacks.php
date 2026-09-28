<?php namespace Tests\Fixtures;

/**
 * A class extension of ExtendableCronCallbacks, as another add-on would register one.
 *
 * XFCP_ExtendedCronCallbacks only exists once XenForo's extension system has resolved
 * ExtendableCronCallbacks, so this file must not be loaded before a test has called
 * addClassExtension() and extendClass().
 */
class ExtendedCronCallbacks extends XFCP_ExtendedCronCallbacks
{
	public static function record(\XF\Entity\CronEntry $entry)
	{
		static::$calls[] = 'extended:' . $entry->entry_id;
	}
}
