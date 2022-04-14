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

 - IP-Symcon ab Version 6.0<br>
 - Husqvarna Automower mit Connect-Modul
 - aktives IP-Symcon Connect oder eigenen Anwendungs-Schlüssel

## 3. Installation

### a. Installation des Moduls

Im [Module Store](https://www.symcon.de/service/dokumentation/komponenten/verwaltungskonsole/module-store/) ist das Modul unter dem Suchbegriff *Husqvarna AutomowerConnect* zu finden.<br>
Alternativ kann das Modul über [Module Control](https://www.symcon.de/service/dokumentation/modulreferenz/module-control/) unter Angabe der URL `https://github.com/demel42/IPSymconAutomowerConnect` installiert werden.

### b. Einrichtung in IPS

In IP-Symcon nun unterhalb von _I/O Instanzen_ die Funktion _Instanz hinzufügen_ auswählen und als Hersteller _Husqvarna_ angeben und _Automower I/O_ auswählen.

Num muss man im I/O-Modul den Verbindungstyp auswählen

#### über IP-Symcon Connect
_Anmelden bei Husqvarna_ auswählen und auf der Husqvarna Login-Seite Benutzernamen und Passwort eingeben.

#### mit Husqvarna Anwendungs-Schlüssel
Dazu muss man sich bei Husqvarna [hier](https://developer.husqvarnagroup.cloud/apps) anmelden, dazu die Anmeldedaten in der Husqvarna-App verwenden…
Dann _My Applications_ anwählen, _Create Application_ auswählen. Als _Connected APIs_ sowohl _Authentication API_ also auch _Automower Connect API_ hinzufügen.
Siehe auch [hier](https://developer.husqvarnagroup.cloud/docs/getting-started).<br>
Den erzeugten _API-Key_ dann mit den Anmeldedaten in der I/O-Instanz eintragen.

Nun in IP-Symcon in _Konfigurator Instanzen_ den Konfigurator _AutomowerConnect Konfigurator_ hinzufügen; dann kann man über den Konfigurator eine Instanz anlegen.

Die so erzeugte Geräte-Instanz enthält neben der Serienummer die interne Geräte-ID.

## 4. Funktionsreferenz

### zentrale Funktion

`string AutomowerConnect_GetRawData(integer $InstanceID, string $Name)`<br>
Liefert interne Datenstrukturen. Beispiel-Script siehe `docs/GetRawData2GoogelMaps.php`.

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

### Variablen

| Eigenschaft              | Typ     | Standardwert | Beschreibung |
| :----------------------- | :-----  | :----------- | :----------- |
| Instanz ist deaktiviert  | boolean | false        | Instanz temporär deaktivieren |
|                          |         |              | |
| Seriennummer             | string  |              | Seriennummer |
| Modell                   | string  |              | Modell |
| Geräte-ID                | string  |              | interne Geräte-ID |
|                          |         |              | |
| mit GPS-Daten            | boolean | false        | Gerät schickt GPS-Daten |
| Position speichern       | boolean | false        | Position in der Variablen 'Position' speichern |
|                          |         |              | |
| Aktualisiere Daten ...   | integer | 1            | Aktualisierungsintervall, Angabe in Minuten |


## AutomowerConnect

| Bezeichnung              | Beschreibung |
| :----------------------- | :----------- |
| Zugangsdaten überprüfen  | Testet die Zugangsdaten und gibt ggfs Accout-Details aus |
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
| Position      | string         | letzte Position (Longitude, Latitude) |

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

in _Position_ wird die akuelle Positon gespeichert; es werden Longitude und Latitude als json-encodeded String abgelegt. Wenn die Variable protokolliert wird, können damit längerfristig die Weg des Mähers dargestellt werden.
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
Automower.HeadlightMode

* Float<br>
Automower.Location


## 6. Anhang

GUIDs
- Modul: `{5D3A5F03-B872-4C4F-802C-65A654A7772C}`
- Instanzen:
  - AutomowerConnectIO: `{AEEFAA3E-8802-086D-6620-E971C03CBEFC}`
  - AutomowerConnectConfig: `{664A5A69-6171-481A-BCB7-1CACDE4BF50D}`
  - AutomowerConnectDevice: `{B64D5F1C-6F12-474B-8DBC-3B263E67954E}`
- Nachrichten
  - `{4C746488-C0FD-A850-3532-8DEBC042C970}`: an AutomowerConnectIO
  - `{277691A0-EF84-1883-2094-45C56419748A}`: an AutomowerConnectDevice

Quellen:
  - https://developer.husqvarnagroup.cloud/apis/Automower+Connect+API

## 7. Versions-Historie

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
