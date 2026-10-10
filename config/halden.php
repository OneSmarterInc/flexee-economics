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
    /*
    | Whether Halden sends email (logins when an instructor adds students, deadline reminders).
    | On by default once MAIL_MAILER is something other than "log"; set HALDEN_MAIL=false to hold it.
    */
    'mail_enabled' => filter_var(env('HALDEN_MAIL', env('MAIL_MAILER', 'log') !== 'log'), FILTER_VALIDATE_BOOL),

    'ai' => [
        'anthropic_key' => env('ANTHROPIC_API_KEY', ''),
        'model' => env('HALDEN_AI_MODEL', 'claude-sonnet-5-5'),
        'max_tokens' => (int) env('HALDEN_AI_MAX_TOKENS', 2000),
    ],
];
