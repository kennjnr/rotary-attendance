<?php
// admin/reports/export_csv.php

require_once '../includes/auth.php';
require_once '../../config/db.php';
require_once '../../includes/ReportGenerator.php';

$pdo      = getPDO();
$reporter = new ReportGenerator($pdo);

// ── Single meeting CSV ───────────────────────────────────────────
if (isset($_GET['id'])) {
     $id   = (int)$_GET['id'];
     $data =  $reporter->getMeetingReport($id);
    if (!$data) die('Meeting not found.');
    outputSingleMeetingCSV($data);
    exit;
}

// ── All meetings summary CSV ─────────────────────────────────────
if (isset($_GET['all'])) {
     $filters = [
        'from_date' =>  $_GET['from_date'] ?? '',
        'to_date'   =>  $_GET['to_date']   ?? '',
        'status'    =>  $_GET['status']    ?? '',
    ];
     $meetings   =  $reporter->getAllMeetingsSummary($filters);
     $aggregates =  $reporter->getClubAggregates();
    outputAllMeetingsCSV($meetings,  $aggregates);
    exit;
}

die('No export target specified.');

// ════════════════════════════════════════════════════════════════
// HELPERS
// ════════════════════════════════════════════════════════════════

function csvHeaders(string  $filename): void
{
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' .  $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');
}

function csvRow($handle, array  $row): void
{
    fputcsv($handle, array_map(fn($v) =>  $v ?? '',  $row));
}

function csvBlankLine($handle): void
{
    fputcsv($handle, []);
}

function csvSectionTitle($handle, string  $title): void
{
    fputcsv($handle, [strtoupper($title)]);
}

// ════════════════════════════════════════════════════════════════
// SINGLE MEETING CSV  (multi-sheet style — sections separated)
// ════════════════════════════════════════════════════════════════
function outputSingleMeetingCSV(array  $data): void
{
    extract($data);

     $filename = 'Meeting_Report_'
        . date('Ymd', strtotime($meeting['meeting_date']))
        . '_' . preg_replace('/[^a-z0-9]/i', '_',  $meeting['title'])
        . '.csv';

    csvHeaders($filename);
     $out = fopen('php://output', 'w');

    // ── Section 1: Meeting Info ──────────────────────────────────
    csvSectionTitle($out, 'Meeting Information');
    csvRow($out, ['Field', 'Value']);
    csvRow($out, ['Club',          $meeting['club_name']]);
    csvRow($out, ['Title',         $meeting['title']]);
    csvRow($out, ['Date',         date('d F Y', strtotime($meeting['meeting_date']))]);
    csvRow($out, ['Start Time',   date('h:i A', strtotime($meeting['start_time']))]);
    csvRow($out, ['End Time',
         $meeting['end_time']
            ? date('h:i A', strtotime($meeting['end_time']))
            : 'N/A']);
    csvRow($out, ['Venue',         $meeting['venue']  ?? 'N/A']);
    csvRow($out, ['Theme',         $meeting['theme']  ?? 'N/A']);
    csvRow($out, ['Status',        $meeting['status']]);
    csvRow($out, ['Report Date',  date('d F Y, h:i A')]);
    csvBlankLine($out);

    // ── Section 2: Attendance Summary ───────────────────────────
    csvSectionTitle($out, 'Attendance Summary');
    csvRow($out, ['Category', 'Count']);
    csvRow($out, ['Club Members Present',     $summary['total_members_present']    ?? 0]);
    csvRow($out, ['Late Arrivals',            $summary['total_late_members']       ?? 0]);
    csvRow($out, ['Visiting Rotarians',       $summary['total_visiting_rotarians'] ?? 0]);
    csvRow($out, ['Guests',                   $summary['total_guests']             ?? 0]);
    csvRow($out, ['Total Attendees',          $summary['total_attendees']          ?? 0]);
    csvRow($out, ['Certificates Issued',      $certStats['total_issued']           ?? 0]);
    csvRow($out, ['Certificates Delivered',   $certStats['total_sent']             ?? 0]);
    csvRow($out, ['Certificates Failed',      $certStats['total_failed']           ?? 0]);
     $rate = ($certStats['total_issued'] ?? 0) > 0
        ? round(($certStats['total_sent'] /  $certStats['total_issued']) * 100) . '%'
        : '0%';
    csvRow($out, ['Delivery Rate',  $rate]);
    csvBlankLine($out);

    // ── Section 3: Club Members ──────────────────────────────────
    csvSectionTitle($out, 'Club Members Attendance');
    csvRow($out, [
        'No.', 'First Name', 'Last Name', 'Role', 'Rotary ID',
        'Email', 'Phone', 'Check-In Time', 'Arrival Status',
        'Certificate No', 'Email Sent', 'Email Sent At',
    ]);
    foreach ($members as  $i =>  $row) {
        csvRow($out, [
             $i + 1,
             $row['first_name'],
             $row['last_name'],
             $row['role'],
             $row['rotary_id'] ?? '',
             $row['email'],
             $row['phone'] ?? '',
            date('h:i A', strtotime($row['check_in_time'])),
             $row['is_late'] ? 'Late' : 'On Time',
             $row['certificate_no'] ?? '',
             $row['email_sent'] ? 'Yes' : 'No',
             $row['email_sent_at']
                ? date('d M Y h:i A', strtotime($row['email_sent_at']))
                : '',
        ]);
    }
    csvBlankLine($out);

    // ── Section 4: Visiting Rotarians ───────────────────────────
    csvSectionTitle($out, 'Visiting Rotarians');
    csvRow($out, [
        'No.', 'First Name', 'Last Name', 'Home Club', 'District',
        'Role in Club', 'Rotary ID', 'Email', 'Phone',
        'Check-In Time', 'Arrival Status',
        'Certificate No', 'Email Sent', 'Email Sent At',
    ]);
    foreach ($visitors as  $i =>  $row) {
        csvRow($out, [
             $i + 1,
             $row['first_name'],
             $row['last_name'],
             $row['home_club_name'],
             $row['district'] ?? '',
             $row['role_in_club'] ?? '',
             $row['rotary_id'] ?? '',
             $row['email'],
             $row['phone'] ?? '',
            date('h:i A', strtotime($row['check_in_time'])),
             $row['is_late'] ? 'Late' : 'On Time',
             $row['certificate_no'] ?? '',
             $row['email_sent'] ? 'Yes' : 'No',
             $row['email_sent_at']
                ? date('d M Y h:i A', strtotime($row['email_sent_at']))
                : '',
        ]);
    }
    csvBlankLine($out);

    // ── Section 5: Guests ────────────────────────────────────────
    csvSectionTitle($out, 'Guests');
    csvRow($out, [
        'No.', 'First Name', 'Last Name', 'Organization',
        'Email', 'Phone', 'Invited By',
        'Check-In Time', 'Arrival Status',
        'Certificate No', 'Email Sent', 'Email Sent At',
    ]);
    foreach ($guests as  $i =>  $row) {
         $host = ($row['host_first'] ?? false)
            ?  $row['host_first'] . ' ' .  $row['host_last']
            : '';
        csvRow($out, [
             $i + 1,
             $row['first_name'],
             $row['last_name'],
             $row['organization'] ?? '',
             $row['email'],
             $row['phone'] ?? '',
             $host,
            date('h:i A', strtotime($row['check_in_time'])),
             $row['is_late'] ? 'Late' : 'On Time',
             $row['certificate_no'] ?? '',
             $row['email_sent'] ? 'Yes' : 'No',
             $row['email_sent_at']
                ? date('d M Y h:i A', strtotime($row['email_sent_at']))
                : '',
        ]);
    }
    csvBlankLine($out);

    // ── Section 6: Visiting Clubs Breakdown ─────────────────────
    if (!empty($visitingByClub)) {
        csvSectionTitle($out, 'Visiting Clubs Breakdown');
        csvRow($out, ['Club Name', 'Number of Visitors']);
        foreach ($visitingByClub as  $vc) {
            csvRow($out, [$vc['home_club_name'],  $vc['count']]);
        }
    }

    fclose($out);
}

