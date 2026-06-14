-- Migration v16: repair legacy demo rows and retire the obsolete D.Pharm login.

UPDATE `students`
   SET `department_id` = (SELECT id FROM departments WHERE code = 'bba' LIMIT 1)
 WHERE `enrollment_no` IN ('BBA2025001', 'BBA2025002')
   AND `department_id` = (SELECT id FROM departments WHERE code = 'mca' LIMIT 1);

UPDATE `faculty`
   SET `is_active` = 0
 WHERE `username` = 'dpharm_faculty';
