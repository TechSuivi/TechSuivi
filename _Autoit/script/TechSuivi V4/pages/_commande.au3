#cs ----------------------------------------------------------------------------
 AutoIt Version: 3.3.16.1
 Author:         Fabien

 Script Function:
	Commandes interactives via ProcessBridge
#ce ----------------------------------------------------------------------------

#include <GuiEdit.au3>
#include <FileConstants.au3>
#include <Misc.au3>

#include <GuiEdit.au3>
#include <FileConstants.au3>
#include <Misc.au3>
#include <GuiListView.au3>

; Globals for this page
Global $sBridgeExe = @ScriptDir & "\tools\ProcessBridge.exe"
Global $sLogFile = @ScriptDir & "\tools\latest.log"
Global $sInputFile = @ScriptDir & "\tools\input.txt"

Global $idListCmd, $idOutput, $idConsoleInput, $idBtnRun, $idBtnStop, $idBtnShell, $idBtnSend
Global $iPidBridge = 0
Global $iLastLogSize = 0
Global $ini_cmd = "ini\commandes.ini"
Global $aCmdQueue[0] ; Command Queue
Global $iCurrentIndex = -1 ; Track running item

; Function to build the UI
Func _cmd()
	; Layout constants
	Local $iMargin = 20
	Local $iTop = 60 ; Increased to clear main menu
	Local $iWidth = 760
	
	; 1. Top Section: Command List & Sidebar
	Local $iHeightList = 140
	Local $iWidthList = 450 ; Reduced from 550
	Local $iLeftSidebar = $iMargin + $iWidthList + 20
	
	GUICtrlCreateLabel("Sélectionnez les commandes à exécuter :", $iMargin, $iTop - 20, $iWidthList, 20)
	
	; ListView with Checkboxes
	$idListCmd = GUICtrlCreateListView("Commande|CmdLine", $iMargin, $iTop, $iWidthList, $iHeightList, BitOR($LVS_REPORT, $LVS_SHOWSELALWAYS), BitOR($LVS_EX_CHECKBOXES, $LVS_EX_FULLROWSELECT))
	_GUICtrlListView_SetColumnWidth($idListCmd, 0, 150) ; Name
	_GUICtrlListView_SetColumnWidth($idListCmd, 1, 280) ; Command Line
	
	; Populate List
	Local $nb_cmd = IniRead($ini_cmd, "cfg", "nb", 0)
	For $a = 1 To $nb_cmd
		Local $name = IniRead($ini_cmd, $a, "nom", "")
		Local $cmd = IniRead($ini_cmd, $a, "commande", "")
		If $name <> "" Then GUICtrlCreateListViewItem($name & "|" & $cmd, $idListCmd)
	Next

	; Sidebar Buttons
	$idBtnRun = GUICtrlCreateButton("Exécuter", $iLeftSidebar, $iTop, 100, 30)
	GUICtrlSetOnEvent($idBtnRun, "_StartSelectedCmd")
	
	$idBtnShell = GUICtrlCreateButton("Terminal (CMD)", $iLeftSidebar, $iTop + 40, 100, 30)
	GUICtrlSetOnEvent($idBtnShell, "_StartShell")
	
	$idBtnStop = GUICtrlCreateButton("Stop", $iLeftSidebar, $iTop + 110, 100, 30)
	GUICtrlSetOnEvent($idBtnStop, "_StopCmd")
	GUICtrlSetState($idBtnStop, $GUI_DISABLE)


	; 3. Bottom Section: Console
	Local $iTopConsole = $iTop + $iHeightList + 30
	Local $iHeightConsole = 260 
	
	GUICtrlCreateLabel("Sortie Console :", $iMargin, $iTopConsole - 20, $iWidth, 20)
	
	; Console Output
	$idOutput = GUICtrlCreateEdit("", $iMargin, $iTopConsole, $iWidth, $iHeightConsole, BitOR($ES_READONLY, $ES_MULTILINE, $WS_VSCROLL, $ES_AUTOVSCROLL))
	GUICtrlSetFont(-1, 9, 400, 0, "Consolas")
	GUICtrlSetBkColor(-1, 0x1E1E1E)
	GUICtrlSetColor(-1, 0xCCCCCC)
	_GUICtrlEdit_SetLimitText($idOutput, -1) 

	; Console Input
	Local $iTopInput = $iTopConsole + $iHeightConsole + 10
	
	$idConsoleInput = GUICtrlCreateInput("", $iMargin, $iTopInput, $iWidth - 110, 30)
	GUICtrlSetFont(-1, 9, 400, 0, "Consolas")
	GUICtrlSetState(-1, $GUI_DISABLE)
	
	$idBtnSend = GUICtrlCreateButton("Envoyer", $iMargin + $iWidth - 100, $iTopInput, 100, 30)
	GUICtrlSetOnEvent($idBtnSend, "_SendCmdInput")
	GUICtrlSetState($idBtnSend, $GUI_DISABLE)
	
	; Register Input Enter Key
	AdlibRegister("_CheckEnterKey", 100)
    ; Background Queue Processor
    AdlibRegister("_ProcessQueue", 500)
