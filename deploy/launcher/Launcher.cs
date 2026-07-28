// Daher Phone — Windows launcher.
// Starts the private MariaDB + PHP services, initializes the database on
// first run, opens the app in the default browser, then lives in the tray.
//
// Build (any Windows, no SDK needed):
//   %WINDIR%\Microsoft.NET\Framework64\v4.0.30319\csc.exe /target:winexe
//     /out:DaherPhone.exe Launcher.cs
//
// Reads server.ini next to the executable. All paths are relative to the
// installation folder, so the package works from any directory.

using System;
using System.Collections.Generic;
using System.Diagnostics;
using System.Drawing;
using System.IO;
using System.Net;
using System.Net.Sockets;
using System.Reflection;
using System.Threading;
using System.Windows.Forms;

[assembly: AssemblyTitle("Daher Phone")]
[assembly: AssemblyProduct("Daher Phone")]
[assembly: AssemblyDescription("Daher Phone - shop management system launcher")]
[assembly: AssemblyCompany("Daher Phone")]
[assembly: AssemblyVersion("1.3.0.0")]
[assembly: AssemblyFileVersion("1.3.0.0")]

namespace DaherPhone
{
    internal static class Program
    {
        [STAThread]
        private static void Main()
        {
            bool createdNew;
            using (var mutex = new Mutex(true, "DaherPhoneLauncher", out createdNew))
            {
                if (!createdNew)
                {
                    // Already running: just reopen the browser tab.
                    var cfgExisting = LauncherConfig.Load();
                    try { Process.Start(cfgExisting.AppUrl); } catch { }
                    return;
                }

                Application.EnableVisualStyles();
                Application.SetCompatibleTextRenderingDefault(false);
                Application.Run(new LauncherForm());
                GC.KeepAlive(mutex);
            }
        }
    }

    internal sealed class LauncherConfig
    {
        public string Root;
        public int PhpPort = 8123;
        public int DbPort = 3307;
        public string AppDir = "Application";
        public string PhpDir = "Server\\PHP";
        public string DbDir = "Server\\MariaDB";
        public string DataDir = "Database\\data";
        public string LogsDir = "Logs";

        public string AppUrl
        {
            get { return string.Format("http://127.0.0.1:{0}/index.php", PhpPort); }
        }

        public string Abs(string relative)
        {
            return Path.GetFullPath(Path.Combine(Root, relative));
        }

        public static LauncherConfig Load()
        {
            var cfg = new LauncherConfig();
            cfg.Root = AppDomain.CurrentDomain.BaseDirectory;
            var ini = Path.Combine(cfg.Root, "server.ini");
            if (File.Exists(ini))
            {
                foreach (var rawLine in File.ReadAllLines(ini))
                {
                    var line = rawLine.Trim();
                    if (line.Length == 0 || line.StartsWith(";") || line.StartsWith("#") || line.StartsWith("["))
                        continue;
                    var eq = line.IndexOf('=');
                    if (eq < 1) continue;
                    var key = line.Substring(0, eq).Trim().ToLowerInvariant();
                    var value = line.Substring(eq + 1).Trim();
                    switch (key)
                    {
                        case "php_port": int.TryParse(value, out cfg.PhpPort); break;
                        case "db_port": int.TryParse(value, out cfg.DbPort); break;
                        case "app_dir": cfg.AppDir = value; break;
                        case "php_dir": cfg.PhpDir = value; break;
                        case "db_dir": cfg.DbDir = value; break;
                        case "data_dir": cfg.DataDir = value; break;
                        case "logs_dir": cfg.LogsDir = value; break;
                    }
                }
            }
            return cfg;
        }
    }

    internal sealed class LauncherForm : Form
    {
        private readonly LauncherConfig _cfg;
        private readonly Label _status;
        private readonly ProgressBar _bar;
        private NotifyIcon _tray;
        private Process _php;
        private bool _startedDb;
        private bool _exiting;

