-- TechSuivi Database Backup v2.8 ULTRA-FORCED
-- Generated on: 2025-12-29 17:33:30
-- Backup type: Partial (6 tables)
-- Format: SQL
-- Version: v2.8 ULTRA-FORCED FINAL UPDATE

SET FOREIGN_KEY_CHECKS=0;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;

-- --------------------------------------------------------
-- Structure for table `autoit_commandes` (v2.8 ULTRA-FORCED)
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `autoit_commandes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nom` varchar(255) NOT NULL,
  `commande` text NOT NULL,
  `description` text DEFAULT NULL,
  `defaut` tinyint(4) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Data for table `autoit_commandes` (6 rows) v2.8 ULTRA-FORCED
-- --------------------------------------------------------

INSERT INTO `autoit_commandes` (`id`, `nom`, `commande`, `description`, `defaut`, `created_at`, `updated_at`) VALUES
(1, 'CHKDSK /R', 'chkdsk /r', 'réparation du système de fichier', 0, '2025-06-24 15:01:42', '2025-12-11 10:51:42'),
(2, 'SFC /scannow', 'SFC /scannow', 'réparation du des fichiers', 0, '2025-06-25 08:31:44', '2025-06-25 08:31:44'),
(3, 'DEFRAG C: /U /V /O', 'DEFRAG C: /U /V /O', 'Defragmentation de C: en ligne de commande', 1, '2025-06-25 08:38:00', '2025-12-11 10:51:51'),
(4, 'CleanMGR', 'Cleanmgr', 'Nettoyage fichier système', 1, '2025-06-25 08:38:19', '2025-12-11 10:51:46'),
(5, 'Winget update', 'Winget update', 'Affichage de la liste des mises à jour de winget', 0, '2025-06-25 08:38:55', '2025-06-25 08:38:55'),
(6, 'Winget upgrade --all', 'winget upgrade --all', 'Mise à jour de tout winget', 1, '2025-06-25 08:39:19', '2025-12-11 10:51:55');

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
  `defaut` tinyint(4) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Data for table `autoit_logiciels` (14 rows) v2.8 ULTRA-FORCED
-- --------------------------------------------------------

INSERT INTO `autoit_logiciels` (`id`, `nom`, `type_installation`, `commande_winget`, `fichier_nom`, `fichier_path`, `est_zip`, `commande_lancement`, `description`, `defaut`, `created_at`, `updated_at`) VALUES
(1, 'Chrome', 'winget', 'google.chrome.exe', '', '', 0, '', '', 1, '2025-06-24 16:00:07', '2025-12-23 09:31:55'),
(2, 'Acrobat Reader 64 Bit', 'winget', 'Adobe.Acrobat.Reader.64-bit', '', '', 0, '', 'Lecteur PDF', 0, '2025-07-04 14:25:08', '2025-10-09 15:41:09'),
(3, 'Mozilla Firefox', 'winget', 'Mozilla.Firefox.fr', '', '', 0, '', '', 1, '2025-07-04 14:26:22', '2025-12-11 10:50:59'),
(4, 'BurnAware Free', 'winget', 'Burnaware.BurnAwareFree', '', '', 0, '', '', 0, '2025-07-04 14:27:31', '2025-07-04 14:27:31'),
(5, '7Zip', 'winget', ' 7zip.7zip', '', '', 0, '', '', 1, '2025-07-04 14:27:57', '2025-12-11 10:50:35'),
(6, 'VLC', 'winget', 'VideoLAN.VLC', '', '', 0, '', '', 1, '2025-07-04 14:28:22', '2025-12-11 10:51:35'),
(7, 'Mozilla Thunderbird', 'winget', 'Mozilla.Thunderbird.fr', '', '', 0, '', '', 1, '2025-07-04 14:29:01', '2025-12-11 10:51:04'),
(8, 'LibreOffice', 'winget', 'TheDocumentFoundation.LibreOffice', '', '', 0, '', '', 1, '2025-07-04 14:29:30', '2025-12-11 10:50:53'),
(9, 'Traystatus', 'winget', 'BinaryFortress.TrayStatus', '', '', 0, '', '', 0, '2025-07-04 14:33:29', '2025-07-04 14:33:29'),
(10, 'Télémaintenance', 'fichier', '', 'Install.exe', 'uploads/autoit/logiciels/Install.exe', 0, 'Install.exe', '', 1, '2025-07-04 14:36:15', '2025-12-11 10:51:28'),
(11, 'Picasa', 'fichier', '', 'picasa.zip', 'uploads/autoit/logiciels/picasa.zip', 1, 'automatisation picasa.exe', '', 1, '2025-07-04 14:37:54', '2025-12-11 10:51:10'),
(12, 'Theme', 'fichier', '', 'Theme.zip', 'uploads/autoit/logiciels/Theme.zip', 1, 'automatisation Theme.exe', '', 0, '2025-07-04 14:38:23', '2025-07-04 14:56:35'),
(13, 'Sensibilisation', 'fichier', '', 'sensibilisation.zip', 'uploads/autoit/logiciels/sensibilisation.zip', 1, 'automatisation sensibilisation.exe', 'Information Sensibilisation Informatique', 1, '2025-07-08 08:31:03', '2025-12-11 10:51:16'),
(14, 'Foxit PDF Reader', 'winget', 'Foxit.FoxitReader', '', '', 0, '', 'Lecteur PDF', 1, '2025-10-09 15:37:49', '2025-12-11 10:50:47');

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
  `defaut` tinyint(4) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Data for table `autoit_nettoyage` (7 rows) v2.8 ULTRA-FORCED
