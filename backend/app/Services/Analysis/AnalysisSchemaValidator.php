<?php

declare(strict_types=1);

namespace App\Services\Analysis;

use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Validator;

/**
 * Validates the LLM output against app/Services/Analysis/schemas/cv_analysis.schema.json.
 */
class AnalysisSchemaValidator
{
    /**
     * @param  array<string, mixed>  $payload
     * @return list<string> empty when valid
     */
    public function validate(array $payload): array
    {
        $schema = file_get_contents(__DIR__.'/schemas/cv_analysis.schema.json');
        if ($schema === false) {
            return ['Schéma d\'analyse introuvable.'];
        }

        $decoded = json_decode($schema, false);
        if (! is_object($decoded)) {
            return ['Schéma d\'analyse invalide.'];
        }

        // opis maps PHP arrays to JSON arrays; objects must be stdClass.
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
