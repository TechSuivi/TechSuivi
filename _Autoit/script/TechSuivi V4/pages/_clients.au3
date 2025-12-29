#cs ----------------------------------------------------------------------------

 AutoIt Version: 3.3.16.1
 Author:         myName

 Script Function:
	Template AutoIt script.

#ce ----------------------------------------------------------------------------

; Script Start - Add your code below here

#include <GuiEdit.au3>
#include <ScrollBarConstants.au3>

;~         GUICtrlCreateTabItem("Client / Info Sys")

;variable :

; Variables pour la tâche planifiée de démarrage automatique
Global $g_sTaskName = "TechSuivi_AutoStart"
Global $g_sTargetPath = @ScriptFullPath

Global $ini_infoclient
Global $aResult
Global $33
Global $Button_autologon = -1
Global $fw_active = 12
Global $edit_cmd



Global $id_inter_sql
Global $id_client

Global $inter_date
Global $inter_encours
Global $inter_info
Global $inter_nettoyage



Global $nom
Global $adresse
Global $adresse2
Global $ville
Global $codePostal
Global $pays
Global $telephone
Global $portable
Global $email
Global $idListview_inter

Global $label_id_inter, $label_date_inter, $label_niveau_inter, $label_details_inter, $edit_inter_info,$note_info,$edit_note_info


Func _clients()
	; Charger les données de l'intervention sélectionnée
	_LoadInterventionData()
	
	$coord1 = 20
	$coord2 = 40

	$id_inter = IniRead(@ScriptDir & "\ini\cfg.ini", "config", "id_inter", "")

	; Créer les contrôles pour les informations du client
 	Global $group_client_info = GUICtrlCreateGroup("Info Client :", $coord1, $coord2, 410, 170)
	Global $label_nom = GUICtrlCreateLabel("Nom: " & $nom, $coord1 + 10, $coord2 + 20, 390)
	Global $label_adresse = GUICtrlCreateLabel("Adresse: " & $adresse, $coord1 + 10, $coord2 + 40, 390)
	Global $label_adresse2 = GUICtrlCreateLabel("Adresse 2: ", $coord1 + 10, $coord2 + 60, 390)
	Global $label_ville = GUICtrlCreateLabel("Ville: " & $ville, $coord1 + 10, $coord2 + 80, 390)
	Global $label_codePostal = GUICtrlCreateLabel("Code Postal: " & $codePostal, $coord1 + 200, $coord2 + 80, 190)
	Global $label_pays = GUICtrlCreateLabel("Pays: " & $pays, $coord1 + 10, $coord2 + 100, 390)
	Global $label_telephone = GUICtrlCreateLabel("Téléphone: " & $telephone, $coord1 + 10, $coord2 + 120, 390)
	Global $label_portable = GUICtrlCreateLabel("Portable: " & $portable, $coord1 + 200, $coord2 + 120, 190)
	Global $label_email = GUICtrlCreateLabel("Email: " & $email, $coord1 + 10, $coord2 + 140, 390)

	$coord1 = 20
	$coord2 = 200

	GUICtrlCreateGroup("Info intervention :", $coord1, $coord2, 410, 200)
	$label_id_inter = GUICtrlCreateLabel("ID Intervention : " & $id_inter_sql, $coord1 + 10, $coord2 + 20, 390)
	$label_date_inter = GUICtrlCreateLabel("Date Intervention : " & $inter_date, $coord1 + 10, $coord2 + 40, 390)

	$coord1 = 180
	$coord2 = 140

	$label_details_inter = GUICtrlCreateLabel("Details Intervention : ", $coord1 + 10, $coord2 + 80, 390)
	$edit_inter_info = GUICtrlCreateEdit($inter_info, $coord1 + 10, $coord2 + 100, 230, 150, $ES_AUTOVSCROLL + $WS_VSCROLL + $ES_READONLY + $ES_OEMCONVERT)

	$coord1 = 20
	$coord2 = 410

	$edit_note_info = GUICtrlCreateEdit($note_info, $coord1, $coord2 , 410, 120)






	; Affichage des informations système
	$coord1 = 460
	$coord2 = 40



	Global $infosys = GUICtrlCreateGroup("Début intervention :", $coord1, $coord2, 320, 490)

	$coord1 = 470
	$coord2 = 60
	
	; Boutons VNC
	Global $btn_install_vnc = GUICtrlCreateButton("Installation VNC", $coord1, $coord2, 120, 30)
	GUICtrlSetOnEvent($btn_install_vnc, "_InstallVNC")
	
	Global $btn_uninstall_vnc = GUICtrlCreateButton("Désinstaller VNC", $coord1 + 130, $coord2, 120, 30)
	GUICtrlSetOnEvent($btn_uninstall_vnc, "_UninstallVNC")
	
	; Voyant d'état du service TightVNC
	$coord2 += 35
	Global $label_vnc_status = GUICtrlCreateLabel("Service VNC: Vérification...", $coord1, $coord2, 250, 20)
	GUICtrlSetFont($label_vnc_status, 9, 600) ; Police en gras
	
	; Vérifier l'état initial du service
	_UpdateVNCStatus()
	
	$coord2 += 35 ; Décaler pour le bouton suivant
	
	; Bouton pour désactiver la mise en veille et l'écran de veille
	Global $btn_disable_sleep = GUICtrlCreateButton("Désactiver Veille/Écran", $coord1, $coord2, 180, 30)
	GUICtrlSetOnEvent($btn_disable_sleep, "_DisableSleepAndScreensaver")
	
	$coord2 += 35 ; Décaler pour le bouton suivant
	
	; Bouton pour gérer le démarrage automatique
	Global $btn_startup_toggle = GUICtrlCreateButton("", $coord1, $coord2, 180, 30)
	GUICtrlSetOnEvent($btn_startup_toggle, "_ToggleStartupTask")
	
	; Label de statut du démarrage automatique
	$coord2 += 35
	Global $label_startup_status = GUICtrlCreateLabel("Démarrage Auto: Vérification...", $coord1, $coord2, 250, 20)
	GUICtrlSetFont($label_startup_status, 9, 600) ; Police en gras
	
	; Mettre à jour le texte du bouton et du statut selon l'état actuel (avec délai)
	AdlibRegister("_DelayedStartupUpdate", 500)
	
	$coord2 += 40
    
    ; Bouton de Nettoyage de fin d'intervention
    Global $btn_cleanup = GUICtrlCreateButton("Nettoyage Fin INTER", $coord1, $coord2, 180, 30)
    GUICtrlSetOnEvent($btn_cleanup, "_CleanupProcess")
    GUICtrlSetColor($btn_cleanup, 0xFF0000) ; Rouge pour attirer l'attention
    
    $coord2 += 40
    
    ; Bouton de Mise à jour
    Global $btn_update = GUICtrlCreateButton("Mise à jour TechSuivi", $coord1, $coord2, 180, 30)
    GUICtrlSetOnEvent($btn_update, "_UpdateProcess")
    GUICtrlSetColor($btn_update, 0x0000FF) ; Bleu
	
	$coord2 += 25 ; Décaler les autres éléments vers le bas
