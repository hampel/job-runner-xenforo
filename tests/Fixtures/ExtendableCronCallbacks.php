<?php namespace Tests\Fixtures;

/**
 * CronCallbacks under a name only the class-extension test resolves.
 *
 * The test framework caches extendClass() results for the whole run, so a class extension added in
 * one test is ignored if any earlier test has already resolved the base class - and every other
 * cron test resolves CronCallbacks. Extend this instead.
 */
class ExtendableCronCallbacks extends CronCallbacks
{
}
