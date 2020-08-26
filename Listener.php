<?php namespace Hampel\JobRunner;

class Listener
{
	public static function templaterGlobalData(\XF\App $app, array &$data, $reply)
	{
		if (empty(\XF::options()->hgJobRunTrigger) || \XF::options()->hgJobRunTrigger != 'activity')
		{
			$data['runJobs'] = false; // disable runJobs trigger because we'll run them ourselves
		}
	}
}