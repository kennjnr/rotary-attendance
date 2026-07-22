<?php
// includes/Mailer.php
// Install: composer require phpmailer/phpmailer

require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class Mailer
{
    public function sendCertificate(
        string  $toEmail,
        string  $toName,
        array   $meeting,
        string  $pdfPath
    ): array {
         $mail = new PHPMailer(true);
        try {
             $mail->isSMTP();
             $mail->Host       = SMTP_HOST;
             $mail->SMTPAuth   = true;
             $mail->Username   = SMTP_USER;
             $mail->Password   = SMTP_PASS;
             $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
             $mail->Port       = SMTP_PORT;
             $mail->setFrom(SMTP_USER, SMTP_FROM_NAME);
             $mail->addAddress($toEmail,  $toName);
             $mail->addReplyTo(SMTP_USER, SMTP_FROM_NAME);

            // Attach certificate PDF
            if (file_exists($pdfPath)) {
                 $mail->addAttachment($pdfPath, basename($pdfPath));
            }

            // Embed logo as inline image
             $logoFile = __DIR__ . '/../assets/images/logo.png';
            if (file_exists($logoFile)) {
                 $mail->addEmbeddedImage($logoFile, 'clublogo', 'logo.png');
            }

             $mail->isHTML(true);
             $mail->Subject = 'Your Attendance Certificate — ' .  $meeting['title'];
             $mail->Body    =  $this->buildEmailBody($toName,  $meeting);
             $mail->AltBody = 'Dear ' .  $toName . ', your certificate for '
                        .  $meeting['title'] . ' is attached.';
             $mail->send();
            return ['sent' => true, 'error' => null];

        } catch (Exception  $e) {
            return ['sent' => false, 'error' =>  $mail->ErrorInfo];
        }
    }


    private function buildEmailBody(string  $name, array  $meeting): string
    {
         $date  = date('l, d F Y', strtotime($meeting['meeting_date']));
         $time  = date('h:i A', strtotime($meeting['start_time']));
         $venue = htmlspecialchars($meeting['venue'] ?? 'Club Venue');
         $title = htmlspecialchars($meeting['title']);

        return <<<HTML
        <!DOCTYPE html>
        <html>
        <head>
          <meta charset="UTF-8">
          <style>
            body { font-family: Arial, sans-serif; background: #f4f4f4; margin:0; padding:0; }
            .wrap { max-width:600px; margin:30px auto; background:#fff;
                    border-radius:10px; overflow:hidden;
                    box-shadow:0 4px 16px rgba(0,0,0,0.08); }
            .header { background:#003f87; padding:28px 32px; text-align:center; }
            .header h1 { color:#fff; margin:0; font-size:1.5rem; }
            .header p  { color:#a8c4e8; margin:6px 0 0; font-size:0.9rem; }
            .body { padding:32px; color:#333; line-height:1.7; }
            .body h2 { color:#003f87; margin-bottom:6px; }
            .info-box { background:#f0f4f8; border-left:4px solid #f7a800;
                        padding:14px 18px; border-radius:6px; margin:20px 0; }
            .info-box p { margin:4px 0; font-size:0.95rem; }
            .footer { background:#f7f7f7; text-align:center; padding:16px;
                      font-size:0.8rem; color:#999; }
          </style>
        </head>
        <body>
          <div class="wrap">
            <div class="hdr">
                <img src="cid:clublogo"
                    alt="Club Logo"
                    style="max-height:60px; max-width:120px;
                            object-fit:contain; margin-bottom:8px;
                            display:block; margin-left:auto; margin-right:auto;">
                <h1>&#9900; Rotary Club</h1>
                <p>Attendance Certificate</p>
            </div>

            <div class="body">
              <p>Dear <strong>{$name}</strong>,</p>
              <p>
                Thank you for attending our club meeting. Please find your
                <strong>Certificate of Attendance</strong> attached to this email.
              </p>
              <div class="info-box">
                <p><strong>📋 Meeting:</strong> {$title}</p>
                <p><strong>📅 Date:</strong> {$date}</p>
                <p><strong>🕐 Time:</strong> {$time}</p>
                <p><strong>📍 Venue:</strong> {$venue}</p>
              </div>
              <p>
                We are delighted to have had you with us and look forward
                to welcoming you at future meetings.
              </p>
              <p>
                Yours in Rotary Service,<br>
                <strong>The Secretary</strong><br>
                Rotary Club
              </p>
            </div>
            <div class="footer">
              Service Above Self &nbsp;|&nbsp; Rotary International
            </div>

            $verifyUrl = APP_URL . '/certificate.php?no=' . urlencode($certNo ?? '');
            <div style="margin-top:20px; padding:14px 18px;
                        background:#f0f4f8; border-radius:8px;
                        font-size:0.85rem; color:#555;">
                🔗 <strong>Verify this certificate online:</strong><br>
                <a href="{$verifyUrl}"
                style="color:#003f87; word-break:break-all;">
                    {$verifyUrl}
                </a>
            </div>
          </div>
          
        </body>
        </html>
        HTML;
    }

    private function buildEmailBodyPlain(string  $name, array  $meeting): string
    {
         $date = date('l, d F Y', strtotime($meeting['meeting_date']));
        return "Dear {$name},\n\nThank you for attending: {$meeting['title']} on {$date}.\n"
             . "Your Certificate of Attendance is attached.\n\nYours in Rotary Service.";
    }
}
