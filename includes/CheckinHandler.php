<?php
// includes/CheckinHandler.php

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/CertificateGenerator.php';
require_once __DIR__ . '/Mailer.php';

class CheckinHandler
{
    private PDO  $pdo;

    public function __construct(PDO  $pdo)
    {
         $this->pdo =  $pdo;
    }

    // ─── MEMBER CHECK-IN ────────────────────────────────────────────
    public function checkinMember(int  $meetingId, int  $memberId): array
    {
        // Prevent duplicate check-in
         $stmt =  $this->pdo->prepare("
            SELECT id FROM member_attendance
            WHERE meeting_id = ? AND member_id = ?
        ");
         $stmt->execute([$meetingId,  $memberId]);
        if ($stmt->fetch()) {
            return ['success' => false, 'message' => 'You have already checked in for this meeting.'];
        }

        // Fetch member and meeting details
         $member  =  $this->fetchMember($memberId);
         $meeting =  $this->fetchMeeting($meetingId);

        if (!$member || !$meeting) {
            return ['success' => false, 'message' => 'Invalid member or meeting.'];
        }

         $isLate =  $this->isLate($meeting);

        // Insert attendance record
         $stmt =  $this->pdo->prepare("
            INSERT INTO member_attendance (meeting_id, member_id, check_in_time, is_late)
            VALUES (?, ?, NOW(), ?)
        ");
         $stmt->execute([$meetingId,  $memberId, (int)$isLate]);
         $attendanceId = (int)$this->pdo->lastInsertId();

        // Generate and send certificate
         $certData = [
            'recipient_name'  =>  $member['first_name'] . ' ' .  $member['last_name'],
            'recipient_email' =>  $member['email'],
            'attendee_type'   => 'Member',
            'attendee_ref_id' =>  $attendanceId,
            'extra_line'      =>  $member['club_name'],
        ];

         $certResult =  $this->issueCertificate($meetingId,  $certData);

        // Update certificate_id back on attendance row
        if ($certResult['success']) {
             $this->pdo->prepare("
                UPDATE member_attendance SET certificate_id = ? WHERE id = ?
            ")->execute([$certResult['cert_id'],  $attendanceId]);
        }

         $this->updateSummary($meetingId);
         $this->writeAudit('CHECKIN_MEMBER', 'member_attendance',  $attendanceId);

        return [
            'success'        => true,
            'message'        => 'Check-in successful!',
            'name'           =>  $certData['recipient_name'],
            'email'          =>  $certData['recipient_email'],
            'attendee_type'  => 'Member',
            'cert_no'        =>  $certResult['cert_no'] ?? '',
            'email_sent'     =>  $certResult['email_sent'] ?? false,
        ];
    }

    // ─── VISITING ROTARIAN CHECK-IN ─────────────────────────────────
    public function checkinVisitor(int  $meetingId, array  $data): array
    {
        // Prevent duplicate check-in
         $stmt =  $this->pdo->prepare("
            SELECT id FROM visiting_rotarians
            WHERE meeting_id = ? AND email = ?
        ");
         $stmt->execute([$meetingId,  $data['email']]);
        if ($stmt->fetch()) {
            return ['success' => false, 'message' => 'This email has already checked in for this meeting.'];
        }

         $meeting =  $this->fetchMeeting($meetingId);
         $isLate  =  $this->isLate($meeting);

        // Resolve or auto-create home club record
         $homeClubId =  $this->resolveClub($data['home_club_name'],  $data['district'] ?? '');

         $stmt =  $this->pdo->prepare("
            INSERT INTO visiting_rotarians
                (meeting_id, first_name, last_name, email, phone, rotary_id,
                 home_club_id, home_club_name, district, role_in_club, check_in_time, is_late)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)
        ");
         $stmt->execute([
             $meetingId,
             $data['first_name'],
             $data['last_name'],
             $data['email'],
             $data['phone']         ?? null,
             $data['rotary_id']     ?: null,
             $homeClubId,
             $data['home_club_name'],
             $data['district']      ?? null,
             $data['role_in_club']  ?? null,
            (int)$isLate,
        ]);
         $visitorId = (int)$this->pdo->lastInsertId();

         $certData = [
            'recipient_name'  =>  $data['first_name'] . ' ' .  $data['last_name'],
            'recipient_email' =>  $data['email'],
            'attendee_type'   => 'Visiting Rotarian',
            'attendee_ref_id' =>  $visitorId,
            'extra_line'      =>  $data['home_club_name'],
        ];

         $certResult =  $this->issueCertificate($meetingId,  $certData);

        if ($certResult['success']) {
             $this->pdo->prepare("
                UPDATE visiting_rotarians SET certificate_id = ? WHERE id = ?
            ")->execute([$certResult['cert_id'],  $visitorId]);
        }

         $this->updateSummary($meetingId);
         $this->writeAudit('CHECKIN_VISITOR', 'visiting_rotarians',  $visitorId);

        return [
            'success'       => true,
            'message'       => 'Check-in successful!',
            'name'          =>  $certData['recipient_name'],
            'email'         =>  $certData['recipient_email'],
            'attendee_type' => 'Visiting Rotarian',
            'cert_no'       =>  $certResult['cert_no'] ?? '',
            'email_sent'    =>  $certResult['email_sent'] ?? false,
        ];
    }

    // ─── GUEST CHECK-IN ─────────────────────────────────────────────
    public function checkinGuest(int  $meetingId, array  $data): array
    {
         $stmt =  $this->pdo->prepare("
            SELECT id FROM guests WHERE meeting_id = ? AND email = ?
        ");
         $stmt->execute([$meetingId,  $data['email']]);
        if ($stmt->fetch()) {
            return ['success' => false, 'message' => 'This email has already checked in for this meeting.'];
        }

         $meeting =  $this->fetchMeeting($meetingId);
         $isLate  =  $this->isLate($meeting);

         $stmt =  $this->pdo->prepare("
            INSERT INTO guests
                (meeting_id, first_name, last_name, email, phone,
                 organization, host_member_id, check_in_time, is_late)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?)
        ");
         $stmt->execute([
             $meetingId,
             $data['first_name'],
             $data['last_name'],
             $data['email'],
             $data['phone']          ?? null,
             $data['organization']   ?? null,
             $data['host_member_id'] ?: null,
            (int)$isLate,
        ]);
         $guestId = (int)$this->pdo->lastInsertId();

         $certData = [
            'recipient_name'  =>  $data['first_name'] . ' ' .  $data['last_name'],
            'recipient_email' =>  $data['email'],
            'attendee_type'   => 'Guest',
            'attendee_ref_id' =>  $guestId,
            'extra_line'      =>  $data['organization'] ?? '',
        ];

         $certResult =  $this->issueCertificate($meetingId,  $certData);

        if ($certResult['success']) {
             $this->pdo->prepare("
                UPDATE guests SET certificate_id = ? WHERE id = ?
            ")->execute([$certResult['cert_id'],  $guestId]);
        }

         $this->updateSummary($meetingId);
         $this->writeAudit('CHECKIN_GUEST', 'guests',  $guestId);

        return [
            'success'       => true,
            'message'       => 'Check-in successful!',
            'name'          =>  $certData['recipient_name'],
            'email'         =>  $certData['recipient_email'],
            'attendee_type' => 'Guest',
            'cert_no'       =>  $certResult['cert_no'] ?? '',
            'email_sent'    =>  $certResult['email_sent'] ?? false,
        ];
    }

    // ─── INTERNAL HELPERS ────────────────────────────────────────────

    private function issueCertificate(int  $meetingId, array  $certData): array
    {
         $meeting =  $this->fetchMeeting($meetingId);
         $certNo  =  $this->generateCertNo();

        // Generate PDF
         $generator = new CertificateGenerator();
         $filePath  =  $generator->generate($certNo,  $certData,  $meeting);

        // Insert certificate record
         $stmt =  $this->pdo->prepare("
            INSERT INTO certificates
                (certificate_no, meeting_id, attendee_type, attendee_ref_id,
                 recipient_name, recipient_email, file_path)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
         $stmt->execute([
             $certNo,
             $meetingId,
             $certData['attendee_type'],
             $certData['attendee_ref_id'],
             $certData['recipient_name'],
             $certData['recipient_email'],
             $filePath,
        ]);
         $certId = (int)$this->pdo->lastInsertId();

        // Send email
         $mailer    = new Mailer();
         $emailSent =  $mailer->sendCertificate(
             $certData['recipient_email'],
             $certData['recipient_name'],
             $meeting,
             $filePath
        );

        // Update email delivery status
         $this->pdo->prepare("
            UPDATE certificates
            SET email_sent = ?, email_sent_at = IF(? = 1, NOW(), NULL),
                email_error = ?
            WHERE id = ?
        ")->execute([
            (int)$emailSent['sent'],
            (int)$emailSent['sent'],
             $emailSent['error'] ?? null,
             $certId,
        ]);

        return [
            'success'    => true,
            'cert_id'    =>  $certId,
            'cert_no'    =>  $certNo,
            'file_path'  =>  $filePath,
            'email_sent' =>  $emailSent['sent'],
        ];
    }

    private function generateCertNo(): string
    {
         $year = date('Y');
         $stmt =  $this->pdo->query("
            SELECT COUNT(*) FROM certificates WHERE YEAR(issued_at) =  $year
        ");
         $count = (int)$stmt->fetchColumn() + 1;
        return sprintf('RCUHCERT-%s-%04d',  $year,  $count);
    }

    private function resolveClub(string  $clubName, string  $district): ?int
    {
         $stmt =  $this->pdo->prepare("
            SELECT id FROM clubs WHERE LOWER(club_name) = LOWER(?) LIMIT 1
        ");
         $stmt->execute([$clubName]);
         $club =  $stmt->fetch();

        if ($club) return (int)$club['id'];

        // Auto-create club entry
         $stmt =  $this->pdo->prepare("
            INSERT INTO clubs (club_name, district, is_host_club) VALUES (?, ?, 0)
        ");
         $stmt->execute([$clubName,  $district ?: null]);
        return (int)$this->pdo->lastInsertId();
    }

    private function isLate(array  $meeting): bool
    {
         $meetingStart = strtotime($meeting['meeting_date'] . ' ' .  $meeting['start_time']);
        return time() > ($meetingStart + 900); // 15-minute grace period
    }

    private function fetchMember(int  $id): ?array
    {
         $stmt =  $this->pdo->prepare("
            SELECT m.*, c.club_name
            FROM   members m
            JOIN   clubs c ON c.id = m.club_id
            WHERE  m.id = ?
        ");
         $stmt->execute([$id]);
        return  $stmt->fetch() ?: null;
    }

    private function fetchMeeting(int  $id): ?array
    {
         $stmt =  $this->pdo->prepare("SELECT * FROM meetings WHERE id = ?");
         $stmt->execute([$id]);
        return  $stmt->fetch() ?: null;
    }

    private function updateSummary(int  $meetingId): void
    {
         $this->pdo->prepare("
            INSERT INTO meeting_summary
                (meeting_id, total_members_present, total_late_members,
                 total_visiting_rotarians, total_guests, total_attendees,
                 total_certificates_sent, report_generated_at)
            SELECT
                m.id,
                COUNT(DISTINCT ma.id),
                SUM(CASE WHEN ma.is_late = 1 THEN 1 ELSE 0 END),
                COUNT(DISTINCT vr.id),
                COUNT(DISTINCT g.id),
                COUNT(DISTINCT ma.id) + COUNT(DISTINCT vr.id) + COUNT(DISTINCT g.id),
                (SELECT COUNT(*) FROM certificates c WHERE c.meeting_id = m.id AND c.email_sent = 1),
                NOW()
            FROM meetings m
            LEFT JOIN member_attendance  ma ON ma.meeting_id = m.id
            LEFT JOIN visiting_rotarians vr ON vr.meeting_id = m.id
            LEFT JOIN guests             g  ON g.meeting_id  = m.id
            WHERE m.id = ?
            GROUP BY m.id
            ON DUPLICATE KEY UPDATE
                total_members_present    = VALUES(total_members_present),
                total_late_members       = VALUES(total_late_members),
                total_visiting_rotarians = VALUES(total_visiting_rotarians),
                total_guests             = VALUES(total_guests),
                total_attendees          = VALUES(total_attendees),
                total_certificates_sent  = VALUES(total_certificates_sent),
                report_generated_at      = NOW()
        ")->execute([$meetingId]);
    }

    private function writeAudit(string  $action, string  $table, int  $refId): void
    {
         $this->pdo->prepare("
            INSERT INTO audit_log (action, target_table, target_id, ip_address)
            VALUES (?, ?, ?, ?)
        ")->execute([$action,  $table,  $refId,  $_SERVER['REMOTE_ADDR'] ?? null]);
    }
}
