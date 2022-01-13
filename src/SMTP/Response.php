<?php

class Response {

    const SEPARATOR = "\r\n";

    private static function sendMessage($connection, $message) {
        $connection->send($message . self::SEPARATOR);
    }

    public static function sendRaw($connection, $message) {
        $connection->send($message);
    }

    public static function askForAuthPassword($connection) {
        self::sendMessage($connection, "334 UGFzc3dvcmQ6");
    }

    public static function askForAuthUsername($connection) {
        self::sendMessage($connection, "334 VXNlcm5hbWU6");
    }

    public static function sendReady($connection) {
        self::sendMessage($connection, "220 Erb SMTPd ready");
    }

    public static function sendGeneral($connection, $message = 'OK') {
        self::sendMessage($connection, "250 " . $message);
    }

    public static function sendSyntaxError($connection) {
        self::sendMessage($connection, "501 Syntax error");
    }

    public static function sendSyntaxErrorCommandUnRecognized($connection) {
        self::sendMessage($connection, "500 Syntax error, command unrecognized");
    }

    public static function sendTLSFailure($connection) {
        self::sendMessage($connection, "454 TLS Not available");
    }

    public static function endConnection($connection) {
        self::sendMessage($connection, "221 Goodbye! Regards, Erb SMTPd");
    }

    public static function initiateTLS($connection) {
        self::sendMessage($connection, "220 Go ahead");
    }

    public static function cmdNotImplemented($connection) {
        self::sendMessage($connection, "502 Command not implemented");
    }

    public static function tooManyInvalidCommands($connection) {
        self::sendMessage($connection, "421 Too many invalid commands");
    }

    public static function requestAuthenticationPlain($connection) {
        self::sendMessage($connection, "334 ");
    }

    public static function sendAuthenticationFailureResponse($connection) {
        self::sendMessage($connection, "535 Authentication failed");
    }

    public static function sendAuthenticationSuccessResponse($connection) {
        self::sendMessage($connection, "235 2.7.0 Authentication successful");
    }

    public static function sendRequestForData($connection) {
        self::sendMessage($connection, '354 Start mail input; end with <CRLF>.<CRLF>');
    }

}