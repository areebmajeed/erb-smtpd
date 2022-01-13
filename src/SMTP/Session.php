<?php

class Session {

    private static $instance = null;
    private $status = [];
    private $mail_details = [];
    private $connection_established_times = [];

    private $extended_commands = ['AUTH PLAIN LOGIN', 'STARTTLS', 'HELP'];
    private $auth_login_credentials = [];
    private $tcp_worker = null;
    private $helper = null;

    public function __construct() {
        $this->helper = new Helper();
    }

    public function setTCPWorker($worker) {
        $this->tcp_worker = $worker;
    }

    public function getHelper() {
        return $this->helper;
    }

    public function addClient($connection) {

        // Initialize the connection status

        $this->status[$connection->id] = [
            'hasHello' => false,
            'hasAuth' => false,
            'hasData' => false,
            'hasMailFrom' => false,
            'isAuthenticated' => false,
            'authType' => '',
            'authLoginPasswordSent' => false,
            'authPlainUsernamePasswordStringSent' => false
        ];

        $this->connection_established_times[$connection->id] = time();

    }

    public function killIdleConnections() {

        $rn = time();

        foreach ($this->connection_established_times as $k => $v) {

            if (($rn - $v) >= 60) {

                if (isset($this->tcp_worker->connections[$k])) {
                    $this->closeConnection($this->tcp_worker->connections[$k]);
                }

            }

        }

    }

    public function closeConnection($connection, $has_ended = false) {

        // Unset parameters

        unset($this->status[$connection->id]);
        unset($this->mail_details[$connection->id]);
        unset($this->auth_login_credentials[$connection->id]);
        unset($this->connection_established_times[$connection->id]);

        // Send End Connection message

        Response::endConnection($connection);

        // If the connection hasn't ended already, then close it

        if (!$has_ended) {
            $connection->close();
        }

    }

    // Getter and setter for statuses

    private function getStatus($connection_id, $key) {
        return $this->status[$connection_id][$key];
    }

    private function setStatus($connection_id, $key, $value) {
        $this->status[$connection_id][$key] = $value;
    }

    public function handleMessage($connection, $data) {

        // Parse the incoming buffer

        $str = new StringParser($data);
        $args = $str->parse();

        // Get command

        $command = array_shift($args);
        $command_cmp = strtolower($command);

        // Handle the commands

        switch ($command_cmp) {

            case 'helo':
                $this->handleHelo($connection);
                break;

            case 'ehlo':
                $this->handleEhlo($connection);
                break;

            case 'starttls':
                $this->handleStartTLS($connection, $args);
                break;

            case 'auth':
                $this->handleAuthentication($connection, $args);
                break;

            case 'mail':
                $this->handleMailFrom($connection, $args);
                break;

            case 'rcpt':
                $this->handleRecipient($connection, $args);
                break;

            case 'data':
                $this->handleData($connection, $args);
                break;

            case 'noop':
                Response::sendGeneral($connection, "G'day!");
                break;

            case 'help':
                Response::sendGeneral($connection, "HELO, EHLO, MAIL FROM, RCPT TO, DATA, NOOP, QUIT");
                break;

            case 'quit':
                $this->closeConnection($connection);
                break;

            default:
                $this->miscHandler($connection, $data, $command);
                break;

        }

    }

