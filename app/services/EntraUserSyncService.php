<?php
namespace App\Services;

use App\Core\Database;
use DateTimeImmutable;

final class EntraUserSyncService
{
    public function __construct(private ?GraphService $graph = null)
    {
        $this->graph ??= new GraphService();
    }

    public function directoryUsers(?string $search = null, int $limit = 250): array
    {
        $settings = (new EntraAccessService($this->graph))->settings();
        $select = 'id,displayName,mail,userPrincipalName,department,jobTitle,officeLocation,businessPhones,mobilePhone,accountEnabled,userType';
        $path = '/users?$select=' . rawurlencode($select) . '&$top=100&$orderby=displayName';
        if ($search !== null && trim($search) !== '') {
            $term = str_replace("'", "''", trim($search));
            $path = '/users?$select=' . rawurlencode($select) . '&$top=100&$filter=' . rawurlencode("startsWith(displayName,'{$term}') or startsWith(userPrincipalName,'{$term}')") . '&$orderby=displayName&$count=true';
        }

        $rows = [];
        do {
            $result = $this->graph->request('GET', $path);
            foreach (($result['value'] ?? []) as $user) {
                if (($user['userType'] ?? 'Member') !== 'Member') { continue; }
                $normalized = $this->normalize($user);
                if (!empty($settings['import_enabled_only']) && !$normalized['account_enabled']) { continue; }
                if (!empty($settings['department_filter']) && strcasecmp($normalized['department'], (string)$settings['department_filter']) !== 0) { continue; }
                if (!empty($settings['group_filter_id']) && !$this->isGroupMember($normalized['id'], (string)$settings['group_filter_id'])) { continue; }
                $rows[] = $normalized;
                if (count($rows) >= $limit) {
                    break 2;
                }
            }
            $next = $result['@odata.nextLink'] ?? null;
            $path = $next ? preg_replace('#^https://graph\.microsoft\.com/v1\.0#', '', $next) : null;
        } while ($path);

        $pdo = Database::connection();
        $existing = $pdo->query("SELECT id,microsoft_object_id,email,role,status FROM users")->fetchAll();
        $byOid = [];
        $byEmail = [];
        foreach ($existing as $row) {
            if (!empty($row['microsoft_object_id'])) {
                $byOid[strtolower((string)$row['microsoft_object_id'])] = $row;
            }
            $byEmail[strtolower((string)$row['email'])] = $row;
        }
        foreach ($rows as &$row) {
            $match = $byOid[strtolower($row['id'])] ?? $byEmail[strtolower($row['email'])] ?? null;
            $row['portal_user_id'] = $match['id'] ?? null;
            $row['portal_role'] = $match['role'] ?? null;
            $row['portal_status'] = $match['status'] ?? null;
        }
        unset($row);
        return $rows;
    }

    public function import(array $objectIds, string $role = 'technician'): array
    {
        $allowedRoles = ['administrator','owner','office','estimator','crew_leader','technician'];
        if (!in_array($role, $allowedRoles, true)) {
            $role = 'technician';
        }
        $created = 0;
        $updated = 0;
        $errors = [];
        foreach (array_unique(array_filter($objectIds)) as $oid) {
            try {
                $resolvedRole = $role;
                $raw = $this->graph->request('GET', '/users/' . rawurlencode((string)$oid) . '?$select=id,displayName,mail,userPrincipalName,department,jobTitle,officeLocation,businessPhones,mobilePhone,accountEnabled,userType');
                $user = $this->normalize($raw);
                if ($user['email'] === '') {
                    throw new \RuntimeException('User has no email or UPN.');
                }
                $settings = (new EntraAccessService($this->graph))->settings();
                if (!empty($settings['sync_group_roles'])) { $resolvedRole = (new EntraAccessService($this->graph))->roleForUser($user['id'], $resolvedRole); }
                $manager = !empty($settings['sync_managers']) ? (new EntraAccessService($this->graph))->manager($user['id']) : null;
                $result = $this->upsert($user, $resolvedRole, $manager);
                $result === 'created' ? $created++ : $updated++;
            } catch (\Throwable $e) {
                $errors[] = $oid . ': ' . $e->getMessage();
            }
        }
        $this->log('import', $created, $updated, 0, $errors);
        return compact('created', 'updated', 'errors');
    }

    public function syncAll(bool $disableMissing = false): array
    {
        $directory = $this->directoryUsers(null, 5000);
        $seen = [];
        $created = 0;
        $updated = 0;
        $disabled = 0;
        $errors = [];
        foreach ($directory as $user) {
            try {
                $seen[] = $user['id'];
                $settings = (new EntraAccessService($this->graph))->settings();
                $role = $user['portal_role'] ?: 'technician';
                if (!empty($settings['sync_group_roles'])) { $resolvedRole = (new EntraAccessService($this->graph))->roleForUser($user['id'], $resolvedRole); }
                $manager = !empty($settings['sync_managers']) ? (new EntraAccessService($this->graph))->manager($user['id']) : null;
                $result = $this->upsert($user, $role, $manager);
                $result === 'created' ? $created++ : $updated++;
            } catch (\Throwable $e) {
                $errors[] = ($user['email'] ?: $user['id']) . ': ' . $e->getMessage();
            }
        }
        if ($disableMissing && $seen) {
            $placeholders = implode(',', array_fill(0, count($seen), '?'));
            $q = Database::connection()->prepare("UPDATE users SET status='disabled' WHERE identity_source='entra' AND microsoft_object_id IS NOT NULL AND microsoft_object_id NOT IN ({$placeholders})");
            $q->execute($seen);
            $disabled = $q->rowCount();
        }
        $this->log('full_sync', $created, $updated, $disabled, $errors);
        return compact('created', 'updated', 'disabled', 'errors');
    }

