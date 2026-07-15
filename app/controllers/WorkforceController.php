<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\View;
use App\Services\AuditService;
use PDO;
use Throwable;

final class WorkforceController
{
    public function index(): void
    {
        Auth::requireRole('owner', 'office', 'crew_leader', 'administrator');
        $pdo = Database::connection();

        $crews = $pdo->query(
            "SELECT c.*,
                    leader.name AS leader_name,
                    vehicle.unit_number AS vehicle_unit,
                    territory.name AS territory_name,
                    COUNT(cm.user_id) AS member_count
             FROM crews c
             LEFT JOIN users leader ON leader.id = c.crew_leader_id
             LEFT JOIN vehicles vehicle ON vehicle.id = c.default_vehicle_id
             LEFT JOIN service_territories territory ON territory.id = c.service_territory_id
             LEFT JOIN crew_members cm ON cm.crew_id = c.id AND cm.active = 1
             GROUP BY c.id
             ORDER BY c.active DESC, c.name"
        )->fetchAll();

        $members = [];
        $rows = $pdo->query(
            "SELECT cm.*, u.name, u.email, u.role, c.name AS crew_name
             FROM crew_members cm
             JOIN users u ON u.id = cm.user_id
             JOIN crews c ON c.id = cm.crew_id
             WHERE cm.active = 1
             ORDER BY c.name, cm.is_primary DESC, u.name"
        )->fetchAll();
        foreach ($rows as $row) {
            $members[(int) $row['crew_id']][] = $row;
        }

        $skills = $pdo->query(
            "SELECT s.*,
                    COUNT(us.user_id) AS assigned_count
             FROM staff_skills s
             LEFT JOIN user_skills us ON us.skill_id = s.id
             GROUP BY s.id
             ORDER BY s.category, s.name"
        )->fetchAll();

        $skillAssignments = [];
        $skillRows = $pdo->query(
            "SELECT us.*, u.name AS user_name, s.name AS skill_name, s.category
             FROM user_skills us
             JOIN users u ON u.id = us.user_id
             JOIN staff_skills s ON s.id = us.skill_id
             ORDER BY u.name, s.category, s.name"
        )->fetchAll();
        foreach ($skillRows as $row) {
            $skillAssignments[(int) $row['user_id']][] = $row;
        }

        $users = $pdo->query(
            "SELECT id, name, email, role, status
             FROM users
             WHERE status = 'active'
             ORDER BY name"
        )->fetchAll();

        $vehicles = $pdo->query(
            "SELECT id, unit_number, make, model
             FROM vehicles
             WHERE status <> 'retired'
             ORDER BY unit_number"
        )->fetchAll();

        $territories = [];
        try {
            $territories = $pdo->query(
                "SELECT id, name FROM service_territories WHERE active = 1 ORDER BY name"
            )->fetchAll();
        } catch (Throwable) {
            // Territory management is optional on older installations.
        }

        $upcomingAvailability = $pdo->query(
            "SELECT a.*, u.name AS user_name
             FROM employee_availability a
             JOIN users u ON u.id = a.user_id
             WHERE a.availability_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 14 DAY)
               AND a.status IN ('limited', 'unavailable', 'time_off')
             ORDER BY a.availability_date, u.name
             LIMIT 50"
        )->fetchAll();

