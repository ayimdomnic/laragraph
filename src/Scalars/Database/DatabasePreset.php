<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Scalars\Database;

/**
 * Registry of database-specific scalar type presets.
 *
 * A preset is a named collection of GraphQL scalar types that map cleanly to
 * the native column types of a specific database engine.  Enabling a preset
 * via `laragraph.database_types.preset` automatically registers its scalars
 * so every schema in the application can use them without manual configuration.
 *
 * ## Supported presets
 *
 * | Preset         | Scalars registered                                              |
 * |----------------|-----------------------------------------------------------------|
 * | `postgres`     | UUID, BigInt, JSONB, Money, TSVector, Interval, Inet            |
 * | `cockroachdb`  | UUID, BigInt, JSONB, Inet                                       |
 * | `mssql`        | UUID, BigInt, Money                                             |
 * | `oracle`       | UUID, BigInt, Interval                                          |
 *
 * Additional scalars can always be added via `laragraph.database_types.custom`.
 *
 * ## Usage in config/laragraph.php
 *
 * ```php
 * 'database_types' => [
 *     'preset' => 'postgres',
 *     'custom' => [
 *         'EmailAddress' => \App\GraphQL\Scalars\EmailAddressType::class,
 *     ],
 * ],
 * ```
 */
final class DatabasePreset
{
    /** @var array<string, array<string, class-string>> */
    private const MAPS = [
        'postgres' => [
            'UUID'     => UuidType::class,
            'BigInt'   => BigIntType::class,
            'JSONB'    => JsonbType::class,
            'Money'    => MoneyType::class,
            'TSVector' => TsvectorType::class,
            'Interval' => IntervalType::class,
            'Inet'     => InetType::class,
        ],

        'cockroachdb' => [
            'UUID'   => UuidType::class,
            'BigInt' => BigIntType::class,
            'JSONB'  => JsonbType::class,
            'Inet'   => InetType::class,
        ],

        'mssql' => [
            'UUID'   => UuidType::class,
            'BigInt' => BigIntType::class,
            'Money'  => MoneyType::class,
        ],

        'oracle' => [
            'UUID'     => UuidType::class,
            'BigInt'   => BigIntType::class,
            'Interval' => IntervalType::class,
        ],
    ];

    /**
     * Return the type map for a given preset name.
     *
     * Keys are the GraphQL type names; values are FQCNs of ScalarType subclasses.
     * Returns an empty array for unrecognised preset names.
     *
     * @return array<string, class-string>
     */
    public static function types(string $preset): array
    {
        return self::MAPS[strtolower($preset)] ?? [];
    }

    /**
     * List all supported preset names.
     *
     * @return list<string>
     */
    public static function supported(): array
    {
        return array_keys(self::MAPS);
    }
}
