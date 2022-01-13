<?php

class Config {

    // This is the HOST_NAME of the SMTP server you are going to host

    const SERVER_HOST = 'smtp.website.tld';

    // Path to the SSL certificate for the HOST_NAME

    const CERTIFICATE_PATH = '/etc/letsencrypt/live/smtp.website.tld/fullchain.pem';

    // Path to the SSL private certificate for the HOST_NAME

    const CERTIFICATE_PK_PATH = '/etc/letsencrypt/live/smtp.website.tld/privkey.pem';

    // Number of processes you want to run. It should be equal to CPU_CORES X 2

    const PROCESSES = 6;

    // Database Credentials

    const DB_HOST = '127.0.0.1';
    const DB_USER = 'database_username';
    const DB_PASS = 'database_password';
    const DB_NAME = 'database_name';

}