# ProcessCameraEvents

Folgende Module beinhaltet das ProcessCameraEvents Repository:

- __ProcessCameraEvents__ ([Dokumentation](ProcessCameraEvents))  
Description of the ProcessCameraEvents Module
The ProcessCameraEvents module is designed to handle events from Hikvision cameras. It registers a webhook to receive event notifications, processes the received data, manages variables and media files, and triggers actions based on motion detection events.

Key Functionalities
Module Creation (Create Method):

Registers properties for the module, including WebhookName, ChannelId, SavePath, MotionActive, and EggTimerModuleId.
Ensures the webhook is registered using the RegisterHook method.
Apply Changes (ApplyChanges Method):

Applies any changes to the module configuration. The RegisterHook method is commented out here but can be used if needed.
Registering the Webhook (RegisterHook Method):

Registers the webhook to the IP-Symcon system if it is not already registered.
Processing Hook Data (ProcessHookData Method):

Reads the incoming webhook data and processes it.
Checks if the Egg Timer module is installed; if not, it prompts the user to install it.
Parses the webhook data and handles motion events by calling the handleMotionData method.
Handling Motion Data (handleMotionData Method):

Manages variables related to motion events.
Sets the value of the Motion variable to true.
Manages the Egg Timer instance and sets it active.
Downloads a snapshot from the Hikvision camera if the username and password are set.
Parsing Event Notifications (parseEventNotificationAlert Method):

Parses the XML event notification alert and converts it into an array.
Managing Variables (manageVariable Method):

Creates and manages variables, including setting their properties and logging status.
Managing Media Files (manageMedia Method):

Creates and manages media files for storing camera snapshots.
Downloading Hikvision Snapshots (downloadHikvisionSnapshot Method):

Downloads a snapshot from the Hikvision camera using the provided IP address, channel ID, username, and password.
Properties
WebhookName: The name of the webhook (default: 'HIKVISION_EVENTS').
ChannelId: The channel ID for the camera (default: '101').
SavePath: The path where images will be saved (default: 'webfront/user/').
MotionActive: The duration in seconds for which the motion is active (default: '30').
EggTimerModuleId: The module ID for the Egg Timer (default: '{17843F0A-BFC8-A4BA-E219-A2D10FC8E5BE}').
Usage
Configuration:

Configure the WebhookName, ChannelId, SavePath, and MotionActive properties when creating the module instance.
Event Handling:

When a camera sends an event to the webhook, the module processes the event data.
Variables related to the motion event are created and updated.
If the username and password are set, the module downloads the snapshot and saves it to the specified path.
Dependencies:

The module requires the Egg Timer module to be installed from the IP-Symcon module store for its functionality.
This module provides an automated way to handle motion events from Hikvision cameras, manage relevant variables, and download snapshots based on the detected motion events.






