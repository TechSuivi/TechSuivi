; Configuration de l'API - Lecture depuis cfg.ini
Global $API_URL = IniRead("ini\cfg.ini", "config", "url_base", "") & "api/interventions_en_cours_autoit.php"
Global $API_KEY = IniRead("ini\cfg.ini", "config", "key_api", "")

; Variables globales pour la sélection d'intervention
Global $hGUI_SelectInter, $hList_SelectInter, $hBtnRefresh_SelectInter, $hBtnDetails_SelectInter, $hBtnExport_SelectInter, $hBtnQuitter_SelectInter
Global $aInterventionsData[0]
Global $bInterventionSelected = False

; Fonction principale pour récupérer les interventions
Func GetInterventionsEnCours($limit = 100, $offset = 0, $format = "simple")
    Local $url = $API_URL & "?api_key=" & $API_KEY & "&limit=" & $limit & "&offset=" & $offset & "&format=" & $format

    Local $binaryData = InetRead($url, 1)
    If @error Then
        MsgBox($MB_ICONERROR, "Erreur", "Erreur de connexion: " & @error)
        Return False
    EndIf

    Local $response = BinaryToString($binaryData, 4)
    If StringLen($response) = 0 Then
        MsgBox($MB_ICONERROR, "Erreur", "Réponse vide du serveur")
        Return False
    EndIf

    Return $response
EndFunc

; Fonction pour extraire une valeur JSON simple (version améliorée)
Func GetJsonValue($jsonString, $key)
    ; Nettoyer le JSON mais garder la structure
    Local $cleanJson = StringRegExpReplace($jsonString, '[\r\n\t]', ' ')
    $cleanJson = StringRegExpReplace($cleanJson, '\s+', ' ')
    $cleanJson = StringStripWS($cleanJson, 3)

    ; Pattern pour les valeurs entre guillemets
    Local $pattern1 = '"' & $key & '"\s*:\s*"([^"]*)"'
    ; Pattern pour les valeurs numériques ou booléennes
    Local $pattern2 = '"' & $key & '"\s*:\s*([^,}\]]+)'

    ; Essayer d'abord le pattern pour les chaînes
    Local $result = StringRegExp($cleanJson, $pattern1, 1)
    If IsArray($result) And UBound($result) > 0 Then
        Local $value = $result[0]
        ; Décoder les caractères échappés
        $value = StringReplace($value, "\/", "/")
        $value = StringReplace($value, "\\", "\")
        $value = StringReplace($value, '\"', '"')
        Return $value
    EndIf

    ; Essayer le pattern pour les valeurs non-chaînes
    $result = StringRegExp($cleanJson, $pattern2, 1)
    If IsArray($result) And UBound($result) > 0 Then
        Local $value = StringStripWS($result[0], 3)
        ; Supprimer les virgules et espaces en fin
        $value = StringRegExpReplace($value, '[,\s]*$', '')
        Return $value
    EndIf

    Return ""
EndFunc

; Fonction pour diviser le JSON en objets intervention (version corrigée)
Func SplitInterventions($jsonString)
    ; Nettoyage plus doux - garder la structure JSON intacte
    Local $cleanJson = StringReplace($jsonString, @CRLF, " ")
    $cleanJson = StringReplace($cleanJson, @LF, " ")
    $cleanJson = StringReplace($cleanJson, @CR, " ")
    $cleanJson = StringReplace($cleanJson, @TAB, " ")
    ; Ne pas supprimer tous les espaces multiples car cela peut casser le JSON
    $cleanJson = StringStripWS($cleanJson, 3)

    Local $startPos = StringInStr($cleanJson, '"interventions":')
    If $startPos = 0 Then Return False

    Local $arrayStart = StringInStr($cleanJson, '[', $startPos)
    If $arrayStart = 0 Then Return False

    ; Chercher le ] de fermeture en comptant les niveaux
    Local $arrayEnd = 0
    Local $bracketCount = 0
    Local $inString = False
    
    For $i = $arrayStart To StringLen($cleanJson)
        Local $char = StringMid($cleanJson, $i, 1)
        
        If $char = '"' And StringMid($cleanJson, $i-1, 1) <> '\' Then
            $inString = Not $inString
        ElseIf Not $inString Then
            If $char = '[' Then
                $bracketCount += 1
            ElseIf $char = ']' Then
                $bracketCount -= 1
                If $bracketCount = 0 Then
                    $arrayEnd = $i
                    ExitLoop
                EndIf
            EndIf
        EndIf
    Next

    If $arrayEnd = 0 Then Return False

    Local $arrayContent = StringMid($cleanJson, $arrayStart + 1, $arrayEnd - $arrayStart - 1)

    Local $interventions[0]
    Local $braceCount = 0
    Local $inString = False
    Local $objStart = 0

    For $i = 1 To StringLen($arrayContent)
        Local $char = StringMid($arrayContent, $i, 1)

        If $char = '"' And StringMid($arrayContent, $i-1, 1) <> '\' Then
            $inString = Not $inString
        ElseIf Not $inString Then
            If $char = '{' Then
                If $braceCount = 0 Then $objStart = $i
                $braceCount += 1
            ElseIf $char = '}' Then
                $braceCount -= 1
                If $braceCount = 0 Then
                    Local $obj = StringMid($arrayContent, $objStart, $i - $objStart + 1)
                    ReDim $interventions[UBound($interventions) + 1]
                    $interventions[UBound($interventions) - 1] = $obj
                EndIf
            EndIf
        EndIf
    Next

    Return $interventions
