<?php
// admin/reports/export_pdf.php

require_once '../includes/auth.php';
require_once '../../config/db.php';
require_once '../../includes/ReportGenerator.php';
require_once '../../vendor/autoload.php';

$pdo      = getPDO();
$reporter = new ReportGenerator($pdo);

// ── Single meeting PDF ───────────────────────────────────────────
if (isset($_GET['id'])) {
     $id   = (int)$_GET['id'];
     $data =  $reporter->getMeetingReport($id);
    if (!$data) die('Meeting not found.');
    outputSingleMeetingPDF($data);
    exit;
}

// ── All meetings summary PDF ─────────────────────────────────────
if (isset($_GET['all'])) {
     $filters  = [
        'from_date' =>  $_GET['from_date'] ?? '',
        'to_date'   =>  $_GET['to_date']   ?? '',
        'status'    =>  $_GET['status']    ?? '',
    ];
     $meetings   =  $reporter->getAllMeetingsSummary($filters);
     $aggregates =  $reporter->getClubAggregates();
    outputAllMeetingsPDF($meetings,  $aggregates,  $filters);
    exit;
}

die('No export target specified.');

// ════════════════════════════════════════════════════════════════
// SINGLE MEETING PDF
// ════════════════════════════════════════════════════════════════
function outputSingleMeetingPDF(array  $data): void
{
    extract($data);
    //  $meeting,  $summary,  $members,  $visitors,  $guests,
    //  $certStats,  $visitingByClub

     $pdf = new FPDF('P', 'mm', 'A4');
     $pdf->SetAutoPageBreak(true, 18);
     $pdf->SetMargins(14, 14, 14);

    // ── Page 1: Cover / Summary ──────────────────────────────────
     $pdf->AddPage();

    // Header band
    pdfHeaderBand($pdf,  $meeting);

    // Summary boxes
     $pdf->Ln(6);
     $boxes = [
        ['Members Present',     $summary['total_members_present']    ?? 0, [0,63,135]],
        ['Late Arrivals',       $summary['total_late_members']       ?? 0, [192,57,43]],
        ['Visiting Rotarians',  $summary['total_visiting_rotarians'] ?? 0, [247,168,0]],
        ['Guests',              $summary['total_guests']             ?? 0, [0,154,68]],
        ['Total Attendees',     $summary['total_attendees']          ?? 0, [0,63,135]],
        ['Certs Sent',          $certStats['total_sent']             ?? 0, [0,154,68]],
    ];

     $boxW = (210 - 28) / 3;
     $col  = 0;
    foreach ($boxes as  $box) {
        [$label,  $val,  $color] =  $box;
         $x = 14 + ($col % 3) *  $boxW;
         $y =  $pdf->GetY();
        if ($col % 3 === 0 &&  $col > 0)  $y += 22;
        if ($col % 3 === 0 &&  $col > 0)  $pdf->SetY($y);

         $pdf->SetXY($x,  $pdf->GetY());
         $pdf->SetFillColor(...$color);
         $pdf->SetDrawColor(255, 255, 255);
         $pdf->Rect($x,  $pdf->GetY(),  $boxW - 2, 20, 'F');

         $pdf->SetXY($x,  $pdf->GetY() + 3);
         $pdf->SetFont('Arial', 'B', 14);
         $pdf->SetTextColor(255, 255, 255);
         $pdf->Cell($boxW - 2, 7, (string)$val, 0, 0, 'C');

         $pdf->SetXY($x,  $pdf->GetY() + 7);
         $pdf->SetFont('Arial', '', 7);
         $pdf->Cell($boxW - 2, 5, strtoupper($label), 0, 0, 'C');

         $col++;
        if ($col % 3 === 0) {
             $pdf->Ln(22);
        }
    }

     $pdf->Ln(26);

    // Certificate stats block
     $pdf->SetTextColor(0, 0, 0);
     $pdf->SetFont('Arial', 'B', 10);
     $pdf->SetFillColor(240, 244, 248);
     $pdf->Cell(0, 7, 'CERTIFICATE DELIVERY SUMMARY', 0, 1, 'L', true);
     $pdf->Ln(1);

     $certRows = [
        ['Total Certificates Issued',  $certStats['total_issued'] ?? 0],
        ['Successfully Delivered',     $certStats['total_sent']   ?? 0],
        ['Failed / Pending',           $certStats['total_failed'] ?? 0],
        ['Delivery Rate',
            (($certStats['total_issued'] ?? 0) > 0
                ? round(($certStats['total_sent'] /  $certStats['total_issued']) * 100) . '%'
                : '0%')
        ],
    ];
    pdfKeyValueRows($pdf,  $certRows);

    // Visiting clubs
    if (!empty($visitingByClub)) {
         $pdf->Ln(4);
         $pdf->SetFont('Arial', 'B', 10);
         $pdf->SetFillColor(240, 244, 248);
         $pdf->Cell(0, 7, 'VISITING CLUBS BREAKDOWN', 0, 1, 'L', true);
         $pdf->Ln(1);
         $pdf->SetFont('Arial', '', 9);
        foreach ($visitingByClub as  $vc) {
             $pdf->SetTextColor(60, 60, 60);
             $pdf->Cell(140, 6,
                '  ' .  $vc['home_club_name'], 'B', 0, 'L');
             $pdf->SetFont('Arial', 'B', 9);
             $pdf->Cell(0, 6,  $vc['count'], 'B', 1, 'R');
             $pdf->SetFont('Arial', '', 9);
        }
    }

    // ── Page 2: Member Attendance ────────────────────────────────
    if (!empty($members)) {
         $pdf->AddPage();
        pdfHeaderBand($pdf,  $meeting, 'Club Members Attendance');

         $pdf->Ln(4);
         $cols = [
            ['#',           8,  'C'],
            ['Name',        46, 'L'],
            ['Role',        28, 'L'],
            ['Rotary ID',   24, 'C'],
            ['Check-In',    20, 'C'],
            ['Status',      18, 'C'],
            ['Cert No',     28, 'C'],
            ['Email Sent',  14, 'C'],
        ];
        pdfTableHeader($pdf,  $cols);

        foreach ($members as  $i =>  $row) {
            pdfCheckPageBreak($pdf,  $meeting, 'Club Members Attendance');
             $pdf->SetFont('Arial', '', 8);
             $pdf->SetTextColor(40, 40, 40);
             $pdf->SetFillColor($i % 2 === 0 ? 255 : 248,  $i % 2 === 0 ? 255 : 250,  $i % 2 === 0 ? 255 : 255);

             $fill =  $i % 2 !== 0;
             $pdf->Cell(8,  6,  $i + 1, 0, 0, 'C',  $fill);
             $pdf->Cell(46, 6,
                substr($row['first_name'].' '.$row['last_name'], 0, 28),
                0, 0, 'L',  $fill);
             $pdf->Cell(28, 6, substr($row['role'], 0, 16),          0, 0, 'L',  $fill);
             $pdf->Cell(24, 6,  $row['rotary_id'] ?? '—',             0, 0, 'C',  $fill);
             $pdf->Cell(20, 6,
                date('h:i A', strtotime($row['check_in_time'])),
                0, 0, 'C',  $fill);
             $pdf->Cell(18, 6,  $row['is_late'] ? 'Late' : 'On Time', 0, 0, 'C',  $fill);
             $pdf->Cell(28, 6,
                substr($row['certificate_no'] ?? '—', 0, 16),
                0, 0, 'C',  $fill);
             $pdf->Cell(14, 6,  $row['email_sent'] ? 'Yes' : 'No',    0, 1, 'C',  $fill);
        }
    }

    // ── Page 3: Visiting Rotarians ───────────────────────────────
    if (!empty($visitors)) {
         $pdf->AddPage();
        pdfHeaderBand($pdf,  $meeting, 'Visiting Rotarians');
         $pdf->Ln(4);

         $cols = [
            ['#',         6,  'C'],
            ['Name',      36, 'L'],
            ['Home Club', 44, 'L'],
            ['District',  18, 'C'],
            ['Role',      22, 'L'],
            ['Email',     40, 'L'],
            ['Check-In',  18, 'C'],
            ['Late?',     12, 'C'],
        ];
        pdfTableHeader($pdf,  $cols);

        foreach ($visitors as  $i =>  $row) {
            pdfCheckPageBreak($pdf,  $meeting, 'Visiting Rotarians');
             $fill =  $i % 2 !== 0;
             $pdf->SetFont('Arial', '', 7.5);
             $pdf->SetFillColor($fill ? 248 : 255,  $fill ? 250 : 255, 255);
             $pdf->Cell(6,  6,  $i + 1, 0, 0, 'C',  $fill);
             $pdf->Cell(36, 6,
                substr($row['first_name'].' '.$row['last_name'], 0, 24),
                0, 0, 'L',  $fill);
             $pdf->Cell(44, 6,
                substr($row['home_club_name'], 0, 28),
                0, 0, 'L',  $fill);
             $pdf->Cell(18, 6,  $row['district']     ?? '—', 0, 0, 'C',  $fill);
             $pdf->Cell(22, 6,
                substr($row['role_in_club'] ?? '—', 0, 14),
                0, 0, 'L',  $fill);
             $pdf->Cell(40, 6,
                substr($row['email'], 0, 26),
                0, 0, 'L',  $fill);
             $pdf->Cell(18, 6,
                date('h:i A', strtotime($row['check_in_time'])),
                0, 0, 'C',  $fill);
             $pdf->Cell(12, 6,  $row['is_late'] ? 'Late' : 'OK', 0, 1, 'C',  $fill);
        }
    }

    // ── Page 4: Guests ───────────────────────────────────────────
    if (!empty($guests)) {
         $pdf->AddPage();
        pdfHeaderBand($pdf,  $meeting, 'Guests');
         $pdf->Ln(4);

         $cols = [
            ['#',           6,  'C'],
            ['Name',        36, 'L'],
            ['Organization',36, 'L'],
            ['Email',       42, 'L'],
            ['Invited By',  30, 'L'],
            ['Check-In',    18, 'C'],
            ['Late?',       12, 'C'],
            ['Cert Sent',   14, 'C'],
        ];
        pdfTableHeader($pdf,  $cols);

        foreach ($guests as  $i =>  $row) {
            pdfCheckPageBreak($pdf,  $meeting, 'Guests');
             $fill =  $i % 2 !== 0;
             $pdf->SetFont('Arial', '', 7.5);
             $pdf->SetFillColor($fill ? 248 : 255,  $fill ? 250 : 255, 255);
             $pdf->Cell(6,  6,  $i + 1, 0, 0, 'C',  $fill);
             $pdf->Cell(36, 6,
                substr($row['first_name'].' '.$row['last_name'], 0, 24),
                0, 0, 'L',  $fill);
             $pdf->Cell(36, 6,
                substr($row['organization'] ?? '—', 0, 22),
                0, 0, 'L',  $fill);
             $pdf->Cell(42, 6,
                substr($row['email'], 0, 28),
                0, 0, 'L',  $fill);
             $host = ($row['host_first'] ?? false)
                ?  $row['host_first'].' '.$row['host_last']
                : '—';
             $pdf->Cell(30, 6, substr($host, 0, 18),  0, 0, 'L',  $fill);
             $pdf->Cell(18, 6,
                date('h:i A', strtotime($row['check_in_time'])),
                0, 0, 'C',  $fill);
             $pdf->Cell(12, 6,  $row['is_late'] ? 'Late' : 'OK', 0, 0, 'C',  $fill);
             $pdf->Cell(14, 6,  $row['email_sent'] ? 'Yes' : 'No', 0, 1, 'C',  $fill);
        }
    }

    // Footer on every page
     $pdf->Output('D',
        'Meeting_Report_' . date('Ymd', strtotime($meeting['meeting_date'])) . '.pdf');
}

