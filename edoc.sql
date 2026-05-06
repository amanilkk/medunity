-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Hôte : 127.0.0.1
-- Généré le : mar. 05 mai 2026 à 22:33
-- Version du serveur : 10.4.32-MariaDB
-- Version de PHP : 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de données : `edoc`
--

-- --------------------------------------------------------

--
-- Structure de la table `alerts`
--

CREATE TABLE `alerts` (
  `id` int(11) NOT NULL,
  `type` enum('stock','critical_result','urgent_patient','maintenance','expiry','bed_unavailable') NOT NULL,
  `severity` enum('info','warning','critical') DEFAULT 'info',
  `message` text NOT NULL,
  `reference_id` int(11) DEFAULT NULL COMMENT 'ID lié (medicine_id, lab_test_id, etc.)',
  `target_role_id` int(11) DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `read_by` int(11) DEFAULT NULL,
  `read_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `ambulance_missions`
--

CREATE TABLE `ambulance_missions` (
  `id` int(11) NOT NULL,
  `patient_id` int(11) DEFAULT NULL,
  `ambulancier_id` int(11) NOT NULL COMMENT 'user_id de l''ambulancier',
  `origin_address` text DEFAULT NULL,
  `destination_address` text DEFAULT NULL,
  `mission_type` enum('urgence','transfert','retour_domicile','autre') DEFAULT 'urgence',
  `status` enum('assigned','in_progress','completed','cancelled') DEFAULT 'assigned',
  `departure_time` datetime DEFAULT NULL,
  `arrival_time` datetime DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `appointments`
--

CREATE TABLE `appointments` (
  `id` int(11) NOT NULL,
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
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `attendances`
--

CREATE TABLE `attendances` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `attendance_date` date NOT NULL,
  `status` enum('present','absent','late','excused','holiday') DEFAULT 'present',
  `check_in` time DEFAULT NULL,
  `check_out` time DEFAULT NULL,
  `hours_worked` decimal(5,2) DEFAULT 0.00,
  `overtime_hours` decimal(5,2) DEFAULT 0.00,
  `notes` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `bed_management`
--

CREATE TABLE `bed_management` (
  `id` int(11) NOT NULL,
  `room_number` varchar(10) NOT NULL,
  `bed_number` varchar(10) NOT NULL,
  `bed_type` enum('standard','icu','pediatric','isolation') DEFAULT 'standard',
  `status` enum('available','occupied','cleaning','maintenance','reserved') DEFAULT 'available',
  `patient_id` int(11) DEFAULT NULL,
  `admission_date` datetime DEFAULT NULL,
  `expected_discharge_date` date DEFAULT NULL,
  `cleaning_task_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `bed_management`
--

INSERT INTO `bed_management` (`id`, `room_number`, `bed_number`, `bed_type`, `status`, `patient_id`, `admission_date`, `expected_discharge_date`, `cleaning_task_id`) VALUES
(1, 'R-1', 'B-101', 'standard', 'available', NULL, NULL, NULL, NULL),
(2, 'R-1', 'B-102', 'standard', 'occupied', 2, '2026-04-20 23:46:35', NULL, NULL),
(3, 'R-1', 'B-103', 'standard', 'occupied', 1, '2026-04-21 14:27:40', NULL, NULL),
(4, 'R-2', 'B-201', '', 'available', NULL, NULL, NULL, NULL),
(5, 'R-2', 'B-202', '', 'occupied', NULL, NULL, NULL, NULL),
(6, 'R-2', 'B-203', '', 'available', NULL, NULL, NULL, NULL),
(7, 'R-3', 'B-301', 'pediatric', 'occupied', NULL, NULL, NULL, NULL),
(8, 'R-3', 'B-302', 'pediatric', 'available', NULL, NULL, NULL, NULL),
(9, 'R-3', 'B-303', 'pediatric', 'available', NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Structure de la table `chat_messages`
--

CREATE TABLE `chat_messages` (
  `id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `receiver_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `attachment_url` varchar(500) DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `read_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `cleaning_tasks`
--

CREATE TABLE `cleaning_tasks` (
  `id` int(11) NOT NULL,
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
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `cleaning_tasks`
--

INSERT INTO `cleaning_tasks` (`id`, `bed_id`, `room_number`, `area_type`, `task_type`, `priority`, `status`, `assigned_to`, `completed_at`, `notes`, `anomaly_report`, `created_at`) VALUES
(1, 2, 'R-1', 'chambre', 'daily', 'high', 'completed', NULL, '2026-04-20 23:45:41', NULL, NULL, '2026-04-20 23:45:37'),
(2, 1, 'R-1', 'chambre', 'daily', 'high', 'pending', NULL, NULL, NULL, NULL, '2026-04-21 14:27:27');

-- --------------------------------------------------------

--
-- Structure de la table `departments`
--

CREATE TABLE `departments` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `manager_id` int(11) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `departments`
--

INSERT INTO `departments` (`id`, `name`, `description`, `manager_id`, `is_active`, `created_at`) VALUES
(1, 'Médecine Générale', 'Service de médecine générale', NULL, 1, '2026-04-22 23:27:41'),
(2, 'Cardiologie', 'Service de cardiologie', NULL, 1, '2026-04-22 23:27:41'),
(3, 'Pédiatrie', 'Service de pédiatrie', NULL, 1, '2026-04-22 23:27:41'),
(4, 'Urgences', 'Service des urgences', NULL, 1, '2026-04-22 23:27:41'),
(5, 'Administration', 'Service administratif', NULL, 1, '2026-04-22 23:27:41'),
(6, 'Laboratoire', 'Laboratoire d\'analyses médicales', NULL, 1, '2026-04-22 23:27:41'),
(7, 'Radiologie', 'Service de radiologie et imagerie', NULL, 1, '2026-04-22 23:27:41'),
(8, 'Chirurgie', 'Service de chirurgie générale', NULL, 1, '2026-04-22 23:27:41'),
(9, 'Gynécologie', 'Service de gynécologie-obstétrique', NULL, 1, '2026-04-22 23:27:41'),
(10, 'Pharmacie', 'Service pharmaceutique', NULL, 1, '2026-04-22 23:27:41'),
(11, 'Médecine Générale', 'Service de médecine générale', NULL, 1, '2026-04-23 01:29:53'),
(12, 'Cardiologie', 'Service de cardiologie', NULL, 1, '2026-04-23 01:29:53'),
(13, 'Pédiatrie', 'Service de pédiatrie', NULL, 1, '2026-04-23 01:29:53'),
(14, 'Urgences', 'Service des urgences', NULL, 1, '2026-04-23 01:29:53'),
(15, 'Administration', 'Service administratif', NULL, 1, '2026-04-23 01:29:53'),
(16, 'Laboratoire', 'Laboratoire d\'analyses médicales', NULL, 1, '2026-04-23 01:29:53'),
(17, 'Radiologie', 'Service de radiologie et imagerie', NULL, 1, '2026-04-23 01:29:53');

-- --------------------------------------------------------

--
-- Structure de la table `doctors`
--

CREATE TABLE `doctors` (
  `id` int(11) NOT NULL,
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
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `doctors`
--

INSERT INTO `doctors` (`id`, `user_id`, `specialty_id`, `consultation_fee`, `room_number`, `department`, `availability_status`, `languages`, `experience_years`, `biography`, `created_at`, `updated_at`) VALUES
(1, 2, 18, 50.00, 'B12', 'Médecine Générale', 'available', NULL, 10, 'Médecin généraliste expérimenté', '2026-04-05 19:59:38', '2026-04-05 19:59:38'),
(3, 20, 5, 2500.00, 'C101', 'Cardiologie', 'available', 'Français, Anglais, Arabe', 12, 'Cardiologue expérimenté, spécialiste des maladies cardiovasculaires', '2026-04-23 01:25:26', '2026-04-23 01:25:26'),
(4, 21, 38, 2000.00, 'P205', 'Pédiatrie', 'available', 'Français, Arabe', 8, 'Pédiatre dévouée, passionnée par la santé des enfants', '2026-04-23 01:25:26', '2026-04-23 01:25:26'),
(5, 2, 18, 1500.00, 'G110', 'Médecine Générale', 'available', 'Français, Arabe, Anglais', 10, 'Médecin généraliste expérimenté', '2026-04-23 01:25:26', '2026-04-23 01:25:26'),
(20, 20, 5, 2500.00, 'C101', 'Cardiologie', 'available', NULL, 12, 'Cardiologue expérimenté', '2026-04-23 01:28:45', '2026-04-23 01:28:45'),
(21, 21, 38, 2000.00, 'P205', 'Pédiatrie', 'available', NULL, 8, 'Pédiatre dévouée', '2026-04-23 01:28:45', '2026-04-23 01:28:45'),
(22, 30, 19, NULL, NULL, NULL, 'available', NULL, NULL, NULL, '2026-05-01 20:06:56', '2026-05-01 20:06:56'),
(23, 31, 3, NULL, NULL, NULL, 'available', NULL, NULL, NULL, '2026-05-01 20:08:55', '2026-05-01 20:08:55'),
(24, 32, 2, NULL, NULL, NULL, 'available', NULL, NULL, NULL, '2026-05-05 19:54:11', '2026-05-05 19:54:11');

-- --------------------------------------------------------

--
-- Structure de la table `employee_assignments`
--

CREATE TABLE `employee_assignments` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `department_id` int(11) NOT NULL,
  `position` varchar(100) DEFAULT NULL,
  `start_date` date NOT NULL,
  `end_date` date DEFAULT NULL,
  `is_primary` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `employee_assignments`
--

INSERT INTO `employee_assignments` (`id`, `user_id`, `department_id`, `position`, `start_date`, `end_date`, `is_primary`, `created_at`) VALUES
(1, 4, 2, 'medcin', '2026-04-23', '2026-04-23', 0, '2026-04-22 23:35:56'),
(2, 4, 2, 'medcin', '2026-03-06', '2026-04-18', 1, '2026-04-22 23:36:31'),
(3, 2, 1, 'Médecin Chef', '2024-01-01', NULL, 1, '2026-04-23 01:30:04'),
(4, 4, 4, 'Infirmier Principal', '2024-01-01', NULL, 1, '2026-04-23 01:30:04'),
(5, 7, 5, 'Secrétaire Médicale', '2024-01-01', NULL, 1, '2026-04-23 01:30:04'),
(6, 20, 2, 'Cardiologue Senior', '2024-01-01', NULL, 1, '2026-04-23 01:30:04'),
(7, 21, 3, 'Pédiatre', '2024-01-01', NULL, 1, '2026-04-23 01:30:04'),
(8, 25, 17, 'tra', '2026-04-23', '2026-04-30', 1, '2026-04-23 21:40:16');

-- --------------------------------------------------------

--
-- Structure de la table `employee_contracts`
--

CREATE TABLE `employee_contracts` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `contract_type` enum('CDI','CDD','stage','freelance') DEFAULT 'CDI',
  `start_date` date NOT NULL,
  `end_date` date DEFAULT NULL,
  `salary` decimal(10,2) DEFAULT NULL,
  `position` varchar(255) DEFAULT NULL,
  `department` varchar(255) DEFAULT NULL,
  `documents_url` varchar(500) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `employee_contracts`
--

INSERT INTO `employee_contracts` (`id`, `user_id`, `contract_type`, `start_date`, `end_date`, `salary`, `position`, `department`, `documents_url`, `created_at`) VALUES
(1, 19, 'stage', '2026-04-23', '2026-04-30', 60000.00, 'infemier', 'jsp', NULL, '2026-04-23 00:13:15'),
(2, 2, 'CDI', '2020-01-01', NULL, 150000.00, 'Médecin Chef', 'Médecine Générale', NULL, '2026-04-23 01:30:15'),
(3, 4, 'CDI', '2021-06-01', NULL, 80000.00, 'Infirmier Principal', 'Urgences', NULL, '2026-04-23 01:30:15'),
(4, 7, 'CDI', '2022-03-01', NULL, 55000.00, 'Secrétaire Médicale', 'Administration', NULL, '2026-04-23 01:30:15'),
(5, 20, 'CDI', '2019-01-01', NULL, 200000.00, 'Cardiologue Senior', 'Cardiologie', NULL, '2026-04-23 01:30:15'),
(6, 21, 'CDD', '2024-01-01', '2024-12-31', 120000.00, 'Pédiatre', 'Pédiatrie', NULL, '2026-04-23 01:30:15');

-- --------------------------------------------------------

--
-- Structure de la table `employee_documents`
--

CREATE TABLE `employee_documents` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `document_type` enum('cv','diploma','certificate','id_card','contract','other') DEFAULT 'other',
  `document_name` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_size` int(11) DEFAULT NULL,
  `mime_type` varchar(100) DEFAULT NULL,
  `uploaded_by` int(11) DEFAULT NULL,
  `uploaded_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `employee_documents`
--

INSERT INTO `employee_documents` (`id`, `user_id`, `document_type`, `document_name`, `file_path`, `file_size`, `mime_type`, `uploaded_by`, `uploaded_at`) VALUES
(1, 17, 'other', 'Lab1.7.docx', '../uploads/rh_documents/doc_17_1776902373_3349.docx', 20181, '0', 18, '2026-04-22 23:59:33');

-- --------------------------------------------------------

--
-- Structure de la table `external_labs`
--

CREATE TABLE `external_labs` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `contact_person` varchar(255) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `api_endpoint` varchar(500) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `external_labs`
--

INSERT INTO `external_labs` (`id`, `name`, `contact_person`, `phone`, `email`, `address`, `api_endpoint`, `is_active`) VALUES
(1, 'BioLab Paris', 'Dr. Martin', '0145678901', 'contact@biolab-paris.fr', '123 Rue de la Santé, 75010 Paris', NULL, 1),
(2, 'MediTest Lyon', 'Sophie Bernard', '0478901234', 'contact@meditest-lyon.fr', '45 Avenue des Analyses, 69003 Lyon', NULL, 1),
(3, 'Eurofins Marseille', 'Pierre Durand', '0491234567', 'marseille@eurofins.fr', '78 Boulevard Scientifique, 13008 Marseille', NULL, 1),
(4, 'Labo Sud Toulouse', 'Isabelle Petit', '0567890123', 'contact@labosud-toulouse.fr', '12 Rue des Biologistes, 31000 Toulouse', NULL, 1),
(5, 'NordLab Lille', 'Thomas Leroy', '0321456789', 'contact@nordlab-lille.fr', '9 Place du Laboratoire, 59000 Lille', NULL, 1);

-- --------------------------------------------------------

--
-- Structure de la table `financial_reports`
--

CREATE TABLE `financial_reports` (
  `id` int(11) NOT NULL,
  `report_type` enum('daily','weekly','monthly','yearly') NOT NULL,
  `report_date` date NOT NULL,
  `total_revenue` decimal(12,2) DEFAULT NULL,
  `total_expenses` decimal(12,2) DEFAULT NULL,
  `net_profit` decimal(12,2) DEFAULT NULL,
  `details` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`details`)),
  `generated_by` int(11) DEFAULT NULL,
  `generated_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `financial_reports`
--

INSERT INTO `financial_reports` (`id`, `report_type`, `report_date`, `total_revenue`, `total_expenses`, `net_profit`, `details`, `generated_by`, `generated_at`) VALUES
(1, 'monthly', '2026-04-01', 150000.00, 85000.00, 65000.00, NULL, 17, '2026-04-23 01:34:45'),
(2, 'weekly', '2026-04-16', 35000.00, 20000.00, 15000.00, NULL, 17, '2026-04-23 01:34:45');

-- --------------------------------------------------------

--
-- Structure de la table `food_stock`
--

CREATE TABLE `food_stock` (
  `id` int(11) NOT NULL,
  `item_name` varchar(255) NOT NULL,
  `category` varchar(100) DEFAULT NULL COMMENT 'légumes, protéines, produits laitiers...',
  `quantity` decimal(8,2) NOT NULL DEFAULT 0.00,
  `unit` varchar(20) DEFAULT 'kg',
  `expiry_date` date DEFAULT NULL,
  `threshold_alert` decimal(8,2) DEFAULT 5.00,
  `supplier_id` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `hospitalizations`
--

CREATE TABLE `hospitalizations` (
  `id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `doctor_id` int(11) NOT NULL,
  `bed_id` int(11) DEFAULT NULL,
  `admission_date` datetime NOT NULL DEFAULT current_timestamp(),
  `discharge_date` datetime DEFAULT NULL,
  `reason` text DEFAULT NULL COMMENT 'Motif d''hospitalisation',
  `diagnosis_entry` text DEFAULT NULL COMMENT 'Diagnostic à l''entrée',
  `status` enum('admitted','discharged','transferred') DEFAULT 'admitted',
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `insurance`
--

CREATE TABLE `insurance` (
  `id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `insurance_company` varchar(255) NOT NULL,
  `policy_number` varchar(100) NOT NULL,
  `coverage_percentage` decimal(5,2) DEFAULT 100.00,
  `valid_from` date DEFAULT NULL,
  `valid_until` date DEFAULT NULL,
  `status` enum('active','expired','suspended') DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `insurance_claims`
--

CREATE TABLE `insurance_claims` (
  `id` int(11) NOT NULL,
  `insurance_id` int(11) NOT NULL,
  `invoice_id` int(11) NOT NULL,
  `claim_amount` decimal(10,2) NOT NULL,
  `approved_amount` decimal(10,2) DEFAULT NULL,
  `status` enum('pending','approved','rejected','partially_approved') DEFAULT 'pending',
  `submission_date` date DEFAULT NULL,
  `response_date` date DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `invoices`
--

CREATE TABLE `invoices` (
  `id` int(11) NOT NULL,
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
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `invoices`
--

INSERT INTO `invoices` (`id`, `invoice_number`, `patient_id`, `appointment_id`, `hospitalization_id`, `total_amount`, `paid_amount`, `status`, `generated_date`, `due_date`, `notes`, `created_at`, `updated_at`) VALUES
(1, 'INV-20260412-6678', 1, NULL, NULL, 50.00, 50.00, 'paid', '2026-04-12', NULL, 'urgence', '2026-04-12 20:45:57', '2026-04-12 20:45:57'),
(2, 'INV-20260412-2926', 2, NULL, NULL, 50.00, 50.00, 'paid', '2026-04-12', NULL, 'suivi', '2026-04-12 20:49:55', '2026-04-12 20:49:55'),
(3, 'INV-20260423-5435', 2, NULL, NULL, 7500.00, 7500.00, 'paid', '2026-04-23', NULL, '', '2026-04-23 00:54:33', '2026-04-23 00:54:55'),
(8, 'INV-20260001', 1, NULL, NULL, 1500.00, 1500.00, 'paid', '2026-04-18', NULL, 'Consultation générale', '2026-04-23 01:32:15', '2026-04-23 01:32:15'),
(9, 'INV-20260002', 2, NULL, NULL, 2500.00, 2500.00, 'paid', '2026-04-20', NULL, 'Consultation cardiologue', '2026-04-23 01:32:15', '2026-04-23 01:32:15'),
(10, 'INV-20260003', 1, NULL, NULL, 3500.00, 3500.00, 'paid', '2026-04-23', NULL, 'Échographie + Consultation', '2026-04-23 01:32:15', '2026-04-23 20:12:43'),
(16, 'INV-20260423-4012', 1, NULL, NULL, 10090.00, 1000.00, 'unpaid', '2026-04-23', NULL, 'tese', '2026-04-23 20:17:38', '2026-04-23 20:23:38');

-- --------------------------------------------------------

--
-- Structure de la table `invoice_items`
--

CREATE TABLE `invoice_items` (
  `id` int(11) NOT NULL,
  `invoice_id` int(11) NOT NULL,
  `description` varchar(255) NOT NULL,
  `quantity` int(11) DEFAULT 1,
  `unit_price` decimal(10,2) NOT NULL,
  `amount` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `invoice_items`
--

INSERT INTO `invoice_items` (`id`, `invoice_id`, `description`, `quantity`, `unit_price`, `amount`) VALUES
(1, 1, 'Consultation - Dr. Dr. Test Doctor', 1, 50.00, 50.00),
(2, 2, 'Consultation - Dr. Dr. Test Doctor', 1, 50.00, 50.00),
(3, 3, 'Consultation spécialiste', 1, 2500.00, 2500.00),
(4, 3, 'Petite chirurgie', 1, 5000.00, 5000.00),
(5, 16, 'Consultation générale', 1, 9000.00, 9000.00),
(6, 16, 'Vaccination', 1, 1000.00, 1000.00),
(7, 16, 'trai', 1, 90.00, 90.00);

-- --------------------------------------------------------

--
-- Structure de la table `lab_inventory`
--

CREATE TABLE `lab_inventory` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `category` varchar(100) DEFAULT NULL,
  `quantity` int(11) NOT NULL DEFAULT 0,
  `unit` varchar(50) DEFAULT NULL,
  `threshold` int(11) DEFAULT 10,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `lab_inventory_movements`
--

CREATE TABLE `lab_inventory_movements` (
  `id` int(11) NOT NULL,
  `item_id` int(11) DEFAULT NULL,
  `type` enum('IN','OUT') DEFAULT NULL,
  `quantity` int(11) DEFAULT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `lab_stock`
--

CREATE TABLE `lab_stock` (
  `id` int(11) NOT NULL,
  `item_name` varchar(200) NOT NULL,
  `category` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `quantity` decimal(10,2) NOT NULL DEFAULT 0.00,
  `unit` varchar(50) DEFAULT NULL,
  `threshold_alert` decimal(10,2) DEFAULT 0.00,
  `location` varchar(200) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `lab_stock`
--

INSERT INTO `lab_stock` (`id`, `item_name`, `category`, `description`, `quantity`, `unit`, `threshold_alert`, `location`, `created_at`, `updated_at`) VALUES
(1, 'Tubes EDTA (violet)', 'Consommables', NULL, 500.00, 'pièces', 50.00, 'Armoire A1', '2026-04-21 22:01:15', '2026-04-21 22:01:15'),
(2, 'Tubes sec (rouge)', 'Consommables', NULL, 300.00, 'pièces', 30.00, 'Armoire A1', '2026-04-21 22:01:15', '2026-04-21 22:01:15'),
(3, 'Aiguilles 21G', 'Consommables', NULL, 199.00, 'pièces', 20.00, 'Armoire A2', '2026-04-21 22:01:15', '2026-04-21 22:37:50'),
(4, 'Alcool 70°', 'Désinfectant', NULL, 10.00, 'litres', 2.00, 'Réserve', '2026-04-21 22:01:15', '2026-04-21 22:01:15'),
(5, 'Gants latex M', 'Consommables', NULL, 50.00, 'boîtes', 5.00, 'Armoire B1', '2026-04-21 22:01:15', '2026-04-21 22:01:15'),
(6, 'Réactif glycémie', 'Réactifs', NULL, 20.00, 'flacons', 3.00, 'Réfrigérateur 1', '2026-04-21 22:01:15', '2026-04-21 22:01:15'),
(7, 'Réactif NFS', 'Réactifs', NULL, 15.00, 'flacons', 2.00, 'Réfrigérateur 1', '2026-04-21 22:01:15', '2026-04-21 22:01:15'),
(8, 'Bandelettes urine', 'Consommables', NULL, 100.00, 'pièces', 10.00, 'Armoire B2', '2026-04-21 22:01:15', '2026-04-21 22:01:15'),
(9, 'Pipettes Pasteur', 'Consommables', NULL, 300.00, 'pièces', 50.00, 'Armoire A3', '2026-04-21 22:01:15', '2026-04-21 22:01:15'),
(10, 'Lames porte-objet', 'Consommables', NULL, 200.00, 'pièces', 25.00, 'Armoire A3', '2026-04-21 22:01:15', '2026-04-21 22:01:15');

-- --------------------------------------------------------

--
-- Structure de la table `lab_stock_movements`
--

CREATE TABLE `lab_stock_movements` (
  `id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `operation` enum('add','remove') NOT NULL,
  `quantity` decimal(10,2) NOT NULL,
  `reason` varchar(300) DEFAULT NULL,
  `performed_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `lab_stock_movements`
--

INSERT INTO `lab_stock_movements` (`id`, `item_id`, `operation`, `quantity`, `reason`, `performed_by`, `created_at`) VALUES
(1, 3, 'remove', 1.00, '', 6, '2026-04-21 22:37:50');

-- --------------------------------------------------------

--
-- Structure de la table `lab_tests`
--

CREATE TABLE `lab_tests` (
  `id` int(11) NOT NULL,
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
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `lab_test_consumables`
--

CREATE TABLE `lab_test_consumables` (
  `id` int(11) NOT NULL,
  `test_id` int(11) DEFAULT NULL,
  `item_id` int(11) DEFAULT NULL,
  `quantity_used` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `lab_test_consumables_required`
--

CREATE TABLE `lab_test_consumables_required` (
  `id` int(11) NOT NULL,
  `test_name` varchar(255) NOT NULL,
  `item_id` int(11) NOT NULL,
  `quantity_required` decimal(10,2) NOT NULL DEFAULT 1.00,
  `is_auto_deduct` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1=Auto, 0=Manuel'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `lab_test_consumables_required`
--

INSERT INTO `lab_test_consumables_required` (`id`, `test_name`, `item_id`, `quantity_required`, `is_auto_deduct`) VALUES
(3, 'Hémogramme (NFS)', 1, 1.00, 1),
(4, 'Hémogramme (NFS)', 3, 1.00, 1),
(5, 'Glycémie', 2, 1.00, 1),
(6, 'Glycémie', 3, 1.00, 1),
(7, 'Groupe sanguin + RAI', 1, 1.00, 1),
(8, 'Groupe sanguin + RAI', 3, 1.00, 1),
(9, 'Créatinine', 2, 1.00, 1),
(10, 'Créatinine', 3, 1.00, 1),
(11, 'Cholestérol total', 2, 1.00, 1),
(12, 'Cholestérol total', 3, 1.00, 1),
(13, 'Hémogramme (NFS)', 4, 2.00, 0),
(14, 'Hémogramme (NFS)', 5, 1.00, 0),
(15, 'Glycémie', 4, 2.00, 0),
(16, 'Glycémie', 5, 1.00, 0);

-- --------------------------------------------------------

--
-- Structure de la table `leave_requests`
--

CREATE TABLE `leave_requests` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `leave_type` enum('annual','sick','unpaid','maternity','other') DEFAULT 'annual',
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `reason` text DEFAULT NULL,
  `status` enum('pending','approved','rejected','cancelled') DEFAULT 'pending',
  `approved_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `leave_requests`
--

INSERT INTO `leave_requests` (`id`, `user_id`, `leave_type`, `start_date`, `end_date`, `reason`, `status`, `approved_by`, `created_at`) VALUES
(1, 4, 'annual', '2026-04-28', '2026-05-03', 'Vacances familiales', 'approved', 18, '2026-04-23 01:29:43'),
(2, 2, 'sick', '2026-04-24', '2026-04-25', 'Grippe', 'approved', NULL, '2026-04-23 01:29:43'),
(3, 7, 'unpaid', '2026-05-08', '2026-05-13', 'Affaires personnelles', 'pending', NULL, '2026-04-23 01:29:43'),
(4, 20, 'annual', '2026-05-23', '2026-05-28', 'Congés annuels', 'approved', NULL, '2026-04-23 01:29:43');

-- --------------------------------------------------------

--
-- Structure de la table `login_logs`
--

CREATE TABLE `login_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `email` varchar(255) NOT NULL,
  `login_time` datetime DEFAULT current_timestamp(),
  `ip_address` varchar(45) DEFAULT NULL,
  `success` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `login_logs`
--

INSERT INTO `login_logs` (`id`, `user_id`, `email`, `login_time`, `ip_address`, `success`) VALUES
(1, 10, 'amanilakehal47@gmail.com', '2026-04-11 22:20:11', '::1', 1),
(2, 11, 'amanilakehal4777@gmail.com', '2026-04-11 22:20:36', '::1', 1),
(3, 12, 'test1@gmail.com', '2026-04-11 22:22:05', '::1', 1),
(4, 10, 'amanilakehal47@gmail.com', '2026-04-11 22:22:15', '::1', 1),
(5, 1, 'admin@edoc.com', '2026-04-11 22:22:30', '::1', 0),
(6, 1, 'admin@edoc.com', '2026-04-11 22:22:35', '::1', 0),
(7, 1, 'admin@edoc.com', '2026-04-11 22:22:40', '::1', 0),
(8, 11, 'amanilakehal4777@gmail.com', '2026-04-11 22:22:57', '::1', 0),
(9, 11, 'amanilakehal4777@gmail.com', '2026-04-11 22:23:00', '::1', 1),
(10, 11, 'amanilakehal4777@gmail.com', '2026-04-11 22:23:46', '::1', 0),
(11, 11, 'amanilakehal4777@gmail.com', '2026-04-11 22:23:51', '::1', 0),
(12, 11, 'amanilakehal4777@gmail.com', '2026-04-11 22:23:54', '::1', 1),
(13, 1, 'admin@edoc.com', '2026-04-11 22:59:18', '::1', 0),
(14, 11, 'amanilakehal4777@gmail.com', '2026-04-11 23:02:36', '::1', 1),
(15, 11, 'amanilakehal4777@gmail.com', '2026-04-12 19:22:42', '::1', 1),
(16, 11, 'amanilakehal4777@gmail.com', '2026-04-12 19:29:16', '::1', 1),
(17, 11, 'amanilakehal4777@gmail.com', '2026-04-12 19:31:48', '::1', 1),
(18, 10, 'amanilakehal47@gmail.com', '2026-04-12 20:00:38', '::1', 0),
(19, 10, 'amanilakehal47@gmail.com', '2026-04-12 20:00:42', '::1', 1),
(20, 10, 'amanilakehal47@gmail.com', '2026-04-12 20:01:14', '::1', 1),
(21, 11, 'amanilakehal4777@gmail.com', '2026-04-12 20:01:31', '::1', 1),
(22, 15, '1@gmail.com', '2026-04-12 20:44:08', '::1', 1),
(23, 11, 'amanilakehal4777@gmail.com', '2026-04-12 20:48:18', '::1', 0),
(24, 11, 'amanilakehal4777@gmail.com', '2026-04-12 20:48:22', '::1', 1),
(25, 15, '1@gmail.com', '2026-04-12 20:49:18', '::1', 1),
(26, 11, 'amanilakehal4777@gmail.com', '2026-04-12 20:50:27', '::1', 1),
(27, 15, '1@gmail.com', '2026-04-13 12:12:16', '::1', 1),
(28, 11, 'amanilakehal4777@gmail.com', '2026-04-13 12:12:45', '::1', 0),
(29, 11, 'amanilakehal4777@gmail.com', '2026-04-13 12:12:52', '::1', 1),
(30, 15, '1@gmail.com', '2026-04-13 12:13:43', '::1', 1),
(31, 11, 'amanilakehal4777@gmail.com', '2026-04-14 10:03:33', '::1', 1),
(32, 15, '1@gmail.com', '2026-04-14 10:07:16', '::1', 1),
(33, 11, 'amanilakehal4777@gmail.com', '2026-04-14 13:21:16', '::1', 1),
(34, 11, 'amanilakehal4777@gmail.com', '2026-04-28 13:43:48', '::1', 1),
(35, 18, 'linou@local.dz', '2026-04-28 13:44:48', '::1', 1),
(36, 15, '1@gmail.com', '2026-04-28 13:49:15', '::1', 1),
(37, 15, '1@gmail.com', '2026-04-28 13:51:05', '::1', 1),
(38, 11, 'amanilakehal4777@gmail.com', '2026-04-28 14:03:47', '::1', 1),
(39, 11, 'amanilakehal4777@gmail.com', '2026-04-28 14:04:05', '::1', 1),
(40, 11, 'amanilakehal4777@gmail.com', '2026-04-28 14:25:01', '::1', 1),
(41, 26, 'pharm@local.dz', '2026-04-28 14:26:22', '::1', 1),
(42, 18, 'rh@local.dz', '2026-04-28 14:27:19', '::1', 1),
(43, 17, 'djam@local.dz', '2026-04-28 14:27:52', '::1', 1),
(44, 11, 'amanilakehal4777@gmail.com', '2026-04-28 14:28:23', '::1', 1),
(45, 27, 'gestMoy@local.dz', '2026-04-28 14:31:34', '::1', 1),
(46, 15, '1@gmail.com', '2026-04-28 14:32:18', '::1', 1),
(47, 11, 'amanilakehal4777@gmail.com', '2026-04-28 14:33:28', '::1', 1),
(48, NULL, 'labo@gmail.com', '2026-04-28 14:34:54', '::1', 0),
(49, 28, 'labo@local.dz', '2026-04-28 14:35:06', '::1', 1),
(50, 27, 'gestMoy@local.dz', '2026-04-28 14:51:13', '::1', 1),
(51, 11, 'amanilakehal4777@gmail.com', '2026-04-28 14:53:15', '::1', 1),
(52, 11, 'amanilakehal4777@gmail.com', '2026-04-28 15:31:25', '::1', 1),
(53, 26, 'pharm@local.dz', '2026-04-28 15:32:04', '::1', 1),
(54, 18, 'rh@local.dz', '2026-04-28 15:34:53', '::1', 1),
(55, 15, '1@gmail.com', '2026-04-28 15:37:00', '::1', 1),
(56, 27, 'gestMoy@local.dz', '2026-04-30 21:13:11', '::1', 1),
(57, 11, 'amanilakehal47@gmail.com', '2026-05-01 12:10:19', '::1', 1),
(58, 11, 'amanilakehal47@gmail.com', '2026-05-01 12:15:40', '::1', 1),
(59, 11, 'amanilakehal47@gmail.com', '2026-05-01 12:15:52', '::1', 1),
(60, 15, '1@gmail.com', '2026-05-01 12:15:58', '::1', 1),
(61, 11, 'amanilakehal47@gmail.com', '2026-05-01 12:18:18', '::1', 1),
(62, 11, 'amanilakehal47@gmail.com', '2026-05-01 12:29:59', '::1', 1),
(63, 11, 'amanilakehal47@gmail.com', '2026-05-01 12:33:39', '::1', 1),
(64, 11, 'amanilakehal47@gmail.com', '2026-05-01 12:56:11', '::1', 1),
(65, 11, 'amanilakehal47@gmail.com', '2026-05-01 12:56:58', '::1', 1),
(66, 11, 'amanilakehal47@gmail.com', '2026-05-01 12:57:16', '::1', 1),
(67, 15, '1@gmail.com', '2026-05-01 13:11:41', '::1', 1),
(68, 15, '1@gmail.com', '2026-05-01 13:11:54', '::1', 0),
(69, 15, '1@gmail.com', '2026-05-01 13:11:57', '::1', 0),
(70, 15, '1@gmail.com', '2026-05-01 13:12:00', '::1', 0),
(71, 15, '1@gmail.com', '2026-05-01 13:12:02', '::1', 0),
(72, 15, '1@gmail.com', '2026-05-01 13:12:06', '::1', 0),
(73, 15, '1@gmail.com', '2026-05-01 13:12:08', '::1', 0),
(74, 11, 'amanilakehal47@gmail.com', '2026-05-01 13:12:22', '::1', 1),
(75, 15, '1@gmail.com', '2026-05-01 13:20:10', '::1', 0),
(76, 11, 'amanilakehal47@gmail.com', '2026-05-01 13:20:32', '::1', 1),
(77, 11, 'amanilakehal47@gmail.com', '2026-05-01 13:20:37', '::1', 1),
(78, 27, 'gestMoy@local.dz', '2026-05-01 13:33:04', '::1', 1),
(79, 11, 'amanilakehal47@gmail.com', '2026-05-01 14:16:56', '::1', 1),
(80, 11, 'amanilakehal47@gmail.com', '2026-05-01 14:17:05', '::1', 1),
(81, 29, 'med@local.dz', '2026-05-01 14:18:07', '::1', 1),
(82, 29, 'med@local.dz', '2026-05-01 14:20:17', '::1', 1),
(83, 29, 'med@local.dz', '2026-05-01 14:25:35', '::1', 1),
(84, 29, 'med@local.dz', '2026-05-01 14:30:56', '::1', 1),
(85, 27, 'gestMoy@local.dz', '2026-05-01 18:49:41', '::1', 1),
(86, 27, 'gestMoy@local.dz', '2026-05-01 18:50:17', '::1', 1),
(87, 11, 'amanilakehal47@gmail.com', '2026-05-01 19:04:18', '::1', 1),
(88, 27, 'gestMoy@local.dz', '2026-05-01 19:42:36', '::1', 1),
(89, 11, 'amanilakehal47@gmail.com', '2026-05-01 20:05:38', '::1', 1),
(90, 27, 'gestMoy@local.dz', '2026-05-01 20:07:13', '::1', 1),
(91, 11, 'amanilakehal47@gmail.com', '2026-05-01 20:08:18', '::1', 1),
(92, 27, 'gestMoy@local.dz', '2026-05-01 20:09:11', '::1', 1),
(93, 27, 'gestMoy@local.dz', '2026-05-01 22:42:54', '::1', 1),
(94, 27, 'gestMoy@local.dz', '2026-05-01 22:43:00', '::1', 1),
(95, 17, 'djam@med-unity.dz', '2026-05-01 23:29:04', '::1', 1),
(96, 18, 'rh@med-unity.dz', '2026-05-01 23:30:37', '::1', 1),
(97, 18, 'rh@med-unity.dz', '2026-05-01 23:35:43', '::1', 1),
(98, 11, 'amanilakehal47@med-unity.dz', '2026-05-01 23:51:40', '::1', 1),
(99, 11, 'amanilakehal47@med-unity.dz', '2026-05-01 23:52:29', '::1', 1),
(100, 11, 'amanilakehal47@med-unity.dz', '2026-05-01 23:52:35', '::1', 1),
(101, 11, 'amanilakehal47@med-unity.dz', '2026-05-01 23:52:40', '::1', 1),
(102, 11, 'amanilakehal47@med-unity.dz', '2026-05-01 23:52:52', '::1', 1),
(103, 27, 'gestMoy@med-unity.dz', '2026-05-01 23:53:10', '::1', 1),
(104, NULL, 'amanilakehal47@gmail.com', '2026-05-05 19:31:47', '::1', 0),
(105, NULL, 'amanilakehal47@gmail.com', '2026-05-05 19:31:57', '::1', 0),
(106, NULL, 'labo1@gmail.com', '2026-05-05 19:32:20', '::1', 0),
(107, NULL, 'labo1@gmail.com', '2026-05-05 19:32:23', '::1', 0),
(108, NULL, 'amanilakehal47@gmail.com', '2026-05-05 19:32:46', '::1', 0),
(109, 11, 'amanilakehal47@med-unity.dz', '2026-05-05 19:33:55', '::1', 0),
(110, 11, 'amanilakehal47@med-unity.dz', '2026-05-05 19:34:08', '::1', 0),
(111, NULL, 'labo1@med-unity.dz', '2026-05-05 19:36:06', '::1', 0),
(112, 6, 'laborantin@med-unity.dz', '2026-05-05 19:37:34', '::1', 0),
(113, NULL, 'amanilakehal47@gmail.com', '2026-05-05 19:41:06', '::1', 0),
(114, 11, 'amanilakehal47@med-unity.dz', '2026-05-05 19:41:30', '::1', 0),
(115, 11, 'amanilakehal47@med-unity.dz', '2026-05-05 19:42:03', '::1', 0),
(116, 11, 'amanilakehal47@med-unity.dz', '2026-05-05 19:44:02', '::1', 0),
(117, 27, 'gestMoy@med-unity.dz', '2026-05-05 19:49:44', '::1', 1),
(118, 11, 'amanilakehal47@med-unity.dz', '2026-05-05 19:50:47', '::1', 1),
(119, 11, 'amanilakehal47@med-unity.dz', '2026-05-05 19:52:00', '::1', 1),
(120, 32, 'douaa.zaatout@med-unity.dz', '2026-05-05 19:55:18', '::1', 1),
(121, 15, '1@med-unity.dz', '2026-05-05 20:14:35', '::1', 1),
(122, 26, 'pharm@med-unity.dz', '2026-05-05 20:19:40', '::1', 1),
(123, 28, 'labo@med-unity.dz', '2026-05-05 20:21:09', '::1', 1),
(124, NULL, 'labo1@gmail.com', '2026-05-05 20:25:24', '::1', 0),
(125, NULL, 'labo@mrd-unity.dz', '2026-05-05 20:25:58', '::1', 0),
(126, 28, 'labo@med-unity.dz', '2026-05-05 20:26:27', '::1', 1);

-- --------------------------------------------------------

--
-- Structure de la table `logs`
--

CREATE TABLE `logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(255) NOT NULL,
  `entity_type` varchar(50) DEFAULT NULL COMMENT 'patient, prescription, invoice...',
  `entity_id` int(11) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `details` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`details`)),
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `logs`
--

INSERT INTO `logs` (`id`, `user_id`, `action`, `entity_type`, `entity_id`, `ip_address`, `user_agent`, `details`, `created_at`) VALUES
(1, 1, 'MANUAL_BACKUP', 'full', NULL, '::1', NULL, NULL, '2026-04-12 19:28:44'),
(2, 1, 'CREATE_USER', 'users', 13, '::1', NULL, NULL, '2026-04-12 19:47:18'),
(3, 1, 'CREATE_USER', 'users', 14, '::1', NULL, NULL, '2026-04-12 19:49:10'),
(4, 1, 'CREATE_USER', 'users', 15, '::1', NULL, NULL, '2026-04-12 20:43:56'),
(5, 1, 'MANUAL_BACKUP', 'full', NULL, '::1', NULL, NULL, '2026-04-14 10:06:58'),
(6, 1, 'CREATE_USER', 'users', 17, '::1', NULL, NULL, '2026-04-21 16:10:59'),
(7, 1, 'CREATE_USER', 'users', 18, '::1', NULL, NULL, '2026-04-21 16:12:08'),
(8, 18, 'APPROVE_LEAVE', 'leave_request', 3, NULL, NULL, NULL, '2026-04-23 01:34:32'),
(9, 17, 'RECORD_PAYMENT', 'invoice', 4, NULL, NULL, NULL, '2026-04-23 01:34:32'),
(10, 1, 'CREATE_USER', 'users', 20, NULL, NULL, NULL, '2026-04-22 01:34:32'),
(11, 1, 'CREATE_USER', 'users', 26, '::1', NULL, NULL, '2026-04-23 01:42:16'),
(12, 1, 'CREATE_USER', 'users', 27, '::1', NULL, NULL, '2026-04-28 14:31:10'),
(13, 1, 'CREATE_USER', 'users', 28, '::1', NULL, NULL, '2026-04-28 14:34:12'),
(14, 1, 'EDIT_USER', 'users', 11, '::1', NULL, NULL, '2026-05-01 12:30:17'),
(15, 1, 'EDIT_USER', 'users', 11, '::1', NULL, NULL, '2026-05-01 12:37:54'),
(16, 11, 'RESTORE_BACKUP', 'restore', 0, '::1', NULL, NULL, '2026-05-01 13:06:11'),
(17, 11, 'CREATE_USER', 'users', 29, '::1', NULL, NULL, '2026-05-01 14:17:49'),
(18, 1, 'CREATE_USER', 'users', 30, '::1', NULL, NULL, '2026-05-01 20:06:56'),
(19, 1, 'CREATE_DOCTOR', 'doctors', 22, '::1', NULL, NULL, '2026-05-01 20:06:56'),
(20, 1, 'CREATE_USER', 'users', 31, '::1', NULL, NULL, '2026-05-01 20:08:55'),
(21, 1, 'CREATE_DOCTOR', 'doctors', 23, '::1', NULL, NULL, '2026-05-01 20:08:55'),
(22, 11, 'CREATE_USER', 'users', 32, '::1', NULL, NULL, '2026-05-05 19:54:11'),
(23, 11, 'CREATE_DOCTOR', 'doctors', 24, '::1', NULL, NULL, '2026-05-05 19:54:11');

-- --------------------------------------------------------

--
-- Structure de la table `maintenance_requests`
--

CREATE TABLE `maintenance_requests` (
  `id` int(11) NOT NULL,
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
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `meal_plans`
--

CREATE TABLE `meal_plans` (
  `id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `meal_type` enum('breakfast','lunch','dinner','snack') NOT NULL,
  `diet_type` varchar(100) DEFAULT NULL COMMENT 'diabétique, sans sel, végétarien...',
  `food_items` text DEFAULT NULL,
  `allergens` text DEFAULT NULL,
  `served_date` date DEFAULT NULL,
  `status` enum('planned','prepared','served','cancelled') DEFAULT 'planned',
  `prepared_by` int(11) DEFAULT NULL COMMENT 'personnel_restauration user_id',
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `medical_records`
--

CREATE TABLE `medical_records` (
  `id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `doctor_id` int(11) NOT NULL,
  `appointment_id` int(11) DEFAULT NULL,
  `diagnosis` text DEFAULT NULL,
  `symptoms` text DEFAULT NULL,
  `clinical_notes` text DEFAULT NULL,
  `treatment_plan` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `medicines`
--

CREATE TABLE `medicines` (
  `id` int(11) NOT NULL,
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
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `medicines`
--

INSERT INTO `medicines` (`id`, `name`, `generic_name`, `interactions`, `is_generic`, `original_medicine_id`, `category`, `dosage_form`, `strength`, `quantity`, `unit`, `expiry_date`, `threshold_alert`, `purchase_price`, `selling_price`, `supplier_id`, `created_at`, `updated_at`) VALUES
(1, 'Doliprane 1000mg', 'Paracétamol', NULL, 0, NULL, 'Antalgique', 'comprimé', '1000mg', 500, 'boîte', '2025-12-31', 10, 50.00, 80.00, 1, '2026-04-23 01:33:57', '2026-04-23 01:33:57'),
(2, 'Augmentin 1g', 'Amoxicilline + Acide clavulanique', NULL, 0, NULL, 'Antibiotique', 'comprimé', '1g', 200, 'boîte', '2026-10-31', 10, 120.00, 180.00, 1, '2026-04-23 01:33:57', '2026-04-23 15:35:01'),
(3, 'Ventoline', 'Salbutamol', NULL, 0, NULL, 'Bronchodilatateur', 'inhalateur', '100mcg', 50, 'boîte', '2025-08-31', 10, 200.00, 300.00, 1, '2026-04-23 01:33:57', '2026-04-23 01:33:57'),
(4, 'first_try', 'dwa', NULL, 0, NULL, 'Antihistaminique', 'comprimé', '500mg', 20, 'gélule', '0000-00-00', 10, 270.00, 300.00, 1, '2026-04-23 19:43:26', '2026-04-28 14:51:51');

-- --------------------------------------------------------

--
-- Structure de la table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `type` varchar(50) DEFAULT 'info',
  `is_read` tinyint(1) DEFAULT 0,
  `link` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `title`, `message`, `type`, `is_read`, `link`, `created_at`) VALUES
(1, 18, 'Nouvelle demande de congé', 'Une nouvelle demande de congé est en attente de validation', 'info', 0, NULL, '2026-04-23 01:34:21'),
(2, 17, 'Facture payée', 'La facture INV-20260001 a été payée', 'success', 0, NULL, '2026-04-23 01:34:21'),
(3, 2, 'Rappel rendez-vous', 'Vous avez un rendez-vous dans 30 minutes', 'warning', 0, NULL, '2026-04-23 01:34:21');

-- --------------------------------------------------------

--
-- Structure de la table `operating_rooms`
--

CREATE TABLE `operating_rooms` (
  `id` int(11) NOT NULL,
  `room_number` varchar(50) NOT NULL COMMENT 'Numéro de la salle (ex: OR-01, Bloc A)',
  `room_name` varchar(100) NOT NULL COMMENT 'Nom de la salle (ex: Bloc A, Salle 1)',
  `room_type` enum('standard','urgences','cardiologie','neurochirurgie','orthopedie','pediatrique') DEFAULT 'standard' COMMENT 'Type de bloc',
  `status` enum('available','in_use','cleaning','maintenance','reserved','sterilization') DEFAULT 'available' COMMENT 'Statut',
  `equipment_available` text DEFAULT NULL COMMENT 'Équipements disponibles (séparés par des virgules)',
  `nurse_assigned` varchar(100) DEFAULT NULL COMMENT 'Infirmier(ère) assigné(e)',
  `anesthesiologist` varchar(100) DEFAULT NULL COMMENT 'Anesthésiste assigné(e)',
  `surgeon` varchar(100) DEFAULT NULL COMMENT 'Chirurgien assigné(e)',
  `current_patient_id` int(11) DEFAULT NULL COMMENT 'ID du patient actuel',
  `current_surgery_type` varchar(100) DEFAULT NULL COMMENT 'Type de chirurgie en cours',
  `scheduled_start` datetime DEFAULT NULL COMMENT 'Début programmé',
  `scheduled_end` datetime DEFAULT NULL COMMENT 'Fin programmée',
  `last_cleaning` datetime DEFAULT NULL COMMENT 'Dernier nettoyage',
  `next_sterilization` datetime DEFAULT NULL COMMENT 'Prochaine stérilisation',
  `notes` text DEFAULT NULL COMMENT 'Notes supplémentaires',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `operating_rooms`
--

INSERT INTO `operating_rooms` (`id`, `room_number`, `room_name`, `room_type`, `status`, `equipment_available`, `nurse_assigned`, `anesthesiologist`, `surgeon`, `current_patient_id`, `current_surgery_type`, `scheduled_start`, `scheduled_end`, `last_cleaning`, `next_sterilization`, `notes`, `created_at`, `updated_at`) VALUES
(1, 'OR-01', 'Bloc A - Salle 1', 'standard', 'available', 'Arthroscope, C-arm, Monitor, Anesthésie', 'Karim Boudiaf', 'an', 'ch', NULL, NULL, NULL, NULL, NULL, NULL, '', '2026-04-30 21:11:11', '2026-05-01 20:14:36'),
(2, 'OR-02', 'Bloc A - Salle 2', 'standard', 'sterilization', 'Arthroscope, C-arm, Monitor, Anesthésie, Navigation 3D', '', 'an', '', NULL, NULL, NULL, NULL, NULL, NULL, '', '2026-04-30 21:11:11', '2026-05-01 20:33:36'),
(3, 'OR-03', 'Bloc B - Urgences', 'urgences', 'maintenance', 'Matériel d\'urgence, Monitor, Défibrillateur', 'Karim Boudiaf', '', '', NULL, NULL, NULL, NULL, NULL, NULL, '', '2026-04-30 21:11:11', '2026-05-01 20:32:01'),
(4, 'OR-04', 'Bloc C - Cardiologie', 'cardiologie', 'maintenance', 'Pompe cardio-pulmonaire, Monitor cardiaque, Échographe', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-04-30 21:11:11', '2026-04-30 21:11:11'),
(5, 'OR-05', 'Bloc D - Neurochirurgie', 'neurochirurgie', 'reserved', 'Microscope opératoire, Neuronavigation, Scanner per-op', '', '', '', NULL, NULL, NULL, NULL, NULL, NULL, '', '2026-04-30 21:11:11', '2026-05-01 20:39:02');

-- --------------------------------------------------------

--
-- Structure de la table `orders`
--

CREATE TABLE `orders` (
  `id` int(11) NOT NULL,
  `requester_id` int(11) NOT NULL,
  `pharmacist_id` int(11) DEFAULT NULL,
  `item_type` enum('medicine','stock') NOT NULL,
  `item_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `status` enum('pending','approved','rejected','completed') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `patients`
--

CREATE TABLE `patients` (
  `id` int(11) NOT NULL,
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
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `patients`
--

INSERT INTO `patients` (`id`, `user_id`, `uhid`, `nic`, `dob`, `gender`, `blood_type`, `emergency_contact_name`, `emergency_contact_phone`, `allergies`, `medical_history`, `insurance_id`, `created_at`, `updated_at`) VALUES
(1, 3, 'UHID20240001', '0000000000', '2000-01-01', 'M', 'A+', 'Jean Dupont', '0600000000', 'Pénicilline', 'Hypertension artérielle', NULL, '2026-04-05 19:59:38', '2026-04-05 19:59:38'),
(2, 16, 'P202695483', '', '2011-11-11', 'F', '', '', '', '', '', NULL, '2026-04-12 20:49:43', '2026-04-12 20:49:43');

-- --------------------------------------------------------

--
-- Structure de la table `payments`
--

CREATE TABLE `payments` (
  `id` int(11) NOT NULL,
  `invoice_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `method` enum('cash','card','bank_transfer','insurance','check') DEFAULT 'cash',
  `transaction_id` varchar(100) DEFAULT NULL,
  `payment_date` datetime DEFAULT current_timestamp(),
  `received_by` int(11) DEFAULT NULL COMMENT 'user_id du comptable',
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `payments`
--

INSERT INTO `payments` (`id`, `invoice_id`, `amount`, `method`, `transaction_id`, `payment_date`, `received_by`, `notes`) VALUES
(1, 1, 50.00, 'card', NULL, '2026-04-12 20:45:57', 15, 'urgence'),
(2, 2, 50.00, 'check', NULL, '2026-04-12 20:49:55', 15, 'suivi'),
(3, 3, 7500.00, 'cash', NULL, '2026-04-23 00:54:55', 17, ''),
(4, 10, 3500.00, 'cash', NULL, '2026-04-23 20:12:43', 17, ''),
(5, 16, 1000.00, 'cash', NULL, '2026-04-23 20:23:38', 17, 'par facilite');

-- --------------------------------------------------------

--
-- Structure de la table `prescriptions`
--

CREATE TABLE `prescriptions` (
  `id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `doctor_id` int(11) NOT NULL,
  `appointment_id` int(11) DEFAULT NULL,
  `prescription_date` datetime DEFAULT current_timestamp(),
  `status` enum('draft','active','delivered','expired','cancelled') DEFAULT 'active',
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `prescription_items`
--

CREATE TABLE `prescription_items` (
  `id` int(11) NOT NULL,
  `prescription_id` int(11) NOT NULL,
  `medicine_id` int(11) NOT NULL,
  `dosage` varchar(100) NOT NULL COMMENT 'Ex: 1 comprimé matin et soir',
  `duration` varchar(100) DEFAULT NULL COMMENT 'Ex: 7 jours',
  `quantity` int(11) DEFAULT 1,
  `instructions` text DEFAULT NULL COMMENT 'Instructions particulières',
  `status` enum('pending','delivered','cancelled') DEFAULT 'pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `psychology_sessions`
--

CREATE TABLE `psychology_sessions` (
  `id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `psychologue_id` int(11) NOT NULL COMMENT 'user_id du psychologue',
  `session_date` datetime NOT NULL,
  `duration_minutes` int(11) DEFAULT 60,
  `session_type` enum('individuelle','groupe','suivi','evaluation') DEFAULT 'individuelle',
  `confidential_notes` text DEFAULT NULL COMMENT 'Notes confidentielles du psychologue',
  `progress` text DEFAULT NULL COMMENT 'Évolution psychologique observée',
  `next_session_date` datetime DEFAULT NULL,
  `status` enum('scheduled','completed','cancelled','no_show') DEFAULT 'scheduled',
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `purchase_orders`
--

CREATE TABLE `purchase_orders` (
  `id` int(11) NOT NULL,
  `order_number` varchar(50) NOT NULL,
  `supplier_id` int(11) NOT NULL,
  `order_date` date DEFAULT NULL,
  `delivery_date` date DEFAULT NULL,
  `status` enum('pending','confirmed','shipped','delivered','cancelled') DEFAULT 'pending',
  `total_amount` decimal(10,2) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `purchase_orders`
--

INSERT INTO `purchase_orders` (`id`, `order_number`, `supplier_id`, `order_date`, `delivery_date`, `status`, `total_amount`, `created_by`, `notes`) VALUES
(2, 'PO-20260423-0792', 4, '2026-04-23', NULL, 'confirmed', 180000.00, 17, 'commande'),
(3, 'PO-20260423-4155', 2, '2026-04-23', NULL, 'pending', 7900.00, 17, ' hkhjh');

-- --------------------------------------------------------

--
-- Structure de la table `purchase_order_items`
--

CREATE TABLE `purchase_order_items` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `item_name` varchar(255) NOT NULL,
  `item_type` enum('medicine','food','equipment','other') DEFAULT 'medicine',
  `item_id` int(11) DEFAULT NULL COMMENT 'medicine_id ou food_stock_id selon type',
  `quantity` decimal(10,2) NOT NULL,
  `unit` varchar(20) DEFAULT NULL,
  `unit_price` decimal(10,2) DEFAULT NULL,
  `total_price` decimal(10,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `radiology_requests`
--

CREATE TABLE `radiology_requests` (
  `id` int(11) NOT NULL,
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
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `rehabilitation_plans`
--

CREATE TABLE `rehabilitation_plans` (
  `id` int(11) NOT NULL,
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
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `rh_alerts`
--

CREATE TABLE `rh_alerts` (
  `id` int(11) NOT NULL,
  `alert_type` enum('contract_expiry','leave_request','absence','document_missing') DEFAULT 'leave_request',
  `user_id` int(11) DEFAULT NULL,
  `message` text NOT NULL,
  `reference_id` int(11) DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `rh_alerts`
--

INSERT INTO `rh_alerts` (`id`, `alert_type`, `user_id`, `message`, `reference_id`, `is_read`, `created_at`) VALUES
(1, 'contract_expiry', 21, 'Le contrat de Dr. Fatima Zohra expire dans 30 jours', 5, 1, '2026-04-23 01:34:09'),
(2, 'leave_request', 18, 'Nouvelle demande de congé en attente', 3, 0, '2026-04-23 01:34:09'),
(3, 'absence', 21, 'Dr. Fatima Zohra absente aujourd\'hui sans justificatif', NULL, 0, '2026-04-23 01:34:09');

-- --------------------------------------------------------

--
-- Structure de la table `roles`
--

CREATE TABLE `roles` (
  `id` int(11) NOT NULL,
  `role_name` varchar(50) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `permissions` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Permissions JSON par rôle - page 16' CHECK (json_valid(`permissions`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `roles`
--

INSERT INTO `roles` (`id`, `role_name`, `description`, `permissions`) VALUES
(1, 'admin_systeme', 'Administrateur du système - gestion utilisateurs, sécurité, sauvegardes', NULL),
(2, 'medecin', 'Médecin - diagnostic, prescriptions, suivi médical', NULL),
(3, 'patient', 'Patient - RDV, dossier médical, paiements', NULL),
(4, 'infirmier', 'Infirmier(ère) - soins quotidiens, constantes vitales', NULL),
(5, 'laborantin', 'Laborantin - analyses médicales, résultats', NULL),
(6, 'pharmacien', 'Pharmacien - gestion médicaments, stock, délivrance', NULL),
(7, 'receptionniste', 'Réceptionniste - accueil, enregistrement patients, RDV', NULL),
(8, 'gestionnaire_rh', 'Gestionnaire RH - personnel, salaires, congés', NULL),
(9, 'comptable', 'Comptable - facturation, paiements, rapports financiers', NULL),
(10, 'agent_nettoyage', 'Agent de nettoyage - entretien des chambres', NULL),
(11, 'gmg', 'gestionnaire de moyens generaux', NULL),
(12, 'personnel_restauration', 'Personnel de restauration - repas patients', NULL),
(13, 'fournisseur', 'Fournisseur - approvisionnement médicaments et produits', NULL),
(14, 'assurance', 'Compagnie d\'assurance - remboursements', NULL),
(15, 'laboratoire_externe', 'Laboratoire externe - analyses spécialisées', NULL),
(16, 'radiologue', 'Radiologue - imagerie médicale', NULL),
(17, 'kinesitherapeute', 'Kinésithérapeute - rééducation physique', NULL),
(18, 'psychologue', 'Psychologue - santé mentale', NULL),
(19, 'ambulancier', 'Ambulancier - transport des patients', NULL),
(20, 'agent_securite', 'Agent de sécurité - contrôle accès', NULL);

-- --------------------------------------------------------

--
-- Structure de la table `schedules`
--

CREATE TABLE `schedules` (
  `id` int(11) NOT NULL,
  `doctor_id` int(11) NOT NULL,
  `day_of_week` tinyint(1) NOT NULL COMMENT '0=Dimanche, 1=Lundi...',
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `type_session` enum('consultation','urgence','chirurgie','garde') DEFAULT 'consultation' COMMENT 'Type de session planifiée',
  `department_id` int(11) DEFAULT NULL COMMENT 'Département concerné',
  `is_emergency_slot` tinyint(1) DEFAULT 0 COMMENT 'Créneau réservé aux urgences',
  `status` enum('actif','annule','suspendu') DEFAULT 'actif' COMMENT 'Statut du planning',
  `max_duration_minutes` int(11) DEFAULT 30 COMMENT 'Durée maximale par patient',
  `max_patients` int(11) DEFAULT 10 COMMENT 'Nombre maximum de patients par session'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `security_incidents`
--

CREATE TABLE `security_incidents` (
  `id` int(11) NOT NULL,
  `agent_id` int(11) NOT NULL COMMENT 'user_id de l''agent de sécurité',
  `incident_type` enum('vol','violence','acces_non_autorise','intrusion','autre') NOT NULL,
  `location` varchar(255) DEFAULT NULL,
  `description` text NOT NULL,
  `severity` enum('low','medium','high','critical') DEFAULT 'medium',
  `status` enum('open','in_progress','resolved','escalated') DEFAULT 'open',
  `reported_at` datetime DEFAULT current_timestamp(),
  `resolved_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `sessions`
--

CREATE TABLE `sessions` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `token` varchar(500) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `otp_verified` tinyint(1) DEFAULT 0 COMMENT '2FA validé ou non',
  `expires_at` datetime NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `settings`
--

CREATE TABLE `settings` (
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `settings`
--

INSERT INTO `settings` (`setting_key`, `setting_value`, `updated_at`) VALUES
('address', '', '2026-04-11 20:36:54'),
('appointment_duration', '0', '2026-04-11 21:07:51'),
('contact_email', 'contact@edoc.com', '2026-04-11 20:36:54'),
('contact_phone', '', '2026-04-11 20:36:54'),
('site_name', 'eDocio', '2026-04-11 22:35:49'),
('timezone', 'Asia/Kolkata', '2026-04-11 20:36:54');

-- --------------------------------------------------------

--
-- Structure de la table `specialties`
--

CREATE TABLE `specialties` (
  `id` int(2) NOT NULL,
  `sname` varchar(100) NOT NULL,
  `description` varchar(255) DEFAULT NULL COMMENT 'Description optionnelle - image',
  `department_id` int(11) DEFAULT NULL COMMENT 'Lien département optionnel - image'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `specialties`
--

INSERT INTO `specialties` (`id`, `sname`, `description`, `department_id`) VALUES
(1, 'Accident and emergency medicine', NULL, NULL),
(2, 'Allergology', NULL, NULL),
(3, 'Anaesthetics', NULL, NULL),
(4, 'Biological hematology', NULL, NULL),
(5, 'Cardiology', NULL, NULL),
(6, 'Child psychiatry', NULL, NULL),
(7, 'Clinical biology', NULL, NULL),
(8, 'Clinical chemistry', NULL, NULL),
(9, 'Clinical neurophysiology', NULL, NULL),
(10, 'Clinical radiology', NULL, NULL),
(11, 'Dental, oral and maxillo-facial surgery', NULL, NULL),
(12, 'Dermato-venerology', NULL, NULL),
(13, 'Dermatology', NULL, NULL),
(14, 'Endocrinology', NULL, NULL),
(15, 'Gastro-enterologic surgery', NULL, NULL),
(16, 'Gastroenterology', NULL, NULL),
(17, 'General hematology', NULL, NULL),
(18, 'General Practice', NULL, NULL),
(19, 'General surgery', NULL, NULL),
(20, 'Geriatrics', NULL, NULL),
(21, 'Immunology', NULL, NULL),
(22, 'Infectious diseases', NULL, NULL),
(23, 'Internal medicine', NULL, NULL),
(24, 'Laboratory medicine', NULL, NULL),
(25, 'Maxillo-facial surgery', NULL, NULL),
(26, 'Microbiology', NULL, NULL),
(27, 'Nephrology', NULL, NULL),
(28, 'Neuro-psychiatry', NULL, NULL),
(29, 'Neurology', NULL, NULL),
(30, 'Neurosurgery', NULL, NULL),
(31, 'Nuclear medicine', NULL, NULL),
(32, 'Obstetrics and gynecology', NULL, NULL),
(33, 'Occupational medicine', NULL, NULL),
(34, 'Ophthalmology', NULL, NULL),
(35, 'Orthopaedics', NULL, NULL),
(36, 'Otorhinolaryngology', NULL, NULL),
(37, 'Paediatric surgery', NULL, NULL),
(38, 'Paediatrics', NULL, NULL),
(39, 'Pathology', NULL, NULL),
(40, 'Pharmacology', NULL, NULL),
(41, 'Physical medicine and rehabilitation', NULL, NULL),
(42, 'Plastic surgery', NULL, NULL),
(43, 'Podiatric Medicine', NULL, NULL),
(44, 'Podiatric Surgery', NULL, NULL),
(45, 'Psychiatry', NULL, NULL),
(46, 'Public health and Preventive Medicine', NULL, NULL),
(47, 'Radiology', NULL, NULL),
(48, 'Radiotherapy', NULL, NULL),
(49, 'Respiratory medicine', NULL, NULL),
(50, 'Rheumatology', NULL, NULL),
(51, 'Stomatology', NULL, NULL),
(52, 'Thoracic surgery', NULL, NULL),
(53, 'Tropical medicine', NULL, NULL),
(54, 'Urology', NULL, NULL),
(55, 'Vascular surgery', NULL, NULL),
(56, 'Venereology', NULL, NULL);

-- --------------------------------------------------------

--
-- Structure de la table `staff_planning`
--

CREATE TABLE `staff_planning` (
  `id` int(11) NOT NULL,
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
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `staff_planning`
--

INSERT INTO `staff_planning` (`id`, `user_id`, `planning_date`, `shift_start`, `shift_end`, `shift_type`, `department`, `is_on_call`, `status`, `replacement_id`, `notes`, `created_by`, `created_at`) VALUES
(1, 2, '2026-04-23', '08:00:00', '16:00:00', 'matin', NULL, 0, 'scheduled', NULL, '', 18, '2026-04-22 23:49:26'),
(2, 2, '2026-04-23', '08:00:00', '16:00:00', 'matin', NULL, 0, 'confirmed', NULL, NULL, NULL, '2026-04-23 01:30:47'),
(3, 4, '2026-04-23', '08:00:00', '16:00:00', 'matin', NULL, 0, 'confirmed', NULL, NULL, NULL, '2026-04-23 01:30:47'),
(4, 7, '2026-04-23', '08:00:00', '16:00:00', 'matin', NULL, 0, 'confirmed', NULL, NULL, NULL, '2026-04-23 01:30:47'),
(5, 20, '2026-04-23', '10:00:00', '18:00:00', 'soir', NULL, 0, 'confirmed', NULL, NULL, NULL, '2026-04-23 01:30:47'),
(6, 21, '2026-04-23', '08:00:00', '16:00:00', 'matin', NULL, 0, 'scheduled', NULL, NULL, NULL, '2026-04-23 01:30:47'),
(7, 2, '2026-04-24', '16:00:00', '00:00:00', 'soir', NULL, 0, 'scheduled', NULL, NULL, NULL, '2026-04-23 01:30:47'),
(8, 4, '2026-04-24', '00:00:00', '08:00:00', 'nuit', NULL, 0, 'scheduled', NULL, NULL, NULL, '2026-04-23 01:30:47');

-- --------------------------------------------------------

--
-- Structure de la table `stock_movements`
--

CREATE TABLE `stock_movements` (
  `id` int(11) NOT NULL,
  `medicine_id` int(11) NOT NULL,
  `type` enum('in','out','adjustment') NOT NULL,
  `quantity` int(11) NOT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `reference_id` int(11) DEFAULT NULL COMMENT 'ID prescription ou commande',
  `performed_by` int(11) DEFAULT NULL COMMENT 'user_id',
  `movement_date` datetime DEFAULT current_timestamp(),
  `reference_type` enum('prescription','order') DEFAULT NULL,
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `stock_movements`
--

INSERT INTO `stock_movements` (`id`, `medicine_id`, `type`, `quantity`, `reason`, `reference_id`, `performed_by`, `movement_date`, `reference_type`, `notes`) VALUES
(1, 2, 'in', 5, 'retour', NULL, 26, '2026-04-23 15:01:17', NULL, NULL),
(2, 2, 'out', 1, 'distribution', NULL, 26, '2026-04-23 15:01:55', NULL, NULL),
(3, 4, 'in', 19, 'commande', NULL, 26, '2026-04-23 19:43:26', NULL, NULL),
(4, 4, 'in', 1, 'Réapprovisionnement manuel GMG', NULL, 1, '2026-04-28 14:51:51', NULL, NULL);

-- --------------------------------------------------------

--
-- Structure de la table `suppliers`
--

CREATE TABLE `suppliers` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `contact_person` varchar(255) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `supplier_type` enum('medicines','equipment','food','other') DEFAULT 'medicines',
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `suppliers`
--

INSERT INTO `suppliers` (`id`, `name`, `contact_person`, `phone`, `email`, `address`, `supplier_type`, `is_active`, `created_at`) VALUES
(1, 'Pharmacie Centrale', 'Mohamed Ali', '0555123456', 'contact@pharmacie.ma', 'Alger Centre', 'medicines', 1, '2026-04-23 01:33:46'),
(2, 'MedEquip SARL', 'Karim Bensaid', '0555234567', 'commercial@medequip.dz', 'Hydra, Alger', 'equipment', 1, '2026-04-23 01:33:46'),
(3, 'FoodMed Distribution', 'Nadia Lounis', '0555345678', 'contact@foodmed.dz', 'Bab Ezzouar', 'equipment', 1, '2026-04-23 01:33:46'),
(4, 'djamfood', 'supp', '0789076435', 'fourniss@gmail.com', 'constantine ain smara', 'food', 1, '2026-04-23 15:12:38');

-- --------------------------------------------------------

--
-- Structure de la table `surgery_schedule`
--

CREATE TABLE `surgery_schedule` (
  `id` int(11) NOT NULL,
  `operating_room_id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `doctor_id` int(11) NOT NULL,
  `surgery_type` varchar(100) NOT NULL COMMENT 'Type d''intervention',
  `priority` enum('routine','urgent','emergency') DEFAULT 'routine',
  `scheduled_start` datetime NOT NULL,
  `scheduled_end` datetime NOT NULL,
  `actual_start` datetime DEFAULT NULL,
  `actual_end` datetime DEFAULT NULL,
  `status` enum('scheduled','preparing','in_progress','paused','completed','cancelled','post_op') DEFAULT 'scheduled',
  `anesthesia_type` enum('general','local','regional','peridural') DEFAULT 'general',
  `team_notes` text DEFAULT NULL,
  `post_op_notes` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `surgery_schedule`
--

INSERT INTO `surgery_schedule` (`id`, `operating_room_id`, `patient_id`, `doctor_id`, `surgery_type`, `priority`, `scheduled_start`, `scheduled_end`, `actual_start`, `actual_end`, `status`, `anesthesia_type`, `team_notes`, `post_op_notes`, `created_by`, `created_at`, `updated_at`) VALUES
(4, 3, 2, 22, 'Appendicectomie', 'routine', '2026-04-27 08:00:00', '2026-04-27 09:00:00', NULL, NULL, 'scheduled', 'general', '', NULL, NULL, '2026-05-01 20:20:22', '2026-05-01 20:20:22'),
(6, 3, 1, 22, 'Cholécystectomie', 'routine', '2026-05-01 08:00:00', '2026-05-01 09:30:00', NULL, NULL, 'scheduled', 'general', '', NULL, NULL, '2026-05-01 20:23:01', '2026-05-01 20:23:01'),
(7, 3, 1, 22, 'Fracture fémur', 'routine', '2026-05-01 03:33:00', '2026-05-01 05:33:00', NULL, NULL, 'scheduled', 'general', '', NULL, NULL, '2026-05-01 20:31:26', '2026-05-01 20:31:26');

-- --------------------------------------------------------

--
-- Structure de la table `surgery_types`
--

CREATE TABLE `surgery_types` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `duration` int(11) DEFAULT 60,
  `is_active` tinyint(4) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `surgery_types`
--

INSERT INTO `surgery_types` (`id`, `name`, `duration`, `is_active`) VALUES
(1, 'Appendicectomie', 60, 1),
(2, 'Cholécystectomie', 90, 1),
(3, 'Pontage coronarien', 240, 1),
(4, 'Hernie inguinale', 60, 1),
(5, 'Fracture fémur', 120, 1),
(6, 'Césarienne', 60, 1),
(7, 'Tumeur cérébrale', 180, 1),
(8, 'Prothèse de hanche', 120, 1),
(9, 'Kyste ovarien', 45, 1),
(10, 'Chirurgie cardiaque', 240, 1);

-- --------------------------------------------------------

--
-- Structure de la table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
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
  `two_factor_secret` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `users`
--

INSERT INTO `users` (`id`, `email`, `password`, `full_name`, `role_id`, `phone`, `address`, `is_active`, `two_factor_enabled`, `otp_secret`, `failed_login_attempts`, `locked_until`, `last_login`, `created_at`, `updated_at`, `two_factor_secret`) VALUES
(1, 'admin@med-unity.dz', '123', 'Administrateur Système', 1, '0100000000', 'Clinique Centrale', 1, 1, NULL, 0, NULL, NULL, '2026-04-05 19:59:38', '2026-05-01 23:08:17', NULL),
(2, 'doctor@med-unity.dz', '123', 'Dr. Test Doctor', 2, '0110000000', 'Service Médecine', 1, 1, NULL, 0, NULL, NULL, '2026-04-05 19:59:38', '2026-05-01 23:08:17', NULL),
(3, 'patient@med-unity.dz', '123', 'Test Patient', 1, '0120000000', 'Adresse Patient', 1, 0, NULL, 0, NULL, NULL, '2026-04-05 19:59:38', '2026-05-01 23:08:17', NULL),
(4, 'infirmier@med-unity.dz', '123', 'Sophie Martin', 4, '0130000000', 'Service Soins', 1, 0, NULL, 0, NULL, NULL, '2026-04-05 19:59:38', '2026-05-01 23:08:17', NULL),
(5, 'pharmacien@med-unity.dz', '123', 'Karim Benali', 6, '0140000000', 'Pharmacie Centrale', 1, 1, NULL, 0, NULL, NULL, '2026-04-05 19:59:38', '2026-05-01 23:08:17', NULL),
(6, 'laborantin@med-unity.dz', '123', 'Leila Mansouri', 5, '0150000000', 'Laboratoire d\'Analyse', 1, 1, NULL, 1, NULL, NULL, '2026-04-05 19:59:38', '2026-05-05 19:37:34', NULL),
(7, 'reception@med-unity.dz', '123', 'Nadia Bouzid', 7, '0160000000', 'Accueil Clinique', 1, 1, NULL, 0, NULL, NULL, '2026-04-05 19:59:38', '2026-05-01 23:08:17', NULL),
(9, 'comptable@med-unity.dz', '123', 'Fatima Zohra', 9, '0180000000', 'Service Finances', 0, 1, NULL, 0, NULL, NULL, '2026-04-05 19:59:38', '2026-05-01 23:08:17', NULL),
(11, 'amanilakehal47@med-unity.dz', '$2y$10$fmOLZu5Q90Ca.rIlgHOx4OPV67rvOOrVcD26eYp6N43q7iRYp86vu', 'Amaniii  pp', 1, '0000000000', NULL, 1, 1, 'AUSZEE6THV5CXU4C', 0, NULL, '2026-05-05 19:52:00', '2026-04-11 21:41:13', '2026-05-05 19:52:00', NULL),
(13, 'ala@med-unity.dz', '$2y$10$893Zk/K7IR9B3HGiqv6Yy.nGjCkWY2uwnGRZyIulIHIIwTmKieJJq', 'ala', 3, '0000000000', NULL, 1, 0, NULL, 0, NULL, NULL, '2026-04-12 19:47:18', '2026-05-01 23:08:17', NULL),
(14, 'lk@med-unity.dz', '$2y$10$yAqEWuBFva.GDxJx5/zVtuichOZg/bAUyXzKPZyTfOtD4rQdO8Hk2', 'lk', 3, '0000000000', NULL, 1, 0, NULL, 0, NULL, NULL, '2026-04-12 19:49:10', '2026-05-01 23:08:17', NULL),
(15, '1@med-unity.dz', '$2y$10$1xHe3bAvauyTVj8Cs3SlfekMDBNs.Qjyop0ovc6HgSITgttudNJCe', '1', 7, '11111111111111', NULL, 1, 0, NULL, 0, NULL, '2026-05-05 20:14:35', '2026-04-12 20:43:56', '2026-05-05 20:14:35', NULL),
(16, 'P202695483@med-unity.dz', '', 'ppd', 3, '0000000000', NULL, 1, 0, NULL, 0, NULL, NULL, '2026-04-12 20:49:42', '2026-05-01 23:08:17', NULL),
(17, 'djam@med-unity.dz', '$2y$10$e7J4E.aUxxD/MlVMsoG5wO0xxr7JDrXuTC/uWbaEks4owlopBrx8O', 'djam', 9, '0555555555', NULL, 1, 0, NULL, 0, NULL, '2026-05-01 23:29:04', '2026-04-21 16:10:59', '2026-05-01 23:29:04', NULL),
(18, 'rh@med-unity.dz', '$2y$10$UH7xwFXNSgz1zZUCKaipCuvmuBHswHD89JbE4Mmi8Tq/GlguZwIdC', 'rh', 8, '066666666', NULL, 1, 0, NULL, 0, NULL, '2026-05-01 23:35:43', '2026-04-21 16:12:08', '2026-05-01 23:35:43', NULL),
(19, 'yl.ik@med-unity.dz', '$2y$10$8D7e73lqVY1cy36/eX3ROuoDSbP5629abT1P5yT7g.NBEYKkaFd0S', 'yalaoui ikram', 4, '0566789676', NULL, 1, 0, NULL, 0, NULL, NULL, '2026-04-23 00:12:24', '2026-05-01 23:08:17', NULL),
(20, 'medecin1@med-unity.dz', '$2y$10$examplehash', 'Dr. Ahmed Benali', 2, '0555123456', 'Service Cardiologie', 1, 0, NULL, 0, NULL, NULL, '2026-04-23 01:25:08', '2026-05-01 23:08:17', NULL),
(21, 'medecin2@med-unity.dz', '$2y$10$examplehash', 'Dr. Fatima Zohra', 2, '0555234567', 'Service Pédiatrie', 1, 0, NULL, 0, NULL, NULL, '2026-04-23 01:25:08', '2026-05-01 23:08:17', NULL),
(22, 'infirmier2@med-unity.dz', '$2y$10$examplehash', 'Karim Boudiaf', 4, '0555345678', 'Service Urgences', 1, 0, NULL, 0, NULL, NULL, '2026-04-23 01:25:08', '2026-05-01 23:08:17', NULL),
(23, 'pharmacien2@med-unity.dz', '$2y$10$examplehash', 'Nadia Lounis', 6, '0555456789', 'Pharmacie Centrale', 1, 0, NULL, 0, NULL, NULL, '2026-04-23 01:25:08', '2026-05-01 23:08:17', NULL),
(24, 'labo2@med-unity.dz', '$2y$10$examplehash', 'Slimane Rahmani', 5, '0555567890', 'Laboratoire', 1, 0, NULL, 0, NULL, NULL, '2026-04-23 01:25:08', '2026-05-01 23:08:17', NULL),
(25, 'secouriste@med-unity.dz', '$2y$10$examplehash', 'Amira Mansouri', 19, '0555678901', 'Service Ambulance', 1, 0, NULL, 0, NULL, NULL, '2026-04-23 01:25:08', '2026-05-01 23:08:17', NULL),
(26, 'pharm@med-unity.dz', '$2y$10$G/g8uTMTeYL69oUwlhJFoOd8bPOMt.XuIFI7qZwCyhp2SXahNrf/6', 'pharmacie', 6, '0555555555', NULL, 1, 0, NULL, 0, NULL, '2026-05-05 20:19:40', '2026-04-23 01:42:16', '2026-05-05 20:19:40', NULL),
(27, 'gestMoy@med-unity.dz', '$2y$10$Gg8Ww8Kqrl8vzn/jcQ692.oXlRI.4m6wp.y8iFkvoIFgFCmZheFwm', 'gestMoy', 11, '0000000000', NULL, 1, 0, NULL, 0, NULL, '2026-05-05 19:49:44', '2026-04-28 14:31:10', '2026-05-05 19:49:44', NULL),
(28, 'labo@med-unity.dz', '$2y$10$r9AZn291Z4ufqoc1e0Uy2O91jkSj52SgjPsWRd5E6lactOln4xrri', 'labo', 5, '0000000000', NULL, 1, 0, NULL, 0, NULL, '2026-05-05 20:26:27', '2026-04-28 14:34:12', '2026-05-05 20:26:27', NULL),
(29, 'med@med-unity.dz', '$2y$10$xmnyVJts68FCsQVusnlYcOA7PeljfkVRTjfeXYhmZVCWM0DxDzmcS', 'med', 2, '0000000000', NULL, 1, 0, NULL, 0, NULL, '2026-05-01 14:30:56', '2026-05-01 14:17:49', '2026-05-01 23:08:17', NULL),
(30, 'ch@med-unity.dz', '$2y$10$bEFz5UJCrARFkuQslC9w3efnbz4mPHb3Oo4byXMN6Mw8DOWy2dnuy', 'ch', 2, '0000000000', NULL, 1, 0, NULL, 0, NULL, NULL, '2026-05-01 20:06:56', '2026-05-01 23:08:17', NULL),
(31, 'an@med-unity.dz', '$2y$10$JycHx99XF12fczoplmTZUuUXpssUm0LcsbJacsZfdVZcsv1QenFAe', 'an', 2, '0000000000', NULL, 1, 0, NULL, 0, NULL, NULL, '2026-05-01 20:08:55', '2026-05-01 23:08:17', NULL),
(32, 'douaa.zaatout@med-unity.dz', '$2y$10$3qOLdz61BLgtmEFhxzYXdOfi14M0mTlib2JADrDXi9Q5Xt3j596z6', 'douaa zaatout', 2, '0643215678', NULL, 1, 0, NULL, 0, NULL, '2026-05-05 19:55:18', '2026-05-05 19:54:11', '2026-05-05 19:55:18', NULL);

-- --------------------------------------------------------

--
-- Structure de la table `visitor_logs`
--

CREATE TABLE `visitor_logs` (
  `id` int(11) NOT NULL,
  `visitor_name` varchar(255) NOT NULL,
  `id_document` varchar(100) DEFAULT NULL COMMENT 'Numéro pièce d''identité',
  `patient_id` int(11) DEFAULT NULL COMMENT 'Patient visité',
  `agent_id` int(11) DEFAULT NULL COMMENT 'Agent ayant enregistré la visite',
  `check_in` datetime DEFAULT current_timestamp(),
  `check_out` datetime DEFAULT NULL,
  `badge_number` varchar(50) DEFAULT NULL,
  `zone_access` varchar(100) DEFAULT NULL COMMENT 'Zones autorisées'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `vital_signs`
--

CREATE TABLE `vital_signs` (
  `id` int(11) NOT NULL,
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
  `recorded_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Index pour les tables déchargées
--

--
-- Index pour la table `alerts`
--
ALTER TABLE `alerts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `target_role_id` (`target_role_id`),
  ADD KEY `is_read` (`is_read`),
  ADD KEY `fk_alert_read_by` (`read_by`);

--
-- Index pour la table `ambulance_missions`
--
ALTER TABLE `ambulance_missions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `patient_id` (`patient_id`),
  ADD KEY `ambulancier_id` (`ambulancier_id`);

--
-- Index pour la table `appointments`
--
ALTER TABLE `appointments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `patient_id` (`patient_id`),
  ADD KEY `doctor_id` (`doctor_id`),
  ADD KEY `appointment_date` (`appointment_date`),
  ADD KEY `status` (`status`),
  ADD KEY `fk_appointment_created_by` (`created_by`);

--
-- Index pour la table `attendances`
--
ALTER TABLE `attendances`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user_date` (`user_id`,`attendance_date`),
  ADD KEY `created_by` (`created_by`);

--
-- Index pour la table `bed_management`
--
ALTER TABLE `bed_management`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `room_bed` (`room_number`,`bed_number`),
  ADD KEY `patient_id` (`patient_id`),
  ADD KEY `status` (`status`);

--
-- Index pour la table `chat_messages`
--
ALTER TABLE `chat_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sender_id` (`sender_id`),
  ADD KEY `receiver_id` (`receiver_id`),
  ADD KEY `is_read` (`is_read`);

--
-- Index pour la table `cleaning_tasks`
--
ALTER TABLE `cleaning_tasks`
  ADD PRIMARY KEY (`id`),
  ADD KEY `assigned_to` (`assigned_to`),
  ADD KEY `bed_id` (`bed_id`);

--
-- Index pour la table `departments`
--
ALTER TABLE `departments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `manager_id` (`manager_id`);

--
-- Index pour la table `doctors`
--
ALTER TABLE `doctors`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `specialty_id` (`specialty_id`);

--
-- Index pour la table `employee_assignments`
--
ALTER TABLE `employee_assignments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `department_id` (`department_id`);

--
-- Index pour la table `employee_contracts`
--
ALTER TABLE `employee_contracts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Index pour la table `employee_documents`
--
ALTER TABLE `employee_documents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `uploaded_by` (`uploaded_by`);

--
-- Index pour la table `external_labs`
--
ALTER TABLE `external_labs`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `financial_reports`
--
ALTER TABLE `financial_reports`
  ADD PRIMARY KEY (`id`),
  ADD KEY `report_date` (`report_date`),
  ADD KEY `fk_report_generated_by` (`generated_by`);

--
-- Index pour la table `food_stock`
--
ALTER TABLE `food_stock`
  ADD PRIMARY KEY (`id`),
  ADD KEY `supplier_id` (`supplier_id`),
  ADD KEY `expiry_date` (`expiry_date`);

--
-- Index pour la table `hospitalizations`
--
ALTER TABLE `hospitalizations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `patient_id` (`patient_id`),
  ADD KEY `doctor_id` (`doctor_id`),
  ADD KEY `bed_id` (`bed_id`);

--
-- Index pour la table `insurance`
--
ALTER TABLE `insurance`
  ADD PRIMARY KEY (`id`),
  ADD KEY `patient_id` (`patient_id`);

--
-- Index pour la table `insurance_claims`
--
ALTER TABLE `insurance_claims`
  ADD PRIMARY KEY (`id`),
  ADD KEY `insurance_id` (`insurance_id`),
  ADD KEY `invoice_id` (`invoice_id`);

--
-- Index pour la table `invoices`
--
ALTER TABLE `invoices`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `invoice_number` (`invoice_number`),
  ADD KEY `patient_id` (`patient_id`),
  ADD KEY `appointment_id` (`appointment_id`),
  ADD KEY `fk_invoice_hosp` (`hospitalization_id`);

--
-- Index pour la table `invoice_items`
--
ALTER TABLE `invoice_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `invoice_id` (`invoice_id`);

--
-- Index pour la table `lab_inventory`
--
ALTER TABLE `lab_inventory`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `lab_inventory_movements`
--
ALTER TABLE `lab_inventory_movements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `item_id` (`item_id`);

--
-- Index pour la table `lab_stock`
--
ALTER TABLE `lab_stock`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `lab_stock_movements`
--
ALTER TABLE `lab_stock_movements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_item` (`item_id`),
  ADD KEY `idx_date` (`created_at`);

--
-- Index pour la table `lab_tests`
--
ALTER TABLE `lab_tests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `patient_id` (`patient_id`),
  ADD KEY `appointment_id` (`appointment_id`),
  ADD KEY `performed_by` (`performed_by`),
  ADD KEY `external_lab_id` (`external_lab_id`);

--
-- Index pour la table `lab_test_consumables`
--
ALTER TABLE `lab_test_consumables`
  ADD PRIMARY KEY (`id`),
  ADD KEY `test_id` (`test_id`),
  ADD KEY `item_id` (`item_id`);

--
-- Index pour la table `lab_test_consumables_required`
--
ALTER TABLE `lab_test_consumables_required`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_test_item` (`test_name`,`item_id`),
  ADD KEY `item_id` (`item_id`),
  ADD KEY `idx_test_name` (`test_name`);

--
-- Index pour la table `leave_requests`
--
ALTER TABLE `leave_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `approved_by` (`approved_by`);

--
-- Index pour la table `login_logs`
--
ALTER TABLE `login_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_login_time` (`login_time`);

--
-- Index pour la table `logs`
--
ALTER TABLE `logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `action` (`action`),
  ADD KEY `created_at` (`created_at`);

--
-- Index pour la table `maintenance_requests`
--
ALTER TABLE `maintenance_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `assigned_to` (`assigned_to`),
  ADD KEY `reported_by` (`reported_by`);

--
-- Index pour la table `meal_plans`
--
ALTER TABLE `meal_plans`
  ADD PRIMARY KEY (`id`),
  ADD KEY `patient_id` (`patient_id`),
  ADD KEY `served_date` (`served_date`),
  ADD KEY `fk_meal_prepared_by` (`prepared_by`);

--
-- Index pour la table `medical_records`
--
ALTER TABLE `medical_records`
  ADD PRIMARY KEY (`id`),
  ADD KEY `patient_id` (`patient_id`),
  ADD KEY `doctor_id` (`doctor_id`),
  ADD KEY `appointment_id` (`appointment_id`);

--
-- Index pour la table `medicines`
--
ALTER TABLE `medicines`
  ADD PRIMARY KEY (`id`),
  ADD KEY `supplier_id` (`supplier_id`),
  ADD KEY `expiry_date` (`expiry_date`),
  ADD KEY `fk_medicine_original` (`original_medicine_id`);

--
-- Index pour la table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `is_read` (`is_read`);

--
-- Index pour la table `operating_rooms`
--
ALTER TABLE `operating_rooms`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_type` (`room_type`),
  ADD KEY `idx_current_patient` (`current_patient_id`);

--
-- Index pour la table `patients`
--
ALTER TABLE `patients`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uhid` (`uhid`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `fk_patient_insurance` (`insurance_id`);

--
-- Index pour la table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `invoice_id` (`invoice_id`),
  ADD KEY `received_by` (`received_by`);

--
-- Index pour la table `prescriptions`
--
ALTER TABLE `prescriptions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `patient_id` (`patient_id`),
  ADD KEY `doctor_id` (`doctor_id`),
  ADD KEY `appointment_id` (`appointment_id`);

--
-- Index pour la table `prescription_items`
--
ALTER TABLE `prescription_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `prescription_id` (`prescription_id`),
  ADD KEY `medicine_id` (`medicine_id`);

--
-- Index pour la table `psychology_sessions`
--
ALTER TABLE `psychology_sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `patient_id` (`patient_id`),
  ADD KEY `psychologue_id` (`psychologue_id`);

--
-- Index pour la table `purchase_orders`
--
ALTER TABLE `purchase_orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `order_number` (`order_number`),
  ADD KEY `supplier_id` (`supplier_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Index pour la table `purchase_order_items`
--
ALTER TABLE `purchase_order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`);

--
-- Index pour la table `radiology_requests`
--
ALTER TABLE `radiology_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `patient_id` (`patient_id`),
  ADD KEY `doctor_id` (`doctor_id`),
  ADD KEY `radiologue_id` (`radiologue_id`);

--
-- Index pour la table `rehabilitation_plans`
--
ALTER TABLE `rehabilitation_plans`
  ADD PRIMARY KEY (`id`),
  ADD KEY `patient_id` (`patient_id`),
  ADD KEY `kine_id` (`kine_id`),
  ADD KEY `fk_rehab_doctor` (`doctor_id`);

--
-- Index pour la table `rh_alerts`
--
ALTER TABLE `rh_alerts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Index pour la table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `schedules`
--
ALTER TABLE `schedules`
  ADD PRIMARY KEY (`id`),
  ADD KEY `doctor_id` (`doctor_id`);

--
-- Index pour la table `security_incidents`
--
ALTER TABLE `security_incidents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `agent_id` (`agent_id`);

--
-- Index pour la table `sessions`
--
ALTER TABLE `sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `token` (`token`(100));

--
-- Index pour la table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`setting_key`);

--
-- Index pour la table `specialties`
--
ALTER TABLE `specialties`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `staff_planning`
--
ALTER TABLE `staff_planning`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `planning_date` (`planning_date`),
  ADD KEY `fk_planning_replacement` (`replacement_id`),
  ADD KEY `fk_planning_created_by` (`created_by`);

--
-- Index pour la table `stock_movements`
--
ALTER TABLE `stock_movements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `medicine_id` (`medicine_id`),
  ADD KEY `performed_by` (`performed_by`);

--
-- Index pour la table `suppliers`
--
ALTER TABLE `suppliers`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `surgery_schedule`
--
ALTER TABLE `surgery_schedule`
  ADD PRIMARY KEY (`id`),
  ADD KEY `operating_room_id` (`operating_room_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_date` (`scheduled_start`);

--
-- Index pour la table `surgery_types`
--
ALTER TABLE `surgery_types`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `role_id` (`role_id`);

--
-- Index pour la table `visitor_logs`
--
ALTER TABLE `visitor_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `patient_id` (`patient_id`),
  ADD KEY `agent_id` (`agent_id`);

--
-- Index pour la table `vital_signs`
--
ALTER TABLE `vital_signs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `patient_id` (`patient_id`),
  ADD KEY `recorded_by` (`recorded_by`);

--
-- AUTO_INCREMENT pour les tables déchargées
--

--
-- AUTO_INCREMENT pour la table `alerts`
--
ALTER TABLE `alerts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `ambulance_missions`
--
ALTER TABLE `ambulance_missions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `appointments`
--
ALTER TABLE `appointments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT pour la table `attendances`
--
ALTER TABLE `attendances`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `bed_management`
--
ALTER TABLE `bed_management`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT pour la table `chat_messages`
--
ALTER TABLE `chat_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `cleaning_tasks`
--
ALTER TABLE `cleaning_tasks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT pour la table `departments`
--
ALTER TABLE `departments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT pour la table `doctors`
--
ALTER TABLE `doctors`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT pour la table `employee_assignments`
--
ALTER TABLE `employee_assignments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT pour la table `employee_contracts`
--
ALTER TABLE `employee_contracts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT pour la table `employee_documents`
--
ALTER TABLE `employee_documents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT pour la table `external_labs`
--
ALTER TABLE `external_labs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT pour la table `financial_reports`
--
ALTER TABLE `financial_reports`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT pour la table `food_stock`
--
ALTER TABLE `food_stock`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `hospitalizations`
--
ALTER TABLE `hospitalizations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT pour la table `insurance`
--
ALTER TABLE `insurance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `insurance_claims`
--
ALTER TABLE `insurance_claims`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `invoices`
--
ALTER TABLE `invoices`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT pour la table `invoice_items`
--
ALTER TABLE `invoice_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT pour la table `lab_inventory`
--
ALTER TABLE `lab_inventory`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `lab_inventory_movements`
--
ALTER TABLE `lab_inventory_movements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `lab_stock`
--
ALTER TABLE `lab_stock`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT pour la table `lab_stock_movements`
--
ALTER TABLE `lab_stock_movements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT pour la table `lab_tests`
--
ALTER TABLE `lab_tests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT pour la table `lab_test_consumables`
--
ALTER TABLE `lab_test_consumables`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `lab_test_consumables_required`
--
ALTER TABLE `lab_test_consumables_required`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT pour la table `leave_requests`
--
ALTER TABLE `leave_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT pour la table `login_logs`
--
ALTER TABLE `login_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=127;

--
-- AUTO_INCREMENT pour la table `logs`
--
ALTER TABLE `logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT pour la table `maintenance_requests`
--
ALTER TABLE `maintenance_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `meal_plans`
--
ALTER TABLE `meal_plans`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `medical_records`
--
ALTER TABLE `medical_records`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `medicines`
--
ALTER TABLE `medicines`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT pour la table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT pour la table `operating_rooms`
--
ALTER TABLE `operating_rooms`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT pour la table `patients`
--
ALTER TABLE `patients`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT pour la table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT pour la table `prescriptions`
--
ALTER TABLE `prescriptions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `prescription_items`
--
ALTER TABLE `prescription_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `psychology_sessions`
--
ALTER TABLE `psychology_sessions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `purchase_orders`
--
ALTER TABLE `purchase_orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT pour la table `purchase_order_items`
--
ALTER TABLE `purchase_order_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `radiology_requests`
--
ALTER TABLE `radiology_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `rehabilitation_plans`
--
ALTER TABLE `rehabilitation_plans`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `rh_alerts`
--
ALTER TABLE `rh_alerts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT pour la table `roles`
--
ALTER TABLE `roles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT pour la table `schedules`
--
ALTER TABLE `schedules`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT pour la table `security_incidents`
--
ALTER TABLE `security_incidents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `sessions`
--
ALTER TABLE `sessions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `staff_planning`
--
ALTER TABLE `staff_planning`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT pour la table `stock_movements`
--
ALTER TABLE `stock_movements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT pour la table `suppliers`
--
ALTER TABLE `suppliers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT pour la table `surgery_schedule`
--
ALTER TABLE `surgery_schedule`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT pour la table `surgery_types`
--
ALTER TABLE `surgery_types`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT pour la table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;

--
-- AUTO_INCREMENT pour la table `visitor_logs`
--
ALTER TABLE `visitor_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `vital_signs`
--
ALTER TABLE `vital_signs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Contraintes pour les tables déchargées
--

--
-- Contraintes pour la table `alerts`
--
ALTER TABLE `alerts`
  ADD CONSTRAINT `fk_alert_read_by` FOREIGN KEY (`read_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_alert_target_role` FOREIGN KEY (`target_role_id`) REFERENCES `roles` (`id`);

--
-- Contraintes pour la table `ambulance_missions`
--
ALTER TABLE `ambulance_missions`
  ADD CONSTRAINT `fk_ambul_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_ambul_user` FOREIGN KEY (`ambulancier_id`) REFERENCES `users` (`id`);

--
-- Contraintes pour la table `appointments`
--
ALTER TABLE `appointments`
  ADD CONSTRAINT `fk_appointment_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_appointment_doctor` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`id`),
  ADD CONSTRAINT `fk_appointment_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `attendances`
--
ALTER TABLE `attendances`
  ADD CONSTRAINT `attendances_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `attendances_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Contraintes pour la table `bed_management`
--
ALTER TABLE `bed_management`
  ADD CONSTRAINT `fk_bed_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE SET NULL;

--
-- Contraintes pour la table `chat_messages`
--
ALTER TABLE `chat_messages`
  ADD CONSTRAINT `fk_chat_receiver` FOREIGN KEY (`receiver_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_chat_sender` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`);

--
-- Contraintes pour la table `cleaning_tasks`
--
ALTER TABLE `cleaning_tasks`
  ADD CONSTRAINT `fk_cleaning_assigned` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_cleaning_bed` FOREIGN KEY (`bed_id`) REFERENCES `bed_management` (`id`) ON DELETE SET NULL;

--
-- Contraintes pour la table `departments`
--
ALTER TABLE `departments`
  ADD CONSTRAINT `departments_ibfk_1` FOREIGN KEY (`manager_id`) REFERENCES `users` (`id`);

--
-- Contraintes pour la table `doctors`
--
ALTER TABLE `doctors`
  ADD CONSTRAINT `fk_doctor_specialty` FOREIGN KEY (`specialty_id`) REFERENCES `specialties` (`id`),
  ADD CONSTRAINT `fk_doctor_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `employee_assignments`
--
ALTER TABLE `employee_assignments`
  ADD CONSTRAINT `employee_assignments_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `employee_assignments_ibfk_2` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`);

--
-- Contraintes pour la table `employee_contracts`
--
ALTER TABLE `employee_contracts`
  ADD CONSTRAINT `fk_contract_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `employee_documents`
--
ALTER TABLE `employee_documents`
  ADD CONSTRAINT `employee_documents_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `employee_documents_ibfk_2` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`);

--
-- Contraintes pour la table `financial_reports`
--
ALTER TABLE `financial_reports`
  ADD CONSTRAINT `fk_report_generated_by` FOREIGN KEY (`generated_by`) REFERENCES `users` (`id`);

--
-- Contraintes pour la table `food_stock`
--
ALTER TABLE `food_stock`
  ADD CONSTRAINT `fk_food_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE SET NULL;

--
-- Contraintes pour la table `hospitalizations`
--
ALTER TABLE `hospitalizations`
  ADD CONSTRAINT `fk_hosp_bed` FOREIGN KEY (`bed_id`) REFERENCES `bed_management` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_hosp_doctor` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`id`),
  ADD CONSTRAINT `fk_hosp_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `insurance`
--
ALTER TABLE `insurance`
  ADD CONSTRAINT `fk_insurance_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `insurance_claims`
--
ALTER TABLE `insurance_claims`
  ADD CONSTRAINT `fk_claim_insurance` FOREIGN KEY (`insurance_id`) REFERENCES `insurance` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_claim_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `invoices`
--
ALTER TABLE `invoices`
  ADD CONSTRAINT `fk_invoice_appointment` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_invoice_hosp` FOREIGN KEY (`hospitalization_id`) REFERENCES `hospitalizations` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_invoice_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `invoice_items`
--
ALTER TABLE `invoice_items`
  ADD CONSTRAINT `fk_invoice_item_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `lab_inventory_movements`
--
ALTER TABLE `lab_inventory_movements`
  ADD CONSTRAINT `lab_inventory_movements_ibfk_1` FOREIGN KEY (`item_id`) REFERENCES `lab_inventory` (`id`);

--
-- Contraintes pour la table `lab_tests`
--
ALTER TABLE `lab_tests`
  ADD CONSTRAINT `fk_labtest_appointment` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_labtest_external_lab` FOREIGN KEY (`external_lab_id`) REFERENCES `external_labs` (`id`),
  ADD CONSTRAINT `fk_labtest_laborantin` FOREIGN KEY (`performed_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_labtest_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `lab_test_consumables`
--
ALTER TABLE `lab_test_consumables`
  ADD CONSTRAINT `lab_test_consumables_ibfk_1` FOREIGN KEY (`test_id`) REFERENCES `lab_tests` (`id`),
  ADD CONSTRAINT `lab_test_consumables_ibfk_2` FOREIGN KEY (`item_id`) REFERENCES `lab_inventory` (`id`);

--
-- Contraintes pour la table `lab_test_consumables_required`
--
ALTER TABLE `lab_test_consumables_required`
  ADD CONSTRAINT `lab_test_consumables_required_ibfk_1` FOREIGN KEY (`item_id`) REFERENCES `lab_stock` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `leave_requests`
--
ALTER TABLE `leave_requests`
  ADD CONSTRAINT `fk_leave_approved_by` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_leave_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `logs`
--
ALTER TABLE `logs`
  ADD CONSTRAINT `fk_log_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Contraintes pour la table `maintenance_requests`
--
ALTER TABLE `maintenance_requests`
  ADD CONSTRAINT `fk_maintenance_assigned` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_maintenance_reported` FOREIGN KEY (`reported_by`) REFERENCES `users` (`id`);

--
-- Contraintes pour la table `meal_plans`
--
ALTER TABLE `meal_plans`
  ADD CONSTRAINT `fk_meal_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_meal_prepared_by` FOREIGN KEY (`prepared_by`) REFERENCES `users` (`id`);

--
-- Contraintes pour la table `medical_records`
--
ALTER TABLE `medical_records`
  ADD CONSTRAINT `fk_medical_record_appointment` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_medical_record_doctor` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`id`),
  ADD CONSTRAINT `fk_medical_record_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `medicines`
--
ALTER TABLE `medicines`
  ADD CONSTRAINT `fk_medicine_original` FOREIGN KEY (`original_medicine_id`) REFERENCES `medicines` (`id`) ON DELETE SET NULL;

--
-- Contraintes pour la table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `fk_notification_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `patients`
--
ALTER TABLE `patients`
  ADD CONSTRAINT `fk_patient_insurance` FOREIGN KEY (`insurance_id`) REFERENCES `insurance` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_patient_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `fk_payment_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_payment_received_by` FOREIGN KEY (`received_by`) REFERENCES `users` (`id`);

--
-- Contraintes pour la table `prescriptions`
--
ALTER TABLE `prescriptions`
  ADD CONSTRAINT `fk_prescription_appointment` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_prescription_doctor` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`id`),
  ADD CONSTRAINT `fk_prescription_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `prescription_items`
--
ALTER TABLE `prescription_items`
  ADD CONSTRAINT `fk_prescription_item_medicine` FOREIGN KEY (`medicine_id`) REFERENCES `medicines` (`id`),
  ADD CONSTRAINT `fk_prescription_item_prescription` FOREIGN KEY (`prescription_id`) REFERENCES `prescriptions` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `psychology_sessions`
--
ALTER TABLE `psychology_sessions`
  ADD CONSTRAINT `fk_psych_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_psych_psy` FOREIGN KEY (`psychologue_id`) REFERENCES `users` (`id`);

--
-- Contraintes pour la table `purchase_orders`
--
ALTER TABLE `purchase_orders`
  ADD CONSTRAINT `fk_po_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_po_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`);

--
-- Contraintes pour la table `purchase_order_items`
--
ALTER TABLE `purchase_order_items`
  ADD CONSTRAINT `fk_poi_order` FOREIGN KEY (`order_id`) REFERENCES `purchase_orders` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `radiology_requests`
--
ALTER TABLE `radiology_requests`
  ADD CONSTRAINT `fk_radio_doctor` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`id`),
  ADD CONSTRAINT `fk_radio_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_radio_radiologue` FOREIGN KEY (`radiologue_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Contraintes pour la table `rehabilitation_plans`
--
ALTER TABLE `rehabilitation_plans`
  ADD CONSTRAINT `fk_rehab_doctor` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_rehab_kine` FOREIGN KEY (`kine_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_rehab_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `rh_alerts`
--
ALTER TABLE `rh_alerts`
  ADD CONSTRAINT `rh_alerts_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Contraintes pour la table `schedules`
--
ALTER TABLE `schedules`
  ADD CONSTRAINT `fk_schedule_doctor` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `security_incidents`
--
ALTER TABLE `security_incidents`
  ADD CONSTRAINT `fk_sec_agent` FOREIGN KEY (`agent_id`) REFERENCES `users` (`id`);

--
-- Contraintes pour la table `sessions`
--
ALTER TABLE `sessions`
  ADD CONSTRAINT `fk_session_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `staff_planning`
--
ALTER TABLE `staff_planning`
  ADD CONSTRAINT `fk_planning_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_planning_replacement` FOREIGN KEY (`replacement_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_planning_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `stock_movements`
--
ALTER TABLE `stock_movements`
  ADD CONSTRAINT `fk_stock_movement_medicine` FOREIGN KEY (`medicine_id`) REFERENCES `medicines` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_stock_movement_user` FOREIGN KEY (`performed_by`) REFERENCES `users` (`id`);

--
-- Contraintes pour la table `surgery_schedule`
--
ALTER TABLE `surgery_schedule`
  ADD CONSTRAINT `surgery_schedule_ibfk_1` FOREIGN KEY (`operating_room_id`) REFERENCES `operating_rooms` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `fk_users_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`);

--
-- Contraintes pour la table `visitor_logs`
--
ALTER TABLE `visitor_logs`
  ADD CONSTRAINT `fk_visitor_agent` FOREIGN KEY (`agent_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_visitor_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE SET NULL;

--
-- Contraintes pour la table `vital_signs`
--
ALTER TABLE `vital_signs`
  ADD CONSTRAINT `fk_vitals_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_vitals_recorded_by` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
