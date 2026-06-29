<?php

$addon = rex_addon::get('phpmailer');
$content = $mailerDebug = '';
$date = new DateTime();
if ('' == $addon->getConfig('from') || '' == $addon->getConfig('test_address')) {
    $content .= rex_view::error($addon->i18n('checkmail_noadress'));
} else {
    $mail = new rex_mailer();
    $mail->addAddress($addon->getConfig('test_address'));
    $mail->Subject = 'PHPMailer-Test | ' . rex_escape(rex::getServerName()) . ' | ' . date_format($date, 'Y-m-d H:i:s');

    $devider = "\n--------------------------------------------------";
    $securityMode = '';

    if ('smtp' == $addon->getConfig('mailer')) {
        $securityMode = $addon->getConfig('security_mode');

        $host = "\nHost: " . rex_escape($addon->getConfig('host'));
        $smtpinfo = $host . "\nPort: " . rex_escape($addon->getConfig('port'));
        $smtpinfo .= $devider;

        if (false == $securityMode) {
            $securityMode = 'manual configured ' . $addon->getConfig('smtpsecure');
            $securityMode = "\n" . $addon->i18n('security_mode') . "\n" . $securityMode . $devider . $smtpinfo;
        } else {
            $securityMode = 'Auto';
            $securityMode = "\n" . $addon->i18n('security_mode') . ": \n" . $securityMode . $devider . $host . $devider;
        }
    }

    $mail->Body = $addon->i18n('checkmail_greeting') . "\n\n" . $addon->i18n('checkmail_text') . ' ' . rex::getServerName();
    $mail->Body .= "\n\nDomain: " . $_SERVER['HTTP_HOST'];

    $mail->Body .= "\nMailer: " . $addon->getConfig('mailer') . $devider . $securityMode;
    $mail->Body .= "\n" . $addon->i18n('checkmail_domain_note') . "\n" . $devider;
    $mail->Debugoutput = static function ($str) use (&$mailerDebug) {
        $mailerDebug .= date('Y-m-d H:i:s', time()) . ' ' . nl2br(rex_escape($str));
    };

    if (!$mail->send()) {
        $alert = '<h2>' . $addon->i18n('checkmail_error_headline') . '</h2><hr>';
        $alert .= $addon->i18n('checkmail_error') . ': ' . rex_escape($mail->ErrorInfo);
        $content .= rex_view::error($alert);
    } else {
        $success = '<h2>' . $addon->i18n('checkmail_send') . '</h2> ' . rex_escape($addon->getConfig('test_address')) . '<br>' . $addon->i18n('checkmail_info');
        $success .= '<br><br><strong>' . $addon->i18n('checkmail_info_subject') . '</strong>';
        $content .= rex_view::success($success);
    }
}

$fragment = new rex_fragment();
$fragment->setVar('title', $addon->i18n('checkmail_headline'));
$fragment->setVar('body', $content . $mailerDebug, false);
$out = $fragment->parse('core/page/section.php');
echo $out;