EndFunc

; Fonction pour parser les interventions
Func ParseInterventionsRobuste($jsonResponse)
    Local $success = GetJsonValue($jsonResponse, "success")
    If $success <> "true" And $success <> "1" Then
        Local $error = GetJsonValue($jsonResponse, "error")
        MsgBox($MB_ICONERROR, "Erreur API", "Erreur: " & $error)
        Return False
    EndIf

    Local $interventions = SplitInterventions($jsonResponse)
    If Not IsArray($interventions) Then
        MsgBox($MB_ICONERROR, "Erreur", "Impossible d'extraire les interventions")
        Return False
    EndIf

    Return $interventions
EndFunc

; Fonction principale pour la sélection d'intervention au démarrage
Func _select_intervention_startup()
    CreateSimpleGUI_OnEvent()
    
    ; Boucle d'attente jusqu'à ce qu'une intervention soit sélectionnée ou que l'utilisateur quitte
    While Not $bInterventionSelected
        Sleep(100)
    WEnd
    
    ; Fermer la fenêtre de sélection
    If IsHWnd($hGUI_SelectInter) Then
        GUIDelete($hGUI_SelectInter)
    EndIf
EndFunc

; Fonction pour créer l'interface graphique avec GUISetOnEvent
Func CreateSimpleGUI_OnEvent()
    $hGUI_SelectInter = GUICreate("TechSuivi - Sélection d'intervention", 600, 500, -1, -1)
    GUISetOnEvent($GUI_EVENT_CLOSE, "_SelectInter_Close", $hGUI_SelectInter)

    ; Titre
    GUICtrlCreateLabel("Interventions en cours", 10, 10, 580, 25)
    GUICtrlSetFont(-1, 12, 800)

    ; Liste simple des interventions
    $hList_SelectInter = GUICtrlCreateList("", 10, 40, 580, 350)

    ; Boutons
    $hBtnRefresh_SelectInter = GUICtrlCreateButton("Actualiser", 10, 400, 100, 30)
    GUICtrlSetOnEvent($hBtnRefresh_SelectInter, "_SelectInter_Refresh")
    
    $hBtnDetails_SelectInter = GUICtrlCreateButton("Voir détails", 120, 400, 100, 30)
    GUICtrlSetOnEvent($hBtnDetails_SelectInter, "_SelectInter_Details")
    
    
    $hBtnQuitter_SelectInter = GUICtrlCreateButton("Quitter", 490, 400, 100, 30)
    GUICtrlSetOnEvent($hBtnQuitter_SelectInter, "_SelectInter_Close")

    ; Label de statut
    Global $hLabel_Status = GUICtrlCreateLabel("Statut: Prêt", 10, 440, 580, 20)
    GUICtrlSetColor(-1, 0x0000FF)

    GUISetState(@SW_SHOW, $hGUI_SelectInter)

    ; Charger les données au démarrage
    LoadInterventionsSimple_OnEvent()
EndFunc

; Fonction pour charger les interventions dans la liste simple (version OnEvent)
Func LoadInterventionsSimple_OnEvent()
    GUICtrlSetData($hLabel_Status, "Statut: Chargement en cours...")

    ; Vider la liste
    GUICtrlSetData($hList_SelectInter, "")

    ; Récupérer les données
    Local $response = GetInterventionsEnCours(50, 0, "simple")
    If $response = False Then
        GUICtrlSetData($hLabel_Status, "Statut: Erreur de connexion")
        Return False
    EndIf

    ; Parser les données
    $aInterventionsData = ParseInterventionsRobuste($response)
    If $aInterventionsData = False Then
        GUICtrlSetData($hLabel_Status, "Statut: Erreur de parsing")
        Return False
    EndIf

    ; Remplir la liste
    If IsArray($aInterventionsData) Then
        For $i = 0 To UBound($aInterventionsData) - 1
            Local $intervention = $aInterventionsData[$i]

            Local $id = GetJsonValue($intervention, "id")
            Local $client = GetJsonValue($intervention, "client_nom")
            Local $ville = GetJsonValue($intervention, "client_ville")
            Local $date = GetJsonValue($intervention, "date")

            ; Format: ID - Client (Ville) - Date
            Local $item = $id & " - " & $client & " (" & $ville & ") - " & $date
            GUICtrlSetData($hList_SelectInter, $item)
        Next

        GUICtrlSetData($hLabel_Status, "Statut: " & UBound($aInterventionsData) & " interventions chargées")
    Else
        GUICtrlSetData($hLabel_Status, "Statut: Aucune intervention trouvée")
    EndIf

    Return True
