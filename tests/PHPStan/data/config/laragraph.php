<?php

declare(strict_types=1);

return [
    'discover' => [
        'types' => 'app/GraphQL/Types',
    ],

    'types' => [
        'Foo' => 'Some\\Foo\\Class',
    ],

    'schemas' => [
        'admin' => [
            'types' => [
                'AdminStats' => 'Some\\AdminStats\\Class',
            ],
        ],
    ],
];
