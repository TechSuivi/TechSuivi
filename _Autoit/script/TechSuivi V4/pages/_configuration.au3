#cs ----------------------------------------------------------------------------

 AutoIt Version: 3.3.16.1
 Author:         myName

 Script Function:
	Template AutoIt script.

#ce ----------------------------------------------------------------------------

#include <GuiConstantsEx.au3>
#include <GuiListView.au3>
#include <FileConstants.au3>
#include <MsgBoxConstants.au3>
#include <InetConstants.au3>
#include <Inet.au3>
#include <WinAPIFiles.au3>


; Script Start - Add your code below here
;----------------------------------- Variables Globales
; Détection améliorée de la version Windows
Global $windows_version = _DetectWindowsVersion()

Func _DetectWindowsVersion()
    Local $sVersion = ""
    
    ; Méthode 1: ProductName dans le registre
    Local $sProductName = RegRead("HKEY_LOCAL_MACHINE\SOFTWARE\Microsoft\Windows NT\CurrentVersion", "ProductName")
    ConsoleWrite("DEBUG: ProductName = " & $sProductName & @CRLF)
    
    ; Méthode 2: CurrentVersion dans le registre
    Local $sCurrentVersion = RegRead("HKEY_LOCAL_MACHINE\SOFTWARE\Microsoft\Windows NT\CurrentVersion", "CurrentVersion")
    ConsoleWrite("DEBUG: CurrentVersion = " & $sCurrentVersion & @CRLF)
    
    ; Méthode 3: CurrentBuild dans le registre
    Local $sCurrentBuild = RegRead("HKEY_LOCAL_MACHINE\SOFTWARE\Microsoft\Windows NT\CurrentVersion", "CurrentBuild")
    ConsoleWrite("DEBUG: CurrentBuild = " & $sCurrentBuild & @CRLF)
    
    ; Méthode 4: @OSVersion macro AutoIt
    ConsoleWrite("DEBUG: @OSVersion = " & @OSVersion & @CRLF)
    
    ; Détection basée sur le build number (plus fiable pour Windows 11)
    If $sCurrentBuild <> "" Then
        Local $iBuild = Number($sCurrentBuild)
        ConsoleWrite("DEBUG: Build number = " & $iBuild & @CRLF)
        
        If $iBuild >= 22000 Then
            $sVersion = "11"
            ConsoleWrite("DEBUG: Détecté Windows 11 via build number" & @CRLF)
        ElseIf $iBuild >= 10240 Then
            $sVersion = "10"
            ConsoleWrite("DEBUG: Détecté Windows 10 via build number" & @CRLF)
        EndIf
    EndIf
    
    ; Fallback: extraction depuis ProductName
    If $sVersion = "" And $sProductName <> "" Then
        Local $aMatches = StringRegExp($sProductName, "(\d+)", 1)
        If IsArray($aMatches) And UBound($aMatches) > 0 Then
            $sVersion = $aMatches[0]
            ConsoleWrite("DEBUG: Détecté Windows " & $sVersion & " via ProductName" & @CRLF)
        EndIf
    EndIf
    
    ; Fallback final: @OSVersion
    If $sVersion = "" Then
        If StringInStr(@OSVersion, "WIN_10") Then
            $sVersion = "10"
            ConsoleWrite("DEBUG: Détecté Windows 10 via @OSVersion" & @CRLF)
        ElseIf StringInStr(@OSVersion, "WIN_11") Then
            $sVersion = "11"
            ConsoleWrite("DEBUG: Détecté Windows 11 via @OSVersion" & @CRLF)
        EndIf
    EndIf
    
    ConsoleWrite("DEBUG: Version Windows finale détectée: " & $sVersion & @CRLF)
    Return $sVersion
EndFunc

;----------------------------------- Gestion Registre
$ini_registre = "ini\personnalisation.ini"
local $Checkbox_registre_[50]
local $label_registre_[50]
$nb_registre = IniRead($ini_registre,"cfg","nb","")
; Position des éléments pour le groupe Registre (moitié gauche)
$left = 90  ; Position horizontale pour les checkboxes
$label_left = 30  ; Position horizontale pour les labels
$top = 135  ; Position verticale de départ

$ini_sys_dir = @ScriptDir & "/ini/sys.ini"



;----------------------------------- Gestion Icones
Global Const $g_sIniPath = @ScriptDir & "\Setting.ini"
Global $g_hDesktop = _WinGetDesktopHandle()
Global $g_hDeskCtrlListView = ControlGetHandle($g_hDesktop, "", "[CLASS:SysListView32;INSTANCE:1]")
ConsoleWrite("$g_sIniPath=" & $g_sIniPath & @CRLF)
ConsoleWrite("$g_hDesktop=" & $g_hDesktop & @CRLF)
ConsoleWrite("$g_hDeskCtrlListView=" & $g_hDeskCtrlListView & @CRLF)





;----------------------------------- Gestion Registre

Func _registre()

; Création du groupe Registre
$group_registre = GUICtrlCreateGroup("Registre", 20, 40, 300, 480)

