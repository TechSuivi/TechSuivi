-- TechSuivi Database Backup v2.8 ULTRA-FORCED
-- Generated on: 2025-11-14 10:19:40
-- Backup type: Full
-- Format: SQL
-- Version: v2.8 ULTRA-FORCED FINAL UPDATE

SET FOREIGN_KEY_CHECKS=0;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;

-- --------------------------------------------------------
-- Structure for table `FC_cyber` (v2.8 ULTRA-FORCED)
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `FC_cyber` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nom` varchar(100) DEFAULT NULL,
  `ha` time DEFAULT NULL,
  `hd` time DEFAULT NULL,
  `imp` decimal(10,2) DEFAULT NULL,
  `imp_c` decimal(11,0) DEFAULT NULL,
  `tarif` decimal(10,2) DEFAULT NULL,
  `moyen_payement` text NOT NULL,
  `info_chq` text NOT NULL,
  `date_cyber` timestamp NULL DEFAULT current_timestamp(),
  `price_nb_page` decimal(10,2) DEFAULT NULL COMMENT 'Prix par page N&B utilisé lors de cette session',
  `price_color_page` decimal(10,2) DEFAULT NULL COMMENT 'Prix par page couleur utilisé lors de cette session',
  `price_time_base` decimal(10,2) DEFAULT NULL COMMENT 'Prix par tranche de temps utilisé lors de cette session',
  `price_time_minimum` decimal(10,2) DEFAULT NULL COMMENT 'Prix minimum utilisé lors de cette session',
  `time_minimum_threshold` int(11) DEFAULT NULL COMMENT 'Seuil session courte utilisé lors de cette session (en minutes)',
  `time_increment` int(11) DEFAULT NULL COMMENT 'Incrément de temps utilisé lors de cette session (en minutes)',
  `credit_id` int(11) DEFAULT NULL,
  `paye_par_credit` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_credit_id` (`credit_id`),
  KEY `idx_paye_par_credit` (`paye_par_credit`),
  CONSTRAINT `FC_cyber_ibfk_1` FOREIGN KEY (`credit_id`) REFERENCES `FC_cyber_credits` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Structure for table `FC_cyber_credits` (v2.8 ULTRA-FORCED)
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `FC_cyber_credits` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nom_client` varchar(255) NOT NULL,
  `solde_actuel` decimal(10,2) DEFAULT 0.00,
  `date_creation` timestamp NULL DEFAULT current_timestamp(),
  `date_modification` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `actif` tinyint(1) DEFAULT 1,
  `notes` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_nom_client` (`nom_client`),
  KEY `idx_actif` (`actif`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Structure for table `FC_cyber_credits_historique` (v2.8 ULTRA-FORCED)
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `FC_cyber_credits_historique` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `credit_id` int(11) NOT NULL,
  `type_mouvement` enum('AJOUT','DEDUCTION','CORRECTION') NOT NULL,
  `montant` decimal(10,2) NOT NULL,
  `solde_avant` decimal(10,2) NOT NULL,
  `solde_apres` decimal(10,2) NOT NULL,
  `description` text DEFAULT NULL,
  `session_cyber_id` int(11) DEFAULT NULL,
  `date_mouvement` timestamp NULL DEFAULT current_timestamp(),
  `utilisateur` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `session_cyber_id` (`session_cyber_id`),
  KEY `idx_credit_id` (`credit_id`),
  KEY `idx_type_mouvement` (`type_mouvement`),
  KEY `idx_date_mouvement` (`date_mouvement`),
  CONSTRAINT `FC_cyber_credits_historique_ibfk_1` FOREIGN KEY (`credit_id`) REFERENCES `FC_cyber_credits` (`id`) ON DELETE CASCADE,
  CONSTRAINT `FC_cyber_credits_historique_ibfk_2` FOREIGN KEY (`session_cyber_id`) REFERENCES `FC_cyber` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Structure for table `FC_feuille_caisse` (v2.8 ULTRA-FORCED)
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `FC_feuille_caisse` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `date_comptage` date NOT NULL,
  `pieces_001` int(11) DEFAULT 0 COMMENT 'Nombre de pièces de 0,01€',
  `pieces_002` int(11) DEFAULT 0 COMMENT 'Nombre de pièces de 0,02€',
  `pieces_005` int(11) DEFAULT 0 COMMENT 'Nombre de pièces de 0,05€',
  `pieces_010` int(11) DEFAULT 0 COMMENT 'Nombre de pièces de 0,10€',
  `pieces_020` int(11) DEFAULT 0 COMMENT 'Nombre de pièces de 0,20€',
  `pieces_050` int(11) DEFAULT 0 COMMENT 'Nombre de pièces de 0,50€',
  `pieces_100` int(11) DEFAULT 0 COMMENT 'Nombre de pièces de 1€',
  `pieces_200` int(11) DEFAULT 0 COMMENT 'Nombre de pièces de 2€',
  `billets_005` int(11) DEFAULT 0 COMMENT 'Nombre de billets de 5€',
  `billets_010` int(11) DEFAULT 0 COMMENT 'Nombre de billets de 10€',
  `billets_020` int(11) DEFAULT 0 COMMENT 'Nombre de billets de 20€',
  `billets_050` int(11) DEFAULT 0 COMMENT 'Nombre de billets de 50€',
  `billets_100` int(11) DEFAULT 0 COMMENT 'Nombre de billets de 100€',
  `billets_200` int(11) DEFAULT 0 COMMENT 'Nombre de billets de 200€',
  `billets_500` int(11) DEFAULT 0 COMMENT 'Nombre de billets de 500€',
  `montant_cheques` decimal(10,2) DEFAULT 0.00 COMMENT 'Montant total des chèques',
  `total_pieces` decimal(10,2) DEFAULT 0.00 COMMENT 'Total des pièces',
  `total_billets` decimal(10,2) DEFAULT 0.00 COMMENT 'Total des billets',
  `total_especes` decimal(10,2) DEFAULT 0.00 COMMENT 'Total espèces (pièces + billets)',
  `total_caisse` decimal(10,2) DEFAULT 0.00 COMMENT 'Total caisse (espèces + chèques)',
  `notes` text DEFAULT NULL COMMENT 'Notes sur le comptage',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `cheques_details` text DEFAULT NULL COMMENT 'Détails des chèques (JSON)',
  `nb_cheques` int(11) DEFAULT 0 COMMENT 'Nombre de chèques',
  `solde_precedent` decimal(10,2) DEFAULT 0.00 COMMENT 'Solde espèces de la feuille précédente',
  `ajustement_especes` decimal(10,2) DEFAULT 0.00 COMMENT 'Ajustement espèces (entrées/sorties)',
  `ecart_constate` decimal(10,2) DEFAULT 0.00 COMMENT 'Écart entre attendu et réel',
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_date_comptage` (`date_comptage`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Structure for table `FC_moyens_paiement` (v2.8 ULTRA-FORCED)
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `FC_moyens_paiement` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `moyen` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Structure for table `FC_transactions` (v2.8 ULTRA-FORCED)
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `FC_transactions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nom` varchar(100) DEFAULT NULL,
  `montant` decimal(10,2) DEFAULT NULL,
  `type` varchar(50) DEFAULT NULL,
  `banque` varchar(100) DEFAULT NULL,
  `num_cheque` varchar(50) DEFAULT NULL,
  `acompte` decimal(10,2) DEFAULT NULL,
  `solde` decimal(10,2) DEFAULT NULL,
  `num_facture` varchar(50) DEFAULT NULL,
  `date_transaction` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Structure for table `Stock` (v2.8 ULTRA-FORCED)
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `Stock` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ref_acadia` varchar(255) DEFAULT NULL,
  `ean_code` varchar(255) NOT NULL,
  `designation` text NOT NULL,
  `prix_achat_ht` decimal(10,2) NOT NULL,
  `prix_vente_ttc` decimal(10,2) NOT NULL,
  `date_ajout` timestamp NULL DEFAULT current_timestamp(),
  `fournisseur` varchar(255) DEFAULT NULL,
  `numero_commande` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ean_code` (`ean_code`),
  KEY `date_ajout` (`date_ajout`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Structure for table `agenda` (v2.8 ULTRA-FORCED)
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `agenda` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `titre` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `date_planifiee` datetime NOT NULL,
  `date_creation` datetime DEFAULT current_timestamp(),
  `date_modification` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `priorite` enum('basse','normale','haute','urgente') DEFAULT 'normale',
  `statut` enum('planifie','en_cours','termine','reporte','annule') DEFAULT 'planifie',
  `utilisateur` varchar(100) DEFAULT NULL,
  `couleur` varchar(7) DEFAULT '#3498db',
  `rappel_minutes` int(11) DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_date_planifiee` (`date_planifiee`),
  KEY `idx_statut` (`statut`),
  KEY `idx_utilisateur` (`utilisateur`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Structure for table `app_config` (v2.8 ULTRA-FORCED)
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `app_config` (
  `config_key` varchar(100) NOT NULL,
  `config_value` text DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`config_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Data for table `app_config` (1 rows) v2.8 ULTRA-FORCED
-- --------------------------------------------------------

INSERT INTO `app_config` (`config_key`, `config_value`, `updated_at`) VALUES
('timezone_offset', 2, '2025-06-19 09:08:12');

-- --------------------------------------------------------
-- Structure for table `autoit_commandes` (v2.8 ULTRA-FORCED)
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `autoit_commandes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nom` varchar(255) NOT NULL,
  `commande` text NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Data for table `autoit_commandes` (6 rows) v2.8 ULTRA-FORCED
-- --------------------------------------------------------

INSERT INTO `autoit_commandes` (`id`, `nom`, `commande`, `description`, `created_at`, `updated_at`) VALUES
(1, 'CHKDSK /R', 'chkdsk /r', 'réparation du système de fichier', '2025-06-24 15:01:42', '2025-06-24 15:01:42'),
(2, 'SFC /scannow', 'SFC /scannow', 'réparation du des fichiers', '2025-06-25 08:31:44', '2025-06-25 08:31:44'),
(3, 'DEFRAG C: /U /V /O', 'DEFRAG C: /U /V /O', 'Defragmentation de C: en ligne de commande', '2025-06-25 08:38:00', '2025-06-25 08:38:00'),
(4, 'CleanMGR', 'Cleanmgr', 'Nettoyage fichier système', '2025-06-25 08:38:19', '2025-06-25 08:38:19'),
(5, 'Winget update', 'Winget update', 'Affichage de la liste des mises à jour de winget', '2025-06-25 08:38:55', '2025-06-25 08:38:55'),
(6, 'Winget upgrade --all', 'winget upgrade --all', 'Mise à jour de tout winget', '2025-06-25 08:39:19', '2025-06-25 08:39:19');

-- --------------------------------------------------------
-- Structure for table `autoit_logiciels` (v2.8 ULTRA-FORCED)
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `autoit_logiciels` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nom` varchar(255) NOT NULL,
  `type_installation` enum('winget','fichier') NOT NULL,
  `commande_winget` text DEFAULT NULL,
  `fichier_nom` varchar(255) DEFAULT NULL,
  `fichier_path` varchar(500) DEFAULT NULL,
  `est_zip` tinyint(1) DEFAULT 0,
  `commande_lancement` text DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Data for table `autoit_logiciels` (15 rows) v2.8 ULTRA-FORCED
-- --------------------------------------------------------

INSERT INTO `autoit_logiciels` (`id`, `nom`, `type_installation`, `commande_winget`, `fichier_nom`, `fichier_path`, `est_zip`, `commande_lancement`, `description`, `created_at`, `updated_at`) VALUES
(1, 'Chrome', 'winget', 'google.chrome', '', '', 0, '', '', '2025-06-24 16:00:07', '2025-07-04 14:25:57'),
(2, 'Acrobat Reader 64 Bit', 'winget', 'Adobe.Acrobat.Reader.64-bit', '', '', 0, '', 'Lecteur PDF', '2025-07-04 14:25:08', '2025-10-09 15:41:09'),
(3, 'Mozilla Firefox', 'winget', 'Mozilla.Firefox.fr', '', '', 0, '', '', '2025-07-04 14:26:22', '2025-07-04 14:26:22'),
(4, 'BurnAware Free', 'winget', 'Burnaware.BurnAwareFree', '', '', 0, '', '', '2025-07-04 14:27:31', '2025-07-04 14:27:31'),
(5, '7Zip', 'winget', ' 7zip.7zip', '', '', 0, '', '', '2025-07-04 14:27:57', '2025-07-04 14:27:57'),
(6, 'VLC', 'winget', 'VideoLAN.VLC', '', '', 0, '', '', '2025-07-04 14:28:22', '2025-07-04 14:28:22'),
(7, 'Mozilla Thunderbird', 'winget', 'Mozilla.Thunderbird.fr', '', '', 0, '', '', '2025-07-04 14:29:01', '2025-07-04 14:29:01'),
(8, 'LibreOffice', 'winget', 'TheDocumentFoundation.LibreOffice', '', '', 0, '', '', '2025-07-04 14:29:30', '2025-07-04 14:29:30'),
(9, 'Traystatus', 'winget', 'BinaryFortress.TrayStatus', '', '', 0, '', '', '2025-07-04 14:33:29', '2025-07-04 14:33:29'),
(10, 'Télémaintenance', 'fichier', '', 'Install.exe', 'uploads/autoit/logiciels/Install.exe', 0, 'Install.exe', '', '2025-07-04 14:36:15', '2025-07-04 15:56:06'),
(11, 'Picasa', 'fichier', '', 'picasa.zip', 'uploads/autoit/logiciels/picasa.zip', 1, 'automatisation picasa.exe', '', '2025-07-04 14:37:54', '2025-07-04 15:55:53'),
(12, 'Theme', 'fichier', '', 'Theme.zip', 'uploads/autoit/logiciels/Theme.zip', 1, 'automatisation Theme.exe', '', '2025-07-04 14:38:23', '2025-07-04 14:56:35'),
(13, 'Sensibilisation', 'fichier', '', 'sensibilisation.zip', 'uploads/autoit/logiciels/sensibilisation.zip', 1, 'automatisation sensibilisation.exe', 'Information Sensibilisation Informatique', '2025-07-08 08:31:03', '2025-07-08 13:11:56'),
(14, 'Foxit PDF Reader', 'winget', 'Foxit.FoxitReader', '', '', 0, '', 'Lecteur PDF', '2025-10-09 15:37:49', '2025-10-09 15:40:53'),
(15, 'SumatraPDF', 'winget', 'SumatraPDF.SumatraPDF', '', '', 0, '', 'Lecteur PDF', '2025-10-09 15:38:15', '2025-10-09 15:40:59');

-- --------------------------------------------------------
-- Structure for table `autoit_nettoyage` (v2.8 ULTRA-FORCED)
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `autoit_nettoyage` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nom` varchar(255) NOT NULL,
  `fichier_nom` varchar(255) DEFAULT NULL,
  `fichier_path` varchar(500) DEFAULT NULL,
  `est_zip` tinyint(1) DEFAULT 0,
  `commande_lancement` text NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Data for table `autoit_nettoyage` (7 rows) v2.8 ULTRA-FORCED
-- --------------------------------------------------------

INSERT INTO `autoit_nettoyage` (`id`, `nom`, `fichier_nom`, `fichier_path`, `est_zip`, `commande_lancement`, `description`, `created_at`, `updated_at`) VALUES
(1, 'Adwcleaner', 'adwcleaner (1).exe', 'uploads/autoit/nettoyage/adwcleaner (1).exe', 0, 'adwcleaner (1).exe', 'Adwcleaner', '2025-06-24 15:02:41', '2025-06-24 15:19:36'),
(2, 'Eset', 'esetonlinescanner_enu.exe', 'uploads/autoit/nettoyage/esetonlinescanner_enu.exe', 0, 'esetonlinescanner_enu.exe', 'Eset Online Scanner', '2025-06-26 14:16:31', '2025-06-27 09:41:47'),
(3, 'CrystalDiskInfo Portable', 'CrystalDiskInfoPortable.zip', 'uploads/autoit/nettoyage/CrystalDiskInfoPortable.zip', 1, 'CrystalDiskInfoPortable.exe', 'Vérification Disque dur et SSD', '2025-06-27 09:44:15', '2025-06-27 09:44:15'),
(4, 'Temp File Cleaner', 'Temp_File_Cleaner.zip', 'uploads/autoit/nettoyage/Temp_File_Cleaner.zip', 1, 'Temp_File_Cleaner.exe', 'Nettoyage temporaire Windows', '2025-06-27 09:56:44', '2025-06-27 09:56:44'),
(5, 'Windows Update Mini Tool', 'windowsupdateminitools.zip', 'uploads/autoit/nettoyage/windowsupdateminitools.zip', 1, 'windowsupdateminitools.exe', 'Outils gestion Mise à jour Windows', '2025-06-27 09:57:56', '2025-06-27 09:57:56'),
(6, 'MalwareByte', 'MBSetup.exe', 'uploads/autoit/nettoyage/MBSetup.exe', 0, 'MBSetup.exe', 'Anti Spyware', '2025-06-27 09:59:31', '2025-06-27 09:59:31'),
(7, 'TreeSize', 'treesize.zip', 'uploads/autoit/nettoyage/treesize.zip', 1, 'treesize.exe', 'Vérification de l\'espace disque', '2025-06-27 10:00:11', '2025-06-27 10:00:18');

-- --------------------------------------------------------
-- Structure for table `autoit_personnalisation` (v2.8 ULTRA-FORCED)
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `autoit_personnalisation` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nom` varchar(255) NOT NULL,
  `type_registre` enum('fichier_reg','ligne_registre') NOT NULL,
  `fichier_reg_nom` varchar(255) DEFAULT NULL,
  `fichier_reg_path` varchar(500) DEFAULT NULL,
  `ligne_registre` text DEFAULT NULL,
  `description` text DEFAULT NULL,
  `OS` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Data for table `autoit_personnalisation` (11 rows) v2.8 ULTRA-FORCED
-- --------------------------------------------------------

INSERT INTO `autoit_personnalisation` (`id`, `nom`, `type_registre`, `fichier_reg_nom`, `fichier_reg_path`, `ligne_registre`, `description`, `OS`, `created_at`, `updated_at`) VALUES
(1, 'Desactive Recherche', 'ligne_registre', '', '', '[HKEY_CURRENT_USER\\Software\\Microsoft\\Windows\\CurrentVersion\\Search]\"SearchBoxTaskbarMode\"=REG_DWORD:1', 'Désactive l\'icone de Recherche

', 0, '2025-07-03 15:45:06', '2025-07-04 14:07:48'),
(2, 'Desactive Meteo', 'ligne_registre', '', '', '[HKEY_CURRENT_USER\\Software\\Microsoft\\Windows\\CurrentVersion\\Feeds]
\"ShellFeedsTaskbarViewMode\"=dword:00000002', '', 0, '2025-07-03 15:56:09', '2025-07-04 14:08:06'),
(4, 'Desactive Compte Microsoft 3 Jours', 'ligne_registre', '', '', '[HKEY_CURRENT_USER\\SOFTWARE\\Microsoft\\Windows\\CurrentVersion\\UserProfileEngagement]
\"ScoobeSystemSettingEnabled\"=dword:00000000', '', 0, '2025-07-04 14:09:19', '2025-07-04 14:09:19'),
(5, 'Icone Bureau Ce PC', 'ligne_registre', '', '', '[HKEY_CURRENT_USER\\Software\\Microsoft\\Windows\\CurrentVersion\\Explorer\\HideDesktopIcons\\NewStartPanel]
\"{20D04FE0-3AEA-1069-A2D8-08002B30309D}\"=dword:00000000', '', 0, '2025-07-04 14:09:53', '2025-07-04 14:09:53'),
(6, 'Icone Bureau Utilisateur', 'ligne_registre', '', '', '[HKEY_CURRENT_USER\\Software\\Microsoft\\Windows\\CurrentVersion\\Explorer\\HideDesktopIcons\\NewStartPanel]
\"{59031a47-3f72-44a7-89c5-5595fe6b30ee}\"=dword:00000000', '', 0, '2025-07-04 14:10:23', '2025-07-04 14:10:23'),
(7, 'Couleur theme noir', 'ligne_registre', '', '', '[HKEY_CURRENT_USER\\SOFTWARE\\Microsoft\\Windows\\CurrentVersion\\Themes\\Personalize]
\"SystemUsesLightTheme\"=dword:00000000', '', 0, '2025-07-04 14:10:54', '2025-07-04 14:10:54'),
(8, 'Icone barre des taches W10', 'ligne_registre', '', '', '[HKEY_CURRENT_USER\\SOFTWARE\\Microsoft\\Windows\\CurrentVersion\\Explorer]
\"EnableAutoTray\"=dword:00000000', '', 10, '2025-07-04 14:11:29', '2025-07-04 14:11:29'),
(9, 'Barre des taches a gauche W11', 'ligne_registre', '', '', '[HKEY_CURRENT_USER\\Software\\Microsoft\\Windows\\CurrentVersion\\Explorer\\Advanced]
\"TaskbarAl\"=dword:00000000', '', 11, '2025-07-04 14:11:59', '2025-07-04 14:11:59'),
(16, 'Notifications liées au compte', 'ligne_registre', '', '', '[HKEY_CURRENT_USER\\Software\\Microsoft\\Windows\\CurrentVersion\\Explorer\\Advanced]
\"Start_ShowAccountNotifications\"=dword:00000000', '', 11, '2025-07-10 14:18:54', '2025-07-10 14:18:54'),
(17, 'Suppr lim 260caract', 'ligne_registre', '', '', '[HKEY_LOCAL_MACHINE\\SYSTEM\\CurrentControlSet\\Control\\FileSystem]
\"LongPathsEnabled\"=dword:00000000', 'Suppression de la limitation des 260 caractères pour le nom de fichier', 0, '2025-09-03 12:16:06', '2025-09-03 12:16:06'),
(18, 'Ancien Menu pour W11', 'ligne_registre', '', '', '[HKEY_CURRENT_USER\\Software\\Classes\\CLSID\\{86ca1aa0-34aa-4e8b-a509-50c905bae2a2}\\InprocServer32]\"(default)\"=dword:00000000', 'Ancien menu contextuel pour W11', 11, '2025-09-18 16:15:09', '2025-09-18 16:17:34');

-- --------------------------------------------------------
-- Structure for table `catalog` (v2.8 ULTRA-FORCED)
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `catalog` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `marque` varchar(500) DEFAULT NULL,
  `famille` varchar(500) DEFAULT NULL,
  `part_number` varchar(500) DEFAULT NULL,
  `ref_acadia` varchar(500) DEFAULT NULL,
  `ean_code` varchar(500) DEFAULT NULL,
  `ref_constructeur` varchar(500) DEFAULT NULL,
  `designation` text DEFAULT NULL,
  `stock_reel` int(11) DEFAULT NULL,
  `prix_ht` decimal(10,2) DEFAULT NULL,
  `prix_client` decimal(10,2) DEFAULT NULL,
  `ecotaxe` decimal(10,2) DEFAULT NULL,
  `copie_privee` decimal(10,2) DEFAULT NULL,
  `poids` decimal(10,2) DEFAULT NULL,
  `image` text DEFAULT NULL,
  `categorie_principale` varchar(500) DEFAULT NULL,
  `categorie_secondaire` varchar(500) DEFAULT NULL,
  `categorie_tertiaire` varchar(500) DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `ref_acadia_ean_code` (`ref_acadia`,`ean_code`) USING HASH
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Structure for table `clients` (v2.8 ULTRA-FORCED)
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `clients` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `nom` varchar(28) DEFAULT NULL,
  `prenom` varchar(19) DEFAULT NULL,
  `adresse1` varchar(33) DEFAULT NULL,
  `adresse2` varchar(26) DEFAULT NULL,
  `cp` varchar(8) DEFAULT NULL,
  `ville` varchar(26) DEFAULT NULL,
  `pays` varchar(6) DEFAULT NULL,
  `telephone` varchar(15) DEFAULT NULL,
  `portable` varchar(15) DEFAULT NULL,
  `mail` varchar(33) DEFAULT NULL,
  PRIMARY KEY (`ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci ROW_FORMAT=COMPACT;

-- --------------------------------------------------------
-- Structure for table `configuration` (v2.8 ULTRA-FORCED)
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `configuration` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `config_key` varchar(100) NOT NULL,
  `config_value` text DEFAULT NULL,
  `config_type` enum('text','textarea','json','boolean','number') DEFAULT 'text',
  `description` text DEFAULT NULL,
  `category` varchar(50) DEFAULT 'general',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `config_key` (`config_key`)
) ENGINE=InnoDB AUTO_INCREMENT=26 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Data for table `configuration` (12 rows) v2.8 ULTRA-FORCED
-- --------------------------------------------------------

INSERT INTO `configuration` (`id`, `config_key`, `config_value`, `config_type`, `description`, `category`, `created_at`, `updated_at`) VALUES
(1, 'company_name', 'QLE INFORMATIQUE', 'text', 'Nom de la société affiché sur les documents', 'intervention_sheet', '2025-11-04 15:24:08', '2025-11-04 15:51:43'),
(2, 'intervention_tarifs', 'TARIFS : FRAIS DE PRISE EN CHARGE = 30 €
Petit Dépannage : 10 € TTC
Test Matériel : 30 € TTC
Forfait 0,5 heure : 45 € TTC
Forfait 1 heure : 70 € TTC
Forfait 2 heures : 140 € TTC
Forfait 1 journée : 420 € TTC
Forfait Réinstallation : 70 € TTC
Forfait récupération de données : 30 € TTC', 'textarea', 'Tarifs affichés sur la feuille d\'intervention', 'intervention_sheet', '2025-11-04 15:24:08', '2025-11-04 15:51:43'),
(3, 'intervention_cgv', 'CONDITIONS GÉNÉRALES DE VENTE
1. QLE Informatique ne pourra être tenu responsable de la perte de données lors de l\'intervention.

2. Le client s\'engage à effectuer une sauvegarde de ses données avant l\'intervention.

3. Le paiement est dû à réception de la facture.

4. Le matériel non récupéré dans un délai de 3 mois sera considéré comme abandonné.

5. Les tarifs indiqués sont TTC et peuvent être modifiés sans préavis.

6. La signature de cette fiche implique l\'acceptation de ces conditions.', 'textarea', 'Conditions générales de vente', 'intervention_sheet', '2025-11-04 15:24:08', '2025-11-04 15:51:43'),
(4, 'intervention_verifications', 'SEATOOLS
MEMTEST
ADW/ROGUE/MBAM/ESET
MAJ WINDOWS
NETTOYAGE OS
DEFRAG
INSTALL ANTIVIRUS', 'textarea', 'Liste des vérifications effectuées', 'intervention_sheet', '2025-11-04 15:24:08', '2025-11-04 15:51:43'),
(5, 'cyber_price_nb_page', 0.20, 'number', 'Prix par page impression noir et blanc (€)', 'cyber_pricing', '2025-11-04 15:44:06', '2025-11-04 15:51:43'),
(6, 'cyber_price_color_page', 0.30, 'number', 'Prix par page impression couleur (€)', 'cyber_pricing', '2025-11-04 15:44:06', '2025-11-04 15:51:43'),
(7, 'cyber_price_time_base', 0.75, 'number', 'Prix par tranche de 15 minutes (€)', 'cyber_pricing', '2025-11-04 15:44:06', '2025-11-04 15:51:43'),
(8, 'cyber_price_time_minimum', 0.50, 'number', 'Prix minimum pour 10 minutes ou moins (€)', 'cyber_pricing', '2025-11-04 15:44:06', '2025-11-04 15:51:43'),
(9, 'cyber_time_increment', 15, 'number', 'Incrément de temps en minutes pour la facturation', 'cyber_pricing', '2025-11-04 15:44:06', '2025-11-04 15:51:43'),
(14, 'cyber_time_minimum_threshold', 10, 'number', 'Seuil en minutes pour les sessions courtes', 'cyber_pricing', '2025-11-04 15:51:43', '2025-11-04 15:51:43'),
(24, 'acadia_catalog_url', 'https://www.acadia-info.com/module/acadia_catalogue/getCatalog?token=a250abed66d2506e8f1dec14c0ee35ed389', 'text', 'URL du catalogue Acadia pour l\'import des produits', 'acadia', '2025-11-05 09:56:09', '2025-11-05 09:56:09'),
(25, 'acadia_api_token', 'a250abed66d2506e8f1dec14c0ee35ed389', 'text', 'Token API pour l\'accès au catalogue Acadia', 'acadia', '2025-11-05 09:56:09', '2025-11-05 09:56:09');

-- --------------------------------------------------------
-- Structure for table `download` (v2.8 ULTRA-FORCED)
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `download` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `NOM` text NOT NULL,
  `DESCRIPTION` mediumtext NOT NULL,
  `URL` text NOT NULL,
  `show_on_login` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Afficher sur la page de login (0=non, 1=oui)',
  PRIMARY KEY (`ID`)
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Data for table `download` (6 rows) v2.8 ULTRA-FORCED
-- --------------------------------------------------------

INSERT INTO `download` (`ID`, `NOM`, `DESCRIPTION`, `URL`, `show_on_login`) VALUES
(9, 'Adwcleaner', 'Le nettoyeur de logiciels publicitaires le plus populaire au monde détecte et supprime
les programmes et des logiciels indésirables, vous garantissant une expérience en ligne optimale
et sans tracas.', 'https://adwcleaner.malwarebytes.com/adwcleaner?channel=release', 0),
(12, 'ESET Online Scanner', 'Scanner antivirus en ligne gratuit d\'ESET pour détecter et supprimer les menaces sur votre ordinateur.

', 'https://www.eset.com/int/home/online-scanner/', 0),
(13, 'Malwarebytes', 'Puissant outil anti-malware pour détecter et supprimer les logiciels malveillants, avec une version gratuite et une version premium.', 'https://www.malwarebytes.com/fr/mwb-download', 0),
(14, 'Kaspersky Virus Removal Tool', 'Outil gratuit de Kaspersky pour scanner et nettoyer votre système des virus et autres menaces.

', 'https://www.kaspersky.fr/downloads/free-virus-removal-tool', 0),
(15, 'Package Auto V3', 'Package Auto V3', 'http://192.168.10.247/Download/____Package_Auto.zip', 0),
(18, 'Télémaintenance', 'Télémaintenance QLE', 'https://qleinfo.fr/DL/Install.exe', 1);

-- --------------------------------------------------------
-- Structure for table `fournisseur` (v2.8 ULTRA-FORCED)
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `fournisseur` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `Fournisseur` text NOT NULL,
  PRIMARY KEY (`ID`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Data for table `fournisseur` (4 rows) v2.8 ULTRA-FORCED
-- --------------------------------------------------------

INSERT INTO `fournisseur` (`ID`, `Fournisseur`) VALUES
(1, 'Acadia'),
(2, 'Techdata'),
(3, 'Amazon'),
(5, 'test');

-- --------------------------------------------------------
-- Structure for table `helpdesk_cat` (v2.8 ULTRA-FORCED)
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `helpdesk_cat` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `CATEGORIE` text NOT NULL,
  PRIMARY KEY (`ID`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Data for table `helpdesk_cat` (8 rows) v2.8 ULTRA-FORCED
-- --------------------------------------------------------

INSERT INTO `helpdesk_cat` (`ID`, `CATEGORIE`) VALUES
(1, 'Appel'),
(2, 'Depannage'),
(3, 'Rendez Vous'),
(4, 'Commande en Cours'),
(5, 'SAV'),
(6, 'TODO'),
(7, 'Demande Devis'),
(8, 'Devis');

-- --------------------------------------------------------
-- Structure for table `helpdesk_msg` (v2.8 ULTRA-FORCED)
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `helpdesk_msg` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `CATEGORIE` text NOT NULL,
  `MESSAGE` longtext NOT NULL,
  `TITRE` text NOT NULL,
  `DATE` datetime DEFAULT NULL,
  `FAIT` int(11) NOT NULL,
  `DATE_FAIT` datetime DEFAULT NULL,
  PRIMARY KEY (`ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Structure for table `inter` (v2.8 ULTRA-FORCED)
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `inter` (
  `id` text NOT NULL,
  `id_client` mediumint(9) NOT NULL,
  `date` text NOT NULL,
  `en_cours` int(11) DEFAULT 1,
  `statut_id` int(11) DEFAULT NULL,
  `statuts_historique` longtext DEFAULT NULL,
  `info` longtext NOT NULL,
  `nettoyage` longtext NOT NULL,
  `info_log` longtext NOT NULL,
  `note_user` longtext NOT NULL,
  KEY `fk_inter_statut` (`statut_id`),
  CONSTRAINT `fk_inter_statut` FOREIGN KEY (`statut_id`) REFERENCES `intervention_statuts` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Structure for table `intervention_photos` (v2.8 ULTRA-FORCED)
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `intervention_photos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `intervention_id` text NOT NULL,
  `filename` varchar(255) NOT NULL,
  `original_filename` varchar(255) NOT NULL,
  `file_size` int(11) NOT NULL,
  `mime_type` varchar(100) NOT NULL,
  `width` int(11) DEFAULT NULL,
  `height` int(11) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `uploaded_at` timestamp NULL DEFAULT current_timestamp(),
  `uploaded_by` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_intervention_id` (`intervention_id`(50))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Structure for table `intervention_statuts` (v2.8 ULTRA-FORCED)
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `intervention_statuts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nom` varchar(100) NOT NULL,
  `couleur` varchar(7) NOT NULL DEFAULT '#007bff',
  `description` text DEFAULT NULL,
  `ordre_affichage` int(11) NOT NULL DEFAULT 0,
  `actif` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_nom` (`nom`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Structure for table `liens` (v2.8 ULTRA-FORCED)
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `liens` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `NOM` text NOT NULL,
  `DESCRIPTION` mediumtext NOT NULL,
  `URL` text NOT NULL,
  `show_on_login` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Afficher sur la page de login (0=non, 1=oui)',
  PRIMARY KEY (`ID`)
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Data for table `liens` (7 rows) v2.8 ULTRA-FORCED
-- --------------------------------------------------------

INSERT INTO `liens` (`ID`, `NOM`, `DESCRIPTION`, `URL`, `show_on_login`) VALUES
(6, 'Cybermalveillance.gouv.fr', 'Plateforme nationale d\'assistance aux victimes de cybermalveillance.', 'https://www.cybermalveillance.gouv.fr/', 0),
(7, 'ANSSI', 'Agence Nationale de la Sécurité des Systèmes d\'Information
L\'autorité nationale en matière de sécurité et de défense des systèmes d\'information.
', 'https://cyber.gouv.fr/', 0),
(8, 'CNIL', 'Commission Nationale de l\'Informatique et des Libertés
Autorité chargée de veiller à la protection des données personnelles.
', 'https://www.cnil.fr/fr', 0),
(9, 'Internet Signalement - Pharos', 'Plateforme officielle de signalement des contenus illicites sur internet.', 'https://www.internet-signalement.gouv.fr/PharosS1/', 0),
(10, 'Signal Spam', 'Association de lutte contre le spam et la cybercriminalité.', 'https://www.signal-spam.fr/', 0),
(11, 'Mon Brouteur', 'DÉNONCEZ VOTRE BROUTEUR
Si vous avez été victime d’un brouteur ou si vous pensez avoir été en contact avec un, il est aujourd’hui possible de le dénoncer et pourquoi pas se venger !', 'https://monbrouteur.net/', 1),
(12, 'Stirling PDF', 'Utilitaire Gestion PDF', 'http://192.168.10.251:8080', 0);

-- --------------------------------------------------------
-- Structure for table `mail_config` (v2.8 ULTRA-FORCED)
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `mail_config` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `smtp_host` varchar(255) NOT NULL,
  `smtp_port` int(11) NOT NULL DEFAULT 587,
  `smtp_username` varchar(255) NOT NULL,
  `smtp_password` varchar(255) NOT NULL,
  `smtp_encryption` enum('none','tls','ssl') NOT NULL DEFAULT 'tls',
  `from_name` varchar(255) NOT NULL,
  `from_email` varchar(255) NOT NULL,
  `reports_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `report_frequency` enum('daily','weekly','monthly') NOT NULL DEFAULT 'weekly',
  `report_recipients` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Data for table `mail_config` (1 rows) v2.8 ULTRA-FORCED
-- --------------------------------------------------------

INSERT INTO `mail_config` (`id`, `smtp_host`, `smtp_port`, `smtp_username`, `smtp_password`, `smtp_encryption`, `from_name`, `from_email`, `reports_enabled`, `report_frequency`, `report_recipients`, `created_at`, `updated_at`) VALUES
(1, 'smtp-mail.outlook.com', 587, 'techsuivi@outlook.fr', 'sQ7M#v5qAA3ja%JpXaHijbJR7r99%UPX', 'tls', 'TechSuivi', 'techsuivi@outlook.fr', 0, 'weekly', '[]', '2025-11-14 09:14:20', '2025-11-14 09:43:47');

-- --------------------------------------------------------
-- Structure for table `oauth2_config` (v2.8 ULTRA-FORCED)
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `oauth2_config` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `provider` varchar(50) NOT NULL,
  `client_id` text NOT NULL,
  `client_secret` text NOT NULL,
  `tenant_id` varchar(255) DEFAULT NULL,
  `redirect_uri` text NOT NULL,
  `scopes` text NOT NULL,
  `is_active` tinyint(1) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_provider` (`provider`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------
-- Structure for table `photos_settings` (v2.8 ULTRA-FORCED)
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `photos_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_name` varchar(50) NOT NULL,
  `setting_value` varchar(255) NOT NULL,
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_name` (`setting_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Structure for table `users` (v2.8 ULTRA-FORCED)
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Data for table `users` (1 rows) v2.8 ULTRA-FORCED
-- --------------------------------------------------------

INSERT INTO `users` (`id`, `username`, `password_hash`, `created_at`) VALUES
(1, 'admin', '$2y$10$whW0umJkhxI4lICGRv2mieuJHMcqMtWS31K9R9sOcyLyLGy/.BKy.', '2025-11-14 08:58:50');

COMMIT;
SET FOREIGN_KEY_CHECKS=1;

-- Backup completed v2.6: 28 tables, 79 total rows
