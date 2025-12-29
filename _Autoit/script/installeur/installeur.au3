#RequireAdmin
#include <Array.au3>
#include <File.au3>
#include <String.au3>
#include <Date.au3>
#include <StringConstants.au3>
#include <GUIConstants.au3>
#include <GuiEdit.au3>
#include <ScrollBarsConstants.au3>

; ===== Configuration =====
Global Const $MANIFEST_FILE = "files.txt"  ; Liste des fichiers à télécharger
Global Const $INSTALL_PATH = "C:\TechSuivi"

; ===== Variables GUI =====
Global $hGUI = 0
Global $hEdit = 0
Global $bGUICreated = False

; ===== Fonctions GUI =====
Func _CreateLogGUI()
    If $bGUICreated Then Return
    
    $hGUI = GUICreate("TechSuivi - Installeur", 800, 600, -1, -1)
    $hEdit = GUICtrlCreateEdit("", 10, 10, 780, 550, BitOR($ES_MULTILINE, $ES_READONLY, $ES_AUTOVSCROLL, $WS_VSCROLL))
    GUICtrlSetFont($hEdit, 9, 400, 0, "Consolas")
    
    GUISetState(@SW_SHOW, $hGUI)
    $bGUICreated = True
    
    _LogToGUI("=== TECHSUIVI INSTALLEUR ===" & @CRLF)
    _LogToGUI("Interface de logging initialisée" & @CRLF & @CRLF)
EndFunc

Func _LogToGUI($sMessage)
    If Not $bGUICreated Then _CreateLogGUI()
    
    ; Ajouter le message à l'éditeur
    Local $sCurrentText = GUICtrlRead($hEdit)
    GUICtrlSetData($hEdit, $sCurrentText & $sMessage)
    
    ; Faire défiler vers le bas
    _GUICtrlEdit_Scroll($hEdit, $SB_SCROLLCARET)
    
    ; Traiter les événements GUI pour maintenir la réactivité
    Local $nMsg = GUIGetMsg()
    If $nMsg = $GUI_EVENT_CLOSE Then
        Exit
    EndIf
EndFunc

Func _CloseLogGUI()
    If $bGUICreated Then
        GUIDelete($hGUI)
        $bGUICreated = False
    EndIf
EndFunc

; ===== Helpers =====
Func _TSHexToString($sHex)
    If $sHex = "" Then Return ""
    Local $bin = "0x" & $sHex
    Return BinaryToString($bin, 4) ; UTF-8
EndFunc

Func _TSStringToHex($s)
    If $s = "" Then Return ""
    Local $b = StringToBinary($s, 4) ; UTF-8 -> "0x..."
    Local $hex = StringTrimLeft($b, 2)
    Return StringUpper($hex)
EndFunc

; Parse "_([0-9A-F]+)(?:__sha([0-9A-F]{64}))?\.exe$" from @ScriptName
; Returns True/False and fills ByRef params.
Func _ParseFromExeName(ByRef $oUrl, ByRef $oSha, ByRef $oUrlHex)
    $oUrl = ""
    $oSha = ""
    $oUrlHex = ""

    Local $name = @ScriptName
    Local $a = StringRegExp($name, "(?i)_([0-9A-F]+)(?:__sha([0-9A-F]{64}))?\.exe$", 3)
    If @error Or UBound($a) = 0 Then
        Return False
    EndIf

    $oUrlHex = $a[0]
    If UBound($a) > 1 Then $oSha = StringUpper($a[1])
    $oUrl = _TSHexToString($oUrlHex)
    If $oUrl = "" Then Return False
    Return True
EndFunc

Func _Download($url, $outPath)
    Local $maxRetries = 10
    _LogToGUI("DEBUG: Téléchargement de: " & $url & @CRLF)
    
    For $i = 1 To $maxRetries
        Local $h = InetGet($url, $outPath, 1, 1) ; Background download
        If @error Then 
            _LogToGUI("  ERREUR InetGet (Start), nouvelle tentative dans 2s (" & $i & "/" & $maxRetries & ")..." & @CRLF)
            Sleep(2000)
            ContinueLoop
        EndIf
        
        While InetGetInfo($h, 0) = 1 ; Tant que le téléchargement est actif
            Sleep(150)
        WEnd
        
        Local $bDownloadSuccess = (InetGetInfo($h, 3) = True) ; Vérifier le flag de succès
        Local $bDownloadError = (InetGetInfo($h, 2) = True) ; Vérifier le flag d'erreur
        
        InetClose($h)
        
        ; Si ça a marché (Succès déclaré OU Fichier présent et non vide)
        ; On est permissif car l'ancien code ne vérifiait rien et ça marchait
        If (FileExists($outPath) And FileGetSize($outPath) > 0) Then
            _LogToGUI("DEBUG: Téléchargement OK (Taille: " & FileGetSize($outPath) & ")" & @CRLF)
            Return $outPath
        Else
            Local $sReason = "Inconnue"
            If Not $bDownloadSuccess Then $sReason = "Flag Succès=Faux"
            If $bDownloadError Then $sReason = "Flag Erreur=Vrai"
            If Not FileExists($outPath) Then $sReason = "Fichier non créé"
            If FileExists($outPath) And FileGetSize($outPath) = 0 Then $sReason = "Fichier vide"
            
            If $i < $maxRetries Then
                _LogToGUI("  Échec (" & $sReason & "), nouvelle tentative dans 2s (" & $i & "/" & $maxRetries & ")..." & @CRLF)
                FileDelete($outPath)
                Sleep(2000)
            Else
                Return SetError(1, 0, 0)
            EndIf
        EndIf
    Next
    Return SetError(1, 0, 0)
EndFunc

; Télécharge la liste des fichiers depuis le serveur (manifest ou auto)
Func _GetFileList($baseUrl)
    ; Essayer le directory listing automatique
    _LogToGUI("Récupération de la liste des fichiers..." & @CRLF)
    
    Local $aFiles = _ParseDirectoryListing($baseUrl)
    If @error Or UBound($aFiles) = 0 Then
        MsgBox(16, "Erreur", "Impossible de récupérer la liste des fichiers" & @CRLF & @CRLF & _
            "Vérifiez que le serveur autorise le directory listing.")
        Return SetError(1, 0, $aFiles)
    EndIf
    
    Return $aFiles
EndFunc

; Parse le directory listing HTML du serveur web
Func _ParseDirectoryListing($baseUrl)
    Local $aFiles[0]
    
    ; Télécharger la page HTML du directory listing
    Local $tempHtml = @TempDir & "\techsuivi_listing.html"
    Local $listUrl = $baseUrl
    If Not StringRight($listUrl, 1) = "/" Then $listUrl &= "/"
    
    Local $result = _Download($listUrl, $tempHtml)
    If @error Or Not FileExists($tempHtml) Then
        Return SetError(1, 0, $aFiles)
    EndIf
    
    ; Lire le contenu HTML
    Local $html = FileRead($tempHtml)
    FileDelete($tempHtml)
    
    If $html = "" Then
        Return SetError(2, 0, $aFiles)
    EndIf
    
    ; Parser récursivement tous les fichiers et dossiers
    $aFiles = _ScanDirectory($baseUrl, "")
    
    Return $aFiles
