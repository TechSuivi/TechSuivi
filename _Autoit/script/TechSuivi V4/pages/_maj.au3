#cs ----------------------------------------------------------------------------

 AutoIt Version: 3.3.16.1
 Author:         myName

 Script Function:
	Gestion des mises à jour Windows - Intégré dans TechSuivi V4

#ce ----------------------------------------------------------------------------

; Variables globales pour la page de mise à jour
Global $idProgressWU ; Barre de progression Windows Update
Global $idStatusLabelWU ; Label de statut Windows Update
Global $button_start_wu ; Bouton de démarrage des mises à jour
Global $button_search_wu ; Bouton de recherche des mises à jour
Global $button_cancel_wu ; Bouton d'annulation
Global $g_aHiddenUpdates[0] ; Tableau pour les mises à jour cachées
Global $g_bUpdateInProgress = False ; Flag pour indiquer si une mise à jour est en cours
Global $checkbox_auto_install ; Case à cocher pour installation automatique
Global $checkbox_auto_reboot ; Case à cocher pour redémarrage automatique

Func _maj()
    Local $coord1 = 20
    Local $coord2 = 40
    
    ; === GROUPE UNIQUE POUR TOUT ===
    Local $groupHeight = 280
    If Not IsAdmin() Then $groupHeight = 330 ; Plus de hauteur si avertissement admin
    
    Local $idGroupMain = GUICtrlCreateGroup("Windows Update", $coord1, $coord2, 650, $groupHeight)
    GUICtrlSetFont($idGroupMain, 9, 600) ; Police en gras pour le groupe
    
    Local $groupCoord = $coord2 + 20 ; Coordonnée Y à l'intérieur du groupe
    
    ; Boutons de contrôle
    $button_search_wu = GUICtrlCreateButton("Rechercher les mises à jour", $coord1 + 15, $groupCoord, 150, 30)
    GUICtrlSetOnEvent($button_search_wu, "_SearchWindowsUpdates")
    
    $button_start_wu = GUICtrlCreateButton("Installer les mises à jour", $coord1 + 175, $groupCoord, 150, 30)
    GUICtrlSetOnEvent($button_start_wu, "_StartWindowsUpdate")
    GUICtrlSetState($button_start_wu, $GUI_DISABLE) ; Désactivé par défaut
    
    $button_cancel_wu = GUICtrlCreateButton("Annuler", $coord1 + 335, $groupCoord, 80, 30)
    GUICtrlSetOnEvent($button_cancel_wu, "_CancelWindowsUpdate")
    GUICtrlSetState($button_cancel_wu, $GUI_DISABLE) ; Désactivé par défaut
    
    $groupCoord += 40
    
    ; Cases à cocher pour les options automatiques
    $checkbox_auto_install = GUICtrlCreateCheckbox("Installer automatiquement après la recherche", $coord1 + 15, $groupCoord, 300, 20)
    GUICtrlSetTip($checkbox_auto_install, "Si cochée, l'installation démarrera automatiquement après la recherche des mises à jour")
    
    $checkbox_auto_reboot = GUICtrlCreateCheckbox("Redémarrer automatiquement si nécessaire", $coord1 + 330, $groupCoord, 300, 20)
    GUICtrlSetTip($checkbox_auto_reboot, "Si cochée, le système redémarrera automatiquement si nécessaire après l'installation")
    
    $groupCoord += 30
    
    ; Séparateur visuel
    GUICtrlCreateLabel("", $coord1 + 15, $groupCoord, 620, 1, $SS_ETCHEDHORZ)
    $groupCoord += 15
    
    ; Barre de progression
    GUICtrlCreateLabel("Progression :", $coord1 + 15, $groupCoord, 80, 15)
    $idProgressWU = GUICtrlCreateProgress($coord1 + 100, $groupCoord, 535, 20)
    GUICtrlSetData($idProgressWU, 0)
    
    $groupCoord += 30
    
    ; Label de statut
    GUICtrlCreateLabel("Statut :", $coord1 + 15, $groupCoord, 50, 15)
    $idStatusLabelWU = GUICtrlCreateLabel("Prêt - Cliquez sur 'Rechercher les mises à jour' pour commencer", $coord1 + 70, $groupCoord, 565, 15, $SS_LEFT)
    
    $groupCoord += 30
    
    ; Séparateur visuel
    GUICtrlCreateLabel("", $coord1 + 15, $groupCoord, 620, 1, $SS_ETCHEDHORZ)
    $groupCoord += 15
    
    ; Informations
    GUICtrlCreateLabel("Informations :", $coord1 + 15, $groupCoord, 80, 15)
    GUICtrlSetFont(-1, 8, 600) ; Petit titre en gras
    $groupCoord += 20
    
    GUICtrlCreateLabel("• Les mises à jour seront affichées dans la zone de log en bas de l'application.", $coord1 + 15, $groupCoord, 620, 15, $SS_LEFT)
    $groupCoord += 18
    GUICtrlCreateLabel("• Un redémarrage peut être nécessaire après l'installation des mises à jour.", $coord1 + 15, $groupCoord, 620, 15, $SS_LEFT)
    
    ; Vérifier les privilèges administrateur
    If Not IsAdmin() Then
        $groupCoord += 25
        
        ; Séparateur visuel
        GUICtrlCreateLabel("", $coord1 + 15, $groupCoord, 620, 1, $SS_ETCHEDHORZ)
        $groupCoord += 15
        
        ; Avertissement admin
        Local $idWarningLabel = GUICtrlCreateLabel("⚠ ATTENTION: Privilèges administrateur requis pour utiliser cette fonctionnalité", $coord1 + 15, $groupCoord, 620, 20, $SS_LEFT)
        GUICtrlSetColor($idWarningLabel, 0xFF0000) ; Rouge
        GUICtrlSetFont($idWarningLabel, 9, 600) ; Gras
        
        GUICtrlSetData($idStatusLabelWU, "ATTENTION: Privilèges administrateur requis")
        GUICtrlSetColor($idStatusLabelWU, 0xFF0000) ; Rouge
        GUICtrlSetState($button_start_wu, $GUI_DISABLE)
        GUICtrlSetState($button_search_wu, $GUI_DISABLE)
    EndIf
