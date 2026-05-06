<?php
/**
 * FONCTIONS POUR LE MODULE RH
 * Gestion des présences, congés, plannings, affectations et documents
 */

// ============================================================================
// 1. GESTION DES PRÉSENCES/ABSENCES
// ============================================================================

/**
 * Enregistrer une présence/absence
 * @param mysqli $db
 * @param int $user_id
 * @param string $attendance_date (Y-m-d)
 * @param string $status (present|absent|late|excused|holiday)
 * @param string|null $check_in (HH:mm:ss)
 * @param string|null $check_out (HH:mm:ss)
 * @param string $notes
 * @return array [success => bool, message => string]
 */
function recordAttendance($db, $user_id, $attendance_date, $status, $check_in = null, $check_out = null, $notes = '') {
    try {
        // Vérifier si une entrée existe déjà pour ce jour
        $stmt = $db->prepare("SELECT id FROM attendances WHERE user_id = ? AND attendance_date = ?");
        $stmt->bind_param('is', $user_id, $attendance_date);
        $stmt->execute();
        $existing = $stmt->get_result()->fetch_assoc();

        // Calculer heures travaillées
        $hours_worked = 0;
        $overtime_hours = 0;
        if ($check_in && $check_out && $status == 'present') {
            $check_in_time = strtotime($check_in);
            $check_out_time = strtotime($check_out);
            $hours_worked = round(($check_out_time - $check_in_time) / 3600, 2);
            $overtime_hours = $hours_worked > 8 ? $hours_worked - 8 : 0;
        }

        if ($existing) {
            // Mettre à jour
            $stmt = $db->prepare("UPDATE attendances SET status = ?, check_in = ?, check_out = ?, hours_worked = ?, overtime_hours = ?, notes = ?, updated_at = NOW() WHERE id = ?");
            $stmt->bind_param('ssdddsi', $status, $check_in, $check_out, $hours_worked, $overtime_hours, $notes, $existing['id']);
        } else {
            // Insérer
            $stmt = $db->prepare("INSERT INTO attendances (user_id, attendance_date, status, check_in, check_out, hours_worked, overtime_hours, notes, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
            $stmt->bind_param('isssddds', $user_id, $attendance_date, $status, $check_in, $check_out, $hours_worked, $overtime_hours, $notes);
        }

        if ($stmt->execute()) {
            return ['success' => true, 'message' => 'Présence enregistrée avec succès'];
        }
        return ['success' => false, 'message' => $stmt->error];
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

/**
 * Récupérer les présences sur une période
 * @param mysqli $db
 * @param string $start_date (Y-m-d)
 * @param string $end_date (Y-m-d)
 * @param int|null $user_id
 * @return array
 */
function getAttendancesByPeriod($db, $start_date, $end_date, $user_id = null) {
    $sql = "SELECT a.*, u.full_name, u.email 
            FROM attendances a
            INNER JOIN users u ON u.id = a.user_id
            WHERE a.attendance_date BETWEEN ? AND ?";

    if ($user_id) {
        $sql .= " AND a.user_id = ?";
        $stmt = $db->prepare($sql);
        $stmt->bind_param('ssi', $start_date, $end_date, $user_id);
    } else {
        $stmt = $db->prepare($sql);
        $stmt->bind_param('ss', $start_date, $end_date);
    }

    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

/**
 * Récupérer les présences d'un employé sur un mois
 * @param mysqli $db
 * @param int $user_id
 * @param int $year
 * @param int $month
 * @return array
 */
function getMonthlyAttendance($db, $user_id, $year, $month) {
    $start_date = "$year-$month-01";
    $end_date = date('Y-m-t', strtotime($start_date));

    $stmt = $db->prepare("SELECT * FROM attendances WHERE user_id = ? AND attendance_date BETWEEN ? AND ? ORDER BY attendance_date");
    $stmt->bind_param('iss', $user_id, $start_date, $end_date);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

/**
 * Statistiques de présence pour un mois
 * @param mysqli $db
 * @param int $year
 * @param int $month
 * @param int|null $department_id
 * @return array
 */
function getAttendanceStats($db, $year, $month, $department_id = null) {
    $start_date = "$year-$month-01";
    $end_date = date('Y-m-t', strtotime($start_date));

    $sql = "SELECT 
                COUNT(DISTINCT a.user_id) as total_employees,
                SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) as present_days,
                SUM(CASE WHEN a.status = 'absent' THEN 1 ELSE 0 END) as absent_days,
                SUM(CASE WHEN a.status = 'late' THEN 1 ELSE 0 END) as late_days,
                SUM(CASE WHEN a.status = 'excused' THEN 1 ELSE 0 END) as excused_days,
                SUM(CASE WHEN a.status = 'holiday' THEN 1 ELSE 0 END) as holiday_days,
                COALESCE(SUM(a.hours_worked), 0) as total_hours,
                COALESCE(SUM(a.overtime_hours), 0) as total_overtime
            FROM attendances a
            WHERE a.attendance_date BETWEEN ? AND ?";

    $stmt = $db->prepare($sql);
    $stmt->bind_param('ss', $start_date, $end_date);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

/**
 * Récupérer les statistiques de présence pour un employé sur un mois
 * @param mysqli $db
 * @param int $user_id
 * @param int $year
 * @param int $month
 * @return array
 */
function getAttendanceStatsForEmployee($db, $user_id, $year, $month) {
    $start_date = "$year-$month-01";
    $end_date = date('Y-m-t', strtotime($start_date));

    $stmt = $db->prepare("
        SELECT 
            SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present_days,
            SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent_days,
            SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late_days,
            SUM(CASE WHEN status = 'excused' THEN 1 ELSE 0 END) as excused_days,
            COALESCE(SUM(hours_worked), 0) as total_hours,
            COALESCE(SUM(overtime_hours), 0) as total_overtime
        FROM attendances
        WHERE user_id = ? AND attendance_date BETWEEN ? AND ?
    ");
    $stmt->bind_param('iss', $user_id, $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();

    if (!$result) {
        return [
            'present_days' => 0,
            'absent_days' => 0,
            'late_days' => 0,
            'excused_days' => 0,
            'total_hours' => 0,
            'total_overtime' => 0
        ];
    }
    return $result;
}

// ============================================================================
// 2. GESTION DES CONGÉS
// ============================================================================

/**
 * Approuver ou rejeter une demande de congé
 * @param mysqli $db
 * @param int $leave_id
 * @param int $approved_by
 * @param string $status (approved|rejected)
 * @return array [success => bool, message => string]
 */
function approveLeaveRequest($db, $leave_id, $approved_by, $status) {
    try {
        if (!in_array($status, ['approved', 'rejected'])) {
            return ['success' => false, 'message' => 'Statut invalide'];
        }

        $stmt = $db->prepare("UPDATE leave_requests SET status = ?, approved_by = ? WHERE id = ?");
        $stmt->bind_param('sii', $status, $approved_by, $leave_id);

        if ($stmt->execute()) {
            return ['success' => true, 'message' => 'Demande ' . ($status == 'approved' ? 'approuvée' : 'rejetée')];
        }
        return ['success' => false, 'message' => $stmt->error];
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

/**
 * Récupérer les demandes de congé
 * @param mysqli $db
 * @param string|null $status
 * @return array
 */
function getLeaveRequests($db, $status = null) {
    $sql = "SELECT lr.*, u.full_name, u.email, u.phone 
            FROM leave_requests lr
            INNER JOIN users u ON u.id = lr.user_id";

    if ($status && $status !== 'all') {
        $sql .= " WHERE lr.status = ?";
        $stmt = $db->prepare($sql);
        $stmt->bind_param('s', $status);
    } else {
        $stmt = $db->prepare($sql);
    }

    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

/**
 * Obtenir les demandes de congé en attente
 * @param mysqli $db
 * @return array
 */
function getPendingLeaveRequests($db) {
    return getLeaveRequests($db, 'pending');
}

/**
 * Récupérer les demandes de congé d'un employé
 * @param mysqli $db
 * @param int $user_id
 * @return array
 */
function getEmployeeLeaveRequests($db, $user_id) {
    $stmt = $db->prepare("
        SELECT * FROM leave_requests 
        WHERE user_id = ? 
        ORDER BY created_at DESC
    ");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

/**
 * Calculer les jours de congé restants pour un employé (25 jours par an)
 * @param mysqli $db
 * @param int $user_id
 * @param int $year
 * @return int
 */
function getRemainingLeaveDays($db, $user_id, $year) {
    $stmt = $db->prepare("
        SELECT SUM(DATEDIFF(end_date, start_date) + 1) as used_days 
        FROM leave_requests 
        WHERE user_id = ? AND YEAR(start_date) = ? AND status = 'approved'
    ");
    $stmt->bind_param('ii', $user_id, $year);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $used_days = (int)($result['used_days'] ?? 0);
    $total_allowed = 25;
    return max(0, $total_allowed - $used_days);
}

// ============================================================================
// 3. GESTION DU PLANNING
// ============================================================================

/**
 * Créer un planning
 * @param mysqli $db
 * @param int $user_id
 * @param string $planning_date
 * @param string $shift_start
 * @param string $shift_end
 * @param string $shift_type
 * @param string $notes
 * @param int $created_by
 * @return array
 */
function createPlanning($db, $user_id, $planning_date, $shift_start, $shift_end, $shift_type, $notes, $created_by) {
    try {
        $stmt = $db->prepare("INSERT INTO staff_planning (user_id, planning_date, shift_start, shift_end, shift_type, notes, created_by, created_at, status) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), 'scheduled')");
        $stmt->bind_param('isssssi', $user_id, $planning_date, $shift_start, $shift_end, $shift_type, $notes, $created_by);

        if ($stmt->execute()) {
            return ['success' => true, 'message' => 'Planning ajouté avec succès'];
        }
        return ['success' => false, 'message' => $stmt->error];
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

/**
 * Vérifier les conflits d'horaires
 * @param mysqli $db
 * @param int $user_id
 * @param string $date
 * @param string $new_start
 * @param string $new_end
 * @return array
 */
function checkScheduleConflict($db, $user_id, $date, $new_start, $new_end) {
    $stmt = $db->prepare("SELECT id FROM staff_planning WHERE user_id = ? AND planning_date = ?");
    $stmt->bind_param('is', $user_id, $date);
    $stmt->execute();
    $existing = $stmt->get_result()->fetch_assoc();

    if ($existing) {
        return ['has_conflict' => true, 'message' => 'Un planning existe déjà pour cette date'];
    }
    return ['has_conflict' => false, 'message' => ''];
}

/**
 * Obtenir les plannings d'une période
 * @param mysqli $db
 * @param string $start_date
 * @param string $end_date
 * @param int|null $user_id
 * @return array
 */
function getPlanningByPeriod($db, $start_date, $end_date, $user_id = null) {
    $sql = "SELECT sp.*, u.full_name FROM staff_planning sp INNER JOIN users u ON u.id = sp.user_id WHERE sp.planning_date BETWEEN ? AND ?";

    if ($user_id) {
        $sql .= " AND sp.user_id = ?";
        $stmt = $db->prepare($sql);
        $stmt->bind_param('ssi', $start_date, $end_date, $user_id);
    } else {
        $stmt = $db->prepare($sql);
        $stmt->bind_param('ss', $start_date, $end_date);
    }

    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

/**
 * Récupérer le planning d'un employé sur une période
 * @param mysqli $db
 * @param int $user_id
 * @param string $start_date
 * @param string $end_date
 * @return array
 */
function getEmployeeSchedule($db, $user_id, $start_date, $end_date) {
    $stmt = $db->prepare("
        SELECT * FROM staff_planning 
        WHERE user_id = ? AND planning_date BETWEEN ? AND ? 
        ORDER BY planning_date ASC
    ");
    $stmt->bind_param('iss', $user_id, $start_date, $end_date);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// ============================================================================
// 4. GESTION DES AFFECTATIONS
// ============================================================================

/**
 * Affecter un employé à un département
 * @param mysqli $db
 * @param int $user_id
 * @param int $department_id
 * @param string $position
 * @param string $start_date
 * @param string|null $end_date
 * @return array
 */
function assignEmployee($db, $user_id, $department_id, $position, $start_date, $end_date = null) {
    try {
        // Vérifier que le département existe
        $stmt = $db->prepare("SELECT id FROM departments WHERE id = ? AND is_active = 1");
        $stmt->bind_param('i', $department_id);
        $stmt->execute();
        if ($stmt->get_result()->num_rows === 0) {
            return ['success' => false, 'message' => 'Département non trouvé ou inactif'];
        }

        // Vérifier que la date de fin est valide
        if ($end_date && $end_date < $start_date) {
            return ['success' => false, 'message' => 'La date de fin doit être postérieure à la date de début'];
        }

        // Désactiver l'affectation principale actuelle
        $stmt = $db->prepare("UPDATE employee_assignments SET is_primary = 0 WHERE user_id = ? AND is_primary = 1");
        $stmt->bind_param('i', $user_id);
        $stmt->execute();

        // Créer la nouvelle affectation
        $stmt = $db->prepare("INSERT INTO employee_assignments (user_id, department_id, position, start_date, end_date, is_primary, created_at) VALUES (?, ?, ?, ?, ?, 1, NOW())");
        $stmt->bind_param('iisss', $user_id, $department_id, $position, $start_date, $end_date);

        if ($stmt->execute()) {
            return ['success' => true, 'message' => 'Employé affecté avec succès'];
        }
        return ['success' => false, 'message' => $stmt->error];
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

/**
 * Obtenir l'affectation actuelle d'un employé
 * @param mysqli $db
 * @param int $user_id
 * @return array|null
 */
function getCurrentAssignment($db, $user_id) {
    $stmt = $db->prepare("
        SELECT ea.*, d.name as department_name 
        FROM employee_assignments ea 
        INNER JOIN departments d ON d.id = ea.department_id 
        WHERE ea.user_id = ? AND ea.is_primary = 1
        AND (ea.end_date IS NULL OR ea.end_date >= CURDATE())
    ");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

/**
 * Récupérer l'historique des affectations d'un employé
 * @param mysqli $db
 * @param int $user_id
 * @return array
 */
function getEmployeeAssignments($db, $user_id) {
    $stmt = $db->prepare("
        SELECT ea.*, d.name as department_name 
        FROM employee_assignments ea 
        INNER JOIN departments d ON d.id = ea.department_id 
        WHERE ea.user_id = ? 
        ORDER BY ea.start_date DESC
    ");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

/**
 * Récupérer tous les départements
 * @param mysqli $db
 * @param bool $active_only
 * @return array
 */
function getAllDepartments($db, $active_only = true) {
    $sql = "SELECT id, name, description FROM departments";
    if ($active_only) {
        $sql .= " WHERE is_active = 1";
    }
    $sql .= " ORDER BY name";

    $result = $db->query($sql);
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

/**
 * Statistiques par département
 * @param mysqli $db
 * @return array
 */
function getDepartmentStats($db) {
    $sql = "SELECT d.id, d.name, COUNT(ea.user_id) as employee_count
            FROM departments d
            LEFT JOIN employee_assignments ea ON ea.department_id = d.id AND ea.is_primary = 1
            WHERE d.is_active = 1
            GROUP BY d.id, d.name
            ORDER BY employee_count DESC";
    $result = $db->query($sql);
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

// ============================================================================
// 5. GESTION DES DOCUMENTS
// ============================================================================

/**
 * Uploader un document employé
 * @param mysqli $db
 * @param int $user_id
 * @param string $document_type
 * @param array $file
 * @param int $uploaded_by
 * @return array
 */
function uploadEmployeeDocument($db, $user_id, $document_type, $file, $uploaded_by) {
    try {
        $upload_dir = '../uploads/rh_documents/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'doc_' . $user_id . '_' . time() . '_' . rand(1000, 9999) . '.' . $extension;
        $filepath = $upload_dir . $filename;

        if (move_uploaded_file($file['tmp_name'], $filepath)) {
            $stmt = $db->prepare("INSERT INTO employee_documents (user_id, document_type, document_name, file_path, file_size, mime_type, uploaded_by, uploaded_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
            $stmt->bind_param('issssis', $user_id, $document_type, $file['name'], $filepath, $file['size'], $file['type'], $uploaded_by);
            $stmt->execute();

            return ['success' => true, 'message' => 'Document uploadé avec succès'];
        }
        return ['success' => false, 'message' => 'Erreur lors de l\'upload'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

/**
 * Récupérer les documents d'un employé
 * @param mysqli $db
 * @param int $user_id
 * @return array
 */
function getEmployeeDocuments($db, $user_id) {
    $stmt = $db->prepare("
        SELECT * FROM employee_documents 
        WHERE user_id = ? 
        ORDER BY uploaded_at DESC
    ");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

/**
 * Supprimer un document
 * @param mysqli $db
 * @param int $document_id
 * @return bool
 */
function deleteEmployeeDocument($db, $document_id) {
    $stmt = $db->prepare("SELECT file_path FROM employee_documents WHERE id = ?");
    $stmt->bind_param('i', $document_id);
    $stmt->execute();
    $doc = $stmt->get_result()->fetch_assoc();

    if ($doc && file_exists($doc['file_path'])) {
        unlink($doc['file_path']);
    }

    $stmt = $db->prepare("DELETE FROM employee_documents WHERE id = ?");
    $stmt->bind_param('i', $document_id);
    return $stmt->execute();
}

// ============================================================================
// 6. GESTION DES CONTRATS
// ============================================================================

/**
 * Sauvegarder un contrat (création ou modification)
 * @param mysqli $db
 * @param int $user_id
 * @param string $contract_type
 * @param string $start_date
 * @param float $salary
 * @param string $position
 * @param string $department
 * @param string|null $end_date
 * @param int|null $contract_id
 * @return array
 */
function saveContract($db, $user_id, $contract_type, $start_date, $salary, $position, $department, $end_date = null, $contract_id = null) {
    try {
        if ($contract_id) {
            // Mise à jour
            $stmt = $db->prepare("
                UPDATE employee_contracts 
                SET contract_type = ?, start_date = ?, end_date = ?, salary = ?, position = ?, department = ? 
                WHERE id = ?
            ");
            $stmt->bind_param('sssdssi', $contract_type, $start_date, $end_date, $salary, $position, $department, $contract_id);
        } else {
            // Nouveau contrat - désactiver les anciens contrats actifs
            $stmt = $db->prepare("UPDATE employee_contracts SET end_date = CURDATE() WHERE user_id = ? AND (end_date IS NULL OR end_date >= CURDATE())");
            $stmt->bind_param('i', $user_id);
            $stmt->execute();

            $stmt = $db->prepare("
                INSERT INTO employee_contracts (user_id, contract_type, start_date, end_date, salary, position, department, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->bind_param('isssdss', $user_id, $contract_type, $start_date, $end_date, $salary, $position, $department);
        }

        if ($stmt->execute()) {
            return ['success' => true, 'message' => $contract_id ? 'Contrat mis à jour avec succès' : 'Contrat créé avec succès', 'contract_id' => $contract_id ?? $db->insert_id];
        }
        return ['success' => false, 'message' => $stmt->error];
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

/**
 * Récupérer les contrats d'un employé
 * @param mysqli $db
 * @param int $user_id
 * @return array
 */
function getEmployeeContracts($db, $user_id) {
    $stmt = $db->prepare("SELECT * FROM employee_contracts WHERE user_id = ? ORDER BY start_date DESC");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

/**
 * Récupérer le contrat actif d'un employé
 * @param mysqli $db
 * @param int $user_id
 * @return array|null
 */
function getActiveContract($db, $user_id) {
    $stmt = $db->prepare("
        SELECT * FROM employee_contracts 
        WHERE user_id = ? AND (end_date IS NULL OR end_date >= CURDATE()) 
        ORDER BY start_date DESC LIMIT 1
    ");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

// ============================================================================
// 7. RAPPORTS RH
// ============================================================================

/**
 * Générer rapport de présence
 * @param mysqli $db
 * @param string $start_date
 * @param string $end_date
 * @return array
 */
function generateAttendanceReport($db, $start_date, $end_date) {
    $stmt = $db->prepare("
        SELECT u.id, u.full_name, u.email,
            COUNT(DISTINCT a.attendance_date) as days_recorded,
            SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) as present_days,
            SUM(CASE WHEN a.status = 'absent' THEN 1 ELSE 0 END) as absent_days,
            SUM(CASE WHEN a.status = 'late' THEN 1 ELSE 0 END) as late_days,
            SUM(CASE WHEN a.status = 'excused' THEN 1 ELSE 0 END) as excused_days,
            COALESCE(SUM(a.hours_worked), 0) as total_hours,
            COALESCE(SUM(a.overtime_hours), 0) as total_overtime
        FROM users u
        LEFT JOIN attendances a ON a.user_id = u.id AND a.attendance_date BETWEEN ? AND ?
        WHERE u.is_active = 1 AND u.role_id NOT IN (1, 3)
        GROUP BY u.id, u.full_name, u.email
        ORDER BY u.full_name
    ");
    $stmt->bind_param('ss', $start_date, $end_date);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

/**
 * Statistiques RH
 * @param mysqli $db
 * @param string $start_date
 * @param string $end_date
 * @return array
 */
function getHRStatistics($db, $start_date, $end_date) {
    $stats = [];

    $stmt = $db->prepare("SELECT COUNT(*) as count FROM users WHERE role_id NOT IN (1, 3) AND is_active = 1");
    $stmt->execute();
    $stats['total_employees'] = (int)$stmt->get_result()->fetch_assoc()['count'];

    $stmt = $db->prepare("SELECT COUNT(*) as count FROM attendances WHERE attendance_date BETWEEN ? AND ? AND status = 'present'");
    $stmt->bind_param('ss', $start_date, $end_date);
    $stmt->execute();
    $stats['total_present'] = (int)$stmt->get_result()->fetch_assoc()['count'];

    $stmt = $db->prepare("SELECT COUNT(*) as count FROM attendances WHERE attendance_date BETWEEN ? AND ? AND status IN ('absent', 'late')");
    $stmt->bind_param('ss', $start_date, $end_date);
    $stmt->execute();
    $stats['total_absent'] = (int)$stmt->get_result()->fetch_assoc()['count'];

    $stmt = $db->prepare("SELECT COUNT(*) as count FROM leave_requests WHERE start_date BETWEEN ? AND ? AND status = 'approved'");
    $stmt->bind_param('ss', $start_date, $end_date);
    $stmt->execute();
    $stats['total_approved_leaves'] = (int)$stmt->get_result()->fetch_assoc()['count'];

    $stmt = $db->prepare("SELECT COALESCE(SUM(overtime_hours), 0) as total FROM attendances WHERE attendance_date BETWEEN ? AND ?");
    $stmt->bind_param('ss', $start_date, $end_date);
    $stmt->execute();
    $stats['total_overtime'] = (float)$stmt->get_result()->fetch_assoc()['total'];

    return $stats;
}

/**
 * Contrats expirant bientôt
 * @param mysqli $db
 * @param int $days
 * @return array
 */
function getExpiringContracts($db, $days = 60) {
    $today = date('Y-m-d');
    $future = date('Y-m-d', strtotime("+$days days"));

    $stmt = $db->prepare("
        SELECT ec.*, u.full_name, u.email
        FROM employee_contracts ec
        INNER JOIN users u ON u.id = ec.user_id
        WHERE ec.end_date BETWEEN ? AND ?
        ORDER BY ec.end_date ASC
    ");
    $stmt->bind_param('ss', $today, $future);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// ============================================================================
// 8. FONCTIONS UTILITAIRES
// ============================================================================

/**
 * Comptage sécurisé
 * @param mysqli $db
 * @param string $sql
 * @param string $types
 * @param array $params
 * @return int
 */
function safeCount($db, $sql, $types = '', $params = []) {
    $stmt = $db->prepare($sql);
    if (!$stmt) return 0;
    if ($types && $params) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return (int)($row['c'] ?? 0);
}

/**
 * Récupérer tous les employés (non patients)
 * @param mysqli $db
 * @param bool $active_only
 * @return array
 */
function getAllEmployees($db, $active_only = true) {
    $sql = "SELECT u.id, u.full_name, u.email, u.phone, u.is_active, r.role_name
            FROM users u
            INNER JOIN roles r ON r.id = u.role_id
            WHERE u.role_id NOT IN (1, 3)";

    if ($active_only) {
        $sql .= " AND u.is_active = 1";
    }
    $sql .= " ORDER BY u.full_name";

    $result = $db->query($sql);
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}
?>