<?php namespace Tests\Unit;

use Hampel\JobRunner\Cli\Command\Validate;
use Symfony\Component\Console\Command\Command;
use Tests\TestCase;

/**
 * XenForo loads every add-on's command classes to list them, so one that cannot load stops cmd.php
 * for the whole forum. Every file under Cli/Command is checked, so a new command is covered here
 * without being named.
 */
class CommandClassesTest extends TestCase
{
	/**
	 * @return string[] every command class the add-on ships, each already proved loadable
	 */
	protected function commandClasses(): array
	{
		return $this->assertConsoleCommandsLoad();
	}

	public function test_every_command_class_is_one_xenforo_can_load()
	{
		$classes = $this->commandClasses();

		// the walk should have found these, or it is looking in the wrong place
		$this->assertContains(Validate::class, $classes);
		$this->assertGreaterThanOrEqual(14, count($classes));

		foreach ($classes AS $class)
		{
			$this->assertNotEmpty((new $class())->getName(), "{$class} has no name");
		}
	}

	public function test_no_command_depends_on_a_base_class_missing_from_xenforo_2_2()
	{
		foreach ($this->commandClasses() AS $class)
		{
			$this->assertNotContains(
				'XF\Cli\Command\AbstractCommand',
				array_values(class_parents($class)),
				"{$class} extends XF\\Cli\\Command\\AbstractCommand, which XenForo 2.2 does not have"
			);
		}
	}

	public function test_no_command_redefines_run()
	{
		foreach ($this->commandClasses() AS $class)
		{
			$this->assertSame(
				Command::class,
				(new \ReflectionMethod($class, 'run'))->getDeclaringClass()->getName(),
				"{$class} defines run(), which collides with Symfony's Command::run()"
			);
		}
	}
}
