<?php namespace Tests\Unit;

use Hampel\JobRunner\Listener;
use Tests\TestCase;

/**
 * The declaration this add-on makes to the Admin API about which of its settings are secrets. It
 * goes stale silently when an option is added or renamed, so it is checked against _output/options.
 */
class SecretsDeclarationTest extends TestCase
{
	/** Options or option.members that read like a credential and are not one, with each reason. */
	private const NOT_SECRETS = [];

	private const CREDENTIAL = '/key|token|secret|password|webhook/i';

	public function test_it_declares_this_add_on_only()
	{
		$this->assertSame(['Hampel/JobRunner'], array_keys($this->declarations()));
	}

	public function test_every_declared_secret_is_an_option_this_add_on_has()
	{
		$problems = [];
		foreach ($this->declared()['options'] AS $path)
		{
			$parts = explode('.', $path);
			$file = $this->addOnRoot() . "/_output/options/{$parts[0]}.json";
			if (!is_file($file))
			{
				$problems[] = "{$path}: no such option";
				continue;
			}

			// a member must be one the option actually has
			$members = json_decode(file_get_contents($file), true)['sub_options'] ?? [];
			if (isset($parts[1]) && !in_array($parts[1], $members, true) && !in_array('*', $members, true))
			{
				$problems[] = "{$path}: not a member of {$parts[0]}";
			}
		}

		$this->assertSame([], $problems);
	}

	public function test_an_option_or_member_that_reads_like_a_credential_is_declared_or_decided()
	{
		$decided = array_merge(self::NOT_SECRETS, $this->declared()['options']);

		$undecided = [];
		foreach ($this->optionIds() AS $optionId)
		{
			if (in_array($optionId, $decided, true))
			{
				continue;
			}
			if (preg_match(self::CREDENTIAL, $optionId))
			{
				$undecided[] = $optionId;
			}

			$file = $this->addOnRoot() . "/_output/options/{$optionId}.json";
			foreach (json_decode(file_get_contents($file), true)['sub_options'] ?? [] AS $member)
			{
				$path = "{$optionId}.{$member}";
				if (preg_match(self::CREDENTIAL, $member) && !in_array($path, $decided, true))
				{
					$undecided[] = $path;
				}
			}
		}

		$this->assertSame([], $undecided, 'declare each as a secret, or add it to NOT_SECRETS');
	}

	public function test_the_listener_is_registered_for_the_event()
	{
		$registered = [];
		foreach (glob($this->addOnRoot() . '/_output/code_event_listeners/*.json') ?: [] AS $file)
		{
			if (basename($file) == '_metadata.json')
			{
				continue;
			}

			$listener = json_decode(file_get_contents($file), true);
			$registered[] = "{$listener['event_id']} -> {$listener['callback_class']}::{$listener['callback_method']}";
		}

		// a declaration nothing calls protects nothing
		$this->assertContains('hampel_admin_api_redaction -> ' . Listener::class . '::adminApiRedaction', $registered);
	}

	private function declarations(): array
	{
		$declarations = [];
		Listener::adminApiRedaction($declarations);

		return $declarations;
	}

	private function declared(): array
	{
		return $this->declarations()['Hampel/JobRunner'] + ['options' => [], 'config' => []];
	}

	private function optionIds(): array
	{
		$ids = [];
		foreach (glob($this->addOnRoot() . '/_output/options/*.json') ?: [] AS $file)
		{
			if (basename($file) != '_metadata.json')
			{
				$ids[] = basename($file, '.json');
			}
		}

		return $ids;
	}

	private function addOnRoot(): string
	{
		return dirname(__DIR__, 2);
	}
}
