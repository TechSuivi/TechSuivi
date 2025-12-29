#include <Date.au3>
#include <StringConstants.au3>

$ini_sys_dir = @ScriptDir & "\ini\sys.ini"
$ini_cfg_dir = @ScriptDir & "\ini\cfg.ini"
IniWrite($ini_sys_dir, "NB", "Total", "0")
local $releaseid = ""


Func _first_run_cfg()



_sys_OS()

if IniRead($ini_cfg_dir,"config","firstinit","") = 0 Then

_sys_CG()
_sys_startup()
_sys_IMP()
_sys_RAM()
_sys_logon()
_sys_screensaver()
_sys_OS()
_sys_BIOS()
_sys_CS()
_sys_CPU()
_sys_CM()
_sys_DD()


IniWrite($ini_cfg_dir,"config","firstinit","1")

If @Compiled Then
	ShellExecute(@scriptdir&"\"&@ScriptName)
Else
	ShellExecute(@scriptdir&"\"&@ScriptName,"", @AutoItExe)
EndIf
Exit


Elseif IniRead($ini_cfg_dir,"config","firstinit","") = 1 Then

	_sys_OS()


	; Vérifier si une intervention a été sélectionnée
	Local $intervention_file = @ScriptDir & "\intervention_selectionnee.ini"
	If Not FileExists($intervention_file) Then
		; Lancer la sélection d'intervention si aucune n'est sélectionnée
		_select_intervention_startup()
	EndIf

EndIf




EndFunc
