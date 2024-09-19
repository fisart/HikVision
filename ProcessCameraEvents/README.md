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

# ProcessCameraEvents Module Documentation

## Overview

The **ProcessCameraEvents** module is designed for IP-Symcon to handle motion detection events from HIKVISION cameras. When a motion event is detected by a HIKVISION camera, the camera sends an event notification to the IP-Symcon server via a webhook. This module processes these events, captures snapshots, and manages related variables and media within IP-Symcon. It also utilizes an Egg Timer to manage the duration of the motion event status.

## Features

- **Webhook Integration**: Receives event notifications from HIKVISION cameras via a customizable webhook.
- **Snapshot Capture**: Downloads snapshots from the camera upon motion detection.
- **Variable Management**: Dynamically creates and updates variables associated with each camera and event.
- **Media Handling**: Stores and updates the latest snapshot as media within IP-Symcon.
- **Egg Timer Integration**: Uses an Egg Timer to reset the motion status after a specified duration.
- **Semaphore Usage**: Ensures thread-safe operations using semaphores.

## Requirements

- **IP-Symcon**: Version compatible with module development.
- **HIKVISION Camera**: Configured to send event notifications via webhook.
- **Egg Timer Module**: Must be installed from the IP-Symcon Module Store.
  - **Module ID**: `{17843F0A-BFC8-A4BA-E219-A2D10FC8E5BE}`

## Installation

1. **Import the Module**:
   - Use the IP-Symcon Module Control to import the `ProcessCameraEvents` module.
   - You can import it via the module's GitHub repository URL or by directly placing the PHP file into the modules directory.

2. **Install the Egg Timer Module**:
   - Navigate to the IP-Symcon Module Store.
   - Search for "Egg Timer" and install it.
   - Ensure it has the Module ID `{17843F0A-BFC8-A4BA-E219-A2D10FC8E5BE}`.

3. **Create an Instance**:
   - In the IP-Symcon Management Console, create a new instance of the `ProcessCameraEvents` module.
   - The instance will appear in your IP-Symcon object tree.

## Configuration

After creating an instance of the module, you need to configure its properties to match your environment and camera settings.

### Properties

1. **Webhook Name** (`WebhookName`)
   - **Type**: String
   - **Default**: `HIKVISION_EVENTS`
   - **Description**: The name of the webhook endpoint that the camera will send event notifications to.
   - **Example**: If set to `HIKVISION_EVENTS`, the webhook URL will be `http://<IP-Symcon-IP>/hook/HIKVISION_EVENTS`.

2. **Channel ID** (`ChannelId`)
   - **Type**: String
   - **Default**: `101`
   - **Description**: The channel ID of the camera stream to capture snapshots from.

3. **Save Path** (`SavePath`)
   - **Type**: String
   - **Default**: `/user/`
   - **Description**: The relative path where snapshots will be saved. The path is relative to the IP-Symcon root directory.

4. **User Name** (`UserName`)
   - **Type**: String
   - **Default**: `NotSet`
   - **Description**: The username for authenticating with the camera to download snapshots.

5. **Password** (`Password`)
   - **Type**: String
   - **Default**: `NotSet`
   - **Description**: The password for authenticating with the camera.

6. **Motion Active Duration** (`MotionActive`)
   - **Type**: Integer
   - **Default**: `30`
   - **Description**: The duration in seconds for which the motion status remains active after a motion event.

7. **Debug Mode** (`debug`)
   - **Type**: Boolean
   - **Default**: `false`
   - **Description**: Enables or disables debug logging.

### Steps to Configure

1. **Set the Webhook Name**:
   - Choose a unique webhook name.
   - Ensure that the camera is configured to send event notifications to this webhook.

2. **Configure Camera Details**:
   - Set the **Channel ID** to match your camera's stream channel.
   - Provide the **User Name** and **Password** for camera authentication.

3. **Set the Save Path**:
   - Define where snapshots should be saved.
   - Ensure the IP-Symcon user has write permissions to this directory.

4. **Adjust Motion Active Duration**:
   - Set how long the motion status should remain active after detection.

5. **Enable Debugging (Optional)**:
   - Set **Debug Mode** to `true` if you wish to enable detailed logging for troubleshooting.

6. **Apply Changes**:
   - Click on "Apply" or "Save" to store the configuration.

## Usage

### Setting Up the Camera Webhook

- **Webhook URL**: `http://<IP-Symcon-IP>/hook/<WebhookName>`
  - Replace `<IP-Symcon-IP>` with your IP-Symcon server's IP address.
  - Replace `<WebhookName>` with the value set in the module's **Webhook Name** property.

- **Camera Configuration**:
  - Access your HIKVISION camera's web interface.
  - Navigate to the event notification settings.
  - Configure the camera to send event notifications to the webhook URL.

### Handling Motion Events

When the camera detects motion:

1. **Event Notification**:
   - The camera sends an event notification to the configured webhook.

2. **Module Processing**:
   - The `ProcessCameraEvents` module receives the notification.
   - Parses the XML data from the camera.
   - Increments the internal event **counter**.

3. **Semaphore Handling**:
   - Uses semaphores to prevent simultaneous processing of events from the same camera.

4. **Variable Management**:
   - Creates or updates variables representing the camera and motion event.
   - Stores details such as camera name, IP address, event description, and date/time.

5. **Snapshot Download**:
   - Authenticates with the camera using the provided credentials.
   - Downloads the snapshot image.
   - Saves the image to the specified **Save Path**.

