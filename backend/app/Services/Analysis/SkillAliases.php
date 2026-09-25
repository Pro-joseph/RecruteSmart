<?php

declare(strict_types=1);

namespace App\Services\Analysis;

/**
 * Skill alias table (spec §6.6): aliases map to the canonical form.
 * Used by ATS-06 keyword matching and by SkillNormalizer.
 */
final class SkillAliases
{
    /** @var array<string, string> */
    public const ALIASES = [
        'js' => 'javascript',
        'ts' => 'typescript',
        'postgres' => 'postgresql',
        'pg' => 'postgresql',
        'mysql' => 'mysql',
        'node' => 'nodejs',
        'node.js' => 'nodejs',
        'node js' => 'nodejs',
        'react.js' => 'reactjs',
        'react js' => 'reactjs',
        'vue.js' => 'vuejs',
        'vue js' => 'vuejs',
        'next.js' => 'nextjs',
        'k8s' => 'kubernetes',
        'golang' => 'go',
        'py' => 'python',
        'yml' => 'yaml',
        'ci/cd' => 'cicd',
        'ci cd' => 'cicd',
        'html5' => 'html',
        'css3' => 'css',
        'mongo' => 'mongodb',
        'elastic' => 'elasticsearch',
        'kafka' => 'apachekafka',
        'tf' => 'terraform',
        'amazon web services' => 'aws',
        'ms sql' => 'mssql',
        'sqlserver' => 'mssql',
    ];

    public static function canonical(string $skill): string
    {
        $key = mb_strtolower(trim($skill));
        $key = preg_replace('/\s+/u', ' ', $key) ?? $key;

        return self::ALIASES[$key] ?? $key;
    }

    /**
     * All textual variants (canonical + aliases + simple plural) to look for in a CV.
     *
     * @return list<string>
     */
    public static function variants(string $skill): array
    {
        $canonical = self::canonical($skill);
        $variants = [$canonical, $canonical.'s'];

        foreach (self::ALIASES as $alias => $target) {
            if ($target === $canonical && ! in_array($alias, $variants, true)) {
                $variants[] = $alias;
            }
        }

        return array_values(array_unique($variants));
    }
}
