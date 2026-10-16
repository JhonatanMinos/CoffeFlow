<?php

namespace App\Actions\Focus;

use App\Models\FocusSessionInterval;
use DateTimeInterface;
use Illuminate\Support\Carbon;
use LogicException;

class ConfirmFocusSessionInterval
{
    public const CONFIRMED = 'confirmed';

    public const EXPIRED = 'expired';

    public function handle(FocusSessionInterval $interval, DateTimeInterface $now): string
    {
        $now = Carbon::instance($now);
        $startedAt = $interval->started_at;
        $lastConfirmedAt = $interval->last_confirmed_at;
        $graceSeconds = self::contactGraceSeconds();

        if ($lastConfirmedAt === null) {
            $interval->update(['ended_at' => $startedAt]);

            return self::EXPIRED;
        }

        $safeNow = $now->greaterThan($lastConfirmedAt) ? $now : $lastConfirmedAt;

        if ($now->greaterThan($lastConfirmedAt->copy()->addSeconds($graceSeconds))) {
            $interval->update(['ended_at' => $lastConfirmedAt->greaterThan($startedAt) ? $lastConfirmedAt : $startedAt]);

            return self::EXPIRED;
        }

        $interval->update(['last_confirmed_at' => $safeNow]);

        return self::CONFIRMED;
    }

    public static function targetDurationSeconds(): int
    {
        return self::positiveConfig('focus.target_duration_seconds');
    }

    public static function contactGraceSeconds(): int
    {
        return self::positiveConfig('focus.contact_grace_seconds');
    }

    private static function positiveConfig(string $key): int
    {
        $value = config($key);
        if (! is_int($value) || $value < 1) {
            throw new LogicException("Configuration [{$key}] must be a positive integer.");
        }

        return $value;
    }
}
