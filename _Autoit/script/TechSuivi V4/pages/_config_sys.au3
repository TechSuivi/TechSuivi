#cs ----------------------------------------------------------------------------

 AutoIt Version: 3.3.16.1
 Author:         myName

 Script Function:
	Template AutoIt script.

#ce ----------------------------------------------------------------------------

#include <EditConstants.au3>
#include <WindowsConstants.au3>

; Chemin vers smartctl
Global $g_sSmartCtl = @ScriptDir & "\tools\smartctl.exe"
Global $g_SmartJsonData = "" ; Stocker le JSON pour sauvegarde

func _configsys()
	$ini_sys_dir = @ScriptDir & "/ini/sys.ini"

			$coord1 = 20
			$coord2 = 40

			Global $infosys = GUICtrlCreateGroup("Information Systeme :", $coord1, $coord2, 320, 490)

			$coord1 = 30
			$coord2 = 60

			GUICtrlCreateLabel("Version : " &IniRead($ini_sys_dir,"OS1","Caption","") & " "& IniRead($ini_sys_dir,"OS1","OSArchitecture","")&" " & IniRead($ini_sys_dir,"OS1","Buildversion","") ,$coord1, $coord2)
			$coord2 += 20
			GUICtrlCreateLabel("Installé le : " & IniRead($ini_sys_dir,"OS1","InstallDate",""),$coord1, $coord2)
			$coord2 += 40
			GUICtrlCreateLabel("CPU : " & IniRead($ini_sys_dir,"CPU1","Name",""),$coord1, $coord2)
			$coord2 += 20
			GUICtrlCreateLabel("Nb CPU : " & IniRead($ini_sys_dir,"ComputerSystem1","NumberOfProcessors","") & " NB Cores : " & IniRead($ini_sys_dir,"ComputerSystem1","NumberOfLogicalProcessors","") ,$coord1, $coord2)
			$coord2 += 40
			GUICtrlCreateLabel("GPU : " & IniRead($ini_sys_dir,"CG1","Description",""),$coord1, $coord2)
			$coord2 += 20
			GUICtrlCreateLabel("Driver : " & IniRead($ini_sys_dir,"CG1","DriverVersion",""),$coord1, $coord2)

$coord2 += 20
$_total_ram = 0

For $i = 1 To IniRead($ini_sys_dir,"NB","RAM","") Step 1
			$coord2 += 20
			GUICtrlCreateLabel("RAM " & IniRead($ini_sys_dir,"RAM" & $i,"DeviceLocator","")& " : " & IniRead($ini_sys_dir,"RAM" & $i,"Capacity","")/1024 & "Go @ " & IniRead($ini_sys_dir,"RAM" & $i,"Speed",""),$coord1, $coord2)

			$_total_ram = $_total_ram + IniRead($ini_sys_dir,"RAM" & $i,"Capacity","")

Next

			$coord2 += 20
			GUICtrlCreateLabel("Total RAM : " & $_total_ram/1024 & "Go",$coord1, $coord2)


$coord2 += 20


For $i = 1 To IniRead($ini_sys_dir,"NB","DD","") Step 1
			$coord2 += 20
			GUICtrlCreateLabel("DD " & $i &" : " & IniRead($ini_sys_dir,"DD" & $i,"Caption","") & " : " & round(IniRead($ini_sys_dir,"DD" & $i,"Size",""),1) & "Go",$coord1, $coord2)