EndFunc

; Fonction pour rechercher les mises à jour
Func _SearchWindowsUpdates()
    If $g_bUpdateInProgress Then Return
    
    $g_bUpdateInProgress = True
    GUICtrlSetState($button_search_wu, $GUI_DISABLE)
    GUICtrlSetState($button_start_wu, $GUI_DISABLE)
    GUICtrlSetState($button_cancel_wu, $GUI_ENABLE)
    
    ; Démarrer la recherche dans un thread séparé
    AdlibRegister("_PerformUpdateSearch", 100)
EndFunc

; Fonction pour démarrer l'installation des mises à jour
Func _StartWindowsUpdate()
    If $g_bUpdateInProgress Then Return
    
    $g_bUpdateInProgress = True
    GUICtrlSetState($button_search_wu, $GUI_DISABLE)
    GUICtrlSetState($button_start_wu, $GUI_DISABLE)
    GUICtrlSetState($button_cancel_wu, $GUI_ENABLE)
    
    ; Démarrer l'installation dans un thread séparé
    AdlibRegister("_PerformWindowsUpdate", 100)
EndFunc

; Fonction pour annuler l'opération
Func _CancelWindowsUpdate()
    $g_bUpdateInProgress = False
    AdlibUnRegister("_PerformUpdateSearch")
    AdlibUnRegister("_PerformWindowsUpdate")
    
    GUICtrlSetState($button_search_wu, $GUI_ENABLE)
    GUICtrlSetState($button_start_wu, $GUI_DISABLE)
    GUICtrlSetState($button_cancel_wu, $GUI_DISABLE)
    
    _UpdateStatusWU("Opération annulée")
    _UpdateProgressWU(0)
    _Log("Opération de mise à jour annulée par l'utilisateur", "Windows Update", "Annulation")
EndFunc

