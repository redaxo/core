# PHPMailer

## Allgemeines
Das PHPMailer-AddOn ermöglicht den Versand von E-Mails. Zusätzlich kann PHPMailer den Administrator bei aufgetretenen Fehlern im System per E-Mail benachrichtigen. 

**Unterstützt werden folgende Sendeverfahren**
- php mail()
- sendmail 
- SMTP/SMTPS
- SMTP/SMTPS-Auth
- Microsoft Graph API (Microsoft 365)

Der Aufruf erfolgt über die Klasse `Mailer`. Dabei werden die nachfolgend beschriebenen und in der Konfiguration hinterlegten Einstellungen berücksichtigt.

Die Werte der Konfiguration können in AddOns oder Modulen leicht überschrieben werden, siehe [Beispiele](#beispiele).

> Liefert ein AddOn oder Modul keine eigenen Einstellungen für das Versenden der E-Mails, gelten die Sende-Einstellungen im PHPMailer-AddOn. 

Weitere Informationen zur Verwendung von PHPMailer unter: [https://github.com/PHPMailer/PHPMailer/wiki/Tutorial](https://github.com/PHPMailer/PHPMailer/wiki/Tutorial)

> **Hinweis:** Eine Test-Mail kann mit dem Button **Speichern und testen** verschickt werden. Hierzu müssen unbedingt Absender- und Test-Adresse festgelegt werden.

## Microsoft Graph API

Das PHPMailer-AddOn unterstützt den Versand von E-Mails über die Microsoft Graph API. Hierzu muss ein Microsoft 365 Geschäfts- oder Schulkonto existieren.

Im Microsoft Backend muss in der Anwendung MS Entra eine App angelegt werden. Dadurch entsteht eine Client-ID, die im PHPMailer-AddOn - genauso wie die Mandanten-Id (Tenant ID) hinterlegt werden muss.  
Für die App muss außerdem ein geheimes Client-Passwort generiert werden ("Geheimnis anlgen"), das ebenfalls im PHPMailer-AddOn hinterlegt werden muss.  

Die App muss die Anwendungs-Berechtigung (nicht "delegate-Berechtigung") `Mail.Send` besitzen.
Die Berechtigung muss dann noch im Microsoft Backend genehmigt werden.

Hinweis 1: Die Microsoft Graph API unterstützt nicht den Versand von Lesebestätigungen an beliebige E-Mail-Adressen. Sie werden immer an die E-Mail-Adresse des Absenders gesendet.  

Hinweis 2: Die Microsoft Graph API unterstützt nicht den Versand von E-Mails mit plain text UND mit HTML body. Das AddOn benutzt den HTML body, wenn er gesetzt ist, ansonsten den plain text body.

## PHPMailer Code-Beispiele


### 1. Beispiel

E-Mail an einen bestimmten Empfänger senden.

```php
<?php
  use Redaxo\Core\Mailer\Mailer;
  
  // PHPMailer-Instanz
  $mail = new mailer();

  //Absenderadresse überschreiben
 // $mail->From = "absender@domain.tld";

  //Absendername überschreiben
  // $mail->FromName = "Vorname Nachname";

  // Antwortadresse festlegen
  // $mail->addReplyTo("username@domain.com", "Software Simian");

  // Empfänger
  $mail->addAddress("empfaenger@domain.tld");

  // Empfänger als CC hinzufügen - Weitere anlegen wenn mehrere erwünscht
  // $mail->addCC("empfaenger2@domain.tld);

  // Empfänger als BCC hinzufügen - Weitere anlegen wenn mehrere erwünscht
  // $mail->addBCC("empfaenger3@domain.tld");

  //Betreff der E-Mail
  $mail->Subject = "PHPMailer-Test";

  //Text der EMail setzen
  $mail->Body = "Hi \n\n this mail was sent by PHPMailer!";

  //Überprüfen ob E-Mail gesendet wurde
  if(!$mail->send())
  {
     echo "An error occurred";
     echo "Error: " . $mail->ErrorInfo;
  }
  else
  {
     echo "E-Mail has been sent";
  }
```


### 2. Beispiel

E-Mail an einen Empfängerkreis senden, der aus der Datenbank ausgelesen wird.


```php

<?php

use Redaxo\Core\Database\Sql;
use Redaxo\Core\Mailer\Mailer;

$mail = new Mailer();
$sql = Sql::factory();

$query = "SELECT full_name, email, photo FROM employee WHERE id= ?";
$sql->setQuery($query, array($id));

foreach($sql as $row)
{
    // HTML body
    $body  = "Hello " . $row->getValue("full_name") . ",";
    $body .= "<p><i>Your</i> personal photograph to this message.<p>";
    $body .= "Sincerely, <br />";
    $body .= "phpmailer List manager";

    // Plain text body (for mail clients that cannot read HTML)
    $text_body  = "Hello " . $row->getValue("full_name") . ", \n\n";
    $text_body .= "Your personal photograph to this message.\n\n";
    $text_body .= "Sincerely, \n";
    $text_body .= "phpmailer List manager";

    $mail->Body    = $body;
    $mail->Subject = "PHPMailer-Test";
    $mail->AltBody = $text_body;
    $mail->AddAddress($row->getValue("email"), $row->getValue("full_name"));
    $mail->AddStringAttachment($sql->getValue("photo"), "YourPhoto.jpg");

    if(!$mail->Send())
        echo "There has been a mail error sending to " . $row->getValue("email") . "<br>";

    // Clear all addresses and attachments for next loop
    $mail->ClearAddresses();
    $mail->ClearAttachments();
}

```

## E-Mail-Versand auf Testadresse umleiten

Wird diese Option aktiviert, werden **alle** über PHPMailer verschickten E-Mails an die `Adresse für Testzwecke` verschickt.


## E-Mail-Benachrichtigung bei Fehlern

PHPMAiler versendet einen Auszug des system.log, wenn es Exceptions, Errors und eigene Logevents findet. 
Der Check und ggf. die Zusendung erfolgen in festen Intervallen, die in den Systemeinstellungen definiert werden können. Empfänger ist die im System hinterlegte Fehleradresse. 

Eigene Events können den Versand ebenso auslösen dazu kann man im Log den Event als Typ: logevent ablegen. 

`Logger::factory()->log('logevent', 'Mein Text zum Event');`

## SMTP-Debug

Das Setzen des Debug-Modus führt zu unterschiedlichen Ausgaben

### Client-Protokoll

Liefert das Protokoll für den Verbindungsaufbau durch den Client

### Server-und Client-Protokoll

Liefert zusätzlich die Informationen vom Server. Das ist die empfohlene Option.

### Verbindungsprotokoll

Liefert noch ausführlichere Informationen und ist ideal um Probleme mit STARTTLS zu analysieren. 

### Low Level Protokoll

Liefert zusätzlich untergeordnete Informationen, sehr ausführlich. Nicht zum Debuggen von SMTP gedacht, nur bei Low-Level-Problemen.

Meist benötigt man keinen Level über **Server-und Client-Protokoll**, es sei denn, es liegen Schwierigkeiten bei der Verbindung vor. Die Ausgabe wird meist nur umfangreicher und schwieriger zu lesen sein.

## E-Mail-Log und E-Mail-Archivierung 

Das Addon stellt ein E-Mail-Log sowie eine E-Mail-Archivierung bereit. 

> Die Nutzung dieser Funktionen sollten datenschutzrechtlich vorab geklärt werden.  

### E-Mail-Log

Das E-Mail-Log findet man unter `System` > `Logdateien` > `PHPMailer`. Das Logging kann in den Einstellungen des PHPMailer-Addons in 3 Stufen eingestellt werden. 

- Nein: Es wird kein Log erstellt
- Nur Fehler Loggen: Es werden nur Fehler geloggt 
- Alle Vorgänge loggen: Alle Sendungen werden geloggt 

Das Log liefert Informationen zu Zeit, Absender, Empfänger, Betreff und Meldung. Es kann über den Button `Logdatei löschen` geleert werden. 

Das Log wird in der Datei `/redaxo/data/log/mail.log` gespeichert.


### E-Mail-Archivierung

Bei eingeschalteter E-Mail-Archivierung werden alle E-Mails im Ordner `/redaxo/data/core/phpmailer/mail_log` im `.eml`-Format chronologisch nach Jahr und Monat in Unterordnern vollständig archiviert. .eml-Dateien können in gängigen E-Mail-Programmen zur Betrachtung geöffnet und importiert werden. 
Nicht versendete E-Mails erhalten das Präfix `not_sent_`. 

Das Archiv kann über den CronJob "Mailer-Archiv bereinigen" regelmäßig bereinigt werden. 

#### Archiv-Verwaltung

Eine eigene Archiv-Verwaltungsseite steht unter `PHPMailer` > `Archiv` zur Verfügung und bietet:

- Übersicht über Archivgröße und Anzahl der gespeicherten E-Mails
- Auflistung der 10 zuletzt archivierten E-Mails mit Betreff, Empfänger und Datum
- Sichere Löschung des kompletten E-Mail-Archivs über das Wartungspanel 

## Extension-Point `PHPMAILER_CONFIG`

Die Konfiguration kann mittels Extension-Point überschrieben und/oder ergänzt werden. 
Weitere Informationen zur [PHPMailer-Konfiguration beim Vendor](https://github.com/PHPMailer/PHPMailer).

Das folgende Beispiel zeigt die Verwendung von selbst signierten Zertifikaten. 

> Per Default wird der Peer verifiziert. Dies kann ggf. zu Problemen führen. Die nachfolgenden Einstellungen helfen, dieses Problem zu umgehen.

```php 
use Redaxo\Core\ExtensionPoint\Extension;
use Redaxo\Core\ExtensionPoint\ExtensionPoint;

Extension::register('PHPMAILER_CONFIG', function (ExtensionPoint $ep) {
    $subject = $ep->getSubject();
    // set SMTPOptions
    $subject->SMTPOptions = [
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true
        ],
        'tls' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true
        ],
    ];
});
```

## Extension-Point `PHPMAILER_PRE_SEND`

Dieser Extension-Point bietet sich an um E-Mails vor dem Versand zu überprüfen. So könnte an dieser Stelle ein Spamfilter greifen oder ein Virusscanner eingesetzt werden.

```php
use Redaxo\Core\ExtensionPoint\Extension;
use Redaxo\Core\ExtensionPoint\ExtensionPoint;

Extension::register('PHPMAILER_PRE_SEND', function (ExtensionPoint $ep) {
    $subject = $ep->getSubject(); 
    if (str_contains('bad word', $subject->Body) { 
        do_something(); 
    }
});
```

## Extension-Point `PHPMAILER_POST_SEND`

Der `PHPMAILER_POST_SEND` bietet sich an um nach dem Versand noch eigene Verarbeitungsschritte durchzuführen. 

### Beispiel: Gesendete Nachricht in IMAP-Ordner speichern: 


```php
use Redaxo\Core\ExtensionPoint\Extension;
use Redaxo\Core\ExtensionPoint\ExtensionPoint;

Extension::register('PHPMAILER_POST_SEND', function (ExtensionPoint $ep) {
    $subject          = $ep->getSubject(); 
    $host             = $subject->Host;
    $port             = '993';
    $user             = 'user@domain.tld';
    $pass             = 'XYZ12324';
    $imap_path_prefix = 'INBOX';
    $server           = '{' . $host . ':' . $port . '/imap/ssl}';
    
    // set IMAP Timeout
    imap_timeout(IMAP_OPENTIMEOUT, 14);
    // establish secure connection 
    if ($connection = imap_open($server, $user, $pass)) {
        // get all mailboxes as Array for testing  
        // $mailboxes = imap_getmailboxes($connection, "{'.$host.'}", "*");
        // dump($mailboxes);
        $result = imap_append($connection, "{'.$host.'}" . $imap_path_prefix . '.Sent', $subject->getSentMIMEMessage());
        imap_close($connection);
    } else {
        // Errorhandling
       throw new RuntimeException(sprintf('IMAP Error: "%s"',  imap_last_error()));
    }
});
```



## Tipps

### Verschlüsselung: AutoTLS

PHPMailer prüft bei "AutoTLS", ob der angegebene Server TLS unterstützt und baut eine verschlüsselte TLS-Verbindung auf. Erlaubt der Server keine Verschlüsselung, wird eine unsichere Verbindung aufgebaut. Sollte es zu Problemen beim Versand kommen, liegt es häufig daran, dass das hinterlegte Zertifikat nicht mit dem angegebenen Host übereinstimmt oder kein gültiges Zertifikat gefunden wurde. Durch Ändern der Verschlüsselung auf "manuelle Auswahl" kann die automatische Erkennung deaktiviert werden und die Verschlüsselung manuell gewählt werden. 

> Diese Einstellung kann zu unsicheren Verbindungen führen, sollte keine TLS-Unterstützung ausgehandelt werden können. Eine Überprüfung per Debug-Modus **Verbindungsprotokoll** sollte durchgeführt werden.

### Spam-Blocker

- Der Server, der die E-Mails versendet, sollte möglichst per SPF-Eintrag für die verwendete E-Mail-Domain als autorisierter Server im DNS hinterlegt sein.

- Prioritäts-Einstellungen können zu einem Spam-Blocking führen.

- Große E-Mail-Verteiler sollten möglichst in kleiner Zahl und als BCC verschickt werden.

> Bei Verwendung von SMTP ist das Versenden von E-Mails mit leerem Body nicht gestattet. 


### Senden über unterschiedliche Domains 

Werden E-Mails über unterschiedliche Absender-Domains verschickt, sollte der SPF-Eintrag der Absender-Domain(s) in den DNS-Einstellungen

- den Webserver (bei sendmail und mail) 
- oder den angegebenen SMTP(S)-Server 

als erlaubte Server-Adressen beinhalten. 

z.B. `a:meine-domain.tld ip4:XXX.XXX.XXX.XXX`

Somit wird sichergestellt, dass PHPMailer E-Mails unter der angegebenen Domain versenden kann und die Mail nicht als SPAM deklariert wird.  

Hierzu ggf. den Registrar oder DNS-Administrator kontaktieren. 