        public LauncherForm()
        {
            _cfg = LauncherConfig.Load();

            Text = "Daher Phone";
            FormBorderStyle = FormBorderStyle.FixedSingle;
            MaximizeBox = false;
            StartPosition = FormStartPosition.CenterScreen;
            ClientSize = new Size(420, 130);

            // Window + tray icon = the icon embedded in this executable.
            try { Icon = Icon.ExtractAssociatedIcon(Application.ExecutablePath); }
            catch { }

            var title = new Label();
            title.Text = "Daher Phone";
            title.Font = new Font("Segoe UI", 14f, FontStyle.Bold);
            title.Location = new Point(16, 12);
            title.AutoSize = true;
            Controls.Add(title);

            _status = new Label();
            _status.Text = "Starting ...";
            _status.Font = new Font("Segoe UI", 9.5f);
            _status.Location = new Point(18, 52);
            _status.Size = new Size(384, 22);
            Controls.Add(_status);

            _bar = new ProgressBar();
            _bar.Style = ProgressBarStyle.Marquee;
            _bar.Location = new Point(18, 82);
            _bar.Size = new Size(384, 16);
            Controls.Add(_bar);

            Shown += delegate { BeginStartup(); };
            FormClosing += OnFormClosing;
        }

        // ------------------------------------------------------------ startup --

        private void BeginStartup()
        {
            var worker = new Thread(RunStartup);
            worker.IsBackground = true;
            worker.Start();
        }

        private void RunStartup()
        {
            try
            {
                Log("---- launcher start ----");

                // 1. Database service.
                if (!IsPortOpen(_cfg.DbPort))
                {
                    if (!Directory.Exists(_cfg.Abs(_cfg.DataDir)) ||
                        !Directory.Exists(Path.Combine(_cfg.Abs(_cfg.DataDir), "mysql")))
                    {
                        SetStatus("First run - preparing the database (about a minute) ...");
                        InitDataDir();
                    }
                    SetStatus("Starting database service ...");
                    StartMariaDb();
                    _startedDb = true;
                }
                if (!WaitFor(delegate { return IsPortOpen(_cfg.DbPort); }, 60))
                    throw new FriendlyError(
                        "The database service could not start.\n\n" +
                        "Please restart the computer and try again. If the problem stays, " +
                        "contact support and send the file:\n" + LogPath());

                // 2. Database schema / migrations (safe on every start).
                SetStatus("Checking the database ...");
                var install = RunPhpCli("bin\\install.php", 120);
                if (install != 0)
                    throw new FriendlyError(
                        "The database could not be prepared.\n\nContact support and send the file:\n" + LogPath());

                // 3. Daily automatic backup (best effort).
                SetStatus("Running daily backup check ...");
                RunPhpCli("bin\\backup.php --auto", 300);

                // 4. Web application.
                if (!IsPortOpen(_cfg.PhpPort))
                {
                    SetStatus("Starting Daher Phone ...");
                    StartPhpServer();
                }
                if (!WaitFor(delegate { return HttpAlive(); }, 30))
                    throw new FriendlyError(
                        "The application did not answer.\n\n" +
                        "Another program may be using port " + _cfg.PhpPort + ". " +
                        "Contact support and send the file:\n" + LogPath());

                // 5. Open the browser and move to the tray.
                SetStatus("Opening Daher Phone ...");
                Process.Start(_cfg.AppUrl);
                Invoke((MethodInvoker)MoveToTray);
                Log("startup complete");
            }
            catch (FriendlyError fe)
            {
                Log("FRIENDLY ERROR: " + fe.Message.Replace("\n", " | "));
                ShowErrorAndExit(fe.Message);
            }
            catch (Exception ex)
            {
                Log("ERROR: " + ex);
                ShowErrorAndExit(
                    "Something unexpected went wrong while starting Daher Phone.\n\n" +
                    "Contact support and send the file:\n" + LogPath());
            }
        }

        // ------------------------------------------------------------ services --

