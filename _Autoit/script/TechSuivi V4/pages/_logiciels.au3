#cs ----------------------------------------------------------------------------

 AutoIt Version: 3.3.16.1
 Author:         myName

 Script Function:
	Template AutoIt script.

#ce ----------------------------------------------------------------------------

; Script Start - Add your code below here

#include <ProgressConstants.au3>
#include <StaticConstants.au3>
#include <GUIConstantsEx.au3>
#include <WindowsConstants.au3>
#include <Array.au3>
#include <File.au3>
#include <TreeViewConstants.au3>
#include <GuiTreeView.au3>
#include <WinAPIFiles.au3>
#include <InetConstants.au3>
#include <EditConstants.au3>
#include <StringConstants.au3>

; Définition manuelle des constantes manquantes
;Global Const $ES_MULTILINE = 0x0004
;Global Const $ES_AUTOVSCROLL = 0x0040

$ini_log = "ini\logiciels.ini"
local $Checkbox_log_[50]
local $label_log_[50]
$nb_log  = IniRead($ini_log,"cfg","nb","")
global $button_toggle_all_log
global $button_defaut_log
Global $toggle_state = False ; Variable pour suivre l'état de bascule
Global $group_progress ; Variable globale pour le groupe de progression
Global $hProgressBar ; Variable globale pour la barre de progression
Global $hProgressText ; Variable globale pour le texte de la barre de progression
Global $edit_cmd ; Variable globale pour le contrôle d'édition des commandes
Global $hLog ; Variable globale pour le fichier de log
Global $idTreeView ; Variable globale pour la TreeView des programmes installés
Global $button_install_log
Global $button_uninstall_log
Global $button_refresh_log




func _logiciels()
    $info_log = 0
    $coord1 = 40 ; Décalage pour être dans le groupe
    $coord2 = 60 ; Décalage pour être dans le groupe

    ; Création du groupe Installation
    $group_install = GUICtrlCreateGroup("Installation", 20, 40, 320, 480)

    ; Création des cases à cocher pour les logiciels (partie verte)
    for $a = 1 To $nb_log  Step 1
        $name_log = IniRead($ini_log,$a,"nom","")
        $type_installation = IniRead($ini_log,$a,"type_installation","")

        ; Détermination du label selon le type d'installation
        if $type_installation = "winget" Then
            $input_log = "Winget"
        elseif $type_installation = "fichier" Then
            $input_log = "FTP"
        Else
            $input_log = "Non défini"
        EndIf

        $label_log_[$a] = GUICtrlCreateLabel($input_log, $coord1, $coord2+5, 70, 25)
        $Checkbox_log_[$a] = GUICtrlCreateCheckbox($name_log, $coord1+80, $coord2, 210, 25)



        $coord2 = $coord2 + 20
    Next

    $coord2 = $coord2 + 20

    ; Boutons
    $button_toggle_all_log = GUICtrlCreateButton("Tout (Dé)Sélectionner",$coord1,$coord2,130,30)
    GUICtrlSetOnEvent($button_toggle_all_log, _toggle_all_log)

    $button_defaut_log = GUICtrlCreateButton("Par défaut",$coord1+135,$coord2,130,30)
    GUICtrlSetOnEvent($button_defaut_log, _checkDefaultlog)

    $button_install_log = GUICtrlCreateButton("    Installation    ",$coord1,$coord2+35,265,30)
    GUICtrlSetOnEvent($button_install_log, _modelog)

    GUICtrlCreateGroup("", -99, -99, 1, 1) ; Fin du groupe Installation


    ; Création de la TreeView pour les programmes installés
    $group_uninstall = GUICtrlCreateGroup("Désinstallation", 360, 40, 400, 420)
    $idTreeView = GUICtrlCreateTreeView(370, 60, 380, 350, BitOR($TVS_CHECKBOXES, $TVS_FULLROWSELECT))
    _GUICtrlTreeView_BeginUpdate($idTreeView)
    
    Local $aListeProgInst = _ListeProgrammes()
    For $i = 0 To UBound($aListeProgInst) - 1
        $hItem = GUICtrlCreateTreeViewItem($aListeProgInst[$i][0], $idTreeView)
        _GUICtrlTreeView_SetIcon($idTreeView, $hItem, $aListeProgInst[$i][1], $aListeProgInst[$i][2])
    Next
    
    _GUICtrlTreeView_EndUpdate($idTreeView)

    ; Nouveaux boutons sous la TreeView
    $coord_uninstall_x = 370
    $coord_uninstall_y = 420

    $button_refresh_log = GUICtrlCreateButton("Actualiser la liste", $coord_uninstall_x, $coord_uninstall_y, 130, 30)
    GUICtrlSetOnEvent($button_refresh_log, _refreshTreeView)
    
    $button_treeview_uninstall = GUICtrlCreateButton("Désinstaller sélection", $coord_uninstall_x+140, $coord_uninstall_y, 150, 30)
    GUICtrlSetOnEvent($button_treeview_uninstall, _uninstalllog)
    
    GUICtrlCreateGroup("", -99, -99, 1, 1) ; Fin du groupe Désinstallation

    ; Groupe Progression
    $group_progress = GUICtrlCreateGroup("Progression", 360, 470, 400, 50)
    
    ; Ajout de la barre de progression
    $hProgressBar = GUICtrlCreateProgress(370, 490, 380, 20, $PBS_SMOOTH)
    GUICtrlSetData($hProgressBar, 0)
    
    ; Ajout du texte pour la barre de progression
    ;$hProgressText = GUICtrlCreateLabel("", 380, 515, 400, 20, $SS_CENTER) ; Masqué pour gagner de la place ou à repositionner si besoin

    GUICtrlCreateGroup("", -99, -99, 1, 1) ; Fin du groupe Progression

    ; Ajout du contrôle d'édition pour les commandes
    ;$edit_cmd = GUICtrlCreateEdit("", $coord1, $coord2+110, 300, 100, $ES_MULTILINE + $ES_AUTOVSCROLL + $WS_VSCROLL)
