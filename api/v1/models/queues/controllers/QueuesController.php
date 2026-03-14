<?php
declare(strict_types=1);

final class QueuesController
{
    private QueuesService $service;

    public function __construct(QueuesService $service)
    {
        $this->service = $service;
    }

    public function handleRequest(string $method, string $route, array $data, array $query): void
    {
        try {
            if ($route === '/stats' && $method === 'GET') {
                ResponseFormatter::success($this->service->getStats());
                return;
            }

            if ($route === '/names' && $method === 'GET') {
                ResponseFormatter::success($this->service->getQueueNames());
                return;
            }

            if ($route === '/retry' && $method === 'POST') {
                $id = (int)($data['id'] ?? $query['id'] ?? 0);
                if ($id <= 0) {
                    ResponseFormatter::error('Job ID is required', 400);
                    return;
                }
                $ok = $this->service->retryJob($id);
                if ($ok) {
                    ResponseFormatter::success(null, 'Job queued for retry');
                } else {
                    ResponseFormatter::error('Job not found or not in failed status', 404);
                }
                return;
            }

            if ($route === '/archive' && $method === 'POST') {
                $count = $this->service->archiveJobs();
                ResponseFormatter::success(['archived' => $count], "Archived {$count} completed jobs");
                return;
            }

            if ($route === '/purge' && $method === 'POST') {
                $days = (int)($data['days'] ?? 30);
                $count = $this->service->purgeArchives($days);
                ResponseFormatter::success(['purged' => $count], "Purged {$count} jobs");
                return;
            }

            // Main List/CRUD
            switch ($method) {
                case 'GET':
                    if (isset($query['id'])) {
                        $item = $this->service->getJob((int)$query['id']);
                        if (!$item) {
                            ResponseFormatter::error('Job not found', 404);
                            return;
                        }
                        ResponseFormatter::success($item);
                    } else {
                        $limit    = isset($query['limit'])  ? (int)$query['limit']  : 25;
                        $offset   = isset($query['offset']) ? (int)$query['offset'] : 0;
                        $filters  = $query;
                        $result   = $this->service->listJobs($limit, $offset, $filters);
                        ResponseFormatter::success($result);
                    }
                    break;

                case 'DELETE':
                    $id = (int)($data['id'] ?? $query['id'] ?? 0);
                    if ($id <= 0) {
                        ResponseFormatter::error('Job ID is required', 400);
                        return;
                    }
                    $ok = $this->service->deleteJob($id);
                    if ($ok) {
                        ResponseFormatter::success(null, 'Job deleted');
                    } else {
                        ResponseFormatter::error('Job not found', 404);
                    }
                    break;

                default:
                    ResponseFormatter::error('Method not allowed', 405);
            }
        } catch (Throwable $e) {
            safe_log('error', 'Error in QueuesController: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            ResponseFormatter::error($e->getMessage(), 500);
        }
    }
}
