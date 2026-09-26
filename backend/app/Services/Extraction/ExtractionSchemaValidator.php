<?php

declare(strict_types=1);

namespace App\Services\Extraction;

use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Validator;

/**
 * Validates the job-description extraction output against
 * app/Services/Extraction/schemas/offer_extraction.schema.json.
 */
class ExtractionSchemaValidator
{
    /**
     * @param  array<string, mixed>  $payload
     * @return list<string> empty when valid
     */
    public function validate(array $payload): array
    {
        $schema = file_get_contents(__DIR__.'/schemas/offer_extraction.schema.json');
        if ($schema === false) {
            return ['Schéma d\'extraction introuvable.'];
        }

        $decoded = json_decode($schema, false);
        if (! is_object($decoded)) {
            return ['Schéma d\'extraction invalide.'];
        }

        $data = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        $result = (new Validator)->validate(json_decode($data, false), $decoded);

        if ($result->isValid()) {
            return [];
        }

        $error = $result->error();
        if ($error === null) {
            return ['Sortie non conforme au schéma.'];
        }

        $formatter = new ErrorFormatter;

        return array_values(array_unique($formatter->formatFlat($error)));
    }
}
