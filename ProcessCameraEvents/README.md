# ProcessCameraEvents
Beschreibung des Moduls.

### Inhaltsverzeichnis

1. [Funktionsumfang](#1-funktionsumfang)
2. [Voraussetzungen](#2-voraussetzungen)
3. [Software-Installation](#3-software-installation)
4. [Einrichten der Instanzen in IP-Symcon](#4-einrichten-der-instanzen-in-ip-symcon)
5. [Statusvariablen und Profile](#5-statusvariablen-und-profile)
6. [WebFront](#6-webfront)
7. [PHP-Befehlsreferenz](#7-php-befehlsreferenz)

### 1. Funktionsumfang

*HikVision Module for IP-Symcon: Functional Description
The HikVision module for IP-Symcon provides seamless integration of Hikvision cameras with the IP-Symcon home automation system. This module offers several key functionalities designed to enhance security and automation through camera event processing. Below is a detailed description of its features and operations:

Key Functionalities
IP Symcon automation of Human and Vehicle Detection through the HikVision Camera:

Hikvision cameras can be configured to detect the presence, entry, or exit of humans or vehicles in specified areas.
The camera sends alerts to an alarm center, essentially calling a webhook pointing to the IP-Symcon system to utilize this module.

ProcessCameraEvents Instance:
Install first the Egg Timer Module and then the HikVision Module using the IP Symcon Modul Store. You can the install a instance called "ProcessCameraEvents"
When an instance of "ProcessCameraEvents" is installed, it automatically sets up a webhook in IP-Symcon.
Upon detecting an event, the camera triggers the webhook, which calls the module in IP-Symcon to process the camera data.

Boolean Variable Creation:
The instance creates a boolean variable under the instance, named after the camera, to indicate the event status.
If multiple cameras trigger the webhook, each camera will have a corresponding boolean variable created under the instance.
These variables can be used to trigger custom scripts or generate motion charts in IP-Symcon.

Event Duration Configuration:
The instance allows setting a duration time during which the boolean variable remains active and no further events from the specific camera are processed, 
preventing flood of alerts from the same camera within the specified time frame.


Event Picture Storage:
Configurable path for storing event pictures (default: /user/). Ensure this path is valid and exists in the IP-Symcon installation. 
You need to set up the Password and the username of the camera to allow the picture to be downloaded

Camera Channel Configuration:
The instance allows defining the channel ID of the camera (default: 101).
Event Snapshot Download:

By providing the camera's username and password, the instance can download a picture at the time of the event.
A media file containing the event snapshot is installed under the camera's name. Make sure that you set up teh configuration
of the Web Authentication in the Camera to digest/basic

Additional Variables:
A string variable named after the camera's IP address, containing the type of event registered.
Another string variable containing the date and time of the event.


Prerequisites
Egg Timer: Before using the HikVision module, an Egg Timer must be installed from the IP-Symcon Module store. This is essential for the module's timer-based functionalities.


Usage Scenarios
Security Monitoring: Automatically trigger alerts and capture snapshots when unauthorized access or movement is detected.
Automation: Integrate with other IP-Symcon scripts to automate responses, such as turning on lights or sounding alarms when events are detected.
Data Analysis: Create motion charts and analyze patterns based on the boolean variables and event logs.


Configuration Steps
After installing the Egg Timer Module and the HikVision Modul from the IP Symcon Modul store install the ProcessCameraEvents Instance in IP-Symcon.

Configure Webhook: 
Ensure the Hikvision camera's webhook points to the IP-Symcon system.

Set Up Variables: 
Configure the camera password and user name, event duration, and storage path as per your requirements.

Download Event Snapshots: 
Enter the camera’s credentials to enable snapshot downloads at event times.

Integrate with Egg Timer: Ensure the Egg Timer is installed for proper timing and event handling.

By following these steps and utilizing the module’s functionalities, users can effectively integrate Hikvision cameras with their IP-Symcon system, enhancing their security setup and automation capabilities.








### 2. Voraussetzungen

- IP-Symcon ab Version 7.1
- HikVision Camera
- Egg Timer Module from the IP Symcon Modul Store


### 3. Software-Installation

* Über den Module Store das 'ProcessCameraEvents'-Modul installieren.
* Alternativ über das Module Control folgende URL hinzufügen
* Egg Timer Module from the IP Symcon Modul Store 

### 4. Einrichten der Instanzen in IP-Symcon

 Unter 'Instanz hinzufügen' kann das 'ProcessCameraEvents'-Modul mithilfe des Schnellfilters gefunden werden.  
	- Weitere Informationen zum Hinzufügen von Instanzen in der [Dokumentation der Instanzen](https://www.symcon.de/service/dokumentation/konzepte/instanzen/#Instanz_hinzufügen)

__Konfigurationsseite__:

Name     | Beschreibung
-------- | ------------------
         |
         |

### 5. Statusvariablen und Profile

Die Statusvariablen/Kategorien werden automatisch angelegt. Das Löschen einzelner kann zu Fehlfunktionen führen.

#### Statusvariablen

Name   | Typ     | Beschreibung
------ | ------- | ------------
       |         |
       |         |

#### Profile

Name   | Typ
------ | -------
       |
       |

### 6. WebFront

Die Funktionalität, die das Modul im WebFront bietet.

### 7. PHP-Befehlsreferenz