EndFunc

; --- Event Handlers ---

Func _StartSelectedCmd()
    ; Build Queue from Checked Items
    ReDim $aCmdQueue[0] ; Clear
    
    Local $iCount = _GUICtrlListView_GetItemCount($idListCmd)
    For $i = 0 To $iCount - 1
        If _GUICtrlListView_GetItemChecked($idListCmd, $i) Then
            ; Get hidden command column (Index 1)
            Local $aItem = _GUICtrlListView_GetItemTextArray($idListCmd, $i)
            If IsArray($aItem) And UBound($aItem) > 2 Then
               ; $aItem[1] is Name, $aItem[2] is Cmd
               _QueueAdd($i, $aItem[1], $aItem[2]) 
            EndIf
             ; Uncheck immediately to show it's "Submitted/Pending"
             _GUICtrlListView_SetItemChecked($idListCmd, $i, False)
        EndIf
    Next
    
    If UBound($aCmdQueue) = 0 Then
        MsgBox(64, "Info", "Veuillez cocher au moins une commande.")
        Return
    EndIf
    
    _ProcessQueue() ; Trigger immediately
EndFunc

Func _StartShell()
     ReDim $aCmdQueue[0]
     _QueueAdd(-1, "Terminal", "cmd.exe") ; -1 index for Shell
     _ProcessQueue()
EndFunc

Func _StopCmd()
	_StopProcess()
    ReDim $aCmdQueue[0] ; Kill queue
    $iCurrentIndex = -1
    GUICtrlSetData($idOutput, ">> Queue vidée." & @CRLF, 1)
    _Log("Arrêt de la file d'attente", "Commande", "Stop")
EndFunc

Func _SendCmdInput()
	_SendInput()
EndFunc

; --- Queue & Logic Helpers ---

Func _QueueAdd($idx, $name, $cmd)
    Local $i = UBound($aCmdQueue)
    ReDim $aCmdQueue[$i + 1]
    ; Use a safer separator *|*
    $aCmdQueue[$i] = $idx & "*|*" & $name & "*|*" & $cmd
EndFunc

Func _ProcessQueue()
    ; If process running, do nothing (wait)
    If $iPidBridge And ProcessExists($iPidBridge) Then Return
    
    ; If queue empty, return
    If UBound($aCmdQueue) = 0 Then Return
    
    ; Pop first item
    Local $sRaw = $aCmdQueue[0]
    Local $aSplit = StringSplit($sRaw, "*|*", 1) ; Flag 1 for whole string match
    
    ; $aSplit[1] = Index
    ; $aSplit[2] = Name
    ; $aSplit[3] = Cmd
    
    Local $iNextIdx = -1
    Local $sNextName = "Commande"
    Local $sNextCmd = ""

    If $aSplit[0] >= 3 Then
        $iNextIdx = Number($aSplit[1])
        $sNextName = $aSplit[2]
        $sNextCmd = $aSplit[3]
    EndIf
    
    ; Shift Queue
    Local $iSize = UBound($aCmdQueue)
    For $i = 0 To $iSize - 2
        $aCmdQueue[$i] = $aCmdQueue[$i+1]
    Next
    ReDim $aCmdQueue[$iSize - 1]
    
    $iCurrentIndex = $iNextIdx
    _StartProcess($sNextCmd, $sNextName)
