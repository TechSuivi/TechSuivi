#cs ----------------------------------------------------------------------------

 AutoIt Version: 3.3.16.1
 Author:         myName

 Script Function:
	Template AutoIt script.

#ce ----------------------------------------------------------------------------

; Script Start - Add your code below here

;~ #include <AutoItConstants.au3>
;~ #include <WinAPIConv.au3>


;~ global $Button_adw = -1

;~ Eval("Checkbox_"&$log)
Global $ini_net = "ini\nettoyage.ini"
Global $ini_cfg = "ini\cfg.ini"
Global $Checkbox_[50]
Global $Button_[50]
Global $Input_[50]
Global $Label_[50]

Local $progress
Local $label


func _nettoyage()

		$nb_net  = IniRead($ini_net,"cfg","nb","")
		$coord1 = 20
		$coord2 = 40

		for $a = 1 To $nb_net  Step 1
			$name_net = IniRead($ini_net,$a,"name","")
			$id_net = $a


			$Checkbox_[$a] = GUICtrlCreateCheckbox($name_net, $coord1, $coord2, 110, 25)
			$Label_[$a] = GUICtrlCreateLabel($id_net,$coord1+20, $coord2, 110, 25)
			GUICtrlSetState($Label_[$a], $GUI_HIDE)
			$Button_[$a] = GUICtrlCreateButton("RUN", $coord1 + 120, $coord2, 75, 25)
			$Input_[$a] = GUICtrlCreateInput("", $coord1 + 220, $coord2, 540, 25)
			GUICtrlSetOnEvent($Button_[$a], _run_nett)
			$coord2 = $coord2 + 30


		Next





EndFunc

; _______________________
;
; téléchargement et lancement appli
; _______________________

Func _run_nett()


	$log = GUICtrlRead(@GUI_CtrlId-1)
	$name_net = IniRead($ini_net,$log,"name","")
	$base_url_net = IniRead($ini_cfg,"config","url_base","")
	$file_path = IniRead($ini_net,$log,"fichier_path","")
	$file_name_net = IniRead($ini_net,$log,"fichier_nom","")
	$file_cmd_net = IniRead($ini_net,$log,"commande_lancement","")

; _______téléchargement


