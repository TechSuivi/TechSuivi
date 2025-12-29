#cs ----------------------------------------------------------------------------

 AutoIt Version: 3.3.16.1
 Author:         TechSuivi

 Script Function:
	Page Sauvegarde & Restauration pour TechSuivi V4
	Adaptée depuis interface_sauvegarde_corrigee.au3

#ce ----------------------------------------------------------------------------

; Variables globales pour la sauvegarde/restauration
Global $sBackupPath = ""
Global $bOperationInProgress = False

; Variables pour les contrôles de l'interface
Global $btnSelectPath, $btnBackup, $btnRestore, $btnListApps, $btnRefreshBackups
Global $btnSelectAllUser, $btnDeselectAllUser
Global $lblDestination, $hListBackups, $progressBar, $lblProgress
Global $checkUserFiles, $checkBrowsers, $checkSystem, $checkApps
Global $checkDesktop, $checkDocuments, $checkPictures, $checkMusic, $checkVideos, $checkDownloads
Global $checkThunderbird, $checkFirefoxPasswords
Global $checkWallpaper, $checkPrinters, $checkSteam, $checkWifi

; Variables pour stocker les chemins des sauvegardes
Global $aBackupPaths[1]
$aBackupPaths[0] = 0

; Fonction principale pour créer l'interface Sauvegarde/Restauration
Func _sauve_restore()
    ; Groupe Destination de sauvegarde
    GUICtrlCreateGroup("Destination de sauvegarde", 30, 50, 740, 80)
    $lblDestination = GUICtrlCreateLabel("Aucune destination sélectionnée", 50, 75, 500, 20)
    GUICtrlSetColor($lblDestination, 0xFF0000)
    $btnSelectPath = GUICtrlCreateButton("Choisir Destination", 580, 70, 150, 30)
    GUICtrlSetOnEvent($btnSelectPath, "_SelectBackupPath")
    GUICtrlCreateGroup("", -99, -99, 1, 1) ; Fermer le groupe
    
    ; Groupe Options de sauvegarde
    GUICtrlCreateGroup("Options de sauvegarde", 30, 150, 740, 200)
    
    ; Sous-groupe Fichiers utilisateur
    GUICtrlCreateLabel("Dossiers utilisateur :", 50, 180, 150, 20)
    GUICtrlSetFont(-1, 9, 600) ; Gras
    $checkDesktop = GUICtrlCreateCheckbox("Bureau", 50, 200, 100, 20)
    GUICtrlSetState($checkDesktop, $GUI_CHECKED)
    $checkDocuments = GUICtrlCreateCheckbox("Documents", 160, 200, 100, 20)
    GUICtrlSetState($checkDocuments, $GUI_CHECKED)
    $checkPictures = GUICtrlCreateCheckbox("Images", 270, 200, 100, 20)
    GUICtrlSetState($checkPictures, $GUI_CHECKED)
    $checkMusic = GUICtrlCreateCheckbox("Musique", 50, 220, 100, 20)
    GUICtrlSetState($checkMusic, $GUI_CHECKED)
    $checkVideos = GUICtrlCreateCheckbox("Vidéos", 160, 220, 100, 20)
    GUICtrlSetState($checkVideos, $GUI_CHECKED)
    $checkDownloads = GUICtrlCreateCheckbox("Téléchargements", 270, 220, 120, 20)
    GUICtrlSetState($checkDownloads, $GUI_CHECKED)
    
    ; Sous-groupe Navigateurs et Email
    GUICtrlCreateLabel("Navigateurs et Email :", 400, 180, 150, 20)
    GUICtrlSetFont(-1, 9, 600) ; Gras
    $checkBrowsers = GUICtrlCreateCheckbox("Favoris des navigateurs", 400, 200, 150, 20)
    GUICtrlSetState($checkBrowsers, $GUI_CHECKED)
    $checkFirefoxPasswords = GUICtrlCreateCheckbox("Mots de passe Firefox", 400, 220, 150, 20)
    GUICtrlSetState($checkFirefoxPasswords, $GUI_CHECKED)
    $checkThunderbird = GUICtrlCreateCheckbox("Thunderbird (emails)", 400, 240, 150, 20)
    GUICtrlSetState($checkThunderbird, $GUI_CHECKED)
    
    ; Sous-groupe Système et Gaming
    GUICtrlCreateLabel("Système et Gaming :", 50, 260, 150, 20)
    GUICtrlSetFont(-1, 9, 600) ; Gras
    $checkSystem = GUICtrlCreateCheckbox("Paramètres système", 50, 280, 150, 20)
    GUICtrlSetState($checkSystem, $GUI_CHECKED)
    $checkApps = GUICtrlCreateCheckbox("Liste des applications", 50, 300, 150, 20)
    GUICtrlSetState($checkApps, $GUI_CHECKED)
    $checkWallpaper = GUICtrlCreateCheckbox("Fond d'écran", 220, 280, 120, 20)
    GUICtrlSetState($checkWallpaper, $GUI_CHECKED)
    $checkPrinters = GUICtrlCreateCheckbox("Imprimantes", 220, 300, 120, 20)
    GUICtrlSetState($checkPrinters, $GUI_CHECKED)
    $checkSteam = GUICtrlCreateCheckbox("Dossiers Steam", 400, 280, 120, 20)
    GUICtrlSetState($checkSteam, $GUI_CHECKED)
    $checkWifi = GUICtrlCreateCheckbox("Paramètres WiFi", 400, 300, 120, 20)
    GUICtrlSetState($checkWifi, $GUI_CHECKED)
    
    ; Boutons de sélection rapide
    $btnSelectAllUser = GUICtrlCreateButton("Tout sélectionner", 50, 320, 100, 25)
    GUICtrlSetOnEvent($btnSelectAllUser, "_SelectAllUserOptions")
    $btnDeselectAllUser = GUICtrlCreateButton("Tout désélectionner", 160, 320, 120, 25)
    GUICtrlSetOnEvent($btnDeselectAllUser, "_DeselectAllUserOptions")
    
    GUICtrlCreateGroup("", -99, -99, 1, 1)
    
    ; Groupe Actions de sauvegarde
    GUICtrlCreateGroup("Actions de sauvegarde", 30, 370, 360, 100)
    $btnBackup = GUICtrlCreateButton("DÉMARRER LA SAUVEGARDE", 50, 400, 200, 40)
    GUICtrlSetFont($btnBackup, 10, 600)
    GUICtrlSetBkColor($btnBackup, 0x90EE90)
    GUICtrlSetOnEvent($btnBackup, "_StartBackupProcess")
    
    $btnListApps = GUICtrlCreateButton("Lister Applications", 270, 400, 100, 40)
    GUICtrlSetOnEvent($btnListApps, "_ListApplications")
    GUICtrlCreateGroup("", -99, -99, 1, 1)
    
    ; Groupe Restauration
    GUICtrlCreateGroup("Restauration", 410, 370, 360, 100)
    GUICtrlCreateLabel("Sauvegardes disponibles :", 430, 390, 150, 20)
    $hListBackups = GUICtrlCreateCombo("", 430, 410, 200, 20)
    
    $btnRefreshBackups = GUICtrlCreateButton("Actualiser", 640, 410, 60, 20)
    GUICtrlSetOnEvent($btnRefreshBackups, "_RefreshBackupsList")
    
    $btnRestore = GUICtrlCreateButton("RESTAURER", 430, 440, 100, 25)
    GUICtrlSetFont($btnRestore, 10, 600)
    GUICtrlSetBkColor($btnRestore, 0xFFB6C1)
    GUICtrlSetOnEvent($btnRestore, "_StartRestoreProcess")
    GUICtrlCreateGroup("", -99, -99, 1, 1)
    
    ; Groupe Progress Bar
    GUICtrlCreateGroup("Progression", 30, 490, 740, 60)
    $lblProgress = GUICtrlCreateLabel("Prêt pour la sauvegarde", 50, 510, 300, 20)
    $progressBar = GUICtrlCreateProgress(50, 530, 680, 15)
    GUICtrlCreateGroup("", -99, -99, 1, 1)
    
    ; Initialisation
    _InitializeSauveRestore()
EndFunc

; Initialisation de l'interface
Func _InitializeSauveRestore()
    _Log("=== GESTIONNAIRE DE SAUVEGARDE & RESTAURATION ===", "Sauvegarde/Restauration", "Info")
    _Log("TechSuivi - Version 4.0", "Sauvegarde/Restauration", "Info")
    _Log("Date: " & @YEAR & "-" & @MON & "-" & @MDAY & " " & @HOUR & ":" & @MIN & ":" & @SEC, "Sauvegarde/Restauration", "Info")
    _Log("", "Sauvegarde/Restauration", "Info")
    
    ; Vérifier si un chemin de sauvegarde par défaut existe
    Local $sDefaultPath = RegRead("HKEY_CURRENT_USER\Software\TechSuivi", "DefaultBackupPath")
    If $sDefaultPath <> "" And FileExists($sDefaultPath) Then
        $sBackupPath = $sDefaultPath
        _UpdateDestinationLabel()
        _RefreshBackupsList()
    EndIf
EndFunc

; Sélection du chemin de sauvegarde
Func _SelectBackupPath()
    Local $sNewPath = FileSelectFolder("Choisir le dossier de destination (disque externe recommandé)", $sBackupPath)
    If $sNewPath <> "" Then
        $sBackupPath = $sNewPath
        _UpdateDestinationLabel()
        _RefreshBackupsList()
        _Log("- Nouveau chemin de sauvegarde: " & $sBackupPath, "Sauvegarde/Restauration", "Config")
        
        ; Sauvegarder le chemin par défaut
        RegWrite("HKEY_CURRENT_USER\Software\TechSuivi", "DefaultBackupPath", "REG_SZ", $sBackupPath)
    EndIf
EndFunc

; Mise à jour du label de destination
Func _UpdateDestinationLabel()
    If $sBackupPath <> "" Then
        GUICtrlSetData($lblDestination, "Destination: " & $sBackupPath)
        GUICtrlSetColor($lblDestination, 0x008000)
        
        ; Vérifier l'espace disponible
        Local $iFreeSpace = DriveSpaceFree($sBackupPath)
        If $iFreeSpace > 0 Then
            GUICtrlSetData($lblDestination, GUICtrlRead($lblDestination) & " (Espace libre: " & Round($iFreeSpace / 1024, 1) & " GB)")
        EndIf
    Else
        GUICtrlSetData($lblDestination, "Aucune destination sélectionnée")
        GUICtrlSetColor($lblDestination, 0xFF0000)
    EndIf
EndFunc

; Démarrage du processus de sauvegarde
Func _StartBackupProcess()
    If $sBackupPath = "" Then
        _Log("ERREUR: Veuillez d'abord choisir une destination de sauvegarde", "Sauvegarde/Restauration", "Erreur")
        Return
    EndIf
    
    If $bOperationInProgress Then
        _Log("ERREUR: Une opération est déjà en cours", "Sauvegarde/Restauration", "Erreur")
        Return
    EndIf
    
    ; Vérifier les options sélectionnées
    Local $bDesktop = (GUICtrlRead($checkDesktop) = $GUI_CHECKED)
    Local $bDocuments = (GUICtrlRead($checkDocuments) = $GUI_CHECKED)
    Local $bPictures = (GUICtrlRead($checkPictures) = $GUI_CHECKED)
    Local $bMusic = (GUICtrlRead($checkMusic) = $GUI_CHECKED)
    Local $bVideos = (GUICtrlRead($checkVideos) = $GUI_CHECKED)
    Local $bDownloads = (GUICtrlRead($checkDownloads) = $GUI_CHECKED)
    Local $bBrowsers = (GUICtrlRead($checkBrowsers) = $GUI_CHECKED)
    Local $bFirefoxPasswords = (GUICtrlRead($checkFirefoxPasswords) = $GUI_CHECKED)
    Local $bThunderbird = (GUICtrlRead($checkThunderbird) = $GUI_CHECKED)
    Local $bSystem = (GUICtrlRead($checkSystem) = $GUI_CHECKED)
    Local $bApps = (GUICtrlRead($checkApps) = $GUI_CHECKED)
    Local $bWallpaper = (GUICtrlRead($checkWallpaper) = $GUI_CHECKED)
    Local $bPrinters = (GUICtrlRead($checkPrinters) = $GUI_CHECKED)
    Local $bSteam = (GUICtrlRead($checkSteam) = $GUI_CHECKED)
    Local $bWifi = (GUICtrlRead($checkWifi) = $GUI_CHECKED)
    
    Local $bUserFiles = ($bDesktop Or $bDocuments Or $bPictures Or $bMusic Or $bVideos Or $bDownloads)
    
    If Not ($bUserFiles Or $bBrowsers Or $bFirefoxPasswords Or $bThunderbird Or $bSystem Or $bApps Or $bWallpaper Or $bPrinters Or $bSteam Or $bWifi) Then
        _Log("ERREUR: Veuillez sélectionner au moins une option de sauvegarde", "Sauvegarde/Restauration", "Erreur")
        Return
    EndIf
    
    ; Démarrer la sauvegarde
    $bOperationInProgress = True
    GUICtrlSetState($btnBackup, $GUI_DISABLE)
    
    _Log("=== DÉBUT DE LA SAUVEGARDE ===", "Sauvegarde/Restauration", "Info")
    _PerformAdvancedBackup($bDesktop, $bDocuments, $bPictures, $bMusic, $bVideos, $bDownloads, $bBrowsers, $bFirefoxPasswords, $bThunderbird, $bWallpaper, $bPrinters, $bSteam, $bWifi, $bSystem, $bApps)
    
    $bOperationInProgress = False
    GUICtrlSetState($btnBackup, $GUI_ENABLE)
    _RefreshBackupsList()
