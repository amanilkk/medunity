-- Backup created on 2026-04-21 22:14:04
-- Backup type: auto_full
-- Note: Sauvegarde automatique quotidienne

DROP TABLE IF EXISTS `alerts`;
CREATE TABLE `alerts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `type` enum('stock','critical_result','urgent_patient','maintenance','expiry','bed_unavailable') NOT NULL,
  `severity` enum('info','warning','critical') DEFAULT 'info',
  `message` text NOT NULL,
  `reference_id` int(11) DEFAULT NULL COMMENT 'ID lié (medicine_id, lab_test_id, etc.)',
  `target_role_id` int(11) DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `read_by` int(11) DEFAULT NULL,
  `read_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `target_role_id` (`target_role_id`),
  KEY `is_read` (`is_read`),
  KEY `fk_alert_read_by` (`read_by`),
  CONSTRAINT `fk_alert_read_by` FOREIGN KEY (`read_by`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_alert_target_role` FOREIGN KEY (`target_role_id`) REFERENCES `roles` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `ambulance_missions`;
CREATE TABLE `ambulance_missions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `patient_id` int(11) DEFAULT NULL,
  `ambulancier_id` int(11) NOT NULL COMMENT 'user_id de l''ambulancier',
  `origin_address` text DEFAULT NULL,
  `destination_address` text DEFAULT NULL,
  `mission_type` enum('urgence','transfert','retour_domicile','autre') DEFAULT 'urgence',
  `status` enum('assigned','in_progress','completed','cancelled') DEFAULT 'assigned',
  `departure_time` datetime DEFAULT NULL,
  `arrival_time` datetime DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `patient_id` (`patient_id`),
  KEY `ambulancier_id` (`ambulancier_id`),
  CONSTRAINT `fk_ambul_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_ambul_user` FOREIGN KEY (`ambulancier_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `appointments`;
CREATE TABLE `appointments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `patient_id` int(11) NOT NULL,
  `doctor_id` int(11) NOT NULL,
  `appointment_date` date NOT NULL,
  `appointment_time` time NOT NULL,
  `status` enum('pending','confirmed','completed','cancelled','urgent','no_show') DEFAULT 'pending',
  `type` enum('OPD','IPD','urgence','chirurgie','consultation') DEFAULT 'consultation',
  `priority` tinyint(1) DEFAULT 0 COMMENT '0=normal, 1=urgent',
  `reason` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `duration_minutes` int(11) DEFAULT 30 COMMENT 'Durée estimée en minutes',
  `room_id` int(11) DEFAULT NULL COMMENT 'Salle d''examen assignée',
  `created_by` int(11) DEFAULT NULL COMMENT 'Créé par réceptionniste ou patient (user_id)',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `patient_id` (`patient_id`),
  KEY `doctor_id` (`doctor_id`),
  KEY `appointment_date` (`appointment_date`),
  KEY `status` (`status`),
  KEY `fk_appointment_created_by` (`created_by`),
  CONSTRAINT `fk_appointment_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_appointment_doctor` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`id`),
  CONSTRAINT `fk_appointment_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `appointments` VALUES('2','1','1','2026-04-12','20:45:57','pending','consultation','1','urgence',NULL,'30',NULL,NULL,'2026-04-12 20:45:57','2026-04-12 20:45:57');
INSERT INTO `appointments` VALUES('3','2','1','2026-04-12','20:49:55','pending','consultation','0','suivi',NULL,'30',NULL,NULL,'2026-04-12 20:49:55','2026-04-12 20:49:55');

DROP TABLE IF EXISTS `bed_management`;
CREATE TABLE `bed_management` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `room_number` varchar(10) NOT NULL,
  `bed_number` varchar(10) NOT NULL,
  `bed_type` enum('standard','icu','pediatric','isolation') DEFAULT 'standard',
  `status` enum('available','occupied','cleaning','maintenance','reserved') DEFAULT 'available',
  `patient_id` int(11) DEFAULT NULL,
  `admission_date` datetime DEFAULT NULL,
  `expected_discharge_date` date DEFAULT NULL,
  `cleaning_task_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `room_bed` (`room_number`,`bed_number`),
  KEY `patient_id` (`patient_id`),
  KEY `status` (`status`),
  CONSTRAINT `fk_bed_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `bed_management` VALUES('1','1','1','icu','cleaning',NULL,NULL,NULL,NULL);

DROP TABLE IF EXISTS `chat_messages`;
CREATE TABLE `chat_messages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sender_id` int(11) NOT NULL,
  `receiver_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `attachment_url` varchar(500) DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `read_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `sender_id` (`sender_id`),
  KEY `receiver_id` (`receiver_id`),
  KEY `is_read` (`is_read`),
  CONSTRAINT `fk_chat_receiver` FOREIGN KEY (`receiver_id`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_chat_sender` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `cleaning_tasks`;
CREATE TABLE `cleaning_tasks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `bed_id` int(11) DEFAULT NULL,
  `room_number` varchar(10) DEFAULT NULL,
  `area_type` enum('chambre','salle_medicale','couloir','salle_attente','bloc','pharmacie','labo','autre') DEFAULT 'chambre',
  `task_type` enum('daily','discharge','deep_cleaning') DEFAULT 'daily',
  `priority` enum('low','normal','high','urgent') DEFAULT 'normal',
  `status` enum('pending','in_progress','completed','cancelled') DEFAULT 'pending',
  `assigned_to` int(11) DEFAULT NULL COMMENT 'agent_nettoyage user_id',
  `completed_at` datetime DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `anomaly_report` text DEFAULT NULL COMMENT 'Anomalie signalée (chambre endommagée, matériel manquant)',
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `assigned_to` (`assigned_to`),
  KEY `bed_id` (`bed_id`),
  CONSTRAINT `fk_cleaning_assigned` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_cleaning_bed` FOREIGN KEY (`bed_id`) REFERENCES `bed_management` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `doctors`;
CREATE TABLE `doctors` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `specialty_id` int(11) DEFAULT NULL,
  `consultation_fee` decimal(10,2) DEFAULT NULL COMMENT 'Tarif consultation',
  `room_number` varchar(10) DEFAULT NULL,
  `department` varchar(100) DEFAULT NULL COMMENT 'Service/département du médecin',
  `availability_status` enum('available','busy','off','on_leave') DEFAULT 'available' COMMENT 'Disponibilité temps réel',
  `languages` varchar(255) DEFAULT NULL COMMENT 'Langues parlées par le médecin',
  `experience_years` int(11) DEFAULT NULL COMMENT 'Années d''expérience',
  `biography` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `specialty_id` (`specialty_id`),
  CONSTRAINT `fk_doctor_specialty` FOREIGN KEY (`specialty_id`) REFERENCES `specialties` (`id`),
  CONSTRAINT `fk_doctor_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `doctors` VALUES('1','2','18','50.00','B12','Médecine Générale','available',NULL,'10','Médecin généraliste expérimenté','2026-04-05 19:59:38','2026-04-05 19:59:38');
INSERT INTO `doctors` VALUES('2','10','5',NULL,NULL,NULL,'available',NULL,NULL,NULL,'2026-04-05 21:27:21','2026-04-05 21:33:18');

DROP TABLE IF EXISTS `employee_contracts`;
CREATE TABLE `employee_contracts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `contract_type` enum('CDI','CDD','stage','freelance') DEFAULT 'CDI',
  `start_date` date NOT NULL,
  `end_date` date DEFAULT NULL,
  `salary` decimal(10,2) DEFAULT NULL,
  `position` varchar(255) DEFAULT NULL,
  `department` varchar(255) DEFAULT NULL,
  `documents_url` varchar(500) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `fk_contract_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `external_labs`;
CREATE TABLE `external_labs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `contact_person` varchar(255) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `api_endpoint` varchar(500) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `financial_reports`;
CREATE TABLE `financial_reports` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `report_type` enum('daily','weekly','monthly','yearly') NOT NULL,
  `report_date` date NOT NULL,
  `total_revenue` decimal(12,2) DEFAULT NULL,
  `total_expenses` decimal(12,2) DEFAULT NULL,
  `net_profit` decimal(12,2) DEFAULT NULL,
  `details` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`details`)),
  `generated_by` int(11) DEFAULT NULL,
  `generated_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `report_date` (`report_date`),
  KEY `fk_report_generated_by` (`generated_by`),
  CONSTRAINT `fk_report_generated_by` FOREIGN KEY (`generated_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `food_stock`;
