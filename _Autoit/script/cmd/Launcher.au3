#include <GUIConstantsEx.au3>
#include <WindowsConstants.au3>
#include <EditConstants.au3>
#include <FileConstants.au3>
#include <GuiEdit.au3>

; Configuration
Global $sBridgeExe = @ScriptDir & "\ProcessBridge.exe"
Global $sCompileBat = @ScriptDir & "\CompileBridge.bat"
Global $sLogFile = @ScriptDir & "\latest.log"
Global $sInputFile = @ScriptDir & "\input.txt"
Global $iLastLogSize = 0
Global $iPidBridge = 0

; Ensure Bridge Exists
If Not FileExists($sBridgeExe) Then
    MsgBox(64, "Compilation", "Le pont C# n'existe pas. Tentative de compilation...")
    RunWait($sCompileBat, @ScriptDir, @SW_HIDE)
    If Not FileExists($sBridgeExe) Then
        MsgBox(16, "Erreur", "Impossible de compiler ProcessBridge.exe.")
        Exit
    EndIf
EndIf

; GUI
Global $hGui = GUICreate("AutoIt CMD Wrapper (via C# Bridge)", 800, 600)
Global $idCmdInput = GUICtrlCreateInput("diskpart", 10, 10, 600, 25)
Global $idBtnRun = GUICtrlCreateButton("Démarrer", 620, 10, 80, 25)
Global $idBtnStop = GUICtrlCreateButton("Stop", 710, 10, 80, 25)

Global $idOutput = GUICtrlCreateEdit("", 10, 50, 780, 500, BitOR($ES_READONLY, $ES_MULTILINE, $WS_VSCROLL, $ES_AUTOVSCROLL))
GUICtrlSetFont(-1, 9, 400, 0, "Consolas")
GUICtrlSetBkColor(-1, 0x1E1E1E)
GUICtrlSetColor(-1, 0xCCCCCC)
_GUICtrlEdit_SetLimitText($idOutput, -1) ; Unlimited text

Global $idConsoleInput = GUICtrlCreateInput("", 10, 560, 690, 30)
GUICtrlSetFont(-1, 9, 400, 0, "Consolas")
GUICtrlSetState(-1, $GUI_DISABLE)

Global $idBtnSend = GUICtrlCreateButton("Envoyer", 710, 560, 80, 30)
GUICtrlSetState(-1, $GUI_DISABLE)

GUISetState(@SW_SHOW)

; Main Loop
While 1
    Switch GUIGetMsg()
        Case $GUI_EVENT_CLOSE
            StopProcess()
            Exit
        Case $idBtnRun
            StartProcess()
        Case $idBtnStop
            StopProcess()
        Case $idBtnSend
            SendInput()
    EndSwitch

    ; Check for Enter key in Console Input
    If _IsPressed("0D") And GUICtrlGetState($idConsoleInput) <> $GUI_DISABLE Then ; Enter Key
        If ControlGetFocus($hGui) = "Edit2" Then 
             SendInput()
             Sleep(200) ; Debounce
        EndIf
    EndIf
    
    UpdateLog()
WEnd

Func StartProcess()
    Local $sCmd = GUICtrlRead($idCmdInput)
    If $sCmd = "" Then Return

    ; Clean files
    FileDelete($sLogFile)
    FileDelete($sInputFile)
    FileWrite($sInputFile, "") ; Create empty
    
    $iLastLogSize = 0
    GUICtrlSetData($idOutput, "")
    
    ; Run Bridge
    ; ProcessBridge.exe <Command> <LogFile> <InputFile>
    $iPidBridge = Run('"' & $sBridgeExe & '" "' & $sCmd & '" "' & $sLogFile & '" "' & $sInputFile & '"', @ScriptDir, @SW_HIDE)
    
    GUICtrlSetState($idBtnRun, $GUI_DISABLE)
    GUICtrlSetState($idConsoleInput, $GUI_ENABLE)
    GUICtrlSetState($idBtnSend, $GUI_ENABLE)
    GUICtrlSetState($idConsoleInput, $GUI_FOCUS)
EndFunc

Func StopProcess()
    If $iPidBridge Then
        ProcessClose($iPidBridge)
        $iPidBridge = 0
    EndIf
    GUICtrlSetState($idBtnRun, $GUI_ENABLE)
    GUICtrlSetState($idConsoleInput, $GUI_DISABLE)
    GUICtrlSetState($idBtnSend, $GUI_DISABLE)
EndFunc

Func SendInput()
    Local $sMsg = GUICtrlRead($idConsoleInput)
    If $sMsg <> "" Then
        FileWriteLine($sInputFile, $sMsg)
        GUICtrlSetData($idConsoleInput, "")
    EndIf
EndFunc

Func UpdateLog()
    If Not FileExists($sLogFile) Then Return
    Local $iSize = FileGetSize($sLogFile)
    
    If $iSize > $iLastLogSize Then
        Local $hFile = FileOpen($sLogFile, $FO_READ + 256) ; 256 = UTF8 NO BOM
        FileSetPos($hFile, $iLastLogSize, $FILE_BEGIN)
        Local $sNewData = FileRead($hFile)
        FileClose($hFile)
        
        $iLastLogSize = $iSize
        
        GUICtrlSetData($idOutput, $sNewData, 1) ; Append
    EndIf
EndFunc

; Helper for IsPressed
Func _IsPressed($sHexKey, $vDLL = 'user32.dll')
	Local $a_R = DllCall($vDLL, "short", "GetAsyncKeyState", "int", '0x' & $sHexKey)
	If @error Then Return SetError(@error, @extended, False)
	Return BitAND($a_R[0], 0x8000) <> 0
EndFunc
