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
#include <GuiListView.au3>

; Globals for ProcessBridge (Logiciels)
Global $sBridgeExeSoft = @ScriptDir & "\tools\ProcessBridge.exe"
Global $sLogFileSoft = @ScriptDir & "\tools\latest_soft.log"
Global $sInputFileSoft = @ScriptDir & "\tools\input_soft.txt"
Global $iPidBridgeSoft = 0
Global $iLastLogSizeSoft = 0
Global $aSoftQueue[0]
Global $iCurrentSoftIndex = -1

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




Global $aLogicielsData[100][3] ; [ID, Cmd, Auto] - Resize if needed or use static max

func _logiciels()
    ; Layout
    $coord1 = 20
    $coord2 = 40
    
    ; --- Group Installation ---
    $group_install = GUICtrlCreateGroup("Installation", $coord1, $coord2, 330, 480)
    
    ; ListView for Software
    ; Columns: Nom (240), Type (65)
    Global $idListSoft = GUICtrlCreateListView("Logiciel|Type", $coord1 + 10, $coord2 + 20, 310, 200, BitOR($LVS_REPORT, $LVS_SHOWSELALWAYS), BitOR($LVS_EX_CHECKBOXES, $LVS_EX_FULLROWSELECT))
    _GUICtrlListView_SetColumnWidth($idListSoft, 0, 200)
    _GUICtrlListView_SetColumnWidth($idListSoft, 1, 65)
    
    ; Populate
    For $a = 1 To $nb_log
        Local $name = IniRead($ini_log, $a, "nom", "")
        Local $type = IniRead($ini_log, $a, "type_installation", "")
        Local $cmd  = IniRead($ini_log, $a, "commande_winget", "")
        Local $auto = IniRead($ini_log, $a, "defaut", "0")
        
        Local $sItem = $name & "|" & $type
        Local $idItem = GUICtrlCreateListViewItem($sItem, $idListSoft)
        Local $iIndex = $a - 1
        
        ; Store Technical Data in Array (mapped by ListView Index)
        If $iIndex >= 0 And $iIndex < UBound($aLogicielsData) Then
            $aLogicielsData[$iIndex][0] = $a    ; ID (INI Index)
            $aLogicielsData[$iIndex][1] = $cmd  ; Winget Cmd
            $aLogicielsData[$iIndex][2] = $auto ; Default
            
            ; Auto-check defaults
            If $auto = "1" Then _GUICtrlListView_SetItemChecked($idListSoft, $iIndex, True)
        EndIf
    Next
    
    ; Console Output using Edit Control
    GUICtrlCreateLabel("Sortie Console (Winget) :", $coord1 + 10, $coord2 + 230, 300, 15)
    Global $idOutputSoft = GUICtrlCreateEdit("", $coord1 + 10, $coord2 + 245, 310, 175, BitOR($ES_READONLY, $ES_MULTILINE, $WS_VSCROLL, $ES_AUTOVSCROLL))
    GUICtrlSetFont(-1, 8, 400, 0, "Consolas")
    GUICtrlSetBkColor(-1, 0x1E1E1E)
    GUICtrlSetColor(-1, 0xCCCCCC)
    
    ; Buttons
    $coordBtnY = $coord2 + 430
    Global $btnSoftAll = GUICtrlCreateButton("Tout (Dé)Select", $coord1 + 10, $coordBtnY, 150, 25)
    GUICtrlSetOnEvent(-1, "_SoftToggleAll")
    
    Global $btnSoftDef = GUICtrlCreateButton("Défaut", $coord1 + 170, $coordBtnY, 150, 25)
    GUICtrlSetOnEvent(-1, "_SoftCheckDefault")
    
    Global $btnSoftRun = GUICtrlCreateButton("INSTALLER SÉLECTION", $coord1 + 10, $coordBtnY + 30, 310, 30)
    GUICtrlSetOnEvent(-1, "_modelog")
    GUICtrlSetFont(-1, 10, 600)
    
    GUICtrlCreateGroup("", -99, -99, 1, 1) ; End Group

    ; --- Initialisation TreeView et Groupes Droite (Reused from existing code logic, placed same coords) ---
    ; Group Désinstallation
    $group_uninstall = GUICtrlCreateGroup("Désinstallation", 360, 40, 400, 420)
    $idTreeView = GUICtrlCreateTreeView(370, 60, 380, 350, BitOR($TVS_CHECKBOXES, $TVS_FULLROWSELECT))
    _GUICtrlTreeView_BeginUpdate($idTreeView)
    Local $aListeProgInst = _ListeProgrammes()
    For $i = 0 To UBound($aListeProgInst) - 1
        $hItem = GUICtrlCreateTreeViewItem($aListeProgInst[$i][0], $idTreeView)
        _GUICtrlTreeView_SetIcon($idTreeView, $hItem, $aListeProgInst[$i][1], $aListeProgInst[$i][2])
    Next
    _GUICtrlTreeView_EndUpdate($idTreeView)

    $button_refresh_log = GUICtrlCreateButton("Actualiser la liste", 370, 420, 130, 30)
    GUICtrlSetOnEvent($button_refresh_log, _refreshTreeView)
    
    $button_treeview_uninstall = GUICtrlCreateButton("Désinstaller sélection", 510, 420, 150, 30)
    GUICtrlSetOnEvent($button_treeview_uninstall, _uninstalllog)
    GUICtrlCreateGroup("", -99, -99, 1, 1)

    ; Group Progression (Global)
    $group_progress = GUICtrlCreateGroup("Progression", 360, 470, 400, 50)
    $hProgressBar = GUICtrlCreateProgress(370, 490, 380, 20, $PBS_SMOOTH)
    GUICtrlSetData($hProgressBar, 0)
    GUICtrlCreateGroup("", -99, -99, 1, 1)
