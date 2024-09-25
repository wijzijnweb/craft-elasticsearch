<?php

namespace lhs\elasticsearch\jobs;

use craft\queue\BaseJob;
use lhs\elasticsearch\services\ElasticsearchService;
use lhs\elasticsearch\services\ReindexQueueManagementService;

class BulkIndexElementJob extends BaseJob
{
    public ReindexQueueManagementService $reindexQueueManagementService;
    public ElasticsearchService $service;

    protected function defaultDescription(): string
    {
        return 'Building bulk of indexing jobs';
    }

    public function execute($queue): void
    {
        $reindexQueueManagementService = $this->reindexQueueManagementService
            ?? new ReindexQueueManagementService();
        $service = $this->service
            ?? new ElasticsearchService();

        // Remove previous reindexing jobs as all elements will be reindexed anyway
        $reindexQueueManagementService->clearJobs();
        $reindexQueueManagementService->enqueueReindexJobs($service->getIndexableElementModels());
    }
}