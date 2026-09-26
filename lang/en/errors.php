<?php

declare(strict_types=1);

// Default English text for every message Laragraph itself produces. Publish
// this file (`php artisan vendor:publish --tag=laragraph-lang`) to translate
// it, or drop overrides straight into lang/vendor/laragraph/{locale}/errors.php.
return [
    'internal' => [
        'default'    => 'Internal server error',
        'unexpected' => 'An unexpected error occurred.',
    ],

    'validation' => [
        'default' => 'Validation failed.',
    ],

    'authorization' => [
        'default' => 'Unauthorized.',
        'field'   => 'You are not authorized to access :field.',
        'policy'  => 'Policy check failed for :field.',
    ],

    'batching' => [
        'disabled'       => 'GraphQL batch requests are disabled.',
        'limit_exceeded' => 'Batch size exceeds the maximum of :limit operations.',
    ],

    'request' => [
        'schema_not_found'              => 'Schema [:schema] does not exist.',
        'method_not_allowed'            => 'Mutations cannot be executed over GET; send them with POST.',
        'persisted_query_not_found'     => 'PersistedQueryNotFound',
        'persisted_query_hash_mismatch' => 'provided sha does not match query',
        'persisted_query_required'      => 'Only persisted queries are allowed.',
    ],
];