EndFunc

; Scanne récursivement un répertoire via HTTP
Func _ScanDirectory($baseUrl, $subPath)
    Local $aFiles[0]
    
    ; URL complète du répertoire à scanner
    Local $dirUrl = $baseUrl
    If Not StringRight($dirUrl, 1) = "/" Then $dirUrl &= "/"
    If $subPath <> "" Then
        $dirUrl &= $subPath
        If Not StringRight($dirUrl, 1) = "/" Then $dirUrl &= "/"
    EndIf
    
    ; Télécharger la page de listing
    Local $tempHtml = @TempDir & "\techsuivi_dir_" & @SEC & @MSEC & ".html"
    Local $result = _Download($dirUrl, $tempHtml)
    If @error Or Not FileExists($tempHtml) Then
        Return $aFiles
    EndIf
    
    Local $html = FileRead($tempHtml)
    FileDelete($tempHtml)
    
    ; Patterns courants pour Apache, nginx, etc.
    ; Chercher les liens <a href="...">
    Local $aMatches = StringRegExp($html, '(?i)<a\s+href="([^"]+)"[^>]*>([^<]+)</a>', 3)
    
    If Not @error And UBound($aMatches) > 0 Then
        For $i = 0 To UBound($aMatches) - 1 Step 2
            Local $link = $aMatches[$i]
            Local $name = $aMatches[$i + 1]
            
            ; Ignorer les liens spéciaux et remontées
            If $link = "" Or $link = "." Or $link = ".." Or $link = "../" Or $link = "./" Then ContinueLoop
            If StringLeft($link, 1) = "/" Then ContinueLoop ; Ignorer les chemins absolus (racine serveur)
            If StringLeft($link, 1) = "?" Then ContinueLoop ; Ignorer les query strings
            If StringInStr($link, ":") Then ContinueLoop ; Ignorer les protocoles (http:, mailto:, etc)
            
            ; Si c'est un dossier (se termine par /)
            If StringRight($link, 1) = "/" Then
                Local $subDirName = StringTrimRight($link, 1)
                Local $newSubPath
                If $subPath <> "" Then
                    $newSubPath = $subPath & "\" & $subDirName
                Else
                    $newSubPath = $subDirName
                EndIf
                
                ; Scanner récursivement ce sous-dossier
                Local $aSubFiles = _ScanDirectory($baseUrl, $newSubPath)
                
                ; Ajouter les fichiers trouvés dans le sous-dossier
                For $j = 0 To UBound($aSubFiles) - 1
                    ReDim $aFiles[UBound($aFiles) + 1]
                    $aFiles[UBound($aFiles) - 1] = $aSubFiles[$j]
                Next
            Else
                ; C'est un fichier
                Local $filePath
                If $subPath <> "" Then
                    $filePath = $subPath & "\" & $link
                Else
                    $filePath = $link
                EndIf
                
                ; Éviter les doublons et les fichiers système
                If $link <> $MANIFEST_FILE And $link <> "index.html" And $link <> "index.htm" Then
                    ReDim $aFiles[UBound($aFiles) + 1]
                    $aFiles[UBound($aFiles) - 1] = $filePath
                EndIf
            EndIf
        Next
    EndIf
    
    Return $aFiles
EndFunc

; ===== MAIN =====
; Initialiser l'interface graphique de logging
_CreateLogGUI()

Local $url = "", $sha = "", $urlHex = ""
Local $found = _ParseFromExeName($url, $sha, $urlHex)

If Not $found Then
    ; Demander l'URL de base
    Local $input = InputBox("TechSuivi Installeur", "Entrez l'URL de base (dossier Install) :" & @CRLF & @CRLF & "Exemple: http://192.168.10.248:8080/Download/Install/", "", "", 500, 150)
    If @error Or $input = "" Then
        Exit 0
    EndIf
    
    $url = $input
    
    ; S'assurer que l'URL se termine par /
    If Not StringRight($url, 1) = "/" Then $url &= "/"
    
    $urlHex = _TSStringToHex($url)
    
    ; Générer le nom de fichier avec HEX
    Local $newFileName = "installeur_" & $urlHex & ".exe"
    
    ; Afficher le HEX et le nom de fichier pour copie
    _LogToGUI("HEX: " & $urlHex & @CRLF)
    _LogToGUI("Nom de fichier: " & $newFileName & @CRLF)
    
    ; Interface simple pour copier le nom de fichier
    Local $choice = MsgBox(35, "Nom de fichier généré", _
        "Pour utilisation future, renommez ce fichier :" & @CRLF & @CRLF & _
        $newFileName & @CRLF & @CRLF & _
        "OUI = Copier le nom dans le presse-papiers" & @CRLF & _
        "NON = Continuer l'installation" & @CRLF & _
        "ANNULER = Quitter")
    
    Switch $choice
        Case 6 ; OUI
            ClipPut($newFileName)
            MsgBox(64, "Copié", "Nom de fichier copié !" & @CRLF & "Vous pouvez maintenant continuer l'installation.")
        Case 2 ; ANNULER
            Exit 0
    EndSwitch
EndIf

; S'assurer que l'URL se termine par /
If Not StringRight($url, 1) = "/" Then $url &= "/"

; Vérifier les arguments en ligne de commande
Local $bForceUpdate = False
Local $bUninstall = False

If $CmdLine[0] > 0 Then
    For $i = 1 To $CmdLine[0]
        If $CmdLine[$i] = "-maj" Then
            $bForceUpdate = True
            _LogToGUI("Mode Mise à jour forcé via arguments" & @CRLF)
        EndIf
        If $CmdLine[$i] = "-uninstall" Then
            $bUninstall = True
            _LogToGUI("Mode Désinstallation forcé via arguments" & @CRLF)
        EndIf
    Next
EndIf

; Si demande de désinstallation directe
If $bUninstall Then
    If FileExists($INSTALL_PATH) Then
        _LogToGUI("Désinstallation en cours..." & @CRLF)
        _SelfDestruct($INSTALL_PATH)
        Exit 0
    Else
        MsgBox(48, "TechSuivi", "TechSuivi n'est pas installé.")
        Exit 0
    EndIf
EndIf

; Procéder à l'installation
_InstallTechSuivi($url, $bForceUpdate)

; Attendre que l'utilisateur ferme la fenêtre de log
_LogToGUI(@CRLF & "=== INSTALLATION TERMINÉE ===" & @CRLF)
_LogToGUI("Vous pouvez fermer cette fenêtre." & @CRLF)

