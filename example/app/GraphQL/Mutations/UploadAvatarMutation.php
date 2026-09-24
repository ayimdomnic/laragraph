<?php

declare(strict_types=1);

namespace App\GraphQL\Mutations;

use Ayimdomnic\Laragraph\Facades\Laragraph;
use Ayimdomnic\Laragraph\Support\Mutation;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;
use Illuminate\Http\UploadedFile;

/**
 * `uploadAvatar(file: Upload!)` — FILE UPLOADS via the GraphQL multipart
 * request spec. $args['file'] is an Illuminate\Http\UploadedFile.
 */
class UploadAvatarMutation extends Mutation
{
    public function type(): Type
    {
        return Type::nonNull(Laragraph::type('User'));
    }

    public function args(): array
    {
        return ['file' => ['type' => Type::nonNull(Laragraph::type('Upload'))]];
    }

    public function authorize(mixed $root, array $args, mixed $context, ResolveInfo $info): bool
    {
        return $context->user() !== null;
    }

    public function rules(array $args = []): array
    {
        return ['file' => ['required', 'image', 'max:2048']];
    }

    public function resolve(mixed $root, array $args, mixed $context, ResolveInfo $info): mixed
    {
        /** @var UploadedFile $file */
        $file = $args['file'];
        $user = $context->user();

        $user->update(['avatar_path' => $file->store('avatars', 'public')]);

        return $user;
    }
}