Next

			$coord2 += 40
			GUICtrlCreateLabel("Fabricant CM : " & IniRead($ini_sys_dir,"CM1","Manufacturer",""),$coord1, $coord2)
			$coord2 += 20
			GUICtrlCreateLabel("Modele CM : " & IniRead($ini_sys_dir,"CM1","Model",""),$coord1, $coord2)
			$coord2 += 20
			GUICtrlCreateLabel("Bios : " & IniRead($ini_sys_dir,"BIOS1","SMBIOSBIOSVersion","") & " @ Le : " &IniRead($ini_sys_dir,"BIOS1","ReleaseDate" ,""),$coord1, $coord2)


			$coord1 = 350
			$coord2 = 40

			; Bouton pour lancer le diagnostic de mémoire Windows
			Global $btn_memory_diag = GUICtrlCreateButton("Diagnostic Mémoire Windows", $coord1, $coord2, 200, 30)
			GUICtrlSetTip($btn_memory_diag, "Exécuter le diagnostic de la mémoire Windows (mdsched)")
			GUICtrlSetOnEvent($btn_memory_diag, "_RunMemoryDiagnostic")

			; Bouton pour afficher les résultats du diagnostic
			$coord1 += 210
			Global $btn_memory_result = GUICtrlCreateButton("Résultat", $coord1, $coord2, 100, 30)
			GUICtrlSetTip($btn_memory_result, "Afficher les résultats du diagnostic de mémoire")
			GUICtrlSetOnEvent($btn_memory_result, "_ShowMemoryTestResult")

			; Bouton pour tester les disques durs avec SMART
			$coord1 = 350
			$coord2 += 40
			Global $btn_disk_test = GUICtrlCreateButton("Test Disques Durs (SMART)", $coord1, $coord2, 200, 30)
			GUICtrlSetTip($btn_disk_test, "Exécuter le test SMART des disques durs")
			GUICtrlSetOnEvent($btn_disk_test, "_RunDiskTest")

EndFunc

; Fonction pour exécuter le diagnostic de mémoire Windows
Func _RunMemoryDiagnostic()

		Run("mdsched.exe")
		_Log("Lancement du diagnostic de mémoire Windows (mdsched.exe)", "Config. Sys", "Mémoire")

EndFunc

; Fonction pour afficher les résultats du diagnostic de mémoire
Func _ShowMemoryTestResult()
	Local $cmd = 'powershell -command "Get-WinEvent -FilterHashtable @{LogName=''System'';Id=1201} | Select-Object -First 1 | Format-List Message"'
	Local $output = Run(@ComSpec & " /c " & $cmd, "", @SW_HIDE, $STDOUT_CHILD)

	; Attendre que la commande se termine
	ProcessWaitClose($output)

	; Lire la sortie
	Local $result = StdoutRead($output)

	; Afficher les résultats
	If $result <> "" Then
		MsgBox(0, "Résultat MemTest", $result)
		_Log("Consultation des résultats du diagnostic de mémoire", "Config. Sys", "Mémoire")
	Else
		MsgBox(16, "Résultat MemTest", "Aucun résultat de diagnostic trouvé." & @CRLF & @CRLF & "Assurez-vous d'avoir exécuté le diagnostic de mémoire Windows au moins une fois.")
		_Log("Aucun résultat de diagnostic de mémoire trouvé", "Config. Sys", "Mémoire")
	EndIf
EndFunc

