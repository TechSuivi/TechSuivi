#cs ----------------------------------------------------------------------------

 AutoIt Version: 3.3.16.1
 Author:         myName

 Script Function:
	Gestion des logiciels au démarrage - Version finale avec StartupApproved

#ce ----------------------------------------------------------------------------

#include <Array.au3>
#include <File.au3>
#include <GUIConstantsEx.au3>
#include <TreeViewConstants.au3>
#include <GuiTreeView.au3>
#include <StaticConstants.au3>
#include <EditConstants.au3>
#include <WindowsConstants.au3>
#include <StringConstants.au3>

; Variables globales
Global $idTreeViewStartup ; TreeView pour les éléments de démarrage
Global $hProgressBarStartup ; Barre de progression
Global $hProgressTextStartup ; Texte de progression
Global $button_refresh_startup ; Bouton de rafraîchissement
Global $button_disable_startup ; Bouton de désactivation
Global $button_enable_startup ; Bouton d'activation
Global $aStartupItems[0][6] ; Tableau des éléments de démarrage [Nom, Type, Chemin, Clé Registre, Activé, Commande]

; Types d'éléments de démarrage
Global Const $STARTUP_REGISTRY_HKLM = "Registre HKLM"
Global Const $STARTUP_REGISTRY_HKCU = "Registre HKCU"

Func _logdem()
    Local $coord1 = 20
    Local $coord2 = 40
    
    ; Titre de la section
    GUICtrlCreateLabel("Gestion des logiciels au démarrage", $coord1, $coord2, 300, 20)
    GUICtrlSetFont(-1, 10, 600) ; Police en gras
    $coord2 += 30
    
    ; Description
    GUICtrlCreateLabel("Cette section permet de visualiser et gérer tous les éléments qui se lancent au démarrage de Windows.", $coord1, $coord2, 750, 20)
    $coord2 += 30
    
    ; Boutons de contrôle
    $button_refresh_startup = GUICtrlCreateButton("Actualiser la liste", $coord1, $coord2, 120, 30)
    GUICtrlSetOnEvent($button_refresh_startup, "_RefreshStartupList")
    
    $button_disable_startup = GUICtrlCreateButton("Désactiver sélection", $coord1 + 130, $coord2, 130, 30)
    GUICtrlSetOnEvent($button_disable_startup, "_DisableStartupItems")
    
    $button_enable_startup = GUICtrlCreateButton("Activer sélection", $coord1 + 270, $coord2, 120, 30)
    GUICtrlSetOnEvent($button_enable_startup, "_EnableStartupItems")
    
    $coord2 += 40
    
    ; TreeView pour afficher les éléments de démarrage
    $idTreeViewStartup = GUICtrlCreateTreeView($coord1, $coord2, 750, 350, BitOR($TVS_CHECKBOXES, $TVS_FULLROWSELECT, $TVS_HASLINES, $TVS_LINESATROOT, $TVS_HASBUTTONS))
    
    $coord2 += 360
    
    ; Barre de progression
    $hProgressBarStartup = GUICtrlCreateProgress($coord1, $coord2, 400, 20)
    GUICtrlSetData($hProgressBarStartup, 0)
    
    ; Texte de progression
    $hProgressTextStartup = GUICtrlCreateLabel("Prêt", $coord1, $coord2 + 25, 400, 40, $SS_LEFT)
    
    ; Charger la liste initiale
    _RefreshStartupList()
EndFunc

; Fonction pour actualiser la liste des éléments de démarrage
Func _RefreshStartupList()
    _UpdateProgressTextStartup("Actualisation de la liste des éléments de démarrage...")
    GUICtrlSetData($hProgressBarStartup, 0)
    
    ; Vider la TreeView
    _GUICtrlTreeView_DeleteAll($idTreeViewStartup)
    _GUICtrlTreeView_BeginUpdate($idTreeViewStartup)
    
    ; Réinitialiser le tableau
    ReDim $aStartupItems[0][6]
    
    ; Créer les nœuds principaux
    Local $hNodeActive = GUICtrlCreateTreeViewItem("Éléments ACTIVÉS", $idTreeViewStartup)
    Local $hNodeDisabled = GUICtrlCreateTreeViewItem("Éléments DÉSACTIVÉS", $idTreeViewStartup)
    
    GUICtrlSetData($hProgressBarStartup, 20)
    
    ; Lire les éléments du registre avec leur état
    _ReadRegistryStartupItemsWithStatus($hNodeActive, $hNodeDisabled)
    
    GUICtrlSetData($hProgressBarStartup, 100)
    
    ; Développer tous les nœuds
    _GUICtrlTreeView_Expand($idTreeViewStartup, $hNodeActive)
    _GUICtrlTreeView_Expand($idTreeViewStartup, $hNodeDisabled)
    
    _GUICtrlTreeView_EndUpdate($idTreeViewStartup)
    _UpdateProgressTextStartup("Liste actualisée - " & UBound($aStartupItems) & " éléments trouvés")
