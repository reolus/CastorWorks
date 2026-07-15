<?php
namespace App\Services;

use App\Core\Database;

final class EntraAccessService
{
    public function __construct(private ?GraphService $graph = null)
    {
        $this->graph ??= new GraphService();
    }

    public function groups(?string $search = null): array
    {
        $select = 'id,displayName,description,securityEnabled,mailEnabled';
        $path = '/groups?$select=' . rawurlencode($select) . '&$top=100&$orderby=displayName';
        if ($search !== null && trim($search) !== '') {
            $term = str_replace("'", "''", trim($search));
            $path = '/groups?$select=' . rawurlencode($select) . '&$top=100&$filter=' . rawurlencode("startsWith(displayName,'{$term}')") . '&$orderby=displayName&$count=true';
        }
        $rows = [];
        do {
            $result = $this->graph->request('GET', $path);
            foreach (($result['value'] ?? []) as $group) {
                $rows[] = $group;
            }
            $next = $result['@odata.nextLink'] ?? null;
            $path = $next ? preg_replace('#^https://graph\.microsoft\.com/v1\.0#', '', $next) : null;
        } while ($path && count($rows) < 500);
        return $rows;
    }

    public function mappings(): array
    {
        return Database::connection()->query('SELECT * FROM entra_group_role_mappings ORDER BY priority,entra_group_name')->fetchAll();
    }

    public function saveMapping(array $data): void
    {
        $roles = ['administrator','owner','office','estimator','crew_leader','technician'];
        $role = in_array($data['portal_role'] ?? '', $roles, true) ? $data['portal_role'] : 'technician';
        $stmt = Database::connection()->prepare('INSERT INTO entra_group_role_mappings(entra_group_id,entra_group_name,portal_role,priority,active) VALUES(?,?,?,?,?) ON DUPLICATE KEY UPDATE entra_group_name=VALUES(entra_group_name),portal_role=VALUES(portal_role),priority=VALUES(priority),active=VALUES(active)');
        $stmt->execute([(string)$data['entra_group_id'],trim((string)$data['entra_group_name']),$role,max(1,(int)($data['priority'] ?? 100)),!empty($data['active']) ? 1 : 0]);
    }

    public function deleteMapping(int $id): void
    {
        Database::connection()->prepare('DELETE FROM entra_group_role_mappings WHERE id=?')->execute([$id]);
    }

    public function roleForUser(string $objectId, string $fallback = 'technician'): string
    {
        $mappings = array_filter($this->mappings(), fn(array $m): bool => (bool)$m['active']);
        foreach ($mappings as $mapping) {
            try {
                $path = '/groups/' . rawurlencode((string)$mapping['entra_group_id']) . '/members?$select=id&$top=999';
                do {
                    $result = $this->graph->request('GET', $path);
                    foreach (($result['value'] ?? []) as $member) {
                        if (strcasecmp((string)($member['id'] ?? ''), $objectId) === 0) {
                            return (string)$mapping['portal_role'];
                        }
                    }
                    $next = $result['@odata.nextLink'] ?? null;
                    $path = $next ? preg_replace('#^https://graph\.microsoft\.com/v1\.0#', '', $next) : null;
                } while ($path);
            } catch (\Throwable) {
                continue;
            }
        }
        return $fallback;
    }

    public function manager(string $objectId): ?array
    {
        try {
            $manager = $this->graph->request('GET','/users/' . rawurlencode($objectId) . '/manager?$select=id,displayName,mail,userPrincipalName');
            return [
                'id'=>(string)($manager['id'] ?? ''),
                'name'=>(string)($manager['displayName'] ?? ''),
                'email'=>strtolower((string)($manager['mail'] ?? $manager['userPrincipalName'] ?? '')),
            ];
        } catch (\Throwable) {
            return null;
        }
    }

    public function settings(): array
    {
        return Database::connection()->query('SELECT * FROM entra_sync_settings WHERE id=1')->fetch() ?: [];
    }

    public function saveSettings(array $data, ?int $userId): void
    {
        $stmt=Database::connection()->prepare('UPDATE entra_sync_settings SET department_filter=?,group_filter_id=?,import_enabled_only=?,disable_missing=?,sync_managers=?,sync_group_roles=?,schedule_enabled=?,schedule_time=?,updated_by=? WHERE id=1');
        $stmt->execute([
            trim((string)($data['department_filter'] ?? '')) ?: null,
            trim((string)($data['group_filter_id'] ?? '')) ?: null,
            !empty($data['import_enabled_only']) ? 1 : 0,
            !empty($data['disable_missing']) ? 1 : 0,
            !empty($data['sync_managers']) ? 1 : 0,
            !empty($data['sync_group_roles']) ? 1 : 0,
            !empty($data['schedule_enabled']) ? 1 : 0,
            preg_match('/^\d{2}:\d{2}$/',(string)($data['schedule_time'] ?? '')) ? $data['schedule_time'].':00' : '02:00:00',
            $userId,
        ]);
    }
}
