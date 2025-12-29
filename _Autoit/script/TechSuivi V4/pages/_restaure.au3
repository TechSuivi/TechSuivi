#cs ----------------------------------------------------------------------------

 AutoIt Version: 3.3.16.1
 Author:         TechSuivi

 Script Function:
	Page Restauration pour TechSuivi V4
	Interface granulaire pour choisir les éléments à restaurer

#ce ----------------------------------------------------------------------------

; Variables globales pour la restauration
Global $sBackupPath = ""
Global $bOperationInProgress = False

; Variables pour les contrôles de l'interface
Global $btnSelectBackupPath, $btnRestore, $btnRefreshBackups
Global $lblBackupSource, $hListBackups, $progressBar, $lblProgress
Global $checkRestoreDesktop, $checkRestoreDocuments, $checkRestorePictures
Global $checkRestoreMusic, $checkRestoreVideos, $checkRestoreDownloads
Global $checkRestoreBrowsers, $checkRestoreFirefoxPasswords, $checkRestoreThunderbird
Global $checkRestoreWallpaper, $checkRestorePrinters, $checkRestoreSteam, $checkRestoreWifi, $checkRestoreSystem
Global $btnSelectAllRestore, $btnDeselectAllRestore

; Variables pour stocker les chemins des sauvegardes
Global $aBackupPaths[1]
$aBackupPaths[0] = 0

; Fonction principale pour créer l'interface Restauration
Func _restaure()
    ; Groupe Source de sauvegarde
    GUICtrlCreateGroup("Source de sauvegarde", 30, 50, 740, 80)
    $lblBackupSource = GUICtrlCreateLabel("Aucune source sélectionnée", 50, 75, 500, 20)
    GUICtrlSetColor($lblBackupSource, 0xFF0000)
    $btnSelectBackupPath = GUICtrlCreateButton("Choisir Source", 580, 70, 150, 30)
    GUICtrlSetOnEvent($btnSelectBackupPath, "_SelectRestoreBackupPath")
    GUICtrlCreateGroup("", -99, -99, 1, 1) ; Fermer le groupe

    ; Groupe Sélection de sauvegarde
    GUICtrlCreateGroup("Sélection de la sauvegarde", 30, 150, 740, 80)
    GUICtrlCreateLabel("Sauvegardes disponibles :", 50, 175, 150, 20)
    $hListBackups = GUICtrlCreateCombo("", 50, 195, 500, 20)
    GUICtrlSetOnEvent($hListBackups, "_OnBackupSelectionChange")

    $btnRefreshBackups = GUICtrlCreateButton("Actualiser", 570, 195, 80, 20)
    GUICtrlSetOnEvent($btnRefreshBackups, "_RefreshBackupsList")

    $btnRestore = GUICtrlCreateButton("RESTAURER SÉLECTION", 660, 195, 110, 20)
    GUICtrlSetFont($btnRestore, 9, 600)
    GUICtrlSetBkColor($btnRestore, 0xFFB6C1)
    GUICtrlSetOnEvent($btnRestore, "_StartRestoreProcess")
    GUICtrlCreateGroup("", -99, -99, 1, 1)

    ; Groupe Options de restauration
    GUICtrlCreateGroup("Éléments à restaurer", 30, 250, 740, 200)

    ; Sous-groupe Fichiers utilisateur
    GUICtrlCreateLabel("Dossiers utilisateur :", 50, 280, 150, 20)
    GUICtrlSetFont(-1, 9, 600) ; Gras
    $checkRestoreDesktop = GUICtrlCreateCheckbox("Bureau", 50, 300, 100, 20)
    $checkRestoreDocuments = GUICtrlCreateCheckbox("Documents", 160, 300, 100, 20)
    $checkRestorePictures = GUICtrlCreateCheckbox("Images", 270, 300, 100, 20)
    $checkRestoreMusic = GUICtrlCreateCheckbox("Musique", 50, 320, 100, 20)
    $checkRestoreVideos = GUICtrlCreateCheckbox("Vidéos", 160, 320, 100, 20)
    $checkRestoreDownloads = GUICtrlCreateCheckbox("Téléchargements", 270, 320, 120, 20)

    ; Sous-groupe Navigateurs et Email
    GUICtrlCreateLabel("Navigateurs et Email :", 400, 280, 150, 20)
    GUICtrlSetFont(-1, 9, 600) ; Gras
    $checkRestoreBrowsers = GUICtrlCreateCheckbox("Favoris des navigateurs", 400, 300, 150, 20)
    $checkRestoreFirefoxPasswords = GUICtrlCreateCheckbox("Mots de passe Firefox", 400, 320, 150, 20)
    $checkRestoreThunderbird = GUICtrlCreateCheckbox("Thunderbird (emails)", 400, 340, 150, 20)

    ; Sous-groupe Système et Gaming
    GUICtrlCreateLabel("Système et Gaming :", 50, 360, 150, 20)
    GUICtrlSetFont(-1, 9, 600) ; Gras
    $checkRestoreSystem = GUICtrlCreateCheckbox("Paramètres système", 50, 380, 150, 20)
    $checkRestoreWallpaper = GUICtrlCreateCheckbox("Fond d'écran", 220, 380, 120, 20)
    $checkRestorePrinters = GUICtrlCreateCheckbox("Imprimantes", 50, 400, 120, 20)
    $checkRestoreSteam = GUICtrlCreateCheckbox("Dossiers Steam", 220, 400, 120, 20)
    $checkRestoreWifi = GUICtrlCreateCheckbox("Paramètres WiFi", 400, 380, 120, 20)

    ; Boutons de sélection rapide
    $btnSelectAllRestore = GUICtrlCreateButton("Tout sélectionner", 50, 420, 100, 25)
    GUICtrlSetOnEvent($btnSelectAllRestore, "_SelectAllRestoreOptions")
    $btnDeselectAllRestore = GUICtrlCreateButton("Tout désélectionner", 160, 420, 120, 25)
    GUICtrlSetOnEvent($btnDeselectAllRestore, "_DeselectAllRestoreOptions")

    GUICtrlCreateGroup("", -99, -99, 1, 1)

    ; Groupe Progress Bar
    GUICtrlCreateGroup("Progression", 30, 470, 740, 60)
    $lblProgress = GUICtrlCreateLabel("Sélectionnez une sauvegarde à restaurer", 50, 490, 300, 20)
    $progressBar = GUICtrlCreateProgress(50, 510, 680, 15)
    GUICtrlCreateGroup("", -99, -99, 1, 1)

    ; Initialisation
    _InitializeRestaure()
