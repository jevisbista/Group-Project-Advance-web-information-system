-- Import into a separately selected, empty database. This file never creates or drops a database.
-- All historical relationships use RESTRICT: review ownership/history before any manual deletion.
CREATE TABLE members (
    member_id INT NOT NULL AUTO_INCREMENT,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    student_id VARCHAR(20) NOT NULL,
    email VARCHAR(100) NOT NULL,
    phone VARCHAR(20) NULL,
    password_hash VARCHAR(255) NOT NULL,
    membership_type ENUM('standard','life') NOT NULL DEFAULT 'standard',
    join_date DATE NOT NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    PRIMARY KEY (member_id),
    UNIQUE KEY uq_members_student (student_id),
    UNIQUE KEY uq_members_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE committee (
    committee_id INT NOT NULL AUTO_INCREMENT,
    member_id INT NOT NULL,
    role VARCHAR(50) NOT NULL,
    term_start DATE NOT NULL,
    term_end DATE NULL,
    PRIMARY KEY (committee_id),
    KEY idx_committee_member_term (member_id, term_start, term_end),
    CONSTRAINT fk_committee_member FOREIGN KEY (member_id) REFERENCES members(member_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT chk_committee_term CHECK (term_end IS NULL OR term_end >= term_start)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE events (
    event_id INT NOT NULL AUTO_INCREMENT,
    event_name VARCHAR(100) NOT NULL,
    description TEXT NOT NULL,
    event_date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    location VARCHAR(150) NOT NULL,
    capacity INT NOT NULL,
    created_by INT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (event_id),
    KEY idx_events_date_time (event_date, start_time),
    KEY idx_events_creator (created_by),
    CONSTRAINT fk_events_creator FOREIGN KEY (created_by) REFERENCES committee(committee_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT chk_events_capacity CHECK (capacity > 0),
    CONSTRAINT chk_events_times CHECK (start_time >= '00:00:00' AND end_time < '24:00:00' AND end_time > start_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE registrations (
    registration_id INT NOT NULL AUTO_INCREMENT,
    event_id INT NOT NULL,
    member_id INT NOT NULL,
    registration_date TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    attendance_status ENUM('registered','attended','cancelled') NOT NULL DEFAULT 'registered',
    PRIMARY KEY (registration_id),
    UNIQUE KEY uq_registrations_event_member (event_id, member_id),
    KEY idx_registrations_member (member_id),
    KEY idx_registrations_event_status (event_id, attendance_status),
    CONSTRAINT fk_registrations_event FOREIGN KEY (event_id) REFERENCES events(event_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_registrations_member FOREIGN KEY (member_id) REFERENCES members(member_id) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
