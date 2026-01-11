# PHPMailer

## About
The PHPMailer-AddOn allows you to send e-mails. In addition, PHPMailer can notify the administrator via e-mail if errors occur in the system. 

**The following transmission methods are supported**
- php mail()
- sendmail 
- SMTP/SMTPS
- SMTP/SMTPS-Auth
- Microsoft Graph API (Microsoft 365)

The call is made via the class `Mailer`. The settings stored in the configuration are taken into account.

The configuration values can easily be overwritten by AddOns or modules, see [Examples](#examples).

> If an AddOn or module does not provide its own settings for sending e-mails, the send settings in the PHPMailer AddOn are applied. 

For more information about using PHPMailer, see: [https://github.com/PHPMailer/PHPMailer/wiki/Tutorial](https://github.com/PHPMailer/PHPMailer/wiki/Tutorial)

> **Note:** A test mail can be sent with the button **Save and test**. For this purpose, the sender and test address must be specified.

## Microsoft Graph API

The PHPMailer add-on supports sending emails via the Microsoft Graph API. For this, a Microsoft 365 business or school account is required.

In the Microsoft backend, an app must be created in the MS Entra application. This will generate a client ID, which must be stored in the PHPMailer add-on, as well as the tenant ID.  
A secret client password ("create secret") must also be generated for the app and stored in the PHPMailer add-on.

The app must have the application permission (not "delegate permission") Mail.Send. This permission must then be approved in the Microsoft backend

Note 1: The Microsoft Graph API does not support sending read receipts to arbitrary addresses. They are always sent to the sender address of the email.  

Note 2: The Microsoft Graph API does not support sending emails with both plain text and HTML body at the same time. Therefore the AddOn uses the HTML body if set and the plain text body otherwise.

## PHPMailer Code Examples


### 1. Example

Send an e-mail to a specific recipient.

```php
<?php
  use Redaxo\Core\Mailer\Mailer;
  
  // PHPMailer Instance
  $mail = new Mailer();

  //Overwrite sender address
 // $mail->From = "sender@domain.tld";

  //Overwrite sender name
  // $mail->FromName = "Firstname Surname";

  // Define reply address
  // $mail->addReplyTo("username@domain.com", "Software Simian");

  //  Recipient
  $mail->addAddress("recipient@domain.tld");

  // Add recipient as CC - Create more if you want more
  // $mail->addCC("recipient2@domain.tld);

  // Add recipient as BCC - Create more if more are desired
  // $mail->addBCC("recipient2@domain.tld");

  //Subject of the e-mail
  $mail->Subject = "PHPMailer-Test";

  //text of the email
  $mail->Body = "Hi \n\n this mail was sent by PHPMailer!";

  //Check if email has been sent
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


### 2. Example

Send an e-mail to a group of recipients that is read from the database.

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

## Redirect e-mail delivery to test address

If this option is activated, **all** e-mails sent via PHPMailer will be sent to the `address for test purposes`.

## E-mail notification in case of errors

PHPMAiler sends an excerpt of system.log when it finds exceptions, errors, and custom log events. 
The check and if necessary the sending are done in fixed intervals, which can be defined in the system settings. The recipient is the error address stored in the system settings. 

Your own events can also trigger the dispatch. You can store this event as type: logevent. 

`Logger::factory()->log('logevent', 'Mein Text zum Event');`

## SMTP-Debug

Setting the debug mode leads to different outputs:

### Client protocol

Output messages sent by the client.

### Server and client protocol

as Client protocol, plus responses received from the server (this is the most useful setting).

### Connection protocol

as Server and client protocol, plus more information about the initial connection - this level can help diagnose STARTTLS failures

### Lowlevel protocol

as Connection protocol, plus even lower-level information, very verbose, don't use for debugging SMTP, only low-level problems.

Most of the time you don't need a level over **server and client protocol**, unless there are difficulties with the connection. The output will usually be more extensive and harder to read.

## Email log and archiving 

> The use of these functions should be clarified in advance in terms of data privacy. 

### Email log

The Email log can be found under 'System' > 'Log files' > 'PHPMailer'. The logging can be set in the settings of the PHPMailer addon at 3 levels. 

- No: No log will be created.
- Log only errors: Only errors will be logged. 
- Log all transactions: All transmissions are logged 

The log provides information about date/time, sender, recipient, subject and message. It can be cleared via 'Clear log file'. 

The log is stored under `/redaxo/data/log/mail.log`.

### Email archiving 

When email archiving is switched on, all emails are saved in complete `.eml` format in the `/redaxo/data/core/phpmailer/mail_log` folder, chronologically by year and month in subfolders. .eml files can be opened and imported for viewing in common email programs. 
Emails that are not sent are prefixed with `not_sent_`. 

The archive can be purged periodically via the CronJob "Purge Mailer Archive". 

#### Archive Management

A dedicated archive management page is available under 'PHPMailer' > 'Archive' which provides:

- Overview of archive size and number of stored emails
- List of the 10 most recent archived emails with subject, recipient and date
- Safe deletion of the complete email archive from the maintenance panel 

### Extension-Point `PHPMAILER_CONFIG`

The configuration can be overwritten and/or extended via extension point. 
More information about [PHPMailer Configuration from vendor](https://github.com/PHPMailer/PHPMailer).
The following example shows the use of self-signed certificates. 

> By default, the peer is verified. This may lead to problems. The following settings help to avoid this problem.

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

## Extension point `PHPMAILER_PRE_SEND`

This extension point can be used to check e-mails before they are sent. So a spam filter or a virus scanner could be used at this point for example.

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

## Extension point `PHPMAILER_POST_SEND`

The `PHPMAILER_POST_SEND` is useful to perform custom processing steps after sending. 

### Example: Save sent message to IMAP folder:

```php
use Redaxo\Core\ExtensionPoint\Extension;
use Redaxo\Core\ExtensionPoint\ExtensionPoint;

Extension::register('PHPMAILER_POST_SEND', function (ExtensionPoint $ep) {
    $subject = $ep->getSubject(); 
    $host = $subject->Host;
    $port = '993';
    $user = 'user@domain.tld';
    $pass = 'XYZ12324';
    $imap_path_prefix = 'INBOX';
    $server = '{' . $host . ':' . $port . '/imap/ssl}';
    
    // set IMAP timeout
    imap_timeout(IMAP_OPENTIMEOUT, 14);
    // establish secure connection 
    if ($connection = imap_open($server, $user, $pass)) {
        // get all mailboxes as array for testing  
        // $mailboxes = imap_getmailboxes($connection, "{'.$host.'}", "*");
        // dump($mailboxes);
        $result = imap_append($connection, "{'.$host.'}" . $imap_path_prefix . '.Sent', $subject->getSentMIMEMessage());
        imap_close($connection);
    } else {
        // error handling
       throw new RuntimeException(sprintf('IMAP Error: "%s"', imap_last_error()));
    }
});
```



## Tips

### Encryption: AutoTLS

PHPMailer checks on "AutoTLS" if the specified server supports TLS and establishes an encrypted TLS connection. If the server does not allow encryption, an insecure connection will be established. If there should be problems with the connection, it is often due to the fact that the deposited certificate does not agree with the indicated host or no valid certificate was found. By changing the encryption to "manual selection" the automatic recognition can be deactivated and the encryption can be selected manually. 

> This setting can lead to insecure connections if TLS support is not found. A debug mode check **connection protocol** should be performed.


### Spam-Blocker

- The server that sends the e-mails should be listed in the DNS as authorized server for the sending e-mail domain, defined via SPF record.

- The priority settings can lead to spam blocking.

- Large e-mail distribution lists should be sent in small numbers and as BCCs if possible.

> SMTP transmission does not allow sending emails with empty body 


### Sending mails over different domains 

If e-mails are sent via different sender domains, the SPF record of the sender domain(s) should include proper settings for

- the web server (if you use sendmail or mail) 
- or the specified SMTP(S) server 

e.g. `a:my-domain.tld ip4:XXX.XXX.XXX.XXX`

This ensures that PHPMailer can send emails under the specified domain and that the mail is not declared as SPAM.  

If necessary, contact the registrar or DNS administrator. 