CREATE TABLE `food_stock` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `item_name` varchar(255) NOT NULL,
  `category` varchar(100) DEFAULT NULL COMMENT 'légumes, protéines, produits laitiers...',
  `quantity` decimal(8,2) NOT NULL DEFAULT 0.00,
  `unit` varchar(20) DEFAULT 'kg',
  `expiry_date` date DEFAULT NULL,
  `threshold_alert` decimal(8,2) DEFAULT 5.00,
  `supplier_id` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `supplier_id` (`supplier_id`),
  KEY `expiry_date` (`expiry_date`),
  CONSTRAINT `fk_food_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `hospitalizations`;
CREATE TABLE `hospitalizations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `patient_id` int(11) NOT NULL,
  `doctor_id` int(11) NOT NULL,
  `bed_id` int(11) DEFAULT NULL,
  `admission_date` datetime NOT NULL DEFAULT current_timestamp(),
  `discharge_date` datetime DEFAULT NULL,
  `reason` text DEFAULT NULL COMMENT 'Motif d''hospitalisation',
  `diagnosis_entry` text DEFAULT NULL COMMENT 'Diagnostic à l''entrée',
  `status` enum('admitted','discharged','transferred') DEFAULT 'admitted',
  `notes` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `patient_id` (`patient_id`),
  KEY `doctor_id` (`doctor_id`),
  KEY `bed_id` (`bed_id`),
  CONSTRAINT `fk_hosp_bed` FOREIGN KEY (`bed_id`) REFERENCES `bed_management` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_hosp_doctor` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`id`),
  CONSTRAINT `fk_hosp_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `insurance`;
CREATE TABLE `insurance` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `patient_id` int(11) NOT NULL,
  `insurance_company` varchar(255) NOT NULL,
  `policy_number` varchar(100) NOT NULL,
  `coverage_percentage` decimal(5,2) DEFAULT 100.00,
  `valid_from` date DEFAULT NULL,
  `valid_until` date DEFAULT NULL,
  `status` enum('active','expired','suspended') DEFAULT 'active',
  PRIMARY KEY (`id`),
  KEY `patient_id` (`patient_id`),
  CONSTRAINT `fk_insurance_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `insurance_claims`;
CREATE TABLE `insurance_claims` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `insurance_id` int(11) NOT NULL,
  `invoice_id` int(11) NOT NULL,
  `claim_amount` decimal(10,2) NOT NULL,
  `approved_amount` decimal(10,2) DEFAULT NULL,
  `status` enum('pending','approved','rejected','partially_approved') DEFAULT 'pending',
  `submission_date` date DEFAULT NULL,
  `response_date` date DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `insurance_id` (`insurance_id`),
  KEY `invoice_id` (`invoice_id`),
  CONSTRAINT `fk_claim_insurance` FOREIGN KEY (`insurance_id`) REFERENCES `insurance` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_claim_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `invoice_items`;
CREATE TABLE `invoice_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `invoice_id` int(11) NOT NULL,
  `description` varchar(255) NOT NULL,
  `quantity` int(11) DEFAULT 1,
  `unit_price` decimal(10,2) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `invoice_id` (`invoice_id`),
  CONSTRAINT `fk_invoice_item_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `invoice_items` VALUES('1','1','Consultation - Dr. Dr. Test Doctor','1','50.00','50.00');
INSERT INTO `invoice_items` VALUES('2','2','Consultation - Dr. Dr. Test Doctor','1','50.00','50.00');

DROP TABLE IF EXISTS `invoices`;
CREATE TABLE `invoices` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `invoice_number` varchar(50) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `appointment_id` int(11) DEFAULT NULL,
  `hospitalization_id` int(11) DEFAULT NULL COMMENT 'Pour factures IPD multi-services',
  `total_amount` decimal(10,2) NOT NULL,
  `paid_amount` decimal(10,2) DEFAULT 0.00,
  `status` enum('draft','unpaid','paid','pending_insurance','cancelled') DEFAULT 'unpaid',
  `generated_date` date DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `invoice_number` (`invoice_number`),
  KEY `patient_id` (`patient_id`),
  KEY `appointment_id` (`appointment_id`),
  KEY `fk_invoice_hosp` (`hospitalization_id`),
  CONSTRAINT `fk_invoice_appointment` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_invoice_hosp` FOREIGN KEY (`hospitalization_id`) REFERENCES `hospitalizations` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_invoice_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `invoices` VALUES('1','INV-20260412-6678','1',NULL,NULL,'50.00','50.00','paid','2026-04-12',NULL,'urgence','2026-04-12 20:45:57','2026-04-12 20:45:57');
INSERT INTO `invoices` VALUES('2','INV-20260412-2926','2',NULL,NULL,'50.00','50.00','paid','2026-04-12',NULL,'suivi','2026-04-12 20:49:55','2026-04-12 20:49:55');

