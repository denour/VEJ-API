<?php

namespace Tests\Unit\Services\AI;

use App\Services\AI\BananaImageGenerator;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BananaImageGeneratorTest extends TestCase
{
    public function test_generate_throws_when_no_task_id_returned(): void
    {
        $this->createApplication();
        Storage::fake('s3');

        Http::fakeSequence()
            ->push([
                'data' => [],
                'message' => 'Invalid request payload',
            ], 200);

        $generator = new BananaImageGenerator('test-api-key');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('taskId');

        $generator->generate('A plant');
    }
}
