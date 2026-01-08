using System;
using System.Diagnostics;
using System.IO;
using System.Text;
using System.Threading;

class ProcessBridge
{
    static Process targetProcess;
    static string logFile;
    static string inputFile;
    static bool isRunning = true;
    static long lastInputSize = 0;
    static object fileLock = new object();

    // Debounce/Buffer Logic
    static StringBuilder outputBuffer = new StringBuilder();
    static DateTime lastFlushTime = DateTime.MinValue;

    static void Main(string[] args)
    {
        if (args.Length < 3)
        {
            Console.WriteLine("Usage: ProcessBridge.exe <Command> <LogFile> <InputFile>");
            return;
        }

        string command = args[0];
        logFile = args[1];
        inputFile = args[2];

        // Reset files
        File.WriteAllText(logFile, "--- Bridge Started (Debounced) ---\r\n");
        File.WriteAllText(inputFile, "");

        ProcessStartInfo psi = new ProcessStartInfo();
        psi.FileName = "cmd.exe";
        psi.Arguments = "/c chcp 65001 > NUL && " + command;
        psi.UseShellExecute = false;
        psi.RedirectStandardOutput = true;
        psi.RedirectStandardError = true;
        psi.RedirectStandardInput = true;
        psi.CreateNoWindow = true;
        
        psi.StandardOutputEncoding = Encoding.UTF8;
        psi.StandardErrorEncoding = Encoding.UTF8;

        targetProcess = new Process();
        targetProcess.StartInfo = psi;

        try
        {
            targetProcess.Start();

            // Start dedicated threads
            Thread stdoutThread = new Thread(() => ReadStream(targetProcess.StandardOutput));
            Thread stderrThread = new Thread(() => ReadStream(targetProcess.StandardError));
            
            stdoutThread.Start();
            stderrThread.Start();
            
            Log("Process started: " + command);

            while (!targetProcess.HasExited && isRunning)
            {
                CheckInput();
                // Periodic flush for long-running non-newline output
                FlushBuffer(false); 
                Thread.Sleep(50);
            }
        }
        catch (Exception ex)
        {
            Log("ERROR: " + ex.Message);
        }
        finally
        {
            // Flush anything remaining
            FlushBuffer(true);
            Log("\r\n--- Bridge Ended ---");
            isRunning = false;
            try { if (!targetProcess.HasExited) targetProcess.Kill(); } catch {}
        }
    }

    static bool pendingCR = false;

    private static void ReadStream(StreamReader reader)
    {
        char[] buffer = new char[1024];
        while (isRunning && !reader.EndOfStream)
        {
            try
            {
                int bytesRead = reader.Read(buffer, 0, buffer.Length);
                if (bytesRead > 0)
                {
                    lock(outputBuffer)
                    {
                        for (int i = 0; i < bytesRead; i++)
                        {
                            char c = buffer[i];
                            if (c == '\r')
                            {
                                pendingCR = true;
                            }
                            else
                            {
                                if (pendingCR)
                                {
                                    if (c == '\n')
                                    {
                                        // It was CRLF (\r\n) -> This is a normal line end. 
                                        // Keep current buffer, append the newline, and FLUSH.
                                        // We don't append \r blindly to avoid messing up the file if not needed,
                                        // but standard text files usually want \r\n or \n. 
                                        // Let's just append Environment.NewLine or just \n.
                                        outputBuffer.Append("\r\n"); 
                                        FlushBuffer(true);
                                    }
                                    else
                                    {
                                        // It was a standalone CR followed by something else.
                                        // This means "Go back to start of line".
                                        // ACTION: Clear the buffer (Debounce!)
                                        outputBuffer.Clear();
                                        outputBuffer.Append(c);
                                    }
                                    pendingCR = false;
                                }
                                else if (c == '\n') // Standalone LF (Unix style or weirdness)
                                {
                                     outputBuffer.Append(c);
                                     FlushBuffer(true);
                                }
                                else
                                {
                                    outputBuffer.Append(c);
                                }
                            }
                        }
                    }
                }
            }
            catch { break; }
        }
    }

    private static void FlushBuffer(bool force)
    {
        lock(outputBuffer)
        {
            if (outputBuffer.Length == 0) return;

            // Debounce: Only flush if > 200ms passed OR if forced (by newline)
            if (force || (DateTime.Now - lastFlushTime).TotalMilliseconds > 200)
            {
                string content = outputBuffer.ToString();
                
                // If we are auto-flushing (not forced by newline) and it looks like a spinner...
                // we might want to wait? No, let's just print.
                // The key is that \r CLEARED the buffer, so we only print the LATEST state
                // instead of 0%... 1%... 
                
                Log(content, false);
                outputBuffer.Clear();
                lastFlushTime = DateTime.Now;
            }
        }
    }

    private static void Log(string message, bool addNewline = true)
    {
        try 
        {
            lock(fileLock) 
            {
                using (StreamWriter sw = new StreamWriter(logFile, true, new UTF8Encoding(false)))
                {
                    if (addNewline)
                        sw.WriteLine(message);
                    else
                        sw.Write(message);
                }
            }
            if(addNewline) Console.WriteLine(message); else Console.Write(message);
        }
        catch {}
    }

    private static void CheckInput()
    {
        try
        {
            FileInfo fi = new FileInfo(inputFile);
            if (!fi.Exists) return;

            long currentLength = fi.Length;
            if (currentLength > lastInputSize)
            {
                 using (FileStream fs = new FileStream(inputFile, FileMode.Open, FileAccess.Read, FileShare.ReadWrite))
                 {
                     fs.Seek(lastInputSize, SeekOrigin.Begin);
                     using (StreamReader sr = new StreamReader(fs, Encoding.UTF8))
                     {
                         string newContent = sr.ReadToEnd();
                         if (!string.IsNullOrEmpty(newContent))
                         {
                             targetProcess.StandardInput.Write(newContent);
                             // Echo? Maybe slightly differently to distinguish
                             Log("> " + newContent.TrimEnd(), true); 
                         }
                     }
                     lastInputSize = currentLength;
                 }
            }
            else if (currentLength < lastInputSize)
            {
                lastInputSize = currentLength;
            }
        }
        catch { }
    }
}
