-- =====================================================
-- ChemInventory AI - Sample Data
-- Includes: Users, Organizations, Labs, Chemicals, Locations, Containers, Transactions
-- =====================================================

USE chem_inventory_db;

-- =====================================================
-- 1. ORGANIZATIONS
-- =====================================================
INSERT INTO organizations (id, name, description, address, phone, email) VALUES
(1, 'มหาวิทยาลัยเทคโนโลยีแห่งประเทศไทย', 'มหาวิทยาลัยชั้นนำด้านวิทยาศาสตร์และเทคโนโลยี', 'ถนนพระราม 1, กรุงเทพฯ', '02-123-4567', 'admin@tu.ac.th'),
(2, 'บริษัท ไทยเคมีภัณฑ์ จำกัด', 'บริษัทผลิตและจำหน่ายสารเคมีอุตสาหกรรม', 'นิคมอุตสาหกรรมระยอง', '038-123-456', 'contact@thaichem.co.th');

-- =====================================================
-- 2. LABS
-- =====================================================
INSERT INTO labs (id, organization_id, name, code, description, manager_id) VALUES
(1, 1, 'ห้องปฏิบัติการเคมีอินทรีย์', 'ORG-001', 'ห้องปฏิบัติการสำหรับการทดลองเคมีอินทรีย์', 5),
(2, 1, 'ห้องปฏิบัติการเคมีอนินทรีย์', 'INORG-001', 'ห้องปฏิบัติการสำหรับการทดลองเคมีอนินทรีย์', 6),
(3, 1, 'ห้องปฏิบัติการชีวเคมี', 'BIOCHEM-001', 'ห้องปฏิบัติการสำหรับการทดลองชีวเคมี', 7),
(4, 1, 'ห้องปฏิบัติการวิเคราะห์', 'ANAL-001', 'ห้องปฏิบัติการสำหรับการวิเคราะห์สาร', 8),
(5, 2, 'ห้องปฏิบัติการ QC', 'QC-001', 'ห้องปฏิบัติการควบคุมคุณภาพ', 5);

-- =====================================================
-- 3. USERS (with specified credentials)
-- =====================================================
-- Password for all: 123 (hashed with bcrypt)
-- Hash: $2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi

INSERT INTO users (id, organization_id, role_id, lab_id, manager_id, username, email, password_hash, first_name, last_name, phone, department, position, theme_preference, language, email_verified, is_active) VALUES
-- Admin users
(1, 1, 1, NULL, NULL, 'admin1', 'admin1@tu.ac.th', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'สมชาย', 'แอดมิน', '081-111-1111', 'ไอที', 'ผู้ดูแลระบบ', 'light', 'th', 1, 1),
(2, 1, 1, NULL, NULL, 'admin2', 'admin2@tu.ac.th', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'สมหญิง', 'แอดมิน', '081-111-1112', 'ไอที', 'ผู้ดูแลระบบ', 'dark', 'th', 1, 1),

-- CEO users
(3, 1, 4, NULL, NULL, 'ceo1', 'ceo1@tu.ac.th', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'ประธาน', 'หนึ่ง', '082-222-2221', 'บริหาร', 'ประธานเจ้าหน้าที่บริหาร', 'light', 'th', 1, 1),
(4, 2, 4, NULL, NULL, 'ceo2', 'ceo2@thaichem.co.th', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'ประธาน', 'สอง', '082-222-2222', 'บริหาร', 'ประธานเจ้าหน้าที่บริหาร', 'light', 'th', 1, 1),

-- Lab Managers
(5, 1, 3, 1, NULL, 'lab1', 'lab1@tu.ac.th', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'ผู้จัดการ', 'หนึ่ง', '083-333-3331', 'เคมี', 'ผู้จัดการห้องปฏิบัติการ', 'light', 'th', 1, 1),
(6, 1, 3, 2, NULL, 'lab2', 'lab2@tu.ac.th', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'ผู้จัดการ', 'สอง', '083-333-3332', 'เคมี', 'ผู้จัดการห้องปฏิบัติการ', 'light', 'th', 1, 1),
(7, 1, 3, 3, NULL, 'lab3', 'lab3@tu.ac.th', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'ผู้จัดการ', 'สาม', '083-333-3333', 'ชีววิทยา', 'ผู้จัดการห้องปฏิบัติการ', 'light', 'th', 1, 1),
(8, 1, 3, 4, NULL, 'lab4', 'lab4@tu.ac.th', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'ผู้จัดการ', 'สี่', '083-333-3334', 'วิทยาศาสตร์', 'ผู้จัดการห้องปฏิบัติการ', 'dark', 'th', 1, 1),

-- Regular Users (Lab 1 - Organic Chemistry)
(9, 1, 2, 1, 5, 'user1', 'user1@tu.ac.th', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'นักวิจัย', 'หนึ่ง', '084-444-4441', 'เคมี', 'นักวิจัย', 'light', 'th', 1, 1),
(10, 1, 2, 1, 5, 'user2', 'user2@tu.ac.th', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'นักวิจัย', 'สอง', '084-444-4442', 'เคมี', 'นักวิจัย', 'light', 'en', 1, 1),

-- Regular Users (Lab 2 - Inorganic Chemistry)
(11, 1, 2, 2, 6, 'user3', 'user3@tu.ac.th', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'นักวิจัย', 'สาม', '084-444-4443', 'เคมี', 'นักวิจัย', 'dark', 'th', 1, 1),
(12, 1, 2, 2, 6, 'user4', 'user4@tu.ac.th', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'นักวิจัย', 'สี่', '084-444-4444', 'เคมี', 'นักวิทยาศาสตร์', 'light', 'th', 1, 1),

-- Regular Users (Lab 3 - Biochemistry)
(13, 1, 2, 3, 7, 'user5', 'user5@tu.ac.th', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'นักวิจัย', 'ห้า', '084-444-4445', 'ชีววิทยา', 'นักชีวเคมี', 'light', 'th', 1, 1),
(14, 1, 2, 3, 7, 'user6', 'user6@tu.ac.th', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'นักวิจัย', 'หก', '084-444-4446', 'ชีววิทยา', 'นักชีวเคมี', 'dark', 'en', 1, 1);

-- Create notification settings for all users
INSERT INTO notification_settings (user_id) 
SELECT id FROM users;

-- =====================================================
-- 4. CHEMICAL CATEGORIES
-- =====================================================
INSERT INTO chemical_categories (id, name, description, parent_id) VALUES
(1, 'กรด', 'สารเคมีที่มีสภาพเป็นกรด', NULL),
(2, 'เบส', 'สารเคมีที่มีสภาพเป็นเบส', NULL),
(3, 'ตัวทำละลาย', 'สารที่ใช้ละลายสารอื่น', NULL),
(4, 'สารเคมีอินทรีย์', 'สารเคมีที่มีคาร์บอนเป็นส่วนประกอบหลัก', NULL),
(5, 'สารเคมีอนินทรีย์', 'สารเคมีที่ไม่มีคาร์บอน', NULL),
(6, 'สารละลายบัฟเฟอร์', 'สารละลายที่รักษาค่า pH', NULL),
(7, 'สารเคมีอันตราย', 'สารเคมีที่มีความอันตรายสูง', NULL);

