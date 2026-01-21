-- TechSuivi Database Backup
-- Generated on: 2025-11-05 13:51:50
-- Backup type: Partial (1 tables)
-- Format: SQL

SET FOREIGN_KEY_CHECKS=0;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;

-- --------------------------------------------------------
-- Structure for table `agenda`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `agenda`;
CREATE TABLE `agenda` (
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
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Data for table `agenda` (2 rows)
-- --------------------------------------------------------

INSERT INTO `agenda` (`id`, `titre`, `description`, `date_planifiee`, `date_creation`, `date_modification`, `priorite`, `statut`, `utilisateur`, `couleur`, `rappel_minutes`) VALUES
(3, 'Formation utilisateurs', 'Formation sur les nouvelles fonctionnalités', '2025-11-14 04:33:00', '2025-10-31 16:10:04', '2025-10-31 16:12:42', 'normale', 'planifie', 'admin', '#2ecc71', 0),
(4, 'Sauvegarde mensuelle', 'Vérification et sauvegarde complète des données', '2024-11-05 18:00:00', '2025-10-31 16:10:04', '2025-10-31 16:31:24', 'haute', 'termine', 'admin', '#f39c12', 0);

COMMIT;
SET FOREIGN_KEY_CHECKS=1;

-- Backup completed: 1 tables, 2 total rows
