<?php

declare(strict_types=1);

namespace App\Enums;

use GraphQL\Type\Definition\Description;

/**
 * Exposed to GraphQL as the `UserRole` enum simply by registering the class in
 * config/laragraph.php — no wrapper type needed.
 */
#[Description('What a user may do inside their organization.')]
enum UserRole: string
{
    #[Description('Manages the organization: sees every member and every post.')]
    case Admin = 'admin';

    #[Description('Writes and publishes their own posts.')]
    case Member = 'member';
}