// ════════════════════════════════════════════════════════════════
// ALL MEETINGS SUMMARY CSV
// ════════════════════════════════════════════════════════════════
function outputAllMeetingsCSV(array  $meetings, array  $agg): void
{
    csvHeaders('Rotary_All_Meetings_Report_' . date('Ymd') . '.csv');
     $out = fopen('php://output', 'w');

    // ── Aggregate summary ────────────────────────────────────────
    csvSectionTitle($out, 'Club-Wide Aggregate Summary');
    csvRow($out, ['Metric', 'Value']);
    csvRow($out, ['Total Meetings',          $agg['total_meetings']]);
    csvRow($out, ['Total Member Check-Ins',  $agg['total_member_checkins']]);
    csvRow($out, ['Total Visiting Rotarians',$agg['total_visitors']]);
    csvRow($out, ['Total Guests',            $agg['total_guests']]);
    csvRow($out, ['Grand Total Attendees',   $agg['grand_total']]);
    csvRow($out, ['Total Certificates Sent',$agg['total_certs_sent']]);
    csvRow($out, ['Avg Attendance/Meeting', number_format($agg['avg_attendance'], 1)]);
    csvRow($out, ['Report Generated',       date('d F Y, h:i A')]);
    csvBlankLine($out);

    // ── All meetings table ───────────────────────────────────────
    csvSectionTitle($out, 'All Meetings Detail');
    csvRow($out, [
        'No.', 'Meeting Title', 'Date', 'Start Time', 'Venue',
        'Status', 'Members Present', 'Late Arrivals',
        'Visiting Rotarians', 'Guests', 'Total Attendees',
        'Certificates Sent',
    ]);

    foreach ($meetings as  $i =>  $m) {
        csvRow($out, [
             $i + 1,
             $m['title'],
            date('d F Y', strtotime($m['meeting_date'])),
            date('h:i A', strtotime($m['start_time'])),
             $m['venue'] ?? '',
             $m['status'],
             $m['members'],
             $m['late'],
             $m['visitors'],
             $m['guests'],
             $m['total'],
             $m['certs_sent'],
        ]);
    }

    // Totals row
    csvRow($out, [
        '', 'TOTALS', '', '', '', '',
        array_sum(array_column($meetings, 'members')),
        array_sum(array_column($meetings, 'late')),
        array_sum(array_column($meetings, 'visitors')),
        array_sum(array_column($meetings, 'guests')),
        array_sum(array_column($meetings, 'total')),
        array_sum(array_column($meetings, 'certs_sent')),
    ]);

    fclose($out);
}