EndFunc

; --- Selection Helpers ---

Func _SoftToggleAll()
    $toggle_state = Not $toggle_state
    Local $iCount = _GUICtrlListView_GetItemCount($idListSoft)
    For $i = 0 To $iCount - 1
        _GUICtrlListView_SetItemChecked($idListSoft, $i, $toggle_state)
    Next
EndFunc

Func _SoftCheckDefault()
    Local $iCount = _GUICtrlListView_GetItemCount($idListSoft)
    For $i = 0 To $iCount - 1
        ; Check from Array
        Local $isDefault = False
        If $i < UBound($aLogicielsData) Then
            $isDefault = ($aLogicielsData[$i][2] == "1")
        EndIf
        _GUICtrlListView_SetItemChecked($idListSoft, $i, $isDefault)
    Next
EndFunc

; --- Queue Logic ---

Func _modelog()
    ; Build Queue
    ReDim $aSoftQueue[0]
    Local $iCount = _GUICtrlListView_GetItemCount($idListSoft)
    
    For $i = 0 To $iCount - 1
        If _GUICtrlListView_GetItemChecked($idListSoft, $i) Then
            ; Uncheck to mark as pending
            _GUICtrlListView_SetItemChecked($idListSoft, $i, False)
            
            ; Get Data from View (Name/Type) AND Array (ID/Cmd)
            Local $name = _GUICtrlListView_GetItemText($idListSoft, $i, 0)
            Local $type = _GUICtrlListView_GetItemText($idListSoft, $i, 1)
            
            Local $id_ini = 0
            Local $cmd = ""
            
            If $i < UBound($aLogicielsData) Then
                $id_ini = $aLogicielsData[$i][0]
                $cmd    = $aLogicielsData[$i][1]
            EndIf
            
            Local $idx = $i
            _SoftQueueAdd($idx, $id_ini, $type, $name, $cmd)
        EndIf
    Next
    
    If UBound($aSoftQueue) > 0 Then
        _Log("Début de l'installation (" & UBound($aSoftQueue) & " éléments)", "Logiciels", "Queue")
        _ProcessSoftQueue()
    Else
        MsgBox(64, "Info", "Aucun logiciel sélectionné.")
    EndIf
EndFunc

Func _SoftQueueAdd($idx, $id_ini, $type, $name, $cmd)
    Local $i = UBound($aSoftQueue)
    ReDim $aSoftQueue[$i+1]
    $aSoftQueue[$i] = $idx & "*|*" & $id_ini & "*|*" & $type & "*|*" & $name & "*|*" & $cmd