EndFunc

; Processus de sauvegarde avancé avec progress bar
Func _PerformAdvancedBackup($bDesktop, $bDocuments, $bPictures, $bMusic, $bVideos, $bDownloads, $bBrowsers, $bFirefoxPasswords, $bThunderbird, $bWallpaper, $bPrinters, $bSteam, $bWifi, $bSystem, $bApps)
    _Log("Préparation de la sauvegarde...", "Sauvegarde/Restauration", "Info")
    GUICtrlSetData($lblProgress, "Initialisation...")
    GUICtrlSetData($progressBar, 0)
    
    ; Créer le dossier de sauvegarde
    Local $sBackupFolder = $sBackupPath & "\Sauvegarde_" & @YEAR & @MON & @MDAY & "_" & @HOUR & @MIN & @SEC
    DirCreate($sBackupFolder)
    
    ; Calculer le nombre total d'étapes
    Local $iTotalSteps = 0
    Local $iCurrentStep = 0
    Local $bUserFiles = ($bDesktop Or $bDocuments Or $bPictures Or $bMusic Or $bVideos Or $bDownloads)
    
    If $bApps Then $iTotalSteps += 1
    If $bUserFiles Then $iTotalSteps += 6 ; 6 dossiers utilisateur possibles
    If $bBrowsers Then $iTotalSteps += 1
    If $bFirefoxPasswords Then $iTotalSteps += 1
    If $bThunderbird Then $iTotalSteps += 1
    If $bWallpaper Then $iTotalSteps += 1
    If $bPrinters Then $iTotalSteps += 1
    If $bSteam Then $iTotalSteps += 1
    If $bWifi Then $iTotalSteps += 1
    If $bSystem Then $iTotalSteps += 1
    
    ; Sauvegarde des applications
    If $bApps Then
        $iCurrentStep += 1
        _UpdateProgress($iCurrentStep, $iTotalSteps, "Sauvegarde de la liste des applications...")
        _Log("- Sauvegarde de la liste des applications...", "Sauvegarde/Restauration", "Applications")
        _ListInstalledApplications($sBackupFolder)
        _Log("  OK: Liste des applications sauvegardée", "Sauvegarde/Restauration", "Applications")
    EndIf
    
    ; Sauvegarde des fichiers utilisateur
    If $bUserFiles Then
        _Log("- Sauvegarde des fichiers utilisateur...", "Sauvegarde/Restauration", "Fichiers")
        _BackupUserFilesGranularRobocopy($sBackupFolder, $bDesktop, $bDocuments, $bPictures, $bMusic, $bVideos, $bDownloads, $iCurrentStep, $iTotalSteps)
        $iCurrentStep += 6 ; Ajuster selon le nombre de dossiers traités
        _Log("  OK: Fichiers utilisateur sauvegardés", "Sauvegarde/Restauration", "Fichiers")
    EndIf
    
    ; Sauvegarde des favoris
    If $bBrowsers Then
        $iCurrentStep += 1
        _UpdateProgress($iCurrentStep, $iTotalSteps, "Sauvegarde des favoris des navigateurs...")
        _Log("- Sauvegarde des favoris des navigateurs...", "Sauvegarde/Restauration", "Navigateurs")
        _BackupBrowserBookmarks($sBackupFolder)
        _Log("  OK: Favoris des navigateurs sauvegardés", "Sauvegarde/Restauration", "Navigateurs")
    EndIf
    
    ; Sauvegarde des mots de passe Firefox
    If $bFirefoxPasswords Then
        $iCurrentStep += 1
        _UpdateProgress($iCurrentStep, $iTotalSteps, "Sauvegarde des mots de passe Firefox...")
        _Log("- Sauvegarde des mots de passe Firefox...", "Sauvegarde/Restauration", "Navigateurs")
        _BackupFirefoxPasswords($sBackupFolder)
        _Log("  OK: Mots de passe Firefox sauvegardés", "Sauvegarde/Restauration", "Navigateurs")
    EndIf
    
    ; Sauvegarde de Thunderbird
    If $bThunderbird Then
        $iCurrentStep += 1
        _UpdateProgress($iCurrentStep, $iTotalSteps, "Sauvegarde de Thunderbird...")
        _Log("- Sauvegarde de Thunderbird...", "Sauvegarde/Restauration", "Thunderbird")
        _BackupThunderbirdRobocopy($sBackupFolder)
        _Log("  OK: Thunderbird sauvegardé", "Sauvegarde/Restauration", "Thunderbird")
    EndIf
    
    ; Sauvegarde du fond d'écran
    If $bWallpaper Then
        $iCurrentStep += 1
        _UpdateProgress($iCurrentStep, $iTotalSteps, "Sauvegarde du fond d'écran...")
        _Log("- Sauvegarde du fond d'écran...", "Sauvegarde/Restauration", "Système")
        _BackupWallpaper($sBackupFolder)
        _Log("  OK: Fond d'écran sauvegardé", "Sauvegarde/Restauration", "Système")
    EndIf
    
    ; Sauvegarde des imprimantes
    If $bPrinters Then
        $iCurrentStep += 1
        _UpdateProgress($iCurrentStep, $iTotalSteps, "Sauvegarde des imprimantes...")
        _Log("- Sauvegarde des imprimantes...", "Sauvegarde/Restauration", "Système")
        _BackupPrinters($sBackupFolder)
        _Log("  OK: Imprimantes sauvegardées", "Sauvegarde/Restauration", "Système")
    EndIf
    
    ; Sauvegarde des dossiers Steam
    If $bSteam Then
        $iCurrentStep += 1
        _UpdateProgress($iCurrentStep, $iTotalSteps, "Sauvegarde des dossiers Steam...")
        _Log("- Sauvegarde des dossiers Steam...", "Sauvegarde/Restauration", "Steam")
        _BackupSteamRobocopy($sBackupFolder)
        _Log("  OK: Dossiers Steam sauvegardés", "Sauvegarde/Restauration", "Steam")
    EndIf
    
    ; Sauvegarde des paramètres WiFi
    If $bWifi Then
        $iCurrentStep += 1
        _UpdateProgress($iCurrentStep, $iTotalSteps, "Sauvegarde des paramètres WiFi...")
        _Log("- Sauvegarde des paramètres WiFi...", "Sauvegarde/Restauration", "Système")
        _BackupWifi($sBackupFolder)
        _Log("  OK: Paramètres WiFi sauvegardés", "Sauvegarde/Restauration", "Système")
    EndIf
    
    ; Sauvegarde des paramètres système
    If $bSystem Then
        $iCurrentStep += 1
        _UpdateProgress($iCurrentStep, $iTotalSteps, "Sauvegarde des paramètres système...")
        _Log("- Sauvegarde des paramètres système...", "Sauvegarde/Restauration", "Système")
        _BackupSystemSettings($sBackupFolder)
        _Log("  OK: Paramètres système sauvegardés", "Sauvegarde/Restauration", "Système")
    EndIf
    
    _UpdateProgress(100, 100, "Sauvegarde terminée !")
    _Log("=== SAUVEGARDE TERMINÉE ===", "Sauvegarde/Restauration", "Info")
    _Log("Dossier: " & $sBackupFolder, "Sauvegarde/Restauration", "Info")
EndFunc

; Fonction pour mettre à jour la progress bar
Func _UpdateProgress($iCurrent, $iTotal, $sMessage)
    Local $iPercent = Round(($iCurrent / $iTotal) * 100)
    GUICtrlSetData($progressBar, $iPercent)
    GUICtrlSetData($lblProgress, $sMessage & " (" & $iPercent & "%)")
EndFunc

; Actualiser la liste des sauvegardes
Func _RefreshBackupsList()
    If $sBackupPath = "" Then Return
    
    GUICtrlSetData($hListBackups, "")
    ReDim $aBackupPaths[1]
    $aBackupPaths[0] = 0
    
    Local $aBackups = _FileListToArray($sBackupPath, "Sauvegarde_*", $FLTA_FOLDERS)
    If IsArray($aBackups) Then
        For $i = 1 To $aBackups[0]
            Local $sBackupPath_Full = $sBackupPath & "\" & $aBackups[$i]
            
            ; Stocker le chemin dans le tableau
            ReDim $aBackupPaths[$aBackupPaths[0] + 2]
            $aBackupPaths[0] += 1
            $aBackupPaths[$aBackupPaths[0]] = $sBackupPath_Full
            
            GUICtrlSetData($hListBackups, $aBackups[$i], $aBackups[$i])
        Next
        _Log("- " & $aBackups[0] & " sauvegarde(s) trouvée(s)", "Sauvegarde/Restauration", "Info")
    EndIf
EndFunc

; Démarrage du processus de restauration avec sélection
Func _StartRestoreProcess()
    If $sBackupPath = "" Then
        _Log("ERREUR: Veuillez d'abord choisir un dossier de sauvegarde", "Sauvegarde/Restauration", "Erreur")
        Return
    EndIf
    
    If $bOperationInProgress Then
        _Log("ERREUR: Une opération est déjà en cours", "Sauvegarde/Restauration", "Erreur")
        Return
    EndIf
    
    ; Vérifier qu'une sauvegarde est sélectionnée
    Local $sSelectedBackup = GUICtrlRead($hListBackups)
    If $sSelectedBackup = "" Then
        _Log("ERREUR: Veuillez sélectionner une sauvegarde à restaurer", "Sauvegarde/Restauration", "Erreur")
        Return
    EndIf
    
    Local $sSelectedBackupPath = $sBackupPath & "\" & $sSelectedBackup
    
    ; Ouvrir la popup de sélection des éléments à restaurer
    Local $aSelectedItems = _ShowRestoreSelectionDialog($sSelectedBackupPath, $sSelectedBackup)
    If Not IsArray($aSelectedItems) Then Return ; Utilisateur a annulé
    
    ; Confirmation finale
    Local $iResponse = MsgBox($MB_YESNO + $MB_ICONQUESTION, "Confirmation", "Restaurer les éléments sélectionnés ?" & @CRLF & @CRLF & "ATTENTION: Cela remplacera vos fichiers actuels!" & @CRLF & @CRLF & "Sauvegarde: " & $sSelectedBackup)
    If $iResponse = $IDNO Then Return
    
    ; Démarrer la restauration
    $bOperationInProgress = True
    GUICtrlSetState($btnRestore, $GUI_DISABLE)
    
    _Log("=== DÉBUT DE LA RESTAURATION ===", "Sauvegarde/Restauration", "Info")
    _PerformSelectiveRestore($sSelectedBackupPath, $aSelectedItems)
    
    $bOperationInProgress = False
    GUICtrlSetState($btnRestore, $GUI_ENABLE)
EndFunc

; Processus de restauration avancé
Func _PerformAdvancedRestore($sRestoreFolder)
    _Log("Restauration en cours depuis: " & $sRestoreFolder, "Sauvegarde/Restauration", "Info")
    
    ; Restaurer selon le contenu disponible
    If FileExists($sRestoreFolder & "\Fichiers_Utilisateur") Then
        _Log("- Restauration des fichiers utilisateur...", "Sauvegarde/Restauration", "Fichiers")
        _RestoreUserFilesGranular($sRestoreFolder)
        _Log("  ✓ Fichiers utilisateur restaurés", "Sauvegarde/Restauration", "Fichiers")
    EndIf
    
    If FileExists($sRestoreFolder & "\Favoris_Navigateurs") Then
        _Log("- Restauration des favoris des navigateurs...", "Sauvegarde/Restauration", "Navigateurs")
        _RestoreBrowserBookmarks($sRestoreFolder)
        _Log("  ✓ Favoris des navigateurs restaurés", "Sauvegarde/Restauration", "Navigateurs")
    EndIf
    
    If FileExists($sRestoreFolder & "\Firefox_Passwords") Then
        _Log("- Restauration des mots de passe Firefox...", "Sauvegarde/Restauration", "Navigateurs")
        _RestoreFirefoxPasswords($sRestoreFolder)
        _Log("  ✓ Mots de passe Firefox restaurés", "Sauvegarde/Restauration", "Navigateurs")
    EndIf
    
    If FileExists($sRestoreFolder & "\Thunderbird") Then
        _Log("- Restauration de Thunderbird...", "Sauvegarde/Restauration", "Thunderbird")
        _RestoreThunderbird($sRestoreFolder)
        _Log("  ✓ Thunderbird restauré", "Sauvegarde/Restauration", "Thunderbird")
    EndIf
    
    If FileExists($sRestoreFolder & "\Fond_Ecran") Then
        _Log("- Restauration du fond d'écran...", "Sauvegarde/Restauration", "Système")
        _RestoreWallpaper($sRestoreFolder)
        _Log("  ✓ Fond d'écran restauré", "Sauvegarde/Restauration", "Système")
    EndIf
    
    If FileExists($sRestoreFolder & "\Imprimantes") Then
        _Log("- Restauration des imprimantes...", "Sauvegarde/Restauration", "Système")
        _RestorePrinters($sRestoreFolder)
        _Log("  ✓ Imprimantes restaurées", "Sauvegarde/Restauration", "Système")
    EndIf
    
    If FileExists($sRestoreFolder & "\Steam") Then
        _Log("- Restauration de Steam...", "Sauvegarde/Restauration", "Steam")
        _RestoreSteam($sRestoreFolder)
        _Log("  ✓ Steam restauré", "Sauvegarde/Restauration", "Steam")
    EndIf
    
    If FileExists($sRestoreFolder & "\Parametres_WiFi") Then
        _Log("- Restauration des paramètres WiFi...", "Sauvegarde/Restauration", "Système")
        _RestoreWifi($sRestoreFolder)
        _Log("  ✓ Paramètres WiFi restaurés", "Sauvegarde/Restauration", "Système")
    EndIf
    
    If FileExists($sRestoreFolder & "\Parametres_Systeme") Then
        _Log("- Restauration des paramètres système...", "Sauvegarde/Restauration", "Système")
        _RestoreSystemSettings($sRestoreFolder)
        _Log("  ✓ Paramètres système restaurés", "Sauvegarde/Restauration", "Système")
    EndIf
    
    _Log("=== RESTAURATION TERMINÉE ===", "Sauvegarde/Restauration", "Info")
    _Log("Un redémarrage peut être nécessaire pour appliquer tous les changements", "Sauvegarde/Restauration", "Info")