    private function miscHandler($connection, $data, $command) {

        if ($this->getStatus($connection->id, 'isAuthenticated') === true && $this->getStatus($connection->id, 'hasData') === true) {

            if ($data === '.') {

                $this->mail_details[$connection->id]['mail'] = substr($this->mail_details[$connection->id]['mail'], 0, -strlen(Response::SEPARATOR));

                $this->helper->handleEmail($this->mail_details[$connection->id]['from'], $this->mail_details[$connection->id]['to'], $this->mail_details[$connection->id]['mail']);

                unset($this->mail_details[$connection->id]);

                return Response::sendGeneral($connection, 'Gotcha!');

            } else {

                if (isset($this->mail_details[$connection->id]['mail'])) {
                    $this->mail_details[$connection->id]['mail'] .= $data . Response::SEPARATOR;
                } else {
                    $this->mail_details[$connection->id]['mail'] = $data . Response::SEPARATOR;
                }

            }

        } elseif ($this->getStatus($connection->id, 'hasAuth') === true && $this->getStatus($connection->id, 'authType') === 'login') {

            if ($this->getStatus($connection->id, 'authLoginPasswordSent') === true) {

                $this->auth_login_credentials[$connection->id]['password'] = base64_decode($command);

                $credentials = $this->auth_login_credentials[$connection->id];

                if ($this->helper->authenticate($credentials['username'], $credentials['password'])) {
                    $this->setStatus($connection->id, 'isAuthenticated', true);
                    return Response::sendAuthenticationSuccessResponse($connection);
                }

                Response::sendAuthenticationFailureResponse($connection);
                $this->closeConnection($connection);

            }

            $this->setStatus($connection->id, 'authLoginPasswordSent', true);
            $this->auth_login_credentials[$connection->id]['username'] = base64_decode($command);

            Response::askForAuthPassword($connection);

        } elseif ($this->getStatus($connection->id, 'hasAuth') === true && $this->getStatus($connection->id, 'authType') === 'plain') {

            if ($this->getStatus($connection->id, 'authPlainUsernamePasswordStringSent')) {

                $credentials = $this->decodePlainAuthentication($command);

                if ($this->helper->authenticate($credentials['username'], $credentials['password'])) {
                    $this->setStatus($connection->id, 'isAuthenticated', true);
                    return Response::sendAuthenticationSuccessResponse($connection);
                }

                Response::sendAuthenticationFailureResponse($connection);
                $this->closeConnection($connection);

            }

        } else {

            Response::cmdNotImplemented($connection);

        }

    }

    private function handleMailFrom($connection, $args) {

        // Make sure that the user hasHelloed and authenticated

        if ($this->getStatus($connection->id, 'hasHello') && $this->getStatus($connection->id, 'isAuthenticated')) {

            if (empty($args)) {
                return Response::sendSyntaxError($connection);
            }

            $this->mail_details[$connection->id]['from'] = $this->helper->getUsername();

            return Response::sendGeneral($connection, 'Go ahead');

        } else {

            return Response::sendSyntaxErrorCommandUnRecognized($connection);

        }

    }

    private function handleRecipient($connection, $args) {

        // Make sure that the user hasHelloed and authenticated

        if ($this->getStatus($connection->id, 'hasHello') && $this->getStatus($connection->id, 'isAuthenticated')) {

            if (empty($args)) {
                return Response::sendSyntaxError($connection);
            }

            $email = trim(str_replace(["to:<", ">"], "", strtolower($args[0])));

            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {

                $this->mail_details[$connection->id]['to'][] = $email;

                return Response::sendGeneral($connection, 'Go ahead');

            } else {

                return Response::sendSyntaxError($connection);

            }

        } else {

            return Response::sendSyntaxErrorCommandUnRecognized($connection);

        }

    }

    private function handleData($connection) {

        // Make sure that the user hasHelloed and authenticated

        if ($this->getStatus($connection->id, 'hasHello') && $this->getStatus($connection->id, 'isAuthenticated')) {

            // Update state

            $this->setStatus($connection->id, 'hasData', true);

            return Response::sendRequestForData($connection);

        } else {

            return Response::sendSyntaxErrorCommandUnRecognized($connection);

        }

    }

    private function decodePlainAuthentication($encoded_str) {

        // Decode and split

        $decoded = base64_decode($encoded_str);
        $parts = explode("\0", $decoded);

        if (!isset($parts[1]) || !isset($parts[2])) {

            $parts = [
                'username' => null,
                'password' => null
            ];

        }

        return [
            'username' => $parts[1],
            'password' => $parts[2]
        ];

    }

