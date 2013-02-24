<?php

// class SwiftWrapper {
//     private static $transport, $mailer;
//     
//     public static function configSmtp($server, $port, $username, $pass) {
//         self::$transport =
//             Swift_SmtpTransport::newInstance($server, $port)
//             ->setUsername($username)
//             ->setPassword($pass);
// 
//         self::$mailer = Swift_Mailer::newInstance(self::$transport);
//     }
// 
//     public static function send($message) {
//         if (empty(self::$transport) || empty(self::$mailer)) {
//             throw new Exception(__METHOD__ . '(): Please make sure that the'
//                 . 'wrapper is properly configured via ' . __CLASS__
//                 . '::configSmtp()');
//         }
// 
//         return self::$mailer->send($message);
//     }
// }

class MailerWrapper {
    private static $mailer;
    
    public static function configSmtp($server, $port, $username, $pass) {
        self::$mailer = new PHPMailer();
        self::$mailer->IsSMTP();
        self::$mailer->Host = $server;
        self::$mailer->Port = $port;
        self::$mailer->SMTPAuth = true;
        self::$mailer->Username = $username;
        self::$mailer->Password = $pass;
        // self::$mailer->SMTPSecure = 'tls';
    }

    public static function send($to, $subject, $body, $charset = 'UTF-8'
                                , $from = 'bilgi@homelineplatform.com'
                                , $from_name = 'Homeline Platform')
    {
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