-- --------------------------------------------------------

INSERT INTO `autoit_nettoyage` (`id`, `nom`, `fichier_nom`, `fichier_path`, `est_zip`, `commande_lancement`, `description`, `defaut`, `created_at`, `updated_at`) VALUES
(1, 'Adwcleaner', 'adwcleaner (1).exe', 'uploads/autoit/nettoyage/adwcleaner (1).exe', 0, 'adwcleaner (1).exe', 'Adwcleaner', 0, '2025-06-24 15:02:41', '2025-06-24 15:19:36'),
(2, 'Eset', 'esetonlinescanner_enu.exe', 'uploads/autoit/nettoyage/esetonlinescanner_enu.exe', 0, 'esetonlinescanner_enu.exe', 'Eset Online Scanner', 0, '2025-06-26 14:16:31', '2025-06-27 09:41:47'),
(3, 'CrystalDiskInfo Portable', 'CrystalDiskInfoPortable.zip', 'uploads/autoit/nettoyage/CrystalDiskInfoPortable.zip', 1, 'CrystalDiskInfoPortable.exe', 'Vérification Disque dur et SSD', 0, '2025-06-27 09:44:15', '2025-06-27 09:44:15'),
(4, 'Temp File Cleaner', 'Temp_File_Cleaner.zip', 'uploads/autoit/nettoyage/Temp_File_Cleaner.zip', 1, 'Temp_File_Cleaner.exe', 'Nettoyage temporaire Windows', 0, '2025-06-27 09:56:44', '2025-06-27 09:56:44'),
(5, 'Windows Update Mini Tool', 'windowsupdateminitools.zip', 'uploads/autoit/nettoyage/windowsupdateminitools.zip', 1, 'windowsupdateminitools.exe', 'Outils gestion Mise à jour Windows', 0, '2025-06-27 09:57:56', '2025-06-27 09:57:56'),
(6, 'MalwareByte', 'MBSetup.exe', 'uploads/autoit/nettoyage/MBSetup.exe', 0, 'MBSetup.exe', 'Anti Spyware', 0, '2025-06-27 09:59:31', '2025-06-27 09:59:31'),
(7, 'TreeSize', 'treesize.zip', 'uploads/autoit/nettoyage/treesize.zip', 1, 'treesize.exe', 'Vérification de l\'espace disque', 0, '2025-06-27 10:00:11', '2025-06-27 10:00:18');

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
  `defaut` tinyint(4) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Data for table `autoit_personnalisation` (11 rows) v2.8 ULTRA-FORCED