DROP TABLE IF EXISTS `lab_tests`;
CREATE TABLE `lab_tests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `patient_id` int(11) NOT NULL,
  `doctor_id` int(11) NOT NULL,
  `appointment_id` int(11) DEFAULT NULL,
  `test_name` varchar(255) NOT NULL,
  `test_category` varchar(100) DEFAULT NULL,
  `priority` enum('normal','urgent','critical') DEFAULT 'normal',
  `status` enum('pending','in_progress','completed','critical','cancelled') DEFAULT 'pending',
  `result` text DEFAULT NULL,
  `is_critical` tinyint(1) DEFAULT 0 COMMENT 'Résultat critique signalé au médecin',
  `result_file_url` varchar(500) DEFAULT NULL COMMENT 'PDF ou fichier résultat uploadé',
  `unit_measure` varchar(50) DEFAULT NULL COMMENT 'Unité de mesure du résultat',
  `result_date` datetime DEFAULT NULL,
  `performed_by` int(11) DEFAULT NULL COMMENT 'laborantin_id',
  `external_lab_id` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `patient_id` (`patient_id`),
  KEY `appointment_id` (`appointment_id`),
  KEY `performed_by` (`performed_by`),
  KEY `external_lab_id` (`external_lab_id`),
  CONSTRAINT `fk_labtest_appointment` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_labtest_external_lab` FOREIGN KEY (`external_lab_id`) REFERENCES `external_labs` (`id`),
  CONSTRAINT `fk_labtest_laborantin` FOREIGN KEY (`performed_by`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_labtest_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `leave_requests`;
CREATE TABLE `leave_requests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `leave_type` enum('annual','sick','unpaid','maternity','other') DEFAULT 'annual',
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `reason` text DEFAULT NULL,
  `status` enum('pending','approved','rejected','cancelled') DEFAULT 'pending',
  `approved_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `approved_by` (`approved_by`),
  CONSTRAINT `fk_leave_approved_by` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_leave_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `login_logs`;
CREATE TABLE `login_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `email` varchar(255) NOT NULL,
  `login_time` datetime DEFAULT current_timestamp(),
  `ip_address` varchar(45) DEFAULT NULL,
  `success` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_login_time` (`login_time`)
) ENGINE=InnoDB AUTO_INCREMENT=43 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `login_logs` VALUES('1','10','amanilakehal47@gmail.com','2026-04-11 22:20:11','::1','1');
INSERT INTO `login_logs` VALUES('2','11','amanilakehal4777@gmail.com','2026-04-11 22:20:36','::1','1');
INSERT INTO `login_logs` VALUES('3','12','test1@gmail.com','2026-04-11 22:22:05','::1','1');
INSERT INTO `login_logs` VALUES('4','10','amanilakehal47@gmail.com','2026-04-11 22:22:15','::1','1');
INSERT INTO `login_logs` VALUES('5','1','admin@edoc.com','2026-04-11 22:22:30','::1','0');
INSERT INTO `login_logs` VALUES('6','1','admin@edoc.com','2026-04-11 22:22:35','::1','0');
INSERT INTO `login_logs` VALUES('7','1','admin@edoc.com','2026-04-11 22:22:40','::1','0');
INSERT INTO `login_logs` VALUES('8','11','amanilakehal4777@gmail.com','2026-04-11 22:22:57','::1','0');
INSERT INTO `login_logs` VALUES('9','11','amanilakehal4777@gmail.com','2026-04-11 22:23:00','::1','1');
INSERT INTO `login_logs` VALUES('10','11','amanilakehal4777@gmail.com','2026-04-11 22:23:46','::1','0');
INSERT INTO `login_logs` VALUES('11','11','amanilakehal4777@gmail.com','2026-04-11 22:23:51','::1','0');
INSERT INTO `login_logs` VALUES('12','11','amanilakehal4777@gmail.com','2026-04-11 22:23:54','::1','1');
INSERT INTO `login_logs` VALUES('13','1','admin@edoc.com','2026-04-11 22:59:18','::1','0');
INSERT INTO `login_logs` VALUES('14','11','amanilakehal4777@gmail.com','2026-04-11 23:02:36','::1','1');
INSERT INTO `login_logs` VALUES('15','11','amanilakehal4777@gmail.com','2026-04-12 19:22:42','::1','1');
INSERT INTO `login_logs` VALUES('16','11','amanilakehal4777@gmail.com','2026-04-12 19:29:16','::1','1');
INSERT INTO `login_logs` VALUES('17','11','amanilakehal4777@gmail.com','2026-04-12 19:31:48','::1','1');
INSERT INTO `login_logs` VALUES('18','10','amanilakehal47@gmail.com','2026-04-12 20:00:38','::1','0');
INSERT INTO `login_logs` VALUES('19','10','amanilakehal47@gmail.com','2026-04-12 20:00:42','::1','1');
INSERT INTO `login_logs` VALUES('20','10','amanilakehal47@gmail.com','2026-04-12 20:01:14','::1','1');
INSERT INTO `login_logs` VALUES('21','11','amanilakehal4777@gmail.com','2026-04-12 20:01:31','::1','1');
INSERT INTO `login_logs` VALUES('22','15','1@gmail.com','2026-04-12 20:44:08','::1','1');
INSERT INTO `login_logs` VALUES('23','11','amanilakehal4777@gmail.com','2026-04-12 20:48:18','::1','0');
INSERT INTO `login_logs` VALUES('24','11','amanilakehal4777@gmail.com','2026-04-12 20:48:22','::1','1');
INSERT INTO `login_logs` VALUES('25','15','1@gmail.com','2026-04-12 20:49:18','::1','1');
INSERT INTO `login_logs` VALUES('26','11','amanilakehal4777@gmail.com','2026-04-12 20:50:27','::1','1');
INSERT INTO `login_logs` VALUES('27','15','1@gmail.com','2026-04-13 12:12:16','::1','1');
INSERT INTO `login_logs` VALUES('28','11','amanilakehal4777@gmail.com','2026-04-13 12:12:45','::1','0');
INSERT INTO `login_logs` VALUES('29','11','amanilakehal4777@gmail.com','2026-04-13 12:12:52','::1','1');
INSERT INTO `login_logs` VALUES('30','15','1@gmail.com','2026-04-13 12:13:43','::1','1');
INSERT INTO `login_logs` VALUES('31','11','amanilakehal4777@gmail.com','2026-04-14 10:03:33','::1','1');
INSERT INTO `login_logs` VALUES('32','15','1@gmail.com','2026-04-14 10:07:16','::1','1');
INSERT INTO `login_logs` VALUES('33','11','amanilakehal4777@gmail.com','2026-04-14 13:21:16','::1','1');
INSERT INTO `login_logs` VALUES('34',NULL,'gmg@gmail.com','2026-04-21 13:43:55','::1','0');
INSERT INTO `login_logs` VALUES('35',NULL,'gmg@gmail.com','2026-04-21 13:45:15','::1','0');
INSERT INTO `login_logs` VALUES('36','11','amanilakehal4777@gmail.com','2026-04-21 13:45:24','::1','1');
INSERT INTO `login_logs` VALUES('37','11','amanilakehal4777@gmail.com','2026-04-21 13:45:35','::1','1');
INSERT INTO `login_logs` VALUES('38','17','gmg@gmail.com','2026-04-21 13:46:13','::1','1');
INSERT INTO `login_logs` VALUES('39','17','gmg@gmail.com','2026-04-21 14:16:42','::1','1');
INSERT INTO `login_logs` VALUES('40','11','amanilakehal4777@gmail.com','2026-04-21 14:29:40','::1','1');
INSERT INTO `login_logs` VALUES('41','17','gmg@gmail.com','2026-04-21 21:29:48','::1','1');
INSERT INTO `login_logs` VALUES('42','11','amanilakehal4777@gmail.com','2026-04-21 21:54:42','::1','1');