-- =====================================================
-- 5. CHEMICALS (with GHS data)
-- =====================================================
INSERT INTO chemicals (id, cas_number, name, iupac_name, synonyms, molecular_formula, molecular_weight, description, category_id, physical_state, appearance, ghs_classifications, hazard_pictograms, signal_word, hazard_statements, precautionary_statements, handling_procedures, storage_requirements, created_by) VALUES
-- Acids
(1, '7647-01-0', 'กรดไฮโดรคลอริก', 'Hydrochloric acid', '["HCl", "Muriatic acid"]', 'HCl', 36.46, 'กรดแก่ที่ใช้กันอย่างแพร่หลายในอุตสาหกรรมและห้องปฏิบัติการ', 1, 'liquid', 'ของเหลวใสไม่มีสี', '["Skin Corr. 1A", "STOT SE 3"]', '["corrosive"]', 'Danger', '["H314: Causes severe skin burns and eye damage", "H335: May cause respiratory irritation"]', '["P280: Wear protective gloves/protective clothing/eye protection/face protection", "P310: Immediately call a POISON CENTER/doctor"]', 'ใช้ในที่ระบายอากาศดี สวมอุปกรณ์ป้องกันส่วนบุคคล', 'เก็บในภาชนะที่ทนต่อกรด ปิดฝาให้สนิท เก็บในที่แห้งเย็น', 1),
(2, '7664-93-9', 'กรดซัลฟิวริก', 'Sulfuric acid', '["H2SO4", "Oil of vitriol"]', 'H2SO4', 98.08, 'กรดแก่ที่ใช้ในอุตสาหกรรมมากที่สุด', 1, 'liquid', 'ของเหลวใสไม่มีสี ข้นขึ้นเมื่อเจอความชื้น', '["Skin Corr. 1A"]', '["corrosive"]', 'Danger', '["H314: Causes severe skin burns and eye damage"]', '["P280: Wear protective gloves/protective clothing/eye protection/face protection", "P301+P330+P331: IF SWALLOWED: Rinse mouth. Do NOT induce vomiting"]', 'เทกรดลงในน้ำอย่างช้าๆ ไม่เทน้ำลงในกรด', 'เก็บในภาชนะพลาสติกหรือแก้วที่ทนต่อกรด', 1),
(3, '64-19-7', 'กรดแอซิติก', 'Acetic acid', '["CH3COOH", "Ethanoic acid", "Vinegar acid"]', 'C2H4O2', 60.05, 'กรดอินทรีย์ที่ใช้กันอย่างแพร่หลาย', 1, 'liquid', 'ของเหลวใสไม่มีสี มีกลิ่นฉุน', '["Flam. Liq. 3", "Skin Corr. 1A"]', '["flammable", "corrosive"]', 'Danger', '["H226: Flammable liquid and vapor", "H314: Causes severe skin burns and eye damage"]', '["P210: Keep away from heat/sparks/open flames/hot surfaces", "P280: Wear protective gloves/protective clothing/eye protection"]', 'หลีกเลี่ยงการสัมผัสกับผิวหนังและดวงตา', 'เก็บในที่ระบายอากาศดี ห่างจากแหล่งความร้อน', 1),

-- Bases
(4, '1310-73-2', 'โซเดียมไฮดรอกไซด์', 'Sodium hydroxide', '["NaOH", "Caustic soda", "Lye"]', 'NaOH', 40.00, 'เบสแก่ที่ใช้กันอย่างแพร่หลาย', 2, 'solid', 'ผลึกสีขาว', '["Skin Corr. 1A"]', '["corrosive"]', 'Danger', '["H314: Causes severe skin burns and eye damage"]', '["P280: Wear protective gloves/protective clothing/eye protection/face protection", "P301+P330+P331: IF SWALLOWED: Rinse mouth. Do NOT induce vomiting"]', 'สวมอุปกรณ์ป้องกันส่วนบุคคลครบชุด', 'เก็บในภาชนะที่ปิดสนิท ห่างจากความชื้น', 1),
(5, '1336-21-6', 'แอมโมเนียมไฮดรอกไซด์', 'Ammonium hydroxide', '["NH4OH", "Ammonia solution", "Aqua ammonia"]', 'NH5O', 35.05, 'สารละลายเบสอ่อน', 2, 'liquid', 'ของเหลวใสไม่มีสี มีกลิ่นฉุน', '["Skin Corr. 1B", "STOT SE 3", "Aquatic Acute 1"]', '["corrosive"]', 'Danger', '["H314: Causes severe skin burns and eye damage", "H335: May cause respiratory irritation", "H400: Very toxic to aquatic life"]', '["P280: Wear protective gloves/protective clothing/eye protection/face protection", "P273: Avoid release to the environment"]', 'ใช้ในที่ระบายอากาศดี', 'เก็บในภาชนะที่ปิดสนิท ในที่เย็น', 1),

-- Solvents
(6, '67-64-1', 'อะซิโตน', 'Acetone', '["Propanone", "Dimethyl ketone"]', 'C3H6O', 58.08, 'ตัวทำละลายอินทรีย์ที่ใช้กันอย่างแพร่หลาย', 3, 'liquid', 'ของเหลวใสไม่มีสี มีกลิ่นหวานฉุน', '["Flam. Liq. 2", "Eye Irrit. 2", "STOT SE 3"]', '["flammable"]', 'Danger', '["H225: Highly flammable liquid and vapor", "H319: Causes serious eye irritation", "H336: May cause drowsiness or dizziness"]', '["P210: Keep away from heat/sparks/open flames/hot surfaces", "P280: Wear protective gloves/protective clothing/eye protection"]', 'ห่างจากแหล่งจุดระเบิด', 'เก็บในภาชนะที่ปิดสนิท ห่างจากแหล่งความร้อน', 1),
(7, '64-17-5', 'เอทานอล', 'Ethanol', '["Ethyl alcohol", "Alcohol", "Grain alcohol"]', 'C2H6O', 46.07, 'แอลกอฮอล์ที่ใช้ในห้องปฏิบัติการและอุตสาหกรรม', 3, 'liquid', 'ของเหลวใสไม่มีสี มีกลิ่นแอลกอฮอล์', '["Flam. Liq. 2"]', '["flammable"]', 'Danger', '["H225: Highly flammable liquid and vapor"]', '["P210: Keep away from heat/sparks/open flames/hot surfaces", "P280: Wear protective gloves/protective clothing/eye protection"]', 'ห่างจากแหล่งจุดระเบิด ห้ามสูบบุหรี่', 'เก็บในภาชนะที่ปิดสนิท ห่างจากแหล่งความร้อน', 1),
(8, '67-63-0', 'ไอโซโพรพานอล', 'Isopropyl alcohol', '["IPA", "Isopropanol", "2-Propanol"]', 'C3H8O', 60.10, 'แอลกอฮอล์ที่ใช้ทำความสะอาดและฆ่าเชื้อ', 3, 'liquid', 'ของเหลวใสไม่มีสี มีกลิ่นแอลกอฮอล์', '["Flam. Liq. 2", "Eye Irrit. 2", "STOT SE 3"]', '["flammable"]', 'Danger', '["H225: Highly flammable liquid and vapor", "H319: Causes serious eye irritation", "H336: May cause drowsiness or dizziness"]', '["P210: Keep away from heat/sparks/open flames/hot surfaces", "P280: Wear protective gloves/protective clothing/eye protection"]', 'ใช้ในที่ระบายอากาศดี', 'เก็บในภาชนะที่ปิดสนิท ห่างจากแหล่งความร้อน', 1),
(9, '75-09-2', 'ไดคลอโรเมเทน', 'Dichloromethane', '["Methylene chloride", "DCM"]', 'CH2Cl2', 84.93, 'ตัวทำละลายคลอรินเนต', 3, 'liquid', 'ของเหลวใสไม่มีสี มีกลิ่นหวาน', '["Carc. 2", "STOT SE 3"]', '["health_hazard"]', 'Danger', '["H351: Suspected of causing cancer", "H336: May cause drowsiness or dizziness"]', '["P280: Wear protective gloves/protective clothing/eye protection/face protection", "P308+P313: IF exposed or concerned: Get medical advice/attention"]', 'ใช้ในตู้ดูดควันเท่านั้น', 'เก็บในตู้ดูดควัน ปิดฝาให้สนิท', 1),

