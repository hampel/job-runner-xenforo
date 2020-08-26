<?php namespace Hampel\JobRunner\XF\Mvc\Renderer;

class Json extends XFCP_Json
{
	protected function addDefaultJsonParams(array $content)
	{
		$content = parent::addDefaultJsonParams($content);

		/**
		 * Disable auto run jobs - we'll let the cron task run those
		 *
		 * Do NOT disable autoBlocking or manual jobs - we need those to run now
		 */
		if (isset($content['job']['auto']) && $content['job']['auto'] && \XF::options()->hgJobRunTrigger == 'activity')
		{
			$content['job']['auto'] = true;
		}
		else
		{
			$content['job']['auto'] = false;
		}

		return $content;
	}
}
