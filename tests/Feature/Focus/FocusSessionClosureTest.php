<?php

use App\Enums\WorkspaceRole;
use App\Models\FocusSession;
use App\Models\FocusSessionInterval;
use App\Models\User;
use Illuminate\Support\Carbon;

function closureSession(User $user, string $status = 'running', ?int $target = 1500, ?DateTimeInterface $startedAt = null): FocusSession
{
    $startedAt ??= Carbon::parse('2026-10-16 12:00:00');

    return FocusSession::create([
        'user_id' => $user->id,
        'status' => $status,
        'started_at' => $startedAt,
        'target_duration_seconds' => $target,
    ]);
}

function closureInterval(FocusSession $session, DateTimeInterface $start, ?DateTimeInterface $end = null, ?DateTimeInterface $confirmation = null): FocusSessionInterval
{
    return $session->intervals()->create([
        'started_at' => $start,
        'ended_at' => $end,
        'last_confirmed_at' => $confirmation,
    ]);
}

test('finishing at the verified goal completes once and frees the active slot', function () {
    $user = User::factory()->create();
    $workspace = $user->personalWorkspace();
    $start = Carbon::parse('2026-10-16 12:00:00');
    $finish = $start->copy()->addSeconds(1500);
    $session = closureSession($user, startedAt: $start);
    $interval = closureInterval($session, $start, confirmation: $finish->copy()->subSecond());
    Carbon::setTestNow($finish);

    $this->actingAs($user)->post(route('focus-sessions.finish', [$workspace, $session]))->assertRedirect();

    $session->refresh();
    expect($session->status)->toBe('completed')
        ->and($session->ended_at->toDateTimeString())->toBe($finish->toDateTimeString());
    expect($interval->fresh()->ended_at->toDateTimeString())->toBe($finish->toDateTimeString());

    $this->actingAs($user)->post(route('focus-sessions.store', $workspace))->assertRedirect();
    $this->assertDatabaseCount('focus_sessions', 2);
});

test('finishing before the verified goal records an interruption', function () {
    $user = User::factory()->create();
    $workspace = $user->personalWorkspace();
    $start = Carbon::parse('2026-10-16 12:00:00');
    $finish = $start->copy()->addSeconds(1499);
    $session = closureSession($user, startedAt: $start);
    $interval = closureInterval($session, $start, confirmation: $finish->copy()->subSecond());
    Carbon::setTestNow($finish);

    $this->actingAs($user)->post(route('focus-sessions.finish', [$workspace, $session]))->assertRedirect();

    expect($session->fresh()->status)->toBe('interrupted')
        ->and($session->fresh()->ended_at->toDateTimeString())->toBe($finish->toDateTimeString())
        ->and($interval->fresh()->ended_at->toDateTimeString())->toBe($finish->toDateTimeString());
});

test('an explicit interruption remains interrupted even when the goal is reached', function () {
    $user = User::factory()->create();
    $workspace = $user->personalWorkspace();
    $start = Carbon::parse('2026-10-16 12:00:00');
    $interrupt = $start->copy()->addSeconds(1500);
    $session = closureSession($user, startedAt: $start);
    closureInterval($session, $start, confirmation: $interrupt->copy()->subSecond());
    Carbon::setTestNow($interrupt);

    $this->actingAs($user)->post(route('focus-sessions.interrupt', [$workspace, $session]))->assertRedirect();

    expect($session->fresh()->status)->toBe('interrupted');
});

test('a paused session can be explicitly interrupted without counting its paused gap', function () {
    $user = User::factory()->create();
    $workspace = $user->personalWorkspace();
    $start = Carbon::parse('2026-10-16 12:00:00');
    $pause = $start->copy()->addSeconds(400);
    $interrupt = $pause->copy()->addHours(2);
    $session = closureSession($user, 'paused', startedAt: $start);
    $interval = closureInterval($session, $start, $pause, $pause);
    Carbon::setTestNow($interrupt);

    $this->actingAs($user)->post(route('focus-sessions.interrupt', [$workspace, $session]))->assertRedirect();

    expect($session->fresh()->status)->toBe('interrupted')
        ->and($interval->fresh()->ended_at->toDateTimeString())->toBe($pause->toDateTimeString())
        ->and($session->fresh()->ended_at->toDateTimeString())->toBe($interrupt->toDateTimeString());
});

