ALTER TABLE `todos`
    ADD COLUMN `status` ENUM('Pending', 'Completed') NOT NULL DEFAULT 'Pending' AFTER `description`;