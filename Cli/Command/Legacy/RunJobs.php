<?php namespace Hampel\JobRunner\Cli\Command\Legacy;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * hg:run-jobs, kept only so that crontab entries written for versions before 3.0.0 keep working.
 *
 * It runs XenForo's own xf:run-jobs with the same options, so a cron run behaves exactly as core's
 * does: no lock, no logging, the same output and exit code. It deliberately prints nothing of its
 * own - cron mails any output - and instead records when it was last used, so the add-on can tell
 * the admin to point the crontab at xf:run-jobs instead.
 */
class RunJobs extends Command
{
	/** simpleCache key recording when this command last ran */
	const LAST_USED_KEY = 'legacyRunJobsLastUsed';

	protected function configure()
	{
		$this
			->setName('hg:run-jobs')
			->setDescription('Deprecated: runs xf:run-jobs. Point your crontab at xf:run-jobs instead.')
			->addOption(
				'max-execution-time',
				't',
				InputOption::VALUE_REQUIRED,
				'Sets a max execution time in seconds',
				55
			)
			->addOption(
				'manual-only',
				null,
				InputOption::VALUE_NONE,
				'Ensures that only manually triggered jobs are run'
			)
			->addOption(
				'reset',
				null,
				InputOption::VALUE_NONE,
				'Ignored - there is no longer a lock to reset'
			);
	}

	protected function execute(InputInterface $input, OutputInterface $output)
	{
		$this->recordUse();

		$arguments = ['--max-execution-time' => intval($input->getOption('max-execution-time'))];
		if ($input->getOption('manual-only'))
		{
			$arguments['--manual-only'] = true;
		}

		$core = new \XF\Cli\Command\RunJobs();
		$coreInput = new ArrayInput($arguments);
		$coreInput->setInteractive(false);

		return $core->run($coreInput, $output);
	}

	/**
	 * Note when this ran, at most once an hour - it runs every minute from cron, and each write is a
	 * registry update.
	 */
	protected function recordUse()
	{
		$cache = \XF::app()->simpleCache();
		$lastUsed = intval($cache->getValue('Hampel/JobRunner', self::LAST_USED_KEY));

		if ($lastUsed < \XF::$time - 3600)
		{
			$cache->setValue('Hampel/JobRunner', self::LAST_USED_KEY, \XF::$time);
		}
	}
}