EndFunc

; Fonction pour lire les éléments du registre avec leur état
Func _ReadRegistryStartupItemsWithStatus($hNodeActive, $hNodeDisabled)
    Local $aRegKeys[4]
    $aRegKeys[0] = "HKLM\SOFTWARE\Microsoft\Windows\CurrentVersion\Run"
    $aRegKeys[1] = "HKLM\SOFTWARE\Microsoft\Windows\CurrentVersion\RunOnce"
    $aRegKeys[2] = "HKCU\SOFTWARE\Microsoft\Windows\CurrentVersion\Run"
    $aRegKeys[3] = "HKCU\SOFTWARE\Microsoft\Windows\CurrentVersion\RunOnce"
    
    ; Ajouter les clés 32-bit sur les systèmes 64-bit
    If @OSArch = "X64" Then
        ReDim $aRegKeys[6]
        $aRegKeys[4] = "HKLM\SOFTWARE\Wow6432Node\Microsoft\Windows\CurrentVersion\Run"
        $aRegKeys[5] = "HKLM\SOFTWARE\Wow6432Node\Microsoft\Windows\CurrentVersion\RunOnce"
    EndIf
    
    Local $iTotalItems = 0
    Local $iCurrentItem = 0
    
    ; Compter d'abord le nombre total d'éléments
    For $i = 0 To UBound($aRegKeys) - 1
        Local $sRegKey = $aRegKeys[$i]
        Local $iIndex = 1
        While 1
            Local $sValueName = RegEnumVal($sRegKey, $iIndex)
            If @error Then ExitLoop
            Local $sValueData = RegRead($sRegKey, $sValueName)
            If $sValueData <> "" Then $iTotalItems += 1
            $iIndex += 1
        WEnd
    Next
    
    ; Maintenant traiter chaque élément avec progression
    For $i = 0 To UBound($aRegKeys) - 1
        Local $sRegKey = $aRegKeys[$i]
        Local $iIndex = 1
        
        _UpdateProgressTextStartup("Lecture de " & $sRegKey & "...")
        
        While 1
            Local $sValueName = RegEnumVal($sRegKey, $iIndex)
            If @error Then ExitLoop
            
            Local $sValueData = RegRead($sRegKey, $sValueName)
            If $sValueData <> "" Then
                $iCurrentItem += 1
                _UpdateProgressTextStartup("Analyse " & $iCurrentItem & "/" & $iTotalItems & " : " & $sValueName)
                GUICtrlSetData($hProgressBarStartup, 20 + (($iCurrentItem * 60) / $iTotalItems))
                
                ; Déterminer le type
                Local $sType = $STARTUP_REGISTRY_HKLM
                If StringLeft($sRegKey, 4) = "HKCU" Then $sType = $STARTUP_REGISTRY_HKCU
                
                ; Vérifier l'état dans StartupApproved
                Local $iStatus = _GetStartupItemStatus($sValueName, $sRegKey)
                Local $bEnabled = ($iStatus = 2) ; 2 = Activé, 3 = Désactivé
                
                ; Ajouter à la TreeView avec l'état approprié
                Local $sDisplayText = ""
                If $bEnabled Then
                    $sDisplayText = $sValueName & " (" & $sType & ")"
                    Local $hItem = GUICtrlCreateTreeViewItem($sDisplayText, $hNodeActive)
                Else
                    $sDisplayText = $sValueName & " (" & $sType & ")"
                    Local $hItem = GUICtrlCreateTreeViewItem($sDisplayText, $hNodeDisabled)
                EndIf
                
                ; Ajouter au tableau
                _AddStartupItem($sValueName, $sType, $sValueData, $sRegKey, $bEnabled, $sValueData)
            EndIf
            $iIndex += 1
        WEnd
    Next
EndFunc