$button_install_registre = GUICtrlCreateButton("Executer registre", 30, 60, 100, 30)
GUICtrlSetOnEvent($button_install_registre, _read_checkbox)

$button_toggle_all = GUICtrlCreateButton("Tout (Dé)Sélectionner", 140, 60, 120, 30)
GUICtrlSetOnEvent($button_toggle_all, _toggle_all_checkboxes)

$button_default_registre = GUICtrlCreateButton("Par défaut", 30, 95, 100, 30)
GUICtrlSetOnEvent($button_default_registre, _check_default_registre)

$os = IniRead($ini_sys_dir,"OS1","Caption","")
$asResult = StringRegExp($os, '([0-9]{1,3})', 1) ;extraction version windows



			for $a = 1 To $nb_registre  Step 1

				$name_registre= IniRead($ini_registre,$a,"nom","")
				$Checkbox_registre_[$a] = GUICtrlCreateCheckbox($name_registre, $left, $top)
				$label_registre_[$a] = GUICtrlCreateLabel("Non Valide", $label_left, $top+3)


				; Vérifier la compatibilité OS (0 = tous, 10 = Windows 10, 11 = Windows 11)
				Local $os_required = IniRead($ini_registre,$a,"OS","0")
				if $os_required = "0" Or $os_required = $asResult[0] then
					_test_reg($a)
				Else
					GUICtrlSetState($Checkbox_registre_[$a],$GUI_DISABLE)
					GUICtrlSetData($label_registre_[$a],"Incompatible OS")
				EndIf





				$top = $top + 20
			Next

GUICtrlCreateGroup("", -99, -99, 1, 1) ;Fin du groupe

EndFunc

Func _toggle_all_checkboxes()
    Static $is_checked = False
    $is_checked = Not $is_checked
    
    For $i = 1 To $nb_registre
        If Not BitAND(GUICtrlGetState($Checkbox_registre_[$i]), $GUI_DISABLE) Then
            GUICtrlSetState($Checkbox_registre_[$i], $is_checked ? $GUI_CHECKED : $GUI_UNCHECKED)
        EndIf
    Next
EndFunc

Func _check_default_registre()
    For $i = 1 To $nb_registre
        Local $defaut = IniRead($ini_registre, $i, "defaut", "0")
        If Not BitAND(GUICtrlGetState($Checkbox_registre_[$i]), $GUI_DISABLE) Then
            If $defaut = "1" Then
                GUICtrlSetState($Checkbox_registre_[$i], $GUI_CHECKED)
            Else
                GUICtrlSetState($Checkbox_registre_[$i], $GUI_UNCHECKED)
            EndIf
        EndIf
    Next
EndFunc

func _read_checkbox()

		for $a = 1 To $nb_registre  Step 1

			if GUICtrlRead($Checkbox_registre_[$a]) = 1 then ;installation logiciel check

			_exec_registre($a)

			EndIf

		Next

EndFunc

