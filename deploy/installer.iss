; ============================================================================
; Daher Phone - Windows installer (Inno Setup 6).
;
; Compile:  ISCC.exe /DMyAppVersion=1.3.0 deploy\installer.iss
;           (deploy\build-installer.ps1 does this automatically)
;
; Produces: deploy\build\DaherPhoneSetup.exe
;
; Design decisions:
;   - Installs to C:\Daher Phone by default (changeable). The package contains
;     a database and receives daily backups, so it must live in a writable
;     location - not under Program Files.
;   - Upgrades: same AppId => installing a newer setup on top upgrades in
;     place. server.ini and Application\config\app.ini are only written when
;     missing, and the Database/Backups folders are never part of [Files],
;     so customer data and settings survive every upgrade.
;   - Uninstall removes the program but LEAVES Database, Backups and Logs -
;     deleting a shop's sales history must be a deliberate manual act.
; ============================================================================

#ifndef MyAppVersion
  #define MyAppVersion "1.3.0"
#endif
#define MyAppName "Daher Phone"
#define MyStage "build\Daher Phone"

[Setup]
AppId={{7D1E8F04-2B7A-4E5C-9C21-DAHERPHONE01}
AppName={#MyAppName}
AppVersion={#MyAppVersion}
AppVerName={#MyAppName} {#MyAppVersion}
AppPublisher={#MyAppName}
DefaultDirName={sd}\Daher Phone
DefaultGroupName={#MyAppName}
DisableProgramGroupPage=yes
OutputDir=build
OutputBaseFilename=DaherPhoneSetup
Compression=lzma2
SolidCompression=yes
WizardStyle=modern
PrivilegesRequired=admin
UninstallDisplayName={#MyAppName}
UninstallDisplayIcon={app}\DaherPhone.exe
SetupIconFile=launcher\app.ico
ArchitecturesInstallIn64BitMode=x64compatible
CloseApplications=yes

[Languages]
Name: "english"; MessagesFile: "compiler:Default.isl"

[Files]
; Server + application + launcher. Data folders are created below instead.
Source: "{#MyStage}\Server\*"; DestDir: "{app}\Server"; \
    Flags: recursesubdirs createallsubdirs ignoreversion
Source: "{#MyStage}\Application\*"; DestDir: "{app}\Application"; \
    Excludes: "config\app.ini"; Flags: recursesubdirs createallsubdirs ignoreversion
Source: "{#MyStage}\DaherPhone.exe"; DestDir: "{app}"; Flags: ignoreversion

; Configuration: written once, preserved on upgrades and updates.
Source: "{#MyStage}\Application\config\app.ini"; \
    DestDir: "{app}\Application\config"; Flags: onlyifdoesntexist uninsneveruninstall
Source: "{#MyStage}\server.ini"; DestDir: "{app}"; \
    Flags: onlyifdoesntexist uninsneveruninstall

[Dirs]
; The installer runs elevated, so {app} would be admin-owned - but the app is
; launched by a NORMAL user afterwards, and MariaDB/logs/updates all write
; inside {app}. Grant Users modify on the whole tree (single-machine POS).
Name: "{app}"; Permissions: users-modify
; Data folders - never touched by upgrades, never removed by the uninstaller.
Name: "{app}\Database"; Flags: uninsneveruninstall; Permissions: users-modify
Name: "{app}\Backups"; Flags: uninsneveruninstall; Permissions: users-modify
Name: "{app}\Updates"; Flags: uninsneveruninstall; Permissions: users-modify
Name: "{app}\Logs"; Flags: uninsneveruninstall; Permissions: users-modify
Name: "{app}\Application\storage\logs"; Flags: uninsneveruninstall

[Icons]
Name: "{autodesktop}\{#MyAppName}"; Filename: "{app}\DaherPhone.exe"; \
    WorkingDir: "{app}"; Comment: "Open {#MyAppName}"
Name: "{group}\{#MyAppName}"; Filename: "{app}\DaherPhone.exe"; WorkingDir: "{app}"

[Run]
Filename: "{app}\DaherPhone.exe"; Description: "Start {#MyAppName} now"; \
    Flags: postinstall nowait skipifsilent

[UninstallRun]
; Stop services politely before removal (ignore failures - they may be stopped).
Filename: "{app}\Server\MariaDB\bin\mysqladmin.exe"; \
    Parameters: "--port=3307 --host=127.0.0.1 -u root shutdown"; \
    Flags: runhidden waituntilterminated skipifdoesntexist; RunOnceId: "StopDb"