EndFunc

Func _toggle_all_log()
    $toggle_state = Not $toggle_state
    
    for $a = 1 To $nb_log  Step 1
        If $toggle_state Then
            GUICtrlSetState($Checkbox_log_[$a], $GUI_CHECKED)
        Else
            GUICtrlSetState($Checkbox_log_[$a], $GUI_UNCHECKED)
        EndIf
    Next
Endfunc

Func _checkDefaultlog()
    for $a = 1 To $nb_log  Step 1
        If IniRead($ini_log,$a,"defaut","") = "1" Then
            GUICtrlSetState($Checkbox_log_[$a], $GUI_CHECKED)
        Else
            GUICtrlSetState($Checkbox_log_[$a], $GUI_UNCHECKED)
        EndIf
    Next
Endfunc

Func _modelog()
    for $a = 1 To $nb_log  Step 1
        if GUICtrlRead($Checkbox_log_[$a]) = 1 then ;installation logiciel check
            $name_log = IniRead($ini_log,$a,"nom","")
            $type_installation = IniRead($ini_log,$a,"type_installation","")
            
            ; Installation selon le type défini dans le fichier INI
            if $type_installation = "winget" Then
                _winget_install($a) ;> installation en mode winget
                ; Rafraîchir la liste après chaque installation winget
                Sleep(2000) ; Attendre que l'installation soit bien enregistrée dans le registre
                _refreshTreeView()
            Elseif $type_installation = "fichier" Then
                _ftp_install($a) ;> Installation en mode FTP
                ; Rafraîchir la liste après chaque installation FTP
                Sleep(3000) ; Attendre plus longtemps pour les installations manuelles
                _refreshTreeView()
            Else
                MsgBox(0,"",$name_log & " : Type d'installation non défini")
            EndIf
        EndIf
    Next
EndFunc