        View::render('portal/workforce/index', [
            'title' => 'Workforce Management',
            'crews' => $crews,
            'members' => $members,
            'skills' => $skills,
            'skillAssignments' => $skillAssignments,
            'users' => $users,
            'vehicles' => $vehicles,
            'territories' => $territories,
            'upcomingAvailability' => $upcomingAvailability,
        ], 'portal');
    }

    public function storeCrew(): void
    {
        Auth::requireRole('owner', 'office', 'administrator');
        verify_csrf();

        $name = trim((string) ($_POST['name'] ?? ''));
        if ($name === '') {
            flash('danger', 'Crew name is required.');
            redirect('/portal/workforce');
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            "INSERT INTO crews
                (name, crew_leader_id, default_vehicle_id, service_territory_id, color_label, notes, active)
             VALUES (?, ?, ?, ?, ?, ?, 1)"
        );
        $stmt->execute([
            $name,
            self::nullableInt($_POST['crew_leader_id'] ?? null),
            self::nullableInt($_POST['default_vehicle_id'] ?? null),
            self::nullableInt($_POST['service_territory_id'] ?? null),
            trim((string) ($_POST['color_label'] ?? '')) ?: null,
            trim((string) ($_POST['notes'] ?? '')) ?: null,
        ]);

        $crewId = (int) $pdo->lastInsertId();
        $leaderId = self::nullableInt($_POST['crew_leader_id'] ?? null);
        if ($leaderId !== null) {
            $this->upsertMember($pdo, $crewId, $leaderId, true);
        }

        AuditService::log('workforce.crew_created', 'crew', $crewId);
        flash('success', 'Crew created.');
        redirect('/portal/workforce');
    }

    public function updateCrew(string $id): void
    {
        Auth::requireRole('owner', 'office', 'administrator');
        verify_csrf();

        $crewId = (int) $id;
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            "UPDATE crews
             SET name = ?, crew_leader_id = ?, default_vehicle_id = ?, service_territory_id = ?,
                 color_label = ?, notes = ?, active = ?
             WHERE id = ?"
        );
        $stmt->execute([
            trim((string) ($_POST['name'] ?? '')),
            self::nullableInt($_POST['crew_leader_id'] ?? null),
            self::nullableInt($_POST['default_vehicle_id'] ?? null),
            self::nullableInt($_POST['service_territory_id'] ?? null),
            trim((string) ($_POST['color_label'] ?? '')) ?: null,
            trim((string) ($_POST['notes'] ?? '')) ?: null,
            isset($_POST['active']) ? 1 : 0,
            $crewId,
        ]);

        $leaderId = self::nullableInt($_POST['crew_leader_id'] ?? null);
        $pdo->prepare('UPDATE crew_members SET is_primary = 0 WHERE crew_id = ?')->execute([$crewId]);
        if ($leaderId !== null) {
            $this->upsertMember($pdo, $crewId, $leaderId, true);
        }

        AuditService::log('workforce.crew_updated', 'crew', $crewId);
        flash('success', 'Crew updated.');
        redirect('/portal/workforce');
    }

    public function addMember(string $id): void
    {
        Auth::requireRole('owner', 'office', 'crew_leader', 'administrator');
        verify_csrf();

        $crewId = (int) $id;
        $userId = (int) ($_POST['user_id'] ?? 0);
        if ($userId < 1) {
            flash('danger', 'Select an employee.');
            redirect('/portal/workforce');
        }

        $pdo = Database::connection();
        $this->upsertMember($pdo, $crewId, $userId, isset($_POST['is_primary']));
        AuditService::log('workforce.member_added', 'crew', $crewId, ['user_id' => $userId]);
        flash('success', 'Crew member assigned.');
        redirect('/portal/workforce');
    }

    public function removeMember(string $crewId, string $userId): void
    {
        Auth::requireRole('owner', 'office', 'crew_leader', 'administrator');
        verify_csrf();

        $pdo = Database::connection();
        $pdo->prepare(
            'UPDATE crew_members SET active = 0, ended_at = NOW(), is_primary = 0 WHERE crew_id = ? AND user_id = ?'
        )->execute([(int) $crewId, (int) $userId]);

        AuditService::log('workforce.member_removed', 'crew', (int) $crewId, ['user_id' => (int) $userId]);
        flash('success', 'Crew member removed.');
        redirect('/portal/workforce');
    }

    public function storeSkill(): void
    {
        Auth::requireRole('owner', 'office', 'administrator');
        verify_csrf();

        $name = trim((string) ($_POST['name'] ?? ''));
        if ($name === '') {
            flash('danger', 'Skill name is required.');
            redirect('/portal/workforce');
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            "INSERT INTO staff_skills(name, category, description, requires_certification, active)
             VALUES (?, ?, ?, ?, 1)
             ON DUPLICATE KEY UPDATE category = VALUES(category), description = VALUES(description),
                                     requires_certification = VALUES(requires_certification), active = 1"
        );
        $stmt->execute([
            $name,
            trim((string) ($_POST['category'] ?? 'General')) ?: 'General',
            trim((string) ($_POST['description'] ?? '')) ?: null,
            isset($_POST['requires_certification']) ? 1 : 0,
        ]);

        AuditService::log('workforce.skill_saved', 'staff_skill', (int) $pdo->lastInsertId());
        flash('success', 'Skill saved.');
        redirect('/portal/workforce');
    }

    public function assignSkill(): void
    {
        Auth::requireRole('owner', 'office', 'crew_leader', 'administrator');
        verify_csrf();

        $userId = (int) ($_POST['user_id'] ?? 0);
        $skillId = (int) ($_POST['skill_id'] ?? 0);
        if ($userId < 1 || $skillId < 1) {
            flash('danger', 'Select an employee and a skill.');
            redirect('/portal/workforce');
        }

        $level = (string) ($_POST['proficiency_level'] ?? 'qualified');
        if (!in_array($level, ['learning', 'qualified', 'advanced', 'trainer'], true)) {
            $level = 'qualified';
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            "INSERT INTO user_skills(user_id, skill_id, proficiency_level, verified_by, verified_at, notes)
             VALUES (?, ?, ?, ?, NOW(), ?)
             ON DUPLICATE KEY UPDATE proficiency_level = VALUES(proficiency_level),
                                     verified_by = VALUES(verified_by), verified_at = NOW(), notes = VALUES(notes)"
        );
        $stmt->execute([
            $userId,
            $skillId,
            $level,
            Auth::id(),
            trim((string) ($_POST['notes'] ?? '')) ?: null,
        ]);

        AuditService::log('workforce.skill_assigned', 'user', $userId, ['skill_id' => $skillId, 'level' => $level]);
        flash('success', 'Employee skill updated.');
        redirect('/portal/workforce');
    }

    public function removeSkill(string $userId, string $skillId): void
    {
        Auth::requireRole('owner', 'office', 'crew_leader', 'administrator');
        verify_csrf();

        Database::connection()->prepare('DELETE FROM user_skills WHERE user_id = ? AND skill_id = ?')
            ->execute([(int) $userId, (int) $skillId]);
        AuditService::log('workforce.skill_removed', 'user', (int) $userId, ['skill_id' => (int) $skillId]);
        flash('success', 'Employee skill removed.');
        redirect('/portal/workforce');
    }

    private function upsertMember(PDO $pdo, int $crewId, int $userId, bool $primary): void
    {
        $stmt = $pdo->prepare(
            "INSERT INTO crew_members(crew_id, user_id, is_primary, active, started_at, ended_at)
             VALUES (?, ?, ?, 1, CURDATE(), NULL)
             ON DUPLICATE KEY UPDATE is_primary = VALUES(is_primary), active = 1,
                                     started_at = COALESCE(started_at, CURDATE()), ended_at = NULL"
        );
        $stmt->execute([$crewId, $userId, $primary ? 1 : 0]);
    }

    private static function nullableInt(mixed $value): ?int
    {
        return $value === null || $value === '' ? null : (int) $value;
    }
}
