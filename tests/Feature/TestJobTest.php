<?php namespace Tests\Feature;

use Hampel\JobRunner\Cli\Command\QueueTestJob;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Mailer\Exception\TransportException;
use Tests\TestCase;
use XF\Job\JobResult;

/**
 * job-monitor:queue-test-job and the job it queues. Mail and the queue are faked: nothing is sent or stored.
 */
class TestJobTest extends TestCase
{
	protected $email = 'someone@example.com';

	public function test_the_command_queues_a_test_job_for_the_address()
	{
		$this->fakesJobs();

		$tester = $this->runConsoleCommand(new QueueTestJob(), ['email' => $this->email]);

		$this->assertSame(0, $tester->getStatusCode());
		$this->assertJobQueuedTimes('Hampel\JobRunner:TestJob', 1);
		$this->assertJobQueued('Hampel\JobRunner:TestJob', function ($job) {
			return $job['execute_data']['email'] == $this->email;
		});
	}

	public function test_the_job_sends_one_mail_to_the_address()
	{
		$this->fakesMail();

		$result = $this->runJobToCompletion('Hampel\JobRunner:TestJob', ['email' => $this->email]);

		$this->assertSame(JobResult::RESULT_COMPLETED, $result->result);
		$this->assertMailSentTimes(1);
		$this->assertMailSent(function ($mail) {
			return $mail->getTo()[0]->getAddress() == $this->email
				&& $mail->getSubject() == 'Test job';
		});
	}

	public function test_the_job_completes_when_the_mail_cannot_be_sent()
	{
		$transport = $this->fakesMail();
		$this->fakesErrors();
		$transport->failWith(new TransportException('smtp is down'));

		$result = $this->runJobToCompletion('Hampel\JobRunner:TestJob', ['email' => $this->email]);

		// a broken mail transport is XenForo's to log; the job must not fail, or be left in the queue
		$this->assertSame(JobResult::RESULT_COMPLETED, $result->result);
		$this->assertMailNotSent();
		$this->assertExceptionLogged(TransportException::class);
	}

	public function test_the_log_says_when_the_mail_was_not_sent()
	{
		$transport = $this->fakesMail();
		$this->fakesErrors();
		$transport->failWith();
		$output = $this->useCliLogger(OutputInterface::VERBOSITY_DEBUG);

		$this->runJobToCompletion('Hampel\JobRunner:TestJob', ['email' => $this->email]);

		$this->assertStringContainsString('Sent mail {"sent":false}', $output->fetch());
	}

	public function test_the_job_logs_its_progress_when_debugging()
	{
		$this->fakesMail();
		$output = $this->useCliLogger(OutputInterface::VERBOSITY_DEBUG);

		$this->runJobToCompletion('Hampel\JobRunner:TestJob', ['email' => $this->email]);

		$log = $output->fetch();
		$this->assertStringContainsString('Hampel\JobRunner:TestJob: About to start test job', $log);
		$this->assertStringContainsString('Hampel\JobRunner:TestJob: Sent mail', $log);
	}

	public function test_the_job_cannot_be_cancelled_or_triggered_from_the_control_panel()
	{
		$job = $this->app()->job('Hampel\JobRunner:TestJob', null, ['email' => $this->email]);

		$this->assertFalse($job->canCancel());
		$this->assertFalse($job->canTriggerByChoice());
		$this->assertSame('Testing jobs', $job->getStatusMessage());
	}
}