-- Organic Chemicals
(10, '50-00-0', 'ฟอร์มาลดีไฮด์', 'Formaldehyde', '["Formalin", "Methanal"]', 'CH2O', 30.03, 'สารที่ใช้ในการถนอมสารและการทดลอง', 4, 'liquid', 'ของเหลวใสไม่มีสี มีกลิ่นฉุน', '["Carc. 1B", "Acute Tox. 3", "Skin Corr. 1B", "Skin Sens. 1"]', '["toxic", "corrosive", "health_hazard"]', 'Danger', '["H301: Toxic if swallowed", "H311: Toxic in contact with skin", "H314: Causes severe skin burns and eye damage", "H317: May cause an allergic skin reaction", "H350: May cause cancer"]', '["P201: Obtain special instructions before use", "P280: Wear protective gloves/protective clothing/eye protection/face protection", "P301+P310: IF SWALLOWED: Immediately call a POISON CENTER/doctor"]', 'ใช้ในตู้ดูดควันเท่านั้น สวมอุปกรณ์ป้องกันส่วนบุคคลครบชุด', 'เก็บในตู้ดูดควัน ที่อุณหภูมิห้อง', 1),
(11, '108-88-3', 'โทลูอีน', 'Toluene', '["Methylbenzene", "Phenylmethane"]', 'C7H8', 92.14, 'ตัวทำละลายอะรอมาติก', 4, 'liquid', 'ของเหลวใสไม่มีสี มีกลิ่นหวาน', '["Flam. Liq. 2", "Repr. 2", "STOT RE 2", "STOT SE 3", "Asp. Tox. 1"]', '["flammable", "health_hazard"]', 'Danger', '["H225: Highly flammable liquid and vapor", "H361d: Suspected of damaging the unborn child", "H373: May cause damage to organs through prolonged or repeated exposure", "H336: May cause drowsiness or dizziness", "H304: May be fatal if swallowed and enters airways"]', '["P201: Obtain special instructions before use", "P210: Keep away from heat/sparks/open flames/hot surfaces", "P280: Wear protective gloves/protective clothing/eye protection"]', 'ห่างจากแหล่งจุดระเบิด ใช้ในที่ระบายอากาศดี', 'เก็บในภาชนะที่ปิดสนิท ห่างจากแหล่งความร้อน', 1),

-- Inorganic Chemicals
(12, '7447-40-7', 'โพแทสเซียมคลอไรด์', 'Potassium chloride', '["KCl", "Potash"]', 'KCl', 74.55, 'เกลือที่ใช้ในห้องปฏิบัติการ', 5, 'solid', 'ผลึกสีขาว', '[]', '[]', 'No signal word', '[]', '["P264: Wash thoroughly after handling"]', 'หลีกเลี่ยงการสูดดมฝุ่น', 'เก็บในที่แห้ง', 1),
(13, '7647-14-5', 'โซเดียมคลอไรด์', 'Sodium chloride', '["NaCl", "Table salt", "Halite"]', 'NaCl', 58.44, 'เกลือที่ใช้กันอย่างแพร่หลาย', 5, 'solid', 'ผลึกสีขาว', '[]', '[]', 'No signal word', '[]', '["P264: Wash thoroughly after handling"]', 'หลีกเลี่ยงการสูดดมฝุ่น', 'เก็บในที่แห้ง', 1),

-- Buffers
(14, '9005-49-6', 'HEPES', '4-(2-hydroxyethyl)-1-piperazineethanesulfonic acid', '["HEPES buffer"]', 'C8H18N2O4S', 238.30, 'บัฟเฟอร์ที่ใช้ในการทดลองชีวภาพ', 6, 'solid', 'ผงสีขาว', '[]', '[]', 'No signal word', '[]', '["P264: Wash thoroughly after handling", "P280: Wear protective gloves/protective clothing/eye protection"]', 'หลีกเลี่ยงการสูดดมฝุ่น', 'เก็บในที่แห้งเย็น', 1),

-- Hazardous Chemicals
(15, '7782-50-5', 'คลอรีน', 'Chlorine', '["Cl2"]', 'Cl2', 70.91, 'ก๊าซพิษที่ใช้ในการฆ่าเชื้อ', 7, 'gas', 'ก๊าซสีเหลืองอ่อน มีกลิ่นฉุน', '["Ox. Gas 1", "Acute Tox. 2", "Skin Corr. 1B", "Aquatic Acute 1"]', '["oxidizing", "toxic", "corrosive"]', 'Danger', '["H270: May cause or intensify fire; oxidizer", "H330: Fatal if inhaled", "H314: Causes severe skin burns and eye damage", "H400: Very toxic to aquatic life"]', '["P220: Keep/Store away from clothing/combustible materials", "P260: Do not breathe gas", "P280: Wear protective gloves/protective clothing/eye protection/face protection", "P310: Immediately call a POISON CENTER/doctor"]', 'ใช้ในตู้ดูดควันเท่านั้น มีระบบตรวจจับก๊าซรั่ว', 'เก็บในภาชนะพิเศษสำหรับก๊าซ ในที่ระบายอากาศดี', 1),
(16, '7664-41-7', 'แอมโมเนีย', 'Ammonia', '["NH3", "Ammonia gas"]', 'NH3', 17.03, 'ก๊าซที่ใช้ในอุตสาหกรรมและห้องปฏิบัติการ', 7, 'gas', 'ก๊าซไม่มีสี มีกลิ่นฉุน', '["Acute Tox. 3", "Skin Corr. 1B", "STOT SE 3", "Aquatic Acute 1"]', '["toxic", "corrosive"]', 'Danger', '["H331: Toxic if inhaled", "H314: Causes severe skin burns and eye damage", "H335: May cause respiratory irritation", "H400: Very toxic to aquatic life"]', '["P260: Do not breathe gas", "P280: Wear protective gloves/protective clothing/eye protection/face protection", "P310: Immediately call a POISON CENTER/doctor"]', 'ใช้ในตู้ดูดควันเท่านั้น', 'เก็บในภาชนะพิเศษสำหรับก๊าซ ในที่เย็น', 1),
(17, '75-15-0', 'คาร์บอนไดซัลไฟด์', 'Carbon disulfide', '["CS2"]', 'CS2', 76.14, 'ตัวทำละลายที่มีความอันตรายสูง', 7, 'liquid', 'ของเหลวใสไม่มีสี มีกลิ่นฉุน', '["Flam. Liq. 1", "Acute Tox. 3", "Repr. 2", "STOT RE 2"]', '["flammable", "toxic", "health_hazard"]', 'Danger', '["H224: Extremely flammable liquid and vapor", "H301: Toxic if swallowed", "H311: Toxic in contact with skin", "H361f: Suspected of damaging fertility", "H373: May cause damage to organs through prolonged or repeated exposure"]', '["P210: Keep away from heat/sparks/open flames/hot surfaces", "P280: Wear protective gloves/protective clothing/eye protection/face protection", "P301+P310: IF SWALLOWED: Immediately call a POISON CENTER/doctor"]', 'ห่างจากแหล่งจุดระเบิดอย่างเคร่งครัด ใช้ในตู้ดูดควัน', 'เก็บในตู้ดูดควัน ห่างจากแหล่งความร้อน', 1);

