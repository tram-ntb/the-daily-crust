Set sh = CreateObject("WScript.Shell")
bat = Replace(WScript.ScriptFullName, "chay_an.vbs", "chay_scheduler.bat")
sh.Run "cmd /c """ & bat & """", 0, False