; ----------------------------------------------------------------------
; Retourne un tableau 2D des devices détectés par "smartctl --scan-open"
; [0][0] = nombre d'entrées
; [i][0] = chemin (/dev/sda, /dev/nvme0, ...)
; [i][1] = type (-d xxx ou chaîne vide)
; ----------------------------------------------------------------------
Func _SmartScanDevices()
    ; Essayer d'abord --scan-open, puis --scan si ça échoue
    Local $aScanCommands[2] = ["--scan-open", "--scan"]
    Local $sScan = ""

    For $sScanCmd In $aScanCommands
        Local $pid = Run('"' & $g_sSmartCtl & '" ' & $sScanCmd, "", @SW_HIDE, $STDOUT_CHILD + $STDERR_CHILD)
        $sScan = ""

        While 1
            Local $sLine = StdoutRead($pid)
            If @error Then ExitLoop
            $sScan &= $sLine
        WEnd
        ProcessWaitClose($pid)

        $sScan = StringStripWS($sScan, 3)

        ; Si on a des résultats, on arrête
        If $sScan <> "" Then ExitLoop
    Next

    If $sScan = "" Then Return SetError(1, 0, 0)

    ; Normaliser les sauts de ligne : remplacer LF seul par CRLF
    $sScan = StringReplace($sScan, @CRLF, @LF) ; D'abord tout en LF
    $sScan = StringReplace($sScan, @LF, @CRLF) ; Puis tout en CRLF

    Local $aLines = StringSplit($sScan, @CRLF, 1)
    If $aLines[0] = 0 Then Return SetError(2, 0, 0)

    ; On prend large (max 32 disques)
    Local $aDev[33][2]
    Local $iCount = 0

    For $i = 1 To $aLines[0]
        Local $l = StringStripWS($aLines[$i], 3)
        If $l = "" Then ContinueLoop

        ; Exemple de ligne :
        ; /dev/sda -d ata # ATA device
        ; /dev/nvme0 -d nvme # NVMe device
        ; /dev/sdb # some device
        Local $aMatch = StringRegExp($l, '(/dev/\S+)(?:\s+-d\s+(\S+))?', 1)
        If @error Then ContinueLoop

        $aDev[$iCount + 1][0] = $aMatch[0] ; chemin
        If UBound($aMatch) > 1 Then
            $aDev[$iCount + 1][1] = $aMatch[1] ; type (-d xxx)
        Else
            $aDev[$iCount + 1][1] = ""
        EndIf

        $iCount += 1
        If $iCount >= 32 Then ExitLoop
    Next

    If $iCount = 0 Then Return SetError(3, 0, 0)

    $aDev[0][0] = $iCount
    Return $aDev
EndFunc

; ----------------------------------------------------------------------
; Helper : retourne la première capture d'un pattern regex, ou "" si rien
; ----------------------------------------------------------------------
Func _SmartGetFirstMatch($sText, $sPattern)
    Local $aMatch = StringRegExp($sText, $sPattern, 1)
    If @error Or UBound($aMatch) = 0 Then Return ""
    Return StringStripWS($aMatch[0], 3)
EndFunc

; ----------------------------------------------------------------------
; Convertit "1 634" → 1634, "7 623 848" → 7623848
; ----------------------------------------------------------------------
Func _SmartToInt($s)
    Local $sNum = StringRegExpReplace($s, "[^\d]", "")
    If $sNum = "" Then Return ""
    Return Number($sNum)
EndFunc

; ----------------------------------------------------------------------
; Calcule le statut global d'un disque : OK / SURVEILLANCE / CRITIQUE
; ----------------------------------------------------------------------
Func _SmartComputeStatus($health, $percentUsed, $mediaErr, $logErr, $critWarn)
    Local $status = "OK"
    Local $reason = ""

    ; Si smartctl dit FAILED → direct critique
    If StringInStr(StringUpper($health), "FAIL") Then
        $status = "CRITIQUE"
        $reason &= "SMART signale un échec. "
    EndIf

    ; Warnings NVMe
    If $critWarn <> "" And $critWarn <> "0x00" Then
        $status = "CRITIQUE"
        $reason &= "Critical Warning NVMe: " & $critWarn & ". "
    EndIf

    ; Usure (Percentage Used)
    If $percentUsed <> "" Then
        Local $u = Number($percentUsed)
        If $u >= 90 Then
            $status = "CRITIQUE"
            $reason &= "Usure SSD >= 90%. "
        ElseIf $u >= 70 And $status = "OK" Then
            $status = "SURVEILLANCE"
            $reason &= "Usure SSD >= 70%. "
        EndIf
    EndIf

    ; Erreurs média
    If $mediaErr <> "" And $mediaErr > 0 Then
        If $status = "OK" Then $status = "SURVEILLANCE"
        $reason &= "Erreurs média détectées (" & $mediaErr & "). "
    EndIf

    ; Logs d'erreur
    If $logErr <> "" And $logErr > 0 Then
        If $status = "OK" Then $status = "SURVEILLANCE"
        $reason &= "Entrées log erreurs: " & $logErr & ". "
    EndIf

    If $reason = "" Then $reason = "RAS."

    Return $status & " - " & $reason
EndFunc

