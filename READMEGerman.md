# ProcessCameraEvents

Folgende Module beinhaltet das ProcessCameraEvents Repository:

- __ProcessCameraEvents__ ([Dokumentation](ProcessCameraEvents))  
### HikVision-Modul für IP-Symcon: Funktionsbeschreibung

Das HikVision-Modul für IP-Symcon ermöglicht eine nahtlose Integration von Hikvision-Kameras in das IP-Symcon-Heimautomationssystem. Dieses Modul bietet mehrere wichtige Funktionen, die darauf abzielen, die Sicherheit und Automatisierung durch Kameraereignisverarbeitung zu verbessern. Nachfolgend finden Sie eine detaillierte Beschreibung seiner Funktionen und Arbeitsweise:

#### Hauptfunktionen

1. **Erkennung von Personen und Fahrzeugen**:
   - Hikvision-Kameras können so konfiguriert werden, dass sie die Anwesenheit, das Betreten oder Verlassen von Personen oder Fahrzeugen in bestimmten Bereichen erkennen.
   - Die Kamera sendet Alarme an ein Alarmzentrum, das im Wesentlichen einen Webhook aufruft, der auf das IP-Symcon-System zeigt, um dieses Modul zu nutzen.

2. **ProcessCameraEvents-Instanz**:
   - Wenn eine Instanz von "ProcessCameraEvents" installiert ist, wird automatisch ein Webhook in IP-Symcon eingerichtet.
   - Bei Erkennung eines Ereignisses löst die Kamera den Webhook aus, der das Modul in IP-Symcon aufruft, um die Kameradaten zu verarbeiten.

3. **Erstellung von Booleschen Variablen**:
   - Das Modul erstellt unter der Instanz eine boolesche Variable, die nach der Kamera benannt ist, um den Ereignisstatus anzuzeigen.
   - Wenn mehrere Kameras den Webhook auslösen, wird für jede Kamera eine entsprechende boolesche Variable unter der Instanz erstellt.
   - Diese Variablen können verwendet werden, um benutzerdefinierte Skripte auszulösen oder Bewegungsdiagramme in IP-Symcon zu erstellen.

4. **Konfiguration der Ereignisdauer**:
   - Das Modul ermöglicht das Festlegen einer Dauer, während der die boolesche Variable aktiv bleibt, um eine Überflutung des Systems mit Alarmen derselben Kamera innerhalb des festgelegten Zeitraums zu verhindern.

5. **Speicherung von Ereignisbildern**:
   - Konfigurierbarer Pfad zur Speicherung von Ereignisbildern (Standard: `/user/`). Stellen Sie sicher, dass dieser Pfad gültig ist und im IP-Symcon-System existiert.

6. **Konfiguration des Kamerakanals**:
   - Das Modul ermöglicht die Definition der Kanal-ID der Kamera (Standard: 101).

7. **Herunterladen von Ereignisschnappschüssen**:
   - Durch Eingabe des Benutzernamens und des Passworts der Kamera kann das Modul ein Bild zum Zeitpunkt des Ereignisses von der Kamera herunterladen.
   - Eine Mediendatei mit dem Ereignisschnappschuss wird unter dem Kameranamen installiert.

8. **Zusätzliche Variablen**:
   - Eine Zeichenfolgevariable mit dem Namen der IP-Adresse der Kamera, die den registrierten Ereignistyp enthält.
   - Eine weitere Zeichenfolgevariable mit Datum und Uhrzeit des Ereignisses.

#### Voraussetzungen

- **Egg Timer**: Bevor das HikVision-Modul verwendet werden kann, muss ein Egg Timer aus dem IP-Symcon-Modulstore installiert werden. Dies ist für die zeitbasierten Funktionen des Moduls unerlässlich.

### Anwendungsfälle

- **Sicherheitsüberwachung**: Automatisches Auslösen von Alarmen und Erfassen von Schnappschüssen bei unbefugtem Zugriff oder Bewegungserkennung.
- **Automatisierung**: Integration mit anderen IP-Symcon-Skripten, um automatische Reaktionen wie das Einschalten von Lichtern oder das Auslösen von Alarmen bei erkannten Ereignissen zu ermöglichen.
- **Datenanalyse**: Erstellen von Bewegungsdiagrammen und Analysieren von Mustern basierend auf den booleschen Variablen und Ereignisprotokollen.

### Konfigurationsschritte

1. **Installieren Sie die ProcessCameraEvents-Instanz** in IP-Symcon.
2. **Webhook konfigurieren**: Stellen Sie sicher, dass der Webhook der Hikvision-Kamera auf das IP-Symcon-System zeigt.
3. **Einrichten von Variablen**: Konfigurieren Sie die booleschen Variablen, die Ereignisdauer und den Speicherpfad nach Ihren Anforderungen.
4. **Herunterladen von Ereignisschnappschüssen**: Geben Sie die Zugangsdaten der Kamera ein, um das Herunterladen von Schnappschüssen bei Ereignissen zu ermöglichen.
5. **Integration mit Egg Timer**: Stellen Sie sicher, dass der Egg Timer installiert ist, um eine ordnungsgemäße Zeitsteuerung und Ereignisverarbeitung zu gewährleisten.

Durch die Befolgung dieser Schritte und die Nutzung der Funktionen des Moduls können Benutzer Hikvision-Kameras effektiv in ihr IP-Symcon-System integrieren, ihre Sicherheitskonfiguration verbessern und die Automatisierungsmöglichkeiten erweitern.