EndFunc

; Initialisation de l'interface
Func _InitializeRestaure()


    ; Vérifier si un chemin de sauvegarde par défaut existe
    Local $sDefaultPath = RegRead("HKEY_CURRENT_USER\Software\TechSuivi", "DefaultBackupPath")
    If $sDefaultPath <> "" And FileExists($sDefaultPath) Then
        $sBackupPath = $sDefaultPath
        _UpdateBackupSourceLabel()
        _RefreshBackupsList()
    EndIf

    ; Désactiver les checkboxes au début
    _DisableAllRestoreOptions()
EndFunc

; Sélection du chemin de sauvegarde
Func _SelectRestoreBackupPath()
    Local $sNewPath = FileSelectFolder("Choisir le dossier contenant les sauvegardes", $sBackupPath)
    If $sNewPath <> "" Then
        $sBackupPath = $sNewPath
        _UpdateBackupSourceLabel()
        _RefreshBackupsList()
        _Log("- Nouveau chemin de sauvegarde: " & $sBackupPath, "Restauration", "Config")

        ; Sauvegarder le chemin par défaut
        RegWrite("HKEY_CURRENT_USER\Software\TechSuivi", "DefaultBackupPath", "REG_SZ", $sBackupPath)
    EndIf
EndFunc

; Mise à jour du label de source
Func _UpdateBackupSourceLabel()
    If $sBackupPath <> "" Then
        GUICtrlSetData($lblBackupSource, "Source: " & $sBackupPath)
        GUICtrlSetColor($lblBackupSource, 0x008000)
    Else
        GUICtrlSetData($lblBackupSource, "Aucune source sélectionnée")
        GUICtrlSetColor($lblBackupSource, 0xFF0000)
    EndIf
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
        _Log("- " & $aBackups[0] & " sauvegarde(s) trouvée(s)", "Restauration", "Info")
    EndIf

    ; Désactiver les options si aucune sauvegarde
    If Not IsArray($aBackups) Or $aBackups[0] = 0 Then
        _DisableAllRestoreOptions()
    EndIf
EndFunc

; Événement de changement de sélection de sauvegarde
Func _OnBackupSelectionChange()
    Local $sSelectedBackup = GUICtrlRead($hListBackups)
    If $sSelectedBackup <> "" Then
        Local $sSelectedBackupPath = $sBackupPath & "\" & $sSelectedBackup
        _AnalyzeBackupAndUpdateOptions($sSelectedBackupPath)
        _Log("- Analyse de la sauvegarde: " & $sSelectedBackup, "Restauration", "Info")
    Else
        _DisableAllRestoreOptions()
    EndIf
EndFunc