func _exec_registre($a)

	$name_registre = IniRead($ini_registre,$a,"nom","")
	$type_registre = IniRead($ini_registre,$a,"type_registre","")
	$ligne_registre = IniRead($ini_registre,$a,"ligne_registre","")

	ConsoleWrite("Exécution registre pour: " & $name_registre & @CRLF)
	ConsoleWrite("Type: " & $type_registre & @CRLF)
	ConsoleWrite("Ligne registre: " & $ligne_registre & @CRLF)

	if $type_registre = "ligne_registre" And $ligne_registre <> "" Then
		; Parser la ligne de registre pour extraire les composants
		; Format: [HKEY_xxx]"ValueName"=dword:value ou "ValueName"="value"
		
		; Extraire la clé de registre (entre [ et ])
		Local $keyPattern = '\[([^\]]+)\]'
		Local $keyMatch = StringRegExp($ligne_registre, $keyPattern, 1)
		
		if IsArray($keyMatch) And UBound($keyMatch) > 0 Then
			Local $keyname = $keyMatch[0]
			
			; Approche plus simple : chercher chaque format séparément
			Local $valuename = ""
			Local $value = ""
			Local $type = ""
			
			; Format 1: "nom"=dword:valeur_hex
			Local $dwordHexPattern = '"([^"]+)"\s*=\s*dword:([0-9a-fA-F]+)'
			Local $dwordHexMatch = StringRegExp($ligne_registre, $dwordHexPattern, 1)
			
			; Format 2: "nom"=REG_DWORD:valeur_decimal
			Local $dwordDecPattern = '"([^"]+)"\s*=\s*REG_DWORD:([0-9]+)'
			Local $dwordDecMatch = StringRegExp($ligne_registre, $dwordDecPattern, 1)
			
			; Format 3: "nom"="valeur_string"
			Local $stringPattern = '"([^"]+)"\s*=\s*"([^"]*)"'
			Local $stringMatch = StringRegExp($ligne_registre, $stringPattern, 1)
			
			if IsArray($dwordHexMatch) And UBound($dwordHexMatch) >= 2 Then
				; Format dword:hex
				$valuename = $dwordHexMatch[0]
				$type = "REG_DWORD"
				$value = Dec($dwordHexMatch[1])
				ConsoleWrite("Format détecté: DWORD HEX" & @CRLF)
			ElseIf IsArray($dwordDecMatch) And UBound($dwordDecMatch) >= 2 Then
				; Format REG_DWORD:decimal
				$valuename = $dwordDecMatch[0]
				$type = "REG_DWORD"
				$value = Number($dwordDecMatch[1])
				ConsoleWrite("Format détecté: DWORD DECIMAL" & @CRLF)
			ElseIf IsArray($stringMatch) And UBound($stringMatch) >= 2 Then
				; Format string
				$valuename = $stringMatch[0]
				$type = "REG_SZ"
				$value = $stringMatch[1]
				ConsoleWrite("Format détecté: STRING" & @CRLF)
			EndIf
			
			if $valuename <> "" And $type <> "" Then
				
				ConsoleWrite("RegWrite - Clé: " & $keyname & @CRLF)
				ConsoleWrite("RegWrite - Valeur: " & $valuename & @CRLF)
				ConsoleWrite("RegWrite - Type: " & $type & @CRLF)
				ConsoleWrite("RegWrite - Données: " & $value & @CRLF)
				
				; Utiliser RegWrite directement - beaucoup plus simple !
				Local $result = RegWrite($keyname, $valuename, $type, $value)
				Local $errorCode = @error
				
				ConsoleWrite("RegWrite result: " & $result & ", @error: " & $errorCode & @CRLF)
				
				; Vérifier si la valeur a été écrite malgré le retour
				Local $checkValue = RegRead($keyname, $valuename)
				Local $checkError = @error
				ConsoleWrite("Vérification post-écriture: " & $checkValue & ", @error: " & $checkError & @CRLF)
				
				if $result = 1 Or String($checkValue) = String($value) Then
					ConsoleWrite("SUCCESS: Registre appliqué avec RegWrite()" & @CRLF)
					_Log("- Registre : " & $name_registre & " OK", "Configuration", "Registre")
					_test_reg($a)
				Else
					ConsoleWrite("ERREUR: Échec RegWrite() - result=" & $result & ", @error=" & $errorCode & @CRLF)
					ConsoleWrite("Valeur attendue: " & $value & ", valeur lue: " & $checkValue & @CRLF)
					_Log("- Registre : " & $name_registre & " ERREUR", "Configuration", "Registre")
				EndIf
			Else
				ConsoleWrite("ERREUR: Aucun format reconnu dans: " & $ligne_registre & @CRLF)
				ConsoleWrite("Formats supportés:" & @CRLF)
				ConsoleWrite('  - "nom"=dword:00000002' & @CRLF)
				ConsoleWrite('  - "nom"=REG_DWORD:1' & @CRLF)
				ConsoleWrite('  - "nom"="valeur"' & @CRLF)
			EndIf
		Else
			ConsoleWrite("ERREUR: Impossible de parser la clé dans: " & $ligne_registre & @CRLF)
		EndIf
	Else
		ConsoleWrite("ERREUR: Type de registre non supporté ou ligne vide" & @CRLF)
	EndIf

EndFunc

