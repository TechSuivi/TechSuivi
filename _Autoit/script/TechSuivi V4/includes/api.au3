#include <WinAPIFiles.au3>
#include <Date.au3>

; Configuration de l'API - Lecture depuis cfg.ini
Global $sBaseURL_API = IniRead("ini\cfg.ini", "config", "url_api", "")
Global $sAPI_KEY = IniRead("ini\cfg.ini", "config", "key_api", "")

; ===============================================
; FONCTION 1: LIRE LES DONNÉES DE L'API
; ===============================================

; Fonction principale pour lire les données d'une intervention
; Retourne un tableau associatif avec tous les champs de l'intervention
; @param $interventionId - ID de l'intervention à récupérer
; @return Array - Tableau associatif avec les données ou False en cas d'erreur
Func API_LireDonnees($interventionId)
    ConsoleWrite("=== LECTURE DONNEES INTERVENTION " & $interventionId & " ===" & @CRLF)
    
    ; Construction de l'URL pour récupérer l'intervention
    Local $sURL = $sBaseURL_API & "?type=intervention&id=" & $interventionId & "&api_key=" & $sAPI_KEY
    
    ; Appel de l'API
    Local $response = _HTTPGet_API_Robust($sURL)
    
    If @error Or $response = "" Then
        ConsoleWrite("✗ Erreur lors de la lecture des données" & @CRLF)
        SetError(1)
        Return False
    EndIf
    
    ; Parse de la réponse JSON (version simplifiée)
    Local $donnees = _ParseJSONResponse_API($response)
    
    If @error Then
        ConsoleWrite("✗ Erreur lors du parsing des données" & @CRLF)
        SetError(2)
        Return False
    EndIf
    
    ConsoleWrite("✓ Données lues avec succès" & @CRLF)
    Return $donnees
EndFunc

; Fonction pour récupérer un champ spécifique d'une intervention
; @param $interventionId - ID de l'intervention
; @param $fieldName - Nom du champ à récupérer
; @return String - Valeur du champ ou "" en cas d'erreur
Func API_LireChamp($interventionId, $fieldName)
    Local $donnees = API_LireDonnees($interventionId)
    
    If @error Or Not IsArray($donnees) Then
        SetError(1)
        Return ""
    EndIf
    
    ; Recherche du champ dans les données
    For $i = 0 To UBound($donnees) - 1 Step 2
        If $donnees[$i] = $fieldName Then
            Return $donnees[$i + 1]
        EndIf
    Next
    
    ; Champ non trouvé
    SetError(2)
    Return ""
EndFunc

; ===============================================
; FONCTION 2: SAUVEGARDER LES DONNÉES VIA L'API
; ===============================================

; Fonction principale pour sauvegarder les données d'une intervention
; @param $interventionId - ID de l'intervention à mettre à jour
; @param $donnees - Tableau associatif avec les champs à sauvegarder
; @return Bool - True si succès, False sinon
Func API_SauvegarderDonnees($interventionId, $donnees)
    ConsoleWrite("=== SAUVEGARDE DONNEES INTERVENTION " & $interventionId & " ===" & @CRLF)
    
    If Not IsArray($donnees) Then
        ConsoleWrite("✗ Données invalides (pas un tableau)" & @CRLF)
        SetError(1)
        Return False
    EndIf
    
    ; Construction des données POST
    Local $postData = "intervention_id=" & $interventionId & _
                      "&action=update_intervention" & _
                      "&api_key=" & $sAPI_KEY
    
    ; Ajout de chaque champ aux données POST
    For $i = 0 To UBound($donnees) - 1 Step 2
        If $i + 1 < UBound($donnees) Then
            $postData &= "&" & $donnees[$i] & "=" & _URLEncode($donnees[$i + 1])
        EndIf
    Next
    
    ; Envoi des données
    Local $response = _HTTPPost_API_Robust($sBaseURL_API, $postData)
    
    If @error Or $response = "" Then
        ConsoleWrite("✗ Erreur lors de la sauvegarde" & @CRLF)
        SetError(2)
        Return False
    EndIf
    
    ; Vérification de la réponse
    If StringInStr($response, "success") > 0 Or StringInStr($response, "OK") > 0 Then
        ConsoleWrite("✓ Sauvegarde réussie" & @CRLF)
        Return True
    Else
        ConsoleWrite("✗ Erreur dans la réponse: " & StringLeft($response, 100) & @CRLF)
        SetError(3)
        Return False
    EndIf