// ════════════════════════════════════════════════════════════════
// ALL MEETINGS SUMMARY PDF
// ════════════════════════════════════════════════════════════════
function outputAllMeetingsPDF(array  $meetings, array  $agg, array  $filters): void
{
     $pdf = new FPDF('L', 'mm', 'A4');
     $pdf->SetAutoPageBreak(true, 18);
     $pdf->SetMargins(12, 12, 12);
     $pdf->AddPage();

    // Title
     $pdf->SetFillColor(0, 63, 135);
     $pdf->SetTextColor(255, 255, 255);
     $pdf->SetFont('Arial', 'B', 14);
     $pdf->Cell(0, 10, 'ROTARY CLUB — ALL MEETINGS SUMMARY REPORT', 0, 1, 'C', true);
     $pdf->SetFont('Arial', '', 8);

     $range = '';
    if ($filters['from_date'] ||  $filters['to_date']) {
         $range = 'Period: '
            . ($filters['from_date']
                ? date('d M Y', strtotime($filters['from_date'])) : 'Start')
            . ' to '
            . ($filters['to_date']
                ? date('d M Y', strtotime($filters['to_date'])) : 'Present');
    }
     $pdf->Cell(0, 6,
        'Generated: ' . date('d M Y, h:i A') . ($range ? '   |   ' .  $range : ''),
        0, 1, 'C', true);

    // Aggregate strip
     $pdf->Ln(4);
     $pdf->SetTextColor(0, 0, 0);
     $pdf->SetFont('Arial', 'B', 9);
     $pdf->SetFillColor(240, 244, 248);
     $aggCells = [
        'Total Meetings'   =>  $agg['total_meetings'],
        'Total Attendees'  => number_format($agg['grand_total']),
        'Member Check-Ins' => number_format($agg['total_member_checkins']),
        'Visitors'         => number_format($agg['total_visitors']),
        'Guests'           => number_format($agg['total_guests']),
        'Certs Sent'       => number_format($agg['total_certs_sent']),
        'Avg Attendance'   => number_format($agg['avg_attendance'], 1),
    ];
     $cellW = (297 - 24) / count($aggCells);
    foreach ($aggCells as  $lbl =>  $val) {
         $x =  $pdf->GetX();  $y =  $pdf->GetY();
         $pdf->SetFillColor(0, 63, 135);
         $pdf->Rect($x,  $y,  $cellW - 1, 14, 'F');
         $pdf->SetFont('Arial', 'B', 11);
         $pdf->SetTextColor(255, 255, 255);
         $pdf->SetXY($x,  $y + 1);
         $pdf->Cell($cellW - 1, 6, (string)$val, 0, 0, 'C');
         $pdf->SetFont('Arial', '', 6.5);
         $pdf->SetXY($x,  $y + 7);
         $pdf->Cell($cellW - 1, 5, strtoupper($lbl), 0, 0, 'C');
         $pdf->SetXY($x +  $cellW,  $y);
    }
     $pdf->Ln(18);

    // Table header
     $pdf->SetTextColor(0, 0, 0);
     $cols = [
        ['#',         6,  'C'],
        ['Meeting',   60, 'L'],
        ['Date',      22, 'C'],
        ['Venue',     36, 'L'],
        ['Status',    18, 'C'],
        ['Members',   16, 'C'],
        ['Late',      12, 'C'],
        ['Visitors',  16, 'C'],
        ['Guests',    14, 'C'],
        ['Total',     14, 'C'],
        ['Certs Sent',18, 'C'],
    ];
    pdfTableHeader($pdf,  $cols);

    foreach ($meetings as  $i =>  $m) {
        if ($pdf->GetY() > 185) {
             $pdf->AddPage();
            pdfTableHeader($pdf,  $cols);
        }
         $fill =  $i % 2 !== 0;
         $pdf->SetFont('Arial', '', 8);
         $pdf->SetFillColor($fill ? 248 : 255,  $fill ? 250 : 255, 255);
         $pdf->SetTextColor(40, 40, 40);

         $pdf->Cell(6,  6,  $i + 1, 0, 0, 'C',  $fill);
         $pdf->Cell(60, 6, substr($m['title'], 0, 38),  0, 0, 'L',  $fill);
         $pdf->Cell(22, 6,
            date('d M Y', strtotime($m['meeting_date'])),
            0, 0, 'C',  $fill);
         $pdf->Cell(36, 6, substr($m['venue'] ?? '—', 0, 22), 0, 0, 'L',  $fill);
         $pdf->Cell(18, 6,  $m['status'],      0, 0, 'C',  $fill);
         $pdf->Cell(16, 6,  $m['members'],     0, 0, 'C',  $fill);
         $pdf->Cell(12, 6,  $m['late'],        0, 0, 'C',  $fill);
         $pdf->Cell(16, 6,  $m['visitors'],    0, 0, 'C',  $fill);
         $pdf->Cell(14, 6,  $m['guests'],      0, 0, 'C',  $fill);
         $pdf->Cell(14, 6,  $m['total'],       0, 0, 'C',  $fill);
         $pdf->Cell(18, 6,  $m['certs_sent'],  0, 1, 'C',  $fill);
    }

    // Totals row
     $pdf->SetFont('Arial', 'B', 8);
     $pdf->SetFillColor(0, 63, 135);
     $pdf->SetTextColor(255, 255, 255);
     $pdf->Cell(6,  7, '',  0, 0, 'C', true);
     $pdf->Cell(60, 7, 'TOTALS', 0, 0, 'L', true);
     $pdf->Cell(22, 7, '',  0, 0, 'C', true);
     $pdf->Cell(36, 7, '',  0, 0, 'L', true);
     $pdf->Cell(18, 7, count($meetings) . ' mtgs', 0, 0, 'C', true);
     $pdf->Cell(16, 7, array_sum(array_column($meetings,'members')),  0, 0, 'C', true);
     $pdf->Cell(12, 7, array_sum(array_column($meetings,'late')),     0, 0, 'C', true);
     $pdf->Cell(16, 7, array_sum(array_column($meetings,'visitors')), 0, 0, 'C', true);
     $pdf->Cell(14, 7, array_sum(array_column($meetings,'guests')),   0, 0, 'C', true);
     $pdf->Cell(14, 7, array_sum(array_column($meetings,'total')),    0, 0, 'C', true);
     $pdf->Cell(18, 7, array_sum(array_column($meetings,'certs_sent')), 0, 1, 'C', true);

     $pdf->Output('D', 'Rotary_All_Meetings_Report_' . date('Ymd') . '.pdf');
}

