<?php

declare(strict_types=1);

return [
    // Default scoring weights (Part I §5.3). Overridable per offer (offers.scoring_weights).
    'scoring_weights' => [
        'required_skills' => 35,
        'preferred_skills' => 10,
        'experience' => 30,
        'education' => 15,
        'languages' => 10,
    ],

    'knockout_threshold' => 40,

    'ats' => [
        'compliant_min' => 75,
        'improvable_min' => 50,
    ],

    'uploads' => [
        'cv_max_mb' => (int) env('CV_MAX_SIZE_MB', 5),
        'photo_max_mb' => (int) env('PHOTO_MAX_SIZE_MB', 2),
        'cv_mimes' => ['pdf', 'docx'],
        'photo_mimes' => ['jpg', 'jpeg', 'png'],
    ],

    'forward' => [
        'max_attachment_mb' => (int) env('FORWARD_MAX_ATTACHMENT_MB', 15),
        'max_candidates' => (int) env('FORWARD_MAX_CANDIDATES', 50),
        'max_recipients' => 10,
        'link_ttl_days' => 7,
    ],

    'retention_months' => (int) env('RETENTION_MONTHS', 12),

    'llm' => [
        'max_input_chars' => (int) env('LLM_MAX_INPUT_CHARS', 30000),
        'timeout' => (int) env('LLM_TIMEOUT', 60),
        'daily_limit_per_user' => (int) env('LLM_DAILY_LIMIT_PER_USER', 300),
        'prompt_version' => env('PROMPT_VERSION', 'v1'),
    ],
];