-- =====================================================
-- 6. BUILDINGS
-- =====================================================
INSERT INTO buildings (id, organization_id, name, code, address) VALUES
(1, 1, 'อาคารวิทยาศาสตร์ 1', 'SCI-01', 'ถนนพระราม 1, กรุงเทพฯ'),
(2, 1, 'อาคารวิทยาศาสตร์ 2', 'SCI-02', 'ถนนพระราม 1, กรุงเทพฯ'),
(3, 2, 'อาคารโรงงานผลิต', 'FACT-01', 'นิคมอุตสาหกรรมระยอง');

-- =====================================================
-- 7. ROOMS
-- =====================================================
INSERT INTO rooms (id, building_id, lab_id, name, code, room_number, floor, safety_level, temperature_controlled, humidity_controlled) VALUES
(1, 1, 1, 'ห้องปฏิบัติการเคมีอินทรีย์ 101', 'ORG-101', '101', 1, 'chemical', 1, 0),
(2, 1, 1, 'ห้องเก็บสารเคมี', 'CHEM-STORE', '102', 1, 'chemical', 1, 0),
(3, 1, 2, 'ห้องปฏิบัติการเคมีอนินทรีย์ 201', 'INORG-201', '201', 2, 'chemical', 1, 0),
(4, 2, 3, 'ห้องปฏิบัติการชีวเคมี 301', 'BIOCHEM-301', '301', 3, 'biohazard', 1, 1),
(5, 2, 4, 'ห้องวิเคราะห์ 401', 'ANAL-401', '401', 4, 'chemical', 1, 0),
(6, 3, 5, 'ห้อง QC 101', 'QC-101', '101', 1, 'chemical', 1, 0);

-- =====================================================
-- 8. CABINETS
-- =====================================================
INSERT INTO cabinets (id, room_id, name, code, type, capacity, ventilation, fire_resistant, position_x, position_y, width, height) VALUES
(1, 1, 'ตู้เก็บสารไวไฟ', 'CAB-FLAM-01', 'safety_cabinet', 4, 1, 1, 10, 20, 120, 180),
(2, 1, 'ตู้เก็บสารกัดกร่อน', 'CAB-CORR-01', 'storage', 5, 0, 0, 150, 20, 100, 200),
(3, 1, 'ตู้ดูดควัน A', 'FUME-A01', 'fume_hood', 3, 1, 0, 50, 100, 150, 80),
(4, 2, 'ตู้เย็นเก็บสาร', 'FRIDGE-01', 'refrigerator', 6, 0, 0, 10, 10, 80, 180),
(5, 2, 'ตู้เก็บสารทั่วไป A', 'CAB-GEN-01', 'storage', 8, 0, 0, 100, 10, 120, 200),
(6, 2, 'ตู้เก็บสารทั่วไป B', 'CAB-GEN-02', 'storage', 8, 0, 0, 240, 10, 120, 200),
(7, 3, 'ตู้ดูดควัน B', 'FUME-B01', 'fume_hood', 3, 1, 0, 30, 50, 150, 80),
(8, 3, 'ตู้เก็บสารอนินทรีย์', 'CAB-INORG-01', 'storage', 6, 0, 0, 200, 50, 100, 200),
(9, 4, 'ตู้เย็นชีวเคมี', 'FRIDGE-BIO', 'refrigerator', 4, 0, 0, 10, 10, 80, 180),
(10, 4, 'ตู้ดูดควัน C', 'FUME-C01', 'fume_hood', 3, 1, 0, 100, 50, 150, 80),
(11, 5, 'ตู้เก็บสารวิเคราะห์', 'CAB-ANAL-01', 'storage', 10, 0, 0, 50, 20, 150, 200),
(12, 6, 'ตู้ QC', 'CAB-QC-01', 'storage', 8, 0, 0, 20, 20, 120, 200);

-- =====================================================
-- 9. SHELVES
-- =====================================================
INSERT INTO shelves (id, cabinet_id, name, code, level, capacity) VALUES
(1, 1, 'ชั้นบน', 'SH-FLAM-01-A', 1, 10),
(2, 1, 'ชั้นกลาง', 'SH-FLAM-01-B', 2, 10),
(3, 1, 'ชั้นล่าง', 'SH-FLAM-01-C', 3, 10),
(4, 2, 'ชั้นบน', 'SH-CORR-01-A', 1, 12),
(5, 2, 'ชั้นกลาง', 'SH-CORR-01-B', 2, 12),
(6, 2, 'ชั้นล่าง', 'SH-CORR-01-C', 3, 12),
(7, 4, 'ชั้นบน', 'SH-FRIDGE-01-A', 1, 8),
(8, 4, 'ชั้นล่าง', 'SH-FRIDGE-01-B', 2, 8),
(9, 5, 'ชั้น 1', 'SH-GEN-01-A', 1, 15),
(10, 5, 'ชั้น 2', 'SH-GEN-01-B', 2, 15),
(11, 5, 'ชั้น 3', 'SH-GEN-01-C', 3, 15),
(12, 5, 'ชั้น 4', 'SH-GEN-01-D', 4, 15),
(13, 6, 'ชั้น 1', 'SH-GEN-02-A', 1, 15),
(14, 6, 'ชั้น 2', 'SH-GEN-02-B', 2, 15),
(15, 6, 'ชั้น 3', 'SH-GEN-02-C', 3, 15),
(16, 6, 'ชั้น 4', 'SH-GEN-02-D', 4, 15),
(17, 8, 'ชั้นบน', 'SH-INORG-01-A', 1, 12),
(18, 8, 'ชั้นกลาง', 'SH-INORG-01-B', 2, 12),
(19, 8, 'ชั้นล่าง', 'SH-INORG-01-C', 3, 12),
(20, 9, 'ชั้นบน', 'SH-BIO-01-A', 1, 10),
(21, 9, 'ชั้นล่าง', 'SH-BIO-01-B', 2, 10),
(22, 11, 'ชั้น 1', 'SH-ANAL-01-A', 1, 15),
(23, 11, 'ชั้น 2', 'SH-ANAL-01-B', 2, 15),
(24, 11, 'ชั้น 3', 'SH-ANAL-01-C', 3, 15),
(25, 12, 'ชั้น 1', 'SH-QC-01-A', 1, 12),
(26, 12, 'ชั้น 2', 'SH-QC-01-B', 2, 12);

