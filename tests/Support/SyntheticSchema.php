<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Tests\Support;

/**
 * Generates a large schema for performance tests and benchmarks.
 *
 * `define(300)` declares 300 object types (`Thing0` … `Thing299`, each with
 * nine scalar fields and a `next` link to the following type) and one root
 * query per type (`thing0` … `thing299`), and returns the matching
 * `laragraph.types` / schema `query` config. Classes are generated once per
 * process and size.
 */
final class SyntheticSchema
{
    /** @var list<string> Type classes constructed so far, in order (reset by the caller). */
    public static array $constructed = [];

    /**
     * @return array{types: array<string, class-string>, query: array<string, class-string>}
     */
    public static function define(int $size): array
    {
        $namespace = __NAMESPACE__ . "\\Synthetic\\Size{$size}";

        if (!class_exists("{$namespace}\\Thing0Type", false)) {
            eval(self::source($namespace, $size));
        }

        $config = ['types' => [], 'query' => []];

        for ($i = 0; $i < $size; $i++) {
            $config['types']["Thing{$i}"] = "{$namespace}\\Thing{$i}Type";
            $config['query']["thing{$i}"] = "{$namespace}\\Thing{$i}Query";
        }

        return $config;
    }

    private static function source(string $namespace, int $size): string
    {
        $code = "namespace {$namespace};\n"
            . "use Ayimdomnic\\Laragraph\\Facades\\Laragraph;\n"
            . "use GraphQL\\Type\\Definition\\ResolveInfo;\n"
            . "use GraphQL\\Type\\Definition\\Type as GType;\n";

        for ($i = 0; $i < $size; $i++) {
            $next = ($i + 1) % $size;

            $code .= <<<PHP
                class Thing{$i}Type extends \\Ayimdomnic\\Laragraph\\Support\\Type
                {
                    protected array \$attributes = ['name' => 'Thing{$i}'];

                    public function __construct()
                    {
                        \\Ayimdomnic\\Laragraph\\Tests\\Support\\SyntheticSchema::\$constructed[] = 'Thing{$i}';
                        parent::__construct();
                    }

                    public function fields(): array
                    {
                        return [
                            'id' => GType::nonNull(GType::id()),
                            'f0' => GType::string(), 'f1' => GType::string(), 'f2' => GType::string(),
                            'f3' => GType::string(), 'f4' => GType::int(), 'f5' => GType::int(),
                            'f6' => GType::boolean(), 'f7' => GType::float(),
                            'next' => Laragraph::type('Thing{$next}'),
                        ];
                    }
                }

                class Thing{$i}Query extends \\Ayimdomnic\\Laragraph\\Support\\Query
                {
                    public function type(): GType
                    {
                        return Laragraph::type('Thing{$i}');
                    }

                    public function args(): array
                    {
                        return ['id' => ['type' => GType::id()]];
                    }

                    public function resolve(mixed \$root, array \$args, mixed \$context, ResolveInfo \$info): mixed
                    {
                        return ['id' => \$args['id'] ?? '1', 'f0' => 'a', 'f4' => 4, 'next' => ['id' => '2', 'f1' => 'b']];
                    }
                }

                PHP;
        }

        return $code;
    }
}
