<?php namespace Hampel\JobRunner\Cli\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class TestJobs extends Command
{
	protected function configure()
	{
		$this
			->setName('xf:test-jobs')
			->setDescription('Generate a test job')
			->addArgument(
				'email',
				InputArgument::REQUIRED,
				"Email address to sent test message to"
			);
	}

	protected function execute(InputInterface $input, OutputInterface $output)
	{
		$app = \XF::app();
		$app->jobManager()->enqueue("Hampel\JobRunner:TestJob", ['email' => $input->getArgument('email')]);

		return 0;
	}
}