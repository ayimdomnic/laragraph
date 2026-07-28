<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Scalars;

use Ayimdomnic\Laragraph\Support\ScalarType;
use GraphQL\Error\Error;
use GraphQL\Language\AST\Node;

/**
 * A scalar representing a file upload.
 *
 * File uploads use the GraphQL Multipart Request Spec:
 * https://github.com/jaydenseric/graphql-multipart-request-spec
 *
 * In your mutation, type the argument as Upload:
 *
 *   'avatar' => ['type' => app('laragraph')->type('Upload')]
 *
 * The resolved value will be an \Illuminate\Http\UploadedFile instance.
 */
class UploadType extends ScalarType
{
    public string $name = 'Upload';
    public ?string $description = 'A file uploaded via the GraphQL multipart request spec.';

    public function serialize(mixed $value): never
    {
        throw new Error('Upload scalars cannot be serialised in responses.');
    }

    public function parseValue(mixed $value): mixed
    {
        if ($value instanceof \Illuminate\Http\UploadedFile) {
            return $value;
        }

        throw new Error('Upload value must be an UploadedFile instance.');
    }

    public function parseLiteral(Node $valueNode, ?array $variables = null): never
    {
        throw new Error('Upload literals are not supported; use variables.');
    }
}
