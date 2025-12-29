$ini_sys_dir = @ScriptDir & "\ini\sys.ini"
$ini_cfg_dir = @ScriptDir & "\ini\cfg.ini"
IniWrite($ini_sys_dir, "NB", "Total", "0")
local $releaseid = ""


Func _sys_CG()
$wbemFlagReturnImmediately = 0x10
$wbemFlagForwardOnly = 0x20
$colItems = ""
$strComputer = "localhost"

$OutputTitle = ""
$Output = ""
$OutputTitle &= "Computer: " & $strComputer  & @CRLF
$OutputTitle &= "==========================================" & @CRLF
$objWMIService = ObjGet("winmgmts:\\" & $strComputer & "\root\CIMV2")
$colItems = $objWMIService.ExecQuery("SELECT * FROM Win32_VideoController", "WQL", _
                                          $wbemFlagReturnImmediately + $wbemFlagForwardOnly)

If IsObj($colItems) then
   Local $Object_Flag = 0
   $i = 1
   For $objItem In $colItems
      $Object_Flag = 1

		IniWrite($ini_sys_dir, "CG" & $i, "AdapterRAM", $objItem.AdapterRAM/1024/1024)
		IniWrite($ini_sys_dir, "CG" & $i, "Description", $objItem.Description)
		IniWrite($ini_sys_dir, "CG" & $i, "DriverDate", WMIDateStringToDate($objItem.DriverDate))
		IniWrite($ini_sys_dir, "CG" & $i, "DriverVersion", $objItem.DriverVersion)

		$i = $i + 1
   Next


      	IniWrite($ini_sys_dir, "NB", "CG", $i-1)

Endif
EndFunc

Func _sys_startup()

$wbemFlagReturnImmediately = 0x10
$wbemFlagForwardOnly = 0x20
$colItems = ""
$strComputer = "localhost"

$OutputTitle = ""
$Output = ""
$OutputTitle &= "Computer: " & $strComputer  & @CRLF
$OutputTitle &= "==========================================" & @CRLF
$objWMIService = ObjGet("winmgmts:\\" & $strComputer & "\root\CIMV2")
$colItems = $objWMIService.ExecQuery("SELECT * FROM Win32_StartupCommand", "WQL", _
                                          $wbemFlagReturnImmediately + $wbemFlagForwardOnly)

If IsObj($colItems) then
   Local $Object_Flag = 0
   $i = 1
   For $objItem In $colItems
      $Object_Flag = 1

		IniWrite($ini_sys_dir, "LOG" & $i, "Caption", $objItem.Caption)
		IniWrite($ini_sys_dir, "LOG" & $i, "Command", $objItem.Command)
		IniWrite($ini_sys_dir, "LOG" & $i, "Location", $objItem.Location)
		IniWrite($ini_sys_dir, "LOG" & $i, "Name", $objItem.Name)

		$i = $i + 1
   Next

   	IniWrite($ini_sys_dir, "NB", "LOG", $i-1)

Endif




EndFunc

Func _sys_IMP()


$wbemFlagReturnImmediately = 0x10
$wbemFlagForwardOnly = 0x20
$colItems = ""
$strComputer = "localhost"

$OutputTitle = ""
$Output = ""
$OutputTitle &= "Computer: " & $strComputer  & @CRLF
$OutputTitle &= "==========================================" & @CRLF
$objWMIService = ObjGet("winmgmts:\\" & $strComputer & "\root\CIMV2")
$colItems = $objWMIService.ExecQuery("SELECT * FROM Win32_Printer", "WQL", _
                                          $wbemFlagReturnImmediately + $wbemFlagForwardOnly)

If IsObj($colItems) then
   Local $Object_Flag = 0

	 $i = 1
    For $objItem In $colItems
      $Object_Flag = 1

		IniWrite($ini_sys_dir, "IMP" & $i, "Name", $objItem.Name)

		$i = $i + 1
    Next

   	IniWrite($ini_sys_dir, "NB", "IMP", $i-1)

Endif

EndFunc

Func _sys_RAM()

$wbemFlagReturnImmediately = 0x10
$wbemFlagForwardOnly = 0x20
$colItems = ""
$strComputer = "localhost"