; Fonction pour effectuer la recherche de mises à jour
Func _PerformUpdateSearch()
    AdlibUnRegister("_PerformUpdateSearch")
    
    ; Enregistrer le gestionnaire d'erreur COM
    Local $oErrorHandler = ObjEvent("AutoIt.Error", "_ComErrorHandlerWU")
    
    ; Initialiser Windows Update
    _UpdateStatusWU("Initialisation de Windows Update...")
    _UpdateProgressWU(5)
    _Log("=== RECHERCHE DES MISES À JOUR WINDOWS ===", "Windows Update", "Recherche")
    _Log("Initialisation de Windows Update...", "Windows Update", "Recherche")
    
    ; Créer la session avec gestion d'erreur
    Local $updateSession = _SafeCreateObjectWU("Microsoft.Update.Session")
    If @error Then
        _Log("ERREUR: Impossible de créer l'objet session de mise à jour.", "Windows Update", "Erreur")
        _UpdateStatusWU("Erreur d'initialisation")
        _UpdateProgressWU(0)
        _ResetButtonsWU()
        Return
    EndIf
    
    Sleep(500)
    
    ; Créer le chercheur avec gestion d'erreur
    Local $updateSearcher = $updateSession.CreateUpdateSearcher()
    If @error Then
        _Log("ERREUR: Impossible de créer l'objet de recherche de mise à jour.", "Windows Update", "Erreur")
        _UpdateStatusWU("Erreur d'initialisation")
        _UpdateProgressWU(0)
        _ResetButtonsWU()
        Return
    EndIf
    
    Sleep(500)
    
    _Log("Recherche des mises à jour disponibles...", "Windows Update", "Recherche")
    _UpdateStatusWU("Recherche des mises à jour...")
    _UpdateProgressWU(20)
    
    ; Rechercher les mises à jour
    Local $searchResult = $updateSearcher.Search("IsInstalled=0 and IsHidden=0")
    If @error Then
        _Log("ERREUR: La recherche de mises à jour a échoué. Code d'erreur: 0x" & Hex(@error), "Windows Update", "Erreur")
        _UpdateStatusWU("Erreur de recherche")
        _UpdateProgressWU(0)
        _ResetButtonsWU()
        Return
    EndIf
    
    _Log("Recherche terminée avec succès.", "Windows Update", "Recherche")
    _UpdateProgressWU(50)
    
    ; Rechercher les mises à jour cachées
    _Log("Recherche des mises à jour cachées...", "Windows Update", "Recherche")
    Local $hiddenResult = $updateSearcher.Search("IsInstalled=0 and IsHidden=1")
    If Not @error And $hiddenResult.Updates.Count > 0 Then
        _Log("Trouvé " & $hiddenResult.Updates.Count & " mise(s) à jour cachée(s)", "Windows Update", "Recherche")
        ReDim $g_aHiddenUpdates[$hiddenResult.Updates.Count]
        For $i = 0 To $hiddenResult.Updates.Count - 1
            $g_aHiddenUpdates[$i] = $hiddenResult.Updates.Item($i)
            _Log("Mise à jour cachée trouvée: " & $g_aHiddenUpdates[$i].Title, "Windows Update", "Recherche")
        Next
    EndIf
    
    ; Afficher les résultats
    _Log("Mises à jour disponibles sur cette machine:", "Windows Update", "Recherche")
    _UpdateStatusWU("Trouvé " & $searchResult.Updates.Count & " mise(s) à jour")
    _UpdateProgressWU(100)
    
    If $searchResult.Updates.Count = 0 Then
        _Log("Aucune mise à jour applicable trouvée.", "Windows Update", "Recherche")
        _UpdateStatusWU("Aucune mise à jour disponible")
        _ResetButtonsWU()
        Return
    EndIf
    
    ; Lister toutes les mises à jour disponibles
    For $i = 0 To $searchResult.Updates.Count - 1
        Local $update = $searchResult.Updates.Item($i)
        _Log(($i + 1) & "> " & $update.Title, "Windows Update", "Recherche")
    Next
    
    ; Activer le bouton d'installation
    GUICtrlSetState($button_start_wu, $GUI_ENABLE)
    _UpdateStatusWU("Prêt à installer " & $searchResult.Updates.Count & " mise(s) à jour")
    
    ; Vérifier si l'installation automatique est activée
    If GUICtrlRead($checkbox_auto_install) = $GUI_CHECKED Then
        _Log("Installation automatique activée - Démarrage de l'installation...", "Windows Update", "Installation")
        _UpdateStatusWU("Installation automatique en cours...")
        
        ; Nettoyer les objets COM avant de lancer l'installation
        $updateSearcher = 0
        $updateSession = 0
        
        ; Lancer l'installation automatiquement avec un délai
        AdlibRegister("_PerformWindowsUpdate", 1000)
        Return
    EndIf
    
    _ResetButtonsWU(True) ; True = garder le bouton d'installation activé
    
    ; Nettoyer les objets COM
    $updateSearcher = 0
    $updateSession = 0
