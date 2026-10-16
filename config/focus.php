<?php

$positiveInteger = static function (string $key, int $default): int {
    $value = env($key, $default);

    if (is_int($value) && $value > 0) {
        return $value;
    }

    if (is_string($value) && preg_match('/^[1-9][0-9]*$/D', $value) === 1) {
        $parsed = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($parsed !== false) {
            return $parsed;
        }
    }

    throw new LogicException("Environment value [{$key}] must be a positive canonical integer.");
};

return [
    'target_duration_seconds' => $positiveInteger('FOCUS_TARGET_DURATION_SECONDS', 1500),
    'contact_grace_seconds' => $positiveInteger('FOCUS_CONTACT_GRACE_SECONDS', 90),
];
