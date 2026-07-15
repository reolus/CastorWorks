<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\View;
use App\Services\AuditService;
use App\Services\LocationTrackingService;
use PDO;

final class MobileController
{
    public function dashboard(): void
    {
        Auth::requireRole('technician', 'crew_leader', 'owner');
        $pdo = Database::connection();
        $jobs = $this->assignedJobs($pdo, (int) Auth::id(), date('Y-m-d'));

        $openTime = $pdo->prepare(
            'SELECT t.*, j.job_number, v.unit_number
             FROM time_entries t
             LEFT JOIN jobs j ON j.id=t.job_id
             LEFT JOIN vehicles v ON v.id=t.vehicle_id
             WHERE t.user_id=? AND t.clock_out IS NULL
             ORDER BY t.clock_in DESC LIMIT 1'
        );
        $openTime->execute([Auth::id()]);

        View::render('portal/mobile/dashboard', [
            'title' => 'Field Dashboard',
            'jobs' => $jobs,
            'openTime' => $openTime->fetch() ?: null,
        ], 'mobile');
    }

    public function job(string $id): void
    {
        Auth::requireRole('technician', 'crew_leader', 'owner');
        $pdo = Database::connection();
        $job = $this->loadJob($pdo, (int) $id);
        $this->authorizeJob($pdo, $job);

        $checklist = $pdo->prepare('SELECT * FROM job_checklist_items WHERE job_id=? ORDER BY sort_order,id');
        $checklist->execute([(int) $id]);
        $photos = $pdo->prepare('SELECT * FROM job_photos WHERE job_id=? ORDER BY created_at DESC');
        $photos->execute([(int) $id]);
        $inspections = $pdo->prepare(
            'SELECT i.*,t.name template_name FROM inspections i
             JOIN inspection_templates t ON t.id=i.inspection_template_id
             WHERE i.job_id=? ORDER BY i.created_at DESC'
        );
        $inspections->execute([(int) $id]);
        $invoice = $pdo->prepare('SELECT * FROM invoices WHERE job_id=? ORDER BY id DESC LIMIT 1');
        $invoice->execute([(int) $id]);
        $crew = [];
        if (!empty($job['crew_id'])) {
            $crewStmt = $pdo->prepare(
                'SELECT u.id,u.name,u.mobile_phone,u.business_phone,cm.is_primary
                 FROM crew_members cm JOIN users u ON u.id=cm.user_id
                 WHERE cm.crew_id=? AND cm.active=1 ORDER BY cm.is_primary DESC,u.name'
            );
            $crewStmt->execute([(int) $job['crew_id']]);
            $crew = $crewStmt->fetchAll();
        }
        $openTime = $pdo->prepare('SELECT * FROM time_entries WHERE user_id=? AND clock_out IS NULL ORDER BY clock_in DESC LIMIT 1');
        $openTime->execute([Auth::id()]);

        View::render('portal/mobile/job', [
            'title' => $job['job_number'],
            'job' => $job,
            'checklist' => $checklist->fetchAll(),
            'photos' => $photos->fetchAll(),
            'inspections' => $inspections->fetchAll(),
            'invoice' => $invoice->fetch() ?: null,
            'crew' => $crew,
            'openTime' => $openTime->fetch() ?: null,
            'completion' => $this->completionState($pdo, (int) $id),
            'gpsPolicy' => (new LocationTrackingService($pdo))->policy(),
        ], 'mobile');
    }

    public function clock(string $id): void
    {
        Auth::requireRole('technician', 'crew_leader', 'owner');
        verify_csrf();
        $pdo = Database::connection();
        $job = $this->loadJob($pdo, (int) $id);
        $this->authorizeJob($pdo, $job);

        $open = $pdo->prepare('SELECT id FROM time_entries WHERE user_id=? AND clock_out IS NULL ORDER BY clock_in DESC LIMIT 1');
        $open->execute([Auth::id()]);
        if ($row = $open->fetch()) {
            $pdo->prepare('UPDATE time_entries SET clock_out=NOW() WHERE id=?')->execute([(int) $row['id']]);
            flash('success', 'Time entry stopped.');
        } else {
            $pdo->prepare(
                'INSERT INTO time_entries(user_id,job_id,vehicle_id,clock_in,entry_type,notes)
                 VALUES(?,?,?,NOW(),?,?)'
            )->execute([
                Auth::id(),
                (int) $id,
                $job['assigned_vehicle_id'] ?: null,
                $_POST['entry_type'] ?? 'job',
                trim((string) ($_POST['notes'] ?? '')),
            ]);
            flash('success', 'Time entry started.');
        }
        redirect('/portal/mobile/jobs/' . $id);
    }

