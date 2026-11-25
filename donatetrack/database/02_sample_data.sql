-- =====================================================================
-- DonateTrack - Phase 2 sample data (File 2 of 3)
-- Demo password for EVERY account below:  DonateTrack#2026
-- Run after 01_schema.sql.
-- =====================================================================
USE donatetrack;

INSERT INTO users (user_id, username, password_hash, full_name, role, is_active) VALUES
(1,'coordinator','$2y$10$d.HSJwDQg4mJZJ5tWt4CdOxjntgg1bwJoKwAkUt416nloB9EfYaF.','Elena Cruz','COORDINATOR',1),
(2,'volunteer1','$2y$10$d.HSJwDQg4mJZJ5tWt4CdOxjntgg1bwJoKwAkUt416nloB9EfYaF.','Mark Tan','VOLUNTEER',1),
(3,'volunteer2','$2y$10$d.HSJwDQg4mJZJ5tWt4CdOxjntgg1bwJoKwAkUt416nloB9EfYaF.','Liza Gomez','VOLUNTEER',1),
(4,'donor1','$2y$10$d.HSJwDQg4mJZJ5tWt4CdOxjntgg1bwJoKwAkUt416nloB9EfYaF.','Maria Santos','DONOR',1),
(5,'donor2','$2y$10$d.HSJwDQg4mJZJ5tWt4CdOxjntgg1bwJoKwAkUt416nloB9EfYaF.','Cebu Rotary Club','DONOR',1),
(6,'donor3','$2y$10$d.HSJwDQg4mJZJ5tWt4CdOxjntgg1bwJoKwAkUt416nloB9EfYaF.','Banilad Bakery','DONOR',1),
(7,'volunteer3','$2y$10$d.HSJwDQg4mJZJ5tWt4CdOxjntgg1bwJoKwAkUt416nloB9EfYaF.','Ramon Dizon','VOLUNTEER',0);

INSERT INTO donation_categories (category_id, category_name, unit, description, is_active) VALUES
(1,'Rice','kg','Milled rice in kilograms',1),
(2,'Canned Goods','cans','Sardines, corned beef, meat loaf and similar',1),
(3,'Food Packs','packs','Ready-to-distribute relief food packs',1),
(4,'Clothing','pieces','Clean, wearable clothing',1),
(5,'School Supplies','sets','Notebook, pen and pad paper sets',1),
(6,'Hygiene Kits','kits','Soap, toothbrush, toothpaste, towel',1),
(7,'Medical Supplies','boxes','Not currently accepted',0);

INSERT INTO donors (donor_id, user_id, donor_name, donor_type, email, phone, address, is_active) VALUES
(1,4,'Maria Santos','INDIVIDUAL','maria.santos@example.com','0917-100-2001','Banilad, Cebu City',1),
(2,5,'Cebu Rotary Club','ORGANIZATION','rotary.cebu@example.com','032-255-0100','Osmena Blvd, Cebu City',1),
(3,6,'Banilad Bakery','BUSINESS','orders@baniladbakery.example.com','032-346-1188','Gov. M. Cuenco Ave, Banilad',1),
(4,NULL,'Juan dela Cruz','INDIVIDUAL','juan.delacruz@example.com','0928-200-3002',NULL,1),
(5,NULL,'St. Francis Parish','ORGANIZATION','stfrancis.parish@example.com','032-233-4455','Talamban, Cebu City',1),
(6,NULL,'Lim Hardware','BUSINESS','limhardware@example.com',NULL,'Mandaue City',1),
(7,NULL,'Ana Reyes','INDIVIDUAL',NULL,'0935-300-4003','Lahug, Cebu City',1),
(8,NULL,'Harmony Students Org','ORGANIZATION','harmony.org@example.com',NULL,'University of Cebu - Banilad',1),
(9,NULL,'Pedro Villanueva','INDIVIDUAL','pedro.v@example.com',NULL,NULL,0),
(10,NULL,'Gaisano Grocers','BUSINESS','csr@gaisanogrocers.example.com','032-412-9000','Cebu City',1);

INSERT INTO donations (donation_id, donor_id, category_id, recorded_by, donation_date, quantity, status, notes, cancel_reason) VALUES
(1,2,1,2,'2026-10-05',200.00,'DISTRIBUTED','Sacks of rice from Rotary fundraiser',NULL),
(2,1,2,2,'2026-10-08',150.00,'PARTIAL','Assorted canned goods',NULL),
(3,3,3,3,'2026-10-10',80.00,'DISTRIBUTED','Bread and grocery packs',NULL),
(4,4,4,2,'2026-10-12',120.00,'VERIFIED','Used clothing, sorted and cleaned',NULL),
(5,5,5,3,'2026-10-15',60.00,'PARTIAL','For the school year outreach',NULL),
(6,6,6,2,'2026-10-18',100.00,'VERIFIED',NULL,NULL),
(7,7,1,3,'2026-10-22',50.00,'CANCELLED','Pledged rice',
   'Donor withdrew; items were never delivered'),
(8,8,5,2,'2026-10-25',40.00,'RECORDED','Collected during campus drive',NULL),
(9,10,3,3,'2026-10-28',120.00,'VERIFIED','Store donation',NULL),
(10,2,2,2,'2026-11-02',200.00,'PARTIAL','Second batch',NULL),
(11,3,3,3,'2026-11-04',60.00,'RECORDED',NULL,NULL),
(12,1,4,2,'2026-11-06',45.00,'DISTRIBUTED','Children clothing',NULL),
(13,4,6,3,'2026-11-09',30.00,'VERIFIED',NULL,NULL),
(14,5,1,2,'2026-11-12',300.00,'VERIFIED','Parish rice drive',NULL),
(15,10,2,3,'2026-11-15',96.00,'RECORDED',NULL,NULL),
(16,6,5,2,'2026-11-17',25.00,'CANCELLED',NULL,'Recorded under the wrong category');

INSERT INTO distributions (distribution_id, donation_id, released_by, recipient_name, recipient_area, quantity_released, distribution_date, remarks) VALUES
(1,1,1,'Barangay Lahug Relief Desk','Lahug',120.00,'2026-10-12','First release'),
(2,1,1,'Barangay Apas Relief Desk','Apas',80.00,'2026-10-15','Final release; donation fully distributed'),
(3,2,1,'Barangay Banilad Health Center','Banilad',60.00,'2026-10-14',NULL),
(4,3,1,'Sitio Mabolo Families','Mabolo',50.00,'2026-10-16',NULL),
(5,3,1,'Barangay Guadalupe Relief Desk','Guadalupe',30.00,'2026-10-18','Final release'),
(6,5,1,'Talamban Elementary School','Talamban',25.00,'2026-10-20',NULL),
(7,10,1,'Lahug Covered Court Evacuation Center','Lahug',100.00,'2026-11-05',NULL),
(8,10,1,'Barangay Apas Relief Desk','Apas',50.00,'2026-11-09',NULL),
(9,12,1,'Barangay Apas Relief Desk','Apas',45.00,'2026-11-10','Final release');