EndFunc   ;==>_clients

; Fonction pour charger les données de l'intervention sélectionnée
Func _LoadInterventionData()
	Local $intervention_file = @ScriptDir & "\intervention_selectionnee.ini"
	
	; Vérifier si le fichier existe
	If FileExists($intervention_file) Then
		; Charger les informations client
		$nom = IniRead($intervention_file, "INTERVENTION", "client_nom", "Non défini")
		$adresse = IniRead($intervention_file, "INTERVENTION", "client_adresse", "Non définie")
		$adresse2 = "" ; Pas dans l'API actuelle
		$ville = IniRead($intervention_file, "INTERVENTION", "client_ville", "Non définie")
		$codePostal = "" ; Pas dans l'API actuelle
		$pays = "" ; Pas dans l'API actuelle
		$telephone = IniRead($intervention_file, "INTERVENTION", "client_telephone", "Non défini")
		$portable = "" ; Pas dans l'API actuelle
		$email = "" ; Pas dans l'API actuelle
		
		; Charger les informations intervention
		$id_inter_sql = IniRead($intervention_file, "INTERVENTION", "id", "")
		$inter_date = IniRead($intervention_file, "INTERVENTION", "date", "Non définie")
		$inter_info = IniRead($intervention_file, "INTERVENTION", "description", "Aucune description")
		$note_info = IniRead($intervention_file, "INTERVENTION", "notes", "Aucune note")
		
		; Convertir les séquences d'échappement en vrais retours à la ligne
		$inter_info = StringReplace($inter_info, "\r\n", @CRLF)
		$inter_info = StringReplace($inter_info, "\n", @CRLF)
		$inter_info = StringReplace($inter_info, "\r", @CRLF)
		
		$note_info = StringReplace($note_info, "\r\n", @CRLF)
		$note_info = StringReplace($note_info, "\n", @CRLF)
		$note_info = StringReplace($note_info, "\r", @CRLF)
		
		; Mettre à jour le fichier cfg.ini avec l'ID de l'intervention
		If $id_inter_sql <> "" Then
			IniWrite(@ScriptDir & "\ini\cfg.ini", "config", "id_inter", $id_inter_sql)
		EndIf
		
		; Afficher un message de confirmation seulement si le log est vide (première utilisation ou réinstallation)
		If $id_inter_sql <> "" And IsDeclared("edit_cmd") And $edit_cmd <> "" Then
			Local $currentLogContent = GUICtrlRead($edit_cmd)
			; Si le log est vide ou ne contient que des espaces/retours à la ligne
			If StringStripWS($currentLogContent, 8) = "" Then
				_Log("Intervention chargée : ID " & $id_inter_sql & " - Client : " & $nom & " (" & $ville & ")", "Client")
			EndIf
		EndIf
	Else
		; Valeurs par défaut si aucune intervention n'est sélectionnée
		$nom = "Aucune intervention sélectionnée"
		$adresse = ""
		$adresse2 = ""
		$ville = ""
		$codePostal = ""
		$pays = ""
		$telephone = ""
		$portable = ""
		$email = ""
		
		$id_inter_sql = ""
		$inter_date = ""
		$inter_info = "Veuillez sélectionner une intervention"
		$note_info = ""
		
		_Log("Aucune intervention sélectionnée. Veuillez redémarrer le programme.", "Client")
	EndIf
