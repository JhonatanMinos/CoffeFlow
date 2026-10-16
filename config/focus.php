<?php

return [
    'target_duration_seconds' => (int) env('FOCUS_TARGET_DURATION_SECONDS', 1500),
    'contact_grace_seconds' => (int) env('FOCUS_CONTACT_GRACE_SECONDS', 90),
];
