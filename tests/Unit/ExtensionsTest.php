<?php namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The add-on's two class extensions are registered, on the core classes it declares, and the ones
 * it removed in 3.0.0 are gone.
 */
class ExtensionsTest extends TestCase
{
	public static function extensions(): array
	{
		// either spelling is right: XenForo 2.3 renamed CalculateNextRun and aliases the old name
		return [
			'Manager' => ['Hampel\JobRunner\XF\Job\Manager', ['XF\Job\Manager']],
			'CalculateNextRun' => ['Hampel\JobRunner\XF\Service\CronEntry\CalculateNextRun', [
				'XF\Service\CronEntry\CalculateNextRun',
				'XF\Service\CronEntry\CalculateNextRunService',
			]],
		];
	}

	#[DataProvider('extensions')]
	public function test_each_extension_is_registered_on_the_class_it_extends($extension, array $bases)
	{
		$base = $this->app()->extension()->classExtendedBy($extension);

		$this->assertNotNull($base, "{$extension} is not registered as a class extension");
		$this->assertContains($base, $bases);
	}

	public function test_cron_is_no_longer_extended()
	{
		// 3.0.0 dropped the copy of Cron::run(); XenForo's own class runs
		$this->assertNull($this->app()->extension()->classExtendedBy('Hampel\JobRunner\XF\Job\Cron'));
	}
}