test('a paused session cannot be finished', function () {
    $user = User::factory()->create();
    $workspace = $user->personalWorkspace();
    $start = Carbon::parse('2026-10-16 12:00:00');
    $session = closureSession($user, 'paused', startedAt: $start);
    $interval = closureInterval($session, $start, $start->copy()->addSeconds(1500), $start->copy()->addSeconds(1500));
    Carbon::setTestNow($start->copy()->addHours(1));

    $this->actingAs($user)->post(route('focus-sessions.finish', [$workspace, $session]))
        ->assertRedirect()->assertSessionHasErrors('focus_session');

    expect($session->fresh()->status)->toBe('paused')->and($session->fresh()->ended_at)->toBeNull();
    expect($interval->fresh()->ended_at->toDateTimeString())->toBe($start->copy()->addSeconds(1500)->toDateTimeString());
});

test('a stale running finish closes at the last confirmation and never completes', function () {
    $user = User::factory()->create();
    $workspace = $user->personalWorkspace();
    $start = Carbon::parse('2026-10-16 12:00:00');
    $previousEnd = $start->copy()->addSeconds(1500);
    $activeStart = $previousEnd->copy()->addHours(1);
    $confirmed = $activeStart->copy()->addSeconds(30);
    $now = $confirmed->copy()->addSeconds(config('focus.contact_grace_seconds') + 1);
    $session = closureSession($user, startedAt: $start);
    closureInterval($session, $start, $previousEnd, $previousEnd);
    $interval = closureInterval($session, $activeStart, confirmation: $confirmed);
    Carbon::setTestNow($now);

    $this->actingAs($user)->post(route('focus-sessions.finish', [$workspace, $session]))->assertRedirect();

    expect($session->fresh()->status)->toBe('interrupted')
        ->and($interval->fresh()->ended_at->toDateTimeString())->toBe($confirmed->toDateTimeString())
        ->and($session->fresh()->ended_at->toDateTimeString())->toBe($now->toDateTimeString());
});

test('a fresh finish ignores browser supplied time and uses server time', function () {
    $user = User::factory()->create();
    $workspace = $user->personalWorkspace();
    $start = Carbon::parse('2026-10-16 12:00:00');
    $now = $start->copy()->addSeconds(500);
    $session = closureSession($user, startedAt: $start);
    $interval = closureInterval($session, $start, confirmation: $now->copy()->subSecond());
    Carbon::setTestNow($now);

    $this->actingAs($user)->post(route('focus-sessions.finish', [$workspace, $session]), [
        'now' => $start->copy()->addDays(2)->toIso8601String(),
        'seconds' => 9000,
    ])->assertRedirect();

    expect($session->fresh()->status)->toBe('interrupted')
        ->and($interval->fresh()->ended_at->toDateTimeString())->toBe($now->toDateTimeString());
});

test('legacy targetless sessions and intervals without confirmation never invent a completed pomodoro', function () {
    $user = User::factory()->create();
    $workspace = $user->personalWorkspace();
    $start = Carbon::parse('2026-10-16 12:00:00');
    $now = $start->copy()->addHours(3);
    $session = closureSession($user, target: null, startedAt: $start);
    $interval = closureInterval($session, $start);
    Carbon::setTestNow($now);

    $this->actingAs($user)->post(route('focus-sessions.finish', [$workspace, $session]))->assertRedirect();

    expect($session->fresh()->status)->toBe('interrupted')
        ->and($interval->fresh()->ended_at->toDateTimeString())->toBe($start->toDateTimeString())
        ->and($session->fresh()->ended_at->toDateTimeString())->toBe($now->toDateTimeString());
});

test('a legacy running session without intervals is interrupted without backfilling activity', function () {
    $user = User::factory()->create();
    $workspace = $user->personalWorkspace();
    $start = Carbon::parse('2026-10-16 12:00:00');
    $session = closureSession($user, startedAt: $start);
    Carbon::setTestNow($start->copy()->addSeconds(1500));

    $this->actingAs($user)->post(route('focus-sessions.finish', [$workspace, $session]))->assertRedirect();

    expect($session->fresh()->status)->toBe('interrupted');
    $this->assertDatabaseCount('focus_session_intervals', 0);
});

test('multiple intervals count exact verified seconds and exclude pauses', function () {
    $user = User::factory()->create();
    $workspace = $user->personalWorkspace();
    $start = Carbon::parse('2026-10-16 12:00:00');
    $resume = $start->copy()->addHours(2);
    $now = $resume->copy()->addSeconds(500);
    $session = closureSession($user, startedAt: $start);
    closureInterval($session, $start, $start->copy()->addSeconds(1010), $start->copy()->addSeconds(1010));
    $active = closureInterval($session, $resume, confirmation: $now->copy()->subSecond());
    Carbon::setTestNow($now);

    $this->actingAs($user)->post(route('focus-sessions.finish', [$workspace, $session]))->assertRedirect();

    expect($session->fresh()->status)->toBe('completed')
        ->and($active->fresh()->ended_at->toDateTimeString())->toBe($now->toDateTimeString());
});