$OutputTitle = ""
$Output = ""
$OutputTitle &= "Computer: " & $strComputer  & @CRLF
$OutputTitle &= "==========================================" & @CRLF
$objWMIService = ObjGet("winmgmts:\\" & $strComputer & "\root\CIMV2")
$colItems = $objWMIService.ExecQuery("SELECT * FROM Win32_PhysicalMemory", "WQL", _
                                          $wbemFlagReturnImmediately + $wbemFlagForwardOnly)

If IsObj($colItems) then
   Local $Object_Flag = 0
	 $i = 1
    For $objItem In $colItems
      $Object_Flag = 1

		IniWrite($ini_sys_dir, "RAM" & $i, "Capacity", $objItem.Capacity /1024/1024)
		IniWrite($ini_sys_dir, "RAM" & $i, "DeviceLocator",  $objItem.DeviceLocator)
		IniWrite($ini_sys_dir, "RAM" & $i, "Model", $objItem.Model)
		IniWrite($ini_sys_dir, "RAM" & $i, "SerialNumber", $objItem.SerialNumber)
		IniWrite($ini_sys_dir, "RAM" & $i, "Speed", $objItem.Speed)


		$i = $i + 1
    Next

   	IniWrite($ini_sys_dir, "NB", "RAM", $i-1)



Endif

Endfunc

Func _sys_logon()

$wbemFlagReturnImmediately = 0x10
$wbemFlagForwardOnly = 0x20
$colItems = ""
$strComputer = "localhost"

$OutputTitle = ""
$Output = ""
$OutputTitle &= "Computer: " & $strComputer  & @CRLF
$OutputTitle &= "==========================================" & @CRLF
$objWMIService = ObjGet("winmgmts:\\" & $strComputer & "\root\CIMV2")
$colItems = $objWMIService.ExecQuery("SELECT * FROM Win32_LogonSession", "WQL", _
                                          $wbemFlagReturnImmediately + $wbemFlagForwardOnly)

If IsObj($colItems) then
   Local $Object_Flag = 0


	 $i = 1
    For $objItem In $colItems
      $Object_Flag = 1

		IniWrite($ini_sys_dir, "StartTime" & $i, "Name", WMIDateStringToDate($objItem.StartTime))

		$i = $i + 1
    Next

   	IniWrite($ini_sys_dir, "NB", "Logon", $i-1)


Endif

EndFunc

Func _sys_screensaver()

$wbemFlagReturnImmediately = 0x10
$wbemFlagForwardOnly = 0x20
$colItems = ""
$strComputer = "localhost"

$OutputTitle = ""
$Output = ""
$OutputTitle &= "Computer: " & $strComputer  & @CRLF
$OutputTitle &= "==========================================" & @CRLF
$objWMIService = ObjGet("winmgmts:\\" & $strComputer & "\root\CIMV2")
$colItems = $objWMIService.ExecQuery("SELECT * FROM Win32_Desktop", "WQL", _
                                          $wbemFlagReturnImmediately + $wbemFlagForwardOnly)

If IsObj($colItems) then
   Local $Object_Flag = 0

	 $i = 1
    For $objItem In $colItems
      $Object_Flag = 1

		IniWrite($ini_sys_dir, "ScreenSaver" & $i, "ScreenSaverActive", $objItem.ScreenSaverActive)
		IniWrite($ini_sys_dir, "ScreenSaver" & $i, "ScreenSaverExecutable", $objItem.ScreenSaverExecutable)
		IniWrite($ini_sys_dir, "ScreenSaver" & $i, "ScreenSaverSecure", $objItem.ScreenSaverSecure)
		IniWrite($ini_sys_dir, "ScreenSaver" & $i, "ScreenSaverTimeout", $objItem.ScreenSaverTimeout)

		$i = $i + 1
    Next

   	IniWrite($ini_sys_dir, "NB", "Screensaver", $i-1)
Endif

EndFunc

Func _sys_OS()

$wbemFlagReturnImmediately = 0x10
$wbemFlagForwardOnly = 0x20
$colItems = ""
$strComputer = "localhost"

$OutputTitle = ""
$Output = ""
$OutputTitle &= "Computer: " & $strComputer  & @CRLF
$OutputTitle &= "==========================================" & @CRLF
$objWMIService = ObjGet("winmgmts:\\" & $strComputer & "\root\CIMV2")
$colItems = $objWMIService.ExecQuery("SELECT * FROM Win32_OperatingSystem", "WQL", _
                                          $wbemFlagReturnImmediately + $wbemFlagForwardOnly)