EndFunc



; ===============================================
; FONCTIONS VNC
; ===============================================

; Fonction pour installer TightVNC
Func _InstallVNC()
	_Log("Installation TightVNC...", "Client", "VNC")
	
	; URL du fichier TightVNC sur le serveur
	Local $baseURL = IniRead(@ScriptDir & "\ini\cfg.ini", "config", "url_base", "")
	If $baseURL = "" Then
		_Log("ERREUR: URL de base non configurée", "Client", "VNC")
		Return False
	EndIf
	
	; S'assurer que l'URL se termine par /
	If Not StringRight($baseURL, 1) = "/" Then $baseURL &= "/"
	
	Local $vncURL = $baseURL & "uploads/autoit/logiciels/tightvnc.msi"
	
	; Créer un dossier temp dans le répertoire du script
	Local $scriptTempDir = @ScriptDir & "\temp"
	If Not FileExists($scriptTempDir) Then DirCreate($scriptTempDir)
	
	Local $localFile = $scriptTempDir & "\tightvnc.msi"
	
	; Supprimer l'ancien fichier s'il existe pour forcer le re-téléchargement
	If FileExists($localFile) Then FileDelete($localFile)
	
	; Créer un fichier temporaire avec _WinAPI_GetTempFileName (comme dans _nettoyage.au3)
	Local $sFilePath = _WinAPI_GetTempFileName(@TempDir)
	
	; Téléchargement avec la méthode exacte de _nettoyage.au3
	Local $hDownload = InetGet($vncURL, $sFilePath, $INET_FORCERELOAD, $INET_DOWNLOADBACKGROUND)
	
	; Attendre la fin du téléchargement (méthode simple de _nettoyage.au3)
	Do
		Sleep(250)
	Until InetGetInfo($hDownload, $INET_DOWNLOADCOMPLETE)
	
	; Récupérer les informations de téléchargement
	Local $iBytesSize = InetGetInfo($hDownload, $INET_DOWNLOADREAD)
	Local $iFileSize = FileGetSize($sFilePath)
	Local $iError = InetGetInfo($hDownload, $INET_DOWNLOADERROR)
	
	; Fermer le handle
	InetClose($hDownload)
	
	; Vérifier les erreurs de téléchargement
	If $iError <> 0 Or $iBytesSize = 0 Or $iFileSize = 0 Then
		_Log("ERREUR: Téléchargement échoué", "Client", "VNC")
		FileDelete($sFilePath)
		Return False
	EndIf
	
	; Déplacer le fichier vers le dossier final
	FileMove($sFilePath, $localFile, 9) ; 9 = overwrite
	
	; Vérifier que le fichier a été téléchargé
	If Not FileExists($localFile) Or FileGetSize($localFile) = 0 Then
		_Log("ERREUR: Fichier téléchargé invalide", "Client", "VNC")
		Return False
	EndIf
	
	; Générer un mot de passe aléatoire de 8 caractères
	Local $vncPassword = _GenerateRandomPassword(8)
	
	; Lancer l'installation avec les paramètres TightVNC complets
	Local $installCmd = 'msiexec.exe /i "' & $localFile & '" /quiet /norestart ' & _
		'ADDLOCAL=Server ' & _
		'SERVER_REGISTER_AS_SERVICE=1 ' & _
		'SERVER_ADD_FIREWALL_EXCEPTION=1 ' & _
		'SET_USEVNCAUTHENTICATION=1 VALUE_OF_USEVNCAUTHENTICATION=1 ' & _
		'SET_PASSWORD=1 VALUE_OF_PASSWORD=' & $vncPassword & ' ' & _
		'SET_REMOVEWALLPAPER=1 VALUE_OF_REMOVEWALLPAPER=0'
	
	Local $pid = Run($installCmd, "", @SW_HIDE)
	
	If $pid = 0 Then
		_Log("ERREUR: Impossible de lancer l'installation", "Client", "VNC")
		Return False
	EndIf
	
	; Attendre la fin de l'installation
	ProcessWaitClose($pid)
	Local $exitCode = @extended
	
	If $exitCode = 0 Then
		_Log("TightVNC installé avec succès", "Client", "VNC")
		
		; Sauvegarder les informations VNC via l'API
		Local $computerIP = _GetComputerIP()
		_SaveVNCInfoToAPI($computerIP, $vncPassword)
		_Log("Mot de passe VNC: " & $vncPassword & " (IP: " & $computerIP & ")", "Client", "VNC")
		
		; Mettre à jour le voyant d'état après installation
		_UpdateVNCStatus()
		Return True
	Else
		_Log("ERREUR: Installation échouée", "Client", "VNC")
		Return False
	EndIf
EndFunc

