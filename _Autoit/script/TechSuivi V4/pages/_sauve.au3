#cs ----------------------------------------------------------------------------

 AutoIt Version: 3.3.16.1
 Author:         TechSuivi

 Script Function:
	Page Sauvegarde pour TechSuivi V4
	Adaptée depuis _sauve-restore.au3

#ce ----------------------------------------------------------------------------

; Variables globales pour la sauvegarde
Global $sBackupPath = ""
Global $bOperationInProgress = False

; Variables pour les contrôles de l'interface
Global $btnSelectPath, $btnBackup, $btnListApps
Global $btnSelectAllUser, $btnDeselectAllUser
Global $lblDestination, $progressBar, $lblProgress
Global $checkUserFiles, $checkBrowsers, $checkSystem, $checkApps
Global $checkDesktop, $checkDocuments, $checkPictures, $checkMusic, $checkVideos, $checkDownloads
Global $checkThunderbird, $checkFirefoxPasswords
Global $checkWallpaper, $checkPrinters, $checkSteam, $checkWifi

; Fonction principale pour créer l'interface Sauvegarde
Func _sauve()
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
    GUICtrlCreateGroup("Actions de sauvegarde", 30, 370, 740, 100)
    $btnBackup = GUICtrlCreateButton("DÉMARRER LA SAUVEGARDE", 50, 400, 200, 40)
    GUICtrlSetFont($btnBackup, 10, 600)
    GUICtrlSetBkColor($btnBackup, 0x90EE90)
    GUICtrlSetOnEvent($btnBackup, "_StartBackupProcess")

    $btnListApps = GUICtrlCreateButton("Lister Applications", 270, 400, 100, 40)
    GUICtrlSetOnEvent($btnListApps, "_ListApplications")
    GUICtrlCreateGroup("", -99, -99, 1, 1)

    ; Groupe Progress Bar
    GUICtrlCreateGroup("Progression", 30, 490, 740, 60)
    $lblProgress = GUICtrlCreateLabel("Prêt pour la sauvegarde", 50, 510, 300, 20)
    $progressBar = GUICtrlCreateProgress(50, 530, 680, 15)
    GUICtrlCreateGroup("", -99, -99, 1, 1)

    ; Initialisation
    _InitializeSauve()
EndFunc

; Initialisation de l'interface
Func _InitializeSauve()


    ; Vérifier si un chemin de sauvegarde par défaut existe
    Local $sDefaultPath = RegRead("HKEY_CURRENT_USER\Software\TechSuivi", "DefaultBackupPath")
    If $sDefaultPath <> "" And FileExists($sDefaultPath) Then
        $sBackupPath = $sDefaultPath
        _UpdateDestinationLabel()
    EndIf
EndFunc

; Sélection du chemin de sauvegarde
Func _SelectBackupPath()
    Local $sNewPath = FileSelectFolder("Choisir le dossier de destination (disque externe recommandé)", $sBackupPath)
    If $sNewPath <> "" Then
        $sBackupPath = $sNewPath
        _UpdateDestinationLabel()
        _Log("- Nouveau chemin de sauvegarde: " & $sBackupPath, "Sauvegarde", "Config")

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
        _Log("ERREUR: Veuillez d'abord choisir une destination de sauvegarde", "Sauvegarde", "Erreur")
        Return
    EndIf

    If $bOperationInProgress Then
        _Log("ERREUR: Une opération est déjà en cours", "Sauvegarde", "Erreur")
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
        _Log("ERREUR: Veuillez sélectionner au moins une option de sauvegarde", "Sauvegarde", "Erreur")
        Return
    EndIf

    ; Démarrer la sauvegarde
    $bOperationInProgress = True
    GUICtrlSetState($btnBackup, $GUI_DISABLE)

    _Log("=== DÉBUT DE LA SAUVEGARDE ===", "Sauvegarde", "Info")
    _PerformAdvancedBackup($bDesktop, $bDocuments, $bPictures, $bMusic, $bVideos, $bDownloads, $bBrowsers, $bFirefoxPasswords, $bThunderbird, $bWallpaper, $bPrinters, $bSteam, $bWifi, $bSystem, $bApps)

    $bOperationInProgress = False
    GUICtrlSetState($btnBackup, $GUI_ENABLE)
