<?php namespace Hampel\JobRunner\XF\Mvc\Renderer;

class Json extends XFCP_Json
{
	protected function addDefaultJsonParams(array $content)
	{
		$content = parent::addDefaultJsonParams($content);

		/**
		 * Disable auto run jobs if we're doing server based activity - we'll let the cron task run those
		 *
		 * Do NOT disable autoBlocking or manual jobs - we need those to run now
		 */
		if (!empty(\XF::options()->jobRunTrigger) && \XF::options()->jobRunTrigger == 'server')
		{
		    $content['job']['auto'] = false;
		}

		return $content;
	}
}
