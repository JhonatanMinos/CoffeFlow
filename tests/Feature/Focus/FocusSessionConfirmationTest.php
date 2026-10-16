<?php

use App\Models\FocusSession;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

test('new focus sessions snapshot a configurable positive target and confirmation time', function () {
    config(['focus.target_duration_seconds' => 1800]);
    $user = User::factory()->create();
    $workspace = $user->personalWorkspace();
    $startedAt = Carbon::parse('2026-10-10 12:00:00');
    Carbon::setTestNow($startedAt);

    $this->actingAs($user)->post(route('focus-sessions.store', $workspace))->assertRedirect();

    $session = FocusSession::firstOrFail();
    $interval = $session->intervals()->firstOrFail();
    config(['focus.target_duration_seconds' => 300]);
    $session->refresh();
    expect($session->target_duration_seconds)->toBe(1800)
        ->and($session->ended_at)->toBeNull()
        ->and($session->target_duration_seconds)->toBe(1800)
        ->and($interval->last_confirmed_at?->equalTo($startedAt))->toBeTrue();
});

test('invalid target and contact grace configuration are rejected', function () {
    config(['focus.target_duration_seconds' => 0]);
    $user = User::factory()->create();
    $workspace = $user->personalWorkspace();

    $this->withoutExceptionHandling();
    expect(fn () => $this->actingAs($user)->post(route('focus-sessions.store', $workspace)))
        ->toThrow(LogicException::class);

    config(['focus.target_duration_seconds' => 1500, 'focus.contact_grace_seconds' => 0]);
    $session = FocusSession::create(['user_id' => $user->id, 'status' => 'running', 'started_at' => now()]);
    $interval = $session->intervals()->create(['started_at' => now(), 'last_confirmed_at' => now()]);
    expect(fn () => $this->actingAs($user)->post(route('focus-sessions.heartbeat', [$workspace, $session])))
        ->toThrow(LogicException::class);
    expect($interval->fresh()->ended_at)->toBeNull();
});

test('fresh heartbeat confirms to server time and accepts exact grace boundary only', function () {
    config(['focus.contact_grace_seconds' => 90]);
    $user = User::factory()->create();
    $workspace = $user->personalWorkspace();
    $startedAt = Carbon::parse('2026-10-10 12:00:00');
    Carbon::setTestNow($startedAt);
    $this->actingAs($user)->post(route('focus-sessions.store', $workspace))->assertRedirect();
    $session = FocusSession::firstOrFail();

    Carbon::setTestNow($startedAt->copy()->addSeconds(90));
    $response = $this->actingAs($user)->postJson(route('focus-sessions.heartbeat', [$workspace, $session]), ['seconds' => 999999, 'timestamp' => '2099-01-01']);

    $response->assertOk()->assertJsonPath('status', 'running')->assertJsonPath('confirmedAt', '2026-10-10T12:01:30+00:00');
    expect($session->intervals()->firstOrFail()->fresh()->last_confirmed_at?->equalTo(Carbon::now()))->toBeTrue();
});

test('stale heartbeat closes at last confirmation without grace credit and pauses session', function () {
    config(['focus.contact_grace_seconds' => 90]);
    $user = User::factory()->create();
    $workspace = $user->personalWorkspace();
    $startedAt = Carbon::parse('2026-10-10 12:00:00');
    Carbon::setTestNow($startedAt);
    $this->actingAs($user)->post(route('focus-sessions.store', $workspace))->assertRedirect();
    $session = FocusSession::firstOrFail();
    $lastConfirmed = $startedAt->copy()->addSeconds(30);
    $session->intervals()->firstOrFail()->update(['last_confirmed_at' => $lastConfirmed]);

    Carbon::setTestNow($lastConfirmed->copy()->addSeconds(91));
    $this->actingAs($user)->postJson(route('focus-sessions.heartbeat', [$workspace, $session]))
        ->assertOk()->assertJsonPath('status', 'paused')->assertJsonPath('confirmedAt', $lastConfirmed->toIso8601String());

    expect($session->fresh()->status)->toBe('paused')
        ->and($session->intervals()->firstOrFail()->fresh()->ended_at?->equalTo($lastConfirmed))->toBeTrue();
});

test('pause uses the same stale cutoff and resume opens a newly confirmed interval', function () {
    config(['focus.contact_grace_seconds' => 90]);
    $user = User::factory()->create();
    $workspace = $user->personalWorkspace();
    $startedAt = Carbon::parse('2026-10-10 12:00:00');
    Carbon::setTestNow($startedAt);
    $this->actingAs($user)->post(route('focus-sessions.store', $workspace))->assertRedirect();
    $session = FocusSession::firstOrFail();
    $lastConfirmed = $startedAt->copy()->addSeconds(30);
    $session->intervals()->firstOrFail()->update(['last_confirmed_at' => $lastConfirmed]);
    Carbon::setTestNow($lastConfirmed->copy()->addSeconds(91));

    $this->actingAs($user)->post(route('focus-sessions.pause', [$workspace, $session]))->assertRedirect();
    expect($session->fresh()->status)->toBe('paused')
        ->and($session->intervals()->firstOrFail()->fresh()->ended_at?->equalTo($lastConfirmed))->toBeTrue();

    Carbon::setTestNow($lastConfirmed->copy()->addMinutes(5));
    $this->actingAs($user)->post(route('focus-sessions.resume', [$workspace, $session]))->assertRedirect();
    $resumed = $session->intervals()->latest('id')->firstOrFail();
    expect($resumed->started_at?->equalTo(Carbon::now()))->toBeTrue()
        ->and($resumed->last_confirmed_at?->equalTo(Carbon::now()))->toBeTrue();
});