test('both terminal commands hide sessions owned by another user', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $workspace = $owner->personalWorkspace();
    $workspace->members()->attach($member, ['role' => WorkspaceRole::Member->value]);
    $session = closureSession($member);
    $interval = closureInterval($session, $session->started_at, confirmation: $session->started_at);

    $this->actingAs($owner)->post(route('focus-sessions.finish', [$workspace, $session]))->assertNotFound();
    $this->actingAs($owner)->post(route('focus-sessions.interrupt', [$workspace, $session]))->assertNotFound();

    expect($session->fresh()->status)->toBe('running')->and($interval->fresh()->ended_at)->toBeNull();
});

test('terminal retries preserve the first result time and intervals even for a conflicting command', function () {
    $user = User::factory()->create();
    $workspace = $user->personalWorkspace();
    $start = Carbon::parse('2026-10-16 12:00:00');
    $firstEnd = $start->copy()->addSeconds(1500);
    $session = closureSession($user, startedAt: $start);
    $interval = closureInterval($session, $start, confirmation: $firstEnd->copy()->subSecond());
    Carbon::setTestNow($firstEnd);
    $this->actingAs($user)->post(route('focus-sessions.finish', [$workspace, $session]))->assertRedirect();
    $endedAt = $session->fresh()->ended_at->toDateTimeString();

    Carbon::setTestNow($firstEnd->copy()->addDay());
    $this->actingAs($user)->post(route('focus-sessions.interrupt', [$workspace, $session]))->assertRedirect();

    expect($session->fresh()->status)->toBe('completed')
        ->and($session->fresh()->ended_at->toDateTimeString())->toBe($endedAt)
        ->and($interval->fresh()->ended_at->toDateTimeString())->toBe($firstEnd->toDateTimeString());
    $this->assertDatabaseCount('focus_session_intervals', 1);
});

test('an interrupted session keeps its terminal result when finish is retried later', function () {
    $user = User::factory()->create();
    $workspace = $user->personalWorkspace();
    $start = Carbon::parse('2026-10-16 12:00:00');
    $firstEnd = $start->copy()->addSeconds(300);
    $session = closureSession($user, startedAt: $start);
    $interval = closureInterval($session, $start, confirmation: $firstEnd->copy()->subSecond());
    Carbon::setTestNow($firstEnd);
    $this->actingAs($user)->post(route('focus-sessions.interrupt', [$workspace, $session]))->assertRedirect();
    $endedAt = $session->fresh()->ended_at->toDateTimeString();

    Carbon::setTestNow($firstEnd->copy()->addDay());
    $this->actingAs($user)->post(route('focus-sessions.finish', [$workspace, $session]))->assertRedirect();

    expect($session->fresh()->status)->toBe('interrupted')
        ->and($session->fresh()->ended_at->toDateTimeString())->toBe($endedAt)
        ->and($interval->fresh()->ended_at->toDateTimeString())->toBe($firstEnd->toDateTimeString());
    $this->assertDatabaseCount('focus_session_intervals', 1);
});

test('cancelled sessions cannot be converted into another terminal result', function () {
    $user = User::factory()->create();
    $workspace = $user->personalWorkspace();
    $start = Carbon::parse('2026-10-16 12:00:00');
    $ended = $start->copy()->addSeconds(42);
    $session = closureSession($user, 'cancelled', startedAt: $start);
    $session->update(['ended_at' => $ended]);
    Carbon::setTestNow($ended->copy()->addHour());

    $this->actingAs($user)->post(route('focus-sessions.finish', [$workspace, $session]))
        ->assertRedirect()->assertSessionHasErrors('focus_session');
    $this->actingAs($user)->post(route('focus-sessions.interrupt', [$workspace, $session]))
        ->assertRedirect()->assertSessionHasErrors('focus_session');

    expect($session->fresh()->status)->toBe('cancelled')
        ->and($session->fresh()->ended_at->toDateTimeString())->toBe($ended->toDateTimeString());
});

test('terminal end time does not precede verified interval time when the server clock moves backward', function () {
    $user = User::factory()->create();
    $workspace = $user->personalWorkspace();
    $start = Carbon::parse('2026-10-16 12:00:00');
    $lastVerified = $start->copy()->addSeconds(500);
    $clock = $start->copy()->addSeconds(100);
    $session = closureSession($user, startedAt: $start);
    $interval = closureInterval($session, $start, confirmation: $lastVerified);
    Carbon::setTestNow($clock);

    $this->actingAs($user)->post(route('focus-sessions.interrupt', [$workspace, $session]))->assertRedirect();

    expect($interval->fresh()->ended_at->toDateTimeString())->toBe($lastVerified->toDateTimeString())
        ->and($session->fresh()->ended_at->toDateTimeString())->toBe($lastVerified->toDateTimeString());
});