; Fonction pour obtenir le statut d'un élément dans StartupApproved
Func _GetStartupItemStatus($sItemName, $sRegKey)
    ; Déterminer la clé StartupApproved correspondante
    Local $sApprovedKey = ""
    
    If StringInStr($sRegKey, "HKLM") Then
        If StringInStr($sRegKey, "Wow6432Node") Then
            $sApprovedKey = "HKLM\SOFTWARE\Microsoft\Windows\CurrentVersion\Explorer\StartupApproved\Run32"
        Else
            $sApprovedKey = "HKLM\SOFTWARE\Microsoft\Windows\CurrentVersion\Explorer\StartupApproved\Run"
        EndIf
    ElseIf StringInStr($sRegKey, "HKCU") Then
        $sApprovedKey = "HKCU\SOFTWARE\Microsoft\Windows\CurrentVersion\Explorer\StartupApproved\Run"
    Else
        Return 2 ; Par défaut activé si pas de clé correspondante
    EndIf
    
    ; Utiliser une commande PowerShell optimisée avec timeout
    Local $sCmd = 'powershell.exe -Command "$ErrorActionPreference=''Stop''; try { $val = Get-ItemProperty -Path ''Registry::' & $sApprovedKey & ''' -Name ''' & $sItemName & '''; $val.''' & $sItemName & '''[0] } catch { 2 }"'
    Local $iPID = Run(@ComSpec & " /c " & $sCmd, @TempDir, @SW_HIDE, $STDOUT_CHILD)
    
    Local $sOutput = ""
    Local $iTimeout = 0
    While 1
        $sOutput &= StdoutRead($iPID)
        If @error Then ExitLoop
        
        ; Timeout après 3 secondes pour éviter les blocages
        Sleep(50)
        $iTimeout += 50
        If $iTimeout > 3000 Then
            ProcessClose($iPID)
            ; Utiliser la méthode AutoIt en fallback
            Local $sBinaryData = RegRead($sApprovedKey, $sItemName)
            If @error Or $sBinaryData = "" Then
                Return 2 ; Par défaut activé
            EndIf
            Return Asc(StringLeft($sBinaryData, 1))
        EndIf
    WEnd
    ProcessClose($iPID)
    
    Local $iStatus = Int(StringStripWS($sOutput, 3))
    
    ; Si pas de résultat valide, utiliser la méthode AutoIt
    If $iStatus < 2 Or $iStatus > 3 Then
        Local $sBinaryData = RegRead($sApprovedKey, $sItemName)
        If @error Or $sBinaryData = "" Then
            Return 2 ; Par défaut activé
        EndIf
        $iStatus = Asc(StringLeft($sBinaryData, 1))
    EndIf
    
    ; Retourner le statut : 2 = Activé, 3 = Désactivé
    Return $iStatus
EndFunc

; Fonction pour définir le statut d'un élément dans StartupApproved
Func _SetStartupItemStatus($sItemName, $sRegKey, $bEnable)
    ; Déterminer la clé StartupApproved correspondante
    Local $sApprovedKey = ""
    
    If StringInStr($sRegKey, "HKLM") Then
        If StringInStr($sRegKey, "Wow6432Node") Then
            $sApprovedKey = "HKLM\SOFTWARE\Microsoft\Windows\CurrentVersion\Explorer\StartupApproved\Run32"
        Else
            $sApprovedKey = "HKLM\SOFTWARE\Microsoft\Windows\CurrentVersion\Explorer\StartupApproved\Run"
        EndIf
    ElseIf StringInStr($sRegKey, "HKCU") Then
        $sApprovedKey = "HKCU\SOFTWARE\Microsoft\Windows\CurrentVersion\Explorer\StartupApproved\Run"
    Else
        Return False
    EndIf
    
    ; Créer les données binaires (12 octets minimum)
    Local $sStatusByte = $bEnable ? Chr(2) : Chr(3) ; 2 = Activé, 3 = Désactivé
    Local $sBinaryData = $sStatusByte & Chr(0) & Chr(0) & Chr(0) & Chr(0) & Chr(0) & Chr(0) & Chr(0) & Chr(0) & Chr(0) & Chr(0) & Chr(0)
    
    ; Écrire dans le registre
    RegWrite($sApprovedKey, $sItemName, "REG_BINARY", $sBinaryData)
    
    Return (@error = 0)
EndFunc

; Fonction pour ajouter un élément au tableau des éléments de démarrage
Func _AddStartupItem($sName, $sType, $sPath, $sRegKey, $bEnabled, $sCommand)
    Local $iSize = UBound($aStartupItems)
    ReDim $aStartupItems[$iSize + 1][6]
    
    $aStartupItems[$iSize][0] = $sName
    $aStartupItems[$iSize][1] = $sType
    $aStartupItems[$iSize][2] = $sPath
    $aStartupItems[$iSize][3] = $sRegKey
    $aStartupItems[$iSize][4] = $bEnabled
    $aStartupItems[$iSize][5] = $sCommand
EndFunc

; Fonction pour désactiver les éléments sélectionnés
Func _DisableStartupItems()
    Local $aSelectedItems = _GetSelectedStartupItems()
    If UBound($aSelectedItems) = 0 Then
        Return
    EndIf
    
    ; Filtrer seulement les éléments actifs
    Local $aActiveItems[0][6]
    For $i = 0 To UBound($aSelectedItems) - 1
        If $aSelectedItems[$i][4] Then ; Si activé
            ReDim $aActiveItems[UBound($aActiveItems) + 1][6]
            For $j = 0 To 5
                $aActiveItems[UBound($aActiveItems) - 1][$j] = $aSelectedItems[$i][$j]
            Next
        EndIf
    Next
    
    If UBound($aActiveItems) = 0 Then
        Return
    EndIf
    
    Local $iSuccess = 0
    Local $iTotal = UBound($aActiveItems)
    
    For $i = 0 To $iTotal - 1
        _UpdateProgressTextStartup("Désactivation " & ($i + 1) & "/" & $iTotal & " : " & $aActiveItems[$i][0])
        GUICtrlSetData($hProgressBarStartup, (($i + 1) * 100) / $iTotal)
        
        If _SetStartupItemStatus($aActiveItems[$i][0], $aActiveItems[$i][3], False) Then
            $iSuccess += 1
            _Log("Logiciel au démarrage Désactivé : " & $aActiveItems[$i][0] & " (" & $aActiveItems[$i][1] & ")", "Démarrage", "Désactivation")
        Else
            _Log("Erreur désactivation : " & $aActiveItems[$i][0], "Démarrage", "Désactivation")
        EndIf
    Next
    
    _UpdateProgressTextStartup("Désactivation terminée : " & $iSuccess & "/" & $iTotal & " éléments")
    
    ; Actualiser la liste
    Sleep(1000)
    _RefreshStartupList()
    
EndFunc

; Fonction pour activer les éléments sélectionnés
Func _EnableStartupItems()
    Local $aSelectedItems = _GetSelectedStartupItems()
    If UBound($aSelectedItems) = 0 Then
        Return
    EndIf
    
    ; Filtrer seulement les éléments désactivés
    Local $aDisabledItems[0][6]
    For $i = 0 To UBound($aSelectedItems) - 1
        If Not $aSelectedItems[$i][4] Then ; Si désactivé
            ReDim $aDisabledItems[UBound($aDisabledItems) + 1][6]
            For $j = 0 To 5
                $aDisabledItems[UBound($aDisabledItems) - 1][$j] = $aSelectedItems[$i][$j]
            Next
        EndIf
    Next
    
    If UBound($aDisabledItems) = 0 Then
        Return
    EndIf
    
    Local $iSuccess = 0
    Local $iTotal = UBound($aDisabledItems)
    
    For $i = 0 To $iTotal - 1
        _UpdateProgressTextStartup("Activation " & ($i + 1) & "/" & $iTotal & " : " & $aDisabledItems[$i][0])
        GUICtrlSetData($hProgressBarStartup, (($i + 1) * 100) / $iTotal)
        
        If _SetStartupItemStatus($aDisabledItems[$i][0], $aDisabledItems[$i][3], True) Then
            $iSuccess += 1
            _Log("Logiciel au démarrage Activé : " & $aDisabledItems[$i][0] & " (" & $aDisabledItems[$i][1] & ")", "Démarrage", "Activation")
        Else
            _Log("Erreur activation : " & $aDisabledItems[$i][0], "Démarrage", "Activation")
        EndIf
    Next
    
    _UpdateProgressTextStartup("Activation terminée : " & $iSuccess & "/" & $iTotal & " éléments")
    
    ; Actualiser la liste
    Sleep(1000)
    _RefreshStartupList()
    
EndFunc

; Fonction pour obtenir les éléments sélectionnés dans la TreeView
Func _GetSelectedStartupItems()
    Local $aSelected[0][6]
    
    ; Parcourir tous les éléments de la TreeView
    Local $hItem = _GUICtrlTreeView_GetFirstItem($idTreeViewStartup)
    
    While $hItem <> 0
        ; Vérifier si l'élément est coché et n'est pas un nœud parent
        If _GUICtrlTreeView_GetChecked($idTreeViewStartup, $hItem) Then
            Local $sItemText = _GUICtrlTreeView_GetText($idTreeViewStartup, $hItem)
            
            ; Chercher l'élément correspondant dans le tableau
            For $i = 0 To UBound($aStartupItems) - 1
                If StringInStr($sItemText, $aStartupItems[$i][0]) > 0 And StringInStr($sItemText, $aStartupItems[$i][1]) > 0 Then
                    ; Ajouter à la liste des sélectionnés
                    ReDim $aSelected[UBound($aSelected) + 1][6]
                    For $j = 0 To 5
                        $aSelected[UBound($aSelected) - 1][$j] = $aStartupItems[$i][$j]
                    Next
                    ExitLoop
                EndIf
            Next
        EndIf
        
        $hItem = _GUICtrlTreeView_GetNext($idTreeViewStartup, $hItem)
    WEnd
    
    Return $aSelected
EndFunc

; Fonction pour mettre à jour le texte de progression
Func _UpdateProgressTextStartup($sText)
    GUICtrlSetData($hProgressTextStartup, $sText)
EndFunc