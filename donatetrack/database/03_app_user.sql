-- =====================================================================
-- DonateTrack - Phase 2 least-privilege application account (File 3 of 3)
-- Run as the MySQL admin (root) after 01_schema.sql and 02_sample_data.sql.
-- CHANGE THE PASSWORD before real use; it must match config/config.php.
-- =====================================================================
CREATE USER IF NOT EXISTS 'donatetrack_app'@'localhost' IDENTIFIED BY 'DonateTrack_App#2026';

GRANT SELECT, INSERT, UPDATE, DELETE ON donatetrack.users               TO 'donatetrack_app'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON donatetrack.donors              TO 'donatetrack_app'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON donatetrack.donation_categories TO 'donatetrack_app'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON donatetrack.donations           TO 'donatetrack_app'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON donatetrack.distributions       TO 'donatetrack_app'@'localhost';
-- Audit table: read-only for the application (it is filled by triggers in Phase 3).
GRANT SELECT ON donatetrack.distribution_audit TO 'donatetrack_app'@'localhost';
FLUSH PRIVILEGES;