; Boucle pour maintenir la fenêtre ouverte
While $bGUICreated
    Local $nMsg = GUIGetMsg()
    If $nMsg = $GUI_EVENT_CLOSE Then
        _CloseLogGUI()
        ExitLoop
    EndIf
    
    Sleep(100)
WEnd

Exit 0

; ===== FONCTIONS =====

; Fonction pour gérer l'auto-destruction et la désinstallation propre
Func _SelfDestruct($installPath)
    Local $sScriptDir = @ScriptDir
    
    ; Normalisation des chemins (retirer le backslash final si présent)
    If StringRight($sScriptDir, 1) = "\" Then $sScriptDir = StringTrimRight($sScriptDir, 1)
    If StringRight($installPath, 1) = "\" Then $installPath = StringTrimRight($installPath, 1)

    ; Attente de la fermeture de l'application principale
    If ProcessExists("auto.exe") Then
        _LogToGUI("Attente de la fermeture de TechSuivi..." & @CRLF)
        Local $iWait = ProcessWaitClose("auto.exe", 30) ; Attendre max 30s
        If $iWait = 0 Then
             _LogToGUI("Le programme ne se ferme pas. Fermeture forcée..." & @CRLF)
             ProcessClose("auto.exe")
             ProcessWaitClose("auto.exe", 5)
        EndIf
    EndIf
    
    ; Si on n'est pas dans le dossier d'installation (ou sous-dossier), on peut supprimer directement
    If StringInStr($sScriptDir, $installPath) = 0 Then
        _LogToGUI("Désinstallation depuis l'extérieur..." & @CRLF)
        DirRemove($installPath, 1)
        FileDelete(@DesktopDir & "\TechSuivi.lnk")
        MsgBox(64, "TechSuivi", "Désinstallation terminée avec succès.")
        Exit 0
    EndIf
    
    ; Si on est DANS le dossier, il faut passer par un script externe
    _LogToGUI("Désinstallation depuis le dossier interne..." & @CRLF)
    _LogToGUI("Lancement du nettoyage différé..." & @CRLF)
    
    Local $sBatchPath = @TempDir & "\TechSuivi_Uninstall.bat"
    
    ; Créer le script batch de nettoyage
    Local $hFile = FileOpen($sBatchPath, 2) ; 2 = Overwrite
    If $hFile = -1 Then
        MsgBox(16, "Erreur", "Impossible de créer le script de désinstallation.")
        Exit 8
    EndIf
    
    FileWriteLine($hFile, "@echo off")
    FileWriteLine($hFile, "title Désinstallation de TechSuivi")
    FileWriteLine($hFile, "echo Attente de la fermeture de l'installeur...")
    FileWriteLine($hFile, ":loop")
    FileWriteLine($hFile, "timeout /t 1 /nobreak >nul")
    FileWriteLine($hFile, 'del "' & @ScriptFullPath & '" >nul 2>&1')
    FileWriteLine($hFile, 'if exist "' & @ScriptFullPath & '" goto loop')
    FileWriteLine($hFile, "echo Suppression des fichiers...")
    FileWriteLine($hFile, 'rmdir /s /q "' & $installPath & '"')
    FileWriteLine($hFile, 'del "' & @DesktopDir & '\TechSuivi.lnk" >nul 2>&1')
    FileWriteLine($hFile, 'del "%~f0"')
    FileClose($hFile)
    
    ; Exécuter le batch en caché
    Run($sBatchPath, @TempDir, @SW_HIDE)
    
    ; Quitter immédiatement pour libérer le fichier
    Exit 0
EndFunc

