<?php
// includes/CertificateGenerator.php
// Install: composer require setasign/fpdf

require_once __DIR__ . '/../vendor/autoload.php';

class CertificateGenerator
{
    public function generate(string  $certNo, array  $certData, array  $meeting): string
    {
         $pdf = new FPDF('L', 'mm', 'A4'); // Landscape A4
         $pdf->AddPage();
         $pdf->SetAutoPageBreak(false);

        // ── Background image (optional) ──────────────────────────────
         $bgPath = __DIR__ . '/../assets/certificate_bg.jpg';
        if (file_exists($bgPath)) {
             $pdf->Image($bgPath, 0, 0, 297, 210);
        } else {
            // Fallback: plain styled background
            //  $pdf->SetFillColor(0, 63, 135);
            //  $pdf->Rect(0, 0, 297, 210, 'F');
            //  $pdf->SetFillColor(255, 255, 255);
            //  $pdf->Rect(10, 10, 277, 190, 'F');
            //  $pdf->SetFillColor(0, 63, 135);
            //  $pdf->Rect(10, 10, 277, 8, 'F');
            //  $pdf->Rect(10, 192, 277, 8, 'F');
        }

        // ── Rotary Wheel placeholder (top center) ────────────────────
         // Logo on certificate (top left)
        $logoFile = __DIR__ . '/../assets/images/logo.png';
        if (file_exists($logoFile)) {
             $pdf->Image($logoFile, 14, 14, 28, 0, 'PNG'); // auto height
        }

        // Logo on certificate (top right mirror)
        if (file_exists($logoFile)) {
             $pdf->Image($logoFile, 255, 14, 28, 0, 'PNG');
        }

        $pdf->SetFont('Arial', 'B', 11);
        $pdf->SetTextColor(0, 63, 135);
        $pdf->SetXY(0, 18);
        $pdf->Cell(297, 8, 'ROTARY INTERNATIONAL', 0, 1, 'C');


        // ── Club name ────────────────────────────────────────────────
         $pdf->SetFont('Arial', 'B', 14);
         $pdf->SetTextColor(180, 130, 0);
         $pdf->SetXY(0, 26);
         $pdf->Cell(297, 8, strtoupper($meeting['club_name'] ?? 'Rotary Club'), 0, 1, 'C');

        // ── Certificate title ────────────────────────────────────────
         $pdf->SetFont('Arial', 'B', 28);
         $pdf->SetTextColor(0, 63, 135);
         $pdf->SetXY(0, 44);
         $pdf->Cell(297, 14, 'CERTIFICATE OF ATTENDANCE', 0, 1, 'C');

        // ── Decorative line ──────────────────────────────────────────
         $pdf->SetDrawColor(180, 130, 0);
         $pdf->SetLineWidth(0.8);
         $pdf->Line(60, 60, 237, 60);

        // ── "This certifies that" ────────────────────────────────────
         $pdf->SetFont('Arial', 'I', 12);
         $pdf->SetTextColor(80, 80, 80);
         $pdf->SetXY(0, 65);
         $pdf->Cell(297, 8, 'This is to certify that', 0, 1, 'C');

        // ── Recipient name ───────────────────────────────────────────
         $pdf->SetFont('Arial', 'B', 24);
         $pdf->SetTextColor(0, 63, 135);
         $pdf->SetXY(0, 76);
         $pdf->Cell(297, 12,  $certData['recipient_name'], 0, 1, 'C');

        // ── Attendee type / club line ────────────────────────────────
         $pdf->SetFont('Arial', 'I', 12);
         $pdf->SetTextColor(80, 80, 80);
         $typeLabel = match($certData['attendee_type']) {
            'Member'             => 'Club Member',
            'Visiting Rotarian'  => 'Visiting Rotarian — ' .  $certData['extra_line'],
            'Guest'              => 'Guest' . (!empty($certData['extra_line'])
                                        ? ' — ' .  $certData['extra_line'] : ''),
            default              =>  $certData['attendee_type'],
        };
         $pdf->SetXY(0, 90);
         $pdf->Cell(297, 7,  $typeLabel, 0, 1, 'C');

        // ── Body text ────────────────────────────────────────────────
         $pdf->SetFont('Arial', '', 12);
         $pdf->SetTextColor(60, 60, 60);
         $pdf->SetXY(0, 100);
         $pdf->Cell(297, 7,
            'attended the club meeting titled:', 0, 1, 'C');

        // ── Meeting title ────────────────────────────────────────────
         $pdf->SetFont('Arial', 'B', 14);
         $pdf->SetTextColor(0, 63, 135);
         $pdf->SetXY(40, 109);
         $pdf->MultiCell(217, 8,
            '"' .  $meeting['title'] . '"', 0, 'C');

        // ── Date / venue ─────────────────────────────────────────────
         $pdf->SetFont('Arial', '', 11);
         $pdf->SetTextColor(60, 60, 60);
         $dateStr = date('l, d F Y', strtotime($meeting['meeting_date']));
         $venueStr = !empty($meeting['venue']) ? ' at ' .  $meeting['venue'] : '';
         $pdf->SetXY(0, 126);
         $pdf->Cell(297, 7, 'Held on ' .  $dateStr .  $venueStr, 0, 1, 'C');

        // ── Decorative line ──────────────────────────────────────────
         $pdf->SetDrawColor(180, 130, 0);
         $pdf->Line(60, 140, 237, 140);

        // ── Signature area ───────────────────────────────────────────
         $pdf->SetFont('Arial', '', 10);
         $pdf->SetTextColor(80, 80, 80);

        // Left: President
         $pdf->SetXY(50, 152);
         $pdf->Cell(80, 5, '____________________________', 0, 1, 'C');
         $pdf->SetXY(50, 157);
         $pdf->Cell(80, 5, 'Club President', 0, 1, 'C');

        // Right: Secretary
         $pdf->SetXY(167, 152);
         $pdf->Cell(80, 5, '____________________________', 0, 1, 'C');
         $pdf->SetXY(167, 157);
         $pdf->Cell(80, 5, 'Club Secretary', 0, 1, 'C');

        // ── Certificate number & QR hint ─────────────────────────────
         $pdf->SetFont('Arial', '', 8);
         $pdf->SetTextColor(150, 150, 150);
         $pdf->SetXY(0, 175);
         $pdf->Cell(297, 5,
            'Certificate No: ' .  $certNo . '   |   Issued: ' . date('d M Y, h:i A'),
            0, 1, 'C');

        // ── Save PDF ─────────────────────────────────────────────────
        if (!is_dir(CERT_DIR)) {
            mkdir(CERT_DIR, 0755, true);
        }
         $fileName = CERT_DIR .  $certNo . '.pdf';
         $pdf->Output('F',  $fileName);

        return  $fileName;
    }
}