If IsObj($colItems) then
   Local $Object_Flag = 0



If(@OSVersion = "WIN_7") Then
	$releaseid = RegRead("HKEY_LOCAL_MACHINE\SOFTWARE\Microsoft\Windows NT\CurrentVersion\", "CSDVersion")
ElseIf(@OSVersion = "WIN_10") Or (@OSVersion = "WIN_11") Then
	$releaseid = RegRead("HKEY_LOCAL_MACHINE\SOFTWARE\Microsoft\Windows NT\CurrentVersion\", "ReleaseId")
	If $releaseid = "2009" Then
		$releaseid = RegRead("HKEY_LOCAL_MACHINE\SOFTWARE\Microsoft\Windows NT\CurrentVersion\", "DisplayVersion")
	EndIf
EndIf

	 $i = 1
    For $objItem In $colItems
      $Object_Flag = 1

		IniWrite($ini_sys_dir, "OS" & $i, "BuildNumber", $objItem.BuildNumber)
		IniWrite($ini_sys_dir, "OS" & $i, "Buildversion", $releaseid)
		IniWrite($ini_sys_dir, "OS" & $i, "Caption", $objItem.Caption)
		IniWrite($ini_sys_dir, "OS" & $i, "InstallDate", WMIDateStringToDate($objItem.InstallDate))
		$strMUILanguages = $objItem.MUILanguages(0)
		IniWrite($ini_sys_dir, "OS" & $i, "MUILanguages", $strMUILanguages)
		IniWrite($ini_sys_dir, "OS" & $i, "Name", $objItem.Name)
		IniWrite($ini_sys_dir, "OS" & $i, "NumberOfUsers", $objItem.NumberOfUsers)
		IniWrite($ini_sys_dir, "OS" & $i, "OSArchitecture", $objItem.OSArchitecture)
		IniWrite($ini_sys_dir, "OS" & $i, "SerialNumber", $objItem.SerialNumber)
		IniWrite($ini_sys_dir, "OS" & $i, "SystemDirectory", $objItem.SystemDirectory)
		IniWrite($ini_sys_dir, "OS" & $i, "Version", $objItem.Version)
		IniWrite($ini_sys_dir, "OS" & $i, "WindowsDirectory", $objItem.WindowsDirectory)


		$i = $i + 1
    Next

   	IniWrite($ini_sys_dir, "NB", "OS", $i-1)


Endif

EndFunc

Func _sys_BIOS()

$wbemFlagReturnImmediately = 0x10
$wbemFlagForwardOnly = 0x20
$colItems = ""
$strComputer = "localhost"

$OutputTitle = ""
$Output = ""
$OutputTitle &= "Computer: " & $strComputer  & @CRLF
$OutputTitle &= "==========================================" & @CRLF
$objWMIService = ObjGet("winmgmts:\\" & $strComputer & "\root\CIMV2")
$colItems = $objWMIService.ExecQuery("SELECT * FROM Win32_BIOS", "WQL", _
                                          $wbemFlagReturnImmediately + $wbemFlagForwardOnly)

If IsObj($colItems) then
   Local $Object_Flag = 0


;~    For $objItem In $colItems
;~       $Object_Flag = 1
;~       $Output &= "Name: " & $objItem.Name & @CRLF
;~ 		$Output &= "SMBIOSBIOSVersion: " & $objItem.SMBIOSBIOSVersion & @CRLF
;~       $Output &= "ReleaseDate: " & WMIDateStringToDate($objItem.ReleaseDate) & @CRLF
;~       $Output &= "SerialNumber: " & $objItem.SerialNumber & @CRLF
;~ 	if Msgbox(1,"Sortie WMI",$Output) = 2 then ExitLoop
;~       $Output=""
;~    Next

	 $i = 1
    For $objItem In $colItems
      $Object_Flag = 1

		IniWrite($ini_sys_dir, "BIOS" & $i, "Name", $objItem.Name)
		IniWrite($ini_sys_dir, "BIOS" & $i, "SMBIOSBIOSVersion", $objItem.SMBIOSBIOSVersion)
		IniWrite($ini_sys_dir, "BIOS" & $i, "ReleaseDate", WMIDateStringToDate($objItem.ReleaseDate))
		IniWrite($ini_sys_dir, "BIOS" & $i, "SerialNumber", $objItem.SerialNumber)

		$i = $i + 1
    Next

   	IniWrite($ini_sys_dir, "NB", "BIOS", $i-1)


Endif


EndFunc

Func _sys_CS()

$wbemFlagReturnImmediately = 0x10
$wbemFlagForwardOnly = 0x20
$colItems = ""
$strComputer = "localhost"

$OutputTitle = ""
$Output = ""
$OutputTitle &= "Computer: " & $strComputer  & @CRLF
$OutputTitle &= "==========================================" & @CRLF
$objWMIService = ObjGet("winmgmts:\\" & $strComputer & "\root\CIMV2")
$colItems = $objWMIService.ExecQuery("SELECT * FROM Win32_ComputerSystem", "WQL", _
                                          $wbemFlagReturnImmediately + $wbemFlagForwardOnly)

If IsObj($colItems) then
   Local $Object_Flag = 0


	 $i = 1

    For $objItem In $colItems
      $Object_Flag = 1

		IniWrite($ini_sys_dir, "ComputerSystem" & $i, "AdminPasswordStatus", $objItem.AdminPasswordStatus)
		IniWrite($ini_sys_dir, "ComputerSystem" & $i, "Caption", $objItem.Caption)
		IniWrite($ini_sys_dir, "ComputerSystem" & $i, "ChassisSKUNumber", $objItem.ChassisSKUNumber)
		IniWrite($ini_sys_dir, "ComputerSystem" & $i, "DNSHostName", $objItem.DNSHostName)
		IniWrite($ini_sys_dir, "ComputerSystem" & $i, "Domain", $objItem.Domain)
		IniWrite($ini_sys_dir, "ComputerSystem" & $i, "Manufacturer", $objItem.Manufacturer)
		IniWrite($ini_sys_dir, "ComputerSystem" & $i, "Model", $objItem.Model)
		IniWrite($ini_sys_dir, "ComputerSystem" & $i, "Name", $objItem.Name)
		IniWrite($ini_sys_dir, "ComputerSystem" & $i, "NumberOfLogicalProcessors", $objItem.NumberOfLogicalProcessors)
		IniWrite($ini_sys_dir, "ComputerSystem" & $i, "NumberOfProcessors", $objItem.NumberOfProcessors)
		IniWrite($ini_sys_dir, "ComputerSystem" & $i, "PartOfDomain", $objItem.PartOfDomain)
		IniWrite($ini_sys_dir, "ComputerSystem" & $i, "UserName", $objItem.UserName)
		IniWrite($ini_sys_dir, "ComputerSystem" & $i, "WakeUpType", $objItem.WakeUpType)
		IniWrite($ini_sys_dir, "ComputerSystem" & $i, "Workgroup", $objItem.Workgroup)

		$i = $i + 1
    Next

   	IniWrite($ini_sys_dir, "NB", "ComputerSystem", $i-1)
Endif


EndFunc

Func _sys_CPU()

$wbemFlagReturnImmediately = 0x10
$wbemFlagForwardOnly = 0x20
$colItems = ""
$strComputer = "localhost"

$OutputTitle = ""
$Output = ""
$OutputTitle &= "Computer: " & $strComputer  & @CRLF
$OutputTitle &= "==========================================" & @CRLF
$objWMIService = ObjGet("winmgmts:\\" & $strComputer & "\root\CIMV2")
$colItems = $objWMIService.ExecQuery("SELECT * FROM Win32_Processor", "WQL", _
                                          $wbemFlagReturnImmediately + $wbemFlagForwardOnly)

If IsObj($colItems) then
   Local $Object_Flag = 0
;~    For $objItem In $colItems
;~       $Object_Flag = 1

;~       $Output &= "Name: " & $objItem.Name & @CRLF
;~       $Output &= "NumberOfCores: " & $objItem.NumberOfCores & @CRLF
;~       $Output &= "NumberOfEnabledCore: " & $objItem.NumberOfEnabledCore & @CRLF
;~       $Output &= "NumberOfLogicalProcessors: " & $objItem.NumberOfLogicalProcessors & @CRLF
;~        if Msgbox(1,"Sortie WMI",$Output) = 2 then ExitLoop
;~       $Output=""
;~    Next

	 $i = 1
    For $objItem In $colItems
      $Object_Flag = 1

		IniWrite($ini_sys_dir, "CPU" & $i, "Name", $objItem.Name)
		IniWrite($ini_sys_dir, "CPU" & $i, "NumberOfCores", $objItem.NumberOfCores)
		IniWrite($ini_sys_dir, "CPU" & $i, "NumberOfEnabledCore", $objItem.NumberOfEnabledCore)
		IniWrite($ini_sys_dir, "CPU" & $i, "NumberOfLogicalProcessors", $objItem.NumberOfLogicalProcessors)

		$i = $i + 1
    Next

   	IniWrite($ini_sys_dir, "NB", "CPU", $i-1)


Endif

EndFunc

Func _sys_CM()

$wbemFlagReturnImmediately = 0x10
$wbemFlagForwardOnly = 0x20
$colItems = ""
$strComputer = "localhost"

$OutputTitle = ""
$Output = ""
$OutputTitle &= "Computer: " & $strComputer  & @CRLF
$OutputTitle &= "==========================================" & @CRLF
$objWMIService = ObjGet("winmgmts:\\" & $strComputer & "\root\CIMV2")
$colItems = $objWMIService.ExecQuery("SELECT * FROM Win32_BaseBoard", "WQL", _
                                          $wbemFlagReturnImmediately + $wbemFlagForwardOnly)

If IsObj($colItems) then
   Local $Object_Flag = 0


	 $i = 1
    For $objItem In $colItems
      $Object_Flag = 1

		IniWrite($ini_sys_dir, "CM" & $i, "Caption", $objItem.Caption)
		IniWrite($ini_sys_dir, "CM" & $i, "Description", $objItem.Description)
		IniWrite($ini_sys_dir, "CM" & $i, "Manufacturer", $objItem.Manufacturer)
		IniWrite($ini_sys_dir, "CM" & $i, "Model", $objItem.Model)
		IniWrite($ini_sys_dir, "CM" & $i, "Name", $objItem.Name)
		IniWrite($ini_sys_dir, "CM" & $i, "PartNumber", $objItem.PartNumber)
		IniWrite($ini_sys_dir, "CM" & $i, "Product", $objItem.Product)
		IniWrite($ini_sys_dir, "CM" & $i, "SerialNumber", $objItem.SerialNumber)
		IniWrite($ini_sys_dir, "CM" & $i, "Version", $objItem.Version)

		$i = $i + 1
    Next

   	IniWrite($ini_sys_dir, "NB", "CM", $i-1)


Endif


EndFunc

func _sys_DD()

$wbemFlagReturnImmediately = 0x10
$wbemFlagForwardOnly = 0x20
$colItems = ""
$strComputer = "localhost"

$OutputTitle = ""
$Output = ""
$OutputTitle &= "Computer: " & $strComputer  & @CRLF
$OutputTitle &= "==========================================" & @CRLF
$objWMIService = ObjGet("winmgmts:\\" & $strComputer & "\root\CIMV2")
$colItems = $objWMIService.ExecQuery("SELECT * FROM Win32_DiskDrive", "WQL", _
                                          $wbemFlagReturnImmediately + $wbemFlagForwardOnly)

If IsObj($colItems) then
   Local $Object_Flag = 0

	 $i = 1
    For $objItem In $colItems
      $Object_Flag = 1

		IniWrite($ini_sys_dir, "DD" & $i, "Caption", $objItem.Caption)
		IniWrite($ini_sys_dir, "DD" & $i, "FirmwareRevision", $objItem.FirmwareRevision)
		IniWrite($ini_sys_dir, "DD" & $i, "InterfaceType", $objItem.InterfaceType)
		IniWrite($ini_sys_dir, "DD" & $i, "LastErrorCode", $objItem.LastErrorCode)
		IniWrite($ini_sys_dir, "DD" & $i, "SerialNumber", $objItem.SerialNumber)
		IniWrite($ini_sys_dir, "DD" & $i, "Size", $objItem.Size/1024/1024/1024)

		$i = $i + 1
    Next

   	IniWrite($ini_sys_dir, "NB", "DD", $i-1)
Endif

EndFunc



Func WMIDateStringToDate($dtmDate)
	Return (StringMid($dtmDate, 5, 2) & "/" & _
	StringMid($dtmDate, 7, 2) & "/" & StringLeft($dtmDate, 4) _
	& " " & StringMid($dtmDate, 9, 2) & ":" & StringMid($dtmDate, 11, 2) & ":" & StringMid($dtmDate,13, 2))
EndFunc