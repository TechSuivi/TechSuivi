#RequireAdmin
#Region ;**** Directives created by AutoIt3Wrapper_GUI ****
#AutoIt3Wrapper_Icon=antibacterien.ico
#EndRegion ;**** Directives created by AutoIt3Wrapper_GUI ****
#cs ----------------------------------------------------------------------------

 AutoIt Version: 3.3.16.1
 Author:         myName

 Script Function:
	Template AutoIt script.

#ce ----------------------------------------------------------------------------

; Script Start - Add your code below here

Opt("GUIOnEventMode", True)

;~ #Tidy_Parameters=/sort_funcs /reel
;~ #AutoIt3Wrapper_Run_AU3Check=Y
;~ #AutoIt3Wrapper_Au3Check_Parameters=-d -w 1 -w 2 -w 3 -w 4 -w 5 -w 6 -w 7

;~ #AutoIt3Wrapper_Run_Au3Stripper=Y
;~ #Au3Stripper_Parameters=/RM






#include <Array.au3>
#include <AutoItConstants.au3>
#include <Date.au3>
#include <File.au3>
#include <GUIConstants.au3>
#include <GUIConstantsEx.au3>
#include <GuiListView.au3>
#include <InetConstants.au3>
#include <MsgBoxConstants.au3>
#include <ProgressConstants.au3>
#include <String.au3>
#include <StringConstants.au3>
#include <WinAPIConv.au3>
#include <WinAPIFiles.au3>
#include <WinAPIShellEx.au3>
#include <WindowsConstants.au3>
#include <GuiEdit.au3>
#include <Misc.au3>
#include <TrayConstants.au3>







;~ includes externe

#include <includes/_general.au3>
#include <includes/_sys.au3>
#include <includes/Zip.au3>
#include <includes/api.au3>



;~ Pages a inserer
#include <pages/_first_start.au3>
#include <pages/_select_inter.au3>
#include <pages/_clients.au3>
#include <pages/_config_sys.au3>
#include <pages/_nettoyage.au3>
#include <pages/_configuration.au3>
#include <pages/_sauve.au3>
#include <pages/_restaure.au3>
#include <pages/_commande.au3>
#include <pages/_logiciels.au3>
#include <pages/_logdem_final.au3>
#include <pages/_maj.au3>

;~ #include <pages/_iso.au3>



#pragma compile(inputboxres, true)


#Region ### START Koda GUI section ### Form=


;~ Variable

$log_name = "TechSuivi V5.5"


;~ Global $cp_in_use
Local $hTimer = TimerInit()
;~ Local $hTimer2 = TimerInit()
Global $edit_cmd = ""


;__________________________________

;____________________ Start
;__________________________________




_first_run_cfg()

; Sélection d'intervention au démarrage (seulement si aucune intervention n'est déjà sélectionnée)
Local $intervention_file = @ScriptDir & "\intervention_selectionnee.ini"
Local $id_inter_cfg = IniRead(@ScriptDir & "\ini\cfg.ini", "config", "id_inter", "")

If Not FileExists($intervention_file) Or $id_inter_cfg = "" Then
    ; Aucune intervention sélectionnée, afficher la fenêtre de sélection
    _select_intervention_startup()
Else
    ; Une intervention est déjà sélectionnée, continuer normalement
    ConsoleWrite("Intervention déjà sélectionnée: " & $id_inter_cfg & @CRLF)
EndIf


;__________________________________

;____________________ Affichage
;__________________________________


;~ _start() ;fenetre de démarrage


$screen_res_L = @DesktopWidth ;largeur
$screen_res_H = @DesktopWidth ;hauteur
;~ $screen_res_H = 902

Local $formHeight = 800
Local $editHeight = 240

if $screen_res_H < 901 Then
    $formHeight = 600
    $editHeight = 40
EndIf

Global $Form1 = GUICreate($log_name, 800, $formHeight, 192, 50)
GUISetOnEvent($GUI_EVENT_CLOSE, Terminate,$Form1)


$edit_cmd =  GUICtrlCreateEdit("", 10, 550 , 780, $editHeight, $ES_AUTOVSCROLL + $WS_VSCROLL + $ES_READONLY+ $ES_OEMCONVERT)



;__________________________________

;____________________ Lecture INI
;__________________________________

$id_client = IniRead( @ScriptDir & "\ini\cfg.ini", "config", "id_client", "" )


;__________________________________

;____________________ MENU
;__________________________________


;~         GUISetFont(9, 300)

        Local $idTab = GUICtrlCreateTab(10, 10, 780, 530)



;-----------------Client
        GUICtrlCreateTabItem("Client / Intervention")
		GUICtrlSetState($idTab, $GUI_SHOW); will be display first
		_Clients()
;~ 		_check_FW()

;-----------------Nettoyage
        GUICtrlCreateTabItem("Nettoyage")
		_nettoyage()


;-----------------Mise à jour
        GUICtrlCreateTabItem("Mise à jour")
		_maj()

;-----------------Logiciels Au démarrage
        GUICtrlCreateTabItem("Demarrage")
 		_logdem()



;-----------------Logiciels
        GUICtrlCreateTabItem("Logiciels")
 		_logiciels()




;-----------------Configuration
        GUICtrlCreateTabItem("Configuration")
 		_registre()
 		_icones()
 		_backupSML()





;-----------------Sauvegarde
         GUICtrlCreateTabItem("Sauvegarde")
         _sauve()

;-----------------Restauration
         GUICtrlCreateTabItem("Restauration")
         _restaure()



;-----------------Commande
        GUICtrlCreateTabItem("Commande")
 		_cmd()


