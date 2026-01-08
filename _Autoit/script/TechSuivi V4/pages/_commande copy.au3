#cs ----------------------------------------------------------------------------

 AutoIt Version: 3.3.16.1
 Author:         myName

 Script Function:
	Template AutoIt script.

#ce ----------------------------------------------------------------------------

; Script Start - Add your code below here


;variable :
$ini_cmd = "ini\commandes.ini"
local $button_cmd_[50]

$nb_cmd = IniRead($ini_cmd,"cfg","nb","")
local $label_cmd_[50]
local $Checkbox_cmd_[50]


Func _cmd()

	; Relire le fichier INI au cas où il aurait été créé depuis le dernier chargement
	$nb_cmd = IniRead($ini_cmd,"cfg","nb","")

	$info_cmd = 0
	$coord1 = 20
	$coord2 = 40

	for $a = 1 To $nb_cmd Step 1

		$name_cmd = IniRead($ini_cmd,$a,"nom","")
		$description_cmd = IniRead($ini_cmd,$a,"description","")

		$Checkbox_cmd_[$a] = GUICtrlCreateCheckbox($name_cmd, $coord1, $coord2, 210, 25)
		$label_cmd_[$a] = GUICtrlCreateLabel($description_cmd, $coord1 + 220, $coord2+5, 300, 25)
		$coord2 = $coord2 + 25

	Next



		$coord2 = $coord2 + 20
		$button_install_cmd =	GUICtrlCreateButton("    Run    ",$coord1+80,$coord2)
		GUICtrlSetOnEvent($button_install_cmd, _check_cmd)




EndFunc

Func _check_cmd()

	for $a = 1 To $nb_cmd Step 1

		if GUICtrlRead($Checkbox_cmd_[$a]) = 1 then

			$commande_cmd = IniRead($ini_cmd,$a,"commande","")

			If $commande_cmd = "func_cleanmgr" Then
				_Log("--------- Commande lancée : Nettoyage du disque - CleanMGR ---------", "Commande")
				CLEANMGR()

			Else

				_cmdrun($a)

			EndIf

		EndIf

	Next



endfunc
#cs commande à supprimer

Func _commande($_cmd)

	If $_cmd = "chk" Then

;~ 			$commande = "chkdsk /r c:"
;~ 			RunWait(@ComSpec & " /c " & $commande)

		_cmdrun("chkdsk /r c:")

	ElseIf $_cmd = "sfc" Then

		_cmdrun("SFC /scannow")



	ElseIf $_cmd = "defrag" Then

		_cmdrun("defrag c: /U /V /O")

	ElseIf $_cmd = "autologon" Then

		;pour windows 11 il faut passer : Computer\HKEY_LOCAL_MACHINE\SOFTWARE\Microsoft\Windows NT\CurrentVersion\PasswordLess\Device à 0
		;ne fonctionne pas avec un code pin

		$commande = 'netplwiz'
		runwait($commande)

		$msg_autologon = MsgBox(4,"","Avez vous activez l'autologon ?")

		if $msg_autologon = 6 Then
		GUICtrlSetState($label_autologon_no, $GUI_hide)
		GUICtrlSetState($label_autologon_yes, $GUI_show)
		ElseIf $msg_autologon = 7 Then
		GUICtrlSetState($label_autologon_yes, $GUI_hide)
		GUICtrlSetState($label_autologon_no, $GUI_show)
		endif

	EndIf




EndFunc
#ce