func _test_reg($a)

	$name_registre = IniRead($ini_registre,$a,"nom","")
	$type_registre = IniRead($ini_registre,$a,"type_registre","")
	$ligne_registre = IniRead($ini_registre,$a,"ligne_registre","")

	if $type_registre = "ligne_registre" And $ligne_registre <> "" Then
		; Utiliser la même logique de parsing que _exec_registre()
		
		; Extraire la clé de registre (entre [ et ])
		Local $keyPattern = '\[([^\]]+)\]'
		Local $keyMatch = StringRegExp($ligne_registre, $keyPattern, 1)
		
		if IsArray($keyMatch) And UBound($keyMatch) > 0 Then
			Local $registryKey = $keyMatch[0]
			
			; Utiliser les mêmes patterns que dans _exec_registre()
			Local $valuename = ""
			Local $expectedValue = ""
			
			; Format 1: "nom"=dword:valeur_hex
			Local $dwordHexPattern = '"([^"]+)"\s*=\s*dword:([0-9a-fA-F]+)'
			Local $dwordHexMatch = StringRegExp($ligne_registre, $dwordHexPattern, 1)
			
			; Format 2: "nom"=REG_DWORD:valeur_decimal
			Local $dwordDecPattern = '"([^"]+)"\s*=\s*REG_DWORD:([0-9]+)'
			Local $dwordDecMatch = StringRegExp($ligne_registre, $dwordDecPattern, 1)
			
			; Format 3: "nom"="valeur_string"
			Local $stringPattern = '"([^"]+)"\s*=\s*"([^"]*)"'
			Local $stringMatch = StringRegExp($ligne_registre, $stringPattern, 1)
			
			if IsArray($dwordHexMatch) And UBound($dwordHexMatch) >= 2 Then
				; Format dword:hex
				$valuename = $dwordHexMatch[0]
				$expectedValue = Dec($dwordHexMatch[1])
			ElseIf IsArray($dwordDecMatch) And UBound($dwordDecMatch) >= 2 Then
				; Format REG_DWORD:decimal
				$valuename = $dwordDecMatch[0]
				$expectedValue = Number($dwordDecMatch[1])
			ElseIf IsArray($stringMatch) And UBound($stringMatch) >= 2 Then
				; Format string
				$valuename = $stringMatch[0]
				$expectedValue = $stringMatch[1]
			EndIf
			
			if $valuename <> "" Then
				; Lire la valeur actuelle du registre
				Local $currentValue = RegRead($registryKey, $valuename)
				Local $readError = @error
				
				ConsoleWrite("Test registre - Clé: " & $registryKey & ", Valeur: " & $valuename & @CRLF)
				ConsoleWrite("Attendu: " & $expectedValue & ", Actuel: " & $currentValue & ", @error: " & $readError & @CRLF)
				
				If $readError = 0 And String($currentValue) = String($expectedValue) Then
					GUICtrlSetData($label_registre_[$a],"Valide")
					GUICtrlSetColor($label_registre_[$a], 0x0BE117)
				Else
					GUICtrlSetData($label_registre_[$a],"Non Valide")
					GUICtrlSetColor($label_registre_[$a], 0xE10000)
				EndIf
			Else
				; Impossible de parser la valeur
				GUICtrlSetData($label_registre_[$a],"Format invalide")
				GUICtrlSetColor($label_registre_[$a], 0xFF8C00)
			EndIf
		Else
			; Impossible de parser la clé
			GUICtrlSetData($label_registre_[$a],"Clé invalide")
			GUICtrlSetColor($label_registre_[$a], 0xFF8C00)
		EndIf
	Else
		; Type non supporté ou ligne vide
		GUICtrlSetData($label_registre_[$a],"Non configuré")
		GUICtrlSetColor($label_registre_[$a], 0x808080)
	EndIf

EndFunc


;----------------------------------- Gestion Icones
Func _icones()

	; Création du groupe Registre
	$group_icones = GUICtrlCreateGroup("Icones", 330, 40, 240, 60)

	$button_restore_icone = GUICtrlCreateButton("Restaurer position icones", 340, 60, 150, 30)
	GUICtrlSetOnEvent($button_restore_icone, _RestoreSetting_icones)	

	$button_sauve_icone = GUICtrlCreateButton("Sauve", 500, 60, 50, 30)
	GUICtrlSetOnEvent($button_sauve_icone, _SaveSetting_icones)
	
	; Vérifier si le bouton doit être désactivé
	If IniRead(@ScriptDir & "\ini\cfg.ini", "config", "verrouillage_sauve_icones", "0") = "1" Then
		GUICtrlSetState($button_sauve_icone, $GUI_DISABLE)
	EndIf


EndFunc



;----------------------------------------------------------------------------------------
Func _SetShortcutPos($Name = "", $X = 0, $Y = 0)
    Local $iIndex = _GUICtrlListView_FindInText($g_hDeskCtrlListView, $Name)
    If $iIndex == -1 Then
        Return SetError(1, 0, "! Could not find icon: " & $Name)
    EndIf
    _GUICtrlListView_SetItemPosition($g_hDeskCtrlListView, $iIndex, $X, $Y)
    Return "- " & $Name & "=" & $X & "," & $Y
EndFunc   ;==>_SetShortcutPos
;----------------------------------------------------------------------------------------
Func _SaveSetting_icones()
    Local $iItemCount, $sTxt, $aPos
    $iItemCount = _GUICtrlListView_GetItemCount($g_hDeskCtrlListView)
    ConsoleWrite("> Save Setting:" & @CRLF)
    ConsoleWrite("- Item Count:" & $iItemCount & @CRLF)

    $sTxt = "[Setting]" & @CRLF

    For $i = 0 To $iItemCount - 1
        $sTxt &= _GUICtrlListView_GetItemText($g_hDeskCtrlListView, $i)
        $aPos = _GUICtrlListView_GetItemPosition($g_hDeskCtrlListView, $i)
        $sTxt &= "=" & $aPos[0] & "," & $aPos[1] & @CRLF
    Next

    ConsoleWrite($sTxt & @CRLF)

    Local $hFileOpen = FileOpen($g_sIniPath, $FO_OVERWRITE + $FO_CREATEPATH + $FO_UTF8_NOBOM)
    If $hFileOpen = -1 Then
        ConsoleWrite("ERREUR: Impossible d'écrire le fichier de sauvegarde des icônes" & @CRLF)
        _Log("- Icônes : Sauvegarde ERREUR", "Configuration", "Icônes")
        Return False
    EndIf

    FileWrite($hFileOpen, $sTxt)
    _Log("- Icônes : Sauvegarde position OK", "Configuration", "Icônes")

