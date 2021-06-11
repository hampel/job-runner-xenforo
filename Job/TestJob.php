<?php namespace Hampel\JobRunner\Job;

use Hampel\JobRunner\Cli\Logger;
use XF\Job\AbstractJob;

class TestJob extends AbstractJob
{
	public function run($maxRunTime)
	{
		$this->log("About to start test job", $this->data);

		$mail = $this->app->mailer()->newMail();
		$mail->setTo($this->data['email']);
		$mail->setContent(
			"Test job",
			"This is an email sent from a test job"
		);
		$sent = $mail->send();

		$this->log("Sent mail", ['sent' => $sent]);

		return $this->complete();
	}

	public function getStatusMessage()
	{
		return "Testing jobs";
	}

	public function canCancel()
	{
		return false;
	}

	public function canTriggerByChoice()
	{
		return false;
	}

	protected function log($message, array $context = [])
	{
		// check to see if we actually have a logger available and abort if not
		if (!isset($this->app['cli.logger'])) return;

		/** @var Logger $logger */
		$logger = $this->app['cli.logger'];
		$logger->logJobProgress($this, $message, $context);
	}
}
