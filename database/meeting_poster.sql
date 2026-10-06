-- ------------------------------------------------------------
-- Meeting poster (optional image per meeting / fellowship)
-- Run once on the rotary_attendance database (e.g. via phpMyAdmin → SQL)
-- ------------------------------------------------------------
ALTER TABLE `meetings`
  ADD COLUMN `poster_path` varchar(255) DEFAULT NULL AFTER `theme`;
