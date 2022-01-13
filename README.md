# Erb SMTPd

Erb SMTPd is a multi-worker SMTP server written in PHP. It has support for plain text, Opportunistic TLS (STARTTLS) and Implicit TLS communication.

## Usage

First things first, navigate to the project files.

```
cd /path/to/erb-smtpd/src
```

After that, download the dependencies.

 ```
composer install
```

Based on your preference, run the SMTPd server(s).

```

php Server.php start 25 -d;
php Server.php start 465 implicit -d;
php Server.php start 587 -d;
php Server.php start 2525 -d;

```

In the above example, 4 instances of the SMTP server are created on ports 24, 465, 587 and 2525 respectively. The argument **implicit** refers to implicit TLS. The flag **-d** runs the Server in production mode (i.e., as a background process).

Please edit the **Config.php** file to include the path to your SSL certificate. You must also include your logic to handle the authentication and email handling in **Helper.php**.

## Workerman

[Workerman](https://github.com/walkor/workerman) is used to create the TCP server. It is an asynchronous event-driven PHP framework with high performance to build fast and scalable network applications.

Workerman recommends **Event** extension recommended for better performance.

## Notice

This package includes modified source code from [Christian Mayer](https://fox21.at/) and [Aaron Schmied](https://github.com/aaronschmied/laravel-smtpd). Specifically the packages [thefox/network](https://github.com/TheFox/network), [thefox/smtpd](https://github.com/TheFox/smtpd) and [aaronschmied/laravel-smtpd](https://github.com/aaronschmied/laravel-smtpd). All are released under the GNU General Public License version 3.