EndFunc

; Fonction pour sauvegarder un champ spécifique
; @param $interventionId - ID de l'intervention
; @param $fieldName - Nom du champ à sauvegarder
; @param $fieldValue - Valeur du champ
; @return Bool - True si succès, False sinon
Func API_SauvegarderChamp($interventionId, $fieldName, $fieldValue)
    ConsoleWrite("=== SAUVEGARDE CHAMP " & $fieldName & " ===" & @CRLF)
    
    Local $data = "intervention_id=" & $interventionId & _
                  "&" & $fieldName & "=" & _URLEncode($fieldValue) & _
                  "&action=update_intervention" & _
                  "&api_key=" & $sAPI_KEY
    
    ConsoleWrite("URL complète: " & $sBaseURL_API & @CRLF)
    ConsoleWrite("Données POST: " & $data & @CRLF)
    
    Local $response = _HTTPPost_API_Robust($sBaseURL_API, $data)
    
    ConsoleWrite("Réponse API: " & $response & @CRLF)
    
    If @error Or $response = "" Then
        ConsoleWrite("✗ Erreur lors de la sauvegarde du champ" & @CRLF)
        SetError(1)
        Return False
    EndIf
    
    ; Vérification de la réponse
    If StringInStr($response, "success") > 0 Or StringInStr($response, "OK") > 0 Then
        ConsoleWrite("✓ Champ sauvegardé avec succès" & @CRLF)
        Return True
    Else
        ConsoleWrite("✗ Erreur dans la réponse: " & StringLeft($response, 200) & @CRLF)
        SetError(2)
        Return False
    EndIf
EndFunc

; ===============================================
; FONCTIONS UTILITAIRES (reprises du fichier test)
; ===============================================

; Fonction HTTP GET robuste avec plusieurs méthodes
Func _HTTPGet_API_Robust($sURL)
    ConsoleWrite("Tentative GET: " & $sURL & @CRLF)
    
    ; Méthode 1: InetRead (méthode simple)
    Local $dData = InetRead($sURL, 1) ; 1 = forcer le téléchargement
    
    If Not @error And $dData <> "" Then
        Local $sResponse = BinaryToString($dData, $SB_UTF8)
        ConsoleWrite("✓ InetRead réussi (" & StringLen($sResponse) & " caractères)" & @CRLF)
        Return $sResponse
    Else
        ConsoleWrite("✗ InetRead échoué (Erreur: " & @error & ")" & @CRLF)
    EndIf
    
    ; Méthode 2: WinHTTP
    Local $oHTTP = ObjCreate("winhttp.winhttprequest.5.1")
    
    If IsObj($oHTTP) Then
        ConsoleWrite("Tentative WinHTTP..." & @CRLF)
        
        $oHTTP.Open("GET", $sURL, False)
        $oHTTP.SetRequestHeader("User-Agent", "AutoIt-TechSuivi/1.0")
        $oHTTP.Send()
        
        If Not @error Then
            Local $response = $oHTTP.ResponseText
            ConsoleWrite("✓ WinHTTP réussi (" & StringLen($response) & " caractères)" & @CRLF)
            Return $response
        Else
            ConsoleWrite("✗ WinHTTP échoué (Erreur: " & @error & ")" & @CRLF)
        EndIf
    Else
        ConsoleWrite("✗ Impossible de créer l'objet WinHTTP" & @CRLF)
    EndIf
    
    ; Méthode 3: CURL (si disponible)
    If FileExists(@SystemDir & "\curl.exe") Then
        ConsoleWrite("Tentative CURL..." & @CRLF)
        
        Local $curlCmd = @SystemDir & '\curl.exe -s "' & $sURL & '"'
        Local $curlResult = ""
        Local $pid = Run(@ComSpec & " /c " & $curlCmd, "", @SW_HIDE, 2)
        
        While ProcessExists($pid)
            $curlResult &= StdoutRead($pid)
            Sleep(100)
        WEnd
        
        If $curlResult <> "" Then
            ConsoleWrite("✓ CURL réussi (" & StringLen($curlResult) & " caractères)" & @CRLF)
            Return $curlResult
        Else
            ConsoleWrite("✗ CURL échoué" & @CRLF)
        EndIf
    EndIf
    
    ConsoleWrite("✗ Toutes les méthodes HTTP ont échoué" & @CRLF)
    SetError(1)
    Return ""
EndFunc

