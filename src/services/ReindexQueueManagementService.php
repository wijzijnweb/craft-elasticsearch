<?php
/**
 * Elasticsearch plugin for Craft CMS 3.x
 *
 * Bring the power of Elasticsearch to you Craft 3 CMS project
 *
 * @link      https://www.lahautesociete.com
 * @copyright Copyright (c) 2018 La Haute Société
 */

namespace lhs\elasticsearch\services;

use Craft;
use craft\base\Component;
use lhs\elasticsearch\Elasticsearch;
use lhs\elasticsearch\jobs\IndexElementJob;
use lhs\elasticsearch\models\IndexableElementModel;
use yii\base\InvalidConfigException;

/**
 * Service used to manage the reindex job queue.
 * It allows clearing failed reindexing jobs before reindexing all entries.
 * @property array $cache
 */
class ReindexQueueManagementService extends Component
{
    const CACHE_KEY = Elasticsearch::PLUGIN_HANDLE . '_reindex_jobs';

    /**
     * @param IndexableElementModel[] $indexableElementModels
     * @return void
     * @throws InvalidConfigException
     */
    public function enqueueReindexJobs(array $indexableElementModels): void
    {
        $jobIds = [];
        foreach ($indexableElementModels as $model) {
            $queue = Elasticsearch::getInstance()->service->getQueue();
            $jobIds[] = $queue->push(new IndexElementJob($model->toArray()));
        }

        $jobIds = array_unique(array_merge($jobIds, $this->getCache()));

        /** @noinspection SummerTimeUnsafeTimeManipulationInspection */
        Craft::$app->getCache()->set(self::CACHE_KEY, $jobIds, 24 * 60 * 60);
    }

    /**
     * Remove all jobs from the queue
     */
    public function clearJobs(): void
    {
        $jobIds = $this->getCache();
        foreach ($jobIds as $jobId) {
            $this->removeJobFromQueue($jobId);
        }

        $cacheService = Craft::$app->getCache();
        $cacheService->delete(self::CACHE_KEY);
    }

    /**
     * Remove a job from the queue
     * @param int $id The id of the job to remove
     */
    public function removeJob(int $id): void
    {
        $this->removeJobFromQueue($id);
        $this->removeJobIdFromCache($id);
    }

    public function enqueueJob(int $entryId, int $siteId, string $type): void
    {
        $job = new IndexElementJob(
            [
                'siteId'    => $siteId,
                'elementId' => $entryId,
                'type'      => $type,
            ]
        );

        $jobId = Craft::$app->queue->push($job);

        $this->addJobIdToCache($jobId);
    }

    /**
     * Remove a job from the queue. This should work with any cache backend.
     * This does NOT remove the job id from the cache
     * @param int $id The id of the job to remove
     */
    protected function removeJobFromQueue(int $id): void
    {
        $queueService = Elasticsearch::getInstance()->service->getQueue();
        $methodName = $queueService instanceof \yii\queue\db\Queue ? 'remove' : 'release';
        $queueService->$methodName($id);
    }

    /**
     * Add a job id to the cache
     * @param int $id The job id to add to the cache
     */
    protected function addJobIdToCache(int $id): void
    {
        $jobIds = $this->getCache();
        $jobIds[] = $id;

        Craft::$app->getCache()->set(self::CACHE_KEY, $jobIds);
    }

    /**
     * Remove a job id from the cache
     * @param int $id The job id to remove from the cache
     */
    protected function removeJobIdFromCache(int $id): void
    {
        $jobIds = array_diff($this->getCache(), [$id]);

        Craft::$app->getCache()->set(self::CACHE_KEY, $jobIds);
    }

    /**
     * Get the job ids from the cache
     * @return array An array of job ids
     */
    protected function getCache(): array
    {
        $cache = Craft::$app->getCache();
        $jobIds = $cache->get(self::CACHE_KEY);

        if ($jobIds === false || !is_array($jobIds)) {
            $jobIds = [];
        }

        return $jobIds;
    }
}