    public function notes(string $id): void
    {
        Auth::requireRole('technician', 'crew_leader', 'owner');
        verify_csrf();
        $pdo = Database::connection();
        $job = $this->loadJob($pdo, (int) $id);
        $this->authorizeJob($pdo, $job);
        $note = trim((string) ($_POST['mobile_note'] ?? ''));
        if ($note !== '') {
            $prefix = '[' . date('Y-m-d H:i') . ' ' . (Auth::user()['name'] ?? 'Technician') . '] ';
            $pdo->prepare("UPDATE jobs SET notes=CONCAT_WS('\n',NULLIF(notes,''),?) WHERE id=?")
                ->execute([$prefix . $note, (int) $id]);
            AuditService::log('job.mobile_note', 'job', (int) $id);
            flash('success', 'Job note saved.');
        }
        redirect('/portal/mobile/jobs/' . $id);
    }

    public function complete(string $id): void
    {
        Auth::requireRole('technician', 'crew_leader', 'owner');
        verify_csrf();
        $pdo = Database::connection();
        $job = $this->loadJob($pdo, (int) $id);
        $this->authorizeJob($pdo, $job);
        $state = $this->completionState($pdo, (int) $id);

        if (!$state['ready'] && empty($_POST['override_requirements'])) {
            flash('danger', 'Completion requirements remain: ' . implode(', ', $state['missing']));
            redirect('/portal/mobile/jobs/' . $id);
        }

        $pdo->prepare(
            "UPDATE jobs SET status='completed',completed_at=NOW(),check_out_at=COALESCE(check_out_at,NOW()),
             mobile_completed_by=?,mobile_completion_summary=? WHERE id=?"
        )->execute([
            Auth::id(),
            trim((string) ($_POST['completion_summary'] ?? '')) ?: null,
            (int) $id,
        ]);
        $pdo->prepare('UPDATE time_entries SET clock_out=NOW() WHERE user_id=? AND job_id=? AND clock_out IS NULL')
            ->execute([Auth::id(), (int) $id]);
        AuditService::log('job.mobile_completed', 'job', (int) $id, ['override' => !$state['ready']]);
        flash('success', 'Job completed.');
        redirect('/portal/mobile');
    }