_Log($name_net & " - Téléchargement en cours", "Nettoyage", "Téléchargement")
$file_path_net = $base_url_net & $file_path


        ; Save the downloaded file to the temporary folder.
        Local $sFilePath = _WinAPI_GetTempFileName(@TempDir)


        ; Download the file in the background with the selected option of 'force a reload from the remote site.'
        Local $hDownload = InetGet($file_path_net, $sFilePath, $INET_FORCERELOAD, $INET_DOWNLOADBACKGROUND)

        ; Wait for the download to complete by monitoring when the 2nd index value of InetGetInfo returns True.
        Do
                Sleep(250)
        Until InetGetInfo($hDownload, $INET_DOWNLOADCOMPLETE)

        ; Retrieve the number of total bytes received and the filesize.
        Local $iBytesSize = InetGetInfo($hDownload, $INET_DOWNLOADREAD)
        Local $iFileSize = FileGetSize($sFilePath)

        ; Close the handle returned by InetGet.
        InetClose($hDownload)

		FileMove($sFilePath,@ScriptDir & "\Download\Nettoyage\" & $file_name_net,9)



; _______Verification si le fichier est un ZIP
		If IniRead($ini_net,$log,"est_zip","") = 1 Then ;Zip


			; _______décompression du fichier :


			;~ _Zip_Unzip($ZipFile,$FileName, $DestPath, [$flag])
				_Log("  - Décompression Zip", "Nettoyage", "Décompression")

				$ZipFile = @ScriptDir & "\Download\Nettoyage\" & $file_name_net
				$DestPath = @ScriptDir & "\Download\Nettoyage\"
				_Zip_UnzipAll($ZipFile, $DestPath, 0)


			; _______suppresion du zip

				FileDelete(@ScriptDir & "\Download\Nettoyage\" & $file_name_net)
				_Log(" - Suppression du ZIP", "Nettoyage", "Décompression")

			; _______lancement du fichier

				_Log(" - Téléchargement en Terminé - Lancement du logiciel : ", "Nettoyage", "Lancement")
				Sleep(500)

				$no_extension_zip = StringRegExpReplace($file_name_net, "\.zip$", "")



			   Local $iFileExists = FileExists(@ScriptDir & "\Download\Nettoyage\" & $no_extension_zip & "\" & $file_cmd_net)



				;affiche message si erreur et lance le script si oK
				If $iFileExists Then
					Run(path(@ScriptDir & "\Download\Nettoyage\" & $no_extension_zip & "\" & $file_cmd_net),@WorkingDir) ;A voir si ça fonctionne correctement sur des commande avec des arguments
					_Log("OK", "Nettoyage", "Succès")
				Else
					_Log("ERREUR", "Nettoyage", "Erreur")

				EndIf








		ElseIf IniRead($ini_net,$log,"est_zip","") = 0 Then ;Pas zip

				_Log(" - Téléchargement en Terminé - Lancement du logiciel : ", "Nettoyage", "Lancement")

				Sleep(500)


			   Local $iFileExists = FileExists(@ScriptDir & "\Download\Nettoyage\" & $file_name_net)

				;affiche message si erreur et lance le script si oK
				If $iFileExists Then
					Run(path(@ScriptDir & "\Download\Nettoyage\" & $file_cmd_net),@WorkingDir) ;A voir si ça fonctionne correctement sur des commande avec des arguments
					_Log("OK", "Nettoyage", "Succès")
				Else
					_Log("ERREUR", "Nettoyage", "Erreur")

				EndIf

		EndIf



ConsoleWrite("$log = " & $log & @CRLF)
ConsoleWrite("$Checkbox_[" & $log & "] = " & $Checkbox_[$log] & @CRLF)
;création variable
GUICtrlSetState($Checkbox_[$log], $GUI_CHECKED)

; Sauvegarder immédiatement l'état après l'exécution
_SaveNettoyageStateToAPI()

EndFunc
; ===============================================
; FONCTIONS DE SAUVEGARDE/RESTAURATION VIA API
; ===============================================

; Fonction pour sauvegarder l'état des logiciels de nettoyage via l'API
Func _SaveNettoyageStateToAPI()
    ConsoleWrite("=== DEBUG _SaveNettoyageStateToAPI() ===" & @CRLF)
    
    Local $id_inter = IniRead(@ScriptDir & "\ini\cfg.ini", "config", "id_inter", "")
    ConsoleWrite("ID Intervention: " & $id_inter & @CRLF)
    
    If $id_inter = "" Then
        ConsoleWrite("ERREUR: Pas d'intervention configurée" & @CRLF)
        Return ; Pas d'intervention configurée
    EndIf
    
    Local $nb_net = IniRead($ini_net, "cfg", "nb", "")
    ConsoleWrite("Nombre de logiciels: " & $nb_net & @CRLF)
    ConsoleWrite("Fichier ini_net: " & $ini_net & @CRLF)
    
    If $nb_net = "" Then
        ConsoleWrite("ERREUR: Aucun logiciel configuré" & @CRLF)
        Return
    EndIf
    
    Local $stateString = ""
    
    ; Parcourir tous les logiciels de nettoyage
    For $a = 1 To $nb_net Step 1
        Local $name_net = IniRead($ini_net, $a, "name", "")
        ConsoleWrite("Logiciel " & $a & ": " & $name_net & @CRLF)
        
        If $name_net <> "" Then
            ; Vérifier l'état de la checkbox
            Local $checkboxValue = GUICtrlRead($Checkbox_[$a])
            Local $isChecked = ($checkboxValue = $GUI_CHECKED) ? 1 : 0
            ConsoleWrite("  Checkbox[" & $a & "] = " & $checkboxValue & " (isChecked=" & $isChecked & ")" & @CRLF)
            
            ; Récupérer le contenu de l'input
            Local $inputContent = GUICtrlRead($Input_[$a])
            ConsoleWrite("  Input[" & $a & "] = '" & $inputContent & "'" & @CRLF)
            
            ; Construire la chaîne : nom=état=contenu
            If $stateString <> "" Then $stateString &= ";"
            $stateString &= $name_net & "=" & $isChecked & "=" & $inputContent
        EndIf
    Next
    
    ConsoleWrite("Chaîne d'état finale: " & $stateString & @CRLF)
    
    ; Sauvegarder via l'API dans le champ nettoyage (qui existe déjà)
    If $stateString <> "" Then
        ConsoleWrite("Sauvegarde via API..." & @CRLF)
        API_SauvegarderChamp($id_inter, "nettoyage", $stateString)
        If @error Then
            ConsoleWrite("ERREUR lors de la sauvegarde API: " & @error & @CRLF)
        Else
            ConsoleWrite("Sauvegarde API réussie !" & @CRLF)
        EndIf
    Else
        ConsoleWrite("ATTENTION: Aucune donnée à sauvegarder" & @CRLF)
    EndIf
    
    ConsoleWrite("=== FIN DEBUG _SaveNettoyageStateToAPI() ===" & @CRLF)
EndFunc

; Fonction pour restaurer l'état des logiciels de nettoyage depuis l'API
Func _RestoreNettoyageStateFromAPI()
    ConsoleWrite("=== DEBUG _RestoreNettoyageStateFromAPI() ===" & @CRLF)
    
    Local $id_inter = IniRead(@ScriptDir & "\ini\cfg.ini", "config", "id_inter", "")
    ConsoleWrite("ID Intervention: " & $id_inter & @CRLF)
    
    If $id_inter = "" Then
        ConsoleWrite("ERREUR: Pas d'intervention configurée" & @CRLF)
        Return ; Pas d'intervention configurée
    EndIf
    
    ; Lire l'état depuis l'API (utiliser le champ nettoyage)
    Local $stateString = API_LireChamp($id_inter, "nettoyage")
    ConsoleWrite("Données lues depuis l'API: '" & $stateString & "'" & @CRLF)
    
    If @error Or $stateString = "" Then
        ConsoleWrite("ATTENTION: Aucun état sauvegardé (erreur=" & @error & ")" & @CRLF)
        Return ; Aucun état sauvegardé
    EndIf
    
    ; Vérifier si c'est notre format de données (contient des "=" et des ";")
    If StringInStr($stateString, "=") = 0 Or StringInStr($stateString, ";") = 0 Then
        ConsoleWrite("ATTENTION: Format de données non reconnu, probablement un ancien log" & @CRLF)
        Return ; Pas notre format
    EndIf
    
    ; Parser la chaîne d'état : nom1=état1=contenu1;nom2=état2=contenu2;...
    Local $logiciels = StringSplit($stateString, ";")
    ConsoleWrite("Nombre de logiciels dans les données: " & $logiciels[0] & @CRLF)
    
    Local $nb_net = IniRead($ini_net, "cfg", "nb", "")
    ConsoleWrite("Nombre de logiciels dans l'interface: " & $nb_net & @CRLF)
    
    For $i = 1 To $logiciels[0]
        ConsoleWrite("Traitement logiciel " & $i & ": " & $logiciels[$i] & @CRLF)
        Local $logicielData = StringSplit($logiciels[$i], "=", 2) ; 2 = pas de compteur
        
        If UBound($logicielData) >= 3 Then
            Local $nomLogiciel = $logicielData[0]
            Local $etatCoche = $logicielData[1]
            Local $contenuInput = $logicielData[2]
            
            ; Reconstituer le contenu si il y avait des "=" dans l'input
            If UBound($logicielData) > 3 Then
                For $j = 3 To UBound($logicielData) - 1
                    $contenuInput &= "=" & $logicielData[$j]
                Next
            EndIf
            
            ConsoleWrite("  Logiciel: " & $nomLogiciel & ", État: " & $etatCoche & ", Contenu: '" & $contenuInput & "'" & @CRLF)
            
            ; Trouver le logiciel correspondant dans l'interface actuelle
            Local $logicielTrouve = False
            For $a = 1 To $nb_net Step 1
                Local $name_net = IniRead($ini_net, $a, "name", "")
                
                If $name_net = $nomLogiciel Then
                    ConsoleWrite("    Logiciel trouvé à l'index " & $a & @CRLF)
                    
                    ; Vérifier que les contrôles existent
                    If $Checkbox_[$a] <> 0 And $Input_[$a] <> 0 Then
                        ; Restaurer l'état de la checkbox
                        If $etatCoche = "1" Then
                            GUICtrlSetState($Checkbox_[$a], $GUI_CHECKED)
                            ConsoleWrite("    Checkbox cochée" & @CRLF)
                        Else
                            GUICtrlSetState($Checkbox_[$a], $GUI_UNCHECKED)
                            ConsoleWrite("    Checkbox décochée" & @CRLF)
                        EndIf
                        
                        ; Restaurer le contenu de l'input
                        GUICtrlSetData($Input_[$a], $contenuInput)
                        ConsoleWrite("    Input restauré: '" & $contenuInput & "'" & @CRLF)
                        
                        $logicielTrouve = True
                    Else
                        ConsoleWrite("    ERREUR: Contrôles non initialisés (Checkbox=" & $Checkbox_[$a] & ", Input=" & $Input_[$a] & ")" & @CRLF)
                    EndIf
                    
                    ExitLoop ; Logiciel trouvé, passer au suivant
                EndIf
            Next
            
            If Not $logicielTrouve Then
                ConsoleWrite("    ATTENTION: Logiciel '" & $nomLogiciel & "' non trouvé dans l'interface actuelle" & @CRLF)
            EndIf
        Else
            ConsoleWrite("  ERREUR: Format de données invalide pour: " & $logiciels[$i] & @CRLF)
        EndIf
    Next
    
    ConsoleWrite("=== FIN DEBUG _RestoreNettoyageStateFromAPI() ===" & @CRLF)
EndFunc