        private void InitDataDir()
        {
            Directory.CreateDirectory(_cfg.Abs(_cfg.DataDir));
            var tool = Path.Combine(_cfg.Abs(_cfg.DbDir), "bin\\mysql_install_db.exe");
            if (!File.Exists(tool))
                throw new FriendlyError("The database installer is missing:\n" + tool);

            var psi = new ProcessStartInfo();
            psi.FileName = tool;
            psi.Arguments = "--datadir=\"" + _cfg.Abs(_cfg.DataDir) + "\"";
            psi.WorkingDirectory = _cfg.Abs(_cfg.DbDir);
            psi.CreateNoWindow = true;
            psi.UseShellExecute = false;
            using (var p = Process.Start(psi))
            {
                if (!p.WaitForExit(180000) || p.ExitCode != 0)
                    throw new FriendlyError("Preparing the database files failed (code " + p.ExitCode + ").");
            }
        }

        private void StartMariaDb()
        {
            var exe = Path.Combine(_cfg.Abs(_cfg.DbDir), "bin\\mysqld.exe");
            if (!File.Exists(exe))
                throw new FriendlyError("The database program is missing:\n" + exe);

            var psi = new ProcessStartInfo();
            psi.FileName = exe;
            psi.Arguments =
                "--no-defaults --console" +
                " --port=" + _cfg.DbPort +
                " --bind-address=127.0.0.1" +
                " --datadir=\"" + _cfg.Abs(_cfg.DataDir) + "\"" +
                " --innodb_buffer_pool_size=64M" +
                " --max_connections=40";
            psi.CreateNoWindow = true;
            psi.UseShellExecute = false;
            Process.Start(psi);
        }

        /**
         * Portable PHP: the ini sits next to php.exe (found automatically),
         * but extension_dir must be absolute — PHP resolves relative values
         * against the process working directory, which is the app folder.
         */
        private string PhpBaseArgs()
        {
            return "-d extension_dir=\"" + Path.Combine(_cfg.Abs(_cfg.PhpDir), "ext") + "\" ";
        }

        private void StartPhpServer()
        {
            var exe = Path.Combine(_cfg.Abs(_cfg.PhpDir), "php.exe");
            if (!File.Exists(exe))
                throw new FriendlyError("The PHP program is missing:\n" + exe);

            var psi = new ProcessStartInfo();
            psi.FileName = exe;
            psi.Arguments = PhpBaseArgs() +
                "-S 127.0.0.1:" + _cfg.PhpPort +
                " -t \"" + Path.Combine(_cfg.Abs(_cfg.AppDir), "public") + "\"";
            psi.WorkingDirectory = _cfg.Abs(_cfg.AppDir);
            psi.CreateNoWindow = true;
            psi.UseShellExecute = false;
            _php = Process.Start(psi);
        }

        private int RunPhpCli(string scriptAndArgs, int timeoutSeconds)
        {
            var exe = Path.Combine(_cfg.Abs(_cfg.PhpDir), "php.exe");
            var psi = new ProcessStartInfo();
            psi.FileName = exe;
            psi.Arguments = PhpBaseArgs() + scriptAndArgs;
            psi.WorkingDirectory = _cfg.Abs(_cfg.AppDir);
            psi.CreateNoWindow = true;
            psi.UseShellExecute = false;
            psi.RedirectStandardOutput = true;
            psi.RedirectStandardError = true;
            using (var p = Process.Start(psi))
            {
                var stdout = p.StandardOutput.ReadToEnd();
                var stderr = p.StandardError.ReadToEnd();
                if (!p.WaitForExit(timeoutSeconds * 1000))
                {
                    try { p.Kill(); } catch { }
                    Log("php cli TIMEOUT: " + scriptAndArgs);
                    return -1;
                }
                if (stdout.Trim().Length > 0) Log("php " + scriptAndArgs + " :: " + stdout.Trim().Replace("\r\n", " | "));
                if (stderr.Trim().Length > 0) Log("php stderr :: " + stderr.Trim().Replace("\r\n", " | "));
                return p.ExitCode;
            }
        }

