<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Recruiter-customisable email copy (EF-906): invitation, refusal and
 * confirmation. Absence of a row means the built-in default is used.
 *
 * @property int $id
 * @property int $user_id
 * @property string $key
 * @property string $subject
 * @property string $body
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class EmailTemplate extends Model
{
    public const CONFIRMATION = 'confirmation';

    public const INVITATION = 'invitation';

    public const REFUSAL = 'refusal';

    /**
     * @var list<string>
     */
    public const KEYS = [self::CONFIRMATION, self::INVITATION, self::REFUSAL];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'key',
        'subject',
        'body',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Default copy shipped with the product, shown when nothing is customised.
     *
     * @return array<string, array{subject: string, body: string}>
     */
    public static function defaults(): array
    {
        return [
            self::CONFIRMATION => [
                'subject' => 'Candidature bien reçue — {offer_title}',
                'body' => "Bonjour {candidate_name},\n\nNous avons bien reçu votre candidature pour l'offre {offer_title}.\n\nLe recruteur reviendra vers vous si votre profil correspond.",
            ],
            self::INVITATION => [
                'subject' => 'Invitation à un entretien — {offer_title}',
                'body' => "Bonjour {candidate_name},\n\nVous êtes invité(e) à un entretien pour l'offre {offer_title} le {starts_at} ({duration} minutes).\n\nL'invitation calendrier (.ics) est jointe à cet email.",
            ],
            self::REFUSAL => [
                'subject' => 'Votre candidature — {offer_title}',
                'body' => "Bonjour {candidate_name},\n\nNous vous remercions pour l'intérêt que vous portez à l'offre {offer_title}.\n\nAprès étude de votre candidature, nous avons décidé de ne pas donner suite à ce stade.\n\nNous vous souhaitons beaucoup de succès dans vos recherches.",
            ],
        ];
    }
}
