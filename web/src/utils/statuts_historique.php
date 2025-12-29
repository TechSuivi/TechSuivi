<?php
// Fonctions utilitaires pour gérer l'historique des statuts

// Inclure les fonctions de gestion du fuseau horaire
require_once __DIR__ . '/timezone_helper.php';

// Définir le fuseau horaire par défaut (Europe/Paris pour la France)
if (!ini_get('date.timezone')) {
    date_default_timezone_set('Europe/Paris');
}

/**
 * Parse l'historique des statuts depuis la chaîne stockée en base
 * Format: id=date@heure;id=date@heure;...
 * @param string $historiqueString
 * @return array
 */
function parseStatutsHistorique($historiqueString) {
    if (empty($historiqueString)) {
        return [];
    }
    
    $historique = [];
    $entries = explode(';', $historiqueString);
    
    foreach ($entries as $entry) {
        $entry = trim($entry);
        if (empty($entry)) continue;
        
        // Format: id=date@heure
        if (preg_match('/^(\d+)=(.+)$/', $entry, $matches)) {
            $statutId = intval($matches[1]);
            $dateHeure = $matches[2];
            
            // Convertir le format date@heure en timestamp
            $dateTime = str_replace('@', ' ', $dateHeure);
            
            $historique[] = [
                'statut_id' => $statutId,
                'date_heure' => $dateTime,
                'timestamp' => strtotime($dateTime)
            ];
        }
    }
    
    // Trier par timestamp (plus récent en premier)
    usort($historique, function($a, $b) {
        return $b['timestamp'] - $a['timestamp'];
    });
    
    return $historique;
}

/**
 * Construit la chaîne d'historique pour la base de données
 * @param array $historique
 * @return string
 */
function buildStatutsHistorique($historique) {
    $entries = [];
    
    foreach ($historique as $entry) {
        $dateHeure = str_replace(' ', '@', $entry['date_heure']);
        $entries[] = $entry['statut_id'] . '=' . $dateHeure;
    }
    
    return implode(';', $entries);
}

/**
 * Ajoute un nouveau statut à l'historique
 * @param string $historiqueActuel
 * @param int $nouveauStatutId
 * @param string $dateHeure (optionnel, par défaut maintenant)
 * @return string
 */
function ajouterStatutHistorique($historiqueActuel, $nouveauStatutId, $dateHeure = null) {
    if ($dateHeure === null) {
        $dateHeure = getCurrentTimeWithTimezone('Y-m-d H:i:s');
    }
    
    $historique = parseStatutsHistorique($historiqueActuel);
    
    // Ajouter le nouveau statut au début
    array_unshift($historique, [
        'statut_id' => $nouveauStatutId,
        'date_heure' => $dateHeure,
        'timestamp' => strtotime($dateHeure)
    ]);
    
    return buildStatutsHistorique($historique);
}

/**
 * Récupère le statut actuel (le plus récent) depuis l'historique
 * @param string $historiqueString
 * @return array|null
 */
function getStatutActuel($historiqueString) {
    $historique = parseStatutsHistorique($historiqueString);
    return !empty($historique) ? $historique[0] : null;
}

/**
 * Récupère l'historique complet avec les informations des statuts
 * @param PDO $pdo
 * @param string $historiqueString
 * @return array
 */
function getHistoriqueComplet($pdo, $historiqueString) {
    $historique = parseStatutsHistorique($historiqueString);
    
    if (empty($historique)) {
        return [];
    }
    
    // Récupérer les informations des statuts
    $statutIds = array_unique(array_column($historique, 'statut_id'));
    $placeholders = str_repeat('?,', count($statutIds) - 1) . '?';
    
    try {
        $stmt = $pdo->prepare("SELECT id, nom, couleur, description FROM intervention_statuts WHERE id IN ($placeholders)");
        $stmt->execute($statutIds);
        $statuts = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Indexer par ID
        $statutsById = [];
        foreach ($statuts as $statut) {
            $statutsById[$statut['id']] = $statut;
        }
        
        // Enrichir l'historique avec les informations des statuts
        foreach ($historique as &$entry) {
            if (isset($statutsById[$entry['statut_id']])) {
                $entry['statut'] = $statutsById[$entry['statut_id']];
            } else {
                $entry['statut'] = [
                    'id' => $entry['statut_id'],
                    'nom' => 'Statut supprimé',
                    'couleur' => '#6c757d',
                    'description' => 'Ce statut n\'existe plus'
                ];
            }
        }
        
        return $historique;
    } catch (PDOException $e) {
        return $historique; // Retourner l'historique sans enrichissement en cas d'erreur
    }
}

/**
 * Formate une date pour l'affichage
 * @param string $dateHeure
 * @return string
 */
function formatDateHistorique($dateHeure) {
    // Utiliser directement la date sans appliquer de décalage supplémentaire
    // car les dates sont déjà stockées dans le bon fuseau horaire
    $timestamp = strtotime($dateHeure);
    return date('d/m/Y à H:i', $timestamp);
}

/**
 * Calcule la durée entre deux statuts
 * @param string $dateDebut
 * @param string $dateFin
 * @return string
 */
function calculerDureeStatut($dateDebut, $dateFin = null) {
    // Utiliser le fuseau horaire configuré pour les calculs
    $dateDebutObj = new DateTime($dateDebut);
    
    if ($dateFin === null) {
        $dateFinObj = new DateTime(); // Date actuelle
    } else {
        $dateFinObj = new DateTime($dateFin);
    }
    
    // Calculer la différence
    $interval = $dateDebutObj->diff($dateFinObj);
    
    // Si la date de début est dans le futur par rapport à la date de fin
    if ($interval->invert == 1) {
        // Date future - probablement un problème de fuseau horaire
        // Considérer comme "aujourd'hui" si c'est le même jour
        $dateDebutStr = $dateDebutObj->format('Y-m-d');
        $dateFinStr = $dateFinObj->format('Y-m-d');
        
        if ($dateDebutStr == $dateFinStr) {
            return "aujourd'hui";
        } else {
            // Vraiment dans le futur
            $jours = $interval->days;
            if ($jours == 0) {
                return "aujourd'hui";
            } else {
                return "dans " . $jours . " jour" . ($jours > 1 ? "s" : "");
            }
        }
    }
    
    // Calculer le nombre de jours depuis le début du statut
    $jours = $interval->days;
    
    if ($jours == 0) {
        return "aujourd'hui";
    } else {
        return $jours . " jour" . ($jours > 1 ? "s" : "");
    }
}
?>