;-----------------Dl ISO
        GUICtrlCreateTabItem("DL Iso")
;~ 		_ISO()



;-----------------Configuration Système
        GUICtrlCreateTabItem("Config. Sys")
		_configsys()


		GUICtrlCreateTabItem(""); end tabitem definition
		GUISetState()

; Restaurer les données depuis l'API APRÈS la création de l'interface
Local $id_inter = IniRead(@ScriptDir & "\ini\cfg.ini", "config", "id_inter", "")
If $id_inter <> "" Then
    _RestoreEditCmdFromAPI()
    _RestoreNettoyageStateFromAPI()
EndIf




;-----------------
;~ _meshcentral() ;verification installation meshcentral



;__________________________________

;____________________ Boucle
;__________________________________




While 1
Sleep(1) ; Pause pendant 1Ms.


	;__________________________________
	;----------Lancement sauvegarde 1min
	;__________________________________

	if TimerDiff($hTimer) >= 60000 Then
		_SaveCleaningInfoToAPI() ;nettoyage.au3
		_SaveEditCmdToAPI() ;_clients.au3
		_SaveUserNotesToAPI() ;_clients.au3
		$hTimer = TimerInit()
	EndIf


	;__________________________________
	;----------Vérification Installation Meshcentral 1s
	;__________________________________


;~ 	if TimerDiff($hTimer2) >= 6000 Then
;~ 		_meshcentral() ;general.au3
;~ 		Local $hTimer2 = TimerInit()
;~ 	EndIf








WEnd


;__________________________________

;____________________ Fonctions
;__________________________________

; Fonction pour restaurer le contenu du log depuis l'API
Func _RestoreEditCmdFromAPI()
    Local $id_inter = IniRead(@ScriptDir & "\ini\cfg.ini", "config", "id_inter", "")
    
    If $id_inter = "" Then
        Return ; Pas d'intervention configurée
    EndIf
    
    ; Restaurer le log principal depuis info_log
    Local $logContent = API_LireChamp($id_inter, "info_log")
    
    If @error Then
        ; En cas d'erreur, essayer de lire depuis le champ nettoyage
        $logContent = API_LireChamp($id_inter, "nettoyage")
        If @error Then
            $logContent = "" ; Pas de log à restaurer
        EndIf
    EndIf
    
    ; Restaurer le contenu dans l'éditeur principal avec correction des retours à la ligne
    If $logContent <> "" Then
        ; Convertir les \r\n en vrais retours à la ligne
        $logContent = StringReplace($logContent, "\r\n", @CRLF)
        $logContent = StringReplace($logContent, "\n", @LF)
        $logContent = StringReplace($logContent, "\r", @CR)
        
        ; Remplacer complètement le contenu de l'éditeur
        GUICtrlSetData($edit_cmd, $logContent)
    EndIf
    
    ; Restaurer séparément les notes utilisateur dans le champ dédié
    _RestoreUserNotesFromAPI()
EndFunc

; Fonction pour restaurer les notes utilisateur depuis l'API
Func _RestoreUserNotesFromAPI()
    Local $id_inter = IniRead(@ScriptDir & "\ini\cfg.ini", "config", "id_inter", "")
    
    If $id_inter = "" Then
        Return ; Pas d'intervention configurée
    EndIf
    
    ; Lire les notes utilisateur depuis l'API
    Local $noteContent = API_LireChamp($id_inter, "note_user")
    
    If Not @error And $noteContent <> "" And IsDeclared("edit_note_info") And $edit_note_info <> "" Then
        ; Convertir les \r\n en vrais retours à la ligne
        $noteContent = StringReplace($noteContent, "\r\n", @CRLF)
        $noteContent = StringReplace($noteContent, "\n", @LF)
        $noteContent = StringReplace($noteContent, "\r", @CR)
        GUICtrlSetData($edit_note_info, $noteContent)
    EndIf
EndFunc

; Fonction pour sauvegarder le contenu du log via l'API
Func _SaveEditCmdToAPI()
    Local $id_inter = IniRead(@ScriptDir & "\ini\cfg.ini", "config", "id_inter", "")
    
    If $id_inter = "" Then
        Return ; Pas d'intervention configurée
    EndIf
    
    ; Récupérer le contenu actuel du log
    Local $logContent = GUICtrlRead($edit_cmd)
    
    If $logContent <> "" Then
        ; Nettoyer le contenu avant sauvegarde (les retours à la ligne sont déjà gérés par _URLEncode)
        ; Pas besoin de conversion supplémentaire car _URLEncode dans api.au3 gère déjà @CRLF, @LF, @CR
        API_SauvegarderChamp($id_inter, "info_log", $logContent)
    EndIf
EndFunc

; Fonction pour sauvegarder les informations de nettoyage via l'API
Func _SaveCleaningInfoToAPI()
    ; Appeler la fonction spécialisée dans _nettoyage.au3
    _SaveNettoyageStateToAPI()
EndFunc

; Fonction pour sauvegarder les notes utilisateur via l'API
Func _SaveUserNotesToAPI()
    Local $id_inter = IniRead(@ScriptDir & "\ini\cfg.ini", "config", "id_inter", "")
    
    If $id_inter = "" Then
        Return ; Pas d'intervention configurée
    EndIf
    
    ; Sauvegarder les notes utilisateur depuis le champ dédié (edit_note_info)
    ; et non pas depuis le log principal
    If IsDeclared("edit_note_info") And $edit_note_info <> "" Then
        Local $noteContent = GUICtrlRead($edit_note_info)
        If $noteContent <> "" Then
            API_LogNote($id_inter, $noteContent)
        EndIf
    EndIf
EndFunc