; Fonction pour nettoyer les caractères d'encodage problématiques
Func _CleanEncodingChars($sText)
    ; Remplacer les caractères d'encodage corrompus
    $sText = StringReplace($sText, "Ôûê", "")
    $sText = StringReplace($sText, "ÆÔû", "")
    $sText = StringReplace($sText, "ÔûÆ", "")
    $sText = StringReplace($sText, "ÔÇÖ", "'")
    $sText = StringReplace($sText, "├®", "é")
    $sText = StringReplace($sText, "├¿", "è")
    $sText = StringReplace($sText, "├á", "à")
    $sText = StringReplace($sText, "├¢", "â")
    $sText = StringReplace($sText, "├¬", "ì")
    $sText = StringReplace($sText, "├¹", "ù")
    $sText = StringReplace($sText, "├¨", "è")
    $sText = StringReplace($sText, "├´", "ô")
    $sText = StringReplace($sText, "├ë", "é")
    $sText = StringReplace($sText, "┬á", "")
    
    ; Supprimer les pourcentages isolés (chiffre + % + espace)
    $sText = StringRegExpReplace($sText, "\d+%\s", "")
    
    ; Supprimer les caractères - \ | / seulement s'ils sont isolés avec des espaces autour
    ; Mais préserver les retours à la ligne @CRLF
    $sText = StringReplace($sText, " - ", " ")
    $sText = StringReplace($sText, " \ ", " ")
    $sText = StringReplace($sText, " | ", " ")
    $sText = StringReplace($sText, " / ", " ")
    
    ; Supprimer les caractères isolés en début de ligne
    $sText = StringRegExpReplace($sText, "(?m)^[\-\\\|/]\s+", "")
    
    ; Nettoyer les espaces multiples mais préserver les retours à la ligne
    $sText = StringRegExpReplace($sText, "[ \t]+", " ")
    
    Return $sText
EndFunc

Func _cmdrun($a)

	$i = 0

	$_cmd_run = IniRead($ini_cmd,$a,"commande","")


	_Log("--------- Commande lancée : " & $_cmd_run & " ---------", "Commande")
	$iPid = Run('PowerShell ' & $_cmd_run, '' , @SW_HIDE , 0x2)
	$sOutput = ''


	ProgressOn("Wait", "")

		While 1

			$i +=10


			ProgressSet($i)

			if $i <= 100 then
				$i = 0
			EndIf


			$sOutput &= StdoutRead($iPID)
				If @error Then ExitLoop



		WEnd


	$sOutput = _WinAPI_MultiByteToWideChar($sOutput, 1, 0 , 1)
	; Nettoyer les caractères d'encodage problématiques
	$sOutput = _CleanEncodingChars($sOutput)
	_Log($sOutput, "Commande", "Sortie")
	_Log("--------- Commande Terminée : " & $_cmd_run & " ---------", "Commande")


	$outarray = StringSplit($sOutput,@CRLF)
	ProgressOff()

EndFunc


Func CLEANMGR()
;~  Suprimer les anciens points de restauration
;~ $commande = "vssadmin delete shadows /for=c: /oldest " ;/quiet

;~ $CmdPid = Runwait("C:\Windows\System32\WindowsPowerShell\v1.0\powershell.exe " & $commande, @ScriptDir, @SW_SHOW)


; choix des case à cocher
;~  $cleanurl= @ScriptDir & "\Scripts\CleanMGR.exe"

;~ Runwait('"'&$cleanurl&'"', @ScriptDir, @SW_SHOW)



;~  Créer un point de restauration système
;~ Wmic.exe /Namespace:\\root\default Path SystemRestore Call CreateRestorePoint "Avant nettoyer des fichiers temporaires", 100, 12

