-- ============================================
-- Queue System - Production Indexes
-- Run once to add recommended indexes for
-- parallel workers and performance
-- ============================================

-- Primary worker query: pop() fetches pending jobs by queue
-- Covers: status + queue + available_at + created_at (ordering)
ALTER TABLE queues ADD INDEX idx_queues_pop (status, queue, available_at, created_at);

-- Stats/filter queries by status only
ALTER TABLE queues ADD INDEX idx_queues_status (status);

-- Filter by queue name
ALTER TABLE queues ADD INDEX idx_queues_queue (queue);

-- Find stuck jobs (WORKING for too long)
ALTER TABLE queues ADD INDEX idx_queues_stuck (status, processed_at);

-- Archive query: done jobs older than 10 seconds
ALTER TABLE queues ADD INDEX idx_queues_archive (status, updated_at);

-- Search in error text
ALTER TABLE queues ADD FULLTEXT INDEX idx_queues_error_ft (error);

-- Same indexes for archive table
ALTER TABLE queues_archive ADD INDEX idx_qa_status (status);
ALTER TABLE queues_archive ADD INDEX idx_qa_queue (queue);
ALTER TABLE queues_archive ADD INDEX idx_qa_created (created_at);
