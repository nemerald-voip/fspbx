<?php

namespace Tests\Unit;

use App\Services\RingotelApiService;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

class RingotelUserPartialUpdateTest extends TestCase
{
    private RingotelApiService $service;
    private bool $returnApiError = false;

    protected function setUp(): void
    {
        parent::setUp();
        config(['ringotel.url' => 'https://ringotel.test']);
        $this->service = Mockery::mock(RingotelApiService::class)->makePartial();
        $this->service->shouldReceive('getRingotelApiToken')->andReturn('test-token');
        $this->app->instance(RingotelApiService::class, $this->service);
        Http::fake(fn () => Http::response($this->returnApiError
            ? ['error' => ['message' => 'User not found']]
            : ['result' => ['id' => 'user-1']]));
    }

    public function test_password_only_update_omits_identity_profile_and_activation_fields(): void
    {
        $this->service->updateUser(['user_id' => 'user-1', 'org_id' => 'org-1', 'password' => 'new-test-password']);

        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => $request->data() === [
            'method' => 'updateUser',
            'params' => ['id' => 'user-1', 'orgid' => 'org-1', 'noemail' => true, 'password' => 'new-test-password'],
        ]);
    }

    public function test_existing_full_user_updates_keep_all_previously_sent_values(): void
    {
        $this->service->updateUser([
            'user_id' => 'user-1', 'org_id' => 'org-1', 'name' => 'Test User', 'email' => '',
            'ext' => '205', 'password' => 'test-password', 'status' => 1, 'no_email' => false,
        ]);

        Http::assertSent(function ($request) {
            $this->assertSame('updateUser', $request['method']);
            $this->assertEquals([
                'id' => 'user-1', 'orgid' => 'org-1', 'name' => 'Test User', 'email' => '',
                'extension' => '205', 'username' => '205', 'authname' => '205',
                'password' => 'test-password', 'status' => 1, 'noemail' => false,
            ], $request['params']);
            return true;
        });
    }

    public function test_ringotel_errors_propagate_so_the_existing_job_can_retry(): void
    {
        $this->returnApiError = true;
        $this->expectExceptionMessage('User not found');
        $this->service->updateUser(['user_id' => 'missing', 'org_id' => 'org-1', 'password' => 'test-password']);
    }
}
