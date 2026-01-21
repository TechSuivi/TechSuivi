#include <GUIConstantsEx.au3>
#include <ListViewConstants.au3>
#include <GuiListView.au3>
#include <MsgBoxConstants.au3>

; Configuration de l'API
Global Const $API_URL = "http://192.168.10.245:8080/api/interventions_en_cours_autoit.php"
Global Const $API_KEY = "techsuivi_autoit_2025"

; Variables globales pour l'interface
Global $hGUI, $hListView, $hBtnRefresh, $hBtnDetails, $hBtnExport
Global $aInterventions[0]
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
    
    Local $total = GetJsonValue($jsonResponse, "total")
    Local $count = GetJsonValue($jsonResponse, "count")
    
    Local $interventions = SplitInterventions($jsonResponse)
    If Not IsArray($interventions) Then
        MsgBox($MB_ICONERROR, "Erreur", "Impossible d'extraire les interventions")
        Return False
    EndIf
    
    Local $data[4]
    $data[0] = $total
    $data[1] = $count
    $data[2] = $interventions
    $data[3] = UBound($interventions)
    
    Return $data
EndFunc

; Fonction pour créer l'interface graphique
Func CreateGUI()
    $hGUI = GUICreate("TechSuivi - Interventions en cours", 900, 600, -1, -1)
    
    ; Titre
    GUICtrlCreateLabel("Interventions en cours", 10, 10, 880, 25)
    GUICtrlSetFont(-1, 14, 800)
    
    ; ListView pour afficher les interventions
    $hListView = GUICtrlCreateListView("ID|Client|Ville|Téléphone|Date|Description", 10, 45, 880, 450, _
        BitOR($LVS_REPORT, $LVS_SINGLESEL, $LVS_SHOWSELALWAYS))
    
    ; Définir la largeur des colonnes
    _GUICtrlListView_SetColumnWidth($hListView, 0, 80)  ; ID
    _GUICtrlListView_SetColumnWidth($hListView, 1, 150) ; Client
    _GUICtrlListView_SetColumnWidth($hListView, 2, 100) ; Ville
    _GUICtrlListView_SetColumnWidth($hListView, 3, 120) ; Téléphone
    _GUICtrlListView_SetColumnWidth($hListView, 4, 130) ; Date
    _GUICtrlListView_SetColumnWidth($hListView, 5, 200) ; Description
    
    ; Boutons
    $hBtnRefresh = GUICtrlCreateButton("Actualiser", 10, 510, 100, 30)
    $hBtnDetails = GUICtrlCreateButton("Voir détails", 120, 510, 100, 30)
    $hBtnExport = GUICtrlCreateButton("Exporter INI", 230, 510, 100, 30)
    
    ; Label de statut
    GUICtrlCreateLabel("Statut: Prêt", 10, 550, 880, 20)
    GUICtrlSetColor(-1, 0x0000FF)
    
    ; Bouton Quitter
    GUICtrlCreateButton("Quitter", 790, 510, 100, 30)
    
    GUISetState(@SW_SHOW, $hGUI)
    
    ; Charger les données au démarrage
    LoadInterventions()
EndFunc

; Fonction pour charger les interventions dans la ListView
Func LoadInterventions()
    GUICtrlSetData(-1, "Statut: Chargement en cours...")
    
    ; Vider la ListView
    _GUICtrlListView_DeleteAllItems($hListView)
    
    ; Récupérer les données
    Local $response = GetInterventionsEnCours(50, 0, "simple")
    If $response = False Then
        GUICtrlSetData(-1, "Statut: Erreur de connexion")
        Return False
    EndIf
    
    ; Parser les données
    Local $data = ParseInterventionsRobuste($response)
    If $data = False Then
        GUICtrlSetData(-1, "Statut: Erreur de parsing")
        Return False
    EndIf
    
    ; Stocker les données globalement
    $aInterventionsData = $data[2]
    
    ; Remplir la ListView
    If IsArray($aInterventionsData) Then
        For $i = 0 To UBound($aInterventionsData) - 1
            Local $intervention = $aInterventionsData[$i]
            
            Local $id = GetJsonValue($intervention, "id")
            Local $client = GetJsonValue($intervention, "client_nom")
            Local $ville = GetJsonValue($intervention, "client_ville")
            Local $telephone = GetJsonValue($intervention, "client_telephone")
            Local $date = GetJsonValue($intervention, "date")
            Local $description = GetJsonValue($intervention, "description")
            
            ; Limiter la description à 50 caractères
            If StringLen($description) > 50 Then
                $description = StringLeft($description, 47) & "..."
            EndIf
            
            ; Ajouter l'item à la ListView
            Local $item = $id & "|" & $client & "|" & $ville & "|" & $telephone & "|" & $date & "|" & $description
            GUICtrlCreateListViewItem($item, $hListView)
        Next
        
        GUICtrlSetData(-1, "Statut: " & UBound($aInterventionsData) & " interventions chargées")
    Else
        GUICtrlSetData(-1, "Statut: Aucune intervention trouvée")
    EndIf
    
    Return True