-- --------------------------------------------------------

INSERT INTO `autoit_personnalisation` (`id`, `nom`, `type_registre`, `fichier_reg_nom`, `fichier_reg_path`, `ligne_registre`, `description`, `OS`, `defaut`, `created_at`, `updated_at`) VALUES
(1, 'Desactive Recherche', 'ligne_registre', '', '', '[HKEY_CURRENT_USER\\Software\\Microsoft\\Windows\\CurrentVersion\\Search]\"SearchBoxTaskbarMode\"=REG_DWORD:1', 'Désactive l\'icone de Recherche ', 0, 0, '2025-07-03 15:45:06', '2025-07-04 14:07:48'),
(2, 'Desactive Meteo', 'ligne_registre', '', '', '[HKEY_CURRENT_USER\\Software\\Microsoft\\Windows\\CurrentVersion\\Feeds] \"ShellFeedsTaskbarViewMode\"=dword:00000002', '', 0, 0, '2025-07-03 15:56:09', '2025-07-04 14:08:06'),
(4, 'Desactive Compte Microsoft 3 Jours', 'ligne_registre', '', '', '[HKEY_CURRENT_USER\\SOFTWARE\\Microsoft\\Windows\\CurrentVersion\\UserProfileEngagement] \"ScoobeSystemSettingEnabled\"=dword:00000000', '', 0, 0, '2025-07-04 14:09:19', '2025-07-04 14:09:19'),
(5, 'Icone Bureau Ce PC', 'ligne_registre', '', '', '[HKEY_CURRENT_USER\\Software\\Microsoft\\Windows\\CurrentVersion\\Explorer\\HideDesktopIcons\\NewStartPanel] \"{20D04FE0-3AEA-1069-A2D8-08002B30309D}\"=dword:00000000', '', 0, 0, '2025-07-04 14:09:53', '2025-07-04 14:09:53'),
(6, 'Icone Bureau Utilisateur', 'ligne_registre', '', '', '[HKEY_CURRENT_USER\\Software\\Microsoft\\Windows\\CurrentVersion\\Explorer\\HideDesktopIcons\\NewStartPanel] \"{59031a47-3f72-44a7-89c5-5595fe6b30ee}\"=dword:00000000', '', 0, 0, '2025-07-04 14:10:23', '2025-07-04 14:10:23'),
(7, 'Couleur theme noir', 'ligne_registre', '', '', '[HKEY_CURRENT_USER\\SOFTWARE\\Microsoft\\Windows\\CurrentVersion\\Themes\\Personalize] \"SystemUsesLightTheme\"=dword:00000000', '', 0, 0, '2025-07-04 14:10:54', '2025-07-04 14:10:54'),
(8, 'Icone barre des taches W10', 'ligne_registre', '', '', '[HKEY_CURRENT_USER\\SOFTWARE\\Microsoft\\Windows\\CurrentVersion\\Explorer] \"EnableAutoTray\"=dword:00000000', '', 10, 0, '2025-07-04 14:11:29', '2025-07-04 14:11:29'),
(9, 'Barre des taches a gauche W11', 'ligne_registre', '', '', '[HKEY_CURRENT_USER\\Software\\Microsoft\\Windows\\CurrentVersion\\Explorer\\Advanced] \"TaskbarAl\"=dword:00000000', '', 11, 0, '2025-07-04 14:11:59', '2025-07-04 14:11:59'),
(16, 'Notifications liées au compte', 'ligne_registre', '', '', '[HKEY_CURRENT_USER\\Software\\Microsoft\\Windows\\CurrentVersion\\Explorer\\Advanced] \"Start_ShowAccountNotifications\"=dword:00000000', '', 11, 0, '2025-07-10 14:18:54', '2025-07-10 14:18:54'),
(17, 'Suppr lim 260caract', 'ligne_registre', '', '', '[HKEY_LOCAL_MACHINE\\SYSTEM\\CurrentControlSet\\Control\\FileSystem] \"LongPathsEnabled\"=dword:00000000', 'Suppression de la limitation des 260 caractères pour le nom de fichier', 0, 0, '2025-09-03 12:16:06', '2025-09-03 12:16:06'),
(18, 'Ancien Menu pour W11', 'ligne_registre', '', '', '[HKEY_CURRENT_USER\\Software\\Classes\\CLSID\\{86ca1aa0-34aa-4e8b-a509-50c905bae2a2}\\InprocServer32]\"(default)\"=dword:00000000', 'Ancien menu contextuel pour W11', 11, 0, '2025-09-18 16:15:09', '2025-09-18 16:17:34');

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
) ENGINE=InnoDB AUTO_INCREMENT=26 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Data for table `download` (7 rows) v2.8 ULTRA-FORCED
-- --------------------------------------------------------

