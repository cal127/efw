<?php

namespace EFW;

class Mail {
    private static $mailer, $default_from, $default_from_name;

    public static function init($conf) {
        self::configSmtp($conf['host'],
                         $conf['port'],
                         $conf['user'],
                         $conf['pass']);

        self::$default_from = $conf['default_from'];
        self::$default_from_name = $conf['default_from_name'];
    }
    
    public static function configSmtp($host, $port, $username, $pass) {
        self::$mailer = new PHPMailer();
        self::$mailer->IsSMTP();
        self::$mailer->Host = $host;
        self::$mailer->Port = $port;
        self::$mailer->SMTPAuth = true;
        self::$mailer->Username = $username;
        self::$mailer->Password = $pass;
        // self::$mailer->SMTPSecure = 'tls';
    }

    public static function send($to, $subject, $body, $charset = 'UTF-8'
                                , $from = null
                                , $from_name = null)
    {
        if (!$from) { $from = self::$default_from; }
        if (!$from_name) { $from_name = self::$default_from_name; }

        if (empty(self::$mailer)) {
            throw new Exception(__METHOD__ . '(): Please make sure that the'
                . 'wrapper is properly configured via ' . __CLASS__
                . '::configSmtp()');
        }

        self::$mailer->CharSet = $charset;
        self::$mailer->From = $from;
        self::$mailer->FromName = $from_name;
        self::$mailer->AddAddress($to);
        self::$mailer->Subject = $subject;
        self::$mailer->Body = $body;

        self::$mailer->WordWrap = 72;

        return self::$mailer->Send();
    }
}

?>
