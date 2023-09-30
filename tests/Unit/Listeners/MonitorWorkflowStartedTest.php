<?php

declare(strict_types=1);

namespace Tests\Unit;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use Workflow\Events\WorkflowStarted;
use Workflow\Listeners\MonitorWorkflowStarted;

final class MonitorWorkflowStartedTest extends TestCase
{
    public function testHandle(): void
    {
        config([
            'workflows.monitor_url' => 'http://test',
        ]);
        config([
            'workflows.monitor_api_key' => 'key',
        ]);

        Http::fake([
            'functions/v1/get-user' => Http::response([
                'user' => 'user',
                'public' => 'public',
                'token' => 'token',
            ]),
            'rest/v1/workflows' => Http::response(),
        ]);

        $event = new WorkflowStarted(1, 'class', 'arguments', 'time');
        $listener = new MonitorWorkflowStarted();
        $listener->handle($event);

        Http::assertSent(static function (Request $request) {
            return $request->hasHeader('Authorization', 'Bearer key') &&
                $request->url() === 'http://test/functions/v1/get-user';
        });

        Http::assertSent(static function (Request $request) {
            $data = json_decode($request->body());
            return $request->hasHeader('apiKey', 'public') &&
                $request->hasHeader('Authorization', 'Bearer token') &&
                $request->url() === 'http://test/rest/v1/workflows' &&
                $data->user_id === 'user' &&
                $data->workflow_id === 1 &&
                $data->class === 'class' &&
                $data->status === 'running' &&
                $data->arguments === 'arguments' &&
                $data->created_at === 'time';
        });
    }
}
