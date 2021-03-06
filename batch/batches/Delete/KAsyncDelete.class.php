<?php
/**
 * @package Scheduler
 * @subpackage Delete
 */

/**
 * Will delete objects in the deleting server
 * according to the suppolied engine type and filter 
 *
 * @package Scheduler
 * @subpackage Delete
 */
class KAsyncDelete extends KJobHandlerWorker
{
	/* (non-PHPdoc)
	 * @see KBatchBase::getType()
	 */
	public static function getType()
	{
		return KalturaBatchJobType::DELETE;
	}
	
	/* (non-PHPdoc)
	 * @see KBatchBase::getJobType()
	 */
	public function getJobType()
	{
		return self::getType();
	}
	
	/* (non-PHPdoc)
	 * @see KJobHandlerWorker::exec()
	 */
	protected function exec(KalturaBatchJob $job)
	{
		return $this->deleteObjects($job, $job->data);
	}
	
	/**
	 * Will take a single filter and call each item to be deleted 
	 */
	private function deleteObjects(KalturaBatchJob $job, KalturaDeleteJobData $data)
	{
		KalturaLog::debug("Delete objects job id [$job->id]");
		
		$engine = KDeletingEngine::getInstance($job->jobSubType);
		$engine->configure($job->partnerId, $this->kClient, $this->taskConfig);
	
		$filter = clone $data->filter;
		
		$continue = true;
		while($continue)
		{
			$deletedObjectsCount = $engine->run($filter);
			$continue = (bool) $deletedObjectsCount;
		}
		
		return $this->closeJob($job, null, null, "Delete objects finished", KalturaBatchJobStatus::FINISHED);
	}
}