    public function handleAuthentication($connection, $args) {

        // The user must greet before trying to authenticate

        if ($this->getStatus($connection->id, 'hasHello')) {

            // Update state

            $this->setStatus($connection->id, 'hasAuth', true);

            if (empty($args)) {
                return Response::sendSyntaxError($connection);
            }

            // Get the authentication type

            $type = strtolower($args[0]);

            if ($type === 'plain') {

                // Set the state

                $this->setStatus($connection->id, 'authType', 'plain');

                // if the user has specified the authentication string in line

                if (isset($args[1])) {

                    // Update state

                    $this->setStatus($connection->id, 'authPlainUsernamePasswordStringSent', false);

                    // Decode the authentication string

                    $credentials = $this->decodePlainAuthentication($args[1]);

                    // Try to authenticate

                    if ($this->helper->authenticate($credentials['username'], $credentials['password'])) {
                        $this->setStatus($connection->id, 'isAuthenticated', true);
                        return Response::sendAuthenticationSuccessResponse($connection);
                    }

                    // Close the connection because of bad credentials

                    Response::sendAuthenticationFailureResponse($connection);
                    $this->closeConnection($connection);

                }

                return Response::requestAuthenticationPlain($connection);

            } elseif ($type === 'login') {

                $this->setStatus($connection->id, 'authType', 'login');

                // If the SMTP client also includes the username in the login

                if (isset($args[1])) {

                    $this->setStatus($connection->id, 'authLoginPasswordSent', true);
                    $this->auth_login_credentials[$connection->id]['username'] = base64_decode($args[1]);

                    return Response::askForAuthPassword($connection);

                }

                $this->setStatus($connection->id, 'authLoginPasswordSent', false);

                Response::askForAuthUsername($connection);

            } else {

                return Response::cmdNotImplemented($connection);

            }

        } else {

            return Response::sendSyntaxErrorCommandUnRecognized($connection);

        }

    }

    public function handleHelo($connection) {

        // Set the state

        $this->setStatus($connection->id, 'hasHello', true);

        Response::sendGeneral($connection, Config::SERVER_HOST);

    }

    public function handleEhlo($connection) {

        // Set the state to true

        $this->setStatus($connection->id, 'hasHello', true);

        // Give the list of extended commands to the client

        $resp = '250-' . Config::SERVER_HOST . Response::SEPARATOR;
        $c = count($this->extended_commands) - 1;

        for ($i = 0; $i < $c; $i++) {

            if ($this->extended_commands[$i] === 'STARTTLS') {

                if ($this->tcp_worker->implicit !== 'implicit') {
                    $resp .= '250-' . $this->extended_commands[$i] . Response::SEPARATOR;
                }

            } else {
                $resp .= '250-' . $this->extended_commands[$i] . Response::SEPARATOR;
            }

        }

        $resp .= '250 ' . end($this->extended_commands) . Response::SEPARATOR;

        Response::sendRaw($connection, $resp);

    }

    public function handleStartTLS($connection, $args) {

        // We don't STARTTLS for implicit mode

        if ($this->tcp_worker->implicit === 'implicit') {
            Response::cmdNotImplemented($connection);
            return false;
        }

        // We don't require any arguments here

        if (!empty($args)) {
            Response::sendSyntaxError($connection);
            return false;
        }

        // Tell the client that we are initializing a TLS connection

        Response::initiateTLS($connection);

        // Get the socket and choose a crypto method

        $socket = $connection->getSocket();

        $crypto_method = STREAM_CRYPTO_METHOD_TLS_SERVER;

        if (defined('STREAM_CRYPTO_METHOD_TLSv1_2_SERVER')) {
            $crypto_method |= STREAM_CRYPTO_METHOD_TLSv1_2_SERVER;
            $crypto_method |= STREAM_CRYPTO_METHOD_TLSv1_1_SERVER;
        }

        // Block the stream, enable crypto and then unblock

        stream_set_blocking($socket, true);
        $result = @stream_socket_enable_crypto($socket, true, $crypto_method);
        stream_set_blocking($socket, false);

        // If we fail to enable the crypto, let the client know

        if ($result === false) {

            // Send a failure message to the client

            Response::sendTLSFailure($connection);

        } else {

            // Reset the connection. After TLS handshake, the user  must resend HELO

            $this->setStatus($connection->id, 'hasHello', false);

        }

    }

}