; Analyser la sauvegarde et mettre à jour les options disponibles
Func _AnalyzeBackupAndUpdateOptions($sBackupPath)
    ; Réinitialiser toutes les options
    _DisableAllRestoreOptions()

    ; Vérifier chaque élément et activer les checkboxes correspondantes
    If FileExists($sBackupPath & "\Fichiers_Utilisateur\Desktop") Then
        GUICtrlSetState($checkRestoreDesktop, $GUI_ENABLE)
        GUICtrlSetState($checkRestoreDesktop, $GUI_CHECKED)
    EndIf

    If FileExists($sBackupPath & "\Fichiers_Utilisateur\Documents") Then
        GUICtrlSetState($checkRestoreDocuments, $GUI_ENABLE)
        GUICtrlSetState($checkRestoreDocuments, $GUI_CHECKED)
    EndIf

    If FileExists($sBackupPath & "\Fichiers_Utilisateur\Pictures") Then
        GUICtrlSetState($checkRestorePictures, $GUI_ENABLE)
        GUICtrlSetState($checkRestorePictures, $GUI_CHECKED)
    EndIf

    If FileExists($sBackupPath & "\Fichiers_Utilisateur\Music") Then
        GUICtrlSetState($checkRestoreMusic, $GUI_ENABLE)
        GUICtrlSetState($checkRestoreMusic, $GUI_CHECKED)
    EndIf

    If FileExists($sBackupPath & "\Fichiers_Utilisateur\Videos") Then
        GUICtrlSetState($checkRestoreVideos, $GUI_ENABLE)
        GUICtrlSetState($checkRestoreVideos, $GUI_CHECKED)
    EndIf

    If FileExists($sBackupPath & "\Fichiers_Utilisateur\Downloads") Then
        GUICtrlSetState($checkRestoreDownloads, $GUI_ENABLE)
        GUICtrlSetState($checkRestoreDownloads, $GUI_CHECKED)
    EndIf

    If FileExists($sBackupPath & "\Favoris_Navigateurs") Then
        GUICtrlSetState($checkRestoreBrowsers, $GUI_ENABLE)
        GUICtrlSetState($checkRestoreBrowsers, $GUI_CHECKED)
    EndIf

    If FileExists($sBackupPath & "\Firefox_Passwords") Then
        GUICtrlSetState($checkRestoreFirefoxPasswords, $GUI_ENABLE)
        GUICtrlSetState($checkRestoreFirefoxPasswords, $GUI_CHECKED)
    EndIf

    If FileExists($sBackupPath & "\Thunderbird") Then
        GUICtrlSetState($checkRestoreThunderbird, $GUI_ENABLE)
        GUICtrlSetState($checkRestoreThunderbird, $GUI_CHECKED)
    EndIf

    If FileExists($sBackupPath & "\Fond_Ecran") Then
        GUICtrlSetState($checkRestoreWallpaper, $GUI_ENABLE)
        GUICtrlSetState($checkRestoreWallpaper, $GUI_CHECKED)
    EndIf

    If FileExists($sBackupPath & "\Imprimantes") Then
        GUICtrlSetState($checkRestorePrinters, $GUI_ENABLE)
        GUICtrlSetState($checkRestorePrinters, $GUI_CHECKED)
    EndIf

    If FileExists($sBackupPath & "\Steam") Then
        GUICtrlSetState($checkRestoreSteam, $GUI_ENABLE)
        GUICtrlSetState($checkRestoreSteam, $GUI_CHECKED)
    EndIf

    If FileExists($sBackupPath & "\Parametres_WiFi") Then
        GUICtrlSetState($checkRestoreWifi, $GUI_ENABLE)
        GUICtrlSetState($checkRestoreWifi, $GUI_CHECKED)
    EndIf

    If FileExists($sBackupPath & "\Parametres_Systeme") Then
        GUICtrlSetState($checkRestoreSystem, $GUI_ENABLE)
        GUICtrlSetState($checkRestoreSystem, $GUI_CHECKED)
    EndIf

    ; Compter les éléments disponibles
    Local $iAvailableItems = 0
    If FileExists($sBackupPath & "\Fichiers_Utilisateur") Then $iAvailableItems += _CountUserFolders($sBackupPath & "\Fichiers_Utilisateur")
    If FileExists($sBackupPath & "\Favoris_Navigateurs") Then $iAvailableItems += 1
    If FileExists($sBackupPath & "\Firefox_Passwords") Then $iAvailableItems += 1
    If FileExists($sBackupPath & "\Thunderbird") Then $iAvailableItems += 1
    If FileExists($sBackupPath & "\Fond_Ecran") Then $iAvailableItems += 1
    If FileExists($sBackupPath & "\Imprimantes") Then $iAvailableItems += 1
    If FileExists($sBackupPath & "\Steam") Then $iAvailableItems += 1
    If FileExists($sBackupPath & "\Parametres_WiFi") Then $iAvailableItems += 1
    If FileExists($sBackupPath & "\Parametres_Systeme") Then $iAvailableItems += 1

    _Log("  " & $iAvailableItems & " élément(s) disponible(s) pour la restauration", "Restauration", "Info")
EndFunc

