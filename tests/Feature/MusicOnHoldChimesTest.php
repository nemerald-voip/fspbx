<?php

namespace Tests\Feature;

use App\Http\Controllers\MusicOnHoldController;
use App\Services\Install\InstallSchema;
use App\Services\MusicOnHoldService;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;

class MusicOnHoldChimesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'chime_test', 'database.connections.chime_test' => [
            'driver' => 'sqlite', 'database' => ':memory:',
        ], 'session.driver' => 'array']);
        app(InstallSchema::class)->ensureMusicOnHoldSchema();
        $this->partialMock(MusicOnHoldService::class)->shouldReceive('refreshRuntime');
        session(['domain_uuid' => (string) Str::uuid(), 'domain_name' => 'moh.test', 'permissions' => [
            (object) ['permission_name' => 'music_on_hold_add'],
            (object) ['permission_name' => 'music_on_hold_edit'],
        ]]);
        Route::post('/test/moh', [MusicOnHoldController::class, 'store']);
        Route::put('/test/moh/{music_on_hold}', [MusicOnHoldController::class, 'update'])->middleware(SubstituteBindings::class);
    }

    public function test_legacy_stream_can_be_reordered_repeated_and_cleared_at_both_rates(): void
    {
        $name = ['music_on_hold_name' => 'Support'];
        $stream = $this->postJson('/test/moh', $name + ['music_on_hold_chime_list' => 'first.wav, second.wav'])
            ->assertCreated()->json('music_on_hold_uuid');
        $this->assertBothRates('first.wav,second.wav');

        $chimes = ['/recordings/moh.test/notice.wav', 'ivr/please-wait.wav', '/recordings/moh.test/notice.wav'];
        $this->putJson('/test/moh/'.$stream, $name + ['music_on_hold_chime_list' => $chimes])->assertOk();
        $this->assertBothRates('/recordings/moh.test/notice.wav,ivr/please-wait.wav,/recordings/moh.test/notice.wav');
        $this->putJson('/test/moh/'.$stream, $name + ['music_on_hold_chime_list' => []])->assertOk();
        $this->assertBothRates(null);
    }

    public function test_invalid_entries_and_lists_beyond_the_module_limit_are_rejected(): void
    {
        $name = ['music_on_hold_name' => 'Support'];
        $this->postJson('/test/moh', $name + ['music_on_hold_chime_list' => ['good.wav', 'bad,name.wav']])
            ->assertUnprocessable()->assertJsonValidationErrors(['music_on_hold_chime_list.1']);
        $this->postJson('/test/moh', $name + ['music_on_hold_chime_list' => array_fill(0, 101, 'good.wav')])
            ->assertUnprocessable()->assertJsonValidationErrors(['music_on_hold_chime_list']);
        $this->assertDatabaseCount('v_music_on_hold', 0);
    }

    private function assertBothRates(?string $list): void
    {
        $this->assertSame([8000 => $list, 16000 => $list], DB::table('v_music_on_hold')
            ->orderBy('music_on_hold_rate')->pluck('music_on_hold_chime_list', 'music_on_hold_rate')->all());
    }
}
