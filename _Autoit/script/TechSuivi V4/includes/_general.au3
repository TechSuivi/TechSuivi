#cs ----------------------------------------------------------------------------

 AutoIt Version: 3.3.16.1
 Author:         myName

 Script Function:
	Template AutoIt script.

#ce ----------------------------------------------------------------------------

; Script Start - Add your code below here
global $sConnectionString
Global $Form3_inter
Global $Form2_listeclient
Global $Form1
Global $edit_crea_inter
local $Checkbox_[50]
local $Input_[50]
Local $Label_[50]
local $save = ""

Global $edit_cmd

Func Terminate()
;Ajouter les informations à sauvegarder si on quitte
    ; Sauvegarder une dernière fois avant de quitter via l'API
    _SaveEditCmdToAPI()
    _SaveCleaningInfoToAPI()
    _SaveUserNotesToAPI()
Exit
EndFunc



Func Terminate_fille2()
	GUIDelete($Form2_listeclient)

EndFunc

Func Terminate_fille3()
	GUIDelete($Form3_inter)

EndFunc






Func _sStrFrAccentRemove($sString)
	;&========================================================================================================================
	;& Description....: Convert characters with accent to a fom without accent based on french language
	;& Parameter(s)...:
	;&					$sString to convert
	;& Return value(s)
	;&		Function..: String converted
	;&		Error(s)..:	-
	;& -
	;& Versions.......: 1.0.0
	;& -
	;& Author(s)......: Jean-Pol Dekimpe (Jeep)
	;& Date...........: 2019/01/01
	;& Language.......: English
	;& AutoIt.........: v3.3.14.5
	;& -
	;& Remark(s)......: Conversion is based on french language
	;&========================================================================================================================
	Local $sFrom = "àáâãäåæçèéêëìíîïòóôöùúûüýÿœ", $sChar = "", $sOutString = ""
	Local $aTo = ["a", "a", "a", "a", "a", "a", "ae", "c", "e", "e", "e", "e", "i", "i", "i", "i", "o", "o", "o", "o", _
			"u", "u", "u", "u", "y", "y", "oe"]
	Local $aChars = StringSplit($sString, "")
	Local $iPos = 0

	For $i = 1 To $aChars[0]
		$sChar = $aChars[$i]
		$iPos = StringInStr($sFrom, $sChar)
		If $iPos = 0 Then
			$sOutString &= $sChar
		Else
			If StringIsUpper($sChar) Then
				$sOutString &= StringUpper($aTo[$iPos])
			Else
				$sOutString &= $aTo[$iPos]
			EndIf
		EndIf
	Next
	Return $sOutString
EndFunc   ;==>_sStrFrAccentRemove

Func _sStrTitleCase($sString, $sLanguage = "FR")
	;&========================================================================================================================
	;& Description....: Uppercase first letter of each word + support a French context
	;&					No uppercase after "'"
	;& Parameter(s)...:
	;&					$sString - string of characters to convert
	;&					$sLanguage = "FR" like French to process accent characters; They are removed before converting to
	;&                               uppercase
	;& Return value(s)
	;&		Function..: resulting string
	;&		Error(s)..: -
	;& -
	;& Versions.......: 1.0.0
	;& -
	;& Author(s)......: Jean-Pol Dekimpe (Jeep)
	;& Date...........: 2019/01/01
	;& Language.......: English
	;& AutoIt.........: v3.3.14.5
	;& -
	;& Remarks........: -
	;&========================================================================================================================
	Local $bCapNext = True
	Local $sChr = "", $sReturn = ""
	Local $aChars = StringSplit($sString, "")

	For $i = 1 To $aChars[0]
		$sChr = $aChars[$i]
		Select
			Case $bCapNext = True
				If StringRegExp($sChr, "[a-zA-Z\xC0-\xFF0-9]") Then
					If $sLanguage = "FR" Then $sChr = (_sStrFrAccentRemove($sChr))
					$sChr = StringUpper($sChr)
					$bCapNext = False
				EndIf
			Case ($sLanguage <> "FR" And Not StringRegExp($sChr, "[a-zA-Z\xC0-\xFF'0-9]")) Or _
					(Not StringRegExp($sChr, "(*UCP)[a-zA-Z\xC0-\xFF0-9'’]"))
				$bCapNext = True
			Case Else
				$sChr = StringLower($sChr)
		EndSelect
		$sReturn &= $sChr
	Next
	Return $sReturn
EndFunc   ;==>_sStrTitleCase







Func path($input) ;ajoute les quote

  If StringLeft($input, 1) <> "'" Then $input = '"' & $input
  If StringRight($input, 1) <> '"' Then $input = $input & '"'
  return $input
EndFunc


; ============================================================================
; Fonction _Log - Homogénéise l'affichage des logs dans $edit_cmd
; ============================================================================
; Description:
;   Cette fonction permet d'écrire de manière standardisée dans le log
;   Elle formatle automatiquement les messages avec un préfixe optionnel
;   et ajoute un timestamp au début
;
; Paramètres:
;   $sMessage   - Le message à afficher dans le log
;   $sPage      - (Optionnel) Le nom de la page/section d'où vient le message
;                 Ex: "Materiel", "Nettoyage", "Config. Sys", etc.
;   $sCategorie - (Optionnel) Une sous-catégorie pour plus de précision
;                 Ex: "Disque", "Mémoire", "Réseau", etc.
;
; Exemple d'utilisation:
;   _Log("Lancement du diagnostic de mémoire Windows (mdsched.exe)", "Config. Sys")
;   _Log("2 disque(s) détecté(s) via smartctl", "Config. Sys", "Disque")
;   _Log("Nettoyage terminé avec succès")
;
; Format de sortie:
;   [2025-12-09 16:16:42] Message
;   [2025-12-09 16:16:42] [Page] Message
;   [2025-12-09 16:16:42] [Page] [Catégorie] Message
;
; Note:
;   Les messages sont automatiquement préfixés par @CRLF et le paramètre
;   append est défini à 1 pour ajouter au contenu existant
; ============================================================================
Func _Log($sMessage, $sPage = "", $sCategorie = "")
    Local $sFormattedMessage = ""
    
    ; Générer le timestamp au format [YYYY-MM-DD HH:MM:SS]
    Local $sTimestamp = "[" & @YEAR & "-" & @MON & "-" & @MDAY & " " & @HOUR & ":" & @MIN & ":" & @SEC & "]"
    
    ; Construire le préfixe en fonction des paramètres fournis
    If $sPage <> "" And $sCategorie <> "" Then
        ; Format : [Timestamp] [Page] [Catégorie] Message
        $sFormattedMessage = $sTimestamp & " [" & $sPage & "] [" & $sCategorie & "] " & $sMessage
    ElseIf $sPage <> "" Then
        ; Format : [Timestamp] [Page] Message
        $sFormattedMessage = $sTimestamp & " [" & $sPage & "] " & $sMessage
    Else
        ; Format : [Timestamp] Message (sans préfixe de page)
        $sFormattedMessage = $sTimestamp & " " & $sMessage
    EndIf
    
    ; Ajouter le message formaté au contrôle edit_cmd
    GUICtrlSetData($edit_cmd, @CRLF & $sFormattedMessage, 1)
EndFunc   ;==> _Log