EndFunc

; Fonction pour sélectionner toutes les options utilisateur
Func _SelectAllUserOptions()
    GUICtrlSetState($checkDesktop, $GUI_CHECKED)
    GUICtrlSetState($checkDocuments, $GUI_CHECKED)
    GUICtrlSetState($checkPictures, $GUI_CHECKED)
    GUICtrlSetState($checkMusic, $GUI_CHECKED)
    GUICtrlSetState($checkVideos, $GUI_CHECKED)
    GUICtrlSetState($checkDownloads, $GUI_CHECKED)
    GUICtrlSetState($checkBrowsers, $GUI_CHECKED)
    GUICtrlSetState($checkFirefoxPasswords, $GUI_CHECKED)
    GUICtrlSetState($checkThunderbird, $GUI_CHECKED)
    GUICtrlSetState($checkSystem, $GUI_CHECKED)
    GUICtrlSetState($checkApps, $GUI_CHECKED)
    GUICtrlSetState($checkWallpaper, $GUI_CHECKED)
    GUICtrlSetState($checkPrinters, $GUI_CHECKED)
    GUICtrlSetState($checkSteam, $GUI_CHECKED)
    GUICtrlSetState($checkWifi, $GUI_CHECKED)
EndFunc

; Fonction pour désélectionner toutes les options utilisateur
Func _DeselectAllUserOptions()
    GUICtrlSetState($checkDesktop, $GUI_UNCHECKED)
    GUICtrlSetState($checkDocuments, $GUI_UNCHECKED)
    GUICtrlSetState($checkPictures, $GUI_UNCHECKED)
    GUICtrlSetState($checkMusic, $GUI_UNCHECKED)
    GUICtrlSetState($checkVideos, $GUI_UNCHECKED)
    GUICtrlSetState($checkDownloads, $GUI_UNCHECKED)
    GUICtrlSetState($checkBrowsers, $GUI_UNCHECKED)
    GUICtrlSetState($checkFirefoxPasswords, $GUI_UNCHECKED)
    GUICtrlSetState($checkThunderbird, $GUI_UNCHECKED)
    GUICtrlSetState($checkSystem, $GUI_UNCHECKED)
    GUICtrlSetState($checkApps, $GUI_UNCHECKED)
    GUICtrlSetState($checkWallpaper, $GUI_UNCHECKED)
    GUICtrlSetState($checkPrinters, $GUI_UNCHECKED)
    GUICtrlSetState($checkSteam, $GUI_UNCHECKED)
    GUICtrlSetState($checkWifi, $GUI_UNCHECKED)
EndFunc

; Fonction pour lister les applications
Func _ListApplications()
    _Log("- Analyse des applications installées...", "Sauvegarde/Restauration", "Applications")
    _ListInstalledApplications(@ScriptDir)
    _Log("  OK: Liste des applications générée", "Sauvegarde/Restauration", "Applications")
EndFunc

; ===============================================================================
; FONCTIONS DE SAUVEGARDE ET RESTAURATION ADAPTÉES
; ===============================================================================

; Fonction pour lister les applications installées
Func _ListInstalledApplications($sBackupFolder)
    Local $sAppFile = $sBackupFolder & "\applications_installees.txt"
    FileDelete($sAppFile) ; Supprimer l'ancien fichier
    
    FileWriteLine($sAppFile, "=== LISTING DES APPLICATIONS INSTALLEES ===")
    FileWriteLine($sAppFile, "Date: " & @YEAR & "-" & @MON & "-" & @MDAY & " " & @HOUR & ":" & @MIN & ":" & @SEC)
    FileWriteLine($sAppFile, "")
    
    ; Applications depuis le registre (64-bit)
    Local $sRegKey = "HKEY_LOCAL_MACHINE\SOFTWARE\Microsoft\Windows\CurrentVersion\Uninstall"
    _ListAppsFromRegistry($sRegKey, "Applications 64-bit", $sAppFile)
    
    ; Applications depuis le registre (32-bit sur système 64-bit)
    $sRegKey = "HKEY_LOCAL_MACHINE\SOFTWARE\WOW6432Node\Microsoft\Windows\CurrentVersion\Uninstall"
    _ListAppsFromRegistry($sRegKey, "Applications 32-bit", $sAppFile)
    
    ; Applications utilisateur
    $sRegKey = "HKEY_CURRENT_USER\SOFTWARE\Microsoft\Windows\CurrentVersion\Uninstall"
    _ListAppsFromRegistry($sRegKey, "Applications utilisateur", $sAppFile)
    
    FileWriteLine($sAppFile, "")
    FileWriteLine($sAppFile, "=== FIN DU LISTING ===")
EndFunc

