<?php

declare(strict_types=1);

namespace AcmeLearn\Importer\Api\Resolvers\Query;

use AcmeLearn\Importer\Api\Resolvers\query_resolver;
use AcmeLearn\Importer\UserRepository;
use PDO;

/**
 * Resolves the `usersCount` query: total number of users in the store.
 * Used by the client to calculate the number of pages.
 */
final class UsersCount extends query_resolver
{
    /**
     * @param array<string, mixed> $args
     */
    public function resolve(PDO $pdo, array $args): int
    {
        return (new UserRepository($pdo))->count();
    }
}
