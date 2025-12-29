<?php
// Fonctions utilitaires pour la gestion du fuseau horaire

/**
 * Charge la configuration du fuseau horaire depuis la base de données
 * @return float Décalage en heures
 */
function getTimezoneOffset() {
    try {
        $pdo = getDatabaseConnection();
        $stmt = $pdo->prepare("SELECT config_value FROM app_config WHERE config_key = 'timezone_offset'");
        $stmt->execute();
        $result = $stmt->fetch();
        
        if ($result) {
            return floatval($result['config_value']);
        }
    } catch (Exception $e) {
        // En cas d'erreur, retourner 0 (pas de décalage)
        error_log("Erreur lors de la récupération du fuseau horaire : " . $e->getMessage());
    }
    
    return 0; // Pas de décalage par défaut
}

/**
 * Applique le décalage horaire configuré à un timestamp
 * @param int $timestamp Timestamp UTC
 * @return int Timestamp avec décalage appliqué
 */
function applyTimezoneOffset($timestamp) {
    $offset = getTimezoneOffset();
    return $timestamp + ($offset * 3600);
}

/**
 * Formate une date avec le décalage horaire configuré
 * @param string|int $dateTime Date/heure ou timestamp
 * @param string $format Format de sortie
 * @return string Date formatée
 */
function formatDateWithTimezone($dateTime, $format = 'd/m/Y à H:i') {
    if (is_string($dateTime)) {
        $timestamp = strtotime($dateTime);
    } else {
        $timestamp = $dateTime;
    }
    
    $adjustedTimestamp = applyTimezoneOffset($timestamp);
    return date($format, $adjustedTimestamp);
}

/**
 * Obtient l'heure actuelle avec le décalage configuré
 * @param string $format Format de sortie
 * @return string Heure actuelle formatée
 */
function getCurrentTimeWithTimezone($format = 'Y-m-d H:i:s') {
    $adjustedTimestamp = applyTimezoneOffset(time());
    return date($format, $adjustedTimestamp);
}

/**
 * Convertit une heure locale en UTC pour stockage en base
 * @param string $localDateTime Date/heure locale
 * @return string Date/heure UTC
 */
function convertLocalToUTC($localDateTime) {
    $offset = getTimezoneOffset();
    $timestamp = strtotime($localDateTime);
    $utcTimestamp = $timestamp - ($offset * 3600);
    return date('Y-m-d H:i:s', $utcTimestamp);
}

/**
 * Convertit une heure UTC de la base en heure locale
 * @param string $utcDateTime Date/heure UTC
 * @return string Date/heure locale
 */
function convertUTCToLocal($utcDateTime) {
    $timestamp = strtotime($utcDateTime);
    $adjustedTimestamp = applyTimezoneOffset($timestamp);
    return date('Y-m-d H:i:s', $adjustedTimestamp);
}
?>