EndFunc

; Fonction pour effectuer l'installation des mises à jour
Func _PerformWindowsUpdate()
    AdlibUnRegister("_PerformWindowsUpdate")
    
    ; Enregistrer le gestionnaire d'erreur COM
    Local $oErrorHandler = ObjEvent("AutoIt.Error", "_ComErrorHandlerWU")
    
    _Log("=== INSTALLATION DES MISES À JOUR WINDOWS ===", "Windows Update", "Installation")
    _UpdateStatusWU("Initialisation de l'installation...")
    _UpdateProgressWU(5)
    
    ; Créer la session
    Local $updateSession = _SafeCreateObjectWU("Microsoft.Update.Session")
    If @error Then
        _Log("ERREUR: Impossible de créer l'objet session de mise à jour.", "Windows Update", "Erreur")
        _UpdateStatusWU("Erreur")
        _UpdateProgressWU(0)
        _ResetButtonsWU()
        Return
    EndIf
    
    ; Rechercher à nouveau les mises à jour pour l'installation
    Local $updateSearcher = $updateSession.CreateUpdateSearcher()
    If @error Then
        _Log("ERREUR: Impossible de créer l'objet de recherche.", "Windows Update", "Erreur")
        _UpdateStatusWU("Erreur")
        _UpdateProgressWU(0)
        _ResetButtonsWU()
        Return
    EndIf
    
    _UpdateStatusWU("Recherche des mises à jour à installer...")
    _UpdateProgressWU(10)
    
    Local $searchResult = $updateSearcher.Search("IsInstalled=0 and IsHidden=0")
    If @error Then
        _Log("ERREUR: Échec de la recherche de mises à jour.", "Windows Update", "Erreur")
        _UpdateStatusWU("Erreur")
        _UpdateProgressWU(0)
        _ResetButtonsWU()
        Return
    EndIf
    
    If $searchResult.Updates.Count = 0 Then
        _Log("Aucune mise à jour à installer.", "Windows Update", "Installation")
        _UpdateStatusWU("Aucune mise à jour à installer")
        _UpdateProgressWU(100)
        _ResetButtonsWU()
        Return
    EndIf
    
    _Log("Traitement de " & $searchResult.Updates.Count & " mise(s) à jour...", "Windows Update", "Installation")
    _UpdateStatusWU("Préparation des mises à jour...")
    _UpdateProgressWU(20)
    
    ; Créer la collection pour le téléchargement
    Local $updatesToDownload = _SafeCreateObjectWU("Microsoft.Update.UpdateColl")
    If @error Then
        _Log("ERREUR: Impossible de créer la collection de mises à jour.", "Windows Update", "Erreur")
        _UpdateStatusWU("Erreur")
        _UpdateProgressWU(0)
        _ResetButtonsWU()
        Return
    EndIf
    
    ; Ajouter toutes les mises à jour à la collection
    Local $addedCount = 0
    For $i = 0 To $searchResult.Updates.Count - 1
        Local $update = $searchResult.Updates.Item($i)
        
        ; Accepter l'EULA si nécessaire
        If $update.EulaAccepted = False Then
            _Log("Acceptation de l'EULA pour: " & $update.Title, "Windows Update", "Installation")
            $update.AcceptEula()
            If @error Then
                _Log("ATTENTION: Échec de l'acceptation de l'EULA pour cette mise à jour", "Windows Update", "Attention")
            EndIf
        EndIf
        
        ; Ajouter la mise à jour à la collection
        _Log("Ajout au téléchargement: " & $update.Title, "Windows Update", "Téléchargement")
        $updatesToDownload.Add($update)
        If @error Then
            _Log("ERREUR: Échec de l'ajout de la mise à jour à la collection", "Windows Update", "Erreur")
        Else
            $addedCount += 1
        EndIf
    Next
    
    If $addedCount = 0 Then
        _Log("ERREUR: Aucune mise à jour n'a pu être ajoutée à la collection.", "Windows Update", "Erreur")
        _UpdateStatusWU("Erreur")
        _UpdateProgressWU(0)
        _ResetButtonsWU()
        Return
    EndIf
    
    _Log("Ajouté avec succès " & $addedCount & " mise(s) à jour à la collection.", "Windows Update", "Téléchargement")
    
    ; Télécharger les mises à jour
    _Log("Téléchargement des mises à jour...", "Windows Update", "Téléchargement")
    _UpdateStatusWU("Téléchargement des mises à jour...")
    _UpdateProgressWU(40)
    
    Local $downloader = $updateSession.CreateUpdateDownloader()
    If @error Then
        _Log("ERREUR: Impossible de créer l'objet de téléchargement.", "Windows Update", "Erreur")
        _UpdateStatusWU("Erreur")
        _UpdateProgressWU(0)
        _ResetButtonsWU()
        Return
    EndIf
    
    $downloader.Updates = $updatesToDownload
    
    Local $downloadResult = $downloader.Download()
    If @error Then
        _Log("ERREUR: Le téléchargement a échoué avec l'erreur: 0x" & Hex(@error), "Windows Update", "Erreur")
        _Log("Tentative de méthode alternative...", "Windows Update", "Téléchargement")
        
        ; Méthode alternative avec PowerShell
        Local $cmd = 'powershell.exe -Command "& {Start-Process wuauclt.exe -ArgumentList "/detectnow /updatenow" -Wait}"'
        RunWait($cmd, "", @SW_HIDE)
    Else
        _Log("Résultat du téléchargement: " & $downloadResult.ResultCode, "Windows Update", "Téléchargement")
    EndIf
    
    _Log("Téléchargement terminé", "Windows Update", "Téléchargement")
    _UpdateStatusWU("Téléchargement terminé")
    _UpdateProgressWU(60)
    
    ; Vérifier quelles mises à jour ont été téléchargées
    Local $downloadedCount = 0
    For $i = 0 To $searchResult.Updates.Count - 1
        Local $update = $searchResult.Updates.Item($i)
        If $update.IsDownloaded Then
            _Log("Téléchargé: " & $update.Title, "Windows Update", "Téléchargement")
            $downloadedCount += 1
        EndIf
    Next
    
    _Log("Total des mises à jour téléchargées: " & $downloadedCount, "Windows Update", "Téléchargement")
    
    If $downloadedCount = 0 Then
        _Log("ERREUR: Aucune mise à jour n'a été téléchargée avec succès.", "Windows Update", "Erreur")
        _UpdateStatusWU("Échec du téléchargement")
        _UpdateProgressWU(0)
        _ResetButtonsWU()
        Return
    EndIf
    
    ; Créer la collection pour l'installation
    Local $updatesToInstall = _SafeCreateObjectWU("Microsoft.Update.UpdateColl")
    If @error Then
        _Log("ERREUR: Impossible de créer la collection d'installation.", "Windows Update", "Erreur")
        _UpdateStatusWU("Erreur")
        _UpdateProgressWU(0)
        _ResetButtonsWU()
        Return
    EndIf
    
    ; Ajouter les mises à jour téléchargées à la collection d'installation
    Local $installCount = 0
    For $i = 0 To $searchResult.Updates.Count - 1
        Local $update = $searchResult.Updates.Item($i)
        If $update.IsDownloaded Then
            _Log("Ajout à l'installation: " & $update.Title, "Windows Update", "Installation")
            $updatesToInstall.Add($update)
            If @error Then
                _Log("ERREUR: Échec de l'ajout à la collection d'installation", "Windows Update", "Erreur")
            Else
                $installCount += 1
            EndIf
        EndIf
    Next
    
    If $installCount = 0 Then
        _Log("ERREUR: Aucune mise à jour n'a pu être ajoutée à la collection d'installation.", "Windows Update", "Erreur")
        _UpdateStatusWU("Erreur")
        _UpdateProgressWU(0)
        _ResetButtonsWU()
        Return
    EndIf
    
    _Log("Installation de " & $installCount & " mise(s) à jour...", "Windows Update", "Installation")
    _UpdateStatusWU("Installation des mises à jour...")
    _UpdateProgressWU(80)
    
    ; Créer l'installateur
    Local $installer = $updateSession.CreateUpdateInstaller()
    If @error Then
        _Log("ERREUR: Impossible de créer l'objet d'installation.", "Windows Update", "Erreur")
        _UpdateStatusWU("Erreur")
        _UpdateProgressWU(0)
        _ResetButtonsWU()
        Return
    EndIf
    
    $installer.Updates = $updatesToInstall
    
    ; Installer les mises à jour
    Local $installationResult = $installer.Install()
    If @error Then
        _Log("ERREUR: L'installation a échoué avec l'erreur: 0x" & Hex(@error), "Windows Update", "Erreur")
        _UpdateStatusWU("Échec de l'installation")
        _UpdateProgressWU(0)
        _ResetButtonsWU()
        Return
    EndIf
    
    ; Afficher les résultats de l'installation
    _UpdateStatusWU("Installation terminée")
    _UpdateProgressWU(100)
    _Log("Installation terminée", "Windows Update", "Installation")
    _Log("Résultat de l'installation: " & $installationResult.ResultCode, "Windows Update", "Installation")
    
    ; Lister les résultats pour chaque mise à jour
    Local $successCount = 0
    Local $failCount = 0
    
    For $i = 0 To $updatesToInstall.Count - 1
        Local $update = $updatesToInstall.Item($i)
        Local $updateResult = $installationResult.GetUpdateResult($i)
        _Log(($i + 1) & "> " & $update.Title & ": " & $updateResult.ResultCode, "Windows Update", "Installation")
        
        If $updateResult.ResultCode = 2 Then ; 2 = Succès
            $successCount += 1
        Else
            $failCount += 1
        EndIf
    Next
    
    _Log("", "Windows Update", "Résumé")
    _Log("Résumé de l'installation:", "Windows Update", "Résumé")
    _Log("Installées avec succès: " & $successCount & " mise(s) à jour", "Windows Update", "Résumé")
    _Log("Échec d'installation: " & $failCount & " mise(s) à jour", "Windows Update", "Résumé")
    
    ; Afficher les mises à jour cachées si trouvées
    If UBound($g_aHiddenUpdates) > 0 Then
        _Log("", "Windows Update", "Résumé")
        _Log("Mises à jour cachées (non installées):", "Windows Update", "Résumé")
        For $i = 0 To UBound($g_aHiddenUpdates) - 1
            If IsObj($g_aHiddenUpdates[$i]) Then
                _Log("!!! MISE À JOUR CACHÉE !!! " & $g_aHiddenUpdates[$i].Title, "Windows Update", "Attention")
            EndIf
        Next
    EndIf
    
    ; Vérifier si un redémarrage est requis
    If $installationResult.RebootRequired Then
        _Log("", "Windows Update", "Redémarrage")
        _Log("REDÉMARRAGE REQUIS pour terminer l'installation.", "Windows Update", "Redémarrage")
        _UpdateStatusWU("Installation terminée - Redémarrage requis")
        
        ; Vérifier si le redémarrage automatique est activé
        If GUICtrlRead($checkbox_auto_reboot) = $GUI_CHECKED Then
            _Log("Redémarrage automatique activé - Redémarrage dans 10 secondes...", "Windows Update", "Redémarrage")
            _UpdateStatusWU("Redémarrage automatique dans 10 secondes...")
            
            ; Compte à rebours de 10 secondes
            For $i = 10 To 1 Step -1
                _UpdateStatusWU("Redémarrage automatique dans " & $i & " seconde(s)...")
                Sleep(1000)
            Next
            
            _Log("Redémarrage du système...", "Windows Update", "Redémarrage")
            Shutdown($SD_REBOOT)
        Else
            Local $rebootMsg = "L'installation des mises à jour est terminée." & @CRLF & @CRLF
            $rebootMsg &= "Un redémarrage est requis pour terminer l'installation." & @CRLF
            $rebootMsg &= "Voulez-vous redémarrer maintenant ?"
            
            Local $result = MsgBox($MB_YESNO + $MB_ICONQUESTION, "Redémarrage requis", $rebootMsg)
            If $result = $IDYES Then
                _Log("Redémarrage du système...", "Windows Update", "Redémarrage")
                Shutdown($SD_REBOOT)
            Else
                _Log("Redémarrage reporté par l'utilisateur.", "Windows Update", "Redémarrage")
            EndIf
        EndIf
    Else
        _Log("", "Windows Update", "Installation")
        _Log("Installation terminée avec succès.", "Windows Update", "Installation")
        If UBound($g_aHiddenUpdates) > 0 Then
            _Log(UBound($g_aHiddenUpdates) & " mise(s) à jour cachée(s) ont été trouvées mais non installées.", "Windows Update", "Attention")
        EndIf
        
        _UpdateStatusWU("Installation terminée avec succès")
        
        ; Vérifier si l'installation automatique est activée pour relancer un scan
        If GUICtrlRead($checkbox_auto_install) = $GUI_CHECKED Then
            _Log("Recherche automatique de nouvelles mises à jour...", "Windows Update", "Recherche")
            _UpdateStatusWU("Recherche de nouvelles mises à jour...")
            
            ; Nettoyer les objets COM
            $updatesToDownload = 0
            $downloader = 0
            $updatesToInstall = 0
            $installer = 0
            $updateSearcher = 0
            $updateSession = 0
            
            ; Relancer une recherche automatique après 2 secondes
            AdlibRegister("_PerformUpdateSearch", 2000)
            Return
        Else
            Local $message = "Toutes les mises à jour ont été installées avec succès."
            If UBound($g_aHiddenUpdates) > 0 Then
                $message &= @CRLF & @CRLF & UBound($g_aHiddenUpdates) & " mise(s) à jour cachée(s) ont été trouvées mais non installées."
                $message &= @CRLF & "Voir le log pour les détails."
            EndIf
            MsgBox($MB_ICONINFORMATION, "Installation terminée", $message)
        EndIf
    EndIf
    
    ; Nettoyer les objets COM
    $updatesToDownload = 0
    $downloader = 0
    $updatesToInstall = 0
    $installer = 0
    $updateSearcher = 0
    $updateSession = 0
    
    _ResetButtonsWU()
