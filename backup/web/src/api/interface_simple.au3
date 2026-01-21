#include <GUIConstantsEx.au3>
#include <MsgBoxConstants.au3>

; Configuration de l'API
Global Const $API_URL = "http://192.168.10.245:8080/api/interventions_en_cours_autoit.php"
Global Const $API_KEY = "techsuivi_autoit_2025"

; Variables globales
Global $hGUI, $hList, $hBtnRefresh, $hBtnDetails, $hBtnExport
Global $aInterventionsData[0]

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

; Fonction pour extraire une valeur JSON simple
Func GetJsonValue($jsonString, $key)
    Local $cleanJson = StringRegExpReplace($jsonString, '[\r\n\t]', '')
    $cleanJson = StringRegExpReplace($cleanJson, '\s+', ' ')
    
    Local $pattern1 = '"' & $key & '":\s*"([^"]*)"'
    Local $pattern2 = '"' & $key & '":\s*([^,}\]]*)'
    
    Local $result = StringRegExp($cleanJson, $pattern1, 1)
    If IsArray($result) And UBound($result) > 0 Then
        Return $result[0]
    EndIf
    
    $result = StringRegExp($cleanJson, $pattern2, 1)
    If IsArray($result) And UBound($result) > 0 Then
        Local $value = StringStripWS($result[0], 3)
        Return $value
    EndIf
    
    Return ""
EndFunc

; Fonction pour diviser le JSON en objets intervention
Func SplitInterventions($jsonString)
    Local $cleanJson = StringRegExpReplace($jsonString, '[\r\n\t]', ' ')
    $cleanJson = StringRegExpReplace($cleanJson, '\s+', ' ')
    
    Local $startPos = StringInStr($cleanJson, '"interventions":')
    If $startPos = 0 Then Return False
    
    Local $arrayStart = StringInStr($cleanJson, '[', $startPos)
    If $arrayStart = 0 Then Return False
    
    Local $arrayEnd = StringInStr($cleanJson, ']', $arrayStart)
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

; Fonction pour créer l'interface graphique simple
Func CreateSimpleGUI()
    $hGUI = GUICreate("TechSuivi - Sélection d'intervention", 600, 500, -1, -1)
    
    ; Titre
    GUICtrlCreateLabel("Interventions en cours", 10, 10, 580, 25)
    GUICtrlSetFont(-1, 12, 800)
    
    ; Liste simple des interventions
    $hList = GUICtrlCreateList("", 10, 40, 580, 350)
    
    ; Boutons
    $hBtnRefresh = GUICtrlCreateButton("Actualiser", 10, 400, 100, 30)
    $hBtnDetails = GUICtrlCreateButton("Voir détails", 120, 400, 100, 30)
    $hBtnExport = GUICtrlCreateButton("Exporter INI", 230, 400, 100, 30)
    GUICtrlCreateButton("Quitter", 490, 400, 100, 30)
    
    ; Label de statut
    GUICtrlCreateLabel("Statut: Prêt", 10, 440, 580, 20)
    GUICtrlSetColor(-1, 0x0000FF)
    
    GUISetState(@SW_SHOW, $hGUI)
    
    ; Charger les données au démarrage
    LoadInterventionsSimple()
EndFunc

; Fonction pour charger les interventions dans la liste simple
Func LoadInterventionsSimple()
    GUICtrlSetData(-1, "Statut: Chargement en cours...")
    
    ; Vider la liste
    GUICtrlSetData($hList, "")
    
    ; Récupérer les données
    Local $response = GetInterventionsEnCours(50, 0, "simple")
    If $response = False Then
        GUICtrlSetData(-1, "Statut: Erreur de connexion")
        Return False
    EndIf
    
    ; Parser les données
    $aInterventionsData = ParseInterventionsRobuste($response)
    If $aInterventionsData = False Then
        GUICtrlSetData(-1, "Statut: Erreur de parsing")
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
            GUICtrlSetData($hList, $item)
        Next
        
        GUICtrlSetData(-1, "Statut: " & UBound($aInterventionsData) & " interventions chargées")
    Else
        GUICtrlSetData(-1, "Statut: Aucune intervention trouvée")
    EndIf
    
    Return True