; Fonction pour lister les applications depuis le registre
Func _ListAppsFromRegistry($sRegKey, $sCategory, $sAppFile)
    FileWriteLine($sAppFile, "--- " & $sCategory & " ---")
    
    Local $i = 1
    While 1
        Local $sSubKey = RegEnumKey($sRegKey, $i)
        If @error Then ExitLoop
        
        Local $sDisplayName = RegRead($sRegKey & "\" & $sSubKey, "DisplayName")
        Local $sVersion = RegRead($sRegKey & "\" & $sSubKey, "DisplayVersion")
        Local $sPublisher = RegRead($sRegKey & "\" & $sSubKey, "Publisher")
        
        If $sDisplayName <> "" Then
            Local $sAppInfo = $sDisplayName
            If $sVersion <> "" Then $sAppInfo &= " (v" & $sVersion & ")"
            If $sPublisher <> "" Then $sAppInfo &= " - " & $sPublisher
            FileWriteLine($sAppFile, $sAppInfo)
        EndIf
        
        $i += 1
    WEnd
    FileWriteLine($sAppFile, "")
EndFunc

; Fonction pour vérifier l'intégrité d'une copie de dossier avec logging détaillé
Func _VerifyFolderCopy($sSourcePath, $sDestPath, $sFolderName, $sBackupFolder = "")
    If Not FileExists($sSourcePath) Or Not FileExists($sDestPath) Then
        _Log("      ⚠ Impossible de vérifier " & $sFolderName & " (dossier manquant)", "Sauvegarde/Restauration", "Erreur")
        Return False
    EndIf
    
    ; Compter les fichiers dans le dossier source (récursif)
    Local $iSourceCount = _CountFilesRecursive($sSourcePath)
    
    ; Compter les fichiers dans le dossier de destination (récursif)
    Local $iDestCount = _CountFilesRecursive($sDestPath)
    
    ; Comparer les nombres
    If $iSourceCount = $iDestCount Then
        _Log("      ✓ " & $sFolderName & " vérifié (" & $iSourceCount & " fichiers)", "Sauvegarde/Restauration", "Fichiers")
        Return True
    Else
        _Log("      ⚠ " & $sFolderName & " incomplet (Source: " & $iSourceCount & ", Copié: " & $iDestCount & ")", "Sauvegarde/Restauration", "Attention")
        
        ; Si un dossier de sauvegarde est fourni, créer un log détaillé
        If $sBackupFolder <> "" Then
            _CreateMissingFilesLog($sSourcePath, $sDestPath, $sFolderName, $sBackupFolder)
        EndIf
        
        Return False
    EndIf
EndFunc

; Fonction pour créer un log des fichiers manquants
Func _CreateMissingFilesLog($sSourcePath, $sDestPath, $sFolderName, $sBackupFolder)
    Local $sLogFile = $sBackupFolder & "\verification_log.txt"
    
    ; Créer ou ouvrir le fichier de log
    If Not FileExists($sLogFile) Then
        FileWriteLine($sLogFile, "=== LOG DE VÉRIFICATION DE SAUVEGARDE ===")
        FileWriteLine($sLogFile, "Date: " & @YEAR & "-" & @MON & "-" & @MDAY & " " & @HOUR & ":" & @MIN & ":" & @SEC)
        FileWriteLine($sLogFile, "")
    EndIf
    
    FileWriteLine($sLogFile, "--- ANALYSE: " & $sFolderName & " ---")
    FileWriteLine($sLogFile, "Source: " & $sSourcePath)
    FileWriteLine($sLogFile, "Destination: " & $sDestPath)
    FileWriteLine($sLogFile, "")
    
    ; Obtenir la liste des fichiers source et destination
    Local $aSourceFiles = _GetAllFilesRecursive($sSourcePath, $sSourcePath)
    Local $aDestFiles = _GetAllFilesRecursive($sDestPath, $sDestPath)
    
    ; Convertir les tableaux en dictionnaires pour comparaison rapide
    Local $dDestFiles = ObjCreate("Scripting.Dictionary")
    If IsArray($aDestFiles) Then
        For $i = 0 To UBound($aDestFiles) - 1
            $dDestFiles.Add($aDestFiles[$i], True)
        Next
    EndIf
    
    ; Trouver les fichiers manquants
    Local $iMissingCount = 0
    If IsArray($aSourceFiles) Then
        FileWriteLine($sLogFile, "FICHIERS MANQUANTS:")
        For $i = 0 To UBound($aSourceFiles) - 1
            If Not $dDestFiles.Exists($aSourceFiles[$i]) Then
                FileWriteLine($sLogFile, "  - " & $aSourceFiles[$i])
                $iMissingCount += 1
            EndIf
        Next
    EndIf
    
    FileWriteLine($sLogFile, "")
    FileWriteLine($sLogFile, "RÉSUMÉ:")
    FileWriteLine($sLogFile, "  Fichiers source: " & (IsArray($aSourceFiles) ? UBound($aSourceFiles) : 0))
    FileWriteLine($sLogFile, "  Fichiers copiés: " & (IsArray($aDestFiles) ? UBound($aDestFiles) : 0))
    FileWriteLine($sLogFile, "  Fichiers manquants: " & $iMissingCount)
    FileWriteLine($sLogFile, "")
    FileWriteLine($sLogFile, "===================================================")
    FileWriteLine($sLogFile, "")
    
    _Log("      📝 Log détaillé créé: verification_log.txt", "Sauvegarde/Restauration", "Info")
EndFunc

; Fonction pour obtenir tous les fichiers récursivement avec chemins relatifs
Func _GetAllFilesRecursive($sPath, $sBasePath)
    Local $aAllFiles[0]
    Local $iCount = 0
    
    ; Obtenir les fichiers du dossier actuel
    Local $aFiles = _FileListToArray($sPath, "*", $FLTA_FILES)
    If IsArray($aFiles) Then
        For $i = 1 To $aFiles[0]
            ReDim $aAllFiles[$iCount + 1]
            $aAllFiles[$iCount] = StringReplace($sPath & "\" & $aFiles[$i], $sBasePath & "\", "")
            $iCount += 1
        Next
    EndIf
    
    ; Traiter récursivement les sous-dossiers
    Local $aFolders = _FileListToArray($sPath, "*", $FLTA_FOLDERS)
    If IsArray($aFolders) Then
        For $i = 1 To $aFolders[0]
            Local $aSubFiles = _GetAllFilesRecursive($sPath & "\" & $aFolders[$i], $sBasePath)
            If IsArray($aSubFiles) Then
                For $j = 0 To UBound($aSubFiles) - 1
                    ReDim $aAllFiles[$iCount + 1]
                    $aAllFiles[$iCount] = $aSubFiles[$j]
                    $iCount += 1
                Next
            EndIf
        Next
    EndIf
    
    Return $aAllFiles
EndFunc

; Fonction pour compter récursivement les fichiers dans un dossier
Func _CountFilesRecursive($sPath)
    Local $iCount = 0
    
    ; Compter les fichiers dans le dossier actuel
    Local $aFiles = _FileListToArray($sPath, "*", $FLTA_FILES)
    If IsArray($aFiles) Then
        $iCount += $aFiles[0]
    EndIf
    
    ; Compter récursivement dans les sous-dossiers
    Local $aFolders = _FileListToArray($sPath, "*", $FLTA_FOLDERS)
    If IsArray($aFolders) Then
        For $i = 1 To $aFolders[0]
            $iCount += _CountFilesRecursive($sPath & "\" & $aFolders[$i])
        Next
    EndIf
    
    Return $iCount
EndFunc

; Fonction pour sauvegarder les fichiers utilisateur de manière granulaire
Func _BackupUserFilesGranular($sBackupFolder, $bDesktop, $bDocuments, $bPictures, $bMusic, $bVideos, $bDownloads)
    Local $sUserBackup = $sBackupFolder & "\Fichiers_Utilisateur"
    DirCreate($sUserBackup)
    
    ; Tableau des dossiers avec leurs options correspondantes
    Local $aUserFolders[6][3] = [ _
        ["Desktop", "Bureau", $bDesktop], _
        ["Documents", "Documents", $bDocuments], _
        ["Pictures", "Images", $bPictures], _
        ["Music", "Musique", $bMusic], _
        ["Videos", "Vidéos", $bVideos], _
        ["Downloads", "Téléchargements", $bDownloads] _
    ]
    
    For $i = 0 To UBound($aUserFolders) - 1
        If $aUserFolders[$i][2] Then ; Si l'option est cochée
            Local $sSourcePath = @UserProfileDir & "\" & $aUserFolders[$i][0]
            Local $sDestPath = $sUserBackup & "\" & $aUserFolders[$i][0]
            
            If FileExists($sSourcePath) Then
                _Log("    Sauvegarde: " & $aUserFolders[$i][1], "Sauvegarde/Restauration", "Fichiers")
                DirCopy($sSourcePath, $sDestPath, $FC_OVERWRITE)
                
                ; Vérification d'intégrité avec logging
                _VerifyFolderCopy($sSourcePath, $sDestPath, $aUserFolders[$i][1], $sBackupFolder)
            Else
                _Log("    Dossier non trouvé: " & $aUserFolders[$i][1], "Sauvegarde/Restauration", "Fichiers")
            EndIf
        EndIf
    Next
EndFunc

; Fonction pour sauvegarder les fichiers utilisateur avec Robocopy
Func _BackupUserFilesGranularRobocopy($sBackupFolder, $bDesktop, $bDocuments, $bPictures, $bMusic, $bVideos, $bDownloads, ByRef $iCurrentStep, $iTotalSteps)
    Local $sUserBackup = $sBackupFolder & "\Fichiers_Utilisateur"
    DirCreate($sUserBackup)
    
    ; Tableau des dossiers avec leurs options correspondantes
    Local $aUserFolders[6][3] = [ _
        ["Desktop", "Bureau", $bDesktop], _
        ["Documents", "Documents", $bDocuments], _
        ["Pictures", "Images", $bPictures], _
        ["Music", "Musique", $bMusic], _
        ["Videos", "Vidéos", $bVideos], _
        ["Downloads", "Téléchargements", $bDownloads] _
    ]
    
    For $i = 0 To UBound($aUserFolders) - 1
        If $aUserFolders[$i][2] Then ; Si l'option est cochée
            $iCurrentStep += 1
            _UpdateProgress($iCurrentStep, $iTotalSteps, "Sauvegarde: " & $aUserFolders[$i][1])
            
            Local $sSourcePath = @UserProfileDir & "\" & $aUserFolders[$i][0]
            Local $sDestPath = $sUserBackup & "\" & $aUserFolders[$i][0]
            
            If FileExists($sSourcePath) Then
                _Log("    Sauvegarde: " & $aUserFolders[$i][1], "Sauvegarde/Restauration", "Fichiers")
                
                ; Utiliser Robocopy avec logging
                Local $sLogFile = $sBackupFolder & "\robocopy_" & $aUserFolders[$i][0] & ".log"
                Local $sRobocopyCmd = 'robocopy "' & $sSourcePath & '" "' & $sDestPath & '" /E /COPYALL /R:3 /W:1 /LOG:"' & $sLogFile & '" /TEE'
                
                Local $iPID = Run($sRobocopyCmd, "", @SW_HIDE)
                ProcessWaitClose($iPID)
                
                ; Vérifier l'intégrité en comparant source vs destination
                _VerifyRobocopyIntegrity($sSourcePath, $sDestPath, $aUserFolders[$i][1], $sLogFile)
                
            Else
                _Log("    Dossier non trouvé: " & $aUserFolders[$i][1], "Sauvegarde/Restauration", "Fichiers")
            EndIf
        Else
            $iCurrentStep += 1 ; Incrémenter même si non sélectionné pour garder la cohérence
        EndIf
    Next
EndFunc

; Fonction pour vérifier l'intégrité avec Robocopy (focus sur les fichiers manquants)
Func _VerifyRobocopyIntegrity($sSourcePath, $sDestPath, $sFolderName, $sLogFile)
    ; Compter les fichiers dans la source et la destination
    Local $iSourceFiles = _CountFilesRecursive($sSourcePath)
    Local $iDestFiles = _CountFilesRecursive($sDestPath)
    Local $iMissingFiles = $iSourceFiles - $iDestFiles
    
    ; Analyser le log pour détecter les erreurs
    Local $iErrors = 0
    If FileExists($sLogFile) Then
        Local $sLogContent = FileRead($sLogFile)
        Local $aLines = StringSplit($sLogContent, @CRLF, 1)
        
        For $i = 1 To $aLines[0]
            Local $sLine = StringStripWS($aLines[$i], 3)
            ; Détecter les erreurs dans le log
            If StringInStr($sLine, "ERREUR") Or StringInStr($sLine, "ERROR") Or StringInStr($sLine, "ÉCHEC") Or StringInStr($sLine, "FAILED") Or StringInStr($sLine, "Accès refusé") Or StringInStr($sLine, "Access denied") Then
                $iErrors += 1
            EndIf
        Next
    EndIf
    
    ; Déterminer le statut et mettre à jour la couleur de la checkbox correspondante
    Local $bSuccess = ($iMissingFiles = 0 And $iErrors = 0 And $iSourceFiles > 0)
    _UpdateCheckboxColor($sFolderName, $bSuccess)
    
    ; Afficher le résultat selon l'intégrité avec texte clair
    If $iMissingFiles > 0 Then
        _Log("      ERREUR: " & $sFolderName & " - " & $iMissingFiles & " fichier(s) manquant(s) !", "Sauvegarde/Restauration", "Erreur")
        If $iErrors > 0 Then
            _Log("      LOG: " & $iErrors & " erreur(s) dans le log - Voir: " & StringReplace($sLogFile, StringLeft($sLogFile, StringInStr($sLogFile, "\", 0, -1)), ""), "Sauvegarde/Restauration", "Erreur")
        EndIf
    ElseIf $iErrors > 0 Then
        _Log("      ERREUR: " & $sFolderName & " - " & $iErrors & " erreur(s) détectée(s)", "Sauvegarde/Restauration", "Erreur")
        _Log("      LOG: Voir détails: " & StringReplace($sLogFile, StringLeft($sLogFile, StringInStr($sLogFile, "\", 0, -1)), ""), "Sauvegarde/Restauration", "Erreur")
    ElseIf $iSourceFiles = 0 Then
        _Log("      INFO: " & $sFolderName & " - Dossier vide", "Sauvegarde/Restauration", "Info")
    Else
        _Log("      OK: " & $sFolderName & " - Intégrité complète (" & $iDestFiles & " fichiers)", "Sauvegarde/Restauration", "Fichiers")
    EndIf
EndFunc

; Fonction pour mettre à jour la couleur des checkboxes selon le statut
Func _UpdateCheckboxColor($sFolderName, $bSuccess)
    Local $iCheckboxControl = 0
    
    ; Identifier la checkbox correspondante
    Switch $sFolderName
        Case "Bureau"
            $iCheckboxControl = $checkDesktop
        Case "Documents"
            $iCheckboxControl = $checkDocuments
        Case "Images"
            $iCheckboxControl = $checkPictures
        Case "Musique"
            $iCheckboxControl = $checkMusic
        Case "Vidéos"
            $iCheckboxControl = $checkVideos
        Case "Téléchargements"
            $iCheckboxControl = $checkDownloads
        Case "Thunderbird Roaming", "Thunderbird Local"
            $iCheckboxControl = $checkThunderbird
        Case "Config Steam", "Données Steam"
            $iCheckboxControl = $checkSteam
    EndSwitch
    
    ; Appliquer la couleur selon le statut
    If $iCheckboxControl > 0 Then
        If $bSuccess Then
            GUICtrlSetBkColor($iCheckboxControl, 0x90EE90) ; Vert clair
        Else
            GUICtrlSetBkColor($iCheckboxControl, 0xFFB6C1) ; Rouge clair
        EndIf
    EndIf
EndFunc

; Fonction pour sauvegarder Thunderbird avec Robocopy
Func _BackupThunderbirdRobocopy($sBackupFolder)
    Local $sThunderbirdBackup = $sBackupFolder & "\Thunderbird"
    DirCreate($sThunderbirdBackup)
    
    ; Chemins Thunderbird
    Local $sThunderbirdRoaming = @AppDataDir & "\Thunderbird"
    Local $sThunderbirdLocal = @LocalAppDataDir & "\Thunderbird"
    
    Local $bFound = False
    
    ; Sauvegarder le dossier Roaming (profils et données)
    If FileExists($sThunderbirdRoaming) Then
        _Log("    Sauvegarde Thunderbird Roaming...", "Sauvegarde/Restauration", "Thunderbird")
        
        Local $sLogFile = $sBackupFolder & "\robocopy_Thunderbird_Roaming.log"
        Local $sRobocopyCmd = 'robocopy "' & $sThunderbirdRoaming & '" "' & $sThunderbirdBackup & '\Roaming" /E /COPYALL /R:3 /W:1 /LOG:"' & $sLogFile & '" /TEE'
        
        Local $iPID = Run($sRobocopyCmd, "", @SW_HIDE)
        ProcessWaitClose($iPID)
        
        _VerifyRobocopyIntegrity($sThunderbirdRoaming, $sThunderbirdBackup & "\Roaming", "Thunderbird Roaming", $sLogFile)
        $bFound = True
    EndIf
    
    ; Sauvegarder le dossier Local (cache et données temporaires)
    If FileExists($sThunderbirdLocal) Then
        _Log("    Sauvegarde Thunderbird Local...", "Sauvegarde/Restauration", "Thunderbird")
        
        Local $sLogFile = $sBackupFolder & "\robocopy_Thunderbird_Local.log"
        Local $sRobocopyCmd = 'robocopy "' & $sThunderbirdLocal & '" "' & $sThunderbirdBackup & '\Local" /E /COPYALL /R:3 /W:1 /LOG:"' & $sLogFile & '" /TEE'
        
        Local $iPID = Run($sRobocopyCmd, "", @SW_HIDE)
        ProcessWaitClose($iPID)
        
        _VerifyRobocopyIntegrity($sThunderbirdLocal, $sThunderbirdBackup & "\Local", "Thunderbird Local", $sLogFile)
        $bFound = True
    EndIf
    
    If Not $bFound Then
        _Log("    Thunderbird non installé ou aucune donnée trouvée", "Sauvegarde/Restauration", "Thunderbird")
        ; Supprimer le dossier vide
        DirRemove($sThunderbirdBackup)
    Else
        _Log("    ✓ Thunderbird sauvegardé (Roaming + Local)", "Sauvegarde/Restauration", "Thunderbird")
    EndIf
EndFunc

; Fonction pour sauvegarder Steam avec Robocopy
Func _BackupSteamRobocopy($sBackupFolder)
    Local $sSteamBackup = $sBackupFolder & "\Steam"
    DirCreate($sSteamBackup)
    
    ; Chemins Steam possibles
    Local $aSteamPaths[4] = [ _
        @ProgramFilesDir & " (x86)\Steam", _
        @ProgramFilesDir & "\Steam", _
        "C:\Steam", _
        @UserProfileDir & "\Steam" _
    ]
    
    Local $bSteamFound = False
    
    For $sSteamPath In $aSteamPaths
        If FileExists($sSteamPath) Then
            _Log("    Steam trouvé: " & $sSteamPath, "Sauvegarde/Restauration", "Steam")
            $bSteamFound = True
            
            ; Sauvegarder les fichiers de configuration Steam
            If FileExists($sSteamPath & "\config") Then
                Local $sLogFile = $sBackupFolder & "\robocopy_Steam_Config.log"
                Local $sRobocopyCmd = 'robocopy "' & $sSteamPath & '\config" "' & $sSteamBackup & '\config" /E /COPYALL /R:3 /W:1 /LOG:"' & $sLogFile & '" /TEE'
                
                Local $iPID = Run($sRobocopyCmd, "", @SW_HIDE)
                ProcessWaitClose($iPID)
                
                _VerifyRobocopyIntegrity($sSteamPath & "\config", $sSteamBackup & "\config", "Config Steam", $sLogFile)
                _Log("    ✓ Configuration Steam sauvegardée", "Sauvegarde/Restauration", "Steam")
            EndIf
            
            ; Sauvegarder les données utilisateur Steam
            If FileExists($sSteamPath & "\userdata") Then
                Local $sLogFile = $sBackupFolder & "\robocopy_Steam_UserData.log"
                Local $sRobocopyCmd = 'robocopy "' & $sSteamPath & '\userdata" "' & $sSteamBackup & '\userdata" /E /COPYALL /R:3 /W:1 /LOG:"' & $sLogFile & '" /TEE'
                
                Local $iPID = Run($sRobocopyCmd, "", @SW_HIDE)
                ProcessWaitClose($iPID)
                
                _VerifyRobocopyIntegrity($sSteamPath & "\userdata", $sSteamBackup & "\userdata", "Données Steam", $sLogFile)
                _Log("    ✓ Données utilisateur Steam sauvegardées", "Sauvegarde/Restauration", "Steam")
            EndIf
            
            ; Sauvegarder les sauvegardes de jeux dans le cloud
            If FileExists($sSteamPath & "\steamapps\common") Then
                ; Créer un fichier listant les jeux installés
                Local $sGamesFile = $sSteamBackup & "\games_list.txt"
                Local $aGames = _FileListToArray($sSteamPath & "\steamapps\common", "*", $FLTA_FOLDERS)
                If IsArray($aGames) Then
                    FileWriteLine($sGamesFile, "=== JEUX STEAM INSTALLÉS ===")
                    For $i = 1 To $aGames[0]
                        FileWriteLine($sGamesFile, $aGames[$i])
                    Next
                    _Log("    ✓ Liste des jeux sauvegardée (" & $aGames[0] & " jeux)", "Sauvegarde/Restauration", "Steam")
                EndIf
            EndIf
            
            ; Sauvegarder les fichiers .acf (App Cache Files)
            If FileExists($sSteamPath & "\steamapps") Then
                Local $aAcfFiles = _FileListToArray($sSteamPath & "\steamapps", "*.acf", $FLTA_FILES)
                If IsArray($aAcfFiles) Then
                    DirCreate($sSteamBackup & "\steamapps")
                    For $i = 1 To $aAcfFiles[0]
                        FileCopy($sSteamPath & "\steamapps\" & $aAcfFiles[$i], $sSteamBackup & "\steamapps\" & $aAcfFiles[$i])
                    Next
                    _Log("    ✓ Fichiers ACF sauvegardés (" & $aAcfFiles[0] & " fichiers)", "Sauvegarde/Restauration", "Steam")
                EndIf
            EndIf
            
            ; Créer un fichier d'information
            Local $sInfoFile = $sSteamBackup & "\steam_info.txt"
            FileWriteLine($sInfoFile, "Chemin Steam: " & $sSteamPath)
            FileWriteLine($sInfoFile, "Date sauvegarde: " & @YEAR & "-" & @MON & "-" & @MDAY & " " & @HOUR & ":" & @MIN)
            
            ExitLoop ; On prend le premier Steam trouvé
        EndIf
    Next
    
    If Not $bSteamFound Then
        _Log("    Steam non installé ou non trouvé", "Sauvegarde/Restauration", "Steam")
        ; Supprimer le dossier vide
        DirRemove($sSteamBackup)
    EndIf
EndFunc

; Fonction pour sauvegarder les favoris des navigateurs
Func _BackupBrowserBookmarks($sBackupFolder)
    Local $sBrowserBackup = $sBackupFolder & "\Favoris_Navigateurs"
    DirCreate($sBrowserBackup)
    
    ; Internet Explorer
    _BackupIEBookmarks($sBrowserBackup)
    
    ; Google Chrome
    _BackupChromeBookmarks($sBrowserBackup)
    
    ; Mozilla Firefox
    _BackupFirefoxBookmarks($sBrowserBackup)
    
    ; Microsoft Edge
    _BackupEdgeBookmarks($sBrowserBackup)
EndFunc

; Sauvegarder les favoris Internet Explorer
Func _BackupIEBookmarks($sBrowserBackup)
    Local $sIEFavorites = @UserProfileDir & "\Favorites"
    If FileExists($sIEFavorites) Then
        _Log("    Sauvegarde favoris Internet Explorer...", "Sauvegarde/Restauration", "Navigateurs")
        DirCopy($sIEFavorites, $sBrowserBackup & "\Internet_Explorer", $FC_OVERWRITE)
        _VerifyFolderCopy($sIEFavorites, $sBrowserBackup & "\Internet_Explorer", "Favoris IE", StringLeft($sBrowserBackup, StringInStr($sBrowserBackup, "\", 0, -1) - 1))
    EndIf
EndFunc

; Sauvegarder les favoris Google Chrome
Func _BackupChromeBookmarks($sBrowserBackup)
    Local $sChromePath = @LocalAppDataDir & "\Google\Chrome\User Data\Default"
    If FileExists($sChromePath & "\Bookmarks") Then
        _Log("    Sauvegarde favoris Google Chrome...", "Sauvegarde/Restauration", "Navigateurs")
        DirCreate($sBrowserBackup & "\Google_Chrome")
        FileCopy($sChromePath & "\Bookmarks", $sBrowserBackup & "\Google_Chrome\Bookmarks")
        FileCopy($sChromePath & "\Bookmarks.bak", $sBrowserBackup & "\Google_Chrome\Bookmarks.bak")
        
        If FileExists($sChromePath & "\Preferences") Then
            FileCopy($sChromePath & "\Preferences", $sBrowserBackup & "\Google_Chrome\Preferences")
        EndIf
    EndIf
EndFunc

; Sauvegarder les favoris Mozilla Firefox
Func _BackupFirefoxBookmarks($sBrowserBackup)
    Local $sFirefoxPath = @AppDataDir & "\Mozilla\Firefox\Profiles"
    If FileExists($sFirefoxPath) Then
        _Log("    Sauvegarde favoris Mozilla Firefox...", "Sauvegarde/Restauration", "Navigateurs")
        
        Local $aProfiles = _FileListToArray($sFirefoxPath, "*", $FLTA_FOLDERS)
        If IsArray($aProfiles) Then
            For $i = 1 To $aProfiles[0]
                Local $sProfilePath = $sFirefoxPath & "\" & $aProfiles[$i]
                If FileExists($sProfilePath & "\places.sqlite") Then
                    DirCreate($sBrowserBackup & "\Mozilla_Firefox")
                    FileCopy($sProfilePath & "\places.sqlite", $sBrowserBackup & "\Mozilla_Firefox\places.sqlite")
                    DirCopy($sProfilePath & "\bookmarkbackups", $sBrowserBackup & "\Mozilla_Firefox\bookmarkbackups", $FC_OVERWRITE)
                    ExitLoop
                EndIf
            Next
        EndIf
    EndIf
EndFunc

; Sauvegarder les favoris Microsoft Edge
Func _BackupEdgeBookmarks($sBrowserBackup)
    Local $sEdgePath = @LocalAppDataDir & "\Microsoft\Edge\User Data\Default"
    If FileExists($sEdgePath & "\Bookmarks") Then
        _Log("    Sauvegarde favoris Microsoft Edge...", "Sauvegarde/Restauration", "Navigateurs")
        DirCreate($sBrowserBackup & "\Microsoft_Edge")
        FileCopy($sEdgePath & "\Bookmarks", $sBrowserBackup & "\Microsoft_Edge\Bookmarks")
        FileCopy($sEdgePath & "\Bookmarks.bak", $sBrowserBackup & "\Microsoft_Edge\Bookmarks.bak")
        
        If FileExists($sEdgePath & "\Preferences") Then
            FileCopy($sEdgePath & "\Preferences", $sBrowserBackup & "\Microsoft_Edge\Preferences")
        EndIf
    EndIf
EndFunc

; Fonction pour sauvegarder les mots de passe Firefox
Func _BackupFirefoxPasswords($sBackupFolder)
    Local $sFirefoxPath = @AppDataDir & "\Mozilla\Firefox\Profiles"
    
    If Not FileExists($sFirefoxPath) Then
        _Log("    Firefox non installé ou aucun profil trouvé", "Sauvegarde/Restauration", "Navigateurs")
        Return
    EndIf
    
    ; Trouver le profil par défaut
    Local $aProfiles = _FileListToArray($sFirefoxPath, "*", $FLTA_FOLDERS)
    If Not IsArray($aProfiles) Then
        _Log("    Aucun profil Firefox trouvé", "Sauvegarde/Restauration", "Navigateurs")
        Return
    EndIf
    
    Local $sFirefoxBackup = $sBackupFolder & "\Firefox_Passwords"
    Local $bHasContent = False
    
    For $i = 1 To $aProfiles[0]
        Local $sProfilePath = $sFirefoxPath & "\" & $aProfiles[$i]
        
        ; Vérifier si les fichiers de mots de passe existent
        If FileExists($sProfilePath & "\logins.json") Or FileExists($sProfilePath & "\key4.db") Or FileExists($sProfilePath & "\key3.db") Then
            ; Créer le dossier seulement si on a des fichiers à sauvegarder
            If Not $bHasContent Then
                DirCreate($sFirefoxBackup)
                $bHasContent = True
            EndIf
            
            ; Sauvegarder les fichiers de mots de passe
            If FileExists($sProfilePath & "\logins.json") Then
                FileCopy($sProfilePath & "\logins.json", $sFirefoxBackup & "\logins.json")
                _Log("    ✓ logins.json sauvegardé", "Sauvegarde/Restauration", "Navigateurs")
            EndIf
            
            If FileExists($sProfilePath & "\key4.db") Then
                FileCopy($sProfilePath & "\key4.db", $sFirefoxBackup & "\key4.db")
                _Log("    ✓ key4.db sauvegardé", "Sauvegarde/Restauration", "Navigateurs")
            EndIf
            
            ; Sauvegarder aussi key3.db si présent (anciennes versions)
            If FileExists($sProfilePath & "\key3.db") Then
                FileCopy($sProfilePath & "\key3.db", $sFirefoxBackup & "\key3.db")
                _Log("    ✓ key3.db sauvegardé", "Sauvegarde/Restauration", "Navigateurs")
            EndIf
            
            ; Sauvegarder le fichier de profil pour la restauration
            If FileExists($sProfilePath & "\prefs.js") Then
                FileCopy($sProfilePath & "\prefs.js", $sFirefoxBackup & "\prefs.js")
                _Log("    ✓ prefs.js sauvegardé", "Sauvegarde/Restauration", "Navigateurs")
            EndIf
            
            ; On prend le premier profil trouvé avec des mots de passe
            ExitLoop
        EndIf
    Next
    
    If Not $bHasContent Then
        _Log("    Aucun fichier de mot de passe Firefox trouvé", "Sauvegarde/Restauration", "Navigateurs")
    EndIf
EndFunc

; Fonction pour sauvegarder Thunderbird
Func _BackupThunderbird($sBackupFolder)
    Local $sThunderbirdBackup = $sBackupFolder & "\Thunderbird"
    DirCreate($sThunderbirdBackup)
    
    ; Chemins Thunderbird
    Local $sThunderbirdRoaming = @AppDataDir & "\Thunderbird"
    Local $sThunderbirdLocal = @LocalAppDataDir & "\Thunderbird"
    
    Local $bFound = False
    
    ; Sauvegarder le dossier Roaming (profils et données)
    If FileExists($sThunderbirdRoaming) Then
        _Log("    Sauvegarde Thunderbird Roaming...", "Sauvegarde/Restauration", "Thunderbird")
        DirCopy($sThunderbirdRoaming, $sThunderbirdBackup & "\Roaming", $FC_OVERWRITE)
        _VerifyFolderCopy($sThunderbirdRoaming, $sThunderbirdBackup & "\Roaming", "Thunderbird Roaming", StringLeft($sThunderbirdBackup, StringInStr($sThunderbirdBackup, "\", 0, -1) - 1))
        $bFound = True
    EndIf
    
    ; Sauvegarder le dossier Local (cache et données temporaires)
    If FileExists($sThunderbirdLocal) Then
        _Log("    Sauvegarde Thunderbird Local...", "Sauvegarde/Restauration", "Thunderbird")
        DirCopy($sThunderbirdLocal, $sThunderbirdBackup & "\Local", $FC_OVERWRITE)
        _VerifyFolderCopy($sThunderbirdLocal, $sThunderbirdBackup & "\Local", "Thunderbird Local", StringLeft($sThunderbirdBackup, StringInStr($sThunderbirdBackup, "\", 0, -1) - 1))
        $bFound = True
    EndIf
    
    If Not $bFound Then
        _Log("    Thunderbird non installé ou aucune donnée trouvée", "Sauvegarde/Restauration", "Thunderbird")
        ; Supprimer le dossier vide
        DirRemove($sThunderbirdBackup)
    Else
        _Log("    ✓ Thunderbird sauvegardé (Roaming + Local)", "Sauvegarde/Restauration", "Thunderbird")
    EndIf
EndFunc

; Fonction pour sauvegarder le fond d'écran
Func _BackupWallpaper($sBackupFolder)
    Local $sWallpaperBackup = $sBackupFolder & "\Fond_Ecran"
    DirCreate($sWallpaperBackup)
    
    ; Récupérer le chemin du fond d'écran actuel depuis le registre
    Local $sWallpaperPath = RegRead("HKEY_CURRENT_USER\Control Panel\Desktop", "Wallpaper")
    
    If $sWallpaperPath <> "" And FileExists($sWallpaperPath) Then
        ; Copier le fichier de fond d'écran
        Local $sFileName = StringRight($sWallpaperPath, StringLen($sWallpaperPath) - StringInStr($sWallpaperPath, "\", 0, -1))
        FileCopy($sWallpaperPath, $sWallpaperBackup & "\" & $sFileName)
        
        ; Sauvegarder les paramètres du fond d'écran
        Local $sWallpaperStyle = RegRead("HKEY_CURRENT_USER\Control Panel\Desktop", "WallpaperStyle")
        Local $sTileWallpaper = RegRead("HKEY_CURRENT_USER\Control Panel\Desktop", "TileWallpaper")
        
        ; Créer un fichier de configuration
        Local $sConfigFile = $sWallpaperBackup & "\wallpaper_config.ini"
        FileWriteLine($sConfigFile, "[Wallpaper]")
        FileWriteLine($sConfigFile, "OriginalPath=" & $sWallpaperPath)
        FileWriteLine($sConfigFile, "FileName=" & $sFileName)
        FileWriteLine($sConfigFile, "WallpaperStyle=" & $sWallpaperStyle)
        FileWriteLine($sConfigFile, "TileWallpaper=" & $sTileWallpaper)
        
        _Log("    ✓ Fond d'écran sauvegardé: " & $sFileName, "Sauvegarde/Restauration", "Système")
    Else
        _Log("    Aucun fond d'écran personnalisé trouvé", "Sauvegarde/Restauration", "Système")
    EndIf
EndFunc

; Fonction pour sauvegarder les imprimantes installées
Func _BackupPrinters($sBackupFolder)
    Local $sPrintersBackup = $sBackupFolder & "\Imprimantes"
    DirCreate($sPrintersBackup)
    
    ; Exporter la liste des imprimantes avec PowerShell
    Local $sPrintersFile = $sPrintersBackup & "\printers_list.txt"
    RunWait('powershell -Command "Get-Printer | Select-Object Name, DriverName, PortName, Shared, Published | Out-File -FilePath \"' & $sPrintersFile & '\" -Encoding UTF8"', "", @SW_HIDE)
    
    ; Alternative avec wmic si PowerShell échoue
    Local $sPrintersFile2 = $sPrintersBackup & "\printers_wmic.txt"
    RunWait('wmic printer list full > "' & $sPrintersFile2 & '"', "", @SW_HIDE)
    
    _Log("    ✓ Liste des imprimantes sauvegardée", "Sauvegarde/Restauration", "Système")
EndFunc

; Fonction pour sauvegarder les dossiers Steam
Func _BackupSteam($sBackupFolder)
    Local $sSteamBackup = $sBackupFolder & "\Steam"
    DirCreate($sSteamBackup)
    
    ; Chemins Steam possibles
    Local $aSteamPaths[4] = [ _
        @ProgramFilesDir & " (x86)\Steam", _
        @ProgramFilesDir & "\Steam", _
        "C:\Steam", _
        @UserProfileDir & "\Steam" _
    ]
    
    Local $bSteamFound = False
    
    For $sSteamPath In $aSteamPaths
        If FileExists($sSteamPath) Then
            _Log("    Steam trouvé: " & $sSteamPath, "Sauvegarde/Restauration", "Steam")
            $bSteamFound = True
            
            ; Sauvegarder les fichiers de configuration Steam
            If FileExists($sSteamPath & "\config") Then
                DirCopy($sSteamPath & "\config", $sSteamBackup & "\config", $FC_OVERWRITE)
                _VerifyFolderCopy($sSteamPath & "\config", $sSteamBackup & "\config", "Config Steam", StringLeft($sSteamBackup, StringInStr($sSteamBackup, "\", 0, -1) - 1))
                _Log("    ✓ Configuration Steam sauvegardée", "Sauvegarde/Restauration", "Steam")
            EndIf
            
            ; Sauvegarder les données utilisateur Steam
            If FileExists($sSteamPath & "\userdata") Then
                DirCopy($sSteamPath & "\userdata", $sSteamBackup & "\userdata", $FC_OVERWRITE)
                _VerifyFolderCopy($sSteamPath & "\userdata", $sSteamBackup & "\userdata", "Données Steam", StringLeft($sSteamBackup, StringInStr($sSteamBackup, "\", 0, -1) - 1))
                _Log("    ✓ Données utilisateur Steam sauvegardées", "Sauvegarde/Restauration", "Steam")
            EndIf
            
            ; Sauvegarder les sauvegardes de jeux dans le cloud
            If FileExists($sSteamPath & "\steamapps\common") Then
                ; Créer un fichier listant les jeux installés
                Local $sGamesFile = $sSteamBackup & "\games_list.txt"
                Local $aGames = _FileListToArray($sSteamPath & "\steamapps\common", "*", $FLTA_FOLDERS)
                If IsArray($aGames) Then
                    FileWriteLine($sGamesFile, "=== JEUX STEAM INSTALLÉS ===")
                    For $i = 1 To $aGames[0]
                        FileWriteLine($sGamesFile, $aGames[$i])
                    Next
                    _Log("    ✓ Liste des jeux sauvegardée (" & $aGames[0] & " jeux)", "Sauvegarde/Restauration", "Steam")
                EndIf
            EndIf
            
            ; Sauvegarder les fichiers .acf (App Cache Files)
            If FileExists($sSteamPath & "\steamapps") Then
                Local $aAcfFiles = _FileListToArray($sSteamPath & "\steamapps", "*.acf", $FLTA_FILES)
                If IsArray($aAcfFiles) Then
                    DirCreate($sSteamBackup & "\steamapps")
                    For $i = 1 To $aAcfFiles[0]
                        FileCopy($sSteamPath & "\steamapps\" & $aAcfFiles[$i], $sSteamBackup & "\steamapps\" & $aAcfFiles[$i])
                    Next
                    _Log("    ✓ Fichiers ACF sauvegardés (" & $aAcfFiles[0] & " fichiers)", "Sauvegarde/Restauration", "Steam")
                EndIf
            EndIf
            
            ; Créer un fichier d'information
            Local $sInfoFile = $sSteamBackup & "\steam_info.txt"
            FileWriteLine($sInfoFile, "Chemin Steam: " & $sSteamPath)
            FileWriteLine($sInfoFile, "Date sauvegarde: " & @YEAR & "-" & @MON & "-" & @MDAY & " " & @HOUR & ":" & @MIN)
            
            ExitLoop ; On prend le premier Steam trouvé
        EndIf
    Next
    
    If Not $bSteamFound Then
        _Log("    Steam non installé ou non trouvé", "Sauvegarde/Restauration", "Steam")
        ; Supprimer le dossier vide
        DirRemove($sSteamBackup)
    EndIf
EndFunc

; Fonction pour sauvegarder les paramètres WiFi
Func _BackupWifi($sBackupFolder)
    Local $sWifiBackup = $sBackupFolder & "\Parametres_WiFi"
    DirCreate($sWifiBackup)
    
    ; Toujours créer les fichiers d'information de base
    Local $bHasContent = False
    
    ; 1. Informations sur les interfaces WiFi
    Local $sInterfacesFile = $sWifiBackup & "\wifi_interfaces.txt"
    RunWait('netsh wlan show interfaces > "' & $sInterfacesFile & '"', "", @SW_HIDE)
    If FileExists($sInterfacesFile) Then
        Local $sContent = FileRead($sInterfacesFile)
        If StringLen($sContent) > 10 Then ; Contenu minimal
            $bHasContent = True
            _Log("    ✓ Informations interfaces WiFi sauvegardées", "Sauvegarde/Restauration", "Système")
        EndIf
    EndIf
    
    ; 2. Liste des profils WiFi
    Local $sNetworksFile = $sWifiBackup & "\wifi_profiles_list.txt"
    RunWait('netsh wlan show profiles > "' & $sNetworksFile & '"', "", @SW_HIDE)
    If FileExists($sNetworksFile) Then
        Local $sContent = FileRead($sNetworksFile)
        If StringLen($sContent) > 10 Then
            $bHasContent = True
            _Log("    ✓ Liste des profils WiFi sauvegardée", "Sauvegarde/Restauration", "Système")
        EndIf
    EndIf
    
    ; 3. Informations sur les pilotes WiFi
    Local $sDriversFile = $sWifiBackup & "\wifi_drivers.txt"
    RunWait('netsh wlan show drivers > "' & $sDriversFile & '"', "", @SW_HIDE)
    If FileExists($sDriversFile) Then
        Local $sContent = FileRead($sDriversFile)
        If StringLen($sContent) > 10 Then
            $bHasContent = True
            _Log("    ✓ Informations pilotes WiFi sauvegardées", "Sauvegarde/Restauration", "Système")
        EndIf
    EndIf
    
    ; 4. Exporter les profils WiFi (avec mots de passe si possible)
    RunWait('netsh wlan export profile folder="' & $sWifiBackup & '" key=clear', "", @SW_HIDE)
    
    ; 5. Vérifier si des profils XML ont été exportés
    Local $aWifiFiles = _FileListToArray($sWifiBackup, "*.xml", $FLTA_FILES)
    If IsArray($aWifiFiles) And $aWifiFiles[0] > 0 Then
        _Log("    ✓ " & $aWifiFiles[0] & " profil(s) WiFi exporté(s)", "Sauvegarde/Restauration", "Système")
        $bHasContent = True
    Else
        _Log("    Aucun profil WiFi exporté (normal si pas de profils sauvegardés)", "Sauvegarde/Restauration", "Système")
    EndIf
    
    ; 6. Informations système réseau supplémentaires
    Local $sNetworkFile = $sWifiBackup & "\network_adapters.txt"
    RunWait('ipconfig /all > "' & $sNetworkFile & '"', "", @SW_HIDE)
    If FileExists($sNetworkFile) Then
        $bHasContent = True
        _Log("    ✓ Configuration réseau sauvegardée", "Sauvegarde/Restauration", "Système")
    EndIf
    
    ; Résultat final
    If $bHasContent Then
        _Log("    ✓ Sauvegarde WiFi terminée", "Sauvegarde/Restauration", "Système")
    Else
        _Log("    Aucune information WiFi récupérée", "Sauvegarde/Restauration", "Système")
        DirRemove($sWifiBackup, 1)
    EndIf
EndFunc

; Fonction pour sauvegarder les paramètres système
Func _BackupSystemSettings($sBackupFolder)
    Local $sSystemBackup = $sBackupFolder & "\Parametres_Systeme"
    DirCreate($sSystemBackup)
    
    ; Exporter les clés de registre importantes
    _Log("    Export des clés de registre...", "Sauvegarde/Restauration", "Système")
    RunWait('reg export "HKEY_CURRENT_USER\Software\Microsoft\Windows\CurrentVersion\Explorer" "' & $sSystemBackup & '\Explorer_Settings.reg"', "", @SW_HIDE)
    RunWait('reg export "HKEY_CURRENT_USER\Control Panel\Desktop" "' & $sSystemBackup & '\Desktop_Settings.reg"', "", @SW_HIDE)
    
    ; Créer un rapport système
    _Log("    Création du rapport système...", "Sauvegarde/Restauration", "Système")
    RunWait('systeminfo > "' & $sSystemBackup & '\system_info.txt"', "", @SW_HIDE)
    RunWait('driverquery > "' & $sSystemBackup & '\drivers_list.txt"', "", @SW_HIDE)
EndFunc

; ===============================================================================
; FONCTIONS DE RESTAURATION
; ===============================================================================

; Fonction pour restaurer les fichiers utilisateur granulaires
Func _RestoreUserFilesGranular($sRestoreFolder)
    Local $sUserRestore = $sRestoreFolder & "\Fichiers_Utilisateur"
    
    If Not FileExists($sUserRestore) Then
        _Log("    Aucun fichier utilisateur à restaurer", "Sauvegarde/Restauration", "Fichiers")
        Return
    EndIf
    
    ; Restaurer les dossiers utilisateur disponibles
    Local $aUserFolders[6] = ["Desktop", "Documents", "Pictures", "Music", "Videos", "Downloads"]
    
    For $sFolder In $aUserFolders
        Local $sSourcePath = $sUserRestore & "\" & $sFolder
        Local $sDestPath = @UserProfileDir & "\" & $sFolder
        
        If FileExists($sSourcePath) Then
            _Log("    Restauration: " & $sFolder, "Sauvegarde/Restauration", "Fichiers")
            DirCopy($sSourcePath, $sDestPath, $FC_OVERWRITE)
        EndIf
    Next
EndFunc

; Fonction pour restaurer les favoris des navigateurs
Func _RestoreBrowserBookmarks($sRestoreFolder)
    Local $sBrowserRestore = $sRestoreFolder & "\Favoris_Navigateurs"
    
    If Not FileExists($sBrowserRestore) Then
        _Log("    Aucun favori de navigateur à restaurer", "Sauvegarde/Restauration", "Navigateurs")
        Return
    EndIf
    
    ; Restaurer Internet Explorer
    If FileExists($sBrowserRestore & "\Internet_Explorer") Then
        _Log("    Restauration favoris Internet Explorer...", "Sauvegarde/Restauration", "Navigateurs")
        DirCopy($sBrowserRestore & "\Internet_Explorer", @UserProfileDir & "\Favorites", $FC_OVERWRITE)
    EndIf
    
    ; Restaurer Google Chrome
    If FileExists($sBrowserRestore & "\Google_Chrome") Then
        _Log("    Restauration favoris Google Chrome...", "Sauvegarde/Restauration", "Navigateurs")
        Local $sChromePath = @LocalAppDataDir & "\Google\Chrome\User Data\Default"
        DirCreate($sChromePath)
        FileCopy($sBrowserRestore & "\Google_Chrome\Bookmarks", $sChromePath & "\Bookmarks", $FC_OVERWRITE)
        FileCopy($sBrowserRestore & "\Google_Chrome\Preferences", $sChromePath & "\Preferences", $FC_OVERWRITE)
    EndIf
    
    ; Restaurer Mozilla Firefox
    If FileExists($sBrowserRestore & "\Mozilla_Firefox") Then
        _Log("    Restauration favoris Mozilla Firefox...", "Sauvegarde/Restauration", "Navigateurs")
        Local $sFirefoxPath = @AppDataDir & "\Mozilla\Firefox\Profiles"
        If FileExists($sFirefoxPath) Then
            Local $aProfiles = _FileListToArray($sFirefoxPath, "*", $FLTA_FOLDERS)
            If IsArray($aProfiles) Then
                For $i = 1 To $aProfiles[0]
                    Local $sProfilePath = $sFirefoxPath & "\" & $aProfiles[$i]
                    FileCopy($sBrowserRestore & "\Mozilla_Firefox\places.sqlite", $sProfilePath & "\places.sqlite", $FC_OVERWRITE)
                    DirCopy($sBrowserRestore & "\Mozilla_Firefox\bookmarkbackups", $sProfilePath & "\bookmarkbackups", $FC_OVERWRITE)
                    ExitLoop
                Next
            EndIf
        EndIf
    EndIf
    
    ; Restaurer Microsoft Edge
    If FileExists($sBrowserRestore & "\Microsoft_Edge") Then
        _Log("    Restauration favoris Microsoft Edge...", "Sauvegarde/Restauration", "Navigateurs")
        Local $sEdgePath = @LocalAppDataDir & "\Microsoft\Edge\User Data\Default"
        DirCreate($sEdgePath)
        FileCopy($sBrowserRestore & "\Microsoft_Edge\Bookmarks", $sEdgePath & "\Bookmarks", $FC_OVERWRITE)
        FileCopy($sBrowserRestore & "\Microsoft_Edge\Preferences", $sEdgePath & "\Preferences", $FC_OVERWRITE)
    EndIf
EndFunc

; Fonction pour restaurer les mots de passe Firefox
Func _RestoreFirefoxPasswords($sRestoreFolder)
    Local $sFirefoxRestore = $sRestoreFolder & "\Firefox_Passwords"
    
    If Not FileExists($sFirefoxRestore) Then
        _Log("    Aucun mot de passe Firefox à restaurer", "Sauvegarde/Restauration", "Navigateurs")
        Return
    EndIf
    
    Local $sFirefoxPath = @AppDataDir & "\Mozilla\Firefox\Profiles"
    If Not FileExists($sFirefoxPath) Then
        _Log("    Firefox non installé - impossible de restaurer les mots de passe", "Sauvegarde/Restauration", "Navigateurs")
        Return
    EndIf
    
    ; Trouver le profil par défaut
    Local $aProfiles = _FileListToArray($sFirefoxPath, "*", $FLTA_FOLDERS)
    If IsArray($aProfiles) Then
        For $i = 1 To $aProfiles[0]
            Local $sProfilePath = $sFirefoxPath & "\" & $aProfiles[$i]
            
            ; Restaurer les fichiers de mots de passe
            If FileExists($sFirefoxRestore & "\logins.json") Then
                FileCopy($sFirefoxRestore & "\logins.json", $sProfilePath & "\logins.json", $FC_OVERWRITE)
                _Log("    ✓ logins.json restauré", "Sauvegarde/Restauration", "Navigateurs")
            EndIf
            
            If FileExists($sFirefoxRestore & "\key4.db") Then
                FileCopy($sFirefoxRestore & "\key4.db", $sProfilePath & "\key4.db", $FC_OVERWRITE)
                _Log("    ✓ key4.db restauré", "Sauvegarde/Restauration", "Navigateurs")
            EndIf
            
            If FileExists($sFirefoxRestore & "\key3.db") Then
                FileCopy($sFirefoxRestore & "\key3.db", $sProfilePath & "\key3.db", $FC_OVERWRITE)
                _Log("    ✓ key3.db restauré", "Sauvegarde/Restauration", "Navigateurs")
            EndIf
            
            If FileExists($sFirefoxRestore & "\prefs.js") Then
                FileCopy($sFirefoxRestore & "\prefs.js", $sProfilePath & "\prefs.js", $FC_OVERWRITE)
                _Log("    ✓ prefs.js restauré", "Sauvegarde/Restauration", "Navigateurs")
            EndIf
            
            ; On restaure dans le premier profil trouvé
            ExitLoop
        Next
    EndIf
EndFunc

; Fonction pour restaurer Thunderbird
Func _RestoreThunderbird($sRestoreFolder)
    Local $sThunderbirdRestore = $sRestoreFolder & "\Thunderbird"
    
    If Not FileExists($sThunderbirdRestore) Then
        _Log("    Aucune donnée Thunderbird à restaurer", "Sauvegarde/Restauration", "Thunderbird")
        Return
    EndIf
    
    ; Restaurer le dossier Roaming
    If FileExists($sThunderbirdRestore & "\Roaming") Then
        _Log("    Restauration Thunderbird Roaming...", "Sauvegarde/Restauration", "Thunderbird")
        DirCopy($sThunderbirdRestore & "\Roaming", @AppDataDir & "\Thunderbird", $FC_OVERWRITE)
    EndIf
    
    ; Restaurer le dossier Local
    If FileExists($sThunderbirdRestore & "\Local") Then
        _Log("    Restauration Thunderbird Local...", "Sauvegarde/Restauration", "Thunderbird")
        DirCopy($sThunderbirdRestore & "\Local", @LocalAppDataDir & "\Thunderbird", $FC_OVERWRITE)
    EndIf
    
    _Log("    ✓ Thunderbird restauré", "Sauvegarde/Restauration", "Thunderbird")
EndFunc

; Fonction pour restaurer le fond d'écran
Func _RestoreWallpaper($sRestoreFolder)
    Local $sWallpaperRestore = $sRestoreFolder & "\Fond_Ecran"
    
    If Not FileExists($sWallpaperRestore) Then
        _Log("    Aucun fond d'écran à restaurer", "Sauvegarde/Restauration", "Système")
        Return
    EndIf
    
    Local $sConfigFile = $sWallpaperRestore & "\wallpaper_config.ini"
    If FileExists($sConfigFile) Then
        Local $sFileName = IniRead($sConfigFile, "Wallpaper", "FileName", "")
        Local $sWallpaperStyle = IniRead($sConfigFile, "Wallpaper", "WallpaperStyle", "")
        Local $sTileWallpaper = IniRead($sConfigFile, "Wallpaper", "TileWallpaper", "")
        
        If $sFileName <> "" And FileExists($sWallpaperRestore & "\" & $sFileName) Then
            ; Copier le fond d'écran vers un dossier temporaire
            Local $sNewPath = @TempDir & "\" & $sFileName
            FileCopy($sWallpaperRestore & "\" & $sFileName, $sNewPath, $FC_OVERWRITE)
            
            ; Appliquer le fond d'écran
            RegWrite("HKEY_CURRENT_USER\Control Panel\Desktop", "Wallpaper", "REG_SZ", $sNewPath)
            If $sWallpaperStyle <> "" Then RegWrite("HKEY_CURRENT_USER\Control Panel\Desktop", "WallpaperStyle", "REG_SZ", $sWallpaperStyle)
            If $sTileWallpaper <> "" Then RegWrite("HKEY_CURRENT_USER\Control Panel\Desktop", "TileWallpaper", "REG_SZ", $sTileWallpaper)
            
            ; Actualiser le bureau
            RunWait('rundll32.exe user32.dll,UpdatePerUserSystemParameters', "", @SW_HIDE)
            
            _Log("    ✓ Fond d'écran restauré: " & $sFileName, "Sauvegarde/Restauration", "Système")
        EndIf
    EndIf
EndFunc

; Fonction pour restaurer les imprimantes
Func _RestorePrinters($sRestoreFolder)
    Local $sPrintersRestore = $sRestoreFolder & "\Imprimantes"
    
    If Not FileExists($sPrintersRestore) Then
        _Log("    Aucune imprimante à restaurer", "Sauvegarde/Restauration", "Système")
        Return
    EndIf
    
    _Log("    ATTENTION: La restauration des imprimantes nécessite des droits administrateur", "Sauvegarde/Restauration", "Attention")
    _Log("    Les pilotes doivent être installés manuellement si nécessaire", "Sauvegarde/Restauration", "Info")
    
    ; Importer la configuration du registre (nécessite des droits admin)
    If FileExists($sPrintersRestore & "\printers_registry.reg") Then
        RunWait('reg import "' & $sPrintersRestore & '\printers_registry.reg"', "", @SW_HIDE)
        _Log("    ✓ Configuration registre importée", "Sauvegarde/Restauration", "Système")
    EndIf
    
    _Log("    ℹ Consultez les fichiers suivants pour la restauration manuelle:", "Sauvegarde/Restauration", "Info")
    _Log("      - printers_list.txt (liste des imprimantes)", "Sauvegarde/Restauration", "Info")
    _Log("      - printer_drivers.txt (pilotes requis)", "Sauvegarde/Restauration", "Info")
EndFunc

; Fonction pour restaurer Steam
Func _RestoreSteam($sRestoreFolder)
    Local $sSteamRestore = $sRestoreFolder & "\Steam"
    
    If Not FileExists($sSteamRestore) Then
        _Log("    Aucune donnée Steam à restaurer", "Sauvegarde/Restauration", "Steam")
        Return
    EndIf
    
    ; Trouver l'installation Steam actuelle
    Local $aSteamPaths[4] = [ _
        @ProgramFilesDir & " (x86)\Steam", _
        @ProgramFilesDir & "\Steam", _
        "C:\Steam", _
        @UserProfileDir & "\Steam" _
    ]
    
    Local $sSteamPath = ""
    For $sPath In $aSteamPaths
        If FileExists($sPath) Then
            $sSteamPath = $sPath
            ExitLoop
        EndIf
    Next
    
    If $sSteamPath = "" Then
        _Log("    Steam non installé, impossible de restaurer", "Sauvegarde/Restauration", "Steam")
        Return
    EndIf
    
    _Log("    Steam trouvé: " & $sSteamPath, "Sauvegarde/Restauration", "Steam")
    
    ; Restaurer la configuration
    If FileExists($sSteamRestore & "\config") Then
        DirCopy($sSteamRestore & "\config", $sSteamPath & "\config", $FC_OVERWRITE)
        _Log("    ✓ Configuration Steam restaurée", "Sauvegarde/Restauration", "Steam")
    EndIf
    
    ; Restaurer les données utilisateur
    If FileExists($sSteamRestore & "\userdata") Then
        DirCopy($sSteamRestore & "\userdata", $sSteamPath & "\userdata", $FC_OVERWRITE)
        _Log("    ✓ Données utilisateur Steam restaurées", "Sauvegarde/Restauration", "Steam")
    EndIf
    
    ; Restaurer les fichiers ACF
    If FileExists($sSteamRestore & "\steamapps") Then
        Local $aAcfFiles = _FileListToArray($sSteamRestore & "\steamapps", "*.acf", $FLTA_FILES)
        If IsArray($aAcfFiles) Then
            For $i = 1 To $aAcfFiles[0]
                FileCopy($sSteamRestore & "\steamapps\" & $aAcfFiles[$i], $sSteamPath & "\steamapps\" & $aAcfFiles[$i], $FC_OVERWRITE)
            Next
            _Log("    ✓ Fichiers ACF restaurés (" & $aAcfFiles[0] & " fichiers)", "Sauvegarde/Restauration", "Steam")
        EndIf
    EndIf
    
    _Log("    ℹ Redémarrez Steam pour appliquer les changements", "Sauvegarde/Restauration", "Info")
EndFunc

; ===============================================================================
; FONCTIONS DE RESTAURATION SÉLECTIVE
; ===============================================================================

; Fonction pour afficher la popup de sélection des éléments à restaurer
Func _ShowRestoreSelectionDialog($sBackupPath, $sBackupName)
    ; Analyser le contenu de la sauvegarde
    Local $aAvailableItems = _AnalyzeBackupContent($sBackupPath)
    If Not IsArray($aAvailableItems) Or UBound($aAvailableItems) = 0 Then
        MsgBox($MB_ICONWARNING, "Attention", "Aucun élément restaurable trouvé dans cette sauvegarde.")
        Return False
    EndIf
    
    ; Créer la fenêtre de sélection
    Local $hRestoreGUI = GUICreate("Sélection des éléments à restaurer - " & $sBackupName, 500, 400, -1, -1, $WS_OVERLAPPEDWINDOW, $WS_EX_TOPMOST)
    
    ; En-tête
    GUICtrlCreateLabel("Choisissez les éléments à restaurer :", 20, 20, 460, 20)
    GUICtrlSetFont(-1, 10, 600)
    
    ; Zone de checkboxes
    Local $aCheckboxes[UBound($aAvailableItems)]
    Local $iYPos = 50
    
    For $i = 0 To UBound($aAvailableItems) - 1
        $aCheckboxes[$i] = GUICtrlCreateCheckbox($aAvailableItems[$i][1], 30, $iYPos, 440, 20)
        GUICtrlSetState($aCheckboxes[$i], $GUI_CHECKED) ; Tout sélectionné par défaut
        $iYPos += 25
    Next
    
    ; Boutons de sélection rapide
    Local $btnSelectAll = GUICtrlCreateButton("Tout sélectionner", 30, $iYPos + 10, 100, 25)
    Local $btnDeselectAll = GUICtrlCreateButton("Tout désélectionner", 140, $iYPos + 10, 120, 25)
    
    ; Boutons de validation
    Local $btnOK = GUICtrlCreateButton("Restaurer", 300, $iYPos + 50, 80, 30)
    GUICtrlSetFont($btnOK, 10, 600)
    GUICtrlSetBkColor($btnOK, 0x90EE90)
    
    Local $btnCancel = GUICtrlCreateButton("Annuler", 390, $iYPos + 50, 80, 30)
    
    GUICtrlSetOnEvent($btnSelectAll, "_SelectAllRestore")
    GUICtrlSetOnEvent($btnDeselectAll, "_DeselectAllRestore")
    GUICtrlSetOnEvent($btnOK, "_ValidateRestoreSelection")
    GUICtrlSetOnEvent($btnCancel, "_CancelRestoreSelection")
    
    ; Variables globales temporaires pour la popup
    Global $g_aRestoreCheckboxes = $aCheckboxes
    Global $g_aRestoreItems = $aAvailableItems
    Global $g_bRestoreValidated = False
    Global $g_aSelectedRestoreItems[0]
    
    GUISetState(@SW_SHOW, $hRestoreGUI)
    
    ; Boucle d'attente
    While GUIGetMsg() <> $GUI_EVENT_CLOSE And Not $g_bRestoreValidated
        Sleep(10)
    WEnd
    
    GUIDelete($hRestoreGUI)
    
    ; Retourner les éléments sélectionnés
    If $g_bRestoreValidated Then
        Return $g_aSelectedRestoreItems
    Else
        Return False
    EndIf
EndFunc

; Fonction pour analyser le contenu d'une sauvegarde
Func _AnalyzeBackupContent($sBackupPath)
    Local $aItems[0]
    Local $iCount = 0
    
    ; Vérifier chaque type d'élément possible
    Local $aElementsToCheck[15][3] = [ _
        ["Favoris_Navigateurs", "Favoris des navigateurs", "bookmarks"], _
        ["Firefox_Passwords", "Mots de passe Firefox", "firefox_passwords"], _
        ["Thunderbird", "Thunderbird (emails)", "thunderbird"], _
        ["Fond_Ecran", "Fond d'écran", "wallpaper"], _
        ["Imprimantes", "Imprimantes", "printers"], _
        ["Steam", "Steam (configuration et données)", "steam"], _
        ["Parametres_WiFi", "Paramètres WiFi", "wifi"], _
        ["Parametres_Systeme", "Paramètres système", "system"], _
        ["applications_installees.txt", "Liste des applications installées", "apps"], _
        ["Fichiers_Utilisateur\Desktop", "Bureau", "desktop"], _
        ["Fichiers_Utilisateur\Documents", "Documents", "documents"], _
        ["Fichiers_Utilisateur\Pictures", "Images", "pictures"], _
        ["Fichiers_Utilisateur\Music", "Musique", "music"], _
        ["Fichiers_Utilisateur\Videos", "Vidéos", "videos"], _
        ["Fichiers_Utilisateur\Downloads", "Téléchargements", "downloads"] _
    ]
    
    For $i = 0 To UBound($aElementsToCheck) - 1
        Local $sElementPath = $sBackupPath & "\" & $aElementsToCheck[$i][0]
        If FileExists($sElementPath) Then
            ReDim $aItems[$iCount + 1][3]
            $aItems[$iCount][0] = $aElementsToCheck[$i][2] ; ID
            $aItems[$iCount][1] = $aElementsToCheck[$i][1] ; Description
            $aItems[$iCount][2] = $sElementPath ; Chemin
            $iCount += 1
        EndIf
    Next
    
    Return $aItems
EndFunc

; Fonctions pour les boutons de la popup
Func _SelectAllRestore()
    For $i = 0 To UBound($g_aRestoreCheckboxes) - 1
        GUICtrlSetState($g_aRestoreCheckboxes[$i], $GUI_CHECKED)
    Next
EndFunc

Func _DeselectAllRestore()
    For $i = 0 To UBound($g_aRestoreCheckboxes) - 1
        GUICtrlSetState($g_aRestoreCheckboxes[$i], $GUI_UNCHECKED)
    Next
EndFunc

Func _ValidateRestoreSelection()
    ; Collecter les éléments sélectionnés
    ReDim $g_aSelectedRestoreItems[0]
    Local $iSelectedCount = 0
    
    For $i = 0 To UBound($g_aRestoreCheckboxes) - 1
        If GUICtrlRead($g_aRestoreCheckboxes[$i]) = $GUI_CHECKED Then
            ReDim $g_aSelectedRestoreItems[$iSelectedCount + 1][3]
            $g_aSelectedRestoreItems[$iSelectedCount][0] = $g_aRestoreItems[$i][0] ; ID
            $g_aSelectedRestoreItems[$iSelectedCount][1] = $g_aRestoreItems[$i][1] ; Description
            $g_aSelectedRestoreItems[$iSelectedCount][2] = $g_aRestoreItems[$i][2] ; Chemin
            $iSelectedCount += 1
        EndIf
    Next
    
    If $iSelectedCount = 0 Then
        MsgBox($MB_ICONWARNING, "Attention", "Veuillez sélectionner au moins un élément à restaurer.")
        Return
    EndIf
    
    $g_bRestoreValidated = True
EndFunc

Func _CancelRestoreSelection()
    $g_bRestoreValidated = False
EndFunc

; Fonction pour effectuer une restauration sélective
Func _PerformSelectiveRestore($sBackupPath, $aSelectedItems)
    _Log("Restauration sélective depuis: " & $sBackupPath, "Sauvegarde/Restauration", "Info")
    _Log("Éléments à restaurer: " & UBound($aSelectedItems), "Sauvegarde/Restauration", "Info")
    
    For $i = 0 To UBound($aSelectedItems) - 1
        Local $sItemID = $aSelectedItems[$i][0]
        Local $sItemDesc = $aSelectedItems[$i][1]
        Local $sItemPath = $aSelectedItems[$i][2]
        
        _Log("- Restauration: " & $sItemDesc, "Sauvegarde/Restauration", "Info")
        
        ; Appeler la fonction de restauration appropriée selon l'ID
        Switch $sItemID
            Case "bookmarks"
                _RestoreBrowserBookmarks($sBackupPath)
            Case "firefox_passwords"
                _RestoreFirefoxPasswords($sBackupPath)
            Case "thunderbird"
                _RestoreThunderbird($sBackupPath)
            Case "wallpaper"
                _RestoreWallpaper($sBackupPath)
            Case "printers"
                _RestorePrinters($sBackupPath)
            Case "steam"
                _RestoreSteam($sBackupPath)
            Case "wifi"
                _RestoreWifi($sBackupPath)
            Case "system"
                _RestoreSystemSettings($sBackupPath)
            Case "apps"
                _Log("  INFO: Liste des applications disponible dans: applications_installees.txt", "Sauvegarde/Restauration", "Info")
            Case "desktop"
                _RestoreSpecificUserFolder($sBackupPath, "Desktop", "Bureau")
            Case "documents"
                _RestoreSpecificUserFolder($sBackupPath, "Documents", "Documents")
            Case "pictures"
                _RestoreSpecificUserFolder($sBackupPath, "Pictures", "Images")
            Case "music"
                _RestoreSpecificUserFolder($sBackupPath, "Music", "Musique")
            Case "videos"
                _RestoreSpecificUserFolder($sBackupPath, "Videos", "Vidéos")
            Case "downloads"
                _RestoreSpecificUserFolder($sBackupPath, "Downloads", "Téléchargements")
        EndSwitch
        
        _Log("  OK: " & $sItemDesc & " restauré", "Sauvegarde/Restauration", "Succès")
    Next
    
    _Log("=== RESTAURATION SÉLECTIVE TERMINÉE ===", "Sauvegarde/Restauration", "Info")
    _Log("Un redémarrage peut être nécessaire pour appliquer tous les changements", "Sauvegarde/Restauration", "Info")
EndFunc

; Fonction pour restaurer les paramètres WiFi
Func _RestoreWifi($sRestoreFolder)
    Local $sWifiRestore = $sRestoreFolder & "\Parametres_WiFi"
    
    If Not FileExists($sWifiRestore) Then
        _Log("    Aucun paramètre WiFi à restaurer", "Sauvegarde/Restauration", "Système")
        Return
    EndIf
    
    _Log("    ATTENTION: Nécessite des droits administrateur", "Sauvegarde/Restauration", "Attention")
    
    ; Importer tous les profils WiFi XML
    Local $aWifiFiles = _FileListToArray($sWifiRestore, "*.xml", $FLTA_FILES)
    If IsArray($aWifiFiles) Then
        For $i = 1 To $aWifiFiles[0]
            RunWait('netsh wlan add profile filename="' & $sWifiRestore & '\' & $aWifiFiles[$i] & '"', "", @SW_HIDE)
        Next
        _Log("    ✓ " & $aWifiFiles[0] & " profil(s) WiFi restauré(s)", "Sauvegarde/Restauration", "Système")
    Else
        _Log("    Aucun profil WiFi à restaurer", "Sauvegarde/Restauration", "Système")
    EndIf
    
    _Log("    ℹ Les mots de passe WiFi peuvent nécessiter une saisie manuelle", "Sauvegarde/Restauration", "Info")
EndFunc

; Fonction pour restaurer les paramètres système
Func _RestoreSystemSettings($sRestoreFolder)
    Local $sSystemRestore = $sRestoreFolder & "\Parametres_Systeme"
    
    If Not FileExists($sSystemRestore) Then
        _Log("    Aucun paramètre système à restaurer", "Sauvegarde/Restauration", "Système")
        Return
    EndIf
    
    ; Importer les clés de registre
    _Log("    Import des clés de registre...", "Sauvegarde/Restauration", "Système")
    If FileExists($sSystemRestore & "\Explorer_Settings.reg") Then
        RunWait('reg import "' & $sSystemRestore & '\Explorer_Settings.reg"', "", @SW_HIDE)
    EndIf
    If FileExists($sSystemRestore & "\Desktop_Settings.reg") Then
        RunWait('reg import "' & $sSystemRestore & '\Desktop_Settings.reg"', "", @SW_HIDE)
    EndIf
EndFunc

; Fonction pour restaurer un dossier utilisateur spécifique
Func _RestoreSpecificUserFolder($sBackupPath, $sFolderName, $sDisplayName)
    Local $sSourcePath = $sBackupPath & "\Fichiers_Utilisateur\" & $sFolderName
    Local $sDestPath = @UserProfileDir & "\" & $sFolderName
    
    If Not FileExists($sSourcePath) Then
        _Log("    Aucun dossier " & $sDisplayName & " à restaurer", "Sauvegarde/Restauration", "Fichiers")
        Return
    EndIf
    
    _Log("    Restauration: " & $sDisplayName & "...", "Sauvegarde/Restauration", "Fichiers")
    
    ; Créer le dossier de destination s'il n'existe pas
    If Not FileExists($sDestPath) Then
        DirCreate($sDestPath)
    EndIf
    
    ; Copier le contenu du dossier
    DirCopy($sSourcePath, $sDestPath, $FC_OVERWRITE)
    
    ; Vérifier la restauration
    Local $iSourceFiles = _CountFilesRecursive($sSourcePath)
    Local $iDestFiles = _CountFilesRecursive($sDestPath)
    
    If $iSourceFiles = $iDestFiles Then
        _Log("      OK: " & $sDisplayName & " restauré (" & $iDestFiles & " fichiers)", "Sauvegarde/Restauration", "Fichiers")
    Else
        _Log("      ERREUR: " & $sDisplayName & " partiellement restauré (" & $iDestFiles & "/" & $iSourceFiles & " fichiers)", "Sauvegarde/Restauration", "Erreur")
    EndIf
EndFunc