; Fonction pour désinstaller TightVNC
Func _UninstallVNC()
	_Log("Désinstallation TightVNC...", "Client", "VNC")
	
	; Demander confirmation
	Local $confirm = MsgBox(36, "Confirmation", "Êtes-vous sûr de vouloir désinstaller TightVNC ?")
	If $confirm <> 6 Then ; 6 = Oui
		_Log("Désinstallation annulée", "Client", "VNC")
		Return False
	EndIf
	
	; Commande de désinstallation via le registre Windows
	Local $uninstallCmd = 'wmic product where "name like ''%TightVNC%''" call uninstall /nointeractive'
	Local $pid = Run(@ComSpec & ' /c ' & $uninstallCmd, "", @SW_HIDE)
	
	If $pid = 0 Then
		_Log("ERREUR: Impossible de lancer la désinstallation", "Client", "VNC")
		Return False
	EndIf
	
	; Attendre la fin de la désinstallation
	ProcessWaitClose($pid)
	
	_Log("TightVNC désinstallé avec succès", "Client", "VNC")
	
	; Supprimer la clé de registre et les informations API
	_RemoveVNCRegistryKey()
	_RemoveVNCInfoFromAPI()
	
	; Mettre à jour le voyant d'état après désinstallation
	_UpdateVNCStatus()
	Return True
EndFunc

; ===============================================
; FONCTIONS UTILITAIRES VNC
; ===============================================

; Fonction pour générer un mot de passe aléatoire
Func _GenerateRandomPassword($length = 8)
	Local $chars = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789"
	Local $password = ""
	
	; Initialiser le générateur aléatoire avec l'heure actuelle
	SRandom(@MSEC)
	
	For $i = 1 To $length
		Local $randomIndex = Random(1, StringLen($chars), 1)
		$password &= StringMid($chars, $randomIndex, 1)
	Next
	
	Return $password
EndFunc

; Fonction pour récupérer l'IP de l'ordinateur
Func _GetComputerIP()
	; Méthode 1: via ipconfig
	Local $pid = Run(@ComSpec & " /c ipconfig | findstr IPv4", "", @SW_HIDE, $STDERR_CHILD + $STDOUT_CHILD)
	ProcessWaitClose($pid)
	Local $output = StdoutRead($pid)
	
	; Extraire l'IP avec regex
	Local $ipPattern = "(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})"
	Local $ipMatch = StringRegExp($output, $ipPattern, 1)
	
	If IsArray($ipMatch) And UBound($ipMatch) > 0 Then
		; Filtrer les IP locales (éviter 127.0.0.1)
		For $i = 0 To UBound($ipMatch) - 1
			If Not StringInStr($ipMatch[$i], "127.0.0.1") Then
				Return $ipMatch[$i]
			EndIf
		Next
	EndIf
	
	; Méthode 2: fallback avec @IPAddress1
	If @IPAddress1 <> "127.0.0.1" And @IPAddress1 <> "" Then
		Return @IPAddress1
	EndIf
	
	; Méthode 3: fallback générique
	Return "IP_NON_DETECTEE"
EndFunc