// ════════════════════════════════════════════════════════════════
// PDF HELPER FUNCTIONS
// ════════════════════════════════════════════════════════════════

function pdfHeaderBand(FPDF  $pdf, array  $meeting, string  $section = ''): void
{
     $logoFile = __DIR__ . '/../../assets/images/logo.png';

     $pdf->SetFillColor(0, 63, 135);
     $pdf->SetTextColor(255, 255, 255);

    // Draw header band
     $pdf->Rect(0, 0, 210, 18, 'F');

    // Logo in header (left side)
    if (file_exists($logoFile)) {
         $pdf->Image($logoFile, 2, 1, 14, 0, 'PNG');
    }

    // Title text
     $pdf->SetFont('Arial', 'B', 13);
     $pdf->SetXY(0, 2);
     $pdf->Cell(210, 8,
        'ROTARY CLUB — MEETING ATTENDANCE REPORT',
        0, 1, 'C');

     $pdf->SetFont('Arial', '', 8);
     $pdf->SetXY(0, 10);
     $pdf->Cell(210, 5,
         $meeting['title'] . '   |   '
        . date('d F Y', strtotime($meeting['meeting_date']))
        . '   |   ' . ($meeting['venue'] ?? 'N/A')
        . ($section ? '   |   ' . strtoupper($section) : ''),
        0, 1, 'C');

     $pdf->SetTextColor(0, 0, 0);
     $pdf->Ln(2);
}


