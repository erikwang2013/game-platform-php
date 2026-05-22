<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\Security\Detector;

class GraphqlInjectionDetector extends AbstractRegexDetector
{
    public function name(): string
    {
        return 'graphql_injection';
    }

    protected function patterns(): array
    {
        return [
            '/\b__schema\b/'            => ['severity' => 'high',     'detail' => 'GraphQL schema introspection'],
            '/\b__type\b/'              => ['severity' => 'high',     'detail' => 'GraphQL type introspection'],
            '/\b__typename\b/'          => ['severity' => 'low',      'detail' => 'GraphQL __typename field access'],
            '/\{\s*\w+\s*\{[^}]*\{/'   => ['severity' => 'medium',   'detail' => 'Deeply nested GraphQL query'],
            '/fragment\s+\w+\s+on\s+\w+/i'
                                                => ['severity' => 'medium',   'detail' => 'GraphQL fragment usage'],
            '/query\s+\w+\s*\{[^}]*\{[^}]*\{[^}]*\{[^}]*\{/i'
                                                => ['severity' => 'high',     'detail' => 'Excessively nested query (DoS)'],
            '/\bmutation\s+\w+\s*\{/i' => ['severity' => 'medium',   'detail' => 'GraphQL mutation detected'],
            '/\bsubscription\s+\w+\s*\{/i'
                                                => ['severity' => 'medium',   'detail' => 'GraphQL subscription detected'],
            '/\,?\s*__schema\s*\{/i'   => ['severity' => 'critical', 'detail' => 'GraphQL schema dump attempt'],
            '/\,?\s*__type\s*\(\s*name\s*:/i'
                                                => ['severity' => 'critical', 'detail' => 'GraphQL type detail query'],
        ];
    }
}
