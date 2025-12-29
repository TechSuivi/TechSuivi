-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Hôte : db
-- Généré le : lun. 29 déc. 2025 à 15:59
-- Version du serveur : 10.11.15-MariaDB-ubu2204
-- Version de PHP : 8.2.27

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de données : `techsuivi_db`
--

-- --------------------------------------------------------

--
-- Structure de la table `agenda`
--

CREATE TABLE `agenda` (
  `id` int(11) NOT NULL,
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
  `id_client` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `app_config`
--

CREATE TABLE `app_config` (
  `config_key` varchar(100) NOT NULL,
  `config_value` text DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `app_config`
--

INSERT INTO `app_config` (`config_key`, `config_value`, `updated_at`) VALUES
('timezone_offset', '2', '2025-06-19 09:08:12');

-- --------------------------------------------------------

--
-- Structure de la table `autoit_commandes`
--

CREATE TABLE `autoit_commandes` (
  `id` int(11) NOT NULL,
  `nom` varchar(255) NOT NULL,
  `commande` text NOT NULL,
  `description` text DEFAULT NULL,
  `defaut` tinyint(4) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `autoit_logiciels`
--

CREATE TABLE `autoit_logiciels` (
  `id` int(11) NOT NULL,
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
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `autoit_nettoyage`
--

CREATE TABLE `autoit_nettoyage` (
  `id` int(11) NOT NULL,
  `nom` varchar(255) NOT NULL,
  `fichier_nom` varchar(255) DEFAULT NULL,
  `fichier_path` varchar(500) DEFAULT NULL,
  `est_zip` tinyint(1) DEFAULT 0,
  `commande_lancement` text NOT NULL,
  `description` text DEFAULT NULL,
  `defaut` tinyint(4) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `autoit_personnalisation`
--

CREATE TABLE `autoit_personnalisation` (
  `id` int(11) NOT NULL,
  `nom` varchar(255) NOT NULL,
  `type_registre` enum('fichier_reg','ligne_registre') NOT NULL,
  `fichier_reg_nom` varchar(255) DEFAULT NULL,
  `fichier_reg_path` varchar(500) DEFAULT NULL,
  `ligne_registre` text DEFAULT NULL,
  `description` text DEFAULT NULL,
  `OS` int(11) NOT NULL,
  `defaut` tinyint(4) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `catalog`
--

CREATE TABLE `catalog` (
  `id` int(11) NOT NULL,
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
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `clients`
--

CREATE TABLE `clients` (
  `ID` int(11) NOT NULL,
  `nom` varchar(80) DEFAULT NULL,
  `prenom` varchar(80) DEFAULT NULL,
  `adresse1` varchar(33) DEFAULT NULL,
  `adresse2` varchar(26) DEFAULT NULL,
  `cp` varchar(8) DEFAULT NULL,
  `ville` varchar(26) DEFAULT NULL,
  `pays` varchar(6) DEFAULT NULL,
  `telephone` varchar(15) DEFAULT NULL,
  `portable` varchar(15) DEFAULT NULL,
  `mail` varchar(33) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci ROW_FORMAT=COMPACT;

-- --------------------------------------------------------

--
-- Structure de la table `configuration`
--

CREATE TABLE `configuration` (
  `id` int(11) NOT NULL,
  `config_key` varchar(100) NOT NULL,
  `config_value` text DEFAULT NULL,
  `config_type` enum('text','textarea','json','boolean','number') DEFAULT 'text',
  `description` text DEFAULT NULL,
  `category` varchar(50) DEFAULT 'general',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `download`
--

CREATE TABLE `download` (
  `ID` int(11) NOT NULL,
  `NOM` text NOT NULL,
  `DESCRIPTION` mediumtext NOT NULL,
  `URL` text NOT NULL,
  `show_on_login` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Afficher sur la page de login (0=non, 1=oui)'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `FC_cyber`
--

CREATE TABLE `FC_cyber` (
  `id` int(11) NOT NULL,
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
  `paye_par_credit` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `FC_cyber_credits`
--

CREATE TABLE `FC_cyber_credits` (
  `id` int(11) NOT NULL,
  `nom_client` varchar(255) NOT NULL,
  `solde_actuel` decimal(10,2) DEFAULT 0.00,
  `date_creation` timestamp NULL DEFAULT current_timestamp(),
  `date_modification` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `actif` tinyint(1) DEFAULT 1,
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `FC_cyber_credits_historique`
--

CREATE TABLE `FC_cyber_credits_historique` (
  `id` int(11) NOT NULL,
  `credit_id` int(11) NOT NULL,
  `type_mouvement` enum('AJOUT','DEDUCTION','CORRECTION') NOT NULL,
  `montant` decimal(10,2) NOT NULL,
  `solde_avant` decimal(10,2) NOT NULL,
  `solde_apres` decimal(10,2) NOT NULL,
  `description` text DEFAULT NULL,
  `session_cyber_id` int(11) DEFAULT NULL,
  `date_mouvement` timestamp NULL DEFAULT current_timestamp(),
  `utilisateur` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `FC_feuille_caisse`
--

CREATE TABLE `FC_feuille_caisse` (
  `id` int(11) NOT NULL,
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
  `retrait_pieces_001` int(11) DEFAULT 0 COMMENT 'Retrait banque - Pièces 0,01€',
  `retrait_pieces_002` int(11) DEFAULT 0 COMMENT 'Retrait banque - Pièces 0,02€',
  `retrait_pieces_005` int(11) DEFAULT 0 COMMENT 'Retrait banque - Pièces 0,05€',
  `retrait_pieces_010` int(11) DEFAULT 0 COMMENT 'Retrait banque - Pièces 0,10€',
  `retrait_pieces_020` int(11) DEFAULT 0 COMMENT 'Retrait banque - Pièces 0,20€',
  `retrait_pieces_050` int(11) DEFAULT 0 COMMENT 'Retrait banque - Pièces 0,50€',
  `retrait_pieces_100` int(11) DEFAULT 0 COMMENT 'Retrait banque - Pièces 1,00€',
  `retrait_pieces_200` int(11) DEFAULT 0 COMMENT 'Retrait banque - Pièces 2,00€',
  `retrait_billets_005` int(11) DEFAULT 0 COMMENT 'Retrait banque - Billets 5€',
  `retrait_billets_010` int(11) DEFAULT 0 COMMENT 'Retrait banque - Billets 10€',
  `retrait_billets_020` int(11) DEFAULT 0 COMMENT 'Retrait banque - Billets 20€',
  `retrait_billets_050` int(11) DEFAULT 0 COMMENT 'Retrait banque - Billets 50€',
  `retrait_billets_100` int(11) DEFAULT 0 COMMENT 'Retrait banque - Billets 100€',
  `retrait_billets_200` int(11) DEFAULT 0 COMMENT 'Retrait banque - Billets 200€',
  `retrait_billets_500` int(11) DEFAULT 0 COMMENT 'Retrait banque - Billets 500€',
  `total_retrait_pieces` decimal(10,2) DEFAULT 0.00 COMMENT 'Total retraits pièces',
  `total_retrait_billets` decimal(10,2) DEFAULT 0.00 COMMENT 'Total retraits billets',
  `total_retrait_especes` decimal(10,2) DEFAULT 0.00 COMMENT 'Total retraits espèces'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `FC_moyens_paiement`
--

CREATE TABLE `FC_moyens_paiement` (
  `id` int(11) NOT NULL,
  `moyen` varchar(50) DEFAULT NULL,
  `montant` decimal(10,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `FC_moyens_paiement`
--

INSERT INTO `FC_moyens_paiement` (`id`, `moyen`, `montant`) VALUES
(1, 'CB', 0.00),
(2, 'CHEQUES', 0.00),
(3, 'ESPECES', 0.00),
(4, 'VIREMENT', 0.00),
(6, 'NON REGLE', 0.00);

-- --------------------------------------------------------

--
-- Structure de la table `FC_transactions`
--

CREATE TABLE `FC_transactions` (
  `id` int(11) NOT NULL,
  `nom` varchar(100) DEFAULT NULL,
  `montant` decimal(10,2) DEFAULT NULL,
  `type` varchar(50) DEFAULT NULL,
  `banque` varchar(100) DEFAULT NULL,
  `num_cheque` varchar(50) DEFAULT NULL,
  `acompte` decimal(10,2) DEFAULT NULL,
  `solde` decimal(10,2) DEFAULT NULL,
  `num_facture` varchar(50) DEFAULT NULL,
  `date_transaction` timestamp NULL DEFAULT current_timestamp(),
  `paye_le` date NOT NULL,
  `id_client` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `fournisseur`
--

CREATE TABLE `fournisseur` (
  `ID` int(11) NOT NULL,
  `Fournisseur` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `helpdesk_cat`
--

CREATE TABLE `helpdesk_cat` (
  `ID` int(11) NOT NULL,
  `CATEGORIE` text NOT NULL,
  `couleur` char(7) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `helpdesk_cat`
--

INSERT INTO `helpdesk_cat` (`ID`, `CATEGORIE`, `couleur`) VALUES
(1, 'Appel', '#f50505'),
(2, 'Depannage', '#0aff0e'),
(3, 'Rendez Vous', '#d2f71d'),
(4, 'Commande en Cours', '#c70fc1'),
(5, 'SAV', '#894506'),
(6, 'TODO', '#fe71fa'),
(7, 'Demande Devis', '#601ddd'),
(8, 'Devis', '#189ab4');

-- --------------------------------------------------------

--
-- Structure de la table `helpdesk_msg`
--

CREATE TABLE `helpdesk_msg` (
  `ID` int(11) NOT NULL,
  `CATEGORIE` text NOT NULL,
  `MESSAGE` longtext NOT NULL,
  `id_client` int(11) DEFAULT NULL,
  `TITRE` text NOT NULL,
  `DATE` datetime DEFAULT NULL,
  `FAIT` int(11) NOT NULL,
  `DATE_FAIT` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `helpdesk_reponses`
--

CREATE TABLE `helpdesk_reponses` (
  `ID` int(11) NOT NULL,
  `MESSAGE_ID` int(11) NOT NULL,
  `MESSAGE` longtext NOT NULL,
  `DATE_REPONSE` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `inter`
--

CREATE TABLE `inter` (
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
  `ip_vnc` varchar(255) DEFAULT NULL,
  `pass_vnc` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `intervention_photos`
--

CREATE TABLE `intervention_photos` (
  `id` int(11) NOT NULL,
  `intervention_id` text NOT NULL,
  `filename` varchar(255) NOT NULL,
  `original_filename` varchar(255) NOT NULL,
  `file_size` int(11) NOT NULL,
  `mime_type` varchar(100) NOT NULL,
  `width` int(11) DEFAULT NULL,
  `height` int(11) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `uploaded_at` timestamp NULL DEFAULT current_timestamp(),
  `uploaded_by` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `intervention_statuts`
--

CREATE TABLE `intervention_statuts` (
  `id` int(11) NOT NULL,
  `nom` varchar(100) NOT NULL,
  `couleur` varchar(7) NOT NULL DEFAULT '#007bff',
  `description` text DEFAULT NULL,
  `ordre_affichage` int(11) NOT NULL DEFAULT 0,
  `actif` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `intervention_statuts`
--

INSERT INTO `intervention_statuts` (`id`, `nom`, `couleur`, `description`, `ordre_affichage`, `actif`, `created_at`, `updated_at`) VALUES
(1, 'En cours', '#ffc107', 'Intervention en cours de traitement', 1, 1, '2025-06-18 12:22:37', '2025-06-18 12:22:37'),
(2, 'En attente', '#6c757d', 'Intervention en attente d\'information client', 2, 1, '2025-06-18 12:22:37', '2025-06-19 12:37:16'),
(3, 'Téléphoné', '#17a2b8', 'Client contacté par téléphone', 3, 1, '2025-06-18 12:22:37', '2025-06-18 12:22:37'),
(5, 'Pièces commandées', '#e83e8c', 'En attente de réception des pièces', 5, 1, '2025-06-18 12:22:37', '2025-06-18 12:22:37'),
(6, 'Clôturée', '#28a745', 'Intervention terminée et clôturée', 6, 1, '2025-06-18 12:22:37', '2025-06-18 12:22:37'),
(16, 'Répondeur', '#fd7e14', 'Message laissé sur le répondeur', 4, 1, '2025-06-18 12:56:21', '2025-06-19 12:36:44');

-- --------------------------------------------------------

--
-- Structure de la table `inventory_items`
--

CREATE TABLE `inventory_items` (
  `id` int(11) NOT NULL,
  `session_id` int(11) NOT NULL,
  `ean_code` varchar(100) DEFAULT NULL,
  `designation` varchar(255) NOT NULL,
  `quantity` int(11) NOT NULL,
  `prix_achat_ht` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `inventory_sessions`
--

CREATE TABLE `inventory_sessions` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `status` enum('OPEN','CLOSED') DEFAULT 'OPEN'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `liens`
--

CREATE TABLE `liens` (
  `ID` int(11) NOT NULL,
  `NOM` text NOT NULL,
  `DESCRIPTION` mediumtext NOT NULL,
  `URL` text NOT NULL,
  `show_on_login` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Afficher sur la page de login (0=non, 1=oui)'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `mail_config`
--

CREATE TABLE `mail_config` (
  `id` int(11) NOT NULL,
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
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `oauth2_config`
--

CREATE TABLE `oauth2_config` (
  `id` int(11) NOT NULL,
  `provider` varchar(50) NOT NULL,
  `client_id` text NOT NULL,
  `client_secret` text NOT NULL,
  `tenant_id` varchar(255) DEFAULT NULL,
  `redirect_uri` text NOT NULL,
  `scopes` text NOT NULL,
  `is_active` tinyint(1) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `photos_settings`
--

CREATE TABLE `photos_settings` (
  `id` int(11) NOT NULL,
  `setting_name` varchar(50) NOT NULL,
  `setting_value` varchar(255) NOT NULL,
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `photos_settings`
--

INSERT INTO `photos_settings` (`id`, `setting_name`, `setting_value`, `updated_at`) VALUES
(1, 'max_width', '1920', '2025-12-19 17:24:29'),
(2, 'max_height', '1080', '2025-12-19 17:24:29'),
(3, 'thumb_size', '300', '2025-06-11 09:51:24'),
(4, 'max_file_size', '10', '2025-06-11 09:51:24'),
(5, 'quality', '85', '2025-06-11 09:51:24');

-- --------------------------------------------------------

--
-- Structure de la table `report_templates`
--

CREATE TABLE `report_templates` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `report_type` varchar(255) NOT NULL,
  `parameters` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`parameters`)),
  `content_template` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `scheduled_tasks`
--

CREATE TABLE `scheduled_tasks` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL COMMENT 'Nom de la tâche',
  `description` text DEFAULT NULL COMMENT 'Description détaillée',
  `task_type` enum('report','notification','backup_reminder','custom') NOT NULL COMMENT 'Type de tâche',
  `frequency_type` enum('once','daily','weekly','monthly','custom_cron') NOT NULL COMMENT 'Type de fréquence',
  `frequency_value` varchar(100) DEFAULT NULL COMMENT 'Valeur pour cron custom (ex: 0 8 * * 1)',
  `specific_time` time DEFAULT '08:00:00' COMMENT 'Heure spécifique d''exécution',
  `specific_days` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Jours spécifiques [1,2,3,4,5] pour lun-ven' CHECK (json_valid(`specific_days`)),
  `recipients` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL COMMENT 'Liste des destinataires ["email1@test.com", "email2@test.com"]' CHECK (json_valid(`recipients`)),
  `content_template` text DEFAULT NULL COMMENT 'Template de contenu du mail',
  `conditions_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Conditions pour déclencher la tâche' CHECK (json_valid(`conditions_json`)),
  `is_active` tinyint(1) DEFAULT 1 COMMENT 'Tâche active ou non',
  `last_executed` datetime DEFAULT NULL COMMENT 'Dernière exécution',
  `next_execution` datetime DEFAULT NULL COMMENT 'Prochaine exécution calculée',
  `execution_count` int(11) DEFAULT 0 COMMENT 'Nombre d''exécutions',
  `created_at` timestamp NULL DEFAULT current_timestamp() COMMENT 'Date de création',
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'Date de modification',
  `report_template_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Table des tâches programmées pour l''envoi d''emails';

-- --------------------------------------------------------

--
-- Structure de la table `scheduled_tasks_mail_logs`
--

CREATE TABLE `scheduled_tasks_mail_logs` (
  `id` int(11) NOT NULL,
  `task_id` int(11) NOT NULL,
  `recipient_email` varchar(255) NOT NULL,
  `subject` varchar(500) NOT NULL,
  `status` enum('success','failed') NOT NULL,
  `error_message` text DEFAULT NULL,
  `sent_at` timestamp NULL DEFAULT current_timestamp(),
  `execution_time_ms` int(11) DEFAULT 0,
  `mail_size_bytes` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `Stock`
--

CREATE TABLE `Stock` (
  `id` int(11) NOT NULL,
  `ref_acadia` varchar(255) DEFAULT NULL,
  `ean_code` varchar(255) NOT NULL,
  `SN` longtext NOT NULL,
  `designation` text NOT NULL,
  `prix_achat_ht` decimal(10,2) NOT NULL,
  `prix_vente_ttc` decimal(10,2) NOT NULL,
  `date_ajout` timestamp NULL DEFAULT current_timestamp(),
  `fournisseur` varchar(255) DEFAULT NULL,
  `numero_commande` varchar(255) DEFAULT NULL,
  `date_commande` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `stock_documents`
--

CREATE TABLE `stock_documents` (
  `id` int(11) NOT NULL,
  `fournisseur` varchar(255) NOT NULL,
  `numero_commande` varchar(255) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `original_name` varchar(255) NOT NULL,
  `date_ajout` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `reset_token` varchar(64) DEFAULT NULL,
  `reset_expires` datetime DEFAULT NULL,
  `email` varchar(254) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Index pour les tables déchargées
--

--
-- Index pour la table `agenda`
--
ALTER TABLE `agenda`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_date_planifiee` (`date_planifiee`),
  ADD KEY `idx_statut` (`statut`),
  ADD KEY `idx_utilisateur` (`utilisateur`);

--
-- Index pour la table `app_config`
--
ALTER TABLE `app_config`
  ADD PRIMARY KEY (`config_key`);

--
-- Index pour la table `autoit_commandes`
--
ALTER TABLE `autoit_commandes`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `autoit_logiciels`
--
ALTER TABLE `autoit_logiciels`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `autoit_nettoyage`
--
ALTER TABLE `autoit_nettoyage`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `autoit_personnalisation`
--
ALTER TABLE `autoit_personnalisation`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `catalog`
--
ALTER TABLE `catalog`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ref_acadia_ean_code` (`ref_acadia`,`ean_code`) USING HASH;

--
-- Index pour la table `clients`
--
ALTER TABLE `clients`
  ADD PRIMARY KEY (`ID`);

--
-- Index pour la table `configuration`
--
ALTER TABLE `configuration`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `config_key` (`config_key`);

--
-- Index pour la table `download`
--
ALTER TABLE `download`
  ADD PRIMARY KEY (`ID`);

--
-- Index pour la table `FC_cyber`
--
ALTER TABLE `FC_cyber`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_credit_id` (`credit_id`),
  ADD KEY `idx_paye_par_credit` (`paye_par_credit`);

--
-- Index pour la table `FC_cyber_credits`
--
ALTER TABLE `FC_cyber_credits`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_nom_client` (`nom_client`),
  ADD KEY `idx_actif` (`actif`);

--
-- Index pour la table `FC_cyber_credits_historique`
--
ALTER TABLE `FC_cyber_credits_historique`
  ADD PRIMARY KEY (`id`),
  ADD KEY `session_cyber_id` (`session_cyber_id`),
  ADD KEY `idx_credit_id` (`credit_id`),
  ADD KEY `idx_type_mouvement` (`type_mouvement`),
  ADD KEY `idx_date_mouvement` (`date_mouvement`);

--
-- Index pour la table `FC_feuille_caisse`
--
ALTER TABLE `FC_feuille_caisse`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_date_comptage` (`date_comptage`);

--
-- Index pour la table `FC_moyens_paiement`
--
ALTER TABLE `FC_moyens_paiement`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `FC_transactions`
--
ALTER TABLE `FC_transactions`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `fournisseur`
--
ALTER TABLE `fournisseur`
  ADD PRIMARY KEY (`ID`);

--
-- Index pour la table `helpdesk_cat`
--
ALTER TABLE `helpdesk_cat`
  ADD PRIMARY KEY (`ID`);

--
-- Index pour la table `helpdesk_msg`
--
ALTER TABLE `helpdesk_msg`
  ADD PRIMARY KEY (`ID`);

--
-- Index pour la table `helpdesk_reponses`
--
ALTER TABLE `helpdesk_reponses`
  ADD PRIMARY KEY (`ID`),
  ADD KEY `idx_message_id` (`MESSAGE_ID`);

--
-- Index pour la table `inter`
--
ALTER TABLE `inter`
  ADD KEY `fk_inter_statut` (`statut_id`);

--
-- Index pour la table `intervention_photos`
--
ALTER TABLE `intervention_photos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_intervention_id` (`intervention_id`(50));

--
-- Index pour la table `intervention_statuts`
--
ALTER TABLE `intervention_statuts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_nom` (`nom`);

--
-- Index pour la table `inventory_items`
--
ALTER TABLE `inventory_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `session_id` (`session_id`);

--
-- Index pour la table `inventory_sessions`
--
ALTER TABLE `inventory_sessions`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `liens`
--
ALTER TABLE `liens`
  ADD PRIMARY KEY (`ID`);

--
-- Index pour la table `mail_config`
--
ALTER TABLE `mail_config`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `oauth2_config`
--
ALTER TABLE `oauth2_config`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_provider` (`provider`);

--
-- Index pour la table `photos_settings`
--
ALTER TABLE `photos_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_name` (`setting_name`);

--
-- Index pour la table `report_templates`
--
ALTER TABLE `report_templates`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_report_type` (`report_type`),
  ADD KEY `idx_is_active` (`is_active`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Index pour la table `scheduled_tasks`
--
ALTER TABLE `scheduled_tasks`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_scheduled_tasks_report_template` (`report_template_id`);

--
-- Index pour la table `scheduled_tasks_mail_logs`
--
ALTER TABLE `scheduled_tasks_mail_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_task_id` (`task_id`),
  ADD KEY `idx_sent_at` (`sent_at`),
  ADD KEY `idx_status` (`status`);

--
-- Index pour la table `Stock`
--
ALTER TABLE `Stock`
  ADD PRIMARY KEY (`id`),
  ADD KEY `ean_code` (`ean_code`),
  ADD KEY `date_ajout` (`date_ajout`);

--
-- Index pour la table `stock_documents`
--
ALTER TABLE `stock_documents`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT pour les tables déchargées
--

--
-- AUTO_INCREMENT pour la table `agenda`
--
ALTER TABLE `agenda`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `autoit_commandes`
--
ALTER TABLE `autoit_commandes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `autoit_logiciels`
--
ALTER TABLE `autoit_logiciels`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `autoit_nettoyage`
--
ALTER TABLE `autoit_nettoyage`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `autoit_personnalisation`
--
ALTER TABLE `autoit_personnalisation`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `catalog`
--
ALTER TABLE `catalog`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `clients`
--
ALTER TABLE `clients`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `configuration`
--
ALTER TABLE `configuration`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `download`
--
ALTER TABLE `download`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `FC_cyber`
--
ALTER TABLE `FC_cyber`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `FC_cyber_credits`
--
ALTER TABLE `FC_cyber_credits`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `FC_cyber_credits_historique`
--
ALTER TABLE `FC_cyber_credits_historique`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `FC_feuille_caisse`
--
ALTER TABLE `FC_feuille_caisse`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `FC_moyens_paiement`
--
ALTER TABLE `FC_moyens_paiement`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT pour la table `FC_transactions`
--
ALTER TABLE `FC_transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `fournisseur`
--
ALTER TABLE `fournisseur`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `helpdesk_cat`
--
ALTER TABLE `helpdesk_cat`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT pour la table `helpdesk_msg`
--
ALTER TABLE `helpdesk_msg`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `helpdesk_reponses`
--
ALTER TABLE `helpdesk_reponses`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `intervention_photos`
--
ALTER TABLE `intervention_photos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `intervention_statuts`
--
ALTER TABLE `intervention_statuts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT pour la table `inventory_items`
--
ALTER TABLE `inventory_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `inventory_sessions`
--
ALTER TABLE `inventory_sessions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `liens`
--
ALTER TABLE `liens`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `mail_config`
--
ALTER TABLE `mail_config`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `oauth2_config`
--
ALTER TABLE `oauth2_config`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `photos_settings`
--
ALTER TABLE `photos_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- AUTO_INCREMENT pour la table `report_templates`
--
ALTER TABLE `report_templates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `scheduled_tasks`
--
ALTER TABLE `scheduled_tasks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `scheduled_tasks_mail_logs`
--
ALTER TABLE `scheduled_tasks_mail_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `Stock`
--
ALTER TABLE `Stock`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `stock_documents`
--
ALTER TABLE `stock_documents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Contraintes pour les tables déchargées
--

--
-- Contraintes pour la table `FC_cyber`
--
ALTER TABLE `FC_cyber`
  ADD CONSTRAINT `FC_cyber_ibfk_1` FOREIGN KEY (`credit_id`) REFERENCES `FC_cyber_credits` (`id`) ON DELETE SET NULL;

--
-- Contraintes pour la table `FC_cyber_credits_historique`
--
ALTER TABLE `FC_cyber_credits_historique`
  ADD CONSTRAINT `FC_cyber_credits_historique_ibfk_1` FOREIGN KEY (`credit_id`) REFERENCES `FC_cyber_credits` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `FC_cyber_credits_historique_ibfk_2` FOREIGN KEY (`session_cyber_id`) REFERENCES `FC_cyber` (`id`) ON DELETE SET NULL;

--
-- Contraintes pour la table `helpdesk_reponses`
--
ALTER TABLE `helpdesk_reponses`
  ADD CONSTRAINT `fk_reponse_message` FOREIGN KEY (`MESSAGE_ID`) REFERENCES `helpdesk_msg` (`ID`) ON DELETE CASCADE;

--
-- Contraintes pour la table `inter`
--
ALTER TABLE `inter`
  ADD CONSTRAINT `fk_inter_statut` FOREIGN KEY (`statut_id`) REFERENCES `intervention_statuts` (`id`) ON DELETE SET NULL;

--
-- Contraintes pour la table `inventory_items`
--
ALTER TABLE `inventory_items`
  ADD CONSTRAINT `inventory_items_ibfk_1` FOREIGN KEY (`session_id`) REFERENCES `inventory_sessions` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `scheduled_tasks`
--
ALTER TABLE `scheduled_tasks`
  ADD CONSTRAINT `fk_scheduled_tasks_report_template` FOREIGN KEY (`report_template_id`) REFERENCES `report_templates` (`id`) ON DELETE SET NULL;

--
-- Contraintes pour la table `scheduled_tasks_mail_logs`
--
ALTER TABLE `scheduled_tasks_mail_logs`
  ADD CONSTRAINT `scheduled_tasks_mail_logs_ibfk_1` FOREIGN KEY (`task_id`) REFERENCES `scheduled_tasks` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
