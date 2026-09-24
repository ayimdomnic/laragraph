<?php

declare(strict_types=1);

namespace App\Enums;

use GraphQL\Type\Definition\Description;

#[Description('Where a post is in its lifecycle.')]
enum PostStatus: string
{
    #[Description('Only visible to its author and organization admins.')]
    case Draft = 'draft';

    #[Description('Visible to everyone.')]
    case Published = 'published';
}
