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

 - xxx

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

In IP-Symcon nun _Instanz hinzufügen_ (_CTRL+1_) auswählen unter der Kategorie, unter der man die Instanz hinzufügen will, und Hersteller _Huyqvarna_ und als Gerät _AutomowerConnect_ auswählen.

In dem Konfigurationsdialog die Zugangsdaten des Accounts eintragen.

## 4. Funktionsreferenz

### zentrale Funktion

`boolean Sipgate_GetHistory(integer $InstanzID)`

## 5. Konfiguration:

### Variablen

| Eigenschaft               | Typ      | Standardwert | Beschreibung |
| :-----------------------: | :-----:  | :----------: | :-------------------------------------------: |
| Benutzer                  | string   |              | Hysqvarna-Benutzer |
| Passwort                  | string   |              | Passwort des Benutzers |

### Schaltflächen

| Bezeichnung                  | Beschreibung |
| :--------------------------: | :-------------------------------------------------: |
| Zugangsdaten überprüfen      | Testet die Zugangsdaten und gibt Accout-Details aus |
| SMS testen                   | SMS-Funktion testen |
| Anruf-Historie abrufen       | Anruf-Historie abrufen und ausgeben |

## 6. Anhang

GUIDs
- Modul: `{5D3A5F03-B872-4C4F-802C-65A654A7772C}`
- Instanzen:
  - AutomowerConnect: `{B64D5F1C-6F12-474B-8DBC-3B263E67954E}`