; Fonction pour encoder le mot de passe TightVNC (simulation simple)
Func _EncodeTightVNCPassword($clearPassword)
	; Pour une implémentation complète, il faudrait utiliser l'algorithme DES de TightVNC
	; Ici, on utilise une méthode simplifiée pour la démonstration
	
	; Padding du mot de passe à 8 caractères (requis par TightVNC)
	Local $paddedPassword = StringLeft($clearPassword & "        ", 8)
	
	; Conversion simple en binaire (à remplacer par l'encodage DES réel)
	Local $binaryData = Binary($paddedPassword)
	
	; Pour l'instant, utiliser une valeur fixe connue pour "123" ou similaire
	; Dans une implémentation réelle, il faudrait implémenter l'algorithme DES
	If $clearPassword = "123" Then
		Return Binary("0xd3b8d88a7e829acc") ; Valeur connue pour "123"
	Else
		; Génération d'une valeur binaire basée sur le mot de passe
		; (Ce n'est pas l'encodage TightVNC réel, juste pour la démonstration)
		Return $binaryData
	EndIf
EndFunc

; Fonction pour sauvegarder les informations VNC via l'API
Func _SaveVNCInfoToAPI($ip, $password)
	Local $id_inter = IniRead(@ScriptDir & "\ini\cfg.ini", "config", "id_inter", "")
	
	If $id_inter = "" Then
		Return False
	EndIf
	
	; Sauvegarder l'IP VNC et le mot de passe
	API_SauvegarderChamp($id_inter, "ip_vnc", $ip)
	API_SauvegarderChamp($id_inter, "pass_vnc", $password)
	
	Return True
EndFunc

; Fonction pour supprimer les informations VNC de l'API
Func _RemoveVNCInfoFromAPI()
	Local $id_inter = IniRead(@ScriptDir & "\ini\cfg.ini", "config", "id_inter", "")
	
	If $id_inter = "" Then
		Return False
	EndIf
	
	; Supprimer l'IP VNC et le mot de passe (vider les champs)
	API_SauvegarderChamp($id_inter, "ip_vnc", "")
	API_SauvegarderChamp($id_inter, "pass_vnc", "")
	
	Return True
EndFunc

; Fonction pour supprimer les clés de registre TightVNC
Func _RemoveVNCRegistryKey()
    ; Supprimer directement tout le dossier TightVNC du registre
    RegDelete("HKEY_LOCAL_MACHINE\SOFTWARE\TightVNC")
    
    ; Supprimer la clé de démarrage automatique résiduelle
    RegDelete("HKEY_LOCAL_MACHINE\SOFTWARE\Microsoft\Windows\CurrentVersion\Run", "tvncontrol")
    
    Return True
EndFunc

; ===============================================
; FONCTION DE DÉSACTIVATION DE LA VEILLE ET ÉCRAN DE VEILLE
; ===============================================

; Fonction pour désactiver la mise en veille et l'écran de veille
Func _DisableSleepAndScreensaver()
	; Désactiver la mise en veille (alimentation secteur)
	Local $powerCmd1 = 'powercfg /x -standby-timeout-ac 0'
	Local $powerCmd2 = 'powercfg /x -monitor-timeout-ac 0'
	
	; Exécuter les commandes powercfg
	Run(@ComSpec & ' /c ' & $powerCmd1, "", @SW_HIDE)
	Run(@ComSpec & ' /c ' & $powerCmd2, "", @SW_HIDE)
	
	; Supprimer l'écran de veille actuel (seule ligne utile)
	Local $regCmd1 = 'reg add "HKCU\Control Panel\Desktop" /v SCRNSAVE.EXE /t REG_SZ /d "" /f'
	Run(@ComSpec & ' /c ' & $regCmd1, "", @SW_HIDE)
	
	_Log("Veille et écran de veille désactivés", "Client", "Veille")
EndFunc

; ===============================================
; FONCTIONS DE GESTION DU DÉMARRAGE AUTOMATIQUE
; ===============================================

; Fonction pour vérifier si la tâche planifiée existe
Func _StartupTask_Exists()
    Local $iExit = RunWait('schtasks /Query /TN "' & $g_sTaskName & '"', "", @SW_HIDE)
    Return ($iExit = 0)
EndFunc

; Fonction pour créer la tâche planifiée de démarrage automatique
Func _StartupTask_Create()
    ; Créer un fichier batch temporaire pour éviter la fenêtre CMD visible
    Local $sBatchFile = @ScriptDir & "\startup_temp.bat"
    Local $sExePath = @ScriptDir & "\auto.exe"
    
    _Log("Création tâche planifiée: " & $g_sTaskName, "Client", "Démarrage")
    
    ; Créer le fichier batch temporaire
    Local $sBatchContent = '@echo off' & @CRLF & _
                          'cd /d "' & @ScriptDir & '"' & @CRLF & _
                          'start "" "auto.exe"' & @CRLF & _
                          'del "%~f0"'
    
    Local $hFile = FileOpen($sBatchFile, 2)
    If $hFile = -1 Then
        _Log("ERREUR: Impossible de créer le fichier batch temporaire", "Client", "Démarrage")
        Return False
    EndIf
    FileWrite($hFile, $sBatchContent)
    FileClose($hFile)
    
    ; Créer la tâche planifiée avec le fichier batch
    Local $sCmd = 'schtasks /Create /F /TN "' & $g_sTaskName & _
                  '" /SC ONLOGON /RL HIGHEST /TR "' & $sBatchFile & _
                  '" /DELAY 0000:30'
    
    Local $iExit = RunWait($sCmd, "", @SW_HIDE)
    
    If $iExit = 0 Then
        _Log("✓ Démarrage automatique activé", "Client", "Démarrage")
        Return True
    Else
        _Log("ERREUR: Impossible d'activer le démarrage automatique (Code: " & $iExit & ")", "Client", "Démarrage")
        ; Supprimer le fichier batch en cas d'échec
        If FileExists($sBatchFile) Then FileDelete($sBatchFile)
        Return False
    EndIf
EndFunc

; Fonction pour obtenir le nom de chemin court (8.3)
Func _GetShortPathName($sLongPath)
    Local $aResult = DllCall("kernel32.dll", "dword", "GetShortPathNameW", "wstr", $sLongPath, "wstr", "", "dword", 260)
    If @error Or $aResult[0] = 0 Then
        Return $sLongPath ; Retourner le chemin original si échec
    EndIf
    Return $aResult[2]
EndFunc

; Fonction pour supprimer la tâche planifiée de démarrage automatique
Func _StartupTask_Delete()
    Local $sCmd = 'schtasks /Delete /F /TN "' & $g_sTaskName & '"'
    Local $iExit = RunWait($sCmd, "", @SW_HIDE)
    Return ($iExit = 0)
EndFunc

; Fonction pour basculer l'état du démarrage automatique
Func _ToggleStartupTask()
    If _StartupTask_Exists() Then
        ; La tâche existe, la supprimer
        If _StartupTask_Delete() Then
            _Log("Démarrage automatique désactivé", "Client", "Démarrage")
        Else
            _Log("ERREUR: Impossible de désactiver le démarrage automatique", "Client", "Démarrage")
        EndIf
    Else
        ; La tâche n'existe pas, la créer
        If _StartupTask_Create() Then
            _Log("Démarrage automatique activé", "Client", "Démarrage")
        Else
            _Log("ERREUR: Impossible d'activer le démarrage automatique", "Client", "Démarrage")
        EndIf
    EndIf
    
    ; Mettre à jour le texte du bouton
    _UpdateStartupButtonText()
EndFunc

; Fonction de mise à jour différée (appelée une seule fois après 500ms)
Func _DelayedStartupUpdate()
    ; Désactiver le timer pour qu'il ne se répète pas
    AdlibUnRegister("_DelayedStartupUpdate")
    
    ; Si le script a été lancé automatiquement, attendre plus longtemps
    If _IsAutoStarted() Then
        ; Attendre 3 secondes supplémentaires pour que le système soit prêt
        Sleep(3000)
    EndIf
    
    ; Appeler la mise à jour avec vérifications
    _UpdateStartupButtonText()
EndFunc

; Fonction pour détecter si le script a été lancé automatiquement
Func _IsAutoStarted()
    ; Vérifier si le processus parent est "svchost.exe" (Planificateur de tâches)
    ; ou si nous sommes dans les premières minutes après le démarrage du système
    Local $uptime = _GetSystemUptime()
    
    ; Si le système a démarré il y a moins de 5 minutes, considérer comme auto-start
    If $uptime < 300000 Then ; 5 minutes en millisecondes
        Return True
    EndIf
    
    Return False
EndFunc

; Fonction pour obtenir le temps de fonctionnement du système en millisecondes
Func _GetSystemUptime()
    Return DllCall("kernel32.dll", "dword", "GetTickCount")[0]
EndFunc

; Fonction pour mettre à jour le texte du bouton et le statut selon l'état
Func _UpdateStartupButtonText()
    ; Vérifier que les contrôles existent avant de les utiliser
    If Not IsDeclared("btn_startup_toggle") Or $btn_startup_toggle = "" Then Return
    If Not IsDeclared("label_startup_status") Or $label_startup_status = "" Then Return
    
    ; Vérifier que les contrôles sont valides (pas -1)
    If $btn_startup_toggle = -1 Or $label_startup_status = -1 Then Return
    
    Local $taskExists = False
    Local $errorOccurred = False
    
    ; Vérifier l'état de la tâche planifiée avec gestion d'erreur AutoIt
    $taskExists = _StartupTask_Exists()
    If @error Then
        $errorOccurred = True
    EndIf
    
    ; Mettre à jour l'interface selon l'état
    If $errorOccurred Then
        ; En cas d'erreur, afficher un état indéterminé
        GUICtrlSetData($btn_startup_toggle, "Vérifier Démarrage Auto")
        GUICtrlSetData($label_startup_status, "Démarrage Auto: ● ERREUR")
        GUICtrlSetColor($label_startup_status, 0xFF6600) ; Orange
    ElseIf $taskExists Then
        GUICtrlSetData($btn_startup_toggle, "Désactiver Démarrage Auto")
        GUICtrlSetData($label_startup_status, "Démarrage Auto: ● ACTIVÉ")
        GUICtrlSetColor($label_startup_status, 0x008000) ; Vert
    Else
        GUICtrlSetData($btn_startup_toggle, "Activer Démarrage Auto")
        GUICtrlSetData($label_startup_status, "Démarrage Auto: ● DÉSACTIVÉ")
        GUICtrlSetColor($label_startup_status, 0xFF0000) ; Rouge
    EndIf
EndFunc

; ===============================================
; FONCTION DE VÉRIFICATION DE L'ÉTAT DU SERVICE VNC
; ===============================================

; Fonction pour vérifier l'état du service TightVNC et mettre à jour le voyant
Func _UpdateVNCStatus()
	; Commande PowerShell pour vérifier l'état du service tvnserver
	Local $psCmd = 'powershell.exe -Command "try { $service = Get-Service tvnserver -ErrorAction Stop; $service.Status } catch { ''NotFound'' }"'
	
	; Exécuter la commande et capturer la sortie
	Local $pid = Run(@ComSpec & " /c " & $psCmd, "", @SW_HIDE, $STDERR_CHILD + $STDOUT_CHILD)
	ProcessWaitClose($pid)
	Local $output = StdoutRead($pid)
	
	; Nettoyer la sortie (supprimer les espaces et retours à la ligne)
	$output = StringStripWS($output, 3)
	
	; Mettre à jour le label selon l'état du service
	If StringInStr($output, "Running") Then
		GUICtrlSetData($label_vnc_status, "Service VNC: ● ACTIF")
		GUICtrlSetColor($label_vnc_status, 0x008000) ; Vert
	ElseIf StringInStr($output, "Stopped") Then
		GUICtrlSetData($label_vnc_status, "Service VNC: ● ARRÊTÉ")
		GUICtrlSetColor($label_vnc_status, 0xFF6600) ; Orange
	ElseIf StringInStr($output, "NotFound") Then
		GUICtrlSetData($label_vnc_status, "Service VNC: ● NON INSTALLÉ")
		GUICtrlSetColor($label_vnc_status, 0xFF0000) ; Rouge
	Else
		GUICtrlSetData($label_vnc_status, "Service VNC: ● INCONNU")
		GUICtrlSetColor($label_vnc_status, 0x808080) ; Gris
	EndIf
EndFunc

; ===============================================
; FONCTIONS DE NETTOYAGE FIN D'INTERVENTION
; ===============================================

Func _CleanupProcess()
    Local $aCleanupOptions = _CleanupPopup()
    If Not IsArray($aCleanupOptions) Then Return ; Annulé

    Local $bUninstallVNC = $aCleanupOptions[0]
    Local $bUninstallMBAM = $aCleanupOptions[1]
    Local $bUninstallTechSuivi = $aCleanupOptions[2]

    _Log("=== DÉBUT DU NETTOYAGE ===", "Client", "Nettoyage")

    ; 1. Désinstallation TightVNC
    If $bUninstallVNC Or $bUninstallTechSuivi Then
        _Log("Désinstallation TightVNC demandée...", "Client", "Nettoyage")
        If _UninstallVNC_Winget() Then
            _Log("✓ TightVNC désinstallé", "Client", "Nettoyage")
            ; Nettoyage traces registre supplémentaires
            _RemoveVNCRegistryKey()
        Else
            _Log("⚠ Échec désinstallation VNC (Tentative WMIC...)", "Client", "Nettoyage")
             ; Fallback sur l'ancienne méthode
            If _UninstallVNC() Then
                _Log("✓ TightVNC désinstallé (WMIC)", "Client", "Nettoyage")
            Else
                _Log("✗ Échec total désinstallation VNC", "Client", "Nettoyage")
            EndIf
        EndIf
    EndIf

    ; 2. Désinstallation Malwarebytes
    If $bUninstallMBAM Or $bUninstallTechSuivi Then
        _Log("Désinstallation Malwarebytes demandée...", "Client", "Nettoyage")
        If _UninstallMBAM_Winget() Then
            _Log("✓ Malwarebytes désinstallé", "Client", "Nettoyage")
        Else
             _Log("✗ Échec désinstallation Malwarebytes", "Client", "Nettoyage")
        EndIf
    EndIf

    ; 3. Désactivation Démarrage Auto (Toujours si désinstallation TechSuivi, ou si existante)
    If _StartupTask_Exists() Or $bUninstallTechSuivi Then
        _Log("Suppression du démarrage automatique...", "Client", "Nettoyage")
        If _StartupTask_Delete() Then
            _Log("✓ Démarrage auto désactivé", "Client", "Nettoyage")
        Else
            _Log("✗ Échec désactivation démarrage auto", "Client", "Nettoyage")
        EndIf
        _UpdateStartupButtonText()
    EndIf

    ; 4. Nettoyage API VNC (Toujours)
    _Log("Nettoyage informations VNC dans l'API...", "Client", "Nettoyage")
    If _RemoveVNCInfoFromAPI() Then
        _Log("✓ Infos VNC supprimées de l'API", "Client", "Nettoyage")
    EndIf
    
    ; Mise à jour voyant VNC
    _UpdateVNCStatus()

    _Log("=== NETTOYAGE TERMINÉ ===", "Client", "Nettoyage")
    
    ; 5. Auto-Uninstall TechSuivi si demandé
    If $bUninstallTechSuivi Then
        _Log("Lancement de la désinstallation de TechSuivi...", "Client", "Nettoyage")
        Local $hSearch = FileFindFirstFile(@ScriptDir & "\installeur*.exe")
        If $hSearch <> -1 Then
            Local $sInstallerName = FileFindNextFile($hSearch)
            FileClose($hSearch)
            
            _Log("Installeur trouvé : " & $sInstallerName, "Client", "Nettoyage")
            _Log("Au revoir !", "Client", "Nettoyage")
            Sleep(1000)
            
            ; Lancer l'installeur trouvé avec l'argument -uninstall
            Run(@ScriptDir & "\" & $sInstallerName & " -uninstall")
            Terminate() ; Quitter proprement avec sauvegarde
        Else
            _Log("ERREUR: Installeur introuvable pour la désinstallation", "Client", "Nettoyage")
            MsgBox(16, "Erreur", "Impossible de trouver l'installeur pour la suppression complète.")
        EndIf
    Else
        MsgBox(64, "Nettoyage", "Opérations de nettoyage terminées.")
    EndIf
EndFunc

; Popup de sélection du nettoyage avec timer
Func _CleanupPopup()
    ; Basculer temporairement en mode MessageLoop pour cette fenêtre modale
    Local $iOldMode = Opt("GUIOnEventMode", 0)
    
    Local $hCleanupGUI = GUICreate("Nettoyage Fin d'Intervention", 400, 280, -1, -1, BitOR($WS_CAPTION, $WS_POPUP, $WS_SYSMENU))
    
    GUICtrlCreateGroup("Sélectionnez les éléments à supprimer :", 20, 20, 360, 130)
    Local $chk_vnc = GUICtrlCreateCheckbox("TightVNC (Service + Fichiers)", 40, 50, 300, 20)
    GUICtrlSetState($chk_vnc, $GUI_CHECKED)
    
    Local $chk_mbam = GUICtrlCreateCheckbox("Malwarebytes", 40, 80, 300, 20)
    GUICtrlSetState($chk_mbam, $GUI_CHECKED)

    Local $chk_techsuivi = GUICtrlCreateCheckbox("Désinstaller TechSuivi (Complet)", 40, 110, 300, 20)
    GUICtrlSetState($chk_techsuivi, $GUI_CHECKED)
    
    Local $lbl_timer = GUICtrlCreateLabel("Validation automatique dans 30 secondes...", 40, 170, 320, 20, $SS_CENTER)
    GUICtrlSetColor($lbl_timer, 0x0000FF)
    
    Local $btn_ok = GUICtrlCreateButton("Valider et Nettoyer", 40, 210, 150, 40)
    Local $btn_cancel = GUICtrlCreateButton("Annuler", 210, 210, 150, 40)
    
    GUISetState(@SW_SHOW, $hCleanupGUI)
    
    Local $alert_timer = TimerInit()
    Local $timeout = 30 ; Secondes
    Local $bCancelled = False
    
    While 1
        Local $elapsed = TimerDiff($alert_timer) / 1000
        Local $remaining = Int($timeout - $elapsed)
        
        If $remaining <= 0 Then
            _Log("Validation automatique du nettoyage (Timer)", "Client", "Nettoyage")
            ExitLoop
        EndIf
        
        GUICtrlSetData($lbl_timer, "Validation automatique dans " & $remaining & " secondes...")
        
        Local $msg = GUIGetMsg()
        If $msg = $btn_ok Then
            ExitLoop
        ElseIf $msg = $btn_cancel Or $msg = $GUI_EVENT_CLOSE Then
            $bCancelled = True
            ExitLoop
        EndIf
        
        Sleep(100)
    WEnd
    
    Local $bVNC = (GUICtrlRead($chk_vnc) = $GUI_CHECKED)
    Local $bMBAM = (GUICtrlRead($chk_mbam) = $GUI_CHECKED)
    Local $bTechSuivi = (GUICtrlRead($chk_techsuivi) = $GUI_CHECKED)
    
    GUIDelete($hCleanupGUI)
    Opt("GUIOnEventMode", $iOldMode) ; Restaurer le mode précédent
    
    If $bCancelled Then
        _Log("Nettoyage annulé par l'utilisateur", "Client", "Nettoyage")
        Return 0
    EndIf
    
    Local $result[3]
    $result[0] = $bVNC
    $result[1] = $bMBAM
    $result[2] = $bTechSuivi
    Return $result
EndFunc

; Processus de mise à jour
Func _UpdateProcess()
    Local $confirm = MsgBox(36, "Mise à jour", "Voulez-vous lancer la mise à jour de TechSuivi ?" & @CRLF & "Le programme va redémarrer.")
    If $confirm <> 6 Then Return
    
    _Log("Lancement de la mise à jour...", "Client", "System")
    
    Local $hSearch = FileFindFirstFile(@ScriptDir & "\installeur*.exe")
    If $hSearch <> -1 Then
        Local $sInstallerName = FileFindNextFile($hSearch)
        FileClose($hSearch)
        
        _Log("Installeur trouvé : " & $sInstallerName, "Client", "System")
        Sleep(500)
        
        ; Lancer l'installeur avec l'argument -maj
        Run(@ScriptDir & "\" & $sInstallerName & " -maj")
        Terminate() ; Quitter proprement avec sauvegarde
    Else
        _Log("ERREUR: Installeur introuvable", "Client", "System")
        MsgBox(16, "Erreur", "Impossible de trouver l'installeur pour la mise à jour.")
    EndIf
EndFunc

; Désactivation VNC via Winget
Func _UninstallVNC_Winget()
    Local $cmd = 'winget uninstall --id GlavSoft.TightVNC -h --accept-source winget --accept-package-agreements --force'
    _Log("CMD: " & $cmd, "Client", "Debug")
    Local $pid = Run(@ComSpec & " /c " & $cmd, "", @SW_HIDE)
    ProcessWaitClose($pid)
    Local $exitCode = @extended
    Return ($exitCode = 0)
EndFunc

; Désactivation Malwarebytes via Winget
Func _UninstallMBAM_Winget()
    Local $cmd = 'winget uninstall --id Malwarebytes.Malwarebytes -h --accept-source winget --accept-package-agreements --force'
    _Log("CMD: " & $cmd, "Client", "Debug")
    Local $pid = Run(@ComSpec & " /c " & $cmd, "", @SW_HIDE)
    ProcessWaitClose($pid)
    Local $exitCode = @extended
    Return ($exitCode = 0)
EndFunc