Func _InstallTechSuivi($baseUrl, $bForceUpdate = False)
    ; Récupérer la liste des fichiers (manifest ou auto-discovery)
    Local $aFiles = _GetFileList($baseUrl)
    If @error Or UBound($aFiles) = 0 Then
        Exit 2
    EndIf
    
    _LogToGUI("Fichiers à télécharger: " & UBound($aFiles) & @CRLF)
    
    ; Créer/Nettoyer le dossier d'installation
    ; Créer/Nettoyer le dossier d'installation
    Local $isUpdateMode = False
    
    If FileExists($INSTALL_PATH) Then
        If $bForceUpdate Then
            $isUpdateMode = True
            _LogToGUI("Mode Mise à jour actif (forcé)" & @CRLF)
        Else
            Local $iMode = _AskInstallMode()
            If $iMode = 0 Then ; Annuler
                Exit 0
            ElseIf $iMode = 1 Then ; Réinstallation
                DirRemove($INSTALL_PATH, 1)
            ElseIf $iMode = 2 Then ; Mise à jour
                $isUpdateMode = True
                _LogToGUI("Mode Mise à jour actif" & @CRLF)
            ElseIf $iMode = 3 Then ; Désinstallation
                _LogToGUI("Désinstallation en cours..." & @CRLF)
                _SelfDestruct($INSTALL_PATH)
                Exit 0
            EndIf
        EndIf
    EndIf
    
    If Not FileExists($INSTALL_PATH) Then
        If Not DirCreate($INSTALL_PATH) Then
            MsgBox(16, "Erreur", "Impossible de créer le dossier d'installation")
            Exit 4
        EndIf
    EndIf
    
    ; Copie de l'installeur lui-même
    _LogToGUI("Copie de l'installeur..." & @CRLF)
    Local $sSelfPath = @ScriptFullPath
    Local $sDestSelf = $INSTALL_PATH & "\" & @ScriptName
    If FileCopy($sSelfPath, $sDestSelf, 1) Then ; 1 = overwrite
        _LogToGUI("  OK: Installeur copié" & @CRLF)
    Else
        _LogToGUI("  ERREUR: Impossible de copier l'installeur" & @CRLF)
    EndIf
    
    ; Télécharger chaque fichier
    Local $successCount = 0
    Local $failedFiles = ""
    
    For $i = 0 To UBound($aFiles) - 1
        Local $filePath = $aFiles[$i]
        Local $fileUrl = $baseUrl
        If Not StringRight($fileUrl, 1) = "/" Then $fileUrl &= "/"
        $fileUrl &= StringReplace($filePath, "\", "/") ; Convertir \ en / pour l'URL
        
        Local $destPath = $INSTALL_PATH & "\" & $filePath
        
        ; En mode mise à jour, on ne touche pas au fichier cfg.ini s'il existe
        If $isUpdateMode And ($filePath = "ini\cfg.ini" Or $filePath = "ini/cfg.ini") And FileExists($destPath) Then
            _LogToGUI("Ignoré (Mise à jour): " & $filePath & @CRLF)
            $successCount += 1
            ContinueLoop
        EndIf
        
        ; Créer les sous-dossiers si nécessaire
        Local $destDir = StringLeft($destPath, StringInStr($destPath, "\", 0, -1) - 1)
        If Not FileExists($destDir) Then
            DirCreate($destDir)
        EndIf
        
        ; Télécharger le fichier
        _LogToGUI("Téléchargement [" & ($i + 1) & "/" & UBound($aFiles) & "]: " & $filePath & @CRLF)

        Local $result = _Download($fileUrl, $destPath)
        If @error Or Not FileExists($destPath) Then
            $failedFiles &= $filePath & @CRLF
            _LogToGUI("  ÉCHEC: " & $filePath & @CRLF)
        Else
            $successCount += 1
            _LogToGUI("  OK: " & FileGetSize($destPath) & " octets" & @CRLF)
        EndIf
        
        Sleep(50) ; Petit délai pour ne pas surcharger le serveur
    Next
    
    ; Vérifier les résultats
    If $successCount = 0 Then
        MsgBox(16, "Erreur", "Aucun fichier n'a pu être téléchargé.")
        Exit 5
    EndIf
    
    If $failedFiles <> "" Then
        MsgBox(48, "Téléchargement partiel", _
            "Fichiers téléchargés: " & $successCount & "/" & UBound($aFiles) & @CRLF & @CRLF & _
            "Fichiers échoués:" & @CRLF & $failedFiles)
    EndIf
    
    ; Génération des fichiers INI depuis l'API (si cfg.ini est disponible)
    Local $cfgPath = $INSTALL_PATH & "\ini\cfg.ini"
    If FileExists($cfgPath) Then
        _LogToGUI("Génération des fichiers INI depuis l'API..." & @CRLF)
        Local $apiResult = _GenerateINIFilesFromAPI($INSTALL_PATH)
        If $apiResult Then
            _LogToGUI("✓ Fichiers INI générés avec succès depuis l'API" & @CRLF)
        Else
            _LogToGUI("⚠ Impossible de générer les fichiers INI depuis l'API" & @CRLF)
        EndIf
    Else
        _LogToGUI("⚠ Fichier cfg.ini non trouvé - génération INI ignorée" & @CRLF)
    EndIf
    
    ; Recherche d'auto.exe et création du raccourci
    Local $autoExePath = _FindAutoExe($INSTALL_PATH)
    If $autoExePath <> "" Then
        Local $desktopPath = @DesktopDir & "\TechSuivi.lnk"
        Local $workingDir = StringLeft($autoExePath, StringInStr($autoExePath, "\", 0, -1) - 1)
        
        ; Rechercher le fichier logo.ico
        Local $iconPath = _FindLogoIcon($INSTALL_PATH)
        If $iconPath <> "" Then
            FileCreateShortcut($autoExePath, $desktopPath, $workingDir, "", "TechSuivi", $iconPath)
            _LogToGUI("Installation terminée - Raccourci créé avec icône" & @CRLF)
        Else
            FileCreateShortcut($autoExePath, $desktopPath, $workingDir, "", "TechSuivi")
            _LogToGUI("Installation terminée - Raccourci créé (icône non trouvée)" & @CRLF)
        EndIf
        
        ; Proposer de lancer le logiciel
        Local $launch = MsgBox(36, "Installation terminée", _
            "TechSuivi a été installé avec succès !" & @CRLF & @CRLF & _
            "Fichiers installés: " & $successCount & "/" & UBound($aFiles) & @CRLF & _
            "Installé dans : C:\TechSuivi" & @CRLF & _
            "Raccourci créé sur le bureau" & @CRLF & @CRLF & _
            "Voulez-vous lancer TechSuivi maintenant ?" & @CRLF & @CRLF & _
            "(Lancement automatique dans 30 secondes)", 30)
        
        If $launch = 6 Or $launch = -1 Then ; OUI ou Timeout
            _LogToGUI("Lancement de TechSuivi..." & @CRLF)
            ShellExecute($autoExePath, "", $workingDir)
            Exit
        EndIf
    Else
        _LogToGUI("Installation terminée - auto.exe non trouvé" & @CRLF)
        MsgBox(48, "Installation terminée", _
            "TechSuivi a été installé dans C:\TechSuivi" & @CRLF & @CRLF & _
            "Fichiers installés: " & $successCount & "/" & UBound($aFiles) & @CRLF & @CRLF & _
            "Attention : Le fichier auto.exe n'a pas été trouvé." & @CRLF & _
            "Vérifiez le contenu du dossier d'installation.")
    EndIf
EndFunc

Func _FindAutoExe($installPath)
    ; Recherche auto.exe dans différents emplacements
    Local $searchPaths[3] = [$installPath & "\auto.exe", $installPath & "\TechSuivi\auto.exe", $installPath & "\TechSuivi V4\auto.exe"]
    
    For $i = 0 To 2
        If FileExists($searchPaths[$i]) Then
            Return $searchPaths[$i]
        EndIf
    Next
    
    ; Recherche récursive
    Local $hSearch = FileFindFirstFile($installPath & "\*")
    If $hSearch <> -1 Then
        While 1
            Local $file = FileFindNextFile($hSearch)
            If @error Then ExitLoop
            If @extended Then
                Local $subPath = $installPath & "\" & $file & "\auto.exe"
                If FileExists($subPath) Then
                    FileClose($hSearch)
                    Return $subPath
                EndIf
            ElseIf $file = "auto.exe" Then
                FileClose($hSearch)
                Return $installPath & "\" & $file
            EndIf
        WEnd
        FileClose($hSearch)
    EndIf
    
    Return ""
EndFunc

; Demander le mode d'installation
Func _AskInstallMode()
    Local $hAskGUI = GUICreate("TechSuivi - Installation", 400, 220, -1, -1, BitOR($WS_CAPTION, $WS_POPUP, $WS_SYSMENU), -1, $hGUI)
    
    GUICtrlCreateLabel("Le dossier C:\TechSuivi existe déjà.", 20, 20, 360, 20, $SS_CENTER)
    GUICtrlSetFont(-1, 10, 600)
    
    GUICtrlCreateLabel("Que voulez-vous faire ?", 20, 45, 360, 20, $SS_CENTER)
    
    Local $btnUpdate = GUICtrlCreateButton("Mise à jour (Conserver cfg.ini)", 40, 80, 320, 30)
    Local $btnReinstall = GUICtrlCreateButton("Réinstallation complète (Tout supprimer)", 40, 120, 320, 30)
    Local $btnUninstall = GUICtrlCreateButton("Désinstaller", 40, 160, 320, 30)
    
    GUISetState(@SW_SHOW, $hAskGUI)
    
    Local $iMode = 0 ; 0=Cancel, 1=Reinstall, 2=Update, 3=Uninstall
    
    While 1
        Local $msg = GUIGetMsg()
        Select
            Case $msg = $GUI_EVENT_CLOSE
                $iMode = 0
                ExitLoop
            Case $msg = $btnUpdate
                $iMode = 2
                ExitLoop
            Case $msg = $btnReinstall
                $iMode = 1
                ExitLoop
            Case $msg = $btnUninstall
                $iMode = 3
                ExitLoop
        EndSelect
    WEnd
    
    GUIDelete($hAskGUI)
    Return $iMode
EndFunc

; ===============================================
; FONCTIONS API POUR GÉNÉRATION DES FICHIERS INI
; ===============================================

; Variables globales pour l'API
Global $sBaseURL_API = ""
Global $sAPI_KEY = ""

; Fonction pour initialiser la configuration API depuis cfg.ini
Func _InitAPIConfig($installPath)
    ; Déclarer explicitement les variables globales dans cette fonction aussi
    Global $sBaseURL_API, $sAPI_KEY
    
    Local $cfgPath = $installPath & "\ini\cfg.ini"
    
    _LogToGUI("DEBUG: Recherche cfg.ini dans: " & $cfgPath & @CRLF)

    If Not FileExists($cfgPath) Then
        _LogToGUI("ERREUR: Fichier cfg.ini non trouvé: " & $cfgPath & @CRLF)
        Return False
    EndIf

    _LogToGUI("DEBUG: cfg.ini trouvé, lecture de la configuration..." & @CRLF)

    $sBaseURL_API = IniRead($cfgPath, "config", "url_api", "")
    $sAPI_KEY = IniRead($cfgPath, "config", "key_api", "")

    _LogToGUI("URL API: " & $sBaseURL_API & @CRLF)
    _LogToGUI("Clé API: " & StringLeft($sAPI_KEY, 10) & "..." & @CRLF)

    If $sBaseURL_API = "" Or $sAPI_KEY = "" Then
        _LogToGUI("ERREUR: Configuration API incomplète dans cfg.ini" & @CRLF)
        If $sBaseURL_API = "" Then
            _LogToGUI("  URL API: VIDE" & @CRLF)
        Else
            _LogToGUI("  URL API: OK" & @CRLF)
        EndIf
        If $sAPI_KEY = "" Then
            _LogToGUI("  Clé API: VIDE" & @CRLF)
        Else
            _LogToGUI("  Clé API: OK" & @CRLF)
        EndIf
        Return False
    EndIf

    _LogToGUI("✓ Configuration API initialisée" & @CRLF)
    Return True
EndFunc

; Fonction HTTP GET robuste (adaptée du script principal)
Func _HTTPGet_Installeur($sURL)
    ; Méthode 1: InetRead (méthode simple)
    Local $dData = InetRead($sURL, 1) ; 1 = forcer le téléchargement
    
    If Not @error And $dData <> "" Then
        Local $sResponse = BinaryToString($dData, 4) ; 4 = UTF-8
        _LogToGUI("  API: " & StringLen($sResponse) & " caractères reçus" & @CRLF)
        Return $sResponse
    EndIf
    
    ; Méthode 2: WinHTTP (fallback)
    Local $oHTTP = ObjCreate("winhttp.winhttprequest.5.1")
    
    If IsObj($oHTTP) Then
        $oHTTP.Open("GET", $sURL, False)
        $oHTTP.SetRequestHeader("User-Agent", "AutoIt-TechSuivi/1.0")
        $oHTTP.Send()
        
        If Not @error Then
            Local $response = $oHTTP.ResponseText
            _LogToGUI("  API: " & StringLen($response) & " caractères reçus (WinHTTP)" & @CRLF)
            Return $response
        EndIf
    EndIf
    
    _LogToGUI("  ERREUR: Impossible de contacter l'API" & @CRLF)
    SetError(1)
    Return ""
EndFunc

; Fonction pour nettoyer les backslashes échappés dans les chaînes JSON
Func _CleanJSONString_Installeur($sString)
    $sString = StringReplace($sString, "\/", "/")
    $sString = StringReplace($sString, "\\", "\")
    Return $sString
EndFunc

; Récupérer les données de logiciels depuis l'API
Func _GetLogiciels_API_Installeur()
    ; Déclarer explicitement les variables globales
    Global $sBaseURL_API, $sAPI_KEY
    
    If $sBaseURL_API = "" Or $sAPI_KEY = "" Then
        _LogToGUI("ERREUR: Variables API non initialisées" & @CRLF)
        SetError(1)
        Return False
    EndIf
    
    Local $sURL = $sBaseURL_API & "?type=logiciels&api_key=" & $sAPI_KEY
    Local $response = _HTTPGet_Installeur($sURL)

    If @error Then
        _LogToGUI("ERREUR: Échec de la requête HTTP pour logiciels" & @CRLF)
        SetError(1)
        Return False
    EndIf

    If $response = "" Or $response = False Then
        _LogToGUI("ERREUR: Réponse vide de l'API logiciels" & @CRLF)
        SetError(2)
        Return False
    EndIf

    Return $response
EndFunc

; Récupérer les données de commandes depuis l'API
Func _GetCommandes_API_Installeur()
    ; Déclarer explicitement les variables globales
    Global $sBaseURL_API, $sAPI_KEY
    
    If $sBaseURL_API = "" Or $sAPI_KEY = "" Then
        SetError(1)
        Return False
    EndIf
    
    Local $sURL = $sBaseURL_API & "?type=commandes&api_key=" & $sAPI_KEY
    Local $response = _HTTPGet_Installeur($sURL)

    If @error Then
        SetError(1)
        Return False
    EndIf

    Return $response
EndFunc

; Récupérer les données de nettoyage depuis l'API
Func _GetNettoyage_API_Installeur()
    ; Déclarer explicitement les variables globales
    Global $sBaseURL_API, $sAPI_KEY
    
    If $sBaseURL_API = "" Or $sAPI_KEY = "" Then
        SetError(1)
        Return False
    EndIf
    
    Local $sURL = $sBaseURL_API & "?type=nettoyage&api_key=" & $sAPI_KEY
    Local $response = _HTTPGet_Installeur($sURL)

    If @error Then
        SetError(1)
        Return False
    EndIf

    Return $response
EndFunc

; Récupérer les données de personnalisation depuis l'API
Func _GetPersonnalisation_API_Installeur()
    ; Déclarer explicitement les variables globales
    Global $sBaseURL_API, $sAPI_KEY
    
    If $sBaseURL_API = "" Or $sAPI_KEY = "" Then
        SetError(1)
        Return False
    EndIf
    
    Local $sURL = $sBaseURL_API & "?type=personnalisation&api_key=" & $sAPI_KEY
    _LogToGUI("DEBUG: URL COMPLÈTE PERSONNALISATION: " & $sURL & @CRLF)
    Local $response = _HTTPGet_Installeur($sURL)

    If @error Then
        SetError(1)
        Return False
    EndIf

    Return $response
EndFunc

; Télécharger et créer le fichier logiciels.ini
Func _DownloadLogiciels_Installeur($installPath)
    _LogToGUI("DEBUG: Début téléchargement logiciels..." & @CRLF)

    ; Protection contre les erreurs critiques
    Local $data = ""
    Local $error_occurred = False
    
    ; Tentative de récupération des données
    $data = _GetLogiciels_API_Installeur()
    If @error Or $data = False Or $data = "" Then
        _LogToGUI("ERREUR: API logiciels inaccessible (erreur: " & @error & ")" & @CRLF)
        $error_occurred = True
    EndIf
    
    If $error_occurred Then
        _LogToGUI("INFO: Création d'un fichier logiciels.ini vide" & @CRLF)
        Local $iniPath = $installPath & "\ini\logiciels.ini"
        IniWrite($iniPath, "cfg", "nb", "0")
        Return True ; Retourner True pour ne pas bloquer l'installation
    EndIf

    _LogToGUI("Logiciels - " & StringLen($data) & " caractères reçus" & @CRLF)

    ; Vérifier si les données contiennent du JSON valide
    If Not StringInStr($data, "{") Or Not StringInStr($data, "}") Then
        _LogToGUI("ERREUR: Les données reçues ne semblent pas être du JSON valide" & @CRLF)
        _LogToGUI("DEBUG: Données reçues: " & $data & @CRLF)
        Local $iniPath = $installPath & "\ini\logiciels.ini"
        IniWrite($iniPath, "cfg", "nb", "0")
        Return True
    EndIf

    Local $pattern = '"id":\s*(\d+)[^}]*"nom":\s*"([^"]*)"[^}]*"type_installation":\s*"([^"]*)"[^}]*"commande_winget":\s*"([^"]*)"[^}]*"fichier_nom":\s*"([^"]*)"[^}]*"fichier_path":\s*"([^"]*)"[^}]*"est_zip":\s*(\d+)[^}]*"commande_lancement":\s*"([^"]*)"[^}]*"description":\s*"([^"]*)"[^}]*"defaut":\s*(\d+)'
    Local $aMatches = StringRegExp($data, $pattern, 3)

    If Not IsArray($aMatches) Then
        _LogToGUI("ERREUR: Parsing logiciels échoué - aucun match trouvé" & @CRLF)
        _LogToGUI("DEBUG: Échantillon des données pour analyse: " & StringLeft($data, 500) & @CRLF)
        ; Créer un fichier vide plutôt que de faire échouer l'installation
        Local $iniPath = $installPath & "\ini\logiciels.ini"
        IniWrite($iniPath, "cfg", "nb", "0")
        Return True
    EndIf

    Local $iniPath = $installPath & "\ini\logiciels.ini"
    ; Supprimé - pas besoin de ce debug

    If FileExists($iniPath) Then
        FileDelete($iniPath)
        _LogToGUI("DEBUG: Ancien fichier supprimé" & @CRLF)
    EndIf

    Local $nb_items = UBound($aMatches) / 10
    ; Supprimé - pas besoin de ce debug

    IniWrite($iniPath, "cfg", "nb", $nb_items)

    For $i = 0 To $nb_items - 1
        Local $section = $i + 1
        ; Supprimé - pas besoin de ce debug
        
        IniWrite($iniPath, $section, "id", $aMatches[$i * 10])
        IniWrite($iniPath, $section, "nom", _CleanJSONString_Installeur($aMatches[$i * 10 + 1]))
        IniWrite($iniPath, $section, "type_installation", _CleanJSONString_Installeur($aMatches[$i * 10 + 2]))
        IniWrite($iniPath, $section, "commande_winget", _CleanJSONString_Installeur($aMatches[$i * 10 + 3]))
        IniWrite($iniPath, $section, "fichier_nom", _CleanJSONString_Installeur($aMatches[$i * 10 + 4]))
        IniWrite($iniPath, $section, "fichier_path", _CleanJSONString_Installeur($aMatches[$i * 10 + 5]))
        IniWrite($iniPath, $section, "est_zip", $aMatches[$i * 10 + 6])
        IniWrite($iniPath, $section, "commande_lancement", _CleanJSONString_Installeur($aMatches[$i * 10 + 7]))
        IniWrite($iniPath, $section, "description", _CleanJSONString_Installeur($aMatches[$i * 10 + 8]))
        IniWrite($iniPath, $section, "defaut", $aMatches[$i * 10 + 9])
    Next

    ; Vérifier que le fichier a bien été créé
    If FileExists($iniPath) Then
        _LogToGUI("✓ Logiciels.ini créé: " & $nb_items & " éléments" & @CRLF)
        Return True
    Else
        _LogToGUI("ERREUR: Le fichier logiciels.ini n'a pas pu être créé" & @CRLF)
        Return False
    EndIf
EndFunc

; Télécharger et créer le fichier commandes.ini
Func _DownloadCommandes_Installeur($installPath)
    Local $data = _GetCommandes_API_Installeur()
    If @error Or $data = False Or $data = "" Then
        _LogToGUI("ERREUR: API commandes inaccessible" & @CRLF)
        _LogToGUI("INFO: Création d'un fichier commandes.ini vide" & @CRLF)
        Local $iniPath = $installPath & "\ini\commandes.ini"
        IniWrite($iniPath, "cfg", "nb", "0")
        Return True
    EndIf

    _LogToGUI("Commandes - données reçues (" & StringLen($data) & " caractères)" & @CRLF)
    
    ; SAUVEGARDER LE RÉSULTAT BRUT POUR DEBUG
    Local $debugFile = $installPath & "\ini\debug_commandes_raw.txt"
    FileWrite($debugFile, $data)
    _LogToGUI("DEBUG: Résultat brut sauvegardé dans: " & $debugFile & @CRLF)

    Local $pattern = '"id":\s*(\d+)[^}]*"nom":\s*"([^"]*)"[^}]*"commande":\s*"([^"]*)"[^}]*"description":\s*"([^"]*)"[^}]*"defaut":\s*(\d+)'
    Local $aMatches = StringRegExp($data, $pattern, 3)

    If Not IsArray($aMatches) Then
        _LogToGUI("ERREUR: Parsing commandes échoué" & @CRLF)
        Local $iniPath = $installPath & "\ini\commandes.ini"
        IniWrite($iniPath, "cfg", "nb", "0")
        Return True
    EndIf

    Local $iniPath = $installPath & "\ini\commandes.ini"
    If FileExists($iniPath) Then FileDelete($iniPath)

    Local $nb_items = UBound($aMatches) / 5
    IniWrite($iniPath, "cfg", "nb", $nb_items)

    For $i = 0 To $nb_items - 1
        Local $section = $i + 1
        IniWrite($iniPath, $section, "id", $aMatches[$i * 5])
        IniWrite($iniPath, $section, "nom", _CleanJSONString_Installeur($aMatches[$i * 5 + 1]))
        IniWrite($iniPath, $section, "commande", _CleanJSONString_Installeur($aMatches[$i * 5 + 2]))
        IniWrite($iniPath, $section, "description", _CleanJSONString_Installeur($aMatches[$i * 5 + 3]))
        IniWrite($iniPath, $section, "defaut", $aMatches[$i * 5 + 4])
    Next

    _LogToGUI("SUCCESS: Commandes - " & $nb_items & " éléments créés" & @CRLF)
    Return True
EndFunc

; Télécharger et créer le fichier nettoyage.ini
Func _DownloadNettoyage_Installeur($installPath)
    Local $data = _GetNettoyage_API_Installeur()
    If @error Or $data = False Or $data = "" Then
        _LogToGUI("ERREUR: API nettoyage inaccessible" & @CRLF)
        _LogToGUI("INFO: Création d'un fichier nettoyage.ini vide" & @CRLF)
        Local $iniPath = $installPath & "\ini\nettoyage.ini"
        IniWrite($iniPath, "cfg", "nb", "0")
        Return True
    EndIf

    _LogToGUI("Nettoyage - données reçues (" & StringLen($data) & " caractères)" & @CRLF)
    
    ; SAUVEGARDER LE RÉSULTAT BRUT POUR DEBUG
    Local $debugFile = $installPath & "\ini\debug_nettoyage_raw.txt"
    FileWrite($debugFile, $data)
    _LogToGUI("DEBUG: Résultat brut sauvegardé dans: " & $debugFile & @CRLF)

    Local $pattern = '"id":\s*(\d+)[^}]*"nom":\s*"([^"]*)"[^}]*"fichier_nom":\s*"([^"]*)"[^}]*"fichier_path":\s*"([^"]*)"[^}]*"est_zip":\s*(\d+)[^}]*"commande_lancement":\s*"([^"]*)"[^}]*"description":\s*"([^"]*)"[^}]*"defaut":\s*(\d+)'
    Local $aMatches = StringRegExp($data, $pattern, 3)

    If Not IsArray($aMatches) Then
        _LogToGUI("ERREUR: Parsing nettoyage échoué" & @CRLF)
        Local $iniPath = $installPath & "\ini\nettoyage.ini"
        IniWrite($iniPath, "cfg", "nb", "0")
        Return True
    EndIf

    Local $iniPath = $installPath & "\ini\nettoyage.ini"
    If FileExists($iniPath) Then FileDelete($iniPath)

    Local $nb_items = UBound($aMatches) / 8
    IniWrite($iniPath, "cfg", "nb", $nb_items)

    For $i = 0 To $nb_items - 1
        Local $section = $i + 1
        IniWrite($iniPath, $section, "id", $aMatches[$i * 8])
        IniWrite($iniPath, $section, "name", _CleanJSONString_Installeur($aMatches[$i * 8 + 1]))
        IniWrite($iniPath, $section, "fichier_nom", _CleanJSONString_Installeur($aMatches[$i * 8 + 2]))
        IniWrite($iniPath, $section, "fichier_path", _CleanJSONString_Installeur($aMatches[$i * 8 + 3]))
        IniWrite($iniPath, $section, "est_zip", $aMatches[$i * 8 + 4])
        IniWrite($iniPath, $section, "commande_lancement", _CleanJSONString_Installeur($aMatches[$i * 8 + 5]))
        IniWrite($iniPath, $section, "description", _CleanJSONString_Installeur($aMatches[$i * 8 + 6]))
        IniWrite($iniPath, $section, "defaut", $aMatches[$i * 8 + 7])
    Next

    _LogToGUI("SUCCESS: Nettoyage - " & $nb_items & " éléments créés" & @CRLF)
    Return True
EndFunc

; Télécharger et créer le fichier personnalisation.ini
Func _DownloadPersonnalisation_Installeur($installPath)
    Local $data = _GetPersonnalisation_API_Installeur()
    If @error Or $data = False Or $data = "" Then
        _LogToGUI("ERREUR: API personnalisation inaccessible" & @CRLF)
        _LogToGUI("INFO: Création d'un fichier personnalisation.ini vide" & @CRLF)
        Local $iniPath = $installPath & "\ini\personnalisation.ini"
        IniWrite($iniPath, "cfg", "nb", "0")
        Return True
    EndIf

    _LogToGUI("Personnalisation - données reçues (" & StringLen($data) & " caractères)" & @CRLF)

    ; Pattern plus robuste pour le JSON réel avec tous les champs
    Local $pattern = '\{\s*"id":\s*(\d+),\s*"nom":\s*"([^"]*)",\s*"type_registre":\s*"([^"]*)",\s*"fichier_reg_nom":\s*"([^"]*)",\s*"fichier_reg_path":\s*"([^"]*)",\s*"ligne_registre":\s*"((?:[^"\\]|\\.)*)",\s*"description":\s*"([^"]*)",\s*"OS":\s*(\d+),\s*"defaut":\s*(\d+)(?:,\s*"[^"]*":\s*"[^"]*")*\s*\}'
    Local $aMatches = StringRegExp($data, $pattern, 3)

    If Not IsArray($aMatches) Then
        _LogToGUI("ERREUR: Parsing personnalisation échoué" & @CRLF)
        _LogToGUI("DEBUG: Échantillon des données pour analyse: " & StringLeft($data, 1000) & @CRLF)
        ; Créer un fichier vide
        Local $iniPath = $installPath & "\ini\personnalisation.ini"
        IniWrite($iniPath, "cfg", "nb", "0")
        Return True
    EndIf

    Local $objectCount = UBound($aMatches) / 9
    _LogToGUI("Personnalisation: " & $objectCount & " éléments trouvés" & @CRLF)

    Local $iniPath = $installPath & "\ini\personnalisation.ini"
    If FileExists($iniPath) Then FileDelete($iniPath)
    IniWrite($iniPath, "cfg", "nb", $objectCount)

    For $i = 0 To $objectCount - 1
        Local $baseIndex = $i * 9
        Local $iniIndex = $i + 1
        
        Local $id = $aMatches[$baseIndex]
        Local $nom = $aMatches[$baseIndex + 1]
        Local $type_registre = $aMatches[$baseIndex + 2]
        Local $fichier_reg_nom = $aMatches[$baseIndex + 3]
        Local $fichier_reg_path = $aMatches[$baseIndex + 4]
        Local $ligne_registre = $aMatches[$baseIndex + 5]
        Local $description = $aMatches[$baseIndex + 6]
        Local $os = $aMatches[$baseIndex + 7]
        Local $defaut = $aMatches[$baseIndex + 8]

        ; Nettoyer les échappements dans ligne_registre
        $ligne_registre = StringReplace($ligne_registre, "]\n", "]")
        $ligne_registre = StringReplace($ligne_registre, "\\", "\")
        $ligne_registre = StringReplace($ligne_registre, '\"', '"')

        IniWrite($iniPath, $iniIndex, "id", $id)
        IniWrite($iniPath, $iniIndex, "nom", $nom)
        IniWrite($iniPath, $iniIndex, "type_registre", $type_registre)
        IniWrite($iniPath, $iniIndex, "fichier_reg_nom", $fichier_reg_nom)
        IniWrite($iniPath, $iniIndex, "fichier_reg_path", $fichier_reg_path)
        IniWrite($iniPath, $iniIndex, "ligne_registre", $ligne_registre)
        IniWrite($iniPath, $iniIndex, "description", $description)
        IniWrite($iniPath, $iniIndex, "OS", $os)
        IniWrite($iniPath, $iniIndex, "defaut", $defaut)
    Next

    _LogToGUI("SUCCESS: Personnalisation - " & $objectCount & " éléments créés" & @CRLF)
    Return True
EndFunc

; Fonction principale pour générer tous les fichiers INI depuis l'API
Func _GenerateINIFilesFromAPI($installPath)
    _LogToGUI("=== GÉNÉRATION DES FICHIERS INI DEPUIS L'API ===" & @CRLF)
    _LogToGUI("DEBUG: Chemin d'installation: " & $installPath & @CRLF)

    ; Initialiser la configuration API
    _LogToGUI("DEBUG: Initialisation de la configuration API..." & @CRLF)
    If Not _InitAPIConfig($installPath) Then
        _LogToGUI("ERREUR: Impossible d'initialiser la configuration API" & @CRLF)
        Return False
    EndIf

    ; Vérifier si le dossier ini existe
    Local $iniDir = $installPath & "\ini"
    _LogToGUI("DEBUG: Vérification du dossier INI: " & $iniDir & @CRLF)

    If Not FileExists($iniDir) Then
        _LogToGUI("DEBUG: Création du dossier INI..." & @CRLF)
        If Not DirCreate($iniDir) Then
            _LogToGUI("ERREUR: Impossible de créer le dossier INI" & @CRLF)
            Return False
        EndIf
    Else
        _LogToGUI("DEBUG: Dossier INI existe déjà" & @CRLF)
    EndIf
    
    Local $success = True
    Local $logicielsOK = False, $commandesOK = False, $nettoyageOK = False, $personnalisationOK = False
    
    ; Télécharger chaque type de données
    _LogToGUI("DEBUG: === DÉBUT TÉLÉCHARGEMENT LOGICIELS ===" & @CRLF)
    $logicielsOK = _DownloadLogiciels_Installeur($installPath)
    $success = $success And $logicielsOK
    If $logicielsOK Then
        _LogToGUI("DEBUG: Logiciels: SUCCESS" & @CRLF)
    Else
        _LogToGUI("DEBUG: Logiciels: ÉCHEC" & @CRLF)
    EndIf

    _LogToGUI("DEBUG: === DÉBUT TÉLÉCHARGEMENT COMMANDES ===" & @CRLF)
    $commandesOK = _DownloadCommandes_Installeur($installPath)
    $success = $success And $commandesOK
    If $commandesOK Then
        _LogToGUI("DEBUG: Commandes: SUCCESS" & @CRLF)
    Else
        _LogToGUI("DEBUG: Commandes: ÉCHEC" & @CRLF)
    EndIf

    _LogToGUI("DEBUG: === DÉBUT TÉLÉCHARGEMENT NETTOYAGE ===" & @CRLF)
    $nettoyageOK = _DownloadNettoyage_Installeur($installPath)
    $success = $success And $nettoyageOK
    If $nettoyageOK Then
        _LogToGUI("DEBUG: Nettoyage: SUCCESS" & @CRLF)
    Else
        _LogToGUI("DEBUG: Nettoyage: ÉCHEC" & @CRLF)
    EndIf

    _LogToGUI("DEBUG: === DÉBUT TÉLÉCHARGEMENT PERSONNALISATION ===" & @CRLF)
    $personnalisationOK = _DownloadPersonnalisation_Installeur($installPath)
    $success = $success And $personnalisationOK
    If $personnalisationOK Then
        _LogToGUI("DEBUG: Personnalisation: SUCCESS" & @CRLF)
    Else
        _LogToGUI("DEBUG: Personnalisation: ÉCHEC" & @CRLF)
    EndIf

    _LogToGUI("DEBUG: === RÉSUMÉ FINAL ===" & @CRLF)
    If $logicielsOK Then
        _LogToGUI("  Logiciels: ✓" & @CRLF)
    Else
        _LogToGUI("  Logiciels: ✗" & @CRLF)
    EndIf
    If $commandesOK Then
        _LogToGUI("  Commandes: ✓" & @CRLF)
    Else
        _LogToGUI("  Commandes: ✗" & @CRLF)
    EndIf
    If $nettoyageOK Then
        _LogToGUI("  Nettoyage: ✓" & @CRLF)
    Else
        _LogToGUI("  Nettoyage: ✗" & @CRLF)
    EndIf
    If $personnalisationOK Then
        _LogToGUI("  Personnalisation: ✓" & @CRLF)
    Else
        _LogToGUI("  Personnalisation: ✗" & @CRLF)
    EndIf

    If $success Then
        _LogToGUI("SUCCESS: Tous les fichiers INI ont été générés depuis l'API" & @CRLF)
        Return True
    Else
        _LogToGUI("ERREUR: Certains fichiers INI n'ont pas pu être générés" & @CRLF)
        Return False
    EndIf
EndFunc

Func _FindLogoIcon($installPath)
    ; Recherche logo.ico dans différents emplacements
    Local $searchPaths[4] = [$installPath & "\logo.ico", $installPath & "\TechSuivi\logo.ico", $installPath & "\TechSuivi V4\logo.ico", $installPath & "\ini\logo.ico"]
    
    For $i = 0 To 3
        If FileExists($searchPaths[$i]) Then
            Return $searchPaths[$i]
        EndIf
    Next
    
    ; Recherche récursive
    Local $hSearch = FileFindFirstFile($installPath & "\*")
    If $hSearch <> -1 Then
        While 1
            Local $file = FileFindNextFile($hSearch)
            If @error Then ExitLoop
            If @extended Then
                Local $subPath = $installPath & "\" & $file & "\logo.ico"
                If FileExists($subPath) Then
                    FileClose($hSearch)
                    Return $subPath
                EndIf
            ElseIf $file = "logo.ico" Then
                FileClose($hSearch)
                Return $installPath & "\" & $file
            EndIf
        WEnd
        FileClose($hSearch)
    EndIf
    
    Return ""
EndFunc