; ----------------------------------------------------------------------
; Génère un JSON complet pour un disque NVMe (pour API TechSuivi)
; ----------------------------------------------------------------------
Func _SmartJson_NVMe($sDevPath, $sDevType, $sSmart)
    Local $model       = _SmartGetFirstMatch($sSmart, "(?m)^Model Number:\s+(.+)$")
    Local $serial      = _SmartGetFirstMatch($sSmart, "(?m)^Serial Number:\s+(.+)$")
    Local $fw          = _SmartGetFirstMatch($sSmart, "(?m)^Firmware Version:\s+(.+)$")
    Local $sizeBytes   = _SmartGetFirstMatch($sSmart, "(?m)^Namespace 1 Size/Capacity:\s+([^\[]+)\[")
    Local $sizeHuman   = _SmartGetFirstMatch($sSmart, "(?m)^Namespace 1 Size/Capacity:.*\[(.+)\]")
    Local $health      = _SmartGetFirstMatch($sSmart, "(?m)^SMART overall-health self-assessment test result:\s+(.+)$")
    Local $temp        = _SmartGetFirstMatch($sSmart, "(?m)^Temperature:\s+(\d+)\s+Celsius")
    Local $hoursRaw    = _SmartGetFirstMatch($sSmart, "(?m)^Power On Hours:\s+(.+)$")
    Local $hours       = _SmartToInt($hoursRaw)
    Local $cyclesRaw   = _SmartGetFirstMatch($sSmart, "(?m)^Power Cycles:\s+(.+)$")
    Local $cycles      = _SmartToInt($cyclesRaw)
    Local $percentUsed = _SmartGetFirstMatch($sSmart, "(?m)^Percentage Used:\s+(\d+)%")
    Local $availSpare  = _SmartGetFirstMatch($sSmart, "(?m)^Available Spare:\s+(\d+)%")
    Local $readTB      = _SmartGetFirstMatch($sSmart, "(?m)^Data Units Read:\s+[^\[]+\[([^\]]+)\]")
    Local $writeTB     = _SmartGetFirstMatch($sSmart, "(?m)^Data Units Written:\s+[^\[]+\[([^\]]+)\]")
    Local $unsafeRaw   = _SmartGetFirstMatch($sSmart, "(?m)^Unsafe Shutdowns:\s+(.+)$")
    Local $unsafe      = _SmartToInt($unsafeRaw)
    Local $mediaErrRaw = _SmartGetFirstMatch($sSmart, "(?m)^Media and Data Integrity Errors:\s+(.+)$")
    Local $mediaErr    = _SmartToInt($mediaErrRaw)
    Local $logErrRaw   = _SmartGetFirstMatch($sSmart, "(?m)^Error Information Log Entries:\s+(.+)$")
    Local $logErr      = _SmartToInt($logErrRaw)
    Local $critWarn    = _SmartGetFirstMatch($sSmart, "(?m)^Critical Warning:\s+(.+)$")

    Local $statusLine  = _SmartComputeStatus($health, $percentUsed, $mediaErr, $logErr, $critWarn)

    ; Construction du JSON (utiliser des guillemets doubles pour le JSON valide)
    Local $json  = "{"
    $json &= '"device":"'        & $sDevPath & '",'
    $json &= '"type":"'          & $sDevType & '",'
    $json &= '"model":"'         & $model & '",'
    $json &= '"serial":"'        & $serial & '",'
    $json &= '"firmware":"'      & $fw & '",'
    $json &= '"size_bytes":"'    & StringStripWS($sizeBytes, 3) & '",'
    $json &= '"size_human":"'    & StringStripWS($sizeHuman, 3) & '",'
    $json &= '"health":"'        & $health & '",'
    $json &= '"status":"'        & $statusLine & '",'
    $json &= '"temperature":"'   & $temp & '",'
    $json &= '"power_on_hours":' & ($hours <> "" ? $hours : 0) & ','
    $json &= '"power_cycles":'   & ($cycles <> "" ? $cycles : 0) & ','
    $json &= '"percent_used":'   & ($percentUsed <> "" ? Number($percentUsed) : 0) & ','
    $json &= '"available_spare":'& ($availSpare <> "" ? Number($availSpare) : 0) & ','
    $json &= '"data_read":"'     & $readTB & '",'
    $json &= '"data_written":"'  & $writeTB & '",'
    $json &= '"unsafe_shutdowns":'& ($unsafe <> "" ? $unsafe : 0) & ','
    $json &= '"media_errors":'   & ($mediaErr <> "" ? $mediaErr : 0) & ','
    $json &= '"log_entries":'    & ($logErr <> "" ? $logErr : 0) & ','
    $json &= '"critical_warning":"' & $critWarn & '"'
    $json &= "}"

    Return $json
