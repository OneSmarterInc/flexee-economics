<?php

return [
    /*
    | Password for the demo accounts the seeder creates. In production the seeder creates no demo
    | accounts unless this is set to 16 or more characters.
    */
    'seed_demo_password' => env('SEED_DEMO_PASSWORD', ''),

    /*
    | The AI behind the advisors. With no API key, a stand-in gives fixed replies so the screens
    | still work. The model is set here so it can change without a code change.
    */
    'ai' => [
        'anthropic_key' => env('ANTHROPIC_API_KEY', ''),
        'model' => env('HALDEN_AI_MODEL', 'claude-sonnet-5-5'),
        'max_tokens' => (int) env('HALDEN_AI_MAX_TOKENS', 600),
    ],
];