-- =====================================================
-- 10. SLOTS
-- =====================================================
INSERT INTO slots (id, shelf_id, name, code, position) VALUES
(1, 1, 'ช่อง A1', 'SL-FLAM-01-A1', 1),
(2, 1, 'ช่อง A2', 'SL-FLAM-01-A2', 2),
(3, 1, 'ช่อง A3', 'SL-FLAM-01-A3', 3),
(4, 1, 'ช่อง A4', 'SL-FLAM-01-A4', 4),
(5, 1, 'ช่อง A5', 'SL-FLAM-01-A5', 5),
(6, 2, 'ช่อง B1', 'SL-FLAM-01-B1', 1),
(7, 2, 'ช่อง B2', 'SL-FLAM-01-B2', 2),
(8, 2, 'ช่อง B3', 'SL-FLAM-01-B3', 3),
(9, 2, 'ช่อง B4', 'SL-FLAM-01-B4', 4),
(10, 2, 'ช่อง B5', 'SL-FLAM-01-B5', 5),
(11, 9, 'ช่อง 1A', 'SL-GEN-01-1A', 1),
(12, 9, 'ช่อง 1B', 'SL-GEN-01-1B', 2),
(13, 9, 'ช่อง 1C', 'SL-GEN-01-1C', 3),
(14, 9, 'ช่อง 1D', 'SL-GEN-01-1D', 4),
(15, 9, 'ช่อง 1E', 'SL-GEN-01-1E', 5),
(16, 10, 'ช่อง 2A', 'SL-GEN-01-2A', 1),
(17, 10, 'ช่อง 2B', 'SL-GEN-01-2B', 2),
(18, 10, 'ช่อง 2C', 'SL-GEN-01-2C', 3),
(19, 10, 'ช่อง 2D', 'SL-GEN-01-2D', 4),
(20, 10, 'ช่อง 2E', 'SL-GEN-01-2E', 5),
(21, 17, 'ช่อง A1', 'SL-INORG-01-A1', 1),
(22, 17, 'ช่อง A2', 'SL-INORG-01-A2', 2),
(23, 17, 'ช่อง A3', 'SL-INORG-01-A3', 3),
(24, 17, 'ช่อง A4', 'SL-INORG-01-A4', 4),
(25, 20, 'ช่อง A1', 'SL-BIO-01-A1', 1),
(26, 20, 'ช่อง A2', 'SL-BIO-01-A2', 2),
(27, 20, 'ช่อง A3', 'SL-BIO-01-A3', 3),
(28, 20, 'ช่อง A4', 'SL-BIO-01-A4', 4),
(29, 22, 'ช่อง 1A', 'SL-ANAL-01-1A', 1),
(30, 22, 'ช่อง 1B', 'SL-ANAL-01-1B', 2),
(31, 22, 'ช่อง 1C', 'SL-ANAL-01-1C', 3),
(32, 22, 'ช่อง 1D', 'SL-ANAL-01-1D', 4),
(33, 25, 'ช่อง 1A', 'SL-QC-01-1A', 1),
(34, 25, 'ช่อง 1B', 'SL-QC-01-1B', 2),
(35, 25, 'ช่อง 1C', 'SL-QC-01-1C', 3),
(36, 25, 'ช่อง 1D', 'SL-QC-01-1D', 4);

-- =====================================================
-- 11. CONTAINERS (with QR codes)
-- =====================================================
INSERT INTO containers (id, qr_code, chemical_id, owner_id, lab_id, location_slot_id, container_type, container_material, container_capacity, capacity_unit, initial_quantity, current_quantity, quantity_unit, received_date, opened_date, expiry_date, status, batch_number, cost, created_by) VALUES
-- HCl containers (chemical_id = 1)
(1, 'CHEM-20240214-001A', 1, 9, 1, 2, 'bottle', 'glass', 1000, 'mL', 1000.00, 850.00, 'mL', '2024-01-15', '2024-01-20', '2026-01-15', 'active', 'HCL-2024-001', 450.00, 5),
(2, 'CHEM-20240214-001B', 1, 9, 1, 2, 'bottle', 'glass', 500, 'mL', 500.00, 120.00, 'mL', '2024-02-01', '2024-02-05', '2026-02-01', 'active', 'HCL-2024-002', 280.00, 5),
(3, 'CHEM-20240214-001C', 1, 10, 1, 2, 'bottle', 'plastic', 2500, 'mL', 2500.00, 2300.00, 'mL', '2024-01-10', NULL, '2026-01-10', 'active', 'HCL-2024-003', 950.00, 5),

-- H2SO4 containers (chemical_id = 2)
(4, 'CHEM-20240214-002A', 2, 9, 1, 4, 'bottle', 'glass', 1000, 'mL', 1000.00, 920.00, 'mL', '2024-01-20', '2024-01-25', '2027-01-20', 'active', 'H2SO4-2024-001', 520.00, 5),
(5, 'CHEM-20240214-002B', 2, 11, 2, 21, 'bottle', 'glass', 500, 'mL', 500.00, 45.00, 'mL', '2023-12-15', '2024-01-05', '2026-12-15', 'active', 'H2SO4-2023-012', 280.00, 6),

-- Acetic acid containers (chemical_id = 3)
(6, 'CHEM-20240214-003A', 3, 10, 1, 1, 'bottle', 'glass', 2500, 'mL', 2500.00, 1800.00, 'mL', '2024-02-01', '2024-02-10', '2026-02-01', 'active', 'ACETIC-2024-001', 1200.00, 5),
(7, 'CHEM-20240214-003B', 3, 10, 1, 1, 'bottle', 'plastic', 500, 'mL', 500.00, 15.00, 'mL', '2023-11-20', '2023-12-01', '2025-11-20', 'active', 'ACETIC-2023-015', 280.00, 5),

-- NaOH containers (chemical_id = 4)
(8, 'CHEM-20240214-004A', 4, 11, 2, 22, 'bottle', 'plastic', 1000, 'g', 1000.00, 750.00, 'g', '2024-01-05', '2024-01-10', '2026-01-05', 'active', 'NAOH-2024-001', 380.00, 6),
(9, 'CHEM-20240214-004B', 4, 11, 2, 22, 'bottle', 'plastic', 500, 'g', 500.00, 480.00, 'g', '2024-02-10', NULL, '2026-02-10', 'active', 'NAOH-2024-002', 200.00, 6),

