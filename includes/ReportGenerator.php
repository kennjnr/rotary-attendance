<?php
// includes/ReportGenerator.php

require_once __DIR__ . '/../config/db.php';

class ReportGenerator
{
    private PDO  $pdo;

    public function __construct(PDO  $pdo)
    {
         $this->pdo =  $pdo;
    }

    // ── Full meeting report data ─────────────────────────────────────
    public function getMeetingReport(int  $meetingId): ?array
    {
        // Meeting info
         $stmt =  $this->pdo->prepare("
            SELECT m.*, c.club_name, c.district, c.city,
                   a.username AS created_by_name
            FROM   meetings m
            JOIN   clubs c ON c.id = m.club_id
            LEFT   JOIN admin_users a ON a.id = m.created_by
            WHERE  m.id = ?
        ");
         $stmt->execute([$meetingId]);
         $meeting =  $stmt->fetch();
        if (!$meeting) return null;

        // Summary row
         $summary =  $this->pdo->prepare("
            SELECT * FROM meeting_summary WHERE meeting_id = ?
        ");
         $summary->execute([$meetingId]);
         $summary =  $summary->fetch() ?: [];

        // Member attendance
         $stmt =  $this->pdo->prepare("
            SELECT ma.check_in_time, ma.is_late,
                   m.first_name, m.last_name, m.email,
                   m.phone, m.rotary_id, m.role,
                   cert.certificate_no, cert.email_sent, cert.email_sent_at
            FROM   member_attendance ma
            JOIN   members m    ON m.id   = ma.member_id
            LEFT   JOIN certificates cert ON cert.id = ma.certificate_id
            WHERE  ma.meeting_id = ?
            ORDER  BY ma.check_in_time ASC
        ");
         $stmt->execute([$meetingId]);
         $members =  $stmt->fetchAll();

        // Visiting Rotarians
         $stmt =  $this->pdo->prepare("
            SELECT vr.first_name, vr.last_name, vr.email, vr.phone,
                   vr.rotary_id, vr.home_club_name, vr.district,
                   vr.role_in_club, vr.check_in_time, vr.is_late,
                   cert.certificate_no, cert.email_sent, cert.email_sent_at
            FROM   visiting_rotarians vr
            LEFT   JOIN certificates cert ON cert.id = vr.certificate_id
            WHERE  vr.meeting_id = ?
            ORDER  BY vr.check_in_time ASC
        ");
         $stmt->execute([$meetingId]);
         $visitors =  $stmt->fetchAll();

        // Guests
         $stmt =  $this->pdo->prepare("
            SELECT g.first_name, g.last_name, g.email, g.phone,
                   g.organization, g.check_in_time, g.is_late,
                   hm.first_name AS host_first, hm.last_name AS host_last,
                   cert.certificate_no, cert.email_sent, cert.email_sent_at
            FROM   guests g
            LEFT   JOIN members hm ON hm.id = g.host_member_id
            LEFT   JOIN certificates cert ON cert.id = g.certificate_id
            WHERE  g.meeting_id = ?
            ORDER  BY g.check_in_time ASC
        ");
         $stmt->execute([$meetingId]);
         $guests =  $stmt->fetchAll();

        // Cert delivery stats
         $certStats =  $this->pdo->prepare("
            SELECT
                COUNT(*)                                    AS total_issued,
                SUM(email_sent = 1)                        AS total_sent,
                SUM(email_sent = 0)                        AS total_failed,
                MIN(issued_at)                             AS first_issued,
                MAX(issued_at)                             AS last_issued
            FROM certificates WHERE meeting_id = ?
        ");
         $certStats->execute([$meetingId]);
         $certStats =  $certStats->fetch();

        // Attendance by home club (for visiting rotarians)
         $stmt =  $this->pdo->prepare("
            SELECT home_club_name, COUNT(*) AS count
            FROM   visiting_rotarians
            WHERE  meeting_id = ?
            GROUP  BY home_club_name
            ORDER  BY count DESC
        ");
         $stmt->execute([$meetingId]);
         $visitingByClub =  $stmt->fetchAll();

        return compact(
            'meeting', 'summary', 'members',
            'visitors', 'guests', 'certStats', 'visitingByClub'
        );
    }

    // ── All meetings summary list ────────────────────────────────────
    public function getAllMeetingsSummary(array  $filters = []): array
    {
         $where  = ['1=1'];
         $params = [];

        if (!empty($filters['from_date'])) {
             $where[]  = 'm.meeting_date >= ?';
             $params[] =  $filters['from_date'];
        }
        if (!empty($filters['to_date'])) {
             $where[]  = 'm.meeting_date <= ?';
             $params[] =  $filters['to_date'];
        }
        if (!empty($filters['status'])) {
             $where[]  = 'm.status = ?';
             $params[] =  $filters['status'];
        }

         $whereStr = implode(' AND ',  $where);

         $stmt =  $this->pdo->prepare("
            SELECT m.id, m.title, m.meeting_date, m.start_time,
                   m.venue, m.status, c.club_name,
                   COALESCE(ms.total_members_present,    0) AS members,
                   COALESCE(ms.total_late_members,       0) AS late,
                   COALESCE(ms.total_visiting_rotarians, 0) AS visitors,
                   COALESCE(ms.total_guests,             0) AS guests,
                   COALESCE(ms.total_attendees,          0) AS total,
                   COALESCE(ms.total_certificates_sent,  0) AS certs_sent,
                   ms.report_generated_at
            FROM   meetings m
            JOIN   clubs c ON c.id = m.club_id
            LEFT   JOIN meeting_summary ms ON ms.meeting_id = m.id
            WHERE   $whereStr
            ORDER  BY m.meeting_date DESC, m.start_time DESC
        ");
         $stmt->execute($params);
        return  $stmt->fetchAll();
    }

    // ── Aggregate club stats ─────────────────────────────────────────
    public function getClubAggregates(): array
    {
        return  $this->pdo->query("
            SELECT
                COUNT(DISTINCT m.id)                           AS total_meetings,
                COALESCE(SUM(ms.total_members_present),    0)  AS total_member_checkins,
                COALESCE(SUM(ms.total_visiting_rotarians), 0)  AS total_visitors,
                COALESCE(SUM(ms.total_guests),             0)  AS total_guests,
                COALESCE(SUM(ms.total_attendees),          0)  AS grand_total,
                COALESCE(SUM(ms.total_certificates_sent),  0)  AS total_certs_sent,
                COALESCE(AVG(ms.total_attendees),          0)  AS avg_attendance
            FROM meetings m
            LEFT JOIN meeting_summary ms ON ms.meeting_id = m.id
            WHERE m.status IN ('Open','Closed')
        ")->fetch();
    }
}
