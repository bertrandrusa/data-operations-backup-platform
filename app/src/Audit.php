<?php

declare(strict_types=1);

namespace DataOps;

use PDO;

final class Audit
{
    public function __construct(private readonly PDO $db)
    {
    }

    /** @param array<string, mixed> $details */
    public function record(
        ?string $userId,
        string $action,
        string $resourceType,
        ?string $resourceId = null,
        array $details = []
    ): void {
        $statement = $this->db->prepare(
            'INSERT INTO audit_logs (user_id, action, resource_type, resource_id, details, ip_address)
             VALUES (:user_id, :action, :resource_type, :resource_id, CAST(:details AS jsonb), CAST(:ip AS inet))'
        );
        $statement->execute([
            'user_id' => $userId,
            'action' => $action,
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
            'details' => json_encode($details, JSON_THROW_ON_ERROR),
            'ip' => Security::clientIp(),
        ]);
    }
}

