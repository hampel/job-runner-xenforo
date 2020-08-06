<?php namespace Hampel\JobRunner\Cli;

use Symfony\Component\Console\Output\OutputInterface;

trait LoggerTrait
{
	private function getLogger()
	{
		$app = $this->app ?? \XF::app();
		return $app['cli.logger'] ?? false;
	}

	private function log($message, array $context = [], array $extra = [], $verbosity = OutputInterface::VERBOSITY_VERBOSE)
	{
		// check to see if we actually have a logger available and abort if not
		if (!$logger = $this->getLogger()) return;

		$logger->log(get_called_class(), $message, $context, $extra, $verbosity);
	}

	private function logNormal($message, array $context = [])
	{
		$this->log($message, $context, [], OutputInterface::VERBOSITY_NORMAL);
	}

	private function logVerbose($message, array $context = [])
	{
		$this->log($message, $context, [], OutputInterface::VERBOSITY_VERBOSE);
	}

	private function logVeryVerbose($message, array $context = [])
	{
		$this->log($message, $context, [], OutputInterface::VERBOSITY_VERY_VERBOSE);
	}

	private function debug($message, array $context = [])
	{
		$this->log($message, $context, [], OutputInterface::VERBOSITY_DEBUG);
	}
}
