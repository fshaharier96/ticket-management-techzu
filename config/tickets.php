<?php

return [
    // Auto-assign every new ticket on creation? (Off by default so the "unassigned queue / accept" flow is visible.)
    'auto_assign_on_create' => env('TICKETS_AUTO_ASSIGN', false),

    'attachments' => [
        'disk' => 'local',      // private disk, never public
        'max_files' => 5,
        'max_kb' => 5120,       // 5 MB per file
        'mimes' => ['jpg', 'jpeg', 'png', 'pdf', 'txt', 'csv'],
    ],
];