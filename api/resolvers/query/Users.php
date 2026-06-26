<?php

declare(strict_types=1);

namespace AcmeLearn\Importer\Api\Resolvers\Query;

use AcmeLearn\Importer\Api\Resolvers\query_resolver;
use AcmeLearn\Importer\Api\UserMapper;
use AcmeLearn\Importer\UserRepository;
use PDO;

/**
 * Resolves the `users` query: read every user through the existing repository
 * and map each row into the GraphQL User shape.
 */
final class Users extends query_resolver
{
    /**
     * @param array{limit?: int, offset?: int} $args
     *
     * @return array<int, array<string, string|int|bool>>
     */
    public function resolve(PDO $pdo, array $args): array
    {
        $repository = new UserRepository($pdo);

        return array_map(
            static fn (array $row): array => UserMapper::toGraphql($row),
            $repository->findAll($args['limit'] ?? 100, $args['offset'] ?? 0),
        );
    }
}