-- Ammonium hydroxide containers (chemical_id = 5)
(10, 'CHEM-20240214-005A', 5, 9, 1, 3, 'bottle', 'plastic', 2500, 'mL', 2500.00, 1200.00, 'mL', '2024-01-25', '2024-02-01', '2025-01-25', 'active', 'NH4OH-2024-001', 850.00, 5),

-- Acetone containers (chemical_id = 6)
(11, 'CHEM-20240214-006A', 6, 9, 1, 1, 'canister', 'metal', 20000, 'mL', 20000.00, 15000.00, 'mL', '2024-01-15', '2024-01-20', '2025-01-15', 'active', 'ACETONE-2024-001', 3500.00, 5),
(12, 'CHEM-20240214-006B', 6, 10, 1, 6, 'bottle', 'glass', 1000, 'mL', 1000.00, 320.00, 'mL', '2024-02-05', '2024-02-10', '2025-02-05', 'active', 'ACETONE-2024-002', 450.00, 5),

-- Ethanol containers (chemical_id = 7)
(13, 'CHEM-20240214-007A', 7, 9, 1, 1, 'canister', 'metal', 20000, 'mL', 20000.00, 18500.00, 'mL', '2024-01-20', '2024-01-25', '2025-01-20', 'active', 'ETOH-2024-001', 4200.00, 5),
(14, 'CHEM-20240214-007B', 7, 13, 3, 25, 'bottle', 'glass', 2500, 'mL', 2500.00, 2100.00, 'mL', '2024-02-01', '2024-02-05', '2025-02-01', 'active', 'ETOH-2024-002', 1200.00, 7),

-- IPA containers (chemical_id = 8)
(15, 'CHEM-20240214-008A', 8, 11, 2, 23, 'bottle', 'plastic', 4000, 'mL', 4000.00, 3800.00, 'mL', '2024-01-30', '2024-02-05', '2025-01-30', 'active', 'IPA-2024-001', 950.00, 6),

-- DCM containers (chemical_id = 9)
(16, 'CHEM-20240214-009A', 9, 9, 1, 7, 'bottle', 'glass', 1000, 'mL', 1000.00, 680.00, 'mL', '2024-01-10', '2024-01-15', '2025-01-10', 'active', 'DCM-2024-001', 650.00, 5),

-- Formaldehyde containers (chemical_id = 10)
(17, 'CHEM-20240214-010A', 10, 13, 3, 26, 'bottle', 'plastic', 1000, 'mL', 1000.00, 850.00, 'mL', '2024-01-05', '2024-01-10', '2025-01-05', 'active', 'FORM-2024-001', 1200.00, 7),

-- Toluene containers (chemical_id = 11)
(18, 'CHEM-20240214-011A', 11, 9, 1, 1, 'bottle', 'glass', 1000, 'mL', 1000.00, 920.00, 'mL', '2024-01-15', '2024-01-20', '2025-01-15', 'active', 'TOLUENE-2024-001', 580.00, 5),

-- KCl containers (chemical_id = 12)
(19, 'CHEM-20240214-012A', 12, 11, 2, 24, 'bottle', 'plastic', 500, 'g', 500.00, 450.00, 'g', '2024-02-01', '2024-02-05', '2027-02-01', 'active', 'KCL-2024-001', 280.00, 6),

-- NaCl containers (chemical_id = 13)
(20, 'CHEM-20240214-013A', 13, 13, 3, 27, 'bottle', 'plastic', 1000, 'g', 1000.00, 980.00, 'g', '2024-01-20', '2024-01-25', '2028-01-20', 'active', 'NACL-2024-001', 150.00, 7),

-- HEPES containers (chemical_id = 14)
(21, 'CHEM-20240214-014A', 14, 13, 3, 28, 'bottle', 'plastic', 100, 'g', 100.00, 85.00, 'g', '2024-01-10', '2024-01-15', '2026-01-10', 'active', 'HEPES-2024-001', 2800.00, 7),

-- Chlorine containers (chemical_id = 15)
(22, 'CHEM-20240214-015A', 15, 11, 2, NULL, 'cylinder', 'metal', 50000, 'g', 50000.00, 35000.00, 'g', '2024-01-05', '2024-01-10', '2025-01-05', 'active', 'CL2-2024-001', 8500.00, 6),

-- Ammonia containers (chemical_id = 16)
(23, 'CHEM-20240214-016A', 16, 11, 2, NULL, 'cylinder', 'metal', 25000, 'g', 25000.00, 22000.00, 'g', '2024-01-15', '2024-01-20', '2025-01-15', 'active', 'NH3-2024-001', 6500.00, 6),

-- CS2 containers (chemical_id = 17)
(24, 'CHEM-20240214-017A', 17, 9, 1, 8, 'bottle', 'glass', 500, 'mL', 500.00, 420.00, 'mL', '2024-01-20', '2024-01-25', '2025-01-20', 'active', 'CS2-2024-001', 1200.00, 5),

-- Low stock containers (for demo)
(25, 'CHEM-20240214-LOW1', 3, 10, 1, 1, 'bottle', 'glass', 500, 'mL', 500.00, 25.00, 'mL', '2023-06-15', '2023-07-01', '2025-06-15', 'active', 'ACETIC-2023-010', 280.00, 5),
(26, 'CHEM-20240214-LOW2', 6, 10, 1, 6, 'bottle', 'glass', 500, 'mL', 500.00, 18.00, 'mL', '2023-08-20', '2023-09-01', '2025-08-20', 'active', 'ACETONE-2023-020', 450.00, 5),
(27, 'CHEM-20240214-LOW3', 8, 11, 2, 23, 'bottle', 'plastic', 1000, 'mL', 1000.00, 32.00, 'mL', '2023-07-10', '2023-07-15', '2025-07-10', 'active', 'IPA-2023-015', 650.00, 6),

-- Expiring soon containers (for demo)
(28, 'CHEM-20240214-EXP1', 5, 9, 1, 3, 'bottle', 'plastic', 1000, 'mL', 1000.00, 850.00, 'mL', '2023-02-20', '2023-03-01', '2025-02-20', 'active', 'NH4OH-2023-005', 450.00, 5),
(29, 'CHEM-20240214-EXP2', 6, 9, 1, 1, 'canister', 'metal', 20000, 'mL', 20000.00, 12000.00, 'mL', '2023-02-15', '2023-02-20', '2025-02-15', 'active', 'ACETONE-2023-003', 3500.00, 5),
(30, 'CHEM-20240214-EXP3', 10, 13, 3, 26, 'bottle', 'plastic', 500, 'mL', 500.00, 420.00, 'mL', '2023-03-10', '2023-03-15', '2025-03-10', 'active', 'FORM-2023-008', 850.00, 7);