DROP TABLE IF EXISTS `logs`;
CREATE TABLE `logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(255) NOT NULL,
  `entity_type` varchar(50) DEFAULT NULL COMMENT 'patient, prescription, invoice...',
  `entity_id` int(11) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `details` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`details`)),
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `action` (`action`),
  KEY `created_at` (`created_at`),
  CONSTRAINT `fk_log_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `logs` VALUES('1','1','MANUAL_BACKUP','full',NULL,'::1',NULL,NULL,'2026-04-12 19:28:44');
INSERT INTO `logs` VALUES('2','1','CREATE_USER','users','13','::1',NULL,NULL,'2026-04-12 19:47:18');
INSERT INTO `logs` VALUES('3','1','CREATE_USER','users','14','::1',NULL,NULL,'2026-04-12 19:49:10');
INSERT INTO `logs` VALUES('4','1','CREATE_USER','users','15','::1',NULL,NULL,'2026-04-12 20:43:56');
INSERT INTO `logs` VALUES('5','1','MANUAL_BACKUP','full',NULL,'::1',NULL,NULL,'2026-04-14 10:06:58');
INSERT INTO `logs` VALUES('6','1','CREATE_USER','users','17','::1',NULL,NULL,'2026-04-21 13:45:56');
INSERT INTO `logs` VALUES('7','1','MANUAL_BACKUP','full',NULL,'::1',NULL,NULL,'2026-04-21 14:35:54');
INSERT INTO `logs` VALUES('8','1','MANUAL_BACKUP','full',NULL,'::1',NULL,NULL,'2026-04-21 21:29:28');

