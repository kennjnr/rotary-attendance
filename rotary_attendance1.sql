-- ============================================================
-- DATABASE
-- ============================================================
-- CREATE DATABASE IF NOT EXISTS rotary_attendance
--   CHARACTER SET utf8mb4
--   COLLATE utf8mb4_unicode_ci;

USE rotary_attendance;

-- ============================================================
-- 1. CLUBS
--    Stores this club and visiting Rotarian home clubs
-- ============================================================
CREATE TABLE clubs (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    club_name       VARCHAR(150)        NOT NULL,
    district        VARCHAR(50)         NULL,
    city            VARCHAR(100)        NULL,
    country         VARCHAR(100)        NULL DEFAULT 'Nigeria',
    is_host_club    TINYINT(1)          NOT NULL DEFAULT 0,  -- 1 = this club
    created_at      TIMESTAMP           NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP           NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ============================================================
-- 2. MEMBERS
--    Club members of the host Rotary club
-- ============================================================
CREATE TABLE members (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    club_id         INT UNSIGNED        NOT NULL,
    first_name      VARCHAR(100)        NOT NULL,
    last_name       VARCHAR(100)        NOT NULL,
    email           VARCHAR(150)        NOT NULL UNIQUE,
    phone           VARCHAR(30)         NULL,
    rotary_id       VARCHAR(50)         NULL UNIQUE,       -- Rotary International member ID
    role            ENUM(
                        'Member',
                        'President',
                        'Secretary',
                        'Treasurer',
                        'Sergeant-at-Arms',
                        'Past President',
                        'Other'
                    )                   NOT NULL DEFAULT 'Member',
    photo_url       VARCHAR(255)        NULL,
    is_active       TINYINT(1)          NOT NULL DEFAULT 1,
    created_at      TIMESTAMP           NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP           NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_members_club FOREIGN KEY (club_id) REFERENCES clubs(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ============================================================
-- 3. MEETINGS
--    Each club meeting session
-- ============================================================
CREATE TABLE meetings (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    club_id         INT UNSIGNED        NOT NULL,
    title           VARCHAR(200)        NOT NULL,
    meeting_date    DATE                NOT NULL,
    start_time      TIME                NOT NULL,
    end_time        TIME                NULL,
    venue           VARCHAR(255)        NULL,
    theme           VARCHAR(255)        NULL,
    qr_token        VARCHAR(100)        NOT NULL UNIQUE,    -- unique token embedded in QR code URL
    qr_expires_at   DATETIME            NULL,               -- optional expiry for QR validity
    status          ENUM(
                        'Scheduled',
                        'Open',       -- QR is live / check-in active
                        'Closed',     -- check-in ended
                        'Cancelled'
                    )                   NOT NULL DEFAULT 'Scheduled',
    created_by      INT UNSIGNED        NULL,               -- FK to admin_users
    created_at      TIMESTAMP           NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP           NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_meetings_club FOREIGN KEY (club_id) REFERENCES clubs(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ============================================================
-- 4. ATTENDANCE — CLUB MEMBERS
--    Check-in records for host club members
-- ============================================================
CREATE TABLE member_attendance (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    meeting_id      INT UNSIGNED        NOT NULL,
    member_id       INT UNSIGNED        NOT NULL,
    check_in_time   DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    is_late         TINYINT(1)          NOT NULL DEFAULT 0,  -- flagged if after meeting start_time
    remarks         VARCHAR(255)        NULL,
    certificate_id  INT UNSIGNED        NULL,                -- FK to certificates (set after issue)
    CONSTRAINT fk_ma_meeting  FOREIGN KEY (meeting_id)  REFERENCES meetings(id) ON DELETE CASCADE,
    CONSTRAINT fk_ma_member   FOREIGN KEY (member_id)   REFERENCES members(id)  ON DELETE CASCADE,
    UNIQUE KEY uq_member_meeting (meeting_id, member_id)    -- prevent duplicate check-in
) ENGINE=InnoDB;

-- ============================================================
-- 5. VISITING ROTARIANS
--    Rotarians from other clubs attending the meeting
-- ============================================================
CREATE TABLE visiting_rotarians (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    meeting_id      INT UNSIGNED        NOT NULL,
    first_name      VARCHAR(100)        NOT NULL,
    last_name       VARCHAR(100)        NOT NULL,
    email           VARCHAR(150)        NOT NULL,
    phone           VARCHAR(30)         NULL,
    rotary_id       VARCHAR(50)         NULL,               -- their Rotary International ID
    home_club_id    INT UNSIGNED        NULL,               -- FK to clubs if club already exists
    home_club_name  VARCHAR(150)        NOT NULL,           -- free-text fallback
    district        VARCHAR(50)         NULL,
    role_in_club    VARCHAR(100)        NULL,               -- e.g. President, Member
    check_in_time   DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    is_late         TINYINT(1)          NOT NULL DEFAULT 0,
    certificate_id  INT UNSIGNED        NULL,
    CONSTRAINT fk_vr_meeting   FOREIGN KEY (meeting_id)   REFERENCES meetings(id)  ON DELETE CASCADE,
    CONSTRAINT fk_vr_home_club FOREIGN KEY (home_club_id) REFERENCES clubs(id)     ON DELETE SET NULL,
    UNIQUE KEY uq_visitor_meeting (meeting_id, email)       -- one check-in per email per meeting
) ENGINE=InnoDB;

-- ============================================================
-- 6. GUESTS
--    Non-Rotarian guests attending the meeting
-- ============================================================
CREATE TABLE guests (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    meeting_id      INT UNSIGNED        NOT NULL,
    first_name      VARCHAR(100)        NOT NULL,
    last_name       VARCHAR(100)        NOT NULL,
    email           VARCHAR(150)        NOT NULL,
    phone           VARCHAR(30)         NULL,
    organization    VARCHAR(150)        NULL,
    host_member_id  INT UNSIGNED        NULL,               -- which member invited them
    check_in_time   DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    is_late         TINYINT(1)          NOT NULL DEFAULT 0,
    certificate_id  INT UNSIGNED        NULL,
    CONSTRAINT fk_guests_meeting FOREIGN KEY (meeting_id)     REFERENCES meetings(id) ON DELETE CASCADE,
    CONSTRAINT fk_guests_host   FOREIGN KEY (host_member_id)  REFERENCES members(id)  ON DELETE SET NULL,
    UNIQUE KEY uq_guest_meeting (meeting_id, email)
) ENGINE=InnoDB;

-- ============================================================
-- 7. CERTIFICATES
--    Attendance certificates issued per check-in
-- ============================================================
CREATE TABLE certificates (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    certificate_no      VARCHAR(80)     NOT NULL UNIQUE,    -- e.g. CERT-2026-0001
    meeting_id          INT UNSIGNED    NOT NULL,
    attendee_type       ENUM(
                            'Member',
                            'Visiting Rotarian',
                            'Guest'
                        )               NOT NULL,
    attendee_ref_id     INT UNSIGNED    NOT NULL,           -- ID from member_attendance / visiting_rotarians / guests
    recipient_name      VARCHAR(200)    NOT NULL,
    recipient_email     VARCHAR(150)    NOT NULL,
    issued_at           DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    file_path           VARCHAR(255)    NULL,               -- path to generated PDF on server
    email_sent          TINYINT(1)      NOT NULL DEFAULT 0,
    email_sent_at       DATETIME        NULL,
    email_error         TEXT            NULL,               -- store error message if delivery fails
    CONSTRAINT fk_cert_meeting FOREIGN KEY (meeting_id) REFERENCES meetings(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- 8. MEETING SUMMARY REPORT (materialized/cached)
--    Pre-aggregated per meeting for fast report generation
-- ============================================================
CREATE TABLE meeting_summary (
    id                      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    meeting_id              INT UNSIGNED    NOT NULL UNIQUE,
    total_members_present   INT UNSIGNED    NOT NULL DEFAULT 0,
    total_late_members      INT UNSIGNED    NOT NULL DEFAULT 0,
    total_visiting_rotarians INT UNSIGNED   NOT NULL DEFAULT 0,
    total_guests            INT UNSIGNED    NOT NULL DEFAULT 0,
    total_attendees         INT UNSIGNED    NOT NULL DEFAULT 0,  -- computed sum
    total_certificates_sent INT UNSIGNED    NOT NULL DEFAULT 0,
    report_generated_at     DATETIME        NULL,
    generated_by            INT UNSIGNED    NULL,               -- FK to admin_users
    CONSTRAINT fk_ms_meeting FOREIGN KEY (meeting_id) REFERENCES meetings(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- 9. ADMIN USERS
--    Secretary, President, or system admins
-- ============================================================
CREATE TABLE admin_users (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    member_id       INT UNSIGNED        NULL,               -- optionally linked to a club member
    username        VARCHAR(80)         NOT NULL UNIQUE,
    email           VARCHAR(150)        NOT NULL UNIQUE,
    password_hash   VARCHAR(255)        NOT NULL,           -- bcrypt / Argon2
    role            ENUM(
                        'Super Admin',
                        'Secretary',
                        'President',
                        'Attendance Officer'
                    )                   NOT NULL DEFAULT 'Secretary',
    last_login      DATETIME            NULL,
    is_active       TINYINT(1)          NOT NULL DEFAULT 1,
    created_at      TIMESTAMP           NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP           NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_admin_member FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ============================================================
-- 10. AUDIT LOG
--     Tracks all key system actions
-- ============================================================
CREATE TABLE audit_log (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    admin_id    INT UNSIGNED        NULL,
    action      VARCHAR(100)        NOT NULL,   -- e.g. 'CHECKIN', 'CERT_SENT', 'REPORT_GENERATED'
    target_table VARCHAR(80)        NULL,
    target_id   INT UNSIGNED        NULL,
    description TEXT                NULL,
    ip_address  VARCHAR(45)         NULL,
    created_at  TIMESTAMP           NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_audit_admin FOREIGN KEY (admin_id) REFERENCES admin_users(id) ON DELETE SET NULL
) ENGINE=InnoDB;
