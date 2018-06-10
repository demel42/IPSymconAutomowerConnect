# sipgate

Modul für IP-Symcon ab Version 4.4

## Dokumentation

**Inhaltsverzeichnis**

1. [Funktionsumfang](#1-funktionsumfang)
2. [Voraussetzungen](#2-voraussetzungen)
3. [Installation](#3-installation)
4. [Funktionsreferenz](#4-funktionsreferenz)
5. [Konfiguration](#5-konfiguration)
6. [Anhang](#6-anhang)

## 1. Funktionsumfang

 - Übernahme von eineigen Status-Informationen zu einem Husqvarna Automower mit Connect-Modul.
 - Senden von Befehlen zum Starten, Stoppen und Parken des Mährobotors.

Es wird die REST-Schnittstelle des Husqvarna Connect-Moduls verwendet. Für diese liegt keine Dokumentation vor, sodaß nicht alle der Funktionen der APP abgebildet werden konnten.
Ich habe die unten angegebenen Quellen verwendet.

## 2. Voraussetzungen

 - IP-Symcon ab Version 4.4
 - Huysvarna Automower mit Connect-Modul

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

`boolean ParkMower(integer $InstanzID)`
Parken des Mähers in der Ladestation

`boolean StartMower(integer $InstanzID)`
Starten eines manuellen Mähvorgangs

`boolean StopMower(integer $InstanzID)`
Stoppen der Aktivität der Mähers

## 5. Konfiguration:

### Variablen

| Eigenschaft               | Typ      | Standardwert | Beschreibung |
| :-----------------------: | :-----:  | :----------: | :-------------------------------------------: |
| Benutzer                  | string   |              | Husqvarna-Benutzer |
| Passwort                  | string   |              | Passwort des Benutzers |
|                           |          |              |                        |

nur _AutomowerDevice_
| Geräte-ID                 | string   |              | interne Geräte-ID |
| Modell                    | string   |              | Modell (scheint aber nicht das konkrete Automower-Modell zu meinen, bisher nur _*G*_)  |

### Schaltflächen

| Bezeichnung                  | Beschreibung |
| :--------------------------: | :-------------------------------------------------: |
| Zugangsdaten überprüfen      | Testet die Zugangsdaten und gibt ggfs Accout-Details aus |

nur _AutomowerConfig_
| Import des Rasenmähers       | Anlage einer _AutomowerDevice_-Instanz |

nur _AutomowerDevice_
| Aktualisiere Status          | Status des Rasenmähers abrufen |

### Statusvariablen

folgende Variable werden angelegt, zum Teil optional

| Name                    | Typ            | Beschreibung                                    |
| :---------------------: | :------------: | :---------------------------------------------: |
| Connected               | boolean        | Verbindungsstatus des Mähers mit Husqvarna      |
| Battery                 | integer        | Ladekapazität der Batterie                      |
| OperationMode           | string         | Betriebsart                                     |
| MowerStatus             | string         | Status des Mähers                               |
| MowerActivity           | integer        | aktuelle Aktivität des Mähers                   |
| MowerAction             | integer        | Start einer Aktivität                           |
| NextStart               | UNIX-Timestamp | nächster geplanter Start                        |
| LastLongitude           | integer        | letzter Längengrad                              |
| LastLatitude            | integer        | letzter Breitengrad                             |
| LastStatus              | UNIX-Timestamp | letzte Status-Abfrage                           |

In _MowerActivity_ werden die diversen _MowerStatus_ in die Haupt-Aktivitäten gruppiert und als Integer abgelegt:

| Wert | Beschreibung                                    |
| :--: | :---------------------------------------------: |
| -1   | Fehler                                          |
|  0   | ausser Betrieb                                  |
|  1   | pausiert                                        |
|  2   | geparkt                                         |
|  3   | lädt                                            |
|  4   | fährt                                           |
|  5   | mäht                                            |

Es ist damit z.B. egal, ob der Mähvorgang vom Timer ausgelöst wurde oder manuell.
Das kann man dann leicht in einem Diagramm darstellen bzw. als Basis für Berechnungen verwenden.

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
