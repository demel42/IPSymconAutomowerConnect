# IPSymconAutomowerConnect

[![Version](https://img.shields.io/badge/Symcon_Version-6.0+-red.svg)](https://www.symcon.de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/)
![Version](https://img.shields.io/badge/Code-PHP-blue.svg)
[![License](https://img.shields.io/badge/License-CC%20BY--NC--SA%204.0-green.svg)](https://creativecommons.org/licenses/by-nc-sa/4.0/)

## Dokumentation

**Inhaltsverzeichnis**

1. [Funktionsumfang](#1-funktionsumfang)
2. [Voraussetzungen](#2-voraussetzungen)
3. [Installation](#3-installation)
4. [Funktionsreferenz](#4-funktionsreferenz)
5. [Konfiguration](#5-konfiguration)
6. [Anhang](#6-anhang)
7. [Versions-Historie](#7-versions-historie)

## 1. Funktionsumfang

 - Übernahme von eineigen Status-Informationen zu einem Husqvarna Automower mit Connect-Modul.
 - Senden von Befehlen zum Starten, Stoppen und Parken des Mährobotors.

## 2. Voraussetzungen

 - IP-Symcon ab Version 6.0
 - Husqvarna Automower mit Connect-Modul
 - aktives IP-Symcon Connect oder eigenen Anwendungs-Schlüssel

## 3. Installation

### a. Installation des Moduls

Im [Module Store](https://www.symcon.de/service/dokumentation/komponenten/verwaltungskonsole/module-store/) ist das Modul unter dem Suchbegriff *Husqvarna AutomowerConnect* zu finden.<br>
Alternativ kann das Modul über [Module Control](https://www.symcon.de/service/dokumentation/modulreferenz/module-control/) unter Angabe der URL `https://github.com/demel42/IPSymconAutomowerConnect` installiert werden.

### b. Einrichtung in IPS

In IP-Symcon nun unterhalb von _Splitter Instanzen_ die Funktion _Instanz hinzufügen_ auswählen und als Hersteller _Husqvarna_ angeben und _Automower Splitter_ auswählen.

Nun muss man im Splitter-Modul den Verbindungstyp auswählen

#### über IP-Symcon Connect
_Anmelden bei Husqvarna_ auswählen und auf der Husqvarna Login-Seite Benutzernamen und Passwort eingeben.

#### mit Husqvarna Anwendungs-Schlüssel
Dazu muss man sich bei Husqvarna [hier](https://developer.husqvarnagroup.cloud/apps) anmelden, dazu die Anmeldedaten in der Husqvarna-App verwenden…
Dann _My Applications_ anwählen, _Create Application_ auswählen. Als _Connected APIs_ sowohl _Authentication API_ also auch _Automower Connect API_ hinzufügen.
Siehe auch [hier](https://developer.husqvarnagroup.cloud/docs/getting-started).<br>
Den erzeugten _API-Key_ dann mit den Anmeldedaten in der Splitter-Instanz eintragen.

Es wird automatisch eine I/O-Instanz vom Typ _WebSocket-Client_ angelegt; über diese Instanz werden die Informationen, die der Mäher an die Husqvarna-Cloud meldet, weiter geleitet.

Nun in IP-Symcon in _Konfigurator Instanzen_ den Konfigurator _AutomowerConnect Konfigurator_ hinzufügen; dann kann man über den Konfigurator eine Instanz anlegen.

Die so erzeugte Geräte-Instanz enthält neben der Serienummer die interne Geräte-ID.

## 4. Funktionsreferenz

### zentrale Funktion

`string AutomowerConnect_GetRawData(integer $InstanceID, string $Name)`<br>
Liefert interne Datenstrukturen. Beispiel-Script siehe [docs/GetRawData2GoogelMaps.php](docs/GetRawData2GoogelMaps.php).

| Name          | Beschreibung |
| :------------ | :----------- |
| LastLocations | mit dem Status werden die letzten 50 GPS-Positionen geliefert |

`AutomowerConnect_SetUpdateInterval(integer $InstanceID, int $Minutes)`<br>
ändert das Aktualisierumgsintervall; eine Angabe von **null** setzt auf den in der Konfiguration vorgegebene Wert zurück.
Es gibt hierzu auch zwei Aktionen (Setzen und Zurücksetzen).

Der Zugriff auf die Steuerung erfolgt per [RequestAction](https://www.symcon.de/service/dokumentation/befehlsreferenz/variablenzugriff/requestaction/),
dabei sind die Variablen mit den folgenden Idents besonders wichtig

#### MowerActionStart
Starten eines manuellen Mähvorgangs. _Value_ bedeutet
| Wert | Bedeutung |
| :--- | :-------- |
| 0    | nächster Zeitplan |
| 1..  | Dauer in Stunden, vorgegeben im Variablenprofil sind 3h (=3), 12h (=12), 1d (=24) ... |

#### MowerActionPark
Parken des Mähers in der Ladestation. _Value_ bedeutet
| Wert | Bedeutung |
| :--- | :-------- |
| -1   | bis auf weiteres |
| 0    | mit Zeitplan starten |
| 1..  | Dauer in Stunden, vorgegeben im Variablenprofil sind 3h (=3), 12h (=12), 1d (=24) ... |

#### MowerActionPause
Unterbrechen der Aktivität der Mähers, _Value_ ist irrelevant

#### CuttingHeight
Einstellen der Schnitthöhe. _Value_ kann den Wert von 1 .. 9 annehmen

#### HeadlightMode
Einstellen der Scheinwerfer. _Value_ bedeutet
| Wert | Bedeutung |
| :--- | :-------- |
| 0    | Immer an |
| 1    | Immer aus |
| 2    | Nur abends |
| 3    | Abends und nachts |

## 5. Konfiguration:

## AutomowerConnectSplitter

### Einstellungen

| Eigenschaft                  | Typ     | Standardwert | Beschreibung |
| :--------------------------- | :-----  | :----------- | :----------- |
| Instanz ist deaktiviert      | boolean | false        | Instanz temporär deaktivieren |
|                              |         |              | |
| Verbindungstyp               | integer |              | IP-Symcon-Connect oder Anwendungsschlüssel |

| Application key              | string  |              | aus dem Huyqvarna-Konto |
| Application secret           | string  |              | aus dem Huyqvarna-Konto |
| Benutzerkennung              | string  |              | Mail-Adresse |
| Passwort                     | string  |              | |

### Aktionen

| Bezeichnung              | Beschreibung |
| :----------------------- | :----------- |
| Zugangsdaten überprüfen  | Testet die Zugangsdaten und gibt ggfs Accout-Details aus |

## AutomowerConnectDevice

### Einstellungen

| Eigenschaft                  | Typ     | Standardwert | Beschreibung |
| :--------------------------- | :-----  | :----------- | :----------- |
| Instanz ist deaktiviert      | boolean | false        | Instanz temporär deaktivieren |
|                              |         |              | |
| Seriennummer                 | string  |              | Seriennummer |
| Modell                       | string  |              | Modell |
| Geräte-ID                    | string  |              | interne Geräte-ID |
|                              |         |              | |
| mit GPS-Daten                | boolean | false        | Gerät schickt GPS-Daten |
| Position speichern           | boolean | false        | Position in der geloggten Variablen 'Position' speichern |
|                              |         |              | |
| mit Schnitthöhen-Verstellung | boolean | true         | Möglichkeit, die Schnitthöhe zu sehen und anzupassen |
| mit Scheinwerfer-Einstellung | boolean | true         | Möglichkeit, das Verhalten der Scheinwerfer zu sehen und anzupassen |
|                              |         |              | |
| Statistik-Daten speichern    | boolean | true         | diverse Nutzungs-Informationen in geloggten Variablen speichern |
|                              |         |              | |
| Aktualisiere Daten ...       | integer | 1            | Aktualisierungsintervall, Angabe in Minuten _[1]_ |

_[1]_: das Abruf-Intervall sollte so lang, wie möglich sein, da die Anzahl der API-Abrufe strikt limitiert ist. Über die WebSocket werde alle Werte von der Husqvarna-Cloud übertragen, sobald der Mäher Werte an die Cloud übertragen hat. Insofern ist ein zyklischer Abruf nur erforderlich/sinnvoll, um die Statistikdaten abzurufen oder wenn die WebSocket-Verbindung mal nicht zur Verfügung gestanden hat.

### Aktionen

| Bezeichnung              | Beschreibung |
| :----------------------- | :----------- |
| Aktualisiere Status      | Status des Rasenmähers abrufen |

### Statusvariablen

folgende Variable werden angelegt, zum Teil optional

| Name          | Typ            | Beschreibung |
| :------------ | :------------- | :----------- |
| Connected     | boolean        | Verbindungsstatus des Mähers mit Husqvarna |
| Battery       | integer        | Ladekapazität der Batterie |
| OperationMode | string         | Betriebsart |
| MowerStatus   | string         | Status des Mähers |
| MowerActivity | integer        | aktuelle Aktivität des Mähers |
| MowerAction   | integer        | Start einer Aktivität |
| NextStart     | UNIX-Timestamp | nächster geplanter Start |
| LastLongitude | integer        | letzter Längengrad |
| LastLatitude  | integer        | letzter Breitengrad |
| LastStatus    | UNIX-Timestamp | letzte Status-Abfrage |
| Position      | string         | letzte Position (longitude, latitude, activity) |
| Schnitthöhe   | integer        | Schnitthöhe in mm |
| Scheinwerfer  | integer        | Verhalten der Scheinwerfer |

In _MowerActivity_ werden die diversen _MowerStatus_ in die Haupt-Aktivitäten gruppiert und als Integer abgelegt:

| Wert | Beschreibung |
| :--- | :----------- |
| 0    | unbekannter Status |
| 1    | nicht zutreffend |
| 2    | Fehler |
| 3    | ausser Betrieb |
| 4    | geparkt |
| 5    | lädt |
| 6    | pausiert |
| 7    | verlässt Ladestation |
| 8    | fährt zur Ladestation |
| 9    | mäht |
| 10   | gestoppt |

Es ist damit z.B. egal, ob der Mähvorgang vom Timer ausgelöst wurde oder manuell.
Das kann man dann leicht in einem Diagramm darstellen bzw. als Basis für Berechnungen verwenden.

in _Position_ wird die akuelle Positon gespeichert; es werden Longitude und Latitude sowie der Wert die aktielle Aktivität als json-encodeded String abgelegt. Wenn die Variable protokolliert wird, können damit längerfristig die Weg des Mähers dargestellt werden.
Beispiel-Script siehe [docs/Position2GoogelMaps.php](docs/Position2GoogelMaps.php).

### Variablenprofile

Es werden folgende Variableprofile angelegt:
* Boolean<br>
Automower.Connection

* Integer<br>
Automower.Error (enthält die Umsetzung der Fehlercodes vom Automower)<br>
Automower.ActionPark,
Automower.ActionPause,
Automower.ActionStart,
Automower.Activity,
Automower.Battery,
Automower.CuttingHeight,
Automower.Duration,
Automower.HeadlightMode,
Automower.State,
Automower.Time

* Float<br>
Automower.Location

## 6. Anhang

GUIDs
- Modul: `{5D3A5F03-B872-4C4F-802C-65A654A7772C}`
- Instanzen:
  - AutomowerConnectSplitter: `{AEEFAA3E-8802-086D-6620-E971C03CBEFC}`
  - AutomowerConnectConfig: `{664A5A69-6171-481A-BCB7-1CACDE4BF50D}`
  - AutomowerConnectDevice: `{B64D5F1C-6F12-474B-8DBC-3B263E67954E}`
- Nachrichten
  - `{4C746488-C0FD-A850-3532-8DEBC042C970}`: an AutomowerConnectSplitter
  - `{277691A0-EF84-1883-2094-45C56419748A}`: an AutomowerConnectConfig, AutomowerConnectDevice
  - `{D62B246F-6BE0-9D6C-C415-FD12560D70C9}`: an AutomowerConnectDevice

Quellen:
  - https://developer.husqvarnagroup.cloud/apis/Automower+Connect+API

## 7. Versions-Historie

- 3.7 @ 19.03.2024 18:05
  - Ergänzung: "Grund für Inaktivität" ergänzt um EXTERNAL ("Fremdsteuerung")

- 3.6 @ 07.02.2024 17:31
  - Änderung: Medien-Objekte haben zur eindeutigen Identifizierung jetzt ebenfalls ein Ident
  - Fix: Absicherung von Zugriffen auf andere Instanzen in Konfiguratoren
  - update submodule CommonStubs

- 3.5 @ 27.01.2024 11:09
  - Neu: Schalter, um Daten zu API-Aufrufen zu sammeln
    Die API-Aufruf-Daten stehen nun in einem Medienobjekt zur Verfügung
  - update submodule CommonStubs

- 3.4 @ 09.12.2023 16:52
  - Neu: ab IPS-Version 7 ist im Konfigurator die Angabe einer Import-Kategorie integriert, daher entfällt die bisher vorhandene separate Einstellmöglichkeit

- 3.3 @ 13.11.2023 11:01
  - Fix: bei dem Empfang von Daten per Websocket wird nun die Geräte-ID ausgewertet und somit nur noch vom richtigen Gerät verarbeitet.

- 3.2 @ 03.11.2023 12:40
  - Fix: bei der Anlage einer Splitter-Instanz wurde u.U. eine bereits vorhandene Websocket-Instanze verwendet
  - update submodule CommonStubs

- 3.1 @ 15.10.2023 13:51
  - Neu: Ermittlung von Speicherbedarf und Laufzeit (aktuell und für 31 Tage) und Anzeige im Panel "Information"
  - Fix: die Statistik der ApiCalls wird nicht mehr nach uri sondern nur noch host+cmd differenziert
  - update submodule CommonStubs

- 3.0 @ 05.07.2023 17:02
  - Neu: unlimited Symcon-API-Key (bei Login via OAuth mittels SymconConnect)
  - Neu: Benutzung der WebSocket-Schnittstelle von Husqvarna. Hierüber werden alle Änderungsmeldung des Mähers umgehend empfangen ohne zyklischen Datenabruf!
    Ein aktiver Abruf ist nur noch unter bestimmten Umständen sinnvoll und kann daher auf ein langes Intervall gesetzt werden.
    Diese Änderung erfordert, das die AutomowerConnect-I/O-Instanz nun als Splitter-Instanz geführt wird; das erfolgt beim Modul-Update automatisch.

	Nach der Durchfühung des Modul-Updates dann in der AutomowerConnect-Splitter-Instanz (frühere I/O), einmalig "Zugriff testen" aufrufen;
	dadurch wird die Schnittstelle (WebSocket-Client) korrekt parametriert und geöffnet.

	Wichtiger Hinweis: die AutomowerConnect-Splitter-Instanz wird fehlerhafterweise noch im Ordner "I/O Instanzen" angezeigt, das ist ein reines
	Darstellungsproblem der Symcon-Console; einfach die Console neu öffnen. Die Automower-Splitter-Instanz kann in dem Zuge auch umbenannt werden.
  - Geändert: Variable "MowerStatus" vom Typ string wird ersetzt durch die Variable "MowerState" vom Typ int
    Eine eventuelle Nutzung der Variable in Scripten etc muss händisch nachgeführt werden.
  - Neu: Schalter, um die Meldung eines inaktiven Gateway zu steuern
  - Vorbereitung auf IPS 7 / PHP 8.2
  - update submodule CommonStubs
    - Absicherung bei Zugriff auf Objekte und Inhalte

- 2.9.2 @ 04.03.2023 17:00
  - Fix: Befehle an den Mäher wurden mit einem Fehler quittiert

- 2.9.1 @ 11.01.2023 15:44
  - Fix: Handling des Datencache abgesichert
  - update submodule CommonStubs

- 2.9 @ 21.12.2022 09:48
  - Verbesserung: Absicherung vor mehreren API-Calls/Sekunde (API lässt 1 Aufruf/Sekunde und 10000/Monat zu)
  - Neu: Führen einer Statistik der API-Calls im IO-Modul, Anzeige als Popup im Experten-Bereich
  - Neu: Verwendung der Option 'discoveryInterval' im Konfigurator (ab 6.3) zur Reduzierung der API-Calls: nur noch ein Discovery/Tag
  - Neu: Daten-Cache für Daten im Konfigurator zur Reduzierung der API-Aufrufe, wird automatisch 1/Tag oder manuell aktualisiert
  - update submodule CommonStubs

- 2.8 @ 11.11.2022 08:34
  - Neu: Option um die Einstellbarkeit der Scheinwerfer zu deaktivieren
  - Neu: Ausgabe von Language und Translation im Debug

- 2.7.4 @ 19.10.2022 09:16
  - Fix: README
  - update submodule CommonStubs

- 2.7.3 @ 12.10.2022 14:44
  - Konfigurator betrachtet nun nur noch Geräte, die entweder noch nicht angelegt wurden oder mit dem gleichen I/O verbunden sind
  - update submodule CommonStubs

- 2.7.2 @ 07.10.2022 13:59
  - update submodule CommonStubs
    Fix: Update-Prüfung wieder funktionsfähig

- 2.7.1 @ 16.08.2022 10:10
  - update submodule CommonStubs
    Fix: in den Konfiguratoren war es nicht möglich, eine Instanz direkt unter dem Wurzelverzeichnis "IP-Symcon" zu erzeugen

- 2.7 @ 28.07.2022 09:48
  - Neu: Login ist nun auch möglich unter Angabe von 'Application secret' anstelle von Benutzer und Passwort
  - update submodule CommonStubs

- 2.6.1 @ 26.07.2022 10:22
  - update submodule CommonStubs
    Fix: CheckModuleUpdate() nicht mehr aufrufen, wenn das erstmalig installiert wird

- 2.6 @ 07.07.2022 11:43
  - Verbesserung: IPS-Status wird nur noch gesetzt, wenn er sich ändert
  - Übersetzung vervollständigt (Variablenprofil "Automower.Error")
  - update submodule CommonStubs
    Fix: RegisterOAuth()

- 2.5.2 @ 22.06.2022 10:33
  - Fix: Angabe der Kompatibilität auf 6.2 korrigiert

- 2.5.1 @ 28.05.2022 11:37
  - update submodule CommonStubs
    Fix: Ausgabe des nächsten Timer-Zeitpunkts

- 2.5 @ 26.05.2022 11:56
  - update submodule CommonStubs
  - einige Funktionen (GetFormElements, GetFormActions) waren fehlerhafterweise "protected" und nicht "private"
  - interne Funktionen sind nun entweder private oder nur noch via IPS_RequestAction() erreichbar

- 2.4.6 @ 17.05.2022 15:38
  - update submodule CommonStubs
    Fix: Absicherung gegen fehlende Objekte

- 2.4.5 @ 10.05.2022 15:06
  - update submodule CommonStubs
  - SetLocation() -> GetConfiguratorLocation()
  - weitere Absicherung ungültiger ID's

- 2.4.4 @ 30.04.2022 18:40
  - Überlagerung von Translate und Aufteilung von locale.json in 3 translation.json (Modul, libs und CommonStubs)

- 2.4.3 @ 26.04.2022 12:27
  - Korrektur: self::$IS_DEACTIVATED wieder IS_INACTIVE

- 2.4.2 @ 24.04.2022 10:26
  - Übersetzung vervollständigt

- 2.4.1 @ 23.04.2022 09:35
  - Tippfehler
  - Bug in IO-Instanz (falsche GUID)

- 2.4 @ 21.04.2022 08:41
  - Schreibfehler ("FROTS" -> "FROST")
  - Implememtierung einer Update-Logik
  - diverse interne Änderungen

- 2.3.9 @ 16.04.2022 11:17
  - Aktualisierung von submodule CommonStubs

- 2.3.8 @ 15.04.2022 18:11
  - Variable "Position" um die Aktivität ergänzt

- 2.3.7 @ 14.04.2022 15:32
  - die Positionen wurden bei LEAVING nicht in die Variable "Positions" protokolliert
  - Nutzung von Attributes statt Buffer (damit reboot-fest)

- 2.3.6 @ 13.04.2022 17:17
  - Anpassung des Aktualisierungsintervalls: Korrektur von 2.3.3

- 2.3.5 @ 13.04.2022 14:28
  - Namenskonflikt (trait CommonStubs)

- 2.3.4 @ 13.04.2022 10:53
 - Beispiel-Script docs/Position2GoogelMaps.php ergänzt um 'restrict_points' und 'skip_points' (setzt akt. GoogleMaps voraus)

- 2.3.3 @ 12.04.2022 13:18
  - Korrektur zu 2.3: nach einer manuellen Auslösung blieb das Intervall auf 15s

- 2.3.2 @ 11.04.2022 11:28
  - Anpassung des Aktualisierungsintervalls (siehe AutomowerConnect_SetUpdateInterval())
  - Ausgabe der Instanz-Timer unter "Referenzen"

- 2.3.1 @ 09.04.2022 17:23
  - Konfigurator zeigt nun auch Instanzen an, die nicht mehr zu den vorhandenen Geräten passen
  - minimales Abrufintervall sind 5 Minuten
  - Korrekturen

- 2.3 @ 07.04.2022 17:50
  - alternativ zur Anmeldung via Symcon-Connect via Anwendungsschlüssel
  - Einstellung der Schnitthöhe ist nun optional

- 2.2 @ 01.04.2022 11:27
  - RestrictedReason ergänzt

- 2.1 @ 31.03.2022 15:37
  - AppKey korrigiert
  - Korrektur bei der Deaktivierung der Aktionen
  - Auswertung von planner.restrictedReason verbessert (geparkt bis auf weiteres)

- 2.0 @ 30.03.2022 14:49
  - Umstellung auf die offizielle Husqvarna-REST-API mit OAuth<br>
    Update-Hinweis:
	- vor dem Update
      - Löschen von Profil "Automower.Action", "Automower.Error" und Variable "MowerAction"
    - nach dem Update
      - I/O-Instanz einrichten
      - Konfigurator-Instanz  aufrufen, die Devises sollten alle erscheien, aber als "Prüfen" markiert sein; das durchführen
	  - in einer der Geräte-Instanzen im Expert-Bereich die "Variablenprofile erneut einrichten"
	  - Script korrigieren (Änderung der Variable _$activity_label_, siehe _docs_)
	  - Geräte-Instanz kontrollieren, Status aktualisieren ...
  - Anpassungen an IPS 6.2 (Prüfung auf ungültige ID's)
  - diverse interen Änderungen
  - Anzeige der Modul/Bibliotheks-Informationen
  - Möglichkeit der Anzeige der Instanz-Referenzen sowie referenzierte Statusvariablen

- 1.19 @ 13.07.2020 14:56
  - LICENSE.md hinzugefügt

- 1.18 @ 07.07.2020 11:41
  - Schreibfehler korrigiert

- 1.17 @ 30.04.2020 14:51
  - alle Errorcodes gemäß Tabelle von Husqvarna hinterlegt
    Hinweis: vor dem Update das Variablenprofil _Automower.Error_ löschen, damit es neu angelegt wird

- 1.16 @ 17.04.2020 17:19
  - Status PARKED_AUTOTIMER auswerten

- 1.15 @ 08.04.2020 16:29
  - define's durch statische Klassen-Variablen ersetzt
  - Einsatz des Konfigurators

- 1.14 @ 30.12.2019 10:56
  - Anpassungen an IPS 5.3
    - Formular-Elemente: 'label' in 'caption' geändert

- 1.13 @ 13.10.2019 13:18
  - Anpassungen an IPS 5.2
    - IPS_SetVariableProfileValues(), IPS_SetVariableProfileDigits() nur bei INTEGER, FLOAT
    - Dokumentation-URL in module.json
  - Umstellung auf strict_types=1
  - Umstellung von StyleCI auf php-cs-fixer

- 1.12 @ 25.04.2019 16:20
  - Konfigurator-Dialog abgesichert

- 1.11 @ 25.04.2019 10:32
  - Schreibfehler korrigiert

- 1.10 @ 23.04.2019 17:08
  - Konfigurator um Sicherheitsabfrage ergänzt

- 1.9 @ 29.03.2019 16:19
  - SetValue() abgesichert

- 1.8 @ 20.03.2019 14:56
  - Anpassungen IPS 5, Abspaltung von Branch _ips_4.4_
  - Schalter, um eine Instanz (temporär) zu deaktivieren

- 1.7 @ 23.01.2019 18:18
  - curl_errno() abfragen

- 1.6 @ 22.12.2018 11:19
  - Fehler in der http-Kommunikation nun nicht mehr mit _echo_ (also als **ERROR**) sondern mit _LogMessage_ als **NOTIFY**

- 1.5 @ 21.12.2018 13:10
  - Standard-Konstanten verwenden

- 1.4 @ 02.12.2018 11:07
  - Variablenprofil _Automower.Connection_ für _Connected_

- 1.3 @ 27.11.2018 16:40
  - OperatingMode _Home_ darstellen

- 1.2 @ 26.09.2018 18:54
  - Fix zu v1.1 (Fehlermeldung bei Aufruf von ApplyChanges())
  - (alle) Fehlercodes als Text hinterlegt<br>
    Hinweis: damit das Profil _Automower.Error_ ergänzt wird, das Profil vor dem Update löschen

- 1.1 @ 17.09.2018 17:54
  - Versionshistorie dazu
  - define's der Variablentypen
  - Schaltfläche mit Link zu README.md im Konfigurationsdialog

- 1.0 @ 25.06.2018 16:59
  - Initiale Version
