# IPSymconAutomowerConnect

[![Version](https://img.shields.io/badge/Symcon_Version-5.0+-red.svg)](https://www.symcon.de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/)
![Version](https://img.shields.io/badge/Modul_Version-1.12-blue.svg)
![Version](https://img.shields.io/badge/Code-PHP-blue.svg)
[![License](https://img.shields.io/badge/License-CC%20BY--NC--SA%204.0-green.svg)](https://creativecommons.org/licenses/by-nc-sa/4.0/)
[![StyleCI](https://github.styleci.io/repos/136723075/shield?branch=master)](https://github.styleci.io/repos/136723075)

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

Es wird die REST-Schnittstelle des Husqvarna Connect-Moduls verwendet. Für diese liegt keine Dokumentation vor, sodaß nicht alle der Funktionen der APP abgebildet werden konnten.
Ich habe die unten angegebenen Quellen verwendet.

## 2. Voraussetzungen

 - IP-Symcon ab Version 5<br>
   Version 4.4 mit Branch _ips_4.4_ (nur noch Fehlerkorrekturen)
 - Husqvarna Automower mit Connect-Modul

## 3. Installation

### a. Laden des Moduls

Die Konsole von IP-Symcon öffnen. Im Objektbaum unter Kerninstanzen die Instanz __*Modules*__ durch einen doppelten Mausklick öffnen.

In der _Modules_ Instanz rechts oben auf den Button __*Hinzufügen*__ drücken.

In dem sich öffnenden Fenster folgende URL hinzufügen:

`https://github.com/demel42/IPSymconAutomowerConnect.git`

und mit _OK_ bestätigen.

Anschließend erscheint ein Eintrag für das Modul in der Liste der Instanz _Modules_

### b. Einrichtung in IPS

In IP-Symcon zuerst _Konfigurator Instanzen_ den Konfigurator _AutomowerConnect Konfigurator_ hinzufügen.
Hier die Zugangsdaten eintragen, _Übernehmen_ und dann kann man in der Auswahlbox einen der mit diesem Account verknüpften Mäher auswählen und mit _Importiern_ eine Instanz anlegen.

Die so erzeugte Instanz enthält neben den Zugangsdaten die interne Geräte-ID.

## 4. Funktionsreferenz

### zentrale Funktion

`boolean AutomowerDevice_ParkMower(integer $InstanzID)`<br>
Parken des Mähers in der Ladestation

`boolean AutomowerDevice_StartMower(integer $InstanzID)`<br>
Starten eines manuellen Mähvorgangs

`boolean AutomowerDevice_StopMower(integer $InstanzID)`<br>
Stoppen der Aktivität der Mähers

`string AutomowerDevice_GetRawData(integer $InstanceID, string $Name)`<br>
Liefert interne Datenstrukturen. Beistpiel-Script siehe `docs/docs/GetRawData2GoogelMaps.php`.

| Name          | Beschreibung |
| :------------ | :----------- |
| LastLocations | mit dem Status werden die letzten 50 GPS-Positionen geliefert |

## 5. Konfiguration:

### Variablen

| Eigenschaft              | Typ     | Standardwert | Beschreibung |
| :----------------------- | :-----  | :----------- | :----------- |
| Instanz ist deaktiviert  | boolean | false        | Instanz temporär deaktivieren |
|                          |         |              | |
| Benutzer                 | string  |              | Husqvarna-Benutzer |
| Passwort                 | string  |              | Passwort des Benutzers |
|                          |         |              | |
| nur _*AutomowerDevice*_  |         |              | |
| Geräte-ID                | string  |              | interne Geräte-ID |
| Modell                   | string  |              | Modell |
|                          |         |              | |
| mit GPS-Daten            | boolean | false        | Gerät schickt GPS-Daten |
| Position speichern       | boolean | false        | Position in der Variablen 'Position' speichern |
|                          |         |              | |
| Aktualisiere Daten ...   | integer | 1            | Aktualisierungsintervall, Angabe in Minuten |


##

| Bezeichnung              | Beschreibung |
| :----------------------- | :----------- |
| Zugangsdaten überprüfen  | Testet die Zugangsdaten und gibt ggfs Accout-Details aus |
| nur _*AutomowerConfig*_  | |
| Import des Rasenmähers   | Anlage einer _AutomowerDevice_-Instanz |
| nur _*AutomowerDevice*_  | |
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
| -1   | Fehler |
|  0   | ausser Betrieb |
|  1   | geparkt |
|  2   | lädt |
|  3   | pausiert |
|  4   | fährt |
|  5   | mäht |

Es ist damit z.B. egal, ob der Mähvorgang vom Timer ausgelöst wurde oder manuell.
Das kann man dann leicht in einem Diagramm darstellen bzw. als Basis für Berechnungen verwenden.

in _Position_ wird die akuelle Positon gespeichert; es werden Longitude und Latitude als json-encodeded String abgelegt. Wenn die Variable protokolliert wird, können damit längerfristig die Weg des Mähers dargestellt werden.
Beistpiel-Script siehe `docs/docs/Position2GoogelMaps.php`.

### Variablenprofile

Es werden folgende Variableprofile angelegt:
* Boolean<br>
  - Automower.Connection

* Integer<br>
  - Automower.Error: enthält die (unvollständige) Umsetzung der Fehlercodes vom Automower.
  - Automower.Action, Automower.Activity, Automower.Battery, Automower.Duration

* Float<br>
  - Automower.Location

## 6. Anhang

GUIDs
- Modul: `{5D3A5F03-B872-4C4F-802C-65A654A7772C}`
- Instanzen:
  - AutomowerConnectConfig: `{664A5A69-6171-481A-BCB7-1CACDE4BF50D}`
  - AutomowerConnectDevice: `{B64D5F1C-6F12-474B-8DBC-3B263E67954E}`

Quellen:
  - https://github.com/chrisz/pyhusmow
  - https://github.com/krannich/dkFHEM/blob/master/FHEM/74_HusqvarnaAutomower.pm
  - https://github.com/rannmann/node-husqvarna-automower/blob/master/HMower.js

## 7. Versions-Historie

- 1.12 @ 25.04.2019 16:20<br>
  - Konfigurator-Dialog abgesichert

- 1.11 @ 25.04.2019 10:32<br>
  - Schreibfehler korrigiert

- 1.10 @ 23.04.2019 17:08<br>
  - Konfigurator um Sicherheitsabfrage ergänzt

- 1.9 @ 29.03.2019 16:19<br>
  - SetValue() abgesichert

- 1.8 @ 20.03.2019 14:56<br>
  - Anpassungen IPS 5, Abspaltung von Branch _ips_4.4_
  - Schalter, um eine Instanz (temporär) zu deaktivieren

- 1.7 @ 23.01.2019 18:18<br>
  - curl_errno() abfragen

- 1.6 @ 22.12.2018 11:19<br>
  - Fehler in der http-Kommunikation nun nicht mehr mit _echo_ (also als **ERROR**) sondern mit _LogMessage_ als **NOTIFY**

- 1.5 @ 21.12.2018 13:10<br>
  - Standard-Konstanten verwenden

- 1.4 @ 02.12.2018 11:07<br>
  - Variablenprofil _Automower.Connection_ für _Connected_

- 1.3 @ 27.11.2018 16:40<br>
  - OperatingMode _Home_ darstellen

- 1.2 @ 26.09.2018 18:54<br>
  - Fix zu v1.1 (Fehlermeldung bei Aufruf von ApplyChanges())
  - (alle) Fehlercodes als Text hinterlegt<br>
    Hinweis: damit das Profil _Automower.Error_ ergänzt wird, das Profil vor dem Update löschen

- 1.1 @ 17.09.2018 17:54<br>
  - Versionshistorie dazu,
  - define's der Variablentypen,
  - Schaltfläche mit Link zu README.md im Konfigurationsdialog

- 1.0 @ 25.06.2018 16:59<br>
  Initiale Version
