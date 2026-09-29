<?php namespace Hampel\JobRunner\Cli\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class QueueTestJob extends Command
{
	protected function configure()
	{
		$this
			->setName('job-monitor:queue-test-job')
			->setDescription('Queues a job that sends one test email to the given address.')
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