EndFunc

; Fonction pour créer des objets COM de manière sécurisée
Func _SafeCreateObjectWU($progID, $maxRetries = 3)
    Local $object = Null
    Local $retryCount = 0
    
    While $retryCount < $maxRetries
        $object = ObjCreate($progID)
        If IsObj($object) Then
            Return $object
        EndIf
        
        _Log("Échec de création de l'objet COM: " & $progID & " (Tentative " & ($retryCount + 1) & " sur " & $maxRetries & ")", "Windows Update", "Erreur")
        Sleep(1000)
        $retryCount += 1
    WEnd
    
    _Log("ERREUR: Échec de création de l'objet COM après " & $maxRetries & " tentatives: " & $progID, "Windows Update", "Erreur")
    SetError(1, 0, "Échec de création de l'objet COM: " & $progID)
    Return Null
EndFunc

; Gestionnaire d'erreur COM
Func _ComErrorHandlerWU($oError)
    _Log("Erreur COM: " & $oError.description & " (0x" & Hex($oError.number) & ")", "Windows Update", "Erreur")
    _Log("Source: " & $oError.source, "Windows Update", "Erreur")
    _Log("Ligne du script: " & $oError.scriptline, "Windows Update", "Erreur")
    
    ; Gestion spéciale pour l'erreur 80020009
    If $oError.number = 0x80020009 Then
        _Log("Gestion de l'erreur spéciale 80020009 - Exception survenue", "Windows Update", "Erreur")
        _Log("Cette erreur survient souvent avec les objets COM Windows Update", "Windows Update", "Erreur")
        _Log("Tentative de continuation de l'opération...", "Windows Update", "Erreur")
        
        SetError(2, $oError.number, $oError.description)
        Return
    EndIf
    
    SetError(1, $oError.number, $oError.description)
    Return
EndFunc



; Fonction pour mettre à jour le statut
Func _UpdateStatusWU($status)
    GUICtrlSetData($idStatusLabelWU, $status)
EndFunc

; Fonction pour mettre à jour la barre de progression
Func _UpdateProgressWU($percent)
    GUICtrlSetData($idProgressWU, $percent)
EndFunc

; Fonction pour réinitialiser les boutons
Func _ResetButtonsWU($keepInstallEnabled = False)
    $g_bUpdateInProgress = False
    GUICtrlSetState($button_search_wu, $GUI_ENABLE)
    GUICtrlSetState($button_cancel_wu, $GUI_DISABLE)
    
    If $keepInstallEnabled Then
        GUICtrlSetState($button_start_wu, $GUI_ENABLE)
    Else
        GUICtrlSetState($button_start_wu, $GUI_DISABLE)
    EndIf
EndFunc