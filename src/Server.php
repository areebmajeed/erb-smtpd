<?php

use Workerman\Worker;
use Workerman\Timer;

require('../vendor/autoload.php');
require('Config/Config.php');
require('StringParser.php');
require('SMTP/Session.php');
require('Helper/Helper.php');
require('SMTP/Response.php');

$context = array(
    'ssl' => array(
        'local_cert' => Config::CERTIFICATE_PATH,
        'local_pk' => Config::CERTIFICATE_PK_PATH,
        'verify_peer' => false,
        'allow_self_signed' => false
    )
);

$port = $argv[2];

if (isset($argv[3])) {
    $tls_implicit = $argv[3];
} else {
    $tls_implicit = '';
}

Worker::$pidFile = "/tmp/" . $port . "_smtp.pid";

$tcp_worker = new Worker('tcp://0.0.0.0:' . $port, $context);
$tcp_worker->count = Config::PROCESSES;
$tcp_worker->buffer = '';

if ($tls_implicit === 'implicit') {
    $tcp_worker->implicit = 'implicit';
} else {
    $tcp_worker->implicit = '';
}

$tcp_worker->onWorkerStart = function () use ($tcp_worker) {

    $tcp_worker->session = new Session();
    $tcp_worker->session->setTCPWorker($tcp_worker);

    Timer::add(10, function () use ($tcp_worker) {
        $tcp_worker->session->killIdleConnections();
    });

};

$tcp_worker->onConnect = function ($connection) use ($tcp_worker, $tls_implicit) {

    echo 'Connected!\n';

    if ($tls_implicit === 'implicit') {

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
            $connection->close();
        }

    }

    $tcp_worker->session->addClient($connection);
    Response::sendReady($connection);

};

$tcp_worker->onMessage = function ($connection, $data) use ($tcp_worker) {

    $session = $tcp_worker->session;

    $data_block = $data;

    do {

        $separator_position = strpos($data_block, Response::SEPARATOR);

        if ($separator_position === false) {

            $tcp_worker->buffer .= $data_block;

            break;

        } else {

            $msg = $tcp_worker->buffer . substr($data_block, 0, $separator_position);
            $tcp_worker->buffer = '';

            $session->handleMessage($connection, $msg);

            $data_block = substr($data_block, $separator_position + strlen(Response::SEPARATOR));

        }

    } while ($data_block);

};

$tcp_worker->onClose = function ($connection) use ($tcp_worker) {
    $tcp_worker->session->closeConnection($connection, true);
};

Worker::runAll();