function pdfTableHeader(FPDF  $pdf, array  $cols): void
{
     $pdf->SetFont('Arial', 'B', 8);
     $pdf->SetFillColor(0, 63, 135);
     $pdf->SetTextColor(255, 255, 255);
    foreach ($cols as [$label,  $width,  $align]) {
         $pdf->Cell($width, 7, strtoupper($label), 0, 0,  $align, true);
    }
     $pdf->Ln();
     $pdf->SetTextColor(0, 0, 0);
}

function pdfKeyValueRows(FPDF  $pdf, array  $rows): void
{
    foreach ($rows as [$key,  $val]) {
         $pdf->SetFont('Arial', '', 9);
         $pdf->SetTextColor(100, 100, 100);
         $pdf->Cell(80, 6, '  ' .  $key, 'B', 0, 'L');
         $pdf->SetFont('Arial', 'B', 9);
         $pdf->SetTextColor(0, 63, 135);
         $pdf->Cell(0, 6, (string)$val, 'B', 1, 'L');
    }
}

function pdfCheckPageBreak(FPDF  $pdf, array  $meeting, string  $section): void
{
    if ($pdf->GetY() > 268) {
         $pdf->AddPage();
        pdfHeaderBand($pdf,  $meeting,  $section);
         $pdf->Ln(4);
    }
}
