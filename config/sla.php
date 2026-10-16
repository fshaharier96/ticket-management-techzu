<?php

return [
    // Carbon dayOfWeek: 0 = Sunday ... 6 = Saturday. Sunday–Thursday:
    'business_days' => [0, 1, 2, 3, 4],
    'start' => '09:00',
    'end' => '18:00',

    // "Approaching breach" window before sla_due_at
    'approaching_minutes' => 60,
];