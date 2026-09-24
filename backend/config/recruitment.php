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

    // Predefined application-form field catalog (Part I §4.4).
    // Copied into offer_form_fields when the recruiter selects a field.
    // `locked` fields (full_name, email, cv) are always present and required.
    // `sensitive` fields are optional by default and excluded from AI analysis.
    'fields' => [
        'full_name' => ['label' => 'Nom complet', 'type' => 'text', 'locked' => true, 'sensitive' => false, 'required' => true],
        'email' => ['label' => 'Email', 'type' => 'email', 'locked' => true, 'sensitive' => false, 'required' => true],
        'cv' => ['label' => 'CV (PDF, DOCX)', 'type' => 'file', 'locked' => true, 'sensitive' => false, 'required' => true],
        'phone' => ['label' => 'Téléphone', 'type' => 'phone', 'locked' => false, 'sensitive' => false, 'required' => false],
        'photo' => ['label' => 'Photo', 'type' => 'image', 'locked' => false, 'sensitive' => true, 'required' => false],
        'age' => ['label' => 'Âge', 'type' => 'number', 'locked' => false, 'sensitive' => true, 'required' => false],
        'birth_date' => ['label' => 'Date de naissance', 'type' => 'date', 'locked' => false, 'sensitive' => true, 'required' => false],
        'city' => ['label' => 'Ville', 'type' => 'text', 'locked' => false, 'sensitive' => false, 'required' => false],
        'country' => ['label' => 'Pays', 'type' => 'text', 'locked' => false, 'sensitive' => false, 'required' => false],
        'linkedin' => ['label' => 'LinkedIn', 'type' => 'url', 'locked' => false, 'sensitive' => false, 'required' => false],
        'portfolio' => ['label' => 'GitHub ou portfolio', 'type' => 'url', 'locked' => false, 'sensitive' => false, 'required' => false],
        'cover_letter' => ['label' => 'Lettre de motivation', 'type' => 'textarea', 'locked' => false, 'sensitive' => false, 'required' => false],
        'salary_expectation' => ['label' => 'Prétentions salariales', 'type' => 'number', 'locked' => false, 'sensitive' => false, 'required' => false],
        'availability' => ['label' => 'Disponibilité ou préavis', 'type' => 'text', 'locked' => false, 'sensitive' => false, 'required' => false],
        'diplomas' => ['label' => 'Diplômes et certificats', 'type' => 'file', 'locked' => false, 'sensitive' => false, 'required' => false],
    ],

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

    'consent_version' => 'v1',

    'llm' => [
        'max_input_chars' => (int) env('LLM_MAX_INPUT_CHARS', 30000),
        'timeout' => (int) env('LLM_TIMEOUT', 60),
        'daily_limit_per_user' => (int) env('LLM_DAILY_LIMIT_PER_USER', 300),
        'prompt_version' => env('PROMPT_VERSION', 'v1'),
    ],
];