EndFunc

; ----------------------------------------------------------------------
; Parse les informations principales d'un disque NVMe
; ----------------------------------------------------------------------
Func _ParseSmartSummary_NVMe($sSmart)
    ; Infos de base
    Local $model     = _SmartGetFirstMatch($sSmart, "(?m)^Model Number:\s+(.+)$")
    Local $serial    = _SmartGetFirstMatch($sSmart, "(?m)^Serial Number:\s+(.+)$")
    Local $fw        = _SmartGetFirstMatch($sSmart, "(?m)^Firmware Version:\s+(.+)$")
    Local $nvmever   = _SmartGetFirstMatch($sSmart, "(?m)^NVMe Version:\s+(.+)$")
    Local $sizeBytes = _SmartGetFirstMatch($sSmart, "(?m)^Namespace 1 Size/Capacity:\s+([^\[]+)\[")
    Local $sizeHuman = _SmartGetFirstMatch($sSmart, "(?m)^Namespace 1 Size/Capacity:.*\[(.+)\]")

    ; État global
    Local $health = _SmartGetFirstMatch($sSmart, "(?m)^SMART overall-health self-assessment test result:\s+(.+)$")

    ; Température
    Local $temp = _SmartGetFirstMatch($sSmart, "(?m)^Temperature:\s+(\d+)\s+Celsius")
    If $temp = "" Then _
        $temp = _SmartGetFirstMatch($sSmart, "(?m)^Composite Temperature:\s+(\d+)\s+Celsius")

    ; Heures de fonctionnement & cycles
    Local $hoursRaw   = _SmartGetFirstMatch($sSmart, "(?m)^Power On Hours:\s+(.+)$")
    Local $hours      = _SmartToInt($hoursRaw)
    Local $cyclesRaw  = _SmartGetFirstMatch($sSmart, "(?m)^Power Cycles:\s+(.+)$")
    Local $cycles     = _SmartToInt($cyclesRaw)

    ; Usure & spare
    Local $percentUsed   = _SmartGetFirstMatch($sSmart, "(?m)^Percentage Used:\s+(\d+)%")
    Local $availSpare    = _SmartGetFirstMatch($sSmart, "(?m)^Available Spare:\s+(\d+)%")
    Local $availSpareThr = _SmartGetFirstMatch($sSmart, "(?m)^Available Spare Threshold:\s+(\d+)%")

    ; Données lues/écrites (version lisible)
    Local $readTB  = _SmartGetFirstMatch($sSmart, "(?m)^Data Units Read:\s+[^\[]+\[([^\]]+)\]")
    Local $writeTB = _SmartGetFirstMatch($sSmart, "(?m)^Data Units Written:\s+[^\[]+\[([^\]]+)\]")

    ; Erreurs / intégrité
    Local $unsafeRaw   = _SmartGetFirstMatch($sSmart, "(?m)^Unsafe Shutdowns:\s+(.+)$")
    Local $unsafe      = _SmartToInt($unsafeRaw)
    Local $mediaErrRaw = _SmartGetFirstMatch($sSmart, "(?m)^Media and Data Integrity Errors:\s+(.+)$")
    Local $mediaErr    = _SmartToInt($mediaErrRaw)
    Local $logErrRaw   = _SmartGetFirstMatch($sSmart, "(?m)^Error Information Log Entries:\s+(.+)$")
    Local $logErr      = _SmartToInt($logErrRaw)

    Local $critWarn = _SmartGetFirstMatch($sSmart, "(?m)^Critical Warning:\s+(.+)$")

    ; Calculer le statut global
    Local $statusLine = _SmartComputeStatus($health, $percentUsed, $mediaErr, $logErr, $critWarn)

    ; Construction du résumé lisible
    Local $sSummary = ""
    $sSummary &= "Modèle        : " & $model & @CRLF
    $sSummary &= "N° de série   : " & $serial & @CRLF
    $sSummary &= "Firmware      : " & $fw & @CRLF
    If $nvmever <> "" Then $sSummary &= "NVMe Version  : " & $nvmever & @CRLF
    If $sizeHuman <> "" Then
        $sSummary &= "Capacité      : " & StringStripWS($sizeHuman, 3)
        If $sizeBytes <> "" Then $sSummary &= " (" & StringStripWS($sizeBytes, 3) & " octets)"
        $sSummary &= @CRLF
    EndIf

    ; Statut global en premier
    $sSummary &= "Statut        : " & $statusLine & @CRLF
    $sSummary &= @CRLF

    If $health <> "" Then $sSummary &= "État global   : " & $health & @CRLF
    If $temp <> "" Then $sSummary   &= "Température   : " & $temp & " °C" & @CRLF
    If $hours <> "" Then $sSummary  &= "Heures ON     : " & $hours & @CRLF
    If $cycles <> "" Then $sSummary &= "Cycles ON/OFF : " & $cycles & @CRLF

    If $percentUsed <> "" Then $sSummary &= "Usure SSD     : " & $percentUsed & " %" & @CRLF
    If $availSpare <> "" Then
        $sSummary &= "Spare dispo   : " & $availSpare & " %"
        If $availSpareThr <> "" Then $sSummary &= " (seuil " & $availSpareThr & " %)"
        $sSummary &= @CRLF
    EndIf

    If $readTB <> "" Then $sSummary  &= "Données lues  : " & $readTB & @CRLF
    If $writeTB <> "" Then $sSummary &= "Données écrites : " & $writeTB & @CRLF

    ; Toujours afficher les erreurs (même à 0 pour rassurer)
    $sSummary &= "Arrêts brutaux: " & ($unsafe <> "" ? $unsafe : "0") & @CRLF
    $sSummary &= "Erreurs média : " & ($mediaErr <> "" ? $mediaErr : "0") & @CRLF
    $sSummary &= "Entrées log err.: " & ($logErr <> "" ? $logErr : "0") & @CRLF

    If $critWarn <> "" Then $sSummary &= "Critical Warn : " & $critWarn & @CRLF

    Return $sSummary