EndFunc

; Processus de sauvegarde avancé avec progress bar
Func _PerformAdvancedBackup($bDesktop, $bDocuments, $bPictures, $bMusic, $bVideos, $bDownloads, $bBrowsers, $bFirefoxPasswords, $bThunderbird, $bWallpaper, $bPrinters, $bSteam, $bWifi, $bSystem, $bApps)
    _Log("Préparation de la sauvegarde...", "Sauvegarde", "Info")
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
        _Log("- Sauvegarde de la liste des applications...", "Sauvegarde", "Applications")
        _ListInstalledApplications($sBackupFolder)
        _Log("  OK: Liste des applications sauvegardée", "Sauvegarde", "Applications")
    EndIf

    ; Sauvegarde des fichiers utilisateur
    If $bUserFiles Then
        _Log("- Sauvegarde des fichiers utilisateur...", "Sauvegarde", "Fichiers")
        _BackupUserFilesGranularRobocopy($sBackupFolder, $bDesktop, $bDocuments, $bPictures, $bMusic, $bVideos, $bDownloads, $iCurrentStep, $iTotalSteps)
        $iCurrentStep += 6 ; Ajuster selon le nombre de dossiers traités
        _Log("  OK: Fichiers utilisateur sauvegardés", "Sauvegarde", "Fichiers")
    EndIf

    ; Sauvegarde des favoris
    If $bBrowsers Then
        $iCurrentStep += 1
        _UpdateProgress($iCurrentStep, $iTotalSteps, "Sauvegarde des favoris des navigateurs...")
        _Log("- Sauvegarde des favoris des navigateurs...", "Sauvegarde", "Navigateurs")
        _BackupBrowserBookmarks($sBackupFolder)
        _Log("  OK: Favoris des navigateurs sauvegardés", "Sauvegarde", "Navigateurs")
    EndIf

    ; Sauvegarde des mots de passe Firefox
    If $bFirefoxPasswords Then
        $iCurrentStep += 1
        _UpdateProgress($iCurrentStep, $iTotalSteps, "Sauvegarde des mots de passe Firefox...")
        _Log("- Sauvegarde des mots de passe Firefox...", "Sauvegarde", "Navigateurs")
        _BackupFirefoxPasswords($sBackupFolder)
        _Log("  OK: Mots de passe Firefox sauvegardés", "Sauvegarde", "Navigateurs")
    EndIf

    ; Sauvegarde de Thunderbird
    If $bThunderbird Then
        $iCurrentStep += 1
        _UpdateProgress($iCurrentStep, $iTotalSteps, "Sauvegarde de Thunderbird...")
        _Log("- Sauvegarde de Thunderbird...", "Sauvegarde", "Thunderbird")
        _BackupThunderbirdRobocopy($sBackupFolder)
        _Log("  OK: Thunderbird sauvegardé", "Sauvegarde", "Thunderbird")
    EndIf

    ; Sauvegarde du fond d'écran
    If $bWallpaper Then
        $iCurrentStep += 1
        _UpdateProgress($iCurrentStep, $iTotalSteps, "Sauvegarde du fond d'écran...")
        _Log("- Sauvegarde du fond d'écran...", "Sauvegarde", "Système")
        _BackupWallpaper($sBackupFolder)
        _Log("  OK: Fond d'écran sauvegardé", "Sauvegarde", "Système")
    EndIf

    ; Sauvegarde des imprimantes
    If $bPrinters Then
        $iCurrentStep += 1
        _UpdateProgress($iCurrentStep, $iTotalSteps, "Sauvegarde des imprimantes...")
        _Log("- Sauvegarde des imprimantes...", "Sauvegarde", "Système")
        _BackupPrinters($sBackupFolder)
        _Log("  OK: Imprimantes sauvegardées", "Sauvegarde", "Système")
    EndIf

    ; Sauvegarde des dossiers Steam
    If $bSteam Then
        $iCurrentStep += 1
        _UpdateProgress($iCurrentStep, $iTotalSteps, "Sauvegarde des dossiers Steam...")
        _Log("- Sauvegarde des dossiers Steam...", "Sauvegarde", "Steam")
        _BackupSteamRobocopy($sBackupFolder)
        _Log("  OK: Dossiers Steam sauvegardés", "Sauvegarde", "Steam")
    EndIf

    ; Sauvegarde des paramètres WiFi
    If $bWifi Then
        $iCurrentStep += 1
        _UpdateProgress($iCurrentStep, $iTotalSteps, "Sauvegarde des paramètres WiFi...")
        _Log("- Sauvegarde des paramètres WiFi...", "Sauvegarde", "Système")
        _BackupWifi($sBackupFolder)
        _Log("  OK: Paramètres WiFi sauvegardés", "Sauvegarde", "Système")
    EndIf

    ; Sauvegarde des paramètres système
    If $bSystem Then
        $iCurrentStep += 1
        _UpdateProgress($iCurrentStep, $iTotalSteps, "Sauvegarde des paramètres système...")
        _Log("- Sauvegarde des paramètres système...", "Sauvegarde", "Système")
        _BackupSystemSettings($sBackupFolder)
        _Log("  OK: Paramètres système sauvegardés", "Sauvegarde", "Système")
    EndIf

    _UpdateProgress(100, 100, "Sauvegarde terminée !")
    _Log("=== SAUVEGARDE TERMINÉE ===", "Sauvegarde", "Info")
    _Log("Dossier: " & $sBackupFolder, "Sauvegarde", "Info")
