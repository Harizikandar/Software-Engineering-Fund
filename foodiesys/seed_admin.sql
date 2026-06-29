-- Run this once after importing foodiesys.sql so you can log in.
-- Email: admin@foodiesys.test
-- Password: Admin123!
INSERT INTO admins (admin_name, email, password, role)
VALUES ('System Administrator', 'admin@foodiesys.test', '$2y$12$.zRMkvL9U.D/aWZCQDsxJulE7g3aIoNS3m3wmR5Ykxt2h5egJa2HO', 'admin');
