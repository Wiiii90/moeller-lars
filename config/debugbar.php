<?php

return [
    // Profiling is opt-in even in local development so normal browser work keeps its baseline overhead.
    'enabled' => env('DEBUGBAR_ENABLED', false),

    // Never allow an environment variable to turn this development dependency into a Production debug surface.
    'force_allow_enable' => false,
];
