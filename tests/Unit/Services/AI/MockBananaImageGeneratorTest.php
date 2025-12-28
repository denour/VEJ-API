<?php

namespace Tests\Unit\Services\AI;

use App\Services\AI\MockBananaImageGenerator;
use Tests\TestCase;

class MockBananaImageGeneratorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        MockBananaImageGenerator::clearTasks();
    }

    public function test_generate_returns_unique_task_id(): void
    {
        $generator = new MockBananaImageGenerator;

        $taskId1 = $generator->generate('Prompt 1');
        $taskId2 = $generator->generate('Prompt 2');

        $this->assertStringStartsWith('task_', $taskId1);
        $this->assertStringStartsWith('task_', $taskId2);
        $this->assertNotSame($taskId1, $taskId2);
    }

    public function test_generate_stores_task_for_later_retrieval(): void
    {
        $generator = new MockBananaImageGenerator;

        $taskId = $generator->generate('Test prompt');

        $tasks = MockBananaImageGenerator::getTasks();
        $this->assertArrayHasKey($taskId, $tasks);
        $this->assertSame('Test prompt', $tasks[$taskId]['prompt']);
    }

    public function test_get_task_status_returns_success_for_existing_task(): void
    {
        $generator = new MockBananaImageGenerator;
        $taskId = $generator->generate('Test prompt');

        $status = $generator->getTaskStatus($taskId);

        $this->assertSame('success', $status['status']);
        $this->assertNotNull($status['imageUrl']);
        $this->assertNull($status['error']);
    }

    public function test_get_task_status_returns_not_found_for_unknown_task(): void
    {
        $generator = new MockBananaImageGenerator;

        $status = $generator->getTaskStatus('unknown-task-id');

        $this->assertSame('not_found', $status['status']);
        $this->assertNull($status['imageUrl']);
        $this->assertSame('Task not found', $status['error']);
    }

    public function test_get_webhook_payload_returns_correct_format(): void
    {
        $generator = new MockBananaImageGenerator;
        $taskId = $generator->generate('Test prompt');

        $payload = MockBananaImageGenerator::getWebhookPayload($taskId);

        $this->assertSame('Image generated successfully.', $payload['msg']);
        $this->assertSame(200, $payload['code']);
        $this->assertSame($taskId, $payload['data']['taskId']);
        $this->assertStringContainsString($taskId, $payload['data']['info']['resultImageUrl']);
    }

    public function test_clear_tasks_removes_all_stored_tasks(): void
    {
        $generator = new MockBananaImageGenerator;
        $generator->generate('Prompt 1');
        $generator->generate('Prompt 2');

        MockBananaImageGenerator::clearTasks();

        $this->assertEmpty(MockBananaImageGenerator::getTasks());
    }

    public function test_get_provider_name_returns_mock_banana(): void
    {
        $generator = new MockBananaImageGenerator;

        $this->assertSame('MockBanana', $generator->getProviderName());
    }
}