EndFunc   ;==>_SaveSetting
;----------------------------------------------------------------------------------------
Func _RestoreSetting_icones()
    ConsoleWrite("> Restore Setting:" & @CRLF)
    Local $aPos

    ; Read the INI section labelled 'Setting'. This will return a 2 dimensional array.
    Local $aArray = IniReadSection($g_sIniPath, "Setting")

    If Not @error Then
        ; Enumerate through the array displaying the keys and their respective values.
        For $i = 1 To $aArray[0][0]
            $aPos = StringSplit($aArray[$i][1], ",")
            If $aPos[0] = 2 Then
                ConsoleWrite(_SetShortcutPos($aArray[$i][0], $aPos[1], $aPos[2]) & @CRLF)
            EndIf
        Next
        _Log("- Icônes : Restauration position OK", "Configuration", "Icônes")
    Else
        ConsoleWrite("ERREUR: Impossible de lire le fichier de configuration des icônes" & @CRLF)
        _Log("- Icônes : Restauration ERREUR (fichier non trouvé)", "Configuration", "Icônes")
    EndIf

EndFunc   ;==>_RestoreSetting
;----------------------------------------------------------------------------------------
;  https://www.autoitscript.com/forum/topic/119783-desktop-class-workerw/#comment-903081
; <_WinGetDesktopHandle.au3>
; Function to get the Windows' Desktop Handle.
;   Since this is no longer a simple '[CLASS:Progman]' on Aero-enabled desktops, this method uses a slightly
;   more involved method to find the correct Desktop Handle.
;
; Author: Ascend4nt, credits to Valik for pointing out the Parent->Child relationship: Desktop->'SHELLDLL_DefView'
;----------------------------------------------------------------------------------------
Func _WinGetDesktopHandle()
    Local $i, $hDeskWin, $hSHELLDLL_DefView, $h_Listview_Configs, $aWinList
    ; The traditional Windows Classname for the Desktop, not always so on newer O/S's
    $hDeskWin = WinGetHandle("[CLASS:Progman]")
    ; Parent->Child relationship: Desktop->SHELLDLL_DefView
    $hSHELLDLL_DefView = ControlGetHandle($hDeskWin, '', '[CLASS:SHELLDLL_DefView; INSTANCE:1]')
    ; No luck with finding the Desktop and/or child?
    If $hDeskWin = '' Or $hSHELLDLL_DefView = '' Then
        ; Look through a list of WorkerW windows - one will be the Desktop on Windows 7+ O/S's
        $aWinList = WinList("[CLASS:WorkerW]")
        For $i = 1 To $aWinList[0][0]
            $hSHELLDLL_DefView = ControlGetHandle($aWinList[$i][1], '', '[CLASS:SHELLDLL_DefView; INSTANCE:1]')
            If $hSHELLDLL_DefView <> '' Then
                $hDeskWin = $aWinList[$i][1]
                ExitLoop
            EndIf
        Next
    EndIf
    ; Parent->Child relationship: Desktop->SHELDLL_DefView->SysListView32
    $h_Listview_Configs = ControlGetHandle($hSHELLDLL_DefView, '', '[CLASS:SysListView32; INSTANCE:1]')
    If $h_Listview_Configs = '' Then Return SetError(-1, 0, '')
    Return SetExtended($h_Listview_Configs, $hDeskWin)
EndFunc


;----------------------------------- Backup SML

Func _backupSML()

	; Création du groupe Registre
	$group_backupsml = GUICtrlCreateGroup("BackupSML", 330, 110, 240, 60)

	$button_restore_menu = GUICtrlCreateButton("Restaurer Menu Windows", 340, 130, 150, 30)
	GUICtrlSetOnEvent($button_restore_menu, _RestoreSetting_backupSML)	

EndFunc

Func _RestoreSetting_backupSML()
    ; Télécharge et extrait les fichiers
    Local $sDestPath = DownloadAndExtractBackupSML()
    If @error Then
        Local $iError = @error
        Local $sErrorMsg = $sDestPath
        MsgBox($MB_ICONERROR, "Erreur BackupSML", "Erreur lors du téléchargement/extraction:" & @CRLF & @CRLF & $sErrorMsg & @CRLF & @CRLF & "Code d'erreur: " & $iError)
        ConsoleWrite("ERREUR _RestoreSetting_backupSML: " & $sErrorMsg & " (Code: " & $iError & ")" & @CRLF)
        Return SetError($iError, 0, False)
    EndIf
    
    ConsoleWrite("Téléchargement et extraction réussis. Dossier: " & $sDestPath & @CRLF)
    
    ; Vérifier que le dossier de destination existe
    If Not FileExists($sDestPath) Then
        MsgBox($MB_ICONERROR, "Erreur BackupSML", "Le dossier d'extraction n'existe pas: " & @CRLF & $sDestPath)
        ConsoleWrite("ERREUR: Dossier d'extraction non trouvé: " & $sDestPath & @CRLF)
        Return SetError(7, 0, False)
    EndIf
    
    ; Restaure le menu Windows
    Local $bResult = backSML()
    If Not $bResult Then
        MsgBox($MB_ICONERROR, "Erreur BackupSML", "Erreur lors de l'exécution de la restauration du menu Windows")
        ConsoleWrite("ERREUR: Échec de l'exécution backSML()" & @CRLF)
        Return SetError(8, 0, False)
    EndIf
    
    ConsoleWrite("SUCCESS: Restauration BackupSML terminée avec succès" & @CRLF)
    Return True
