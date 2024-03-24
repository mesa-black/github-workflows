<?php

namespace App\Service;

interface WorkflowManagerInterface
{
    public function getWorkflows(int $page, int &$pages): array;
    public function deleteWorkflow(int $workflowId): bool;
}