; Fonction HTTP POST robuste
Func _HTTPPost_API_Robust($sURL, $sData)
    ConsoleWrite("Tentative POST: " & $sURL & @CRLF)
    ConsoleWrite("Données: " & StringLeft($sData, 100) & "..." & @CRLF)
    
    ; Méthode 1: WinHTTP
    Local $oHTTP = ObjCreate("winhttp.winhttprequest.5.1")
    
    If IsObj($oHTTP) Then
        ConsoleWrite("Tentative POST WinHTTP..." & @CRLF)
        
        $oHTTP.Open("POST", $sURL, False)
        $oHTTP.SetRequestHeader("Content-Type", "application/x-www-form-urlencoded")
        $oHTTP.SetRequestHeader("User-Agent", "AutoIt-TechSuivi/1.0")
        $oHTTP.Send($sData)
        
        If Not @error Then
            Local $response = $oHTTP.ResponseText
            ConsoleWrite("✓ POST WinHTTP réussi (" & StringLen($response) & " caractères)" & @CRLF)
            Return $response
        Else
            ConsoleWrite("✗ POST WinHTTP échoué (Erreur: " & @error & ")" & @CRLF)
        EndIf
    EndIf
    
    ; Méthode 2: CURL POST (si disponible)
    If FileExists(@SystemDir & "\curl.exe") Then
        ConsoleWrite("Tentative POST CURL..." & @CRLF)
        
        Local $curlCmd = @SystemDir & '\curl.exe -s -X POST -d "' & $sData & '" "' & $sURL & '"'
        Local $curlResult = ""
        Local $pid = Run(@ComSpec & " /c " & $curlCmd, "", @SW_HIDE, 2)
        
        While ProcessExists($pid)
            $curlResult &= StdoutRead($pid)
            Sleep(100)
        WEnd
        
        If $curlResult <> "" Then
            ConsoleWrite("✓ POST CURL réussi (" & StringLen($curlResult) & " caractères)" & @CRLF)
            Return $curlResult
        Else
            ConsoleWrite("✗ POST CURL échoué" & @CRLF)
        EndIf
    EndIf
    
    ConsoleWrite("✗ Toutes les méthodes POST ont échoué" & @CRLF)
    SetError(1)
    Return ""
EndFunc