EndFunc

Func _ProcessSoftQueue()
    ; Check if Bridge running (Winget)
    If $iPidBridgeSoft And ProcessExists($iPidBridgeSoft) Then Return
    
    ; Check if empty
    If UBound($aSoftQueue) = 0 Then
        _Log("Fin de la file d'attente logiciels.", "Logiciels", "Queue")
        Return
    EndIf
    
    ; Pop
    Local $sRaw = $aSoftQueue[0]
    Local $aSplit = StringSplit($sRaw, "*|*", 1)
    ; [1]Idx, [2]ID_INI, [3]Type, [4]Name, [5]Cmd
    
    ; Shift Array
    Local $iSize = UBound($aSoftQueue)
    For $i = 0 To $iSize - 2
        $aSoftQueue[$i] = $aSoftQueue[$i+1]
    Next
    ReDim $aSoftQueue[$iSize-1]
    
    ; Parse Data
    Local $iListIdx = $aSplit[1]
    Local $id_ini   = $aSplit[2]
    Local $sType    = $aSplit[3]
    Local $sName    = $aSplit[4]
    Local $sCmd     = $aSplit[5]
    
    $iCurrentSoftIndex = $iListIdx
    
    _UpdateProgressText("Traitement de : " & $sName)
    _Log("Traitement : " & $sName & " (" & $sType & ")", "Logiciels", "Traitement")
    
    If $sType = "winget" Then
        ; Launch Async Bridge
        Local $fullCmd = "winget install " & $sCmd & " --accept-source-agreements --accept-package-agreements --silent"
        _StartBridgeSoft($fullCmd, $sName)
        
    ElseIf $sType = "fichier" Then
        ; Launch Sync/Blocking FTP
        _ftp_install($id_ini)
        
        ; Sync function finished, loop immediately to next
        $iCurrentSoftIndex = -1
        _ProcessSoftQueue()
    Else
        _Log("Type inconnu : " & $sType, "Logiciels", "Erreur")
        _ProcessSoftQueue()
    EndIf
EndFunc

; --- Bridge Helpers (Winget) ---

Func _StartBridgeSoft($sCmd, $sName)
    If $iPidBridgeSoft Then _StopBridgeSoft()
    
    FileDelete($sLogFileSoft)
    FileDelete($sInputFileSoft)
    FileWrite($sInputFileSoft, "")
    $iLastLogSizeSoft = 0
    
    GUICtrlSetData($idOutputSoft, "--- Winget : " & $sName & " ---" & @CRLF)
    
    ; Run Bridge (Output to latest_soft.log)
    $iPidBridgeSoft = Run('"' & $sBridgeExeSoft & '" "' & $sCmd & '" "' & $sLogFileSoft & '" "' & $sInputFileSoft & '"', @ScriptDir & "\tools", @SW_HIDE)
    
    AdlibRegister("_UpdateLogSoftCtx", 200)
EndFunc

Func _StopBridgeSoft()
    If $iPidBridgeSoft Then ProcessClose($iPidBridgeSoft)
    $iPidBridgeSoft = 0
    AdlibUnRegister("_UpdateLogSoftCtx")
    $iCurrentSoftIndex = -1
EndFunc

Func _UpdateLogSoftCtx()
	If Not FileExists($sLogFileSoft) Then Return
	Local $iSize = FileGetSize($sLogFileSoft)
	
	If $iSize > $iLastLogSizeSoft Then
		Local $hFile = FileOpen($sLogFileSoft, 256 + $FO_READ) 
		FileSetPos($hFile, $iLastLogSizeSoft, $FILE_BEGIN)
		Local $sNewData = FileRead($hFile)
		FileClose($hFile)
		
		$iLastLogSizeSoft = $iSize
		GUICtrlSetData($idOutputSoft, $sNewData, 1)
	EndIf
	
	; If process finished
	If $iPidBridgeSoft And Not ProcessExists($iPidBridgeSoft) Then
	    _StopBridgeSoft()
        _ProcessSoftQueue()
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