EndFunc

; Fonction pour afficher les détails d'une intervention sélectionnée
Func ShowSelectedInterventionDetails()
    Local $selected = GUICtrlRead($hList)
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
        
        Local $details = "=== INTERVENTION SÉLECTIONNÉE ===" & @CRLF & @CRLF
        $details &= "ID: " & GetJsonValue($intervention, "id") & @CRLF
        $details &= "Client: " & GetJsonValue($intervention, "client_nom") & @CRLF
        $details &= "Ville: " & GetJsonValue($intervention, "client_ville") & @CRLF
        $details &= "Téléphone: " & GetJsonValue($intervention, "client_telephone") & @CRLF
        $details &= "Adresse: " & GetJsonValue($intervention, "client_adresse") & @CRLF
        $details &= "Date: " & GetJsonValue($intervention, "date") & @CRLF & @CRLF
        $details &= "Description:" & @CRLF & GetJsonValue($intervention, "description") & @CRLF & @CRLF
        $details &= "Notes:" & @CRLF & GetJsonValue($intervention, "notes") & @CRLF & @CRLF
        $details &= "Voulez-vous traiter cette intervention ?"
        
        Local $result = MsgBox($MB_YESNO + $MB_ICONQUESTION, "Intervention sélectionnée", $details)
        
        If $result = $IDYES Then
            ; Ici vous pouvez ajouter le code pour traiter l'intervention
            MsgBox($MB_ICONINFORMATION, "Action", "Intervention " & $id & " sélectionnée pour traitement!")
            
            ; Exemple: sauvegarder l'intervention sélectionnée
            SaveSelectedIntervention($intervention)
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
    
    MsgBox($MB_ICONINFORMATION, "Sauvegarde", "Intervention sauvegardée dans:" & @CRLF & $fichier)
EndFunc

; Fonction pour exporter toutes les interventions
Func ExportAllToINI()
    If Not IsArray($aInterventionsData) Or UBound($aInterventionsData) = 0 Then
        MsgBox($MB_ICONWARNING, "Attention", "Aucune donnée à exporter")
        Return
    EndIf
    
    Local $fichierINI = @ScriptDir & "\toutes_interventions_" & @YEAR & @MON & @MDAY & "_" & @HOUR & @MIN & ".ini"
    
    FileDelete($fichierINI)
    
    IniWrite($fichierINI, "GENERAL", "nb_interventions", UBound($aInterventionsData))
    IniWrite($fichierINI, "GENERAL", "export_date", @YEAR & "-" & @MON & "-" & @MDAY & " " & @HOUR & ":" & @MIN & ":" & @SEC)
    
    For $i = 0 To UBound($aInterventionsData) - 1
        Local $intervention = $aInterventionsData[$i]
        Local $section = "INTERVENTION_" & ($i + 1)
        
        IniWrite($fichierINI, $section, "id", GetJsonValue($intervention, "id"))
        IniWrite($fichierINI, $section, "client_nom", GetJsonValue($intervention, "client_nom"))
        IniWrite($fichierINI, $section, "client_ville", GetJsonValue($intervention, "client_ville"))
        IniWrite($fichierINI, $section, "client_telephone", GetJsonValue($intervention, "client_telephone"))
        IniWrite($fichierINI, $section, "date", GetJsonValue($intervention, "date"))
        IniWrite($fichierINI, $section, "description", GetJsonValue($intervention, "description"))
    Next
    
    MsgBox($MB_ICONINFORMATION, "Export réussi", "Toutes les interventions exportées dans:" & @CRLF & $fichierINI)
EndFunc

; Fonction principale
Func Main()
    CreateSimpleGUI()
    
    While 1
        Local $msg = GUIGetMsg()
        
        Switch $msg
            Case $GUI_EVENT_CLOSE
                ExitLoop
                
            Case $hBtnRefresh
                LoadInterventionsSimple()
                
            Case $hBtnDetails
                ShowSelectedInterventionDetails()
                
            Case $hBtnExport
                ExportAllToINI()
        EndSwitch
    WEnd
    
    GUIDelete($hGUI)
EndFunc

; Lancement de l'application
Main()