EndFunc


Func backSML()
    Local $sDir = @ScriptDir & "\Download\BackupSML\"
    Local $sExeFile = ""
    Local $sCommande = ""
    Local $CmdPid = 0
    
    ConsoleWrite("Fonction backSML() - Version Windows détectée: " & $windows_version & @CRLF)
    ConsoleWrite("Répertoire de travail: " & $sDir & @CRLF)
    
    ; Vérifier que le répertoire existe
    If Not FileExists($sDir) Then
        ConsoleWrite("ERREUR: Répertoire BackupSML non trouvé: " & $sDir & @CRLF)
        Return False
    EndIf
    
    ; Simplification: chercher directement dans le sous-dossier BackupSML (structure typique après extraction ZIP)
    Local $sTypicalDir = $sDir & "BackupSML\"
    Local $sTypicalPath = $sTypicalDir & "BackupSML_x64.exe"
    
    ConsoleWrite("Vérification du chemin typique: " & $sTypicalPath & @CRLF)
    
    If FileExists($sTypicalPath) Then
        ; Utiliser le sous-dossier BackupSML comme répertoire de travail (comme dans l'ancien code)
        $sDir = $sTypicalDir
        ConsoleWrite("Répertoire de travail mis à jour: " & $sDir & @CRLF)
        ConsoleWrite("Exécutable trouvé: BackupSML_x64.exe" & @CRLF)
    Else
        ConsoleWrite("ERREUR: Fichier BackupSML_x64.exe non trouvé dans " & $sTypicalPath & @CRLF)
        ConsoleWrite("Vérifiez que le fichier BackupSML.zip a été correctement extrait dans " & $sDir & @CRLF)
        Return False
    EndIf
    
    ConsoleWrite("Fichier exécutable final: " & $sExeFile & @CRLF)
    ConsoleWrite("Répertoire de travail final: " & $sDir & @CRLF)
    
    ; Définir la commande selon la version de Windows (utilisation du chemin relatif comme dans l'ancien code)
    ConsoleWrite("DEBUG backSML: Version Windows utilisée pour la commande: '" & $windows_version & "'" & @CRLF)
    
    If $windows_version = "10" Then
        $sCommande = " .\BackupSML_x64.exe /R 20200717_143306"
        ConsoleWrite("Commande Windows 10: " & $sCommande & @CRLF)
    ElseIf $windows_version = "11" Then
        $sCommande = " .\BackupSML_x64.exe /R 20230622_110045"
        ConsoleWrite("Commande Windows 11: " & $sCommande & @CRLF)
    Else
        ConsoleWrite("ERREUR: Version Windows non reconnue: '" & $windows_version & "'" & @CRLF)
        ConsoleWrite("ERREUR: Impossible de déterminer les paramètres BackupSML appropriés" & @CRLF)
        
        ; Afficher un message d'erreur et arrêter l'exécution
        MsgBox($MB_ICONERROR, "Erreur - Version Windows", "Impossible de détecter la version Windows correctement." & @CRLF & @CRLF & _
               "Version détectée: " & $windows_version & @CRLF & @CRLF & _
               "Versions supportées: Windows 10, Windows 11" & @CRLF & @CRLF & _
               "Veuillez vérifier votre système ou contacter le support technique." & @CRLF & @CRLF & _
               "Informations de debug disponibles dans la console.")
        
        Return False
    EndIf
    
    ; Exécution de la commande
    ConsoleWrite("=== DEBUG BACKUPSML ===" & @CRLF)
    ConsoleWrite("Répertoire de travail: " & $sDir & @CRLF)
    ConsoleWrite("Fichier exécutable: " & $sExeFile & @CRLF)
    ConsoleWrite("Commande: " & $sCommande & @CRLF)
    ConsoleWrite("Commande complète: powershell.exe" & $sCommande & @CRLF)
    ConsoleWrite("Version Windows: " & $windows_version & @CRLF)
    
    ; Vérifier que le fichier existe avant de le lancer (chemin relatif)
    Local $sFullExePath = $sDir & "BackupSML_x64.exe"
    If Not FileExists($sFullExePath) Then
        ConsoleWrite("ERREUR: Fichier BackupSML_x64.exe non trouvé: " & $sFullExePath & @CRLF)
        _Log("ERREUR: BackupSML non trouvé dans " & $sDir, "Configuration", "BackupSML")
        Return False
    EndIf
    
    ConsoleWrite("Exécution de la commande BackupSML..." & @CRLF)
    $CmdPid = Run("C:\Windows\System32\WindowsPowerShell\v1.0\powershell.exe " & $sCommande, $sDir, @SW_HIDE)
    
    ; Vérifier que la commande a été lancée
    If $CmdPid = 0 Then
        ConsoleWrite("ERREUR: Impossible de lancer la commande BackupSML" & @CRLF)
        _Log("ERREUR: Impossible de lancer BackupSML", "Configuration", "BackupSML")
        Return False
    EndIf
    
    ConsoleWrite("Commande BackupSML lancée avec succès (PID: " & $CmdPid & ")" & @CRLF)
    _Log("- BackupSML : Configuration du menu démarrer OK", "Configuration", "BackupSML")
    Return True
EndFunc   ;==>backSML

Func DownloadAndExtractBackupSML()
    Local $sFileName = "BackupSML.zip"
    Local $sDestDir = @ScriptDir & "\Download\BackupSML\"
    Local $sZipPath = $sDestDir & $sFileName
    Local $bSuccess = True
    
    ; Création du dossier de destination
    DirCreate($sDestDir)
    
    ; Vérifier d'abord si le fichier existe localement dans le projet
    Local $sLocalPath = @ScriptDir & "\..\..\..\logiciels\" & $sFileName
    ConsoleWrite("Vérification du fichier local : " & $sLocalPath & @CRLF)
    
    If FileExists($sLocalPath) Then
        ; Utiliser le fichier local
        ConsoleWrite("Fichier local trouvé, copie en cours..." & @CRLF)
        If Not FileCopy($sLocalPath, $sZipPath, 9) Then
            ConsoleWrite("ERREUR : Impossible de copier le fichier local" & @CRLF)
            Return SetError(1, 0, "Impossible de copier le fichier local")
        EndIf
        ConsoleWrite("Fichier local copié avec succès" & @CRLF)
    Else
        ; Fichier local non trouvé, essayer le téléchargement
        ConsoleWrite("Fichier local non trouvé, téléchargement en cours : " & $sFileName & @CRLF)
        
        ; Création du fichier temporaire
        Local $sTempFile = _WinAPI_GetTempFileName(@TempDir)
        
        ; Construction de l'URL à partir du fichier cfg.ini
        Local $sUrl = ""
        Local $sFilePath = "uploads/autoit/logiciels/" & $sFileName
        Local $sUrlBase = IniRead(@ScriptDir & "\ini\cfg.ini", "config", "url_base", "")
        
        If $sUrlBase <> "" Then
            ; Utilisation de url_base si disponible
            If StringRight($sUrlBase, 1) <> "/" Then $sUrlBase = $sUrlBase & "/"
            $sUrl = $sUrlBase & $sFilePath
        Else
            ; Fallback vers l'ancienne méthode avec protocole/ip/chemin
            Local $sProto = IniRead(@ScriptDir & "\ini\cfg.ini", "dl", "protocole", "")
            Local $sIp = IniRead(@ScriptDir & "\ini\cfg.ini", "dl", "ip", "")
            Local $sChemin = IniRead(@ScriptDir & "\ini\cfg.ini", "dl", "chemin", "")
            
            ; S'assurer que le chemin se termine par /
            If StringRight($sChemin, 1) <> "/" Then $sChemin = $sChemin & "/"
            
            $sUrl = $sProto & "://" & $sIp & $sChemin & $sFilePath
        EndIf
        
        ConsoleWrite("URL de téléchargement : " & $sUrl & @CRLF)
        
        ; Vérification que l'URL est valide
        If $sUrl = "" Or $sUrl = "/" Then
            ConsoleWrite("ERREUR : URL de téléchargement invalide" & @CRLF)
            Return SetError(2, 0, "URL de téléchargement invalide")
        EndIf
        
        ; Téléchargement du fichier
        Local $hDownload = InetGet($sUrl, $sTempFile, $INET_FORCERELOAD, $INET_DOWNLOADBACKGROUND)
        
        ; Boucle de téléchargement avec timeout
        Local $iTimeout = 0
        Do
            Sleep(250)
            $iTimeout += 250
            Local $iBytes = InetGetInfo($hDownload, $INET_DOWNLOADREAD)
            Local $iFileSize = InetGetInfo($hDownload, $INET_DOWNLOADSIZE)
            Local $iComplete = InetGetInfo($hDownload, $INET_DOWNLOADCOMPLETE)
            
            If $iFileSize > 0 Then
                Local $iProgress = ($iBytes * 100) / $iFileSize
                ConsoleWrite("Téléchargement : " & $sFileName & " (" & Round($iProgress, 1) & "%)" & @CRLF)
            EndIf
            
            ; Timeout après 60 secondes
            If $iTimeout > 60000 Then
                ConsoleWrite("Timeout de téléchargement : " & $sFileName & @CRLF)
                InetClose($hDownload)
                FileDelete($sTempFile)
                Return SetError(3, 0, "Timeout de téléchargement")
            EndIf
        Until $iComplete
        
        ; Vérification si le téléchargement a réussi
        Local $iError = InetGetInfo($hDownload, $INET_DOWNLOADERROR)
        Local $iBytesRead = InetGetInfo($hDownload, $INET_DOWNLOADREAD)
        InetClose($hDownload)
        
        If $iError <> 0 Then
            Local $sErrorMsg = "Erreur téléchargement (Code: " & $iError & ")"
            Switch $iError
                Case 1
                    $sErrorMsg = "Erreur téléchargement : URL invalide"
                Case 2
                    $sErrorMsg = "Erreur téléchargement : Connexion impossible"
                Case 3
                    $sErrorMsg = "Erreur téléchargement : Fichier non trouvé (404)"
                Case 6
                    $sErrorMsg = "Erreur téléchargement : Accès refusé"
                Case 13
                    $sErrorMsg = "Erreur téléchargement : Fichier non accessible sur le serveur"
            EndSwitch
            
            ConsoleWrite($sErrorMsg & " : " & $sFileName & @CRLF)
            FileDelete($sTempFile)
            Return SetError(4, $iError, $sErrorMsg)
        ElseIf $iBytesRead = 0 Then
            ConsoleWrite("Erreur : Fichier vide téléchargé : " & $sFileName & @CRLF)
            FileDelete($sTempFile)
            Return SetError(5, 0, "Fichier vide téléchargé")
        EndIf
        
        ConsoleWrite("Téléchargement terminé : " & $sFileName & @CRLF)
        
        ; Déplacement du fichier téléchargé
        FileMove($sTempFile, $sZipPath, 9)
    EndIf
    
    ; Décompression du fichier ZIP
    ConsoleWrite("Décompression en cours : " & $sFileName & @CRLF)
    
    ; Vérification que le fichier zip existe
    If Not FileExists($sZipPath) Then
        ConsoleWrite("Erreur : Fichier zip non trouvé : " & $sZipPath & @CRLF)
        Return SetError(6, 0, "Fichier zip non trouvé")
    EndIf
    
    ; Tentative de décompression avec PowerShell d'abord
    Local $sPSCmd = 'powershell.exe -Command "Expand-Archive -Path ''' & $sZipPath & ''' -DestinationPath ''' & $sDestDir & ''' -Force"'
    Local $iPSResult = RunWait($sPSCmd, @ScriptDir, @SW_HIDE)
    
    If $iPSResult <> 0 Then
        ConsoleWrite("PowerShell échoué, tentative avec _Zip_UnzipAll..." & @CRLF)
        ; Fallback vers _Zip_UnzipAll comme dans la page nettoyage
        Local $iZipResult = _Zip_UnzipAll($sZipPath, $sDestDir, 0)
        
        If @error <> 0 Or $iZipResult = 0 Then
            ConsoleWrite("Erreur de décompression avec les deux méthodes : " & $sFileName & @CRLF)
            Return SetError(7, $iPSResult, "Erreur de décompression")
        Else
            ConsoleWrite("Décompression réussie avec _Zip_UnzipAll" & @CRLF)
        EndIf
    Else
        ConsoleWrite("Décompression réussie avec PowerShell" & @CRLF)
    EndIf
    
    ConsoleWrite("Décompression réussie : " & $sFileName & @CRLF)
    
    ; Suppression du fichier zip après décompression réussie
    FileDelete($sZipPath)
    
    ; Retourner le chemin du dossier de destination
    Return $sDestDir
EndFunc   ;==>DownloadAndExtractBackupSML



; Fonction helper pour rechercher récursivement BackupSML_x64.exe
Func _FindBackupSMLRecursive($sSearchDir)
    Local $sExePath = ""
    
    ; Vérifier d'abord dans le dossier courant
    Local $sCurrentExe = $sSearchDir & "BackupSML_x64.exe"
    If FileExists($sCurrentExe) Then
        ConsoleWrite("Trouvé dans: " & $sCurrentExe & @CRLF)
        Return $sCurrentExe
    EndIf
    
    ; Chercher dans les sous-dossiers
    Local $hDirSearch = FileFindFirstFile($sSearchDir & "*")
    If $hDirSearch <> -1 Then
        While True
            Local $sItem = FileFindNextFile($hDirSearch)
            If @error Then ExitLoop
            
            ; Ignorer les fichiers cachés et les dossiers système
            If StringLeft($sItem, 1) = "." Then ContinueLoop
            
            Local $sItemPath = $sSearchDir & $sItem
            If StringInStr(FileGetAttrib($sItemPath), "D") Then ; C'est un dossier
                ConsoleWrite("Recherche dans le dossier: " & $sItemPath & @CRLF)
                
                ; Recherche récursive dans le sous-dossier
                Local $sSubResult = _FindBackupSMLRecursive($sItemPath & "\")
                If $sSubResult <> "" Then
                    $sExePath = $sSubResult
                    ExitLoop
                EndIf
            EndIf
        WEnd
        FileClose($hDirSearch)
    EndIf
    
    Return $sExePath
EndFunc   ;==>_FindBackupSMLRecursive
