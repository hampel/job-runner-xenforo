<?php namespace Tests\Unit;

use Hampel\JobRunner\Cli\Command\Validate;
use Symfony\Component\Console\Command\Command;
use Tests\TestCase;
use XF\Cli\Runner;

/**
 * XenForo loads every add-on's command classes to list them, so one that cannot load stops cmd.php
 * for the whole forum. Every file under Cli/Command is checked, so a new command is covered here
 * without being named.
 */
class CommandClassesTest extends TestCase
{
	/**
	 * @return string[] class names, found the way XenForo's runner finds them
	 */
	protected function commandClasses(): array
	{
		$root = dirname(__DIR__, 2) . '/Cli/Command';
		$classes = [];

		$files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
		foreach ($files AS $file)
		{
			if ($file->getExtension() != 'php')
			{
				continue;
			}

			$local = substr($file->getPathname(), strlen($root) + 1, -4);
			$classes[] = 'Hampel\JobRunner\Cli\Command\\' . str_replace('/', '\\', $local);
		}

		sort($classes);

		return $classes;
	}

	public function test_the_command_classes_are_found()
	{
		$classes = $this->commandClasses();

		// a walk that finds nothing would make every test below pass
		$this->assertContains(Validate::class, $classes);
		$this->assertGreaterThanOrEqual(13, count($classes));
	}

	public function test_every_command_class_is_one_xenforo_can_load()
	{
		$runner = (new \ReflectionClass(Runner::class))->newInstanceWithoutConstructor();
		$isValid = new \ReflectionMethod(Runner::class, 'isValidCommandClass');
		$isValid->setAccessible(true);

		foreach ($this->commandClasses() AS $class)
		{
			$this->assertTrue($isValid->invoke($runner, $class), "{$class} is not a command XenForo can load");
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
