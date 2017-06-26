-- Add page title
ALTER TABLE `page_descriptions` ADD COLUMN `title_en` varchar(140) DEFAULT '' AFTER `url`;
