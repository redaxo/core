<?php

use Redaxo\Core\Core;
use Redaxo\Core\Mailer\Mailer;
use Redaxo\Core\Translation\I18n;
use Redaxo\Core\View\Fragment;
use Redaxo\Core\View\Message;

use function Redaxo\Core\View\escape;

$content = $mailerDebug = '';
$date = new DateTime();
if ('' == Core::getConfig('phpmailer_from') || '' == Core::getConfig('phpmailer_test_address')) {
    $content .= Message::error(I18n::msg('phpmailer_checkmail_noadress'));
} else {
    $mail = new Mailer();
    $mail->addAddress(Core::getConfig('phpmailer_test_address'));
    $mail->Subject = 'PHPMailer-Test | ' . escape(Core::getServerName()) . ' | ' . date_format($date, 'Y-m-d H:i:s');

    $devider = "\n--------------------------------------------------";
    $securityMode = '';

    if ('smtp' == Core::getConfig('phpmailer_mailer')) {
        $securityMode = Core::getConfig('phpmailer_security_mode');

        $host = "\nHost: " . escape(Core::getConfig('phpmailer_host'));
        $smtpinfo = $host . "\nPort: " . escape(Core::getConfig('phpmailer_port'));
        $smtpinfo .= $devider;

        if (false == $securityMode) {
            $securityMode = 'manual configured ' . Core::getConfig('phpmailer_smtpsecure');
            $securityMode = "\n" . I18n::msg('phpmailer_security_mode') . "\n" . $securityMode . $devider . $smtpinfo;
        } else {
            $securityMode = 'Auto';
            $securityMode = "\n" . I18n::msg('phpmailer_security_mode') . ": \n" . $securityMode . $devider . $host . $devider;
        }
    }

    $mail->Body = I18n::msg('phpmailer_checkmail_greeting') . "\n\n" . I18n::msg('phpmailer_checkmail_text') . ' ' . Core::getServerName();
    $mail->Body .= "\n\nDomain: " . $_SERVER['HTTP_HOST'];

    $mail->Body .= "\nMailer: " . Core::getConfig('phpmailer_mailer') . $devider . $securityMode;
    $mail->Body .= "\n" . I18n::msg('phpmailer_checkmail_domain_note') . "\n" . $devider;
    $mail->Debugoutput = static function ($str) use (&$mailerDebug) {
        $mailerDebug .= date('Y-m-d H:i:s', time()) . ' phpmailer.checkmail.php' . nl2br($str);
    };

    if (!$mail->send()) {
        $alert = '<h2>' . I18n::msg('phpmailer_checkmail_error_headline') . '</h2><hr>';
        $alert .= I18n::msg('phpmailer_checkmail_error') . ': ' . $mail->ErrorInfo;
        $content .= Message::error($alert);
    } else {
        $success = '<h2>' . I18n::msg('phpmailer_checkmail_send') . '</h2> ' . escape(Core::getConfig('phpmailer_test_address')) . '<br>' . I18n::msg('phpmailer_checkmail_info');
        $success .= '<br><br><strong>' . I18n::msg('phpmailer_checkmail_info_subject') . '</strong>';
        $content .= Message::success($success);
    }
}

$fragment = new Fragment();
$fragment->setVar('title', I18n::msg('phpmailer_checkmail_headline'));
$fragment->setVar('body', $content . $mailerDebug, false);
$out = $fragment->parse('core/page/section.php');
echo $out;
