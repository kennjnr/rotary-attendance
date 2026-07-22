<?php
// includes/QRGenerator.php

require_once __DIR__ . '/../vendor/autoload.php';

use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Color\Color;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel\ErrorCorrectionLevelHigh;
use Endroid\QrCode\RoundBlockSizeMode\RoundBlockSizeModeMargin;
use Endroid\QrCode\Logo\Logo;

class QRGenerator
{
    public static function generate(string  $url, string  $token): string
    {
         $dir = __DIR__ . '/../assets/qrcodes/';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

         $filePath =  $dir . 'qr_' .  $token . '.png';

        if (!file_exists($filePath)) {
             $qrCode = QrCode::create($url)
                ->setEncoding(new Encoding('UTF-8'))
                ->setErrorCorrectionLevel(new ErrorCorrectionLevelHigh())
                ->setSize(400)
                ->setMargin(16)
                ->setRoundBlockSizeMode(new RoundBlockSizeModeMargin())
                ->setForegroundColor(new Color(0, 63, 135))
                ->setBackgroundColor(new Color(255, 255, 255));

             $writer = new PngWriter();
             $result =  $writer->write($qrCode);
             $result->saveToFile($filePath);
        }

        return  $filePath;
    }

    public static function getUrl(string  $token): string
    {
        return APP_URL . '/checkin.php?token=' . urlencode($token);
    }

    public static function getWebPath(string  $token): string
    {
        return APP_URL . '/assets/qrcodes/qr_' .  $token . '.png';
    }
}
