<?php
// Blacknova Traders - A web-based massively multiplayer space combat and trading game
// Copyright (C) 2001-2012 Ron Harwood and the BNT development team
//
//  This program is free software: you can redistribute it and/or modify
//  it under the terms of the GNU Affero General Public License as
//  published by the Free Software Foundation, either version 3 of the
//  License, or (at your option) any later version.
//
//  This program is distributed in the hope that it will be useful,
//  but WITHOUT ANY WARRANTY; without even the implied warranty of
//  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
//  GNU Affero General Public License for more details.
//
//  You should have received a copy of the GNU Affero General Public License
//  along with this program.  If not, see <http://www.gnu.org/licenses/>.
//
// File: includes/mailer.php

function bnt_mailer_bootstrap(): bool
{
    static $loaded = false;

    if ($loaded) {
        return true;
    }

    $autoload = dirname(__DIR__, 2) . '/vendor/autoload.php';
    if (!is_file($autoload)) {
        return false;
    }

    require_once $autoload;
    $loaded = true;

    return true;
}

function bnt_send_email(string $to, string $subject, string $plainBody, array $options = array()): bool
{
    global $admin_mail, $adminname, $email_server;

    if (!bnt_mailer_bootstrap()) {
        return false;
    }

    $fromEmail = (string) ($options['from_email'] ?? $admin_mail);
    $fromName = (string) ($options['from_name'] ?? $adminname);
    $replyToEmail = (string) ($options['reply_to_email'] ?? '');
    $replyToName = (string) ($options['reply_to_name'] ?? '');

    $mailer = new \PHPMailer\PHPMailer\PHPMailer(true);
    $mailer->CharSet = 'UTF-8';

    $smtpHost = (string) getenv('BNT_SMTP_HOST');
    if ($smtpHost !== '') {
        $mailer->isSMTP();
        $mailer->Host = $smtpHost;
        $mailer->Port = (int) (getenv('BNT_SMTP_PORT') ?: 587);
        $mailer->SMTPAuth = (getenv('BNT_SMTP_AUTH') === false) ? true : (strtolower((string) getenv('BNT_SMTP_AUTH')) !== 'false');
        $mailer->Username = (string) getenv('BNT_SMTP_USER');
        $mailer->Password = (string) getenv('BNT_SMTP_PASS');
        $secure = strtolower((string) getenv('BNT_SMTP_SECURE'));
        if ($secure === 'ssl') {
            $mailer->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
        } elseif ($secure === 'tls' || $secure === '') {
            $mailer->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        }
    } else {
        $mailer->isMail();
    }

    $mailer->setFrom($fromEmail !== '' ? $fromEmail : ('noreply@' . $email_server), $fromName !== '' ? $fromName : 'BlackNova Traders');
    $mailer->addAddress($to);
    if ($replyToEmail !== '') {
        $mailer->addReplyTo($replyToEmail, $replyToName !== '' ? $replyToName : $replyToEmail);
    }
    $mailer->Subject = $subject;
    $mailer->Body = $plainBody;
    $mailer->AltBody = $plainBody;
    $mailer->isHTML(false);

    return $mailer->send();
}