-- =====================================================
-- 12. CONTAINER HISTORY (Usage & Movements)
-- =====================================================
INSERT INTO container_history (container_id, action_type, user_id, from_location_id, to_location_id, quantity_change, quantity_after, notes, created_at) VALUES
-- Usage history
(1, 'used', 9, NULL, NULL, -50.00, 850.00, 'ใช้ในการทดลองสังเคราะห์สารประกอบ A', '2024-02-01 10:30:00'),
(1, 'used', 9, NULL, NULL, -100.00, 750.00, 'ใช้ในการทดลองสังเคราะห์สารประกอบ B', '2024-02-05 14:20:00'),
(2, 'used', 9, NULL, NULL, -200.00, 120.00, 'ใช้ล้างเครื่องมือ', '2024-02-10 09:15:00'),
(4, 'used', 9, NULL, NULL, -80.00, 920.00, 'ใช้ในการทดลองไฮโดรไลซิส', '2024-02-03 11:00:00'),
(6, 'used', 10, NULL, NULL, -300.00, 1800.00, 'ใช้เป็น catalyst', '2024-02-08 13:45:00'),
(7, 'used', 10, NULL, NULL, -350.00, 15.00, 'ใช้ในการทดลอง esterification', '2024-02-12 10:00:00'),
(11, 'used', 9, NULL, NULL, -2000.00, 15000.00, 'ใช้ล้างเครื่องมือ', '2024-02-06 15:30:00'),
(12, 'used', 10, NULL, NULL, -500.00, 320.00, 'ใช้ในการทดลอง recrystallization', '2024-02-11 09:00:00'),
(13, 'used', 9, NULL, NULL, -1500.00, 18500.00, 'ใช้เป็น solvent', '2024-02-07 11:30:00'),
(16, 'used', 9, NULL, NULL, -200.00, 680.00, 'ใช้ในการสกัดสาร', '2024-02-09 14:00:00'),

-- Location movements
(5, 'moved', 6, 21, 22, NULL, 45.00, 'ย้ายไปเก็บรวมกับสารกรดอื่น', '2024-02-05 10:00:00'),
(19, 'moved', 11, 24, 23, NULL, 450.00, 'จัดเรียงใหม่ตามหมวดหมู่', '2024-02-08 16:00:00'),

-- Container creation
(1, 'created', 5, NULL, NULL, NULL, 1000.00, 'รับเข้าคลัง', '2024-01-15 09:00:00'),
(2, 'created', 5, NULL, NULL, NULL, 500.00, 'รับเข้าคลัง', '2024-02-01 09:00:00'),
(3, 'created', 5, NULL, NULL, NULL, 2500.00, 'รับเข้าคลัง', '2024-01-10 09:00:00');