EndFunc

; Fonction pour afficher les détails d'une intervention
Func ShowInterventionDetails()
    Local $selected = _GUICtrlListView_GetSelectedIndices($hListView)
    If $selected = "" Then
        MsgBox($MB_ICONWARNING, "Attention", "Veuillez sélectionner une intervention")
        Return
    EndIf
    
    Local $index = Number($selected)
    If $index >= 0 And $index < UBound($aInterventionsData) Then
        Local $intervention = $aInterventionsData[$index]
        
        Local $details = "=== DÉTAILS DE L'INTERVENTION ===" & @CRLF & @CRLF
        $details &= "ID: " & GetJsonValue($intervention, "id") & @CRLF
        $details &= "Client ID: " & GetJsonValue($intervention, "client_id") & @CRLF
        $details &= "Nom: " & GetJsonValue($intervention, "client_nom") & @CRLF
        $details &= "Ville: " & GetJsonValue($intervention, "client_ville") & @CRLF
        $details &= "Téléphone: " & GetJsonValue($intervention, "client_telephone") & @CRLF
        $details &= "Adresse: " & GetJsonValue($intervention, "client_adresse") & @CRLF
        $details &= "Date: " & GetJsonValue($intervention, "date") & @CRLF & @CRLF
        $details &= "Description:" & @CRLF & GetJsonValue($intervention, "description") & @CRLF & @CRLF
        $details &= "Notes:" & @CRLF & GetJsonValue($intervention, "notes")
        
        MsgBox($MB_ICONINFORMATION, "Détails de l'intervention", $details)
    EndIf
EndFunc

; Fonction pour exporter en INI
Func ExportToINI()
    If Not IsArray($aInterventionsData) Or UBound($aInterventionsData) = 0 Then
        MsgBox($MB_ICONWARNING, "Attention", "Aucune donnée à exporter")
        Return
    EndIf
    
    Local $fichierINI = @ScriptDir & "\interventions_" & @YEAR & @MON & @MDAY & "_" & @HOUR & @MIN & ".ini"
    
    ; Supprimer le fichier existant
    FileDelete($fichierINI)
    
    ; Section générale
    IniWrite($fichierINI, "GENERAL", "nb_interventions", UBound($aInterventionsData))
    IniWrite($fichierINI, "GENERAL", "export_date", @YEAR & "-" & @MON & "-" & @MDAY & " " & @HOUR & ":" & @MIN & ":" & @SEC)
    
    ; Interventions
    For $i = 0 To UBound($aInterventionsData) - 1
        Local $intervention = $aInterventionsData[$i]
        Local $section = "INTERVENTION_" & ($i + 1)
        
        IniWrite($fichierINI, $section, "id", GetJsonValue($intervention, "id"))
        IniWrite($fichierINI, $section, "client_id", GetJsonValue($intervention, "client_id"))
        IniWrite($fichierINI, $section, "client_nom", GetJsonValue($intervention, "client_nom"))
        IniWrite($fichierINI, $section, "client_ville", GetJsonValue($intervention, "client_ville"))
        IniWrite($fichierINI, $section, "client_telephone", GetJsonValue($intervention, "client_telephone"))
        IniWrite($fichierINI, $section, "client_adresse", GetJsonValue($intervention, "client_adresse"))
        IniWrite($fichierINI, $section, "date", GetJsonValue($intervention, "date"))
        IniWrite($fichierINI, $section, "description", GetJsonValue($intervention, "description"))
        IniWrite($fichierINI, $section, "notes", GetJsonValue($intervention, "notes"))
    Next
    
    MsgBox($MB_ICONINFORMATION, "Export réussi", "Données exportées dans:" & @CRLF & $fichierINI)
EndFunc

; Fonction principale
Func Main()
    CreateGUI()
    
    While 1
        Local $msg = GUIGetMsg()
        
        Switch $msg
            Case $GUI_EVENT_CLOSE
                ExitLoop
                
            Case $hBtnRefresh
                LoadInterventions()
                
            Case $hBtnDetails
                ShowInterventionDetails()
                
            Case $hBtnExport
                ExportToINI()
                
            Case Else
                ; Double-clic sur la ListView pour voir les détails
                If $msg = $hListView Then
                    ShowInterventionDetails()
                EndIf
        EndSwitch
    WEnd
    
    GUIDelete($hGUI)
EndFunc

; Lancement de l'application
Main()