    public function location(): void
    {
        Auth::requireRole('technician', 'crew_leader', 'owner');
        $token = (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        if (!hash_equals((string)($_SESSION['_csrf'] ?? ''), $token)) {
            http_response_code(419); header('Content-Type: application/json');
            echo json_encode(['ok' => false, 'error' => 'Session expired.']); return;
        }
        header('Content-Type: application/json');
        $payload = json_decode((string) file_get_contents('php://input'), true);
        if (!is_array($payload)) {
            http_response_code(422);
            echo json_encode(['ok' => false, 'error' => 'Invalid location payload.']);
            return;
        }
        try {
            $result = (new LocationTrackingService())->record((int) Auth::id(), $payload);
            echo json_encode(['ok' => true] + $result);
        } catch (\Throwable $e) {
            http_response_code(422);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
    }

    public function subscribe(): void
    {
        Auth::requireLogin();
        verify_csrf();
        $payload = json_decode((string) file_get_contents('php://input'), true);
        if (!is_array($payload) || empty($payload['endpoint'])) {
            http_response_code(422);
            echo json_encode(['ok' => false]);
            return;
        }
        Database::connection()->prepare(
            'INSERT INTO mobile_push_subscriptions(user_id,endpoint,p256dh,auth_token,user_agent,last_seen_at)
             VALUES(?,?,?,?,?,NOW())
             ON DUPLICATE KEY UPDATE p256dh=VALUES(p256dh),auth_token=VALUES(auth_token),
             user_agent=VALUES(user_agent),last_seen_at=NOW(),active=1'
        )->execute([
            Auth::id(),
            $payload['endpoint'],
            $payload['keys']['p256dh'] ?? null,
            $payload['keys']['auth'] ?? null,
            substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500),
        ]);
        header('Content-Type: application/json');
        echo json_encode(['ok' => true]);
    }

    private function assignedJobs(PDO $pdo, int $userId, string $date): array
    {
        $sql = "SELECT DISTINCT j.*,c.display_name,c.phone,p.address1,p.city,p.state,p.postal_code,
                       cr.name crew_name,v.unit_number
                FROM jobs j
                JOIN customers c ON c.id=j.customer_id
                LEFT JOIN properties p ON p.id=j.property_id
                LEFT JOIN crews cr ON cr.id=j.crew_id
                LEFT JOIN crew_members cm ON cm.crew_id=j.crew_id AND cm.active=1
                LEFT JOIN vehicles v ON v.id=j.assigned_vehicle_id
                WHERE DATE(j.scheduled_start)=? AND (j.assigned_user_id=? OR cm.user_id=?)
                ORDER BY COALESCE(j.route_order,9999),j.scheduled_start";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$date, $userId, $userId]);
        return $stmt->fetchAll();
    }

    private function loadJob(PDO $pdo, int $id): array
    {
        $stmt = $pdo->prepare(
            "SELECT j.*,c.display_name,c.phone,c.email,p.address1,p.city,p.state,p.postal_code,
                    CONCAT_WS(', ',p.address1,p.city,p.state,p.postal_code) full_address,
                    cr.name crew_name,v.unit_number
             FROM jobs j JOIN customers c ON c.id=j.customer_id
             LEFT JOIN properties p ON p.id=j.property_id
             LEFT JOIN crews cr ON cr.id=j.crew_id
             LEFT JOIN vehicles v ON v.id=j.assigned_vehicle_id
             WHERE j.id=?"
        );
        $stmt->execute([$id]);
        $job = $stmt->fetch();
        if (!$job) {
            http_response_code(404);
            exit('Job not found.');
        }
        return $job;
    }

    private function authorizeJob(PDO $pdo, array $job): void
    {
        if (Auth::can('owner', 'crew_leader')) {
            return;
        }
        if ((int) ($job['assigned_user_id'] ?? 0) === (int) Auth::id()) {
            return;
        }
        if (!empty($job['crew_id'])) {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM crew_members WHERE crew_id=? AND user_id=? AND active=1');
            $stmt->execute([(int) $job['crew_id'], Auth::id()]);
            if ((int) $stmt->fetchColumn() > 0) {
                return;
            }
        }
        http_response_code(403);
        exit('Access denied.');
    }

    private function completionState(PDO $pdo, int $jobId): array
    {
        $missing = [];
        $stmt = $pdo->prepare('SELECT COUNT(*) total,SUM(completed=1) done FROM job_checklist_items WHERE job_id=?');
        $stmt->execute([$jobId]);
        $check = $stmt->fetch();
        if ((int) ($check['total'] ?? 0) > 0 && (int) ($check['done'] ?? 0) < (int) $check['total']) {
            $missing[] = 'checklist';
        }
        $stmt = $pdo->prepare("SELECT SUM(photo_type='before') before_count,SUM(photo_type='after') after_count FROM job_photos WHERE job_id=?");
        $stmt->execute([$jobId]);
        $photos = $stmt->fetch();
        if ((int) ($photos['before_count'] ?? 0) < 1) {
            $missing[] = 'before photo';
        }
        if ((int) ($photos['after_count'] ?? 0) < 1) {
            $missing[] = 'after photo';
        }
        $stmt = $pdo->prepare('SELECT customer_signed_at FROM jobs WHERE id=?');
        $stmt->execute([$jobId]);
        if (!$stmt->fetchColumn()) {
            $missing[] = 'customer signature';
        }
        return ['ready' => $missing === [], 'missing' => $missing];
    }
}