; Encoder une chaîne pour URL
Func _URLEncode($sString)
    ; Remplacer les caractères spéciaux pour l'URL
    $sString = StringReplace($sString, "%", "%25")
    $sString = StringReplace($sString, " ", "%20")
    $sString = StringReplace($sString, @CRLF, "%0D%0A")
    $sString = StringReplace($sString, @LF, "%0A")
    $sString = StringReplace($sString, @CR, "%0D")
    $sString = StringReplace($sString, "&", "%26")
    $sString = StringReplace($sString, "=", "%3D")
    $sString = StringReplace($sString, "+", "%2B")
    $sString = StringReplace($sString, "#", "%23")
    $sString = StringReplace($sString, "?", "%3F")
    $sString = StringReplace($sString, """", "%22")
    $sString = StringReplace($sString, "'", "%27")
    $sString = StringReplace($sString, "<", "%3C")
    $sString = StringReplace($sString, ">", "%3E")
    Return $sString
EndFunc

; Parser amélioré pour les réponses JSON de l'API
; @param $jsonString - Chaîne JSON à parser
; @return Array - Tableau associatif avec les données
Func _ParseJSONResponse_API($jsonString)
    ConsoleWrite("Parsing JSON: " & StringLeft($jsonString, 200) & "..." & @CRLF)
    
    Local $result[200] ; Tableau plus grand pour stocker les paires clé-valeur
    Local $index = 0
    
    ; Nettoyage initial
    $jsonString = StringStripWS($jsonString, 3)
    
    ; Recherche de la section "data" qui contient les vraies données
    Local $dataStart = StringInStr($jsonString, '"data":{')
    If $dataStart > 0 Then
        ; Extraire seulement la partie data
        Local $dataEnd = StringInStr($jsonString, '},"', $dataStart)
        If $dataEnd = 0 Then $dataEnd = StringLen($jsonString) - 1
        
        Local $dataSection = StringMid($jsonString, $dataStart + 8, $dataEnd - $dataStart - 8)
        ConsoleWrite("Section data extraite: " & $dataSection & @CRLF)
        
        ; Parser la section data
        $jsonString = $dataSection
    EndIf
    
    ; Suppression des caractères JSON de base
    $jsonString = StringReplace($jsonString, "{", "")
    $jsonString = StringReplace($jsonString, "}", "")
    
    ; Traitement des chaînes entre guillemets
    Local $inQuotes = False
    Local $currentField = ""
    Local $currentValue = ""
    Local $isValue = False
    Local $chars = StringSplit($jsonString, "", 2) ; Split en caractères
    
    For $i = 0 To UBound($chars) - 1
        Local $char = $chars[$i]
        
        If $char = '"' Then
            $inQuotes = Not $inQuotes
        ElseIf $char = ':' And Not $inQuotes Then
            $isValue = True
        ElseIf $char = ',' And Not $inQuotes Then
            ; Fin d'une paire clé-valeur
            If $currentField <> "" Then
                ; Nettoyage des valeurs
                $currentField = StringStripWS($currentField, 3)
                $currentValue = StringStripWS($currentValue, 3)
                
                ; Décodage des caractères échappés
                $currentValue = StringReplace($currentValue, "\/", "/")
                $currentValue = StringReplace($currentValue, "\\", "\")
                $currentValue = StringReplace($currentValue, '\"', '"')
                $currentValue = StringReplace($currentValue, "\\r\\n", @CRLF)
                $currentValue = StringReplace($currentValue, "\\n", @LF)
                $currentValue = StringReplace($currentValue, "\\r", @CR)
                
                $result[$index] = $currentField
                $result[$index + 1] = $currentValue
                $index += 2
                
                ConsoleWrite("Champ trouvé: " & $currentField & " = " & $currentValue & @CRLF)
            EndIf
            
            $currentField = ""
            $currentValue = ""
            $isValue = False
        ElseIf $inQuotes Or ($char <> ' ' And $char <> @TAB) Then
            If $isValue Then
                $currentValue &= $char
            Else
                $currentField &= $char
            EndIf
        EndIf
    Next
    
    ; Traiter la dernière paire si elle existe
    If $currentField <> "" Then
        $currentField = StringStripWS($currentField, 3)
        $currentValue = StringStripWS($currentValue, 3)
        
        ; Décodage des caractères échappés
        $currentValue = StringReplace($currentValue, "\/", "/")
        $currentValue = StringReplace($currentValue, "\\", "\")
        $currentValue = StringReplace($currentValue, '\"', '"')
        $currentValue = StringReplace($currentValue, "\\r\\n", @CRLF)
        $currentValue = StringReplace($currentValue, "\\n", @LF)
        $currentValue = StringReplace($currentValue, "\\r", @CR)
        
        $result[$index] = $currentField
        $result[$index + 1] = $currentValue
        $index += 2
        
        ConsoleWrite("Dernier champ trouvé: " & $currentField & " = " & $currentValue & @CRLF)
    EndIf
    
    ; Redimensionner le tableau à la taille réelle
    ReDim $result[$index]
    
    If $index = 0 Then
        ConsoleWrite("Aucune donnée trouvée dans le JSON" & @CRLF)
        SetError(1)
        Return False
    EndIf
    
    ConsoleWrite("Parsing terminé: " & ($index / 2) & " champs trouvés" & @CRLF)
    Return $result
EndFunc

; ===============================================
; FONCTIONS DE COMMODITÉ POUR LES LOGS
; ===============================================

; Log de nettoyage
Func API_LogNettoyage($interventionId, $logText)
    Return API_SauvegarderChamp($interventionId, "nettoyage", $logText)
EndFunc

; Log d'informations
Func API_LogInfo($interventionId, $logText)
    Return API_SauvegarderChamp($interventionId, "info_log", $logText)
EndFunc

; Log de notes utilisateur
Func API_LogNote($interventionId, $noteText)
    Return API_SauvegarderChamp($interventionId, "note_user", $noteText)
EndFunc

; ===============================================
; EXEMPLE D'UTILISATION
; ===============================================

; Exemple de lecture de données
; Local $donnees = API_LireDonnees("66D81708")
; If Not @error Then
;     Local $clientNom = API_LireChamp("66D81708", "client_nom")
;     ConsoleWrite("Nom du client: " & $clientNom & @CRLF)
; EndIf

; Exemple de sauvegarde d'un champ
; API_SauvegarderChamp("66D81708", "note_user", "Intervention terminée avec succès")

; Exemple de sauvegarde multiple
; Local $donneesASauver[4] = ["note_user", "Intervention terminée", "statut", "Terminé"]
; API_SauvegarderDonnees("66D81708", $donneesASauver)