6. **Media Handling**:
   - Stores the snapshot as media within IP-Symcon.
   - Updates the media object to display the latest snapshot.

7. **Egg Timer Activation**:
   - Activates an Egg Timer instance to reset the motion status after the specified duration.
   - If an Egg Timer instance doesn't exist, it creates one and sets up an event to reset the motion status.

### Variables and Media

- **Camera Variable**:
  - Represents the camera.
  - Stores motion status (boolean), IP address, and user credentials.

- **Event Description Variable**:
  - Child variable under the camera.
  - Stores the event description and associated data.

- **Snapshot Media**:
  - Media object that holds the latest snapshot image from the camera.

## Internal Methods and Processes

### `Create()`

- Initializes the module.
- Registers properties and attributes.
- Ensures the webhook is registered.

### `ApplyChanges()`

- Called when settings are saved.
- Re-registers the webhook to ensure it's up-to-date.

### `RegisterHook($WebHook)`

- Manages the registration of the webhook with IP-Symcon's WebHook Control instance.
- Adds, updates, or removes the webhook as necessary.

### `ProcessHookData()`

- Main method that processes incoming webhook data.
- Reads raw input from `php://input` or `$_POST`.
- Parses the XML data and delegates handling to `handleMotionData()`.

### `handleMotionData($motionData, $source)`

- Handles the motion data received from the camera.
- Manages variables and media.
- Downloads the snapshot.
- Activates the Egg Timer.

### `parseEventNotificationAlert($xmlString)`

- Parses the XML string from the camera into an associative array.
- Returns the parsed data or `false` on failure.

### `handle_egg_timer($source, $kamera_name, $kameraId)`

- Manages the Egg Timer instance for resetting the motion status.
- Creates an Egg Timer if one doesn't exist.
- Sets up an event to reset the motion status after the specified duration.

### `manageVariable($parent, $name, $type, $profile, $logging, $aggregationType, $initialValue)`

- Creates or retrieves a variable.
- Sets up logging and aggregation if necessary.
- Initializes the variable with a value if provided.

### `manageMedia($parent, $name, $imageFile)`

- Creates or retrieves a media object.
- Updates the media file with the latest snapshot.

### `downloadHikvisionSnapshot($cameraIp, $channelId, $username, $password, $relativePath)`

- Downloads a snapshot image from the camera.
- Handles authentication.
- Retries the download up to three times if unsuccessful.

### `Destroy()`

- Cleans up when the module is deleted.
- Unregisters the webhook from the WebHook Control instance.

## Debugging

- **Enable Debug Mode**:
  - Set the **Debug Mode** property to `true` to enable detailed logging.
  - Logs are written to the IP-Symcon log and can be viewed for troubleshooting.

- **Log Messages**:
  - Provides information on the module's operations, including semaphore access, variable management, and errors.

## Notes and Best Practices

- **Security**:
  - Ensure that the **User Name** and **Password** for the camera are securely stored.
  - Avoid using default credentials.

- **Permissions**:
  - The IP-Symcon user must have write permissions to the **Save Path** directory.

- **Camera Configuration**:
  - The camera must be properly configured to send event notifications to the IP-Symcon webhook.
  - Test the webhook URL directly to ensure it's reachable from the camera's network.

- **Egg Timer Module**:
  - The Egg Timer module is essential for resetting the motion status.
  - Ensure it's installed and updated to the latest version.

- **Multiple Cameras**:
  - For multiple cameras, create separate instances of the `ProcessCameraEvents` module.
  - Use unique webhook names and ensure each camera is configured correctly.

## Troubleshooting

- **No Events Processed**:
  - Verify that the camera is sending events to the correct webhook URL.
  - Check network connectivity between the camera and IP-Symcon server.

- **Snapshots Not Downloaded**:
  - Ensure the camera credentials are correct.
  - Check that the camera's snapshot URL is accessible from the IP-Symcon server.

- **Egg Timer Not Working**:
  - Confirm that the Egg Timer module is installed.
  - Check the logs for any errors related to semaphore access or event handling.

- **Debug Logs**:
  - Enable **Debug Mode** to get detailed logs.
  - Review logs for any error messages or warnings.

## Example Configuration

1. **Create Module Instance**:
   - Name: `HIKVISION Camera 1`

2. **Set Properties**:
   - **Webhook Name**: `HIKVISION_CAMERA_1_EVENTS`
   - **Channel ID**: `101`
   - **Save Path**: `/user/hikvision_camera_1/`
   - **User Name**: `admin`
   - **Password**: `YourSecurePassword`
   - **Motion Active Duration**: `60`
   - **Debug Mode**: `true`

3. **Configure Camera**:
   - Webhook URL: `http://192.168.1.100/hook/HIKVISION_CAMERA_1_EVENTS`
   - Replace `192.168.1.100` with your IP-Symcon server's IP address.

4. **Test Setup**:
   - Trigger a motion event on the camera.
   - Check IP-Symcon for updated variables and snapshot media.
   - Review logs if **Debug Mode** is enabled.

## Conclusion

The `ProcessCameraEvents` module provides seamless integration between HIKVISION cameras and IP-Symcon. By handling motion events, capturing snapshots, and managing the motion status, it enhances your home automation setup's security capabilities. With proper configuration and utilization of the Egg Timer module, you can effectively monitor and respond to motion events within your IP-Symcon environment.



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