Func _winget_install($id)
	$name = IniRead($ini_log,$id,"nom","")
	$commande_winget = IniRead($ini_log,$id,"commande_winget","")
	
	; Vérification que la commande winget existe
	if $commande_winget = "" Then
		_UpdateProgressText("Erreur : Commande winget manquante pour " & $name)
		MsgBox(0, "Erreur", "Commande winget manquante pour " & $name)
		; Décocher et colorer le label en rouge (erreur)
		GUICtrlSetState($Checkbox_log_[$id], $GUI_UNCHECKED)
		GUICtrlSetColor($label_log_[$id], 0xFF0000) ; Rouge
		GUICtrlSetData($label_log_[$id], "Erreur")
		Return
	EndIf
	
	_UpdateProgressText("Installation winget en cours : " & $name)
	
	; Construction de la commande winget complète
	$cmd_complete = "winget install " & $commande_winget & " --accept-source-agreements --accept-package-agreements --silent"
	
	; Exécution de la commande
	$CmdPid = RunWait(@ComSpec & " /c " & $cmd_complete, @ScriptDir, @SW_HIDE)
	
	; Vérification du code de retour avec messages explicites
	if $CmdPid = 0 Then
		_UpdateProgressText("Installation terminée avec succès : " & $name)
		_Log("Installation réussie : " & $name, "Logiciels", "Installation")
		; Décocher et colorer le label en vert (installé)
		GUICtrlSetState($Checkbox_log_[$id], $GUI_UNCHECKED)
		GUICtrlSetColor($label_log_[$id], 0x008000) ; Vert
		GUICtrlSetData($label_log_[$id], "Installé")
	ElseIf $CmdPid = -1978335189 Then
		; Cas spécial : déjà installé
		Local $errorMessage = _GetWingetErrorMessage($CmdPid)
		_UpdateProgressText($errorMessage & " : " & $name)
		_Log($errorMessage & " : " & $name, "Logiciels", "Installation")
		; Décocher et colorer le label en bleu (déjà installé)
		GUICtrlSetState($Checkbox_log_[$id], $GUI_UNCHECKED)
		GUICtrlSetColor($label_log_[$id], 0x0000FF) ; Bleu
		GUICtrlSetData($label_log_[$id], "Déjà installé")
	Else
		Local $errorMessage = _GetWingetErrorMessage($CmdPid)
		_UpdateProgressText("Erreur lors de l'installation : " & $name)
		_Log($errorMessage & " : " & $name, "Logiciels", "Installation")
		; Décocher et colorer le label en rouge (erreur)
		GUICtrlSetState($Checkbox_log_[$id], $GUI_UNCHECKED)
		GUICtrlSetColor($label_log_[$id], 0xFF0000) ; Rouge
		GUICtrlSetData($label_log_[$id], "Erreur")
	EndIf
EndFunc

; Fonction pour interpréter les codes d'erreur winget
Func _GetWingetErrorMessage($errorCode)
	Switch $errorCode
		Case -1978335189, 0x8A15002B
			Return "Logiciel déjà installé ou version plus récente présente"
		Case -1978335188, 0x8A15002C
			Return "Aucune version applicable trouvée"
		Case -1978335187, 0x8A15002D
			Return "Échec du téléchargement"
		Case -1978335186, 0x8A15002E
			Return "Échec de l'installation"
		Case -1978335185, 0x8A15002F
			Return "Fichier d'installation corrompu"
		Case -1978335184, 0x8A150030
			Return "Privilèges administrateur requis"
		Case -1978335183, 0x8A150031
			Return "Redémarrage requis"
		Case -1978335182, 0x8A150032
			Return "Application en cours d'utilisation"
		Case -1978335181, 0x8A150033
			Return "Paquet non trouvé"
		Case -1978335180, 0x8A150034
			Return "Source non disponible"
		Case -1978335179, 0x8A150035
			Return "Plusieurs paquets trouvés"
		Case -1978335178, 0x8A150036
			Return "Accord de licence requis"
		Case -1978335177, 0x8A150037
			Return "Système non supporté"
		Case -1978335176, 0x8A150038
			Return "Dépendances manquantes"
		Case -1978335175, 0x8A150039
			Return "Espace disque insuffisant"
		Case -1978335174, 0x8A15003A
			Return "Connexion réseau requise"
		Case -1978335173, 0x8A15003B
			Return "Certificat non valide"
		Case -1978335172, 0x8A15003C
			Return "Source corrompue"
		Case -1978335171, 0x8A15003D
			Return "Données corrompues"
		Case -1978335170, 0x8A15003E
			Return "Opération annulée par l'utilisateur"
		Case -1978335169, 0x8A15003F
			Return "Limite de téléchargement atteinte"
		Case Else
			Return "Erreur inconnue (Code: " & $errorCode & ")"
	EndSwitch
EndFunc