    public function syncOne(string $objectId, ?string $role = null): array
    {
        $raw = $this->graph->request('GET','/users/' . rawurlencode($objectId) . '?$select=id,displayName,mail,userPrincipalName,department,jobTitle,officeLocation,businessPhones,mobilePhone,accountEnabled,userType');
        $user = $this->normalize($raw);
        $settings = (new EntraAccessService($this->graph))->settings();
        $resolved = $role ?: 'technician';
        if (!empty($settings['sync_group_roles'])) { $resolved = (new EntraAccessService($this->graph))->roleForUser($objectId,$resolved); }
        $manager = !empty($settings['sync_managers']) ? (new EntraAccessService($this->graph))->manager($objectId) : null;
        $result = $this->upsert($user,$resolved,$manager);
        return ['result'=>$result,'user'=>$user,'role'=>$resolved,'manager'=>$manager];
    }

    public function preview(): array
    {
        $rows=$this->directoryUsers(null,5000);
        $summary=['create'=>0,'update'=>0,'disable'=>0,'unchanged'=>0,'rows'=>[]];
        foreach($rows as $row){
            $action=$row['portal_user_id'] ? 'update' : 'create';
            $summary[$action]++;
            $summary['rows'][]=['id'=>$row['id'],'name'=>$row['name'],'email'=>$row['email'],'department'=>$row['department'],'current_role'=>$row['portal_role'],'action'=>$action];
        }
        return $summary;
    }

    private function upsert(array $user, string $role, ?array $manager = null): string
    {
        $pdo = Database::connection();
        $q = $pdo->prepare('SELECT id,role FROM users WHERE microsoft_object_id=? OR LOWER(email)=LOWER(?) LIMIT 1');
        $q->execute([$user['id'], $user['email']]);
        $existing = $q->fetch();
        $status = $user['account_enabled'] ? 'active' : 'disabled';
        $phone = $user['mobile_phone'] ?: $user['business_phone'];
        if ($existing) {
            $stmt = $pdo->prepare('UPDATE users SET name=?,email=?,microsoft_object_id=?,entra_email=?,entra_upn=?,department=?,job_title=?,office_location=?,business_phone=?,mobile_phone=?,status=?,role=?,identity_source=\'entra\',last_synced_at=NOW(),entra_manager_object_id=?,entra_manager_name=?,entra_manager_email=? WHERE id=?');
            $stmt->execute([$user['name'],$user['email'],$user['id'],$user['email'],$user['upn'],$user['department'],$user['job_title'],$user['office_location'],$user['business_phone'],$phone,$status,$role,$manager['id']??null,$manager['name']??null,$manager['email']??null,$existing['id']]);
            return 'updated';
        }
        $stmt = $pdo->prepare('INSERT INTO users(name,email,password_hash,role,status,microsoft_object_id,entra_email,entra_upn,department,job_title,office_location,business_phone,mobile_phone,identity_source,last_synced_at,entra_manager_object_id,entra_manager_name,entra_manager_email) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,\'entra\',NOW(),?,?,?)');
        $stmt->execute([$user['name'],$user['email'],password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT),$role,$status,$user['id'],$user['email'],$user['upn'],$user['department'],$user['job_title'],$user['office_location'],$user['business_phone'],$phone,$manager['id']??null,$manager['name']??null,$manager['email']??null]);
        return 'created';
    }


    private function isGroupMember(string $objectId, string $groupId): bool
    {
        try {
            $result=$this->graph->request('POST','/users/' . rawurlencode($objectId) . '/checkMemberGroups',['groupIds'=>[$groupId]]);
            return in_array($groupId,$result['value']??[],true);
        } catch (\Throwable) {
            return false;
        }
    }

    private function normalize(array $user): array
    {
        $phones = $user['businessPhones'] ?? [];
        return [
            'id' => (string)($user['id'] ?? ''),
            'name' => trim((string)($user['displayName'] ?? '')),
            'email' => strtolower(trim((string)($user['mail'] ?? $user['userPrincipalName'] ?? ''))),
            'upn' => strtolower(trim((string)($user['userPrincipalName'] ?? ''))),
            'department' => trim((string)($user['department'] ?? '')),
            'job_title' => trim((string)($user['jobTitle'] ?? '')),
            'office_location' => trim((string)($user['officeLocation'] ?? '')),
            'business_phone' => trim((string)($phones[0] ?? '')),
            'mobile_phone' => trim((string)($user['mobilePhone'] ?? '')),
            'account_enabled' => (bool)($user['accountEnabled'] ?? true),
        ];
    }

    private function log(string $type, int $created, int $updated, int $disabled, array $errors): void
    {
        $stmt = Database::connection()->prepare('INSERT INTO entra_sync_runs(sync_type,created_count,updated_count,disabled_count,error_count,error_detail,completed_at) VALUES(?,?,?,?,?,?,NOW())');
        $stmt->execute([$type,$created,$updated,$disabled,count($errors),$errors ? implode("\n", $errors) : null]);
    }
}
