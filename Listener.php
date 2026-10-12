<?php namespace Hampel\JobRunner;

class Listener
{
	/**
	 * Tells the Admin API add-on, where a forum has it, which of this add-on's settings are secrets,
	 * so that it can show the rest. There are none: the add-on has no options, no config.php keys
	 * and no credentials. An option or key added later is declared here in the same change.
	 *
	 * The event belongs to that add-on and is never fired without it, so this is not a dependency.
	 */
	public static function adminApiRedaction(array &$declarations)
	{
		$declarations['Hampel/JobRunner'] = [];
	}
}