Func _ftp_install($id)
	$name = IniRead($ini_log,$id,"nom","")
	$fichier = IniRead($ini_log,$id,"fichier_nom","")
	$fichier_path = IniRead($ini_log,$id,"fichier_path","")
	$est_zip = IniRead($ini_log,$id,"est_zip","")
	$commande_lancement = IniRead($ini_log,$id,"commande_lancement","")
	Local $bSuccess = True ; Variable pour suivre le succès de l'opération

	; Vérification que les informations nécessaires existent
	if $fichier = "" Or $fichier_path = "" Then
		_UpdateProgressText("Erreur : Informations fichier manquantes pour " & $name)
		MsgBox(0, "Erreur", "Informations fichier manquantes pour " & $name)
		_Log("✗ Erreur : Informations manquantes pour " & $name, "Logiciels", "Installation")
		; Décocher et colorer le label en rouge (erreur)
		GUICtrlSetState($Checkbox_log_[$id], $GUI_UNCHECKED)
		GUICtrlSetColor($label_log_[$id], 0xFF0000) ; Rouge
		GUICtrlSetData($label_log_[$id], "Erreur")
		Return
	EndIf

	; _______téléchargement
	_UpdateProgressText("Téléchargement en cours : " & $fichier)
	GUICtrlSetData($hProgressBar, 0)

	Local $sFilePath = _WinAPI_GetTempFileName(@TempDir)

	;création URL - utilisation de la configuration disponible
	$url_base = IniRead("ini\cfg.ini", "config", "url_base", "")
	if $url_base = "" Then
		; Fallback vers l'ancienne méthode si url_base n'existe pas
		$proto = IniRead("ini\cfg.ini", "dl", "protocole", "")
		$ip = IniRead("ini\cfg.ini", "dl", "ip", "")
		$chemin = IniRead("ini\cfg.ini", "dl", "chemin", "")
		$url = $proto & "://" & $ip & $chemin & $fichier_path
	Else
		; Utilisation de url_base
		if StringRight($url_base, 1) <> "/" Then $url_base = $url_base & "/"
		$url = $url_base & $fichier_path
	EndIf

	_UpdateProgressText("Téléchargement en cours : " & $fichier & @CRLF & "URL: " & $url)
	
	; Vérification que l'URL est valide
	if $url = "" Or $url = "/" Then
		_UpdateProgressText("Erreur : URL de téléchargement invalide")
		_Log("✗ Erreur : URL invalide pour " & $name, "Logiciels", "Téléchargement")
		GUICtrlSetState($Checkbox_log_[$id], $GUI_UNCHECKED)
		GUICtrlSetColor($label_log_[$id], 0xFF0000) ; Rouge
		GUICtrlSetData($label_log_[$id], "Erreur")
		Return
	EndIf

	Local $hDownload = InetGet($url, $sFilePath, $INET_FORCERELOAD, $INET_DOWNLOADBACKGROUND)

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
			GUICtrlSetData($hProgressBar, $iProgress)
			_UpdateProgressText("Téléchargement : " & $fichier & " (" & Round($iProgress, 1) & "%)")
		EndIf
		
		; Timeout après 60 secondes
		if $iTimeout > 60000 Then
			_UpdateProgressText("Timeout de téléchargement : " & $fichier)
			InetClose($hDownload)
			FileDelete($sFilePath)
			_Log("✗ Timeout téléchargement : " & $name, "Logiciels", "Téléchargement")
			GUICtrlSetState($Checkbox_log_[$id], $GUI_UNCHECKED)
			GUICtrlSetColor($label_log_[$id], 0xFF0000) ; Rouge
			GUICtrlSetData($label_log_[$id], "Erreur")
			Return
		EndIf
	Until $iComplete

	; Vérification si le téléchargement a réussi
	Local $iError = InetGetInfo($hDownload, $INET_DOWNLOADERROR)
	Local $iBytesRead = InetGetInfo($hDownload, $INET_DOWNLOADREAD)
	InetClose($hDownload)

	; Vérification détaillée des erreurs
	if $iError <> 0 Then
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
			Case Else
				$sErrorMsg = "Erreur téléchargement : Code " & $iError
		EndSwitch
		
		_UpdateProgressText($sErrorMsg & " : " & $fichier)
		_Log("✗ " & $sErrorMsg & " : " & $name, "Logiciels", "Téléchargement")
		FileDelete($sFilePath)
		$bSuccess = False
	ElseIf $iBytesRead = 0 Then
		_UpdateProgressText("Erreur : Fichier vide téléchargé : " & $fichier)
		_Log("✗ Fichier vide : " & $name, "Logiciels", "Téléchargement")
		FileDelete($sFilePath)
		$bSuccess = False
	Else
		_UpdateProgressText("Téléchargement terminé : " & $fichier)
		GUICtrlSetData($hProgressBar, 100)

		; Création du dossier de destination si nécessaire
		DirCreate(@ScriptDir & "\Download\logiciels\")
		FileMove($sFilePath, @ScriptDir & "\Download\logiciels\" & $fichier, 9)

		; Traitement selon le type de fichier
		if $est_zip = "1" Then
			; _______décompression du fichier zip
			_UpdateProgressText("Décompression en cours : " & $fichier)
			GUICtrlSetData($hProgressBar, 0)

			$ZipFile = @ScriptDir & "\Download\logiciels\" & $fichier
			$DestPath = @ScriptDir & "\Download\logiciels\"
			
			; Vérification que le fichier zip existe
			if Not FileExists($ZipFile) Then
				_UpdateProgressText("Erreur : Fichier zip non trouvé : " & $ZipFile)
				_Log("✗ Fichier zip non trouvé : " & $name, "Logiciels", "Décompression")
				$bSuccess = False
			Else
				; Tentative de décompression avec _Zip_UnzipAll
				Local $iZipResult = _Zip_UnzipAll($ZipFile, $DestPath, 0)
				
				if @error <> 0 Or $iZipResult = 0 Then
					; Si _Zip_UnzipAll échoue, utiliser une méthode alternative avec PowerShell
					_UpdateProgressText("Méthode alternative de décompression : " & $fichier)
					_Log("ℹ Utilisation de PowerShell pour décompresser : " & $name, "Logiciels", "Décompression")
					
					Local $sPSCmd = 'powershell.exe -Command "Expand-Archive -Path ''' & $ZipFile & ''' -DestinationPath ''' & $DestPath & ''' -Force"'
					Local $iPSResult = RunWait($sPSCmd, @ScriptDir, @SW_HIDE)
					
					if $iPSResult <> 0 Then
						_UpdateProgressText("Erreur de décompression : " & $fichier)
						_Log("✗ Erreur décompression : " & $name & " (Code: " & $iPSResult & ")", "Logiciels", "Décompression")
						$bSuccess = False
					Else
						_UpdateProgressText("Décompression réussie : " & $fichier)
						_Log("✓ Décompression réussie : " & $name, "Logiciels", "Décompression")
					EndIf
				Else
					_UpdateProgressText("Décompression réussie : " & $fichier)
					_Log("✓ Décompression réussie : " & $name, "Logiciels", "Décompression")
				EndIf
				
				GUICtrlSetData($hProgressBar, 100)
				
				; Suppression du zip après décompression réussie
				if $bSuccess Then
					FileDelete(@ScriptDir & "\Download\logiciels\" & $fichier)
				EndIf
			EndIf
			
			; Lancement selon la commande définie (seulement si décompression réussie)
			if $bSuccess And $commande_lancement <> "" Then
				$log = StringSplit($fichier, ".")
				$log_2 = @ScriptDir & "\Download\logiciels\" & $log[1]
				
				; Vérification que le dossier décompressé existe
				if Not FileExists($log_2) Then
					_Log("✗ Dossier décompressé non trouvé : " & $log_2, "Logiciels", "Installation")
					$bSuccess = False
				Else
					FileChangeDir($log_2)
					
					; Lancement direct de la commande spécifiée dans le fichier INI
					_UpdateProgressText("Lancement de : " & $commande_lancement)
					
					if FileExists($commande_lancement) Then
						Run($commande_lancement, @WorkingDir)
						_Log("✓ Installation lancée : " & $commande_lancement & " pour " & $name, "Logiciels", "Installation")
					Else
						_Log("✗ Fichier non trouvé : " & $commande_lancement & " dans " & $log_2, "Logiciels", "Installation")
						$bSuccess = False
					EndIf
					
					FileChangeDir(@ScriptDir)
				EndIf
			ElseIf $bSuccess Then
				_Log("✓ Fichier décompressé avec succès : " & $name, "Logiciels", "Décompression")
			EndIf
		Else
			; Fichier direct (non zip)
			if $commande_lancement <> "" Then
				; Changement vers le dossier de téléchargement
				FileChangeDir(@ScriptDir & "\Download\logiciels\")
				
				_UpdateProgressText("Lancement de : " & $commande_lancement)
				
				if FileExists($commande_lancement) Then
					Run($commande_lancement, @WorkingDir)
					_Log("Installation lancée : " & $commande_lancement & " pour " & $name, "Logiciels", "Installation")
				Else
					_Log("Fichier non trouvé : " & $commande_lancement & " dans Download\logiciels\", "Logiciels", "Installation")
					$bSuccess = False
				EndIf
				
				FileChangeDir(@ScriptDir)
			Else
				_Log("Fichier téléchargé avec succès : " & $name, "Logiciels", "Téléchargement")
			EndIf
		EndIf
	EndIf

	; Décocher et colorer le label selon le résultat
	GUICtrlSetState($Checkbox_log_[$id], $GUI_UNCHECKED)
	if $bSuccess Then
		GUICtrlSetColor($label_log_[$id], 0x008000) ; Vert (installé)
		GUICtrlSetData($label_log_[$id], "Installé")
	Else
		GUICtrlSetColor($label_log_[$id], 0xFF0000) ; Rouge (erreur)
		GUICtrlSetData($label_log_[$id], "Erreur")
	EndIf
EndFunc

Func _UpdateProgressText($text)
	GUICtrlSetData($group_progress, "Progression : " & $text)
EndFunc

Func _ListeProgrammes()
    Local $act_key, $act_name, $sIcon, $iIDIcon = 0,$sUninstallString, $sQuietUninstallString, $sInstallDate, $system_component, $aVirg, $iWindowsInstaller
	Local $count, $tab = 1, $all_keys[0][9]

	Local $keys[2]
	$keys[0] = "HKLM\SOFTWARE\Microsoft\Windows\CurrentVersion\Uninstall"
	$keys[1] = "HKCU\Software\Microsoft\Windows\CurrentVersion\Uninstall"

	If(@OSArch = "X64") Then
		ReDim $keys[4]
		$keys[2] = "HKLM\Software\Wow6432Node\Microsoft\Windows\CurrentVersion\Uninstall"
		$keys[3] = "HKLM64\SOFTWARE\Microsoft\Windows\CurrentVersion\Uninstall"
	EndIf

	For $key in $keys
		$count = 1
		While 1
			$act_key = RegEnumKey ($key, $count)
			;MsgBox(0,"",$key & "\" & $act_key)

			If @error <> 0 then ExitLoop
			$act_name = RegRead ($key & "\" & $act_key, "DisplayName")
			$system_component = RegRead ($key & "\" & $act_key, "SystemComponent")
			$act_name = StringReplace ($act_name, " (remove only)", "")
			$sIcon = RegRead ($key & "\" & $act_key, "DisplayIcon")
			$iIDIcon = 0

			if($sIcon = "") Then
				$sIcon = "shell32.dll"
			EndIf

			$sUninstallString = RegRead ($key & "\" & $act_key, "UninstallString")
			$sQuietUninstallString = RegRead ($key & "\" & $act_key, "QuietUninstallString")
			$sInstallDate = RegRead ($key & "\" & $act_key, "InstallDate")
			$iWindowsInstaller = RegRead ($key & "\" & $act_key, "WindowsInstaller")
			;MsgBox(0,"",$act_name)

			If $act_name <> "" And $system_component <> "1" And _ArraySearch($all_keys, $act_name,0,0,0,0,0,0) = -1 Then
				ReDim $all_keys[$tab][9]

				If($sIcon = "shell32.dll") Then
					Local $hSearch = FileFindFirstFile(@WindowsDir & "\Installer\" & $act_key & "\" & "*.ico")

					 If $hSearch <> -1 Then
						$sIcon = @WindowsDir & "\Installer\" & $act_key & "\" & FileFindNextFile($hSearch)
					 Else
						$hSearch = FileFindFirstFile(@WindowsDir & "\Installer\" & $act_key & "\" & "*.exe")
						 If $hSearch <> -1 Then
							$sIcon = @WindowsDir & "\Installer\" & $act_key & "\" & FileFindNextFile($hSearch)
						Else
							$iIDIcon = 23
						EndIf
					 EndIf
					 FileClose($hSearch)
				Else
					$aVirg = StringSplit($sIcon, ",")
					if(@error = 0 And $aVirg[0] > 1) Then
						$sIcon = StringReplace($aVirg[1], '"',"")
						$iIDIcon = $aVirg[2]
					EndIf
				EndIf

				$all_keys[$tab-1][0] = $act_name
				$all_keys[$tab-1][1] = $sIcon
				$all_keys[$tab-1][2] = $iIDIcon
				$all_keys[$tab-1][3] = $sUninstallString
				$all_keys[$tab-1][4] = $sQuietUninstallString
				$all_keys[$tab-1][5] = $sInstallDate
				$all_keys[$tab-1][6] = $iWindowsInstaller
				$all_keys[$tab-1][7] = $act_key
				$all_keys[$tab-1][8] = $key & "\" & $act_key

				$tab = $tab + 1
			EndIf
			$count = $count + 1
		WEnd

	Next
	_ArraySort($all_keys,0,0,0,0)
	Return $all_keys
EndFunc

Func _uninstalllog()
    Local $aListeProgInst = _ListeProgrammes()
    Local $iNBItems = UBound($aListeProgInst)
    Local $sEchecs = ""
    Local $iNbDesinstalle = 0

    ; Compter le nombre de programmes sélectionnés
    Local $hItem = _GUICtrlTreeView_GetFirstItem($idTreeView)
    While $hItem <> 0
        If _GUICtrlTreeView_GetChecked($idTreeView, $hItem) Then
            $iNbDesinstalle += 1
        EndIf
        $hItem = _GUICtrlTreeView_GetNextSibling($idTreeView, $hItem)
    WEnd

    If $iNbDesinstalle = 0 Then
        MsgBox(0, "Information", "Aucun programme sélectionné pour la désinstallation.")
        Return
    EndIf

    ; Confirmation avant désinstallation
    Local $iReponse = MsgBox(4, "Confirmation", "Êtes-vous sûr de vouloir désinstaller " & $iNbDesinstalle & " programme(s) sélectionné(s) ?")
    If $iReponse <> 6 Then Return ; 6 = Oui

    Local $iCompteur = 0
    Local $hItem = _GUICtrlTreeView_GetFirstItem($idTreeView)
    Local $iIndex = 0
    
    While $hItem <> 0
        If _GUICtrlTreeView_GetChecked($idTreeView, $hItem) Then
            $iCompteur += 1
            _UpdateProgressText("Désinstallation " & $iCompteur & "/" & $iNbDesinstalle & " : " & $aListeProgInst[$iIndex][0])
            GUICtrlSetData($hProgressBar, ($iCompteur * 100) / $iNbDesinstalle)

            Local $sUninstallString = ""

            ; Utiliser QuietUninstallString en priorité s'il existe
            If $aListeProgInst[$iIndex][4] <> "" Then
                $sUninstallString = $aListeProgInst[$iIndex][4]
                _UpdateProgressText("Désinstallation silencieuse : " & $aListeProgInst[$iIndex][0])
            Else
                $sUninstallString = $aListeProgInst[$iIndex][3]
                
                ; Adaptation de la commande selon le type d'installeur
                If $aListeProgInst[$iIndex][6] = "1" Then
                    ; Windows Installer (MSI)
                    $sUninstallString = "MsiExec.exe /X" & $aListeProgInst[$iIndex][7] & " /passive /norestart"
                    _UpdateProgressText("Désinstallation MSI : " & $aListeProgInst[$iIndex][0])
                ElseIf StringLeft($sUninstallString, 7) = "MsiExec" Then
                    ; Commande MsiExec existante
                    $sUninstallString = StringReplace($sUninstallString, "/i", "/x")
                    If StringLeft($sUninstallString, 1) = '"' Then
                        $sUninstallString = $sUninstallString & ' /passive /norestart'
                    Else
                        $sUninstallString = '"' & $sUninstallString & '" /passive /norestart'
                    EndIf
                    _UpdateProgressText("Désinstallation MsiExec : " & $aListeProgInst[$iIndex][0])
                ElseIf StringRight($sUninstallString, 10) = "/uninstall" Or StringRegExp($sUninstallString, "unins00[0-9]{1}.exe") Then
                    ; Désinstalleur Inno Setup ou similaire
                    If StringLeft($sUninstallString, 1) = '"' Then
                        $sUninstallString = $sUninstallString & ' /silent'
                    Else
                        If StringRight($sUninstallString, 10) = "/uninstall" Then
                            $sUninstallString = '"' & StringReplace($sUninstallString, " /uninstall", '" /uninstall /silent')
                        Else
                            $sUninstallString = '"' & $sUninstallString & '" /silent'
                        EndIf
                    EndIf
                    _UpdateProgressText("Désinstallation Inno Setup : " & $aListeProgInst[$iIndex][0])
                ElseIf StringRight($sUninstallString, 4) = ".exe" Or StringRight($sUninstallString, 5) = '.exe"' Then
                    ; Exécutable standard
                    If StringLeft($sUninstallString, 1) = '"' Then
                        $sUninstallString = $sUninstallString & ' /S'
                    Else
                        $sUninstallString = '"' & $sUninstallString & '" /S'
                    EndIf
                    _UpdateProgressText("Désinstallation standard : " & $aListeProgInst[$iIndex][0])
                ElseIf StringLeft($sUninstallString, 1) <> '"' Then
                    ; Correction des guillemets manquants
                    Local $iPosExe = StringInStr($sUninstallString, ".exe")
                    If $iPosExe <> 0 Then
                        $sUninstallString = '"' & StringLeft($sUninstallString, $iPosExe + 3) & '" ' & StringMid($sUninstallString, $iPosExe + 4)
                    Else
                        $iPosExe = StringInStr($sUninstallString, "/")
                        If $iPosExe <> 0 Then
                            $sUninstallString = '"' & StringLeft($sUninstallString, $iPosExe - 2) & '" ' & StringMid($sUninstallString, $iPosExe)
                        EndIf
                    EndIf
                    _UpdateProgressText("Désinstallation (corrigée) : " & $aListeProgInst[$iIndex][0])
                Else
                    _UpdateProgressText("Désinstallation : " & $aListeProgInst[$iIndex][0])
                EndIf
            EndIf

            ; Exécution de la commande de désinstallation
            Local $ipid = RunWait(@ComSpec & ' /c ' & $sUninstallString, "", @SW_HIDE)
            
            ; Vérifier le code de retour
            If $ipid = 0 Then
                _UpdateProgressText("✓ Désinstallation réussie : " & $aListeProgInst[$iIndex][0])
                _Log("Désinstallation réussie : " & $aListeProgInst[$iIndex][0], "Logiciels", "Désinstallation")
                ; Décocher le programme désinstallé avec succès
                _GUICtrlTreeView_SetChecked($idTreeView, $hItem, False)
            Else
                $sEchecs &= $aListeProgInst[$iIndex][0] & " (Code erreur: " & $ipid & ")" & @LF
                _UpdateProgressText("✗ Erreur désinstallation : " & $aListeProgInst[$iIndex][0])
                _Log("Erreur désinstallation : " & $aListeProgInst[$iIndex][0] & " (Code: " & $ipid & ")", "Logiciels", "Désinstallation")
            EndIf

            Sleep(500)
        EndIf
        
        ; Passer à l'élément suivant
        $hItem = _GUICtrlTreeView_GetNextSibling($idTreeView, $hItem)
        $iIndex += 1
    WEnd

    GUICtrlSetData($hProgressBar, 100)
    _UpdateProgressText("Réactualisation de la liste des programmes...")

    ; Réactualiser la liste des programmes installés
    _GUICtrlTreeView_DeleteAll($idTreeView)
    _GUICtrlTreeView_BeginUpdate($idTreeView)
    
    Local $aListeProgInstNew = _ListeProgrammes()
    For $i = 0 To UBound($aListeProgInstNew) - 1
        $hItem = GUICtrlCreateTreeViewItem($aListeProgInstNew[$i][0], $idTreeView)
        _GUICtrlTreeView_SetIcon($idTreeView, $hItem, $aListeProgInstNew[$i][1], $aListeProgInstNew[$i][2])
    Next
    
    _GUICtrlTreeView_EndUpdate($idTreeView)
    
    _UpdateProgressText("Liste des programmes mise à jour")

    If $sEchecs <> "" Then
        MsgBox(0, "Attention", "Certains programmes n'ont pas pu être désinstallés :" & @LF & @LF & $sEchecs & @LF & "Vous pouvez réessayer ou les désinstaller manuellement.")
    Else
        MsgBox(0, "Succès", "Tous les programmes sélectionnés ont été désinstallés avec succès !")
    EndIf
EndFunc

; Fonction pour rafraîchir la TreeView des programmes installés
Func _refreshTreeView()
    _UpdateProgressText("Actualisation de la liste des programmes...")
    GUICtrlSetData($hProgressBar, 0)
    
    ; Vider la TreeView
    _GUICtrlTreeView_DeleteAll($idTreeView)
    _GUICtrlTreeView_BeginUpdate($idTreeView)
    
    ; Recharger la liste des programmes installés
    Local $aListeProgInstNew = _ListeProgrammes()
    For $i = 0 To UBound($aListeProgInstNew) - 1
        $hItem = GUICtrlCreateTreeViewItem($aListeProgInstNew[$i][0], $idTreeView)
        _GUICtrlTreeView_SetIcon($idTreeView, $hItem, $aListeProgInstNew[$i][1], $aListeProgInstNew[$i][2])
        GUICtrlSetData($hProgressBar, ($i * 100) / UBound($aListeProgInstNew))
    Next
    
    _GUICtrlTreeView_EndUpdate($idTreeView)
    GUICtrlSetData($hProgressBar, 100)
    _UpdateProgressText("Liste des programmes actualisée (" & UBound($aListeProgInstNew) & " programmes)")
EndFunc