        private void StopServices()
        {
            try { if (_php != null && !_php.HasExited) _php.Kill(); }
            catch { }

            if (_startedDb)
            {
                try
                {
                    var admin = Path.Combine(_cfg.Abs(_cfg.DbDir), "bin\\mysqladmin.exe");
                    var psi = new ProcessStartInfo();
                    psi.FileName = admin;
                    psi.Arguments = "--port=" + _cfg.DbPort + " --host=127.0.0.1 -u root shutdown";
                    psi.CreateNoWindow = true;
                    psi.UseShellExecute = false;
                    using (var p = Process.Start(psi)) { p.WaitForExit(20000); }
                }
                catch { }
            }
        }

        // ------------------------------------------------------------ tray & UI --

        private void MoveToTray()
        {
            _tray = new NotifyIcon();
            _tray.Icon = Icon != null ? Icon : SystemIcons.Application;
            _tray.Text = "Daher Phone is running";
            _tray.Visible = true;

            var menu = new ContextMenu();
            menu.MenuItems.Add("Open Daher Phone", delegate { try { Process.Start(_cfg.AppUrl); } catch { } });
            menu.MenuItems.Add("-");
            menu.MenuItems.Add("Exit (stop services)", delegate { ExitFromTray(); });
            _tray.ContextMenu = menu;
            _tray.DoubleClick += delegate { try { Process.Start(_cfg.AppUrl); } catch { } };

            Hide();
            ShowInTaskbar = false;
        }

        private void ExitFromTray()
        {
            _exiting = true;
            if (_tray != null) _tray.Visible = false;
            StopServices();
            Application.Exit();
        }

        private void OnFormClosing(object sender, FormClosingEventArgs e)
        {
            // Closing the splash before startup finished = user wants out.
            if (!_exiting && _tray == null)
            {
                StopServices();
            }
        }

        private void ShowErrorAndExit(string message)
        {
            try
            {
                Invoke((MethodInvoker)delegate
                {
                    MessageBox.Show(this, message, "Daher Phone",
                        MessageBoxButtons.OK, MessageBoxIcon.Error);
                    StopServices();
                    Application.Exit();
                });
            }
            catch { }
        }

        private void SetStatus(string text)
        {
            Log(text);
            try { Invoke((MethodInvoker)delegate { _status.Text = text; }); }
            catch { }
        }

        // ------------------------------------------------------------ helpers --

        private static bool WaitFor(Func<bool> condition, int seconds)
        {
            var until = DateTime.UtcNow.AddSeconds(seconds);
            while (DateTime.UtcNow < until)
            {
                if (condition()) return true;
                Thread.Sleep(500);
            }
            return condition();
        }

        private bool IsPortOpen(int port)
        {
            try
            {
                using (var client = new TcpClient())
                {
                    var async = client.BeginConnect("127.0.0.1", port, null, null);
                    if (!async.AsyncWaitHandle.WaitOne(700)) return false;
                    client.EndConnect(async);
                    return true;
                }
            }
            catch { return false; }
        }

        private bool HttpAlive()
        {
            try
            {
                var req = (HttpWebRequest)WebRequest.Create(_cfg.AppUrl + "?r=auth/login");
                req.Timeout = 3000;
                using (var resp = (HttpWebResponse)req.GetResponse())
                {
                    return resp.StatusCode == HttpStatusCode.OK;
                }
            }
            catch { return false; }
        }

        private string LogPath()
        {
            var dir = _cfg.Abs(_cfg.LogsDir);
            Directory.CreateDirectory(dir);
            return Path.Combine(dir, "launcher.log");
        }

        private void Log(string message)
        {
            try
            {
                File.AppendAllText(LogPath(),
                    DateTime.Now.ToString("yyyy-MM-dd HH:mm:ss") + "  " + message + Environment.NewLine);
            }
            catch { }
        }
    }

    internal sealed class FriendlyError : Exception
    {
        public FriendlyError(string message) : base(message) { }
    }
}