EndFunc

Func _StartProcess($sCmd, $sName = "")
	If $iPidBridge Then _StopProcess()

    Local $sDisplay = ($sName <> "" ? $sName : $sCmd)
    _Log("Lancement de : " & $sDisplay, "Commande", "Execution")

	; Reset Files
	FileDelete($sLogFile)
	FileDelete($sInputFile)
	FileWrite($sInputFile, "")
	
	$iLastLogSize = 0
	GUICtrlSetData($idOutput, "---------------------------------------------------" & @CRLF & ">>> Démarrage : " & $sDisplay & @CRLF & "---------------------------------------------------" & @CRLF, 1)
	
	; Ensure tool exists
	If Not FileExists($sBridgeExe) Then
		GUICtrlSetData($idOutput, "Erreur : ProcessBridge.exe introuvable dans tools/." & @CRLF, 1)
		Return
	EndIf

	; Run Bridge
	$iPidBridge = Run('"' & $sBridgeExe & '" "' & $sCmd & '" "' & $sLogFile & '" "' & $sInputFile & '"', @ScriptDir & "\tools", @SW_HIDE)
	
	; Update UI
	GUICtrlSetState($idBtnRun, $GUI_DISABLE)
    GUICtrlSetState($idBtnShell, $GUI_DISABLE)
	GUICtrlSetState($idBtnStop, $GUI_ENABLE)
	GUICtrlSetState($idConsoleInput, $GUI_ENABLE)
	GUICtrlSetState($idBtnSend, $GUI_ENABLE)
	GUICtrlSetState($idConsoleInput, $GUI_FOCUS)
	
	; Start Log Monitor
	AdlibRegister("_UpdateLogCtx", 200)
EndFunc

Func _StopProcess()
	If $iPidBridge Then
		ProcessClose($iPidBridge)
		$iPidBridge = 0
	EndIf
	
	AdlibUnRegister("_UpdateLogCtx")
	
	; Uncheck item if applicable
	If $iCurrentIndex > -1 Then
	    _GUICtrlListView_SetItemChecked($idListCmd, $iCurrentIndex, False)
	    $iCurrentIndex = -1
	EndIf
	
	GUICtrlSetState($idBtnRun, $GUI_ENABLE)
    GUICtrlSetState($idBtnShell, $GUI_ENABLE)
	GUICtrlSetState($idBtnStop, $GUI_DISABLE)
	GUICtrlSetState($idConsoleInput, $GUI_DISABLE)
	GUICtrlSetState($idBtnSend, $GUI_DISABLE)
	; GUICtrlSetData($idOutput, @CRLF & "--- Fin du processus ---" & @CRLF, 1)
EndFunc

Func _SendInput()
	Local $sMsg = GUICtrlRead($idConsoleInput)
	If $sMsg <> "" Then
		FileWriteLine($sInputFile, $sMsg)
		GUICtrlSetData($idConsoleInput, "")
	EndIf
EndFunc

Func _UpdateLogCtx()
	If Not FileExists($sLogFile) Then Return
	Local $iSize = FileGetSize($sLogFile)
	
	If $iSize > $iLastLogSize Then
		; Read UTF-8
		Local $hFile = FileOpen($sLogFile, 256 + $FO_READ) 
		FileSetPos($hFile, $iLastLogSize, $FILE_BEGIN)
		Local $sNewData = FileRead($hFile)
		FileClose($hFile)
		
		$iLastLogSize = $iSize
		GUICtrlSetData($idOutput, $sNewData, 1)
	EndIf
	
	If $iPidBridge And Not ProcessExists($iPidBridge) Then
		_StopProcess()
	EndIf
EndFunc

Func _CheckEnterKey()
	; Simple polling for Enter key when Input has focus
	If BitAND(WinGetState(GUICtrlGetHandle($idConsoleInput)), 2) And _IsPressed("0D") Then
		_SendInput()
		Sleep(200)
	EndIf
EndFunc