EndFunc

; Fonction pour mettre à jour la progress bar
Func _UpdateProgress($iCurrent, $iTotal, $sMessage)
    Local $iPercent = Round(($iCurrent / $iTotal) * 100)
    GUICtrlSetData($progressBar, $iPercent)
    GUICtrlSetData($lblProgress, $sMessage & " (" & $iPercent & "%)")
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
    _Log("- Analyse des applications installées...", "Sauvegarde", "Applications")
    _ListInstalledApplications(@ScriptDir)
    _Log("  OK: Liste des applications générée", "Sauvegarde", "Applications")
EndFunc

; ===============================================================================
; FONCTIONS DE SAUVEGARDE
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
                _Log("    Sauvegarde: " & $aUserFolders[$i][1], "Sauvegarde", "Fichiers")

                ; Utiliser Robocopy avec logging
                Local $sLogFile = $sBackupFolder & "\robocopy_" & $aUserFolders[$i][0] & ".log"
                Local $sRobocopyCmd = 'robocopy "' & $sSourcePath & '" "' & $sDestPath & '" /E /COPYALL /R:3 /W:1 /LOG:"' & $sLogFile & '" /TEE'

                Local $iPID = Run($sRobocopyCmd, "", @SW_HIDE)
                ProcessWaitClose($iPID)

                ; Vérifier l'intégrité en comparant source vs destination
                _VerifyRobocopyIntegrity($sSourcePath, $sDestPath, $aUserFolders[$i][1], $sLogFile)

            Else
                _Log("    Dossier non trouvé: " & $aUserFolders[$i][1], "Sauvegarde", "Fichiers")
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
        _Log("      ERREUR: " & $sFolderName & " - " & $iMissingFiles & " fichier(s) manquant(s) !", "Sauvegarde", "Erreur")
        If $iErrors > 0 Then
            _Log("      LOG: " & $iErrors & " erreur(s) dans le log - Voir: " & StringReplace($sLogFile, StringLeft($sLogFile, StringInStr($sLogFile, "\", 0, -1)), ""), "Sauvegarde", "Erreur")
        EndIf
    ElseIf $iErrors > 0 Then
        _Log("      ERREUR: " & $sFolderName & " - " & $iErrors & " erreur(s) détectée(s)", "Sauvegarde", "Erreur")
        _Log("      LOG: Voir détails: " & StringReplace($sLogFile, StringLeft($sLogFile, StringInStr($sLogFile, "\", 0, -1)), ""), "Sauvegarde", "Erreur")
    ElseIf $iSourceFiles = 0 Then
        _Log("      INFO: " & $sFolderName & " - Dossier vide", "Sauvegarde", "Info")
    Else
        _Log("      OK: " & $sFolderName & " - Intégrité complète (" & $iDestFiles & " fichiers)", "Sauvegarde", "Fichiers")
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
        _Log("    Sauvegarde Thunderbird Roaming...", "Sauvegarde", "Thunderbird")

        Local $sLogFile = $sBackupFolder & "\robocopy_Thunderbird_Roaming.log"
        Local $sRobocopyCmd = 'robocopy "' & $sThunderbirdRoaming & '" "' & $sThunderbirdBackup & '\Roaming" /E /COPYALL /R:3 /W:1 /LOG:"' & $sLogFile & '" /TEE'

        Local $iPID = Run($sRobocopyCmd, "", @SW_HIDE)
        ProcessWaitClose($iPID)

        _VerifyRobocopyIntegrity($sThunderbirdRoaming, $sThunderbirdBackup & "\Roaming", "Thunderbird Roaming", $sLogFile)
        $bFound = True
    EndIf

    ; Sauvegarder le dossier Local (cache et données temporaires)
    If FileExists($sThunderbirdLocal) Then
        _Log("    Sauvegarde Thunderbird Local...", "Sauvegarde", "Thunderbird")

        Local $sLogFile = $sBackupFolder & "\robocopy_Thunderbird_Local.log"
        Local $sRobocopyCmd = 'robocopy "' & $sThunderbirdLocal & '" "' & $sThunderbirdBackup & '\Local" /E /COPYALL /R:3 /W:1 /LOG:"' & $sLogFile & '" /TEE'

        Local $iPID = Run($sRobocopyCmd, "", @SW_HIDE)
        ProcessWaitClose($iPID)

        _VerifyRobocopyIntegrity($sThunderbirdLocal, $sThunderbirdBackup & "\Local", "Thunderbird Local", $sLogFile)
        $bFound = True
    EndIf

    If Not $bFound Then
        _Log("    Thunderbird non installé ou aucune donnée trouvée", "Sauvegarde", "Thunderbird")
        ; Supprimer le dossier vide
        DirRemove($sThunderbirdBackup)
    Else
        _Log("    ✓ Thunderbird sauvegardé (Roaming + Local)", "Sauvegarde", "Thunderbird")
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
            _Log("    Steam trouvé: " & $sSteamPath, "Sauvegarde", "Steam")
            $bSteamFound = True

            ; Sauvegarder les fichiers de configuration Steam
            If FileExists($sSteamPath & "\config") Then
                Local $sLogFile = $sBackupFolder & "\robocopy_Steam_Config.log"
                Local $sRobocopyCmd = 'robocopy "' & $sSteamPath & '\config" "' & $sSteamBackup & '\config" /E /COPYALL /R:3 /W:1 /LOG:"' & $sLogFile & '" /TEE'

                Local $iPID = Run($sRobocopyCmd, "", @SW_HIDE)
                ProcessWaitClose($iPID)

                _VerifyRobocopyIntegrity($sSteamPath & "\config", $sSteamBackup & "\config", "Config Steam", $sLogFile)
                _Log("    ✓ Configuration Steam sauvegardée", "Sauvegarde", "Steam")
            EndIf

            ; Sauvegarder les données utilisateur Steam
            If FileExists($sSteamPath & "\userdata") Then
                Local $sLogFile = $sBackupFolder & "\robocopy_Steam_UserData.log"
                Local $sRobocopyCmd = 'robocopy "' & $sSteamPath & '\userdata" "' & $sSteamBackup & '\userdata" /E /COPYALL /R:3 /W:1 /LOG:"' & $sLogFile & '" /TEE'

                Local $iPID = Run($sRobocopyCmd, "", @SW_HIDE)
                ProcessWaitClose($iPID)

                _VerifyRobocopyIntegrity($sSteamPath & "\userdata", $sSteamBackup & "\userdata", "Données Steam", $sLogFile)
                _Log("    ✓ Données utilisateur Steam sauvegardées", "Sauvegarde", "Steam")
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
                    _Log("    ✓ Liste des jeux sauvegardée (" & $aGames[0] & " jeux)", "Sauvegarde", "Steam")
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
                    _Log("    ✓ Fichiers ACF sauvegardés (" & $aAcfFiles[0] & " fichiers)", "Sauvegarde", "Steam")
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
        _Log("    Steam non installé ou non trouvé", "Sauvegarde", "Steam")
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
        _Log("    Sauvegarde favoris Internet Explorer...", "Sauvegarde", "Navigateurs")
        DirCopy($sIEFavorites, $sBrowserBackup & "\Internet_Explorer", $FC_OVERWRITE)
    EndIf
EndFunc

; Sauvegarder les favoris Google Chrome
Func _BackupChromeBookmarks($sBrowserBackup)
    Local $sChromePath = @LocalAppDataDir & "\Google\Chrome\User Data\Default"
    If FileExists($sChromePath & "\Bookmarks") Then
        _Log("    Sauvegarde favoris Google Chrome...", "Sauvegarde", "Navigateurs")
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
        _Log("    Sauvegarde favoris Mozilla Firefox...", "Sauvegarde", "Navigateurs")

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
        _Log("    Sauvegarde favoris Microsoft Edge...", "Sauvegarde", "Navigateurs")
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
        _Log("    Firefox non installé ou aucun profil trouvé", "Sauvegarde", "Navigateurs")
        Return
    EndIf

    ; Trouver le profil par défaut
    Local $aProfiles = _FileListToArray($sFirefoxPath, "*", $FLTA_FOLDERS)
    If Not IsArray($aProfiles) Then
        _Log("    Aucun profil Firefox trouvé", "Sauvegarde", "Navigateurs")
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
                _Log("    ✓ logins.json sauvegardé", "Sauvegarde", "Navigateurs")
            EndIf

            If FileExists($sProfilePath & "\key4.db") Then
                FileCopy($sProfilePath & "\key4.db", $sFirefoxBackup & "\key4.db")
                _Log("    ✓ key4.db sauvegardé", "Sauvegarde", "Navigateurs")
            EndIf

            ; Sauvegarder aussi key3.db si présent (anciennes versions)
            If FileExists($sProfilePath & "\key3.db") Then
                FileCopy($sProfilePath & "\key3.db", $sFirefoxBackup & "\key3.db")
                _Log("    ✓ key3.db sauvegardé", "Sauvegarde", "Navigateurs")
            EndIf

            ; Sauvegarder le fichier de profil pour la restauration
            If FileExists($sProfilePath & "\prefs.js") Then
                FileCopy($sProfilePath & "\prefs.js", $sFirefoxBackup & "\prefs.js")
                _Log("    ✓ prefs.js sauvegardé", "Sauvegarde", "Navigateurs")
            EndIf

            ; On prend le premier profil trouvé avec des mots de passe
            ExitLoop
        EndIf
    Next

    If Not $bHasContent Then
        _Log("    Aucun fichier de mot de passe Firefox trouvé", "Sauvegarde", "Navigateurs")
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

        _Log("    ✓ Fond d'écran sauvegardé: " & $sFileName, "Sauvegarde", "Système")
    Else
        _Log("    Aucun fond d'écran personnalisé trouvé", "Sauvegarde", "Système")
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

    _Log("    ✓ Liste des imprimantes sauvegardée", "Sauvegarde", "Système")
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
            _Log("    ✓ Informations interfaces WiFi sauvegardées", "Sauvegarde", "Système")
        EndIf
    EndIf

    ; 2. Liste des profils WiFi
    Local $sNetworksFile = $sWifiBackup & "\wifi_profiles_list.txt"
    RunWait('netsh wlan show profiles > "' & $sNetworksFile & '"', "", @SW_HIDE)
    If FileExists($sNetworksFile) Then
        Local $sContent = FileRead($sNetworksFile)
        If StringLen($sContent) > 10 Then
            $bHasContent = True
            _Log("    ✓ Liste des profils WiFi sauvegardée", "Sauvegarde", "Système")
        EndIf
    EndIf

    ; 3. Informations sur les pilotes WiFi
    Local $sDriversFile = $sWifiBackup & "\wifi_drivers.txt"
    RunWait('netsh wlan show drivers > "' & $sDriversFile & '"', "", @SW_HIDE)
    If FileExists($sDriversFile) Then
        Local $sContent = FileRead($sDriversFile)
        If StringLen($sContent) > 10 Then
            $bHasContent = True
            _Log("    ✓ Informations pilotes WiFi sauvegardées", "Sauvegarde", "Système")
        EndIf
    EndIf

    ; 4. Exporter les profils WiFi (avec mots de passe si possible)
    RunWait('netsh wlan export profile folder="' & $sWifiBackup & '" key=clear', "", @SW_HIDE)

    ; 5. Vérifier si des profils XML ont été exportés
    Local $aWifiFiles = _FileListToArray($sWifiBackup, "*.xml", $FLTA_FILES)
    If IsArray($aWifiFiles) And $aWifiFiles[0] > 0 Then
        _Log("    ✓ " & $aWifiFiles[0] & " profil(s) WiFi exporté(s)", "Sauvegarde", "Système")
        $bHasContent = True
    Else
        _Log("    Aucun profil WiFi exporté (normal si pas de profils sauvegardés)", "Sauvegarde", "Système")
    EndIf

    ; 6. Informations système réseau supplémentaires
    Local $sNetworkFile = $sWifiBackup & "\network_adapters.txt"
    RunWait('ipconfig /all > "' & $sNetworkFile & '"', "", @SW_HIDE)
    If FileExists($sNetworkFile) Then
        $bHasContent = True
        _Log("    ✓ Configuration réseau sauvegardée", "Sauvegarde", "Système")
    EndIf

    ; Résultat final
    If $bHasContent Then
        _Log("    ✓ Sauvegarde WiFi terminée", "Sauvegarde", "Système")
    Else
        _Log("    Aucune information WiFi récupérée", "Sauvegarde", "Système")
        DirRemove($sWifiBackup, 1)
    EndIf
EndFunc
; Fonction pour sauvegarder les paramètres système
Func _BackupSystemSettings($sBackupFolder)
    Local $sSystemBackup = $sBackupFolder & "\Parametres_Systeme"
    DirCreate($sSystemBackup)

    ; Exporter les clés de registre importantes
    _Log("    Export des clés de registre...", "Sauvegarde", "Système")
    RunWait('reg export "HKEY_CURRENT_USER\Software\Microsoft\Windows\CurrentVersion\Explorer" "' & $sSystemBackup & '\Explorer_Settings.reg"', "", @SW_HIDE)
    RunWait('reg export "HKEY_CURRENT_USER\Control Panel\Desktop" "' & $sSystemBackup & '\Desktop_Settings.reg"', "", @SW_HIDE)

    ; Créer un rapport système
    _Log("    Création du rapport système...", "Sauvegarde", "Système")
    RunWait('systeminfo > "' & $sSystemBackup & '\system_info.txt"', "", @SW_HIDE)
    RunWait('driverquery > "' & $sSystemBackup & '\drivers_list.txt"', "", @SW_HIDE)
EndFunc