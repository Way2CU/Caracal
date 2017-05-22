-- Add page title
ALTER TABLE `page_description` ADD COLUMN `title_en` varchar(140) DEFAULT '' AFTER `url`;