EndFunc

; ----------------------------------------------------------------------
; Récupère les données SMART pour un device donné (/dev/sda, /dev/nvme0, ...)
; $sDevPath  = "/dev/sda"
; $sDevType  = "ata", "nvme", "sat", ... ou ""
; Retourne la sortie brute de smartctl -a
; ----------------------------------------------------------------------
Func GetSmart($sDevPath, $sDevType = "")
    Local $sData = ""
    Local $sCmd

    ; Si on connaît déjà le type (scan-open), on commence par ça
    If $sDevType <> "" Then
        $sCmd = '"' & $g_sSmartCtl & '" -a ' & $sDevPath & ' -d ' & $sDevType
        Local $pid = Run($sCmd, "", @SW_HIDE, $STDOUT_CHILD)
        While 1
            Local $sLine = StdoutRead($pid)
            If @error Then ExitLoop
            $sData &= $sLine
        WEnd
        ProcessWaitClose($pid)

        ; Si ça marche et qu'il n'y a pas d'erreur d'argument, on garde
        If $sData <> "" And _
           Not StringInStr($sData, "Unable to detect device type") And _
           Not StringInStr($sData, "Invalid argument") Then
            Return $sData
        EndIf
    EndIf

    ; Fallback : essayer différents types automatiquement
    Local $aTypes[4] = ["", "nvme", "sat", "ata"]

    For $t In $aTypes
        $sData = ""
        If $t = "" Then
            $sCmd = '"' & $g_sSmartCtl & '" -a ' & $sDevPath
        Else
            $sCmd = '"' & $g_sSmartCtl & '" -a ' & $sDevPath & ' -d ' & $t
        EndIf

        Local $pid2 = Run($sCmd, "", @SW_HIDE, $STDOUT_CHILD)
        While 1
            Local $sLine2 = StdoutRead($pid2)
            If @error Then ExitLoop
            $sData &= $sLine2
        WEnd
        ProcessWaitClose($pid2)

        If $sData <> "" And _
           Not StringInStr($sData, "Unable to detect device type") And _
           Not StringInStr($sData, "Invalid argument") Then
            ExitLoop
        EndIf
    Next

    Return $sData