EndFunc

; Fonctions d'événements pour GUISetOnEvent
Func _SelectInter_Close()
    $bInterventionSelected = True
EndFunc

Func _SelectInter_Refresh()
    LoadInterventionsSimple_OnEvent()
EndFunc

Func _SelectInter_Details()
    ShowSelectedInterventionDetails_OnEvent()
EndFunc



; Fonction pour afficher les détails d'une intervention sélectionnée (version OnEvent)
Func ShowSelectedInterventionDetails_OnEvent()
    Local $selected = GUICtrlRead($hList_SelectInter)
    If $selected = "" Then
        MsgBox($MB_ICONWARNING, "Attention", "Veuillez sélectionner une intervention dans la liste")
        Return
    EndIf

    ; Extraire l'ID de la sélection (format: ID - Client...)
    Local $id = StringLeft($selected, StringInStr($selected, " - ") - 1)

    ; Trouver l'intervention correspondante
    Local $index = -1
    For $i = 0 To UBound($aInterventionsData) - 1
        If GetJsonValue($aInterventionsData[$i], "id") = $id Then
            $index = $i
            ExitLoop
        EndIf
    Next

    If $index >= 0 Then
        Local $intervention = $aInterventionsData[$index]

        ; Récupérer et formater les données
        Local $description = GetJsonValue($intervention, "description")
        Local $notes = GetJsonValue($intervention, "notes")
        
        ; Convertir les séquences d'échappement en vrais retours à la ligne
        $description = StringReplace($description, "\r\n", @CRLF)
        $description = StringReplace($description, "\n", @CRLF)
        $description = StringReplace($description, "\r", @CRLF)
        
        $notes = StringReplace($notes, "\r\n", @CRLF)
        $notes = StringReplace($notes, "\n", @CRLF)
        $notes = StringReplace($notes, "\r", @CRLF)

        Local $details = "=== INTERVENTION SÉLECTIONNÉE ===" & @CRLF & @CRLF
        $details &= "ID: " & GetJsonValue($intervention, "id") & @CRLF
        $details &= "Client: " & GetJsonValue($intervention, "client_nom") & @CRLF
        $details &= "Ville: " & GetJsonValue($intervention, "client_ville") & @CRLF
        $details &= "Téléphone: " & GetJsonValue($intervention, "client_telephone") & @CRLF
        $details &= "Adresse: " & GetJsonValue($intervention, "client_adresse") & @CRLF
        $details &= "Date: " & GetJsonValue($intervention, "date") & @CRLF & @CRLF
        $details &= "Description:" & @CRLF & $description & @CRLF & @CRLF
        $details &= "Notes:" & @CRLF & $notes & @CRLF & @CRLF
        $details &= "Voulez-vous traiter cette intervention ?"

        Local $result = MsgBox($MB_YESNO + $MB_ICONQUESTION, "Intervention sélectionnée", $details)

        If $result = $IDYES Then
            ; Sauvegarder l'intervention sélectionnée
            SaveSelectedIntervention($intervention)
            
            ; Marquer qu'une intervention a été sélectionnée
            $bInterventionSelected = True
            
           ; MsgBox($MB_ICONINFORMATION, "Action", "Intervention " & $id & " sélectionnée pour traitement!" & @CRLF & "Le programme va maintenant continuer.")
        EndIf
    Else
        MsgBox($MB_ICONERROR, "Erreur", "Intervention non trouvée")
    EndIf
EndFunc

; Fonction pour sauvegarder l'intervention sélectionnée
Func SaveSelectedIntervention($intervention)
    Local $fichier = @ScriptDir & "\intervention_selectionnee.ini"

    FileDelete($fichier)

    IniWrite($fichier, "INTERVENTION", "id", GetJsonValue($intervention, "id"))
    IniWrite($fichier, "INTERVENTION", "client_id", GetJsonValue($intervention, "client_id"))
    IniWrite($fichier, "INTERVENTION", "client_nom", GetJsonValue($intervention, "client_nom"))
    IniWrite($fichier, "INTERVENTION", "client_ville", GetJsonValue($intervention, "client_ville"))
    IniWrite($fichier, "INTERVENTION", "client_telephone", GetJsonValue($intervention, "client_telephone"))
    IniWrite($fichier, "INTERVENTION", "client_adresse", GetJsonValue($intervention, "client_adresse"))
    IniWrite($fichier, "INTERVENTION", "date", GetJsonValue($intervention, "date"))
    IniWrite($fichier, "INTERVENTION", "description", GetJsonValue($intervention, "description"))
    IniWrite($fichier, "INTERVENTION", "notes", GetJsonValue($intervention, "notes"))
    IniWrite($fichier, "INTERVENTION", "selection_date", @YEAR & "-" & @MON & "-" & @MDAY & " " & @HOUR & ":" & @MIN & ":" & @SEC)

   ; MsgBox($MB_ICONINFORMATION, "Sauvegarde", "Intervention sauvegardée dans:" & @CRLF & $fichier)
EndFunc