;~  Création la configuration SageRun:100 pour cleanmgr

	RegWrite("HKEY_LOCAL_MACHINE\SOFTWARE\Microsoft\Windows\CurrentVersion\Explorer\VolumeCaches\Active Setup Temp Folders", "StateFlags0100", "REG_DWORD", IniRead("cfg.ini","cleanmgr","Active_Setup_Temp_Folders",""))
	RegWrite("HKEY_LOCAL_MACHINE\SOFTWARE\Microsoft\Windows\CurrentVersion\Explorer\VolumeCaches\BranchCache", "StateFlags0100", "REG_DWORD", IniRead("cfg.ini","cleanmgr","BranchCache",""))
	RegWrite("HKEY_LOCAL_MACHINE\SOFTWARE\Microsoft\Windows\CurrentVersion\Explorer\VolumeCaches\Downloaded Program Files", "StateFlags0100", "REG_DWORD", IniRead("cfg.ini","cleanmgr","Downloaded_Program_Files",""))
	RegWrite("HKEY_LOCAL_MACHINE\SOFTWARE\Microsoft\Windows\CurrentVersion\Explorer\VolumeCaches\GameNewsFiles", "StateFlags0100", "REG_DWORD", IniRead("cfg.ini","cleanmgr","GameNewsFiles",""))
	RegWrite("HKEY_LOCAL_MACHINE\SOFTWARE\Microsoft\Windows\CurrentVersion\Explorer\VolumeCaches\GameStatisticsFiles", "StateFlags0100", "REG_DWORD", IniRead("cfg.ini","cleanmgr","GameStatisticsFiles",""))
	RegWrite("HKEY_LOCAL_MACHINE\SOFTWARE\Microsoft\Windows\CurrentVersion\Explorer\VolumeCaches\GameUpdateFiles", "StateFlags0100", "REG_DWORD", IniRead("cfg.ini","cleanmgr","GameUpdateFiles",""))
	RegWrite("HKEY_LOCAL_MACHINE\SOFTWARE\Microsoft\Windows\CurrentVersion\Explorer\VolumeCaches\Internet Cache Files", "StateFlags0100", "REG_DWORD", IniRead("cfg.ini","cleanmgr","Internet_Cache_Files",""))
	RegWrite("HKEY_LOCAL_MACHINE\SOFTWARE\Microsoft\Windows\CurrentVersion\Explorer\VolumeCaches\Memory Dump Files", "StateFlags0100", "REG_DWORD", IniRead("cfg.ini","cleanmgr","Memory_Dump_Files",""))
	RegWrite("HKEY_LOCAL_MACHINE\SOFTWARE\Microsoft\Windows\CurrentVersion\Explorer\VolumeCaches\Offline Pages Files", "StateFlags0100", "REG_DWORD", IniRead("cfg.ini","cleanmgr","Offline_Pages_Files",""))
	RegWrite("HKEY_LOCAL_MACHINE\SOFTWARE\Microsoft\Windows\CurrentVersion\Explorer\VolumeCaches\Old ChkDsk Files", "StateFlags0100", "REG_DWORD", IniRead("cfg.ini","cleanmgr","Old_ChkDsk_Files",""))
	RegWrite("HKEY_LOCAL_MACHINE\SOFTWARE\Microsoft\Windows\CurrentVersion\Explorer\VolumeCaches\Previous Installations", "StateFlags0100", "REG_DWORD", IniRead("cfg.ini","cleanmgr","Previous_Installations",""))
	RegWrite("HKEY_LOCAL_MACHINE\SOFTWARE\Microsoft\Windows\CurrentVersion\Explorer\VolumeCaches\Recycle Bin", "StateFlags0100", "REG_DWORD", IniRead("cfg.ini","cleanmgr","Recycle_Bin",""))
	RegWrite("HKEY_LOCAL_MACHINE\SOFTWARE\Microsoft\Windows\CurrentVersion\Explorer\VolumeCaches\Service Pack Cleanup", "StateFlags0100", "REG_DWORD", IniRead("cfg.ini","cleanmgr","Service_Pack_Cleanup",""))
	RegWrite("HKEY_LOCAL_MACHINE\SOFTWARE\Microsoft\Windows\CurrentVersion\Explorer\VolumeCaches\Setup Log Files", "StateFlags0100", "REG_DWORD", IniRead("cfg.ini","cleanmgr","Setup_Log_Files",""))
	RegWrite("HKEY_LOCAL_MACHINE\SOFTWARE\Microsoft\Windows\CurrentVersion\Explorer\VolumeCaches\System error memory dump files", "StateFlags0100", "REG_DWORD", IniRead("cfg.ini","cleanmgr","System_error_memory_dump_files",""))
	RegWrite("HKEY_LOCAL_MACHINE\SOFTWARE\Microsoft\Windows\CurrentVersion\Explorer\VolumeCaches\System error minidump files", "StateFlags0100", "REG_DWORD", IniRead("cfg.ini","cleanmgr","System_error_minidump_files",""))



	RegWrite("HKEY_LOCAL_MACHINE\SOFTWARE\Microsoft\Windows\CurrentVersion\Explorer\VolumeCaches\Temporary Files", "StateFlags0100", "REG_DWORD", IniRead("cfg.ini","cleanmgr","Temporary_Files",""))
	RegWrite("HKEY_LOCAL_MACHINE\SOFTWARE\Microsoft\Windows\CurrentVersion\Explorer\VolumeCaches\Temporary Setup Files", "StateFlags0100", "REG_DWORD", IniRead("cfg.ini","cleanmgr","Temporary_Setup_Files",""))
	RegWrite("HKEY_LOCAL_MACHINE\SOFTWARE\Microsoft\Windows\CurrentVersion\Explorer\VolumeCaches\Temporary Sync Files", "StateFlags0100", "REG_DWORD", IniRead("cfg.ini","cleanmgr","Temporary_Sync_Files",""))
	RegWrite("HKEY_LOCAL_MACHINE\SOFTWARE\Microsoft\Windows\CurrentVersion\Explorer\VolumeCaches\Thumbnail Cache", "StateFlags0100", "REG_DWORD", IniRead("cfg.ini","cleanmgr","Thumbnail_Cache",""))
	RegWrite("HKEY_LOCAL_MACHINE\SOFTWARE\Microsoft\Windows\CurrentVersion\Explorer\VolumeCaches\Update Cleanup", "StateFlags0100", "REG_DWORD", IniRead("cfg.ini","cleanmgr","Update_Cleanup",""))
	RegWrite("HKEY_LOCAL_MACHINE\SOFTWARE\Microsoft\Windows\CurrentVersion\Explorer\VolumeCaches\Upgrade Discarded Files", "StateFlags0100", "REG_DWORD", IniRead("cfg.ini","cleanmgr","Upgrade_Discarded_Files",""))
	RegWrite("HKEY_LOCAL_MACHINE\SOFTWARE\Microsoft\Windows\CurrentVersion\Explorer\VolumeCaches\User file versions", "StateFlags0100", "REG_DWORD", IniRead("cfg.ini","cleanmgr","User_file_versions",""))
	RegWrite("HKEY_LOCAL_MACHINE\SOFTWARE\Microsoft\Windows\CurrentVersion\Explorer\VolumeCaches\Windows Defender", "StateFlags0100", "REG_DWORD", IniRead("cfg.ini","cleanmgr","Windows_Defender",""))
	RegWrite("HKEY_LOCAL_MACHINE\SOFTWARE\Microsoft\Windows\CurrentVersion\Explorer\VolumeCaches\Windows Error Reporting Archive Files", "StateFlags0100", "REG_DWORD", IniRead("cfg.ini","cleanmgr","Windows_Error_Reporting_Archive_Files",""))
	RegWrite("HKEY_LOCAL_MACHINE\SOFTWARE\Microsoft\Windows\CurrentVersion\Explorer\VolumeCaches\Windows Error Reporting Queue Files", "StateFlags0100", "REG_DWORD", IniRead("cfg.ini","cleanmgr","Windows_Error_Reporting_Queue_Files",""))
	RegWrite("HKEY_LOCAL_MACHINE\SOFTWARE\Microsoft\Windows\CurrentVersion\Explorer\VolumeCaches\Windows Error Reporting System Archive Files", "StateFlags0100", "REG_DWORD", IniRead("cfg.ini","cleanmgr","Windows_Error_Reporting_System_Archive_Files",""))
	RegWrite("HKEY_LOCAL_MACHINE\SOFTWARE\Microsoft\Windows\CurrentVersion\Explorer\VolumeCaches\Windows Error Reporting System Queue Files", "StateFlags0100", "REG_DWORD", IniRead("cfg.ini","cleanmgr","Windows_Error_Reporting_System_Queue_Files",""))
	RegWrite("HKEY_LOCAL_MACHINE\SOFTWARE\Microsoft\Windows\CurrentVersion\Explorer\VolumeCaches\Windows ESD installation files", "StateFlags0100", "REG_DWORD", IniRead("cfg.ini","cleanmgr","Windows_ESD_installation_files",""))


	RegWrite("HKEY_LOCAL_MACHINE\SOFTWARE\Microsoft\Windows\CurrentVersion\Explorer\VolumeCaches\Windows Upgrade Log Files", "StateFlags0100", "REG_DWORD", IniRead("cfg.ini","cleanmgr","Windows_Upgrade_Log_Files",""))
	RegWrite("HKEY_LOCAL_MACHINE\SOFTWARE\Microsoft\Windows\CurrentVersion\Explorer\VolumeCaches\Temporary Files", "StateFlags0100", "REG_DWORD", IniRead("cfg.ini","cleanmgr","Temporary_Files",""))



$commande = "cleanmgr.exe START /WAIT cleanmgr /sagerun:100"
$CmdPid = Runwait("C:\Windows\System32\WindowsPowerShell\v1.0\powershell.exe " & $commande, @ScriptDir, @SW_HIDE)

EndFunc
