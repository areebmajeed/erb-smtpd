<?php

class Helper {

    static $username = null;

    public static function getUsername() {
        return self::$username;
    }

    public static function authenticate($username, $password) {

        self::$username = $username;

        // Handle the authentication here

        return true;

    }

    public static function handleEmail($from, $to, $mail) {

        // Put the logic to parse and store the email here

    }

}