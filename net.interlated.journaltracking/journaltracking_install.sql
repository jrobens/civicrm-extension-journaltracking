DROP TABLE IF EXISTS `civijournal_item`;
DROP TABLE IF EXISTS `civijournal_catchup`;

-- /*******************************************************
-- *
-- * civijournal_item
-- *
-- * A Journal tracking entry.
-- *
-- *******************************************************/
CREATE TABLE `civijournal_item` (
     `id` int unsigned NOT NULL AUTO_INCREMENT  COMMENT 'Discount Item ID',
     `code` varchar(255) NOT NULL   COMMENT 'Identifier for this journal batch.',
     `activity_code` varchar(255) NOT NULL   COMMENT 'Unique across queries. Used as the activity code.',
     `description` varchar(255) NOT NULL   COMMENT 'Description for this journal batch.',
     `batch_date` datetime DEFAULT NULL  COMMENT 'When was the batch run?',
     `count` int DEFAULT NULL COMMENT 'Number of records found',
    PRIMARY KEY ( `id` )
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci;

--  alter table civijournal_item add  activity_code VARCHAR(255) NOT NULL AFTER code;

-- /*******************************************************
-- *
-- * civijournal_catchup
-- *
-- * Track when catchup batches were run.
-- * JournalItem oneToMany with Catchup.
-- *
-- *******************************************************/
CREATE TABLE `civijournal_catchup` (
     `id` int unsigned NOT NULL AUTO_INCREMENT  COMMENT 'Discount Item ID',
     `code` varchar(255) NOT NULL   COMMENT 'Identifier for this journal batch.',
     `activity_code` varchar(255) NOT NULL   COMMENT 'Unique across queries. Used as the activity code.',
     `description` varchar(255) NOT NULL   COMMENT 'Description for this journal batch.',
     `batch_date` datetime DEFAULT NULL  COMMENT 'When was the batch run.',
     `catchup_date` datetime DEFAULT NULL COMMENT 'When the catchup was run.',
     `count` int DEFAULT NULL COMMENT 'Number of records found',
    PRIMARY KEY ( `id` )
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci;


--  alter table civijournal_catchup add  activity_code VARCHAR(255) NOT NULL AFTER code;

-- there was a select query here. It could be useful to validate that things are running, but
-- it is hard to debug what goes wrong and means that the query has to get updated in one more place.