EndFunc

; Fonction pour exécuter le test SMART des disques durs
Func _RunDiskTest()
    _Log("Scan SMART des disques...", "Config. Sys", "Disque")

    Local $aDev = _SmartScanDevices()
    If @error Then
        Local $errNum = @error
        Local $errMsg = "Aucun disque détecté par smartctl."

        If $errNum = 1 Then
            $errMsg &= @CRLF & "smartctl n'a retourné aucune donnée."
        ElseIf $errNum = 2 Then
            $errMsg &= @CRLF & "Impossible de parser la sortie de smartctl."
        ElseIf $errNum = 3 Then
            $errMsg &= @CRLF & "Aucun disque trouvé dans la sortie de smartctl."
        EndIf

        $errMsg &= @CRLF & @CRLF & "Vérifiez que :" & @CRLF
        $errMsg &= "• smartctl.exe est présent dans " & @ScriptDir & "\tools\" & @CRLF
        $errMsg &= "• Le programme est lancé en administrateur" & @CRLF
        $errMsg &= "• Consultez la console pour voir la sortie de smartctl"

        MsgBox(48, "Test SMART", $errMsg)
        _Log("Aucun disque détecté (smartctl) - Erreur " & $errNum, "Config. Sys", "Disque")
        Return
    EndIf

    Local $iCount = $aDev[0][0]
    _Log($iCount & " disque(s) détecté(s) via smartctl", "Config. Sys", "Disque")

    Local $sResults = "=== Test SMART des Disques ===" & @CRLF
    $sResults &= "Nombre de disques détectés : " & $iCount & @CRLF & @CRLF

    ; Construire le JSON pour tous les disques
    Global $g_SmartJsonData = "["

    For $i = 1 To $iCount
        Local $sPath = $aDev[$i][0]
        Local $sType = $aDev[$i][1]

        Local $sTypeInfo = ($sType <> "" ? " (type: " & $sType & ")" : "")
        _Log("Lecture SMART : " & $sPath & $sTypeInfo & "...", "Config. Sys", "Disque")

        Local $sSmart = GetSmart($sPath, $sType)

        $sResults &= "--- Disque " & $i & " ---" & @CRLF
        $sResults &= "Device : " & $sPath & @CRLF
        If $sType <> "" Then $sResults &= "Type   : " & $sType & @CRLF
        $sResults &= @CRLF

        If $sSmart <> "" And _
           Not StringInStr($sSmart, "failed:") And _
           Not StringInStr($sSmart, "Invalid argument") Then

            ; Parser les infos principales si c'est un NVMe
            If $sType = "nvme" Then
                Local $sSummary = _ParseSmartSummary_NVMe($sSmart)
                $sResults &= $sSummary & @CRLF

                ; Ajouter au JSON
                If $i > 1 Then $g_SmartJsonData &= ","
                $g_SmartJsonData &= _SmartJson_NVMe($sPath, $sType, $sSmart)

                ; Extraire les infos clés pour le log
                Local $logModel = _SmartGetFirstMatch($sSmart, "(?m)^Model Number:\s+(.+)$")
                Local $logHealth = _SmartGetFirstMatch($sSmart, "(?m)^SMART overall-health self-assessment test result:\s+(.+)$")
                Local $logTemp = _SmartGetFirstMatch($sSmart, "(?m)^Temperature:\s+(\d+)\s+Celsius")
                Local $logHoursRaw = _SmartGetFirstMatch($sSmart, "(?m)^Power On Hours:\s+(.+)$")
                Local $logHours = _SmartToInt($logHoursRaw)
                Local $logUsure = _SmartGetFirstMatch($sSmart, "(?m)^Percentage Used:\s+(\d+)%")

                ; Afficher dans le log
                _Log("  → Modèle: " & $logModel, "Config. Sys", "Disque")
                _Log("  → État: " & $logHealth & " - Usure: " & $logUsure & "% - Heures: " & $logHours & " - Temp: " & $logTemp & "°C", "Config. Sys", "Disque")
            EndIf

            $sResults &= "------ DONNÉES SMART BRUTES ------" & @CRLF
            $sResults &= $sSmart & @CRLF & @CRLF
        Else
            $sResults &= "Impossible de récupérer les données SMART" & @CRLF & _
                         "(droits admin ? contrôleur exotique ?)" & @CRLF & @CRLF
            If $sSmart <> "" Then
                $sResults &= "Erreur brute :" & @CRLF & $sSmart & @CRLF & @CRLF
            EndIf
            _Log("  ✗ Échec de lecture SMART", "Config. Sys", "Disque")
        EndIf
    Next

    $g_SmartJsonData &= "]"

    ; Fenêtre d'affichage des résultats
    Global $hSmartGui = GUICreate("Résultats SMART", 900, 600)
    Global $editSmart = GUICtrlCreateEdit($sResults, 10, 10, 880, 540, _
        BitOR($ES_MULTILINE, $ES_AUTOVSCROLL, $ES_AUTOHSCROLL, $ES_READONLY, $WS_VSCROLL, $WS_HSCROLL))
    GUICtrlSetFont($editSmart, 9, 400, 0, "Courier New")

    ; Boutons en bas
    Global $btnSaveJsonGui = GUICtrlCreateButton("Sauvegarder JSON", 250, 560, 150, 30)
    GUICtrlSetOnEvent($btnSaveJsonGui, "_SaveSmartJson")
    Global $btnCloseSmartGui = GUICtrlCreateButton("Fermer", 500, 560, 100, 30)
    GUICtrlSetOnEvent($btnCloseSmartGui, "_CloseSmartGui")
    GUISetOnEvent(-3, "_CloseSmartGui", $hSmartGui)
    GUISetState(@SW_SHOW, $hSmartGui)

    _Log("Test SMART terminé - " & $iCount & " disque(s) analysé(s)", "Config. Sys", "Disque")