-- =====================================================
-- 13. BORROW REQUESTS (Sample transactions)
-- =====================================================
INSERT INTO borrow_requests (request_number, requester_id, owner_id, container_id, chemical_id, request_type, requested_quantity, quantity_unit, purpose, experiment_id, needed_by_date, expected_return_date, status, approved_by, approved_at, fulfilled_container_id, fulfilled_quantity, fulfilled_by, fulfilled_at, returned_quantity, return_condition, created_at) VALUES
-- Pending requests
('BRW-20240210-001', 10, 9, 1, 1, 'borrow', 100.00, 'mL', 'ใช้ในการทดลองสังเคราะห์สารประกอบใหม่', 'EXP-2024-015', '2024-02-15', '2024-02-20', 'pending', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2024-02-10 09:30:00'),
('BRW-20240212-002', 12, 11, 8, 4, 'borrow', 50.00, 'g', 'ใช้ในการทดลอง titration', 'EXP-2024-018', '2024-02-14', '2024-02-18', 'pending', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2024-02-12 11:00:00'),
('BRW-20240213-003', 14, 13, 20, 13, 'borrow', 20.00, 'g', 'ใช้ในการทดลองเตรียม buffer', 'EXP-2024-020', '2024-02-15', '2024-02-22', 'pending', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2024-02-13 14:30:00'),

-- Approved but not fulfilled
('BRW-20240208-004', 10, 9, 4, 2, 'borrow', 50.00, 'mL', 'ใช้ในการทดลอง hydrolysis', 'EXP-2024-012', '2024-02-12', '2024-02-16', 'approved', 5, '2024-02-09 10:00:00', NULL, NULL, NULL, NULL, NULL, NULL, '2024-02-08 16:00:00'),

-- Fulfilled and active
('BRW-20240201-005', 10, 9, 13, 7, 'borrow', 500.00, 'mL', 'ใช้เป็น solvent ในการสกัด', 'EXP-2024-008', '2024-02-05', '2024-02-15', 'fulfilled', 5, '2024-02-02 09:00:00', 13, 500.00, 5, '2024-02-02 10:30:00', NULL, NULL, '2024-02-01 11:00:00'),
('BRW-20240205-006', 12, 11, 15, 8, 'borrow', 200.00, 'mL', 'ใช้ล้างเครื่องมือ', 'EXP-2024-010', '2024-02-08', '2024-02-12', 'fulfilled', 6, '2024-02-06 10:00:00', 15, 200.00, 6, '2024-02-06 11:00:00', NULL, NULL, '2024-02-05 14:00:00'),

-- Returned
('BRW-20240125-007', 10, 9, 3, 1, 'borrow', 200.00, 'mL', 'ใช้ในการทดลอง neutralization', 'EXP-2024-005', '2024-01-28', '2024-02-02', 'returned', 5, '2024-01-26 09:00:00', 3, 200.00, 5, '2024-01-26 10:00:00', 180.00, 'partially_used', '2024-01-25 10:00:00'),
('BRW-20240128-008', 12, 11, 9, 4, 'borrow', 100.00, 'g', 'ใช้ในการทดลอง pH adjustment', 'EXP-2024-006', '2024-01-30', '2024-02-03', 'returned', 6, '2024-01-29 11:00:00', 9, 100.00, 6, '2024-01-29 14:00:00', 100.00, 'good', '2024-01-28 09:30:00'),

-- Overdue
('BRW-20240115-009', 10, 9, 6, 3, 'borrow', 300.00, 'mL', 'ใช้เป็น catalyst', 'EXP-2024-002', '2024-01-20', '2024-01-25', 'overdue', 5, '2024-01-16 10:00:00', 6, 300.00, 5, '2024-01-16 11:00:00', NULL, NULL, '2024-01-15 09:00:00'),
('BRW-20240120-010', 14, 13, 17, 10, 'borrow', 100.00, 'mL', 'ใช้ในการทดลอง fixation', 'EXP-2024-004', '2024-01-25', '2024-01-30', 'overdue', 7, '2024-01-21 14:00:00', 17, 100.00, 7, '2024-01-21 15:00:00', NULL, NULL, '2024-01-20 10:00:00');

-- =====================================================
-- 14. ALERTS (Sample notifications)
-- =====================================================
INSERT INTO alerts (alert_type, severity, title, message, user_id, chemical_id, container_id, is_read, action_required, created_at) VALUES
('expiry', 'critical', 'สารเคมีใกล้หมดอายุ', 'แอมโมเนียมไฮดรอกไซด์ จะหมดอายุในอีก 6 วัน', 9, 5, 28, 0, 1, '2024-02-14 08:00:00'),
('expiry', 'warning', 'สารเคมีใกล้หมดอายุ', 'อะซิโตน จะหมดอายุในอีก 1 วัน', 9, 6, 29, 0, 1, '2024-02-14 08:00:00'),
('expiry', 'warning', 'สารเคมีใกล้หมดอายุ', 'ฟอร์มาลดีไฮด์ จะหมดอายุในอีก 24 วัน', 13, 10, 30, 0, 1, '2024-02-14 08:00:00'),
('low_stock', 'critical', 'สต็อกต่ำมาก', 'กรดแอซิติก (ACETIC-2023-010) เหลือเพียง 5%', 10, 3, 25, 0, 1, '2024-02-14 09:00:00'),
('low_stock', 'critical', 'สต็อกต่ำมาก', 'อะซิโตน (ACETONE-2023-020) เหลือเพียง 3.6%', 10, 6, 26, 0, 1, '2024-02-14 09:00:00'),
('low_stock', 'critical', 'สต็อกต่ำมาก', 'ไอโซโพรพานอล (IPA-2023-015) เหลือเพียง 3.2%', 11, 8, 27, 0, 1, '2024-02-14 09:00:00'),
('overdue_borrow', 'critical', 'การยืมเกินกำหนด', 'กรดแอซิติก 300 mL เกินกำหนด 20 วัน', 10, 3, 6, 0, 1, '2024-02-14 10:00:00'),
('overdue_borrow', 'critical', 'การยืมเกินกำหนด', 'ฟอร์มาลดีไฮด์ 100 mL เกินกำหนด 15 วัน', 14, 10, 17, 0, 1, '2024-02-14 10:00:00'),
('borrow_request', 'info', 'คำขอยืมใหม่', 'นักวิจัย สอง ขอยืม HCl 100 mL', 9, 1, 1, 0, 1, '2024-02-10 09:30:00'),
('borrow_request', 'info', 'คำขอยืมใหม่', 'นักวิจัย สี่ ขอยืม NaOH 50 g', 11, 4, 8, 0, 1, '2024-02-12 11:00:00');

-- =====================================================
-- 15. AI CHAT SESSIONS (Sample)
-- =====================================================
INSERT INTO ai_chat_sessions (user_id, session_id, title, context, created_at) VALUES
(9, 'chat_1707894000_abc123', 'ค้นหาสารเคมี', '{"topic": "chemical_search"}', '2024-02-14 10:00:00'),
(10, 'chat_1707897600_def456', 'ขอ SDS', '{"topic": "sds_request"}', '2024-02-14 11:00:00');

INSERT INTO ai_chat_messages (session_id, role, content, referenced_chemicals, created_at) VALUES
('chat_1707894000_abc123', 'user', 'HCl อยู่ที่ไหน?', '[1]', '2024-02-14 10:00:00'),
('chat_1707894000_abc123', 'assistant', 'พบ HCl จำนวน 3 ภาชนะ:\n- ภาชนะ CHEM-20240214-001A: 850 mL ที่ตู้เก็บสารกัดกร่อน ชั้นกลาง\n- ภาชนะ CHEM-20240214-001B: 120 mL ที่ตู้เก็บสารกัดกร่อน ชั้นกลาง\n- ภาชนะ CHEM-20240214-001C: 2300 mL ที่ตู้เก็บสารกัดกร่อน ชั้นกลาง', '[1]', '2024-02-14 10:00:05'),
('chat_1707894000_abc123', 'user', 'สารไหนใกล้หมดอายุบ้าง?', '[]', '2024-02-14 10:05:00'),
('chat_1707894000_abc123', 'assistant', 'สารเคมีที่ใกล้หมดอายุ:\n⚠️ แอมโมเนียมไฮดรอกไซด์ - 6 วัน\n⚠️ อะซิโตน - 1 วัน\n⚠️ ฟอร์มาลดีไฮด์ - 24 วัน', '[5,6,10]', '2024-02-14 10:05:10');

-- =====================================================
-- 16. AUDIT LOGS (Sample)
-- =====================================================
INSERT INTO audit_logs (table_name, record_id, action, old_values, new_values, user_id, ip_address, created_at) VALUES
('containers', 1, 'UPDATE', '{"current_quantity": 900}', '{"current_quantity": 850}', 9, '192.168.1.100', '2024-02-01 10:30:00'),
('containers', 2, 'UPDATE', '{"current_quantity": 320}', '{"current_quantity": 120}', 9, '192.168.1.100', '2024-02-10 09:15:00'),
('borrow_requests', 1, 'INSERT', NULL, '{"status": "pending", "requested_quantity": 100}', 10, '192.168.1.101', '2024-02-10 09:30:00'),
('users', 15, 'INSERT', NULL, '{"username": "user6", "role_id": 2}', 1, '192.168.1.1', '2024-02-14 08:00:00');

-- =====================================================
-- 17. USAGE PREDICTIONS (Sample)
-- =====================================================
INSERT INTO usage_predictions (chemical_id, lab_id, prediction_date, predicted_usage, confidence_level, factors, created_at) VALUES
(1, 1, '2024-03-01', 500.00, 0.85, '{"historical_avg": 450, "seasonal_factor": 1.1}', '2024-02-14'),
(6, 1, '2024-03-01', 3000.00, 0.78, '{"historical_avg": 2800, "lab_activity": "high"}', '2024-02-14'),
(7, 1, '2024-03-01', 2500.00, 0.82, '{"historical_avg": 2300, "upcoming_experiments": 5}', '2024-02-14');

-- Reset AUTO_INCREMENT for all tables
ALTER TABLE organizations AUTO_INCREMENT = 3;
ALTER TABLE labs AUTO_INCREMENT = 6;
ALTER TABLE users AUTO_INCREMENT = 15;
ALTER TABLE buildings AUTO_INCREMENT = 4;
ALTER TABLE rooms AUTO_INCREMENT = 7;
ALTER TABLE cabinets AUTO_INCREMENT = 13;
ALTER TABLE shelves AUTO_INCREMENT = 27;
ALTER TABLE slots AUTO_INCREMENT = 37;
ALTER TABLE chemical_categories AUTO_INCREMENT = 8;
ALTER TABLE chemicals AUTO_INCREMENT = 18;
ALTER TABLE containers AUTO_INCREMENT = 31;
ALTER TABLE container_history AUTO_INCREMENT = 15;
ALTER TABLE borrow_requests AUTO_INCREMENT = 11;
ALTER TABLE alerts AUTO_INCREMENT = 11;
ALTER TABLE ai_chat_sessions AUTO_INCREMENT = 3;
ALTER TABLE ai_chat_messages AUTO_INCREMENT = 5;
ALTER TABLE audit_logs AUTO_INCREMENT = 5;
ALTER TABLE usage_predictions AUTO_INCREMENT = 4;

-- =====================================================
-- SUMMARY
-- =====================================================
-- Organizations: 2
-- Labs: 5
-- Users: 14 (admin1, admin2, ceo1, ceo2, lab1-4, user1-6)
-- Buildings: 3
-- Rooms: 6
-- Cabinets: 12
-- Shelves: 26
-- Slots: 36
-- Chemical Categories: 7
-- Chemicals: 17
-- Containers: 30 (with various statuses)
-- Borrow Requests: 10 (pending, approved, fulfilled, returned, overdue)
-- Alerts: 10 (expiry, low_stock, overdue, borrow_request)
-- =====================================================
