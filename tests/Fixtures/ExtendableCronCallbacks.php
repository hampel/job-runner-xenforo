<?php namespace Tests\Fixtures;

/**
 * CronCallbacks under a name only the class-extension test uses.
 *
 * An extension added during a test stays applied for the rest of the run: its proxy is a
 * class_alias(), which PHP cannot undo, and the test framework keeps the extended resolution
 * cached. Extending CronCallbacks itself would hand every later cron test the extended class.
 */
class ExtendableCronCallbacks extends CronCallbacks
{
}