INSERT INTO `download` (`ID`, `NOM`, `DESCRIPTION`, `URL`, `show_on_login`) VALUES
(9, 'Adwcleaner', 'Le nettoyeur de logiciels publicitaires le plus populaire au monde détecte et supprime les programmes et des logiciels indésirables, vous garantissant une expérience en ligne optimale et sans tracas.', 'https://adwcleaner.malwarebytes.com/adwcleaner?channel=release', 0),
(12, 'ESET Online Scanner', 'Scanner antivirus en ligne gratuit d\'ESET pour détecter et supprimer les menaces sur votre ordinateur. ', 'https://www.eset.com/int/home/online-scanner/', 0),
(13, 'Malwarebytes', 'Puissant outil anti-malware pour détecter et supprimer les logiciels malveillants, avec une version gratuite et une version premium.', 'https://www.malwarebytes.com/fr/mwb-download', 0),
(14, 'Kaspersky Virus Removal Tool', 'Outil gratuit de Kaspersky pour scanner et nettoyer votre système des virus et autres menaces. ', 'https://www.kaspersky.fr/downloads/free-virus-removal-tool', 0),
(15, 'Package Auto V3', 'Package Auto V3', 'http://192.168.10.247/Download/____Package_Auto.zip', 0),
(18, 'Télémaintenance', 'Télémaintenance QLE', 'https://qleinfo.fr/DL/Install.exe', 1),
(20, 'TechSuivi', 'TechSuivi Installeur', 'http://192.168.10.248/Download/installeur_687474703A2F2F3139322E3136382E31302E3234382F446F776E6C6F61642F496E7374616C6C2F.exe', 1);

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
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Data for table `liens` (7 rows) v2.8 ULTRA-FORCED
-- --------------------------------------------------------

INSERT INTO `liens` (`ID`, `NOM`, `DESCRIPTION`, `URL`, `show_on_login`) VALUES
(6, 'Cybermalveillance.gouv.fr', 'Plateforme nationale d\'assistance aux victimes de cybermalveillance.', 'https://www.cybermalveillance.gouv.fr/', 0),
(7, 'ANSSI', 'Agence Nationale de la Sécurité des Systèmes d\'Information L\'autorité nationale en matière de sécurité et de défense des systèmes d\'information.', 'https://cyber.gouv.fr/', 0),
(8, 'CNIL', 'Commission Nationale de l\'Informatique et des Libertés Autorité chargée de veiller à la protection des données personnelles. ', 'https://www.cnil.fr/fr', 0),
(9, 'Internet Signalement - Pharos', 'Plateforme officielle de signalement des contenus illicites sur internet.', 'https://www.internet-signalement.gouv.fr/PharosS1/', 0),
(10, 'Signal Spam', 'Association de lutte contre le spam et la cybercriminalité.', 'https://www.signal-spam.fr/', 0),
(11, 'Mon Brouteur', 'DÉNONCEZ VOTRE BROUTEUR Si vous avez été victime d’un brouteur ou si vous pensez avoir été en contact avec un, il est aujourd’hui possible de le dénoncer et pourquoi pas se venger !', 'https://monbrouteur.net/', 1),
(12, 'Stirling PDF', 'Utilitaire Gestion PDF', 'http://192.168.10.251:8080', 0);

COMMIT;
SET FOREIGN_KEY_CHECKS=1;

-- Backup completed v2.6: 6 tables, 52 total rows
