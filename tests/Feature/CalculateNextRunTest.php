<?php namespace Tests\Feature;

use Hampel\Testing\Concerns\UsesDatabaseTransactions;
use Symfony\Component\Console\Output\OutputInterface;
use Tests\TestCase;

/**
 * XF\Service\CronEntry\CalculateNextRun as extended by this add-on.
 */
class CalculateNextRunTest extends TestCase
{
	use UsesDatabaseTransactions;

	protected function setUp(): void
	{
		parent::setUp();

		$this->setTestTime(1000);
	}

	/**
	 * @return \Hampel\JobRunner\XF\Service\CronEntry\CalculateNextRun
	 */
	protected function service()
	{
		return $this->app()->service('XF:CronEntry\CalculateNextRun');
	}

	protected function entry($entryId)
	{
		return $this->app()->em()->find('XF:CronEntry', $entryId);
	}

	public function test_the_extension_is_applied()
	{
		$this->assertInstanceOf(\Hampel\JobRunner\XF\Service\CronEntry\CalculateNextRun::class, $this->service());
	}

	public function test_claiming_an_entry_moves_its_next_run_forward()
	{
		$this->insertCronEntry('jrTestClaim', 'record', 100);

		$this->assertTrue($this->service()->updateCronRunTimeAtomic($this->entry('jrTestClaim')));
		$this->assertGreaterThan(1000, $this->cronNextRun('jrTestClaim'));
	}

	public function test_an_entry_already_claimed_is_not_claimed_again()
	{
		$this->insertCronEntry('jrTestRace', 'record', 100);
		$entry = $this->entry('jrTestRace');

		// another runner claimed it first, so the entity's next_run is stale
		$this->assertTrue($this->service()->updateCronRunTimeAtomic($entry));
		$this->assertFalse($this->service()->updateCronRunTimeAtomic($entry));
	}

	public function test_a_claim_is_logged_when_very_verbose()
	{
		$output = $this->useCliLogger(OutputInterface::VERBOSITY_VERY_VERBOSE);
		$this->insertCronEntry('jrTestClaimLog', 'record', 100);

		$this->service()->updateCronRunTimeAtomic($this->entry('jrTestClaimLog'));

		$this->assertStringContainsString('Cron entry [jrTestClaimLog] next run time updated', $output->fetch());
	}

	public function test_the_minimum_next_run_is_xenforos_answer_and_is_logged()
	{
		$output = $this->useCliLogger(OutputInterface::VERBOSITY_VERY_VERBOSE);
		$this->insertCronEntry('jrTestSoonest', 'record', 100);

		$this->assertSame(100, intval($this->service()->getMinimumNextRunTime()));
		$this->assertStringContainsString('"entry_id":"jrTestSoonest"', $output->fetch());
	}

	public function test_a_claim_lost_to_another_runner_is_logged_as_skipped()
	{
		$output = $this->useCliLogger(OutputInterface::VERBOSITY_VERBOSE);
		$this->insertCronEntry('jrTestLost', 'record', 100);
		$entry = $this->entry('jrTestLost');

		$this->service()->updateCronRunTimeAtomic($entry);
		$this->service()->updateCronRunTimeAtomic($entry);

		$this->assertStringContainsString('Could not update run time for cron [jrTestLost], skipping', $output->fetch());
	}
}
