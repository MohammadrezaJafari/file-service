<?php

return [
    // Filesystem disk used to store file blobs.
    'disk' => env('FILESERVICE_DISK', 'blobs'),

    // Base URL of the Quasar frontend, used to build public share links.
    'frontend_url' => env('FRONTEND_URL', 'http://localhost:9000'),
];
