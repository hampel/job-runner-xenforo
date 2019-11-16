<?php namespace Hampel\JobRunner\XF\Job;

class Manager extends XFCP_Manager
{
	public function updateNextRunTime()
	{
		return \XF::$time; // just return the current time, we're actively ignoring the next run time for jobs
	}
}
