<?php declare(strict_types=1);

use Monolog\Handler\StreamHandler;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport\SendmailTransport;
use Symfony\Component\Mailer\Transport\Smtp\SmtpTransport;
use Symfony\Component\Mailer\Transport\Smtp\Stream\SocketStream;
use Symfony\Component\Mime\Email;

include '../vendor/autoload.php';

$prodEnv = $prodEnv ?? true;

$from = $argv[1];
$to = $argv[2];
$templatePath = $argv[3];
$dataPath = $argv[4];
$logPath = $argv[5] ?? '../var/mail.log';

$logger = new Monolog\Logger('mail-log');
$logger->pushHandler(new StreamHandler($logPath));

try {
    if (!is_file($templatePath)) throw new InvalidArgumentException(sprintf(
        '{f4135b02-e094-4839-b512-25b8f9e39b6e} template file `%s` does not exist',
        $templatePath,
    ));

    if (!is_file($dataPath)) throw new InvalidArgumentException(sprintf(
        '{4f767657-a672-48cc-b436-a2528cd7be32} data file `%s` does not exist',
        $dataPath,
    ));

    $template = json_decode(file_get_contents($templatePath) ?: '[]', true);
    $data = json_decode(file_get_contents($dataPath) ?: '[]', true);

    if (empty($template)) throw new InvalidArgumentException(sprintf(
        '{cdf62ad9-04b9-40e1-9131-3a58722bc7d2} template file `%s` does not yield any data',
        $templatePath,
    ));

    if (empty($data)) throw new InvalidArgumentException(sprintf(
        '{4e24c06a-76e8-4dca-8278-b5afdb312a9d} data file `%s` does not yield any data',
        $dataPath,
    ));

    $subject = preg_replace_callback('/{{(?:&nbsp;|\W)(.*?)(?:&nbsp;|\W)}}/', function ($patterns) use ($data) {
        return array_key_exists($patterns[1], $data) ? $data[$patterns[1]] : $patterns[0];
    }, $template['subject'] ?? '');

    $body = preg_replace_callback('/{{(?:&nbsp;|\W)(.*?)(?:&nbsp;|\W)}}/', function ($patterns) use ($data) {
        return array_key_exists($patterns[1], $data) ? $data[$patterns[1]] : $patterns[0];
    }, $template['content'] ?? '');

    $email = (new Email())
        ->from($from)
        ->to($to)
        ->subject($subject)
        ->html($body);

    if ($prodEnv) {
        $transport = new SendmailTransport();
    } else {
        /** @noinspection PhpInternalEntityUsedInspection */
        $socketStream = new SocketStream();
        $socketStream->disableTls();
        $socketStream->setHost('tcp://localhost');
        $socketStream->setPort(1025);
        $socketStream->initialize();
        $transport = new SmtpTransport($socketStream);
    }

    $mailer = new Mailer($transport);

    $mailer->send($email);
} catch (Throwable $e) {
    $logger->critical($e->getMessage()."\r\n".$e->getTraceAsString());

    echo $e->getMessage()."\r\n".$e->getTraceAsString();
}