; Compter les dossiers utilisateur disponibles
Func _CountUserFolders($sUserPath)
    Local $iCount = 0
    Local $aFolders[6] = ["Desktop", "Documents", "Pictures", "Music", "Videos", "Downloads"]

    For $sFolder In $aFolders
        If FileExists($sUserPath & "\" & $sFolder) Then $iCount += 1
    Next

    Return $iCount
EndFunc

; Désactiver toutes les options de restauration
Func _DisableAllRestoreOptions()
    GUICtrlSetState($checkRestoreDesktop, $GUI_DISABLE)
    GUICtrlSetState($checkRestoreDesktop, $GUI_UNCHECKED)
    GUICtrlSetState($checkRestoreDocuments, $GUI_DISABLE)
    GUICtrlSetState($checkRestoreDocuments, $GUI_UNCHECKED)
    GUICtrlSetState($checkRestorePictures, $GUI_DISABLE)
    GUICtrlSetState($checkRestorePictures, $GUI_UNCHECKED)
    GUICtrlSetState($checkRestoreMusic, $GUI_DISABLE)
    GUICtrlSetState($checkRestoreMusic, $GUI_UNCHECKED)
    GUICtrlSetState($checkRestoreVideos, $GUI_DISABLE)
    GUICtrlSetState($checkRestoreVideos, $GUI_UNCHECKED)
    GUICtrlSetState($checkRestoreDownloads, $GUI_DISABLE)
    GUICtrlSetState($checkRestoreDownloads, $GUI_UNCHECKED)
    GUICtrlSetState($checkRestoreBrowsers, $GUI_DISABLE)
    GUICtrlSetState($checkRestoreBrowsers, $GUI_UNCHECKED)
    GUICtrlSetState($checkRestoreFirefoxPasswords, $GUI_DISABLE)
    GUICtrlSetState($checkRestoreFirefoxPasswords, $GUI_UNCHECKED)
    GUICtrlSetState($checkRestoreThunderbird, $GUI_DISABLE)
    GUICtrlSetState($checkRestoreThunderbird, $GUI_UNCHECKED)
    GUICtrlSetState($checkRestoreWallpaper, $GUI_DISABLE)
    GUICtrlSetState($checkRestoreWallpaper, $GUI_UNCHECKED)
    GUICtrlSetState($checkRestorePrinters, $GUI_DISABLE)
    GUICtrlSetState($checkRestorePrinters, $GUI_UNCHECKED)
    GUICtrlSetState($checkRestoreSteam, $GUI_DISABLE)
    GUICtrlSetState($checkRestoreSteam, $GUI_UNCHECKED)
    GUICtrlSetState($checkRestoreWifi, $GUI_DISABLE)
    GUICtrlSetState($checkRestoreWifi, $GUI_UNCHECKED)
    GUICtrlSetState($checkRestoreSystem, $GUI_DISABLE)
    GUICtrlSetState($checkRestoreSystem, $GUI_UNCHECKED)
EndFunc

; Sélectionner toutes les options disponibles
Func _SelectAllRestoreOptions()
    If GUICtrlGetState($checkRestoreDesktop) <> $GUI_DISABLE Then GUICtrlSetState($checkRestoreDesktop, $GUI_CHECKED)
    If GUICtrlGetState($checkRestoreDocuments) <> $GUI_DISABLE Then GUICtrlSetState($checkRestoreDocuments, $GUI_CHECKED)
    If GUICtrlGetState($checkRestorePictures) <> $GUI_DISABLE Then GUICtrlSetState($checkRestorePictures, $GUI_CHECKED)
    If GUICtrlGetState($checkRestoreMusic) <> $GUI_DISABLE Then GUICtrlSetState($checkRestoreMusic, $GUI_CHECKED)
    If GUICtrlGetState($checkRestoreVideos) <> $GUI_DISABLE Then GUICtrlSetState($checkRestoreVideos, $GUI_CHECKED)
    If GUICtrlGetState($checkRestoreDownloads) <> $GUI_DISABLE Then GUICtrlSetState($checkRestoreDownloads, $GUI_CHECKED)
    If GUICtrlGetState($checkRestoreBrowsers) <> $GUI_DISABLE Then GUICtrlSetState($checkRestoreBrowsers, $GUI_CHECKED)
    If GUICtrlGetState($checkRestoreFirefoxPasswords) <> $GUI_DISABLE Then GUICtrlSetState($checkRestoreFirefoxPasswords, $GUI_CHECKED)
    If GUICtrlGetState($checkRestoreThunderbird) <> $GUI_DISABLE Then GUICtrlSetState($checkRestoreThunderbird, $GUI_CHECKED)
    If GUICtrlGetState($checkRestoreWallpaper) <> $GUI_DISABLE Then GUICtrlSetState($checkRestoreWallpaper, $GUI_CHECKED)
    If GUICtrlGetState($checkRestorePrinters) <> $GUI_DISABLE Then GUICtrlSetState($checkRestorePrinters, $GUI_CHECKED)
    If GUICtrlGetState($checkRestoreSteam) <> $GUI_DISABLE Then GUICtrlSetState($checkRestoreSteam, $GUI_CHECKED)
    If GUICtrlGetState($checkRestoreWifi) <> $GUI_DISABLE Then GUICtrlSetState($checkRestoreWifi, $GUI_CHECKED)
    If GUICtrlGetState($checkRestoreSystem) <> $GUI_DISABLE Then GUICtrlSetState($checkRestoreSystem, $GUI_CHECKED)
EndFunc

; Désélectionner toutes les options
Func _DeselectAllRestoreOptions()
    GUICtrlSetState($checkRestoreDesktop, $GUI_UNCHECKED)
    GUICtrlSetState($checkRestoreDocuments, $GUI_UNCHECKED)
    GUICtrlSetState($checkRestorePictures, $GUI_UNCHECKED)
    GUICtrlSetState($checkRestoreMusic, $GUI_UNCHECKED)
    GUICtrlSetState($checkRestoreVideos, $GUI_UNCHECKED)
    GUICtrlSetState($checkRestoreDownloads, $GUI_UNCHECKED)
    GUICtrlSetState($checkRestoreBrowsers, $GUI_UNCHECKED)
    GUICtrlSetState($checkRestoreFirefoxPasswords, $GUI_UNCHECKED)
    GUICtrlSetState($checkRestoreThunderbird, $GUI_UNCHECKED)
    GUICtrlSetState($checkRestoreWallpaper, $GUI_UNCHECKED)
    GUICtrlSetState($checkRestorePrinters, $GUI_UNCHECKED)
    GUICtrlSetState($checkRestoreSteam, $GUI_UNCHECKED)
    GUICtrlSetState($checkRestoreWifi, $GUI_UNCHECKED)
    GUICtrlSetState($checkRestoreSystem, $GUI_UNCHECKED)
EndFunc

; Démarrage du processus de restauration
Func _StartRestoreProcess()
    If $sBackupPath = "" Then
        _Log("ERREUR: Veuillez d'abord choisir un dossier de sauvegarde", "Restauration", "Erreur")
        Return
    EndIf

    If $bOperationInProgress Then
        _Log("ERREUR: Une opération est déjà en cours", "Restauration", "Erreur")
        Return
    EndIf

    ; Vérifier qu'une sauvegarde est sélectionnée
    Local $sSelectedBackup = GUICtrlRead($hListBackups)
    If $sSelectedBackup = "" Then
        _Log("ERREUR: Veuillez sélectionner une sauvegarde à restaurer", "Restauration", "Erreur")
        Return
    EndIf

    Local $sSelectedBackupPath = $sBackupPath & "\" & $sSelectedBackup

    ; Vérifier les options sélectionnées
    Local $bDesktop = (GUICtrlRead($checkRestoreDesktop) = $GUI_CHECKED)
    Local $bDocuments = (GUICtrlRead($checkRestoreDocuments) = $GUI_CHECKED)
    Local $bPictures = (GUICtrlRead($checkRestorePictures) = $GUI_CHECKED)
    Local $bMusic = (GUICtrlRead($checkRestoreMusic) = $GUI_CHECKED)
    Local $bVideos = (GUICtrlRead($checkRestoreVideos) = $GUI_CHECKED)
    Local $bDownloads = (GUICtrlRead($checkRestoreDownloads) = $GUI_CHECKED)
    Local $bBrowsers = (GUICtrlRead($checkRestoreBrowsers) = $GUI_CHECKED)
    Local $bFirefoxPasswords = (GUICtrlRead($checkRestoreFirefoxPasswords) = $GUI_CHECKED)
    Local $bThunderbird = (GUICtrlRead($checkRestoreThunderbird) = $GUI_CHECKED)
    Local $bWallpaper = (GUICtrlRead($checkRestoreWallpaper) = $GUI_CHECKED)
    Local $bPrinters = (GUICtrlRead($checkRestorePrinters) = $GUI_CHECKED)
    Local $bSteam = (GUICtrlRead($checkRestoreSteam) = $GUI_CHECKED)
    Local $bWifi = (GUICtrlRead($checkRestoreWifi) = $GUI_CHECKED)
    Local $bSystem = (GUICtrlRead($checkRestoreSystem) = $GUI_CHECKED)

    If Not ($bDesktop Or $bDocuments Or $bPictures Or $bMusic Or $bVideos Or $bDownloads Or $bBrowsers Or $bFirefoxPasswords Or $bThunderbird Or $bWallpaper Or $bPrinters Or $bSteam Or $bWifi Or $bSystem) Then
        _Log("ERREUR: Veuillez sélectionner au moins un élément à restaurer", "Restauration", "Erreur")
        Return
    EndIf

    ; Confirmation finale
    Local $iResponse = MsgBox($MB_YESNO + $MB_ICONQUESTION, "Confirmation", "Restaurer les éléments sélectionnés ?" & @CRLF & @CRLF & "ATTENTION: Cela remplacera vos fichiers actuels!" & @CRLF & @CRLF & "Sauvegarde: " & $sSelectedBackup)
    If $iResponse = $IDNO Then Return

    ; Démarrer la restauration
    $bOperationInProgress = True
    GUICtrlSetState($btnRestore, $GUI_DISABLE)

    _Log("=== DÉBUT DE LA RESTAURATION ===", "Restauration", "Info")
    _PerformGranularRestore($sSelectedBackupPath, $bDesktop, $bDocuments, $bPictures, $bMusic, $bVideos, $bDownloads, $bBrowsers, $bFirefoxPasswords, $bThunderbird, $bWallpaper, $bPrinters, $bSteam, $bWifi, $bSystem)

    $bOperationInProgress = False
    GUICtrlSetState($btnRestore, $GUI_ENABLE)
EndFunc

; Processus de restauration granulaire
Func _PerformGranularRestore($sBackupPath, $bDesktop, $bDocuments, $bPictures, $bMusic, $bVideos, $bDownloads, $bBrowsers, $bFirefoxPasswords, $bThunderbird, $bWallpaper, $bPrinters, $bSteam, $bWifi, $bSystem)
    _Log("Restauration depuis: " & $sBackupPath, "Restauration", "Info")
    GUICtrlSetData($lblProgress, "Initialisation...")
    GUICtrlSetData($progressBar, 0)

    ; Calculer le nombre total d'étapes
    Local $iTotalSteps = 0
    Local $iCurrentStep = 0

    If $bDesktop Then $iTotalSteps += 1
    If $bDocuments Then $iTotalSteps += 1
    If $bPictures Then $iTotalSteps += 1
    If $bMusic Then $iTotalSteps += 1
    If $bVideos Then $iTotalSteps += 1
    If $bDownloads Then $iTotalSteps += 1
    If $bBrowsers Then $iTotalSteps += 1
    If $bFirefoxPasswords Then $iTotalSteps += 1
    If $bThunderbird Then $iTotalSteps += 1
    If $bWallpaper Then $iTotalSteps += 1
    If $bPrinters Then $iTotalSteps += 1
    If $bSteam Then $iTotalSteps += 1
    If $bWifi Then $iTotalSteps += 1
    If $bSystem Then $iTotalSteps += 1

    ; Restauration des dossiers utilisateur individuels
    If $bDesktop Then
        $iCurrentStep += 1
        _UpdateRestoreProgress($iCurrentStep, $iTotalSteps, "Restauration du Bureau...")
        _Log("- Restauration du Bureau...", "Restauration", "Fichiers")
        _RestoreSpecificUserFolder($sBackupPath, "Desktop", "Bureau")
    EndIf

    If $bDocuments Then
        $iCurrentStep += 1
        _UpdateRestoreProgress($iCurrentStep, $iTotalSteps, "Restauration des Documents...")
        _Log("- Restauration des Documents...", "Restauration", "Fichiers")
        _RestoreSpecificUserFolder($sBackupPath, "Documents", "Documents")
    EndIf

    If $bPictures Then
        $iCurrentStep += 1
        _UpdateRestoreProgress($iCurrentStep, $iTotalSteps, "Restauration des Images...")
        _Log("- Restauration des Images...", "Restauration", "Fichiers")
        _RestoreSpecificUserFolder($sBackupPath, "Pictures", "Images")
    EndIf

    If $bMusic Then
        $iCurrentStep += 1
        _UpdateRestoreProgress($iCurrentStep, $iTotalSteps, "Restauration de la Musique...")
        _Log("- Restauration de la Musique...", "Restauration", "Fichiers")
        _RestoreSpecificUserFolder($sBackupPath, "Music", "Musique")
    EndIf

    If $bVideos Then
        $iCurrentStep += 1
        _UpdateRestoreProgress($iCurrentStep, $iTotalSteps, "Restauration des Vidéos...")
        _Log("- Restauration des Vidéos...", "Restauration", "Fichiers")
        _RestoreSpecificUserFolder($sBackupPath, "Videos", "Vidéos")
    EndIf

    If $bDownloads Then
        $iCurrentStep += 1
        _UpdateRestoreProgress($iCurrentStep, $iTotalSteps, "Restauration des Téléchargements...")
        _Log("- Restauration des Téléchargements...", "Restauration", "Fichiers")
        _RestoreSpecificUserFolder($sBackupPath, "Downloads", "Téléchargements")
    EndIf

    ; Restauration des favoris des navigateurs
    If $bBrowsers Then
        $iCurrentStep += 1
        _UpdateRestoreProgress($iCurrentStep, $iTotalSteps, "Restauration des favoris des navigateurs...")
        _Log("- Restauration des favoris des navigateurs...", "Restauration", "Navigateurs")
        _RestoreBrowserBookmarks($sBackupPath)
        _Log("  OK: Favoris des navigateurs restaurés", "Restauration", "Succès")
    EndIf

    ; Restauration des mots de passe Firefox
    If $bFirefoxPasswords Then
        $iCurrentStep += 1
        _UpdateRestoreProgress($iCurrentStep, $iTotalSteps, "Restauration des mots de passe Firefox...")
        _Log("- Restauration des mots de passe Firefox...", "Restauration", "Navigateurs")
        _RestoreFirefoxPasswords($sBackupPath)
        _Log("  OK: Mots de passe Firefox restaurés", "Restauration", "Succès")
    EndIf

    ; Restauration de Thunderbird
    If $bThunderbird Then
        $iCurrentStep += 1
        _UpdateRestoreProgress($iCurrentStep, $iTotalSteps, "Restauration de Thunderbird...")
        _Log("- Restauration de Thunderbird...", "Restauration", "Thunderbird")
        _RestoreThunderbird($sBackupPath)
        _Log("  OK: Thunderbird restauré", "Restauration", "Succès")
    EndIf

    ; Restauration du fond d'écran
    If $bWallpaper Then
        $iCurrentStep += 1
        _UpdateRestoreProgress($iCurrentStep, $iTotalSteps, "Restauration du fond d'écran...")
        _Log("- Restauration du fond d'écran...", "Restauration", "Système")
        _RestoreWallpaper($sBackupPath)
        _Log("  OK: Fond d'écran restauré", "Restauration", "Succès")
    EndIf

    ; Restauration des imprimantes
    If $bPrinters Then
        $iCurrentStep += 1
        _UpdateRestoreProgress($iCurrentStep, $iTotalSteps, "Restauration des imprimantes...")
        _Log("- Restauration des imprimantes...", "Restauration", "Système")
        _RestorePrinters($sBackupPath)
        _Log("  OK: Imprimantes restaurées", "Restauration", "Succès")
    EndIf

    ; Restauration de Steam
    If $bSteam Then
        $iCurrentStep += 1
        _UpdateRestoreProgress($iCurrentStep, $iTotalSteps, "Restauration de Steam...")
        _Log("- Restauration de Steam...", "Restauration", "Steam")
        _RestoreSteam($sBackupPath)
        _Log("  OK: Steam restauré", "Restauration", "Succès")
    EndIf

    ; Restauration des paramètres WiFi
    If $bWifi Then
        $iCurrentStep += 1
        _UpdateRestoreProgress($iCurrentStep, $iTotalSteps, "Restauration des paramètres WiFi...")
        _Log("- Restauration des paramètres WiFi...", "Restauration", "Système")
        _RestoreWifi($sBackupPath)
        _Log("  OK: Paramètres WiFi restaurés", "Restauration", "Succès")
    EndIf

    ; Restauration des paramètres système
    If $bSystem Then
        $iCurrentStep += 1
        _UpdateRestoreProgress($iCurrentStep, $iTotalSteps, "Restauration des paramètres système...")
        _Log("- Restauration des paramètres système...", "Restauration", "Système")
        _RestoreSystemSettings($sBackupPath)
        _Log("  OK: Paramètres système restaurés", "Restauration", "Succès")
    EndIf

    _UpdateRestoreProgress(100, 100, "Restauration terminée !")
    _Log("=== RESTAURATION TERMINÉE ===", "Restauration", "Info")
    _Log("Un redémarrage peut être nécessaire pour appliquer tous les changements", "Restauration", "Info")
EndFunc

; Fonction pour mettre à jour la progress bar de restauration
Func _UpdateRestoreProgress($iCurrent, $iTotal, $sMessage)
    Local $iPercent = Round(($iCurrent / $iTotal) * 100)
    GUICtrlSetData($progressBar, $iPercent)
    GUICtrlSetData($lblProgress, $sMessage & " (" & $iPercent & "%)")
EndFunc

; ===============================================================================
; FONCTIONS DE RESTAURATION
; ===============================================================================

; Fonction pour restaurer un dossier utilisateur spécifique
Func _RestoreSpecificUserFolder($sBackupPath, $sFolderName, $sDisplayName)
    Local $sSourcePath = $sBackupPath & "\Fichiers_Utilisateur\" & $sFolderName
    Local $sDestPath = @UserProfileDir & "\" & $sFolderName

    If Not FileExists($sSourcePath) Then
        _Log("    Aucun dossier " & $sDisplayName & " à restaurer", "Restauration", "Fichiers")
        Return
    EndIf

    _Log("    Restauration: " & $sDisplayName & "...", "Restauration", "Fichiers")

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
        _Log("      OK: " & $sDisplayName & " restauré (" & $iDestFiles & " fichiers)", "Restauration", "Succès")
    Else
        _Log("      ERREUR: " & $sDisplayName & " partiellement restauré (" & $iDestFiles & "/" & $iSourceFiles & " fichiers)", "Restauration", "Erreur")
    EndIf
EndFunc

; Fonction pour restaurer les favoris des navigateurs
Func _RestoreBrowserBookmarks($sRestoreFolder)
    Local $sBrowserRestore = $sRestoreFolder & "\Favoris_Navigateurs"

    If Not FileExists($sBrowserRestore) Then
        _Log("    Aucun favori de navigateur à restaurer", "Restauration", "Navigateurs")
        Return
    EndIf

    ; Restaurer Internet Explorer
    If FileExists($sBrowserRestore & "\Internet_Explorer") Then
        _Log("    Restauration favoris Internet Explorer...", "Restauration", "Navigateurs")
        DirCopy($sBrowserRestore & "\Internet_Explorer", @UserProfileDir & "\Favorites", $FC_OVERWRITE)
    EndIf

    ; Restaurer Google Chrome
    If FileExists($sBrowserRestore & "\Google_Chrome") Then
        _Log("    Restauration favoris Google Chrome...", "Restauration", "Navigateurs")
        Local $sChromePath = @LocalAppDataDir & "\Google\Chrome\User Data\Default"
        DirCreate($sChromePath)
        FileCopy($sBrowserRestore & "\Google_Chrome\Bookmarks", $sChromePath & "\Bookmarks", $FC_OVERWRITE)
        FileCopy($sBrowserRestore & "\Google_Chrome\Preferences", $sChromePath & "\Preferences", $FC_OVERWRITE)
    EndIf

    ; Restaurer Mozilla Firefox
    If FileExists($sBrowserRestore & "\Mozilla_Firefox") Then
        _Log("    Restauration favoris Mozilla Firefox...", "Restauration", "Navigateurs")
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
        _Log("    Restauration favoris Microsoft Edge...", "Restauration", "Navigateurs")
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
        _Log("    Aucun mot de passe Firefox à restaurer", "Restauration", "Navigateurs")
        Return
    EndIf

    Local $sFirefoxPath = @AppDataDir & "\Mozilla\Firefox\Profiles"
    If Not FileExists($sFirefoxPath) Then
        _Log("    Firefox non installé - impossible de restaurer les mots de passe", "Restauration", "Navigateurs")
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
                _Log("    ✓ logins.json restauré", "Restauration", "Navigateurs")
            EndIf

            If FileExists($sFirefoxRestore & "\key4.db") Then
                FileCopy($sFirefoxRestore & "\key4.db", $sProfilePath & "\key4.db", $FC_OVERWRITE)
                _Log("    ✓ key4.db restauré", "Restauration", "Navigateurs")
            EndIf

            If FileExists($sFirefoxRestore & "\key3.db") Then
                FileCopy($sFirefoxRestore & "\key3.db", $sProfilePath & "\key3.db", $FC_OVERWRITE)
                _Log("    ✓ key3.db restauré", "Restauration", "Navigateurs")
            EndIf

            If FileExists($sFirefoxRestore & "\prefs.js") Then
                FileCopy($sFirefoxRestore & "\prefs.js", $sProfilePath & "\prefs.js", $FC_OVERWRITE)
                _Log("    ✓ prefs.js restauré", "Restauration", "Navigateurs")
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
        _Log("    Aucune donnée Thunderbird à restaurer", "Restauration", "Thunderbird")
        Return
    EndIf

    ; Restaurer le dossier Roaming
    If FileExists($sThunderbirdRestore & "\Roaming") Then
        _Log("    Restauration Thunderbird Roaming...", "Restauration", "Thunderbird")
        DirCopy($sThunderbirdRestore & "\Roaming", @AppDataDir & "\Thunderbird", $FC_OVERWRITE)
    EndIf

    ; Restaurer le dossier Local
    If FileExists($sThunderbirdRestore & "\Local") Then
        _Log("    Restauration Thunderbird Local...", "Restauration", "Thunderbird")
        DirCopy($sThunderbirdRestore & "\Local", @LocalAppDataDir & "\Thunderbird", $FC_OVERWRITE)
    EndIf

    _Log("    ✓ Thunderbird restauré", "Restauration", "Thunderbird")
EndFunc

; Fonction pour restaurer le fond d'écran
Func _RestoreWallpaper($sRestoreFolder)
    Local $sWallpaperRestore = $sRestoreFolder & "\Fond_Ecran"

    If Not FileExists($sWallpaperRestore) Then
        _Log("    Aucun fond d'écran à restaurer", "Restauration", "Système")
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

            _Log("    ✓ Fond d'écran restauré: " & $sFileName, "Restauration", "Système")
        EndIf
    EndIf
EndFunc

; Fonction pour restaurer les imprimantes
Func _RestorePrinters($sRestoreFolder)
    Local $sPrintersRestore = $sRestoreFolder & "\Imprimantes"

    If Not FileExists($sPrintersRestore) Then
        _Log("    Aucune imprimante à restaurer", "Restauration", "Système")
        Return
    EndIf

    _Log("    ATTENTION: La restauration des imprimantes nécessite des droits administrateur", "Restauration", "Attention")
    _Log("    Les pilotes doivent être installés manuellement si nécessaire", "Restauration", "Info")

    _Log("    ℹ Consultez les fichiers suivants pour la restauration manuelle:", "Restauration", "Info")
    _Log("      - printers_list.txt (liste des imprimantes)", "Restauration", "Info")
    _Log("      - printers_wmic.txt (informations détaillées)", "Restauration", "Info")
EndFunc

; Fonction pour restaurer Steam
Func _RestoreSteam($sRestoreFolder)
    Local $sSteamRestore = $sRestoreFolder & "\Steam"

    If Not FileExists($sSteamRestore) Then
        _Log("    Aucune donnée Steam à restaurer", "Restauration", "Steam")
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
        _Log("    Steam non installé, impossible de restaurer", "Restauration", "Steam")
        Return
    EndIf

    _Log("    Steam trouvé: " & $sSteamPath, "Restauration", "Steam")

    ; Restaurer la configuration
    If FileExists($sSteamRestore & "\config") Then
        DirCopy($sSteamRestore & "\config", $sSteamPath & "\config", $FC_OVERWRITE)
        _Log("    ✓ Configuration Steam restaurée", "Restauration", "Steam")
    EndIf

    ; Restaurer les données utilisateur
    If FileExists($sSteamRestore & "\userdata") Then
        DirCopy($sSteamRestore & "\userdata", $sSteamPath & "\userdata", $FC_OVERWRITE)
        _Log("    ✓ Données utilisateur Steam restaurées", "Restauration", "Steam")
    EndIf

    ; Restaurer les fichiers ACF
    If FileExists($sSteamRestore & "\steamapps") Then
        Local $aAcfFiles = _FileListToArray($sSteamRestore & "\steamapps", "*.acf", $FLTA_FILES)
        If IsArray($aAcfFiles) Then
            For $i = 1 To $aAcfFiles[0]
                FileCopy($sSteamRestore & "\steamapps\" & $aAcfFiles[$i], $sSteamPath & "\steamapps\" & $aAcfFiles[$i], $FC_OVERWRITE)
            Next
            _Log("    ✓ Fichiers ACF restaurés (" & $aAcfFiles[0] & " fichiers)", "Restauration", "Steam")
        EndIf
    EndIf

    _Log("    ℹ Redémarrez Steam pour appliquer les changements", "Restauration", "Info")
EndFunc

; Fonction pour restaurer les paramètres WiFi
Func _RestoreWifi($sRestoreFolder)
    Local $sWifiRestore = $sRestoreFolder & "\Parametres_WiFi"

    If Not FileExists($sWifiRestore) Then
        _Log("    Aucun paramètre WiFi à restaurer", "Restauration", "Système")
        Return
    EndIf

    _Log("    ATTENTION: Nécessite des droits administrateur", "Restauration", "Attention")

    ; Importer tous les profils WiFi XML
    Local $aWifiFiles = _FileListToArray($sWifiRestore, "*.xml", $FLTA_FILES)
    If IsArray($aWifiFiles) Then
        For $i = 1 To $aWifiFiles[0]
            RunWait('netsh wlan add profile filename="' & $sWifiRestore & '\' & $aWifiFiles[$i] & '"', "", @SW_HIDE)
        Next
        _Log("    ✓ " & $aWifiFiles[0] & " profil(s) WiFi restauré(s)", "Restauration", "Système")
    Else
        _Log("    Aucun profil WiFi à restaurer", "Restauration", "Système")
    EndIf

    _Log("    ℹ Les mots de passe WiFi peuvent nécessiter une saisie manuelle", "Restauration", "Info")
EndFunc

; Fonction pour restaurer les paramètres système
Func _RestoreSystemSettings($sRestoreFolder)
    Local $sSystemRestore = $sRestoreFolder & "\Parametres_Systeme"

    If Not FileExists($sSystemRestore) Then
        _Log("    Aucun paramètre système à restaurer", "Restauration", "Système")
        Return
    EndIf

    ; Importer les clés de registre
    _Log("    Import des clés de registre...", "Restauration", "Système")
    If FileExists($sSystemRestore & "\Explorer_Settings.reg") Then
        RunWait('reg import "' & $sSystemRestore & '\Explorer_Settings.reg"', "", @SW_HIDE)
    EndIf
    If FileExists($sSystemRestore & "\Desktop_Settings.reg") Then
        RunWait('reg import "' & $sSystemRestore & '\Desktop_Settings.reg"', "", @SW_HIDE)
    EndIf
EndFunc