EndFunc

; Fonction pour sauvegarder le JSON SMART
Func _SaveSmartJson()
    If $g_SmartJsonData = "" Or $g_SmartJsonData = "[]" Then
        MsgBox(48, "Sauvegarder JSON", "Aucune donnée SMART à sauvegarder.")
        Return
    EndIf

    Local $sTimestamp = @YEAR & "-" & @MON & "-" & @MDAY & "_" & @HOUR & "-" & @MIN & "-" & @SEC
    Local $sFileName = @ScriptDir & "\smart_" & $sTimestamp & ".json"

    Local $hFile = FileOpen($sFileName, 2) ; Mode écriture (écrase si existe)
    If $hFile = -1 Then
        MsgBox(16, "Erreur", "Impossible de créer le fichier JSON." & @CRLF & $sFileName)
        Return
    EndIf

    FileWrite($hFile, $g_SmartJsonData)
    FileClose($hFile)

    MsgBox(64, "JSON Sauvegardé", "Les données SMART ont été sauvegardées :" & @CRLF & @CRLF & $sFileName)
    _Log("JSON sauvegardé : " & $sFileName, "Config. Sys", "JSON")
	_Log("Test du système de log", "Config. Sys")
EndFunc

; Fonction pour fermer la fenêtre SMART
Func _CloseSmartGui()
    GUIDelete($hSmartGui)
EndFunc