DROP TABLE IF EXISTS `maintenance_requests`;
CREATE TABLE `maintenance_requests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `equipment_name` varchar(255) NOT NULL,
  `equipment_location` varchar(255) DEFAULT NULL,
  `issue_description` text NOT NULL,
  `maintenance_type` enum('corrective','preventive') DEFAULT 'corrective',
  `priority` enum('low','normal','high','critical') DEFAULT 'normal',
  `status` enum('pending','in_progress','completed','cancelled') DEFAULT 'pending',
  `reported_by` int(11) DEFAULT NULL,
  `assigned_to` int(11) DEFAULT NULL COMMENT 'agent_maintenance user_id',
  `scheduled_date` datetime DEFAULT NULL COMMENT 'Date planifiée (maintenance préventive)',
  `completed_at` datetime DEFAULT NULL,
  `cost` decimal(10,2) DEFAULT NULL,
  `spare_parts_used` text DEFAULT NULL COMMENT 'Pièces de rechange utilisées',
  `notes` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `assigned_to` (`assigned_to`),
  KEY `reported_by` (`reported_by`),
  CONSTRAINT `fk_maintenance_assigned` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_maintenance_reported` FOREIGN KEY (`reported_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `meal_plans`;
CREATE TABLE `meal_plans` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `patient_id` int(11) NOT NULL,
  `meal_type` enum('breakfast','lunch','dinner','snack') NOT NULL,
  `diet_type` varchar(100) DEFAULT NULL COMMENT 'diabétique, sans sel, végétarien...',
  `food_items` text DEFAULT NULL,
  `allergens` text DEFAULT NULL,
  `served_date` date DEFAULT NULL,
  `status` enum('planned','prepared','served','cancelled') DEFAULT 'planned',
  `prepared_by` int(11) DEFAULT NULL COMMENT 'personnel_restauration user_id',
  `notes` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `patient_id` (`patient_id`),
  KEY `served_date` (`served_date`),
  KEY `fk_meal_prepared_by` (`prepared_by`),
  CONSTRAINT `fk_meal_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_meal_prepared_by` FOREIGN KEY (`prepared_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `medical_records`;
CREATE TABLE `medical_records` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `patient_id` int(11) NOT NULL,
  `doctor_id` int(11) NOT NULL,
  `appointment_id` int(11) DEFAULT NULL,
  `diagnosis` text DEFAULT NULL,
  `symptoms` text DEFAULT NULL,
  `clinical_notes` text DEFAULT NULL,
  `treatment_plan` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `patient_id` (`patient_id`),
  KEY `doctor_id` (`doctor_id`),
  KEY `appointment_id` (`appointment_id`),
  CONSTRAINT `fk_medical_record_appointment` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_medical_record_doctor` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`id`),
  CONSTRAINT `fk_medical_record_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `medicines`;
CREATE TABLE `medicines` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `generic_name` varchar(255) DEFAULT NULL,
  `interactions` text DEFAULT NULL COMMENT 'Interactions médicamenteuses connues',
  `is_generic` tinyint(1) DEFAULT 0 COMMENT '1 si médicament générique',
  `original_medicine_id` int(11) DEFAULT NULL COMMENT 'Référence vers médicament original',
  `category` varchar(100) DEFAULT NULL,
  `dosage_form` varchar(50) DEFAULT NULL COMMENT 'comprimé, sirop, injection...',
  `strength` varchar(50) DEFAULT NULL COMMENT '500mg, 10ml...',
  `quantity` int(11) NOT NULL DEFAULT 0,
  `unit` varchar(20) DEFAULT 'boîte',
  `expiry_date` date DEFAULT NULL,
  `threshold_alert` int(11) DEFAULT 10,
  `purchase_price` decimal(10,2) DEFAULT NULL,
  `selling_price` decimal(10,2) DEFAULT NULL,
  `supplier_id` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `supplier_id` (`supplier_id`),
  KEY `expiry_date` (`expiry_date`),
  KEY `fk_medicine_original` (`original_medicine_id`),
  CONSTRAINT `fk_medicine_original` FOREIGN KEY (`original_medicine_id`) REFERENCES `medicines` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `notifications`;
CREATE TABLE `notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `type` varchar(50) DEFAULT 'info',
  `is_read` tinyint(1) DEFAULT 0,
  `link` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `is_read` (`is_read`),
  CONSTRAINT `fk_notification_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `patients`;
CREATE TABLE `patients` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `uhid` varchar(20) NOT NULL COMMENT 'Unique Health ID - généré par réceptionniste',
  `nic` varchar(20) DEFAULT NULL COMMENT 'Numéro de carte nationale',
  `dob` date DEFAULT NULL,
  `gender` enum('M','F','other') DEFAULT NULL,
  `blood_type` varchar(5) DEFAULT NULL COMMENT 'Groupe sanguin - page 53 diagramme',
  `emergency_contact_name` varchar(255) DEFAULT NULL COMMENT 'Contact urgence',
  `emergency_contact_phone` varchar(20) DEFAULT NULL,
  `allergies` text DEFAULT NULL COMMENT 'Allergies connues - alertes automatiques',
  `medical_history` text DEFAULT NULL COMMENT 'Antécédents médicaux',
  `insurance_id` int(11) DEFAULT NULL COMMENT 'Référence rapide à l''assurance active',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uhid` (`uhid`),
  KEY `user_id` (`user_id`),
  KEY `fk_patient_insurance` (`insurance_id`),
  CONSTRAINT `fk_patient_insurance` FOREIGN KEY (`insurance_id`) REFERENCES `insurance` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_patient_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `patients` VALUES('1','3','UHID20240001','0000000000','2000-01-01','M','A+','Jean Dupont','0600000000','Pénicilline','Hypertension artérielle',NULL,'2026-04-05 19:59:38','2026-04-05 19:59:38');
INSERT INTO `patients` VALUES('2','16','P202695483','','2011-11-11','F','','','','','',NULL,'2026-04-12 20:49:43','2026-04-12 20:49:43');

DROP TABLE IF EXISTS `payments`;
CREATE TABLE `payments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `invoice_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `method` enum('cash','card','bank_transfer','insurance','check') DEFAULT 'cash',
  `transaction_id` varchar(100) DEFAULT NULL,
  `payment_date` datetime DEFAULT current_timestamp(),
  `received_by` int(11) DEFAULT NULL COMMENT 'user_id du comptable',
  `notes` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `invoice_id` (`invoice_id`),
  KEY `received_by` (`received_by`),
  CONSTRAINT `fk_payment_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_payment_received_by` FOREIGN KEY (`received_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `payments` VALUES('1','1','50.00','card',NULL,'2026-04-12 20:45:57','15','urgence');
INSERT INTO `payments` VALUES('2','2','50.00','check',NULL,'2026-04-12 20:49:55','15','suivi');

DROP TABLE IF EXISTS `prescription_items`;
CREATE TABLE `prescription_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `prescription_id` int(11) NOT NULL,
  `medicine_id` int(11) NOT NULL,
  `dosage` varchar(100) NOT NULL COMMENT 'Ex: 1 comprimé matin et soir',
  `duration` varchar(100) DEFAULT NULL COMMENT 'Ex: 7 jours',
  `quantity` int(11) DEFAULT 1,
  `instructions` text DEFAULT NULL COMMENT 'Instructions particulières',
  `status` enum('pending','delivered','cancelled') DEFAULT 'pending',
  PRIMARY KEY (`id`),
  KEY `prescription_id` (`prescription_id`),
  KEY `medicine_id` (`medicine_id`),
  CONSTRAINT `fk_prescription_item_medicine` FOREIGN KEY (`medicine_id`) REFERENCES `medicines` (`id`),
  CONSTRAINT `fk_prescription_item_prescription` FOREIGN KEY (`prescription_id`) REFERENCES `prescriptions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `prescriptions`;
CREATE TABLE `prescriptions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `patient_id` int(11) NOT NULL,
  `doctor_id` int(11) NOT NULL,
  `appointment_id` int(11) DEFAULT NULL,
  `prescription_date` datetime DEFAULT current_timestamp(),
  `status` enum('draft','active','delivered','expired','cancelled') DEFAULT 'active',
  `notes` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `patient_id` (`patient_id`),
  KEY `doctor_id` (`doctor_id`),
  KEY `appointment_id` (`appointment_id`),
  CONSTRAINT `fk_prescription_appointment` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_prescription_doctor` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`id`),
  CONSTRAINT `fk_prescription_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `psychology_sessions`;
CREATE TABLE `psychology_sessions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `patient_id` int(11) NOT NULL,
  `psychologue_id` int(11) NOT NULL COMMENT 'user_id du psychologue',
  `session_date` datetime NOT NULL,
  `duration_minutes` int(11) DEFAULT 60,
  `session_type` enum('individuelle','groupe','suivi','evaluation') DEFAULT 'individuelle',
  `confidential_notes` text DEFAULT NULL COMMENT 'Notes confidentielles du psychologue',
  `progress` text DEFAULT NULL COMMENT 'Évolution psychologique observée',
  `next_session_date` datetime DEFAULT NULL,
  `status` enum('scheduled','completed','cancelled','no_show') DEFAULT 'scheduled',
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `patient_id` (`patient_id`),
  KEY `psychologue_id` (`psychologue_id`),
  CONSTRAINT `fk_psych_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_psych_psy` FOREIGN KEY (`psychologue_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `purchase_order_items`;
CREATE TABLE `purchase_order_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL,
  `item_name` varchar(255) NOT NULL,
  `item_type` enum('medicine','food','equipment','other') DEFAULT 'medicine',
  `item_id` int(11) DEFAULT NULL COMMENT 'medicine_id ou food_stock_id selon type',
  `quantity` decimal(10,2) NOT NULL,
  `unit` varchar(20) DEFAULT NULL,
  `unit_price` decimal(10,2) DEFAULT NULL,
  `total_price` decimal(10,2) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `order_id` (`order_id`),
  CONSTRAINT `fk_poi_order` FOREIGN KEY (`order_id`) REFERENCES `purchase_orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `purchase_orders`;
CREATE TABLE `purchase_orders` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_number` varchar(50) NOT NULL,
  `supplier_id` int(11) NOT NULL,
  `order_date` date DEFAULT NULL,
  `delivery_date` date DEFAULT NULL,
  `status` enum('pending','confirmed','shipped','delivered','cancelled') DEFAULT 'pending',
  `total_amount` decimal(10,2) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `order_number` (`order_number`),
  KEY `supplier_id` (`supplier_id`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `fk_po_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_po_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `radiology_requests`;
CREATE TABLE `radiology_requests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `patient_id` int(11) NOT NULL,
  `doctor_id` int(11) NOT NULL,
  `appointment_id` int(11) DEFAULT NULL,
  `exam_type` enum('radio','echographie','scanner','IRM','autre') NOT NULL,
  `priority` enum('normal','urgent','critical') DEFAULT 'normal',
  `status` enum('pending','scheduled','in_progress','completed','cancelled') DEFAULT 'pending',
  `radiologue_id` int(11) DEFAULT NULL COMMENT 'user_id du radiologue assigné',
  `scheduled_date` datetime DEFAULT NULL,
  `report` text DEFAULT NULL COMMENT 'Rapport radiologique rédigé par le radiologue',
  `image_url` varchar(500) DEFAULT NULL COMMENT 'Lien image DICOM ou PDF uploadé',
  `is_critical` tinyint(1) DEFAULT 0 COMMENT 'Anomalie critique détectée',
  `notes` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `patient_id` (`patient_id`),
  KEY `doctor_id` (`doctor_id`),
  KEY `radiologue_id` (`radiologue_id`),
  CONSTRAINT `fk_radio_doctor` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`id`),
  CONSTRAINT `fk_radio_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_radio_radiologue` FOREIGN KEY (`radiologue_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `rehabilitation_plans`;
CREATE TABLE `rehabilitation_plans` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `patient_id` int(11) NOT NULL,
  `kine_id` int(11) NOT NULL COMMENT 'user_id du kinésithérapeute',
  `doctor_id` int(11) DEFAULT NULL COMMENT 'Médecin prescripteur',
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `objective` text DEFAULT NULL COMMENT 'Objectif de rééducation',
  `exercises` text DEFAULT NULL COMMENT 'Description des exercices prescrits',
  `activity_type` enum('motrice','piscine','respiratoire','autre') DEFAULT 'motrice',
  `sessions_per_week` int(11) DEFAULT 3,
  `progress_notes` text DEFAULT NULL COMMENT 'Notes de progression patient',
  `status` enum('active','completed','suspended','cancelled') DEFAULT 'active',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `patient_id` (`patient_id`),
  KEY `kine_id` (`kine_id`),
  KEY `fk_rehab_doctor` (`doctor_id`),
  CONSTRAINT `fk_rehab_doctor` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_rehab_kine` FOREIGN KEY (`kine_id`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_rehab_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `roles`;
CREATE TABLE `roles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `role_name` varchar(50) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `permissions` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Permissions JSON par rôle - page 16' CHECK (json_valid(`permissions`)),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `roles` VALUES('1','admin_systeme','Administrateur du système - gestion utilisateurs, sécurité, sauvegardes',NULL);
INSERT INTO `roles` VALUES('2','medecin','Médecin - diagnostic, prescriptions, suivi médical',NULL);
INSERT INTO `roles` VALUES('3','patient','Patient - RDV, dossier médical, paiements',NULL);
INSERT INTO `roles` VALUES('4','infirmier','Infirmier(ère) - soins quotidiens, constantes vitales',NULL);
INSERT INTO `roles` VALUES('5','laborantin','Laborantin - analyses médicales, résultats',NULL);
INSERT INTO `roles` VALUES('6','pharmacien','Pharmacien - gestion médicaments, stock, délivrance',NULL);
INSERT INTO `roles` VALUES('7','receptionniste','Réceptionniste - accueil, enregistrement patients, RDV',NULL);
INSERT INTO `roles` VALUES('8','gestionnaire_rh','Gestionnaire RH - personnel, salaires, congés',NULL);
INSERT INTO `roles` VALUES('9','comptable','Comptable - facturation, paiements, rapports financiers',NULL);
INSERT INTO `roles` VALUES('10','agent_nettoyage','Agent de nettoyage - entretien des chambres',NULL);
INSERT INTO `roles` VALUES('11','agent_maintenance','Agent de maintenance - équipements et infrastructures',NULL);
INSERT INTO `roles` VALUES('12','personnel_restauration','Personnel de restauration - repas patients',NULL);
INSERT INTO `roles` VALUES('13','fournisseur','Fournisseur - approvisionnement médicaments et produits',NULL);
INSERT INTO `roles` VALUES('14','assurance','Compagnie d\'assurance - remboursements',NULL);
INSERT INTO `roles` VALUES('15','laboratoire_externe','Laboratoire externe - analyses spécialisées',NULL);
INSERT INTO `roles` VALUES('16','radiologue','Radiologue - imagerie médicale',NULL);
INSERT INTO `roles` VALUES('17','kinesitherapeute','Kinésithérapeute - rééducation physique',NULL);
INSERT INTO `roles` VALUES('18','psychologue','Psychologue - santé mentale',NULL);
INSERT INTO `roles` VALUES('19','ambulancier','Ambulancier - transport des patients',NULL);
INSERT INTO `roles` VALUES('20','agent_securite','Agent de sécurité - contrôle accès',NULL);

DROP TABLE IF EXISTS `schedules`;
CREATE TABLE `schedules` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `doctor_id` int(11) NOT NULL,
  `day_of_week` tinyint(1) NOT NULL COMMENT '0=Dimanche, 1=Lundi...',
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `type_session` enum('consultation','urgence','chirurgie','garde') DEFAULT 'consultation' COMMENT 'Type de session planifiée',
  `department_id` int(11) DEFAULT NULL COMMENT 'Département concerné',
  `is_emergency_slot` tinyint(1) DEFAULT 0 COMMENT 'Créneau réservé aux urgences',
  `status` enum('actif','annule','suspendu') DEFAULT 'actif' COMMENT 'Statut du planning',
  `max_duration_minutes` int(11) DEFAULT 30 COMMENT 'Durée maximale par patient',
  `max_patients` int(11) DEFAULT 10 COMMENT 'Nombre maximum de patients par session',
  PRIMARY KEY (`id`),
  KEY `doctor_id` (`doctor_id`),
  CONSTRAINT `fk_schedule_doctor` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `schedules` VALUES('1','2','1','11:11:00','11:11:00','consultation',NULL,'0','actif','30','5');

DROP TABLE IF EXISTS `security_incidents`;
CREATE TABLE `security_incidents` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `agent_id` int(11) NOT NULL COMMENT 'user_id de l''agent de sécurité',
  `incident_type` enum('vol','violence','acces_non_autorise','intrusion','autre') NOT NULL,
  `location` varchar(255) DEFAULT NULL,
  `description` text NOT NULL,
  `severity` enum('low','medium','high','critical') DEFAULT 'medium',
  `status` enum('open','in_progress','resolved','escalated') DEFAULT 'open',
  `reported_at` datetime DEFAULT current_timestamp(),
  `resolved_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `agent_id` (`agent_id`),
  CONSTRAINT `fk_sec_agent` FOREIGN KEY (`agent_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `sessions`;
CREATE TABLE `sessions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `token` varchar(500) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `otp_verified` tinyint(1) DEFAULT 0 COMMENT '2FA validé ou non',
  `expires_at` datetime NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `token` (`token`(100)),
  CONSTRAINT `fk_session_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `settings`;
CREATE TABLE `settings` (
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `settings` VALUES('address','','2026-04-11 21:36:54');
INSERT INTO `settings` VALUES('appointment_duration','0','2026-04-11 22:07:51');
INSERT INTO `settings` VALUES('contact_email','contact@edoc.com','2026-04-11 21:36:54');
INSERT INTO `settings` VALUES('contact_phone','','2026-04-11 21:36:54');
INSERT INTO `settings` VALUES('site_name','eDocio','2026-04-11 23:35:49');
INSERT INTO `settings` VALUES('timezone','Asia/Kolkata','2026-04-11 21:36:54');

DROP TABLE IF EXISTS `specialties`;
CREATE TABLE `specialties` (
  `id` int(2) NOT NULL,
  `sname` varchar(100) NOT NULL,
  `description` varchar(255) DEFAULT NULL COMMENT 'Description optionnelle - image',
  `department_id` int(11) DEFAULT NULL COMMENT 'Lien département optionnel - image',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `specialties` VALUES('1','Accident and emergency medicine',NULL,NULL);
INSERT INTO `specialties` VALUES('2','Allergology',NULL,NULL);
INSERT INTO `specialties` VALUES('3','Anaesthetics',NULL,NULL);
INSERT INTO `specialties` VALUES('4','Biological hematology',NULL,NULL);
INSERT INTO `specialties` VALUES('5','Cardiology',NULL,NULL);
INSERT INTO `specialties` VALUES('6','Child psychiatry',NULL,NULL);
INSERT INTO `specialties` VALUES('7','Clinical biology',NULL,NULL);
INSERT INTO `specialties` VALUES('8','Clinical chemistry',NULL,NULL);
INSERT INTO `specialties` VALUES('9','Clinical neurophysiology',NULL,NULL);
INSERT INTO `specialties` VALUES('10','Clinical radiology',NULL,NULL);
INSERT INTO `specialties` VALUES('11','Dental, oral and maxillo-facial surgery',NULL,NULL);
INSERT INTO `specialties` VALUES('12','Dermato-venerology',NULL,NULL);
INSERT INTO `specialties` VALUES('13','Dermatology',NULL,NULL);
INSERT INTO `specialties` VALUES('14','Endocrinology',NULL,NULL);
INSERT INTO `specialties` VALUES('15','Gastro-enterologic surgery',NULL,NULL);
INSERT INTO `specialties` VALUES('16','Gastroenterology',NULL,NULL);
INSERT INTO `specialties` VALUES('17','General hematology',NULL,NULL);
INSERT INTO `specialties` VALUES('18','General Practice',NULL,NULL);
INSERT INTO `specialties` VALUES('19','General surgery',NULL,NULL);
INSERT INTO `specialties` VALUES('20','Geriatrics',NULL,NULL);
INSERT INTO `specialties` VALUES('21','Immunology',NULL,NULL);
INSERT INTO `specialties` VALUES('22','Infectious diseases',NULL,NULL);
INSERT INTO `specialties` VALUES('23','Internal medicine',NULL,NULL);
INSERT INTO `specialties` VALUES('24','Laboratory medicine',NULL,NULL);
INSERT INTO `specialties` VALUES('25','Maxillo-facial surgery',NULL,NULL);
INSERT INTO `specialties` VALUES('26','Microbiology',NULL,NULL);
INSERT INTO `specialties` VALUES('27','Nephrology',NULL,NULL);
INSERT INTO `specialties` VALUES('28','Neuro-psychiatry',NULL,NULL);
INSERT INTO `specialties` VALUES('29','Neurology',NULL,NULL);
INSERT INTO `specialties` VALUES('30','Neurosurgery',NULL,NULL);
INSERT INTO `specialties` VALUES('31','Nuclear medicine',NULL,NULL);
INSERT INTO `specialties` VALUES('32','Obstetrics and gynecology',NULL,NULL);
INSERT INTO `specialties` VALUES('33','Occupational medicine',NULL,NULL);
INSERT INTO `specialties` VALUES('34','Ophthalmology',NULL,NULL);
INSERT INTO `specialties` VALUES('35','Orthopaedics',NULL,NULL);
INSERT INTO `specialties` VALUES('36','Otorhinolaryngology',NULL,NULL);
INSERT INTO `specialties` VALUES('37','Paediatric surgery',NULL,NULL);
INSERT INTO `specialties` VALUES('38','Paediatrics',NULL,NULL);
INSERT INTO `specialties` VALUES('39','Pathology',NULL,NULL);
INSERT INTO `specialties` VALUES('40','Pharmacology',NULL,NULL);
INSERT INTO `specialties` VALUES('41','Physical medicine and rehabilitation',NULL,NULL);
INSERT INTO `specialties` VALUES('42','Plastic surgery',NULL,NULL);
INSERT INTO `specialties` VALUES('43','Podiatric Medicine',NULL,NULL);
INSERT INTO `specialties` VALUES('44','Podiatric Surgery',NULL,NULL);
INSERT INTO `specialties` VALUES('45','Psychiatry',NULL,NULL);
INSERT INTO `specialties` VALUES('46','Public health and Preventive Medicine',NULL,NULL);
INSERT INTO `specialties` VALUES('47','Radiology',NULL,NULL);
INSERT INTO `specialties` VALUES('48','Radiotherapy',NULL,NULL);
INSERT INTO `specialties` VALUES('49','Respiratory medicine',NULL,NULL);
INSERT INTO `specialties` VALUES('50','Rheumatology',NULL,NULL);
INSERT INTO `specialties` VALUES('51','Stomatology',NULL,NULL);
INSERT INTO `specialties` VALUES('52','Thoracic surgery',NULL,NULL);
INSERT INTO `specialties` VALUES('53','Tropical medicine',NULL,NULL);
INSERT INTO `specialties` VALUES('54','Urology',NULL,NULL);
INSERT INTO `specialties` VALUES('55','Vascular surgery',NULL,NULL);
INSERT INTO `specialties` VALUES('56','Venereology',NULL,NULL);

DROP TABLE IF EXISTS `staff_planning`;
CREATE TABLE `staff_planning` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `planning_date` date NOT NULL,
  `shift_start` time NOT NULL,
  `shift_end` time NOT NULL,
  `shift_type` enum('matin','soir','nuit','garde','urgence') DEFAULT 'matin',
  `department` varchar(100) DEFAULT NULL,
  `is_on_call` tinyint(1) DEFAULT 0 COMMENT 'Médecin de garde',
  `status` enum('scheduled','confirmed','absent','replaced') DEFAULT 'scheduled',
  `replacement_id` int(11) DEFAULT NULL COMMENT 'user_id du remplaçant',
  `notes` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `planning_date` (`planning_date`),
  KEY `fk_planning_replacement` (`replacement_id`),
  KEY `fk_planning_created_by` (`created_by`),
  CONSTRAINT `fk_planning_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_planning_replacement` FOREIGN KEY (`replacement_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_planning_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `stock_movements`;
CREATE TABLE `stock_movements` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `medicine_id` int(11) NOT NULL,
  `type` enum('in','out','adjustment') NOT NULL,
  `quantity` int(11) NOT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `reference_id` int(11) DEFAULT NULL COMMENT 'ID prescription ou commande',
  `performed_by` int(11) DEFAULT NULL COMMENT 'user_id',
  `movement_date` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `medicine_id` (`medicine_id`),
  KEY `performed_by` (`performed_by`),
  CONSTRAINT `fk_stock_movement_medicine` FOREIGN KEY (`medicine_id`) REFERENCES `medicines` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_stock_movement_user` FOREIGN KEY (`performed_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `suppliers`;
CREATE TABLE `suppliers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `contact_person` varchar(255) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `supplier_type` enum('medicines','equipment','food','other') DEFAULT 'medicines',
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(255) NOT NULL,
  `role_id` int(11) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `two_factor_enabled` tinyint(1) DEFAULT 0 COMMENT '2FA obligatoire admin et personnel médical',
  `otp_secret` varchar(255) DEFAULT NULL COMMENT 'Secret pour génération OTP',
  `failed_login_attempts` tinyint(3) DEFAULT 0 COMMENT 'Tentatives échouées',
  `locked_until` datetime DEFAULT NULL COMMENT 'Compte verrouillé jusqu''à cette date',
  `last_login` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `two_factor_secret` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  KEY `role_id` (`role_id`),
  CONSTRAINT `fk_users_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `users` VALUES('1','admin@edoc.com','123','Administrateur Système','1','0100000000','Clinique Centrale','1','1',NULL,'0',NULL,NULL,'2026-04-05 19:59:38','2026-04-05 21:25:18',NULL);
INSERT INTO `users` VALUES('2','doctor@edoc.com','123','Dr. Test Doctor','2','0110000000','Service Médecine','1','1',NULL,'0',NULL,NULL,'2026-04-05 19:59:38','2026-04-05 21:25:18',NULL);
INSERT INTO `users` VALUES('3','patient@edoc.com','123','Test Patient','1','0120000000','Adresse Patient','1','0',NULL,'0',NULL,NULL,'2026-04-05 19:59:38','2026-04-12 19:24:57',NULL);
INSERT INTO `users` VALUES('4','infirmier@clinique.com','123','Sophie Martin','4','0130000000','Service Soins','1','0',NULL,'0',NULL,NULL,'2026-04-05 19:59:38','2026-04-11 22:02:53',NULL);
INSERT INTO `users` VALUES('5','pharmacien@clinique.com','123','Karim Benali','6','0140000000','Pharmacie Centrale','1','1',NULL,'0',NULL,NULL,'2026-04-05 19:59:38','2026-04-05 21:25:18',NULL);
INSERT INTO `users` VALUES('6','laborantin@clinique.com','123','Leila Mansouri','5','0150000000','Laboratoire d\'Analyse','1','1',NULL,'0',NULL,NULL,'2026-04-05 19:59:38','2026-04-05 21:25:18',NULL);
INSERT INTO `users` VALUES('7','reception@clinique.com','123','Nadia Bouzid','7','0160000000','Accueil Clinique','1','1',NULL,'0',NULL,NULL,'2026-04-05 19:59:38','2026-04-12 19:08:24',NULL);
INSERT INTO `users` VALUES('8','rh@clinique.com','123','Ahmed Khelil','8','0170000000','Service RH','1','1',NULL,'0',NULL,NULL,'2026-04-05 19:59:38','2026-04-12 19:08:20',NULL);
INSERT INTO `users` VALUES('9','comptable@clinique.com','123','Fatima Zohra','9','0180000000','Service Finances','0','1',NULL,'0',NULL,NULL,'2026-04-05 19:59:38','2026-04-12 19:23:09',NULL);
INSERT INTO `users` VALUES('10','amanilakehal47@gmail.com','$2y$10$W7B0xIvS5YDALA5EiE4C..mJZwp2cX0wo8fMUbMpYJw1h8nqN0Oay','Amani Lakehal','2','11111111111111',NULL,'1','0',NULL,'0',NULL,NULL,'2026-04-05 21:27:21','2026-04-12 20:00:26',NULL);
INSERT INTO `users` VALUES('11','amanilakehal4777@gmail.com','$2y$10$fmOLZu5Q90Ca.rIlgHOx4OPV67rvOOrVcD26eYp6N43q7iRYp86vu','Amaniii  pp','1','0000000000',NULL,'1','1',NULL,'0',NULL,NULL,'2026-04-11 21:41:13','2026-04-12 19:20:24',NULL);
INSERT INTO `users` VALUES('13','ala@gmail.c','$2y$10$893Zk/K7IR9B3HGiqv6Yy.nGjCkWY2uwnGRZyIulIHIIwTmKieJJq','ala','3','0000000000',NULL,'1','0',NULL,'0',NULL,NULL,'2026-04-12 19:47:18','2026-04-12 19:48:32',NULL);
INSERT INTO `users` VALUES('14','lk@g.c','$2y$10$yAqEWuBFva.GDxJx5/zVtuichOZg/bAUyXzKPZyTfOtD4rQdO8Hk2','lk','3','0000000000',NULL,'1','0',NULL,'0',NULL,NULL,'2026-04-12 19:49:10','2026-04-12 19:49:10',NULL);
INSERT INTO `users` VALUES('15','1@gmail.com','$2y$10$1xHe3bAvauyTVj8Cs3SlfekMDBNs.Qjyop0ovc6HgSITgttudNJCe','1','7','11111111111111',NULL,'1','0',NULL,'0',NULL,NULL,'2026-04-12 20:43:56','2026-04-12 20:43:56',NULL);
INSERT INTO `users` VALUES('16','P202695483@clinic.local','','ppd','3','0000000000',NULL,'1','0',NULL,'0',NULL,NULL,'2026-04-12 20:49:42','2026-04-12 20:49:42',NULL);
INSERT INTO `users` VALUES('17','gmg@gmail.com','$2y$10$sA8aS0o.Tye.sVcadL7Q.OFacFy5O69i7Bzbf4OdaMgMn/UVh3G9W','g','11','0000000000',NULL,'1','0',NULL,'0',NULL,NULL,'2026-04-21 13:45:56','2026-04-21 14:32:28',NULL);

DROP TABLE IF EXISTS `visitor_logs`;
CREATE TABLE `visitor_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `visitor_name` varchar(255) NOT NULL,
  `id_document` varchar(100) DEFAULT NULL COMMENT 'Numéro pièce d''identité',
  `patient_id` int(11) DEFAULT NULL COMMENT 'Patient visité',
  `agent_id` int(11) DEFAULT NULL COMMENT 'Agent ayant enregistré la visite',
  `check_in` datetime DEFAULT current_timestamp(),
  `check_out` datetime DEFAULT NULL,
  `badge_number` varchar(50) DEFAULT NULL,
  `zone_access` varchar(100) DEFAULT NULL COMMENT 'Zones autorisées',
  PRIMARY KEY (`id`),
  KEY `patient_id` (`patient_id`),
  KEY `agent_id` (`agent_id`),
  CONSTRAINT `fk_visitor_agent` FOREIGN KEY (`agent_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_visitor_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `vital_signs`;
CREATE TABLE `vital_signs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `patient_id` int(11) NOT NULL,
  `recorded_by` int(11) NOT NULL COMMENT 'infirmier_id',
  `temperature` decimal(4,1) DEFAULT NULL COMMENT 'Température en °C',
  `blood_pressure_systolic` int(11) DEFAULT NULL,
  `blood_pressure_diastolic` int(11) DEFAULT NULL,
  `heart_rate` int(11) DEFAULT NULL COMMENT 'Fréquence cardiaque',
  `respiratory_rate` int(11) DEFAULT NULL COMMENT 'Fréquence respiratoire',
  `oxygen_saturation` int(11) DEFAULT NULL COMMENT 'SpO2 %',
  `blood_glucose` decimal(5,2) DEFAULT NULL COMMENT 'Glycémie',
  `weight` decimal(5,2) DEFAULT NULL COMMENT 'Poids en kg',
  `height` decimal(5,2) DEFAULT NULL COMMENT 'Taille en cm',
  `notes` text DEFAULT NULL,
  `recorded_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `patient_id` (`patient_id`),
  KEY `recorded_by` (`recorded_by`),
  CONSTRAINT `fk_vitals_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_vitals_recorded_by` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