test('legacy open intervals close at start without confirmation and heartbeat never creates missing history', function () {
    $user = User::factory()->create();
    $workspace = $user->personalWorkspace();
    $startedAt = Carbon::parse('2026-10-09 12:00:00');
    $session = FocusSession::create(['user_id' => $user->id, 'status' => 'running', 'started_at' => $startedAt]);
    $interval = $session->intervals()->create(['started_at' => $startedAt]);

    Carbon::setTestNow($startedAt->copy()->addDay());
    $this->actingAs($user)->postJson(route('focus-sessions.heartbeat', [$workspace, $session]))->assertOk()->assertJsonPath('status', 'paused');
    expect($interval->fresh()->ended_at?->equalTo($startedAt))->toBeTrue()
        ->and($session->fresh()->target_duration_seconds)->toBeNull()
        ->and($session->fresh()->ended_at)->toBeNull();

    $recentLegacyUser = User::factory()->create();
    $recentLegacyWorkspace = $recentLegacyUser->personalWorkspace();
    $recentLegacy = FocusSession::create(['user_id' => $recentLegacyUser->id, 'status' => 'running', 'started_at' => Carbon::now()]);
    $recentStartedAt = Carbon::now();
    $recentInterval = $recentLegacy->intervals()->create(['started_at' => $recentStartedAt]);
    Carbon::setTestNow($recentStartedAt->copy()->addSeconds(30));
    $this->actingAs($recentLegacyUser)->post(route('focus-sessions.pause', [$recentLegacyWorkspace, $recentLegacy]))->assertRedirect();
    expect($recentInterval->fresh()->ended_at?->equalTo($recentStartedAt))->toBeTrue();

    $legacy = FocusSession::create(['user_id' => User::factory()->create()->id, 'status' => 'running', 'started_at' => $startedAt]);
    $legacyWorkspace = $legacy->user->personalWorkspace();
    $this->actingAs($legacy->user)->postJson(route('focus-sessions.heartbeat', [$legacyWorkspace, $legacy]))->assertOk()->assertJsonPath('status', 'paused');
    expect($legacy->intervals()->count())->toBe(0);
});

test('heartbeat is owner scoped and only allowed for running sessions', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $workspace = $owner->personalWorkspace();
    $session = FocusSession::create(['user_id' => $other->id, 'status' => 'running', 'started_at' => now()]);
    $interval = $session->intervals()->create(['started_at' => now(), 'last_confirmed_at' => now()]);

    $this->actingAs($owner)->postJson(route('focus-sessions.heartbeat', [$workspace, $session]))->assertNotFound();
    $session->update(['status' => 'paused']);
    $this->actingAs($other)->postJson(route('focus-sessions.heartbeat', [$other->personalWorkspace(), $session]))->assertUnprocessable();
    $session->update(['status' => 'completed']);
    $this->actingAs($other)->postJson(route('focus-sessions.heartbeat', [$other->personalWorkspace(), $session]))->assertUnprocessable();
    expect($interval->fresh()->ended_at)->toBeNull();
});

test('a different owner cannot resume an existing focus session and terminal sessions reject transitions', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $workspace = $other->personalWorkspace();
    $session = FocusSession::create(['user_id' => $owner->id, 'status' => 'paused', 'started_at' => now()]);

    $this->actingAs($other)->post(route('focus-sessions.resume', [$workspace, $session]))->assertNotFound();
    $session->update(['status' => 'completed']);
    $this->actingAs($owner)->post(route('focus-sessions.pause', [$owner->personalWorkspace(), $session]))
        ->assertSessionHasErrors('focus_session');
    $this->actingAs($owner)->post(route('focus-sessions.resume', [$owner->personalWorkspace(), $session]))
        ->assertSessionHasErrors('focus_session');
});

test('backward server clock never moves confirmation or closure before interval start', function () {
    $user = User::factory()->create();
    $workspace = $user->personalWorkspace();
    $startedAt = Carbon::parse('2026-10-10 12:00:00');
    $session = FocusSession::create(['user_id' => $user->id, 'status' => 'running', 'started_at' => $startedAt]);
    $interval = $session->intervals()->create(['started_at' => $startedAt, 'last_confirmed_at' => $startedAt->copy()->addMinute()]);

    Carbon::setTestNow($startedAt->copy()->subMinute());
    $this->actingAs($user)->post(route('focus-sessions.pause', [$workspace, $session]))->assertRedirect();
    expect($interval->fresh()->ended_at?->equalTo($startedAt->copy()->addMinute()))->toBeTrue();
});

test('database still rejects a second open interval for a session', function () {
    $session = FocusSession::create(['user_id' => User::factory()->create()->id, 'status' => 'running', 'started_at' => now()]);
    $session->intervals()->create(['started_at' => now(), 'last_confirmed_at' => now()]);
    expect(fn () => DB::transaction(fn () => $session->intervals()->create(['started_at' => now(), 'last_confirmed_at' => now()])))
        ->toThrow(QueryException::class);
});
