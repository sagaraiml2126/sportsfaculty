-- Migration v15: scope each jersey form to one department.
-- Apply once to installations that already imported migration v13.

ALTER TABLE `jersey_forms`
    ADD COLUMN `department_id` TINYINT UNSIGNED NULL AFTER `id`;

UPDATE `jersey_forms` jf
JOIN (
    SELECT jr.jersey_form_id, MIN(s.department_id) AS department_id
      FROM jersey_requests jr
      JOIN students s ON s.id = jr.student_id
     GROUP BY jr.jersey_form_id
) request_departments ON request_departments.jersey_form_id = jf.id
SET jf.department_id = request_departments.department_id;

UPDATE `jersey_forms` jf
JOIN (
    SELECT ft.game_name, ft.event_label, COALESCE(ft.academic_year, '') AS academic_year,
           MIN(s.department_id) AS department_id
      FROM final_teams ft
      JOIN students s ON s.id = ft.student_id
     GROUP BY ft.game_name, ft.event_label, COALESCE(ft.academic_year, '')
) team_departments
  ON team_departments.game_name = jf.game_name
 AND team_departments.event_label = jf.event_label
 AND team_departments.academic_year = COALESCE(jf.academic_year, '')
SET jf.department_id = COALESCE(jf.department_id, team_departments.department_id);

-- Orphan forms have no final team and cannot be managed safely.
DELETE FROM `jersey_forms` WHERE `department_id` IS NULL;

UPDATE `jersey_forms` SET `academic_year` = '' WHERE `academic_year` IS NULL;

ALTER TABLE `jersey_forms`
    DROP INDEX `uq_jersey_form_team`,
    MODIFY `department_id` TINYINT UNSIGNED NOT NULL,
    MODIFY `academic_year` VARCHAR(10) NOT NULL DEFAULT '',
    ADD UNIQUE KEY `uq_jersey_form_team`
        (`department_id`, `game_name`, `event_label`, `academic_year`),
    ADD KEY `idx_jersey_department` (`department_id`),
    ADD CONSTRAINT `fk_jersey_form_department`
        FOREIGN KEY (`department_id`) REFERENCES `departments`(`id`)
        ON UPDATE CASCADE ON DELETE RESTRICT;
