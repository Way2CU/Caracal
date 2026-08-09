-- This file modifies database to adapt it to changes introduced with the
-- migration of `system_access` passwords to `password_hash`/`password_verify`.
-- Existing hashes remain valid and are upgraded in place on the next successful
-- login, so no password reset is required. The `salt` column is kept only for
-- verifying not-yet-upgraded legacy hashes and may be dropped once every account
-- has logged in at least once.

ALTER TABLE `system_access` MODIFY `password` varchar(255) COLLATE utf8_bin NOT NULL;

-- Add indexes for columns used as lookup keys. Authentication resolves accounts by
-- `username` and account creation checks for existing accounts by `email`, both of
-- which scanned the whole table. Retry throttling looks up records by `address` on
-- every login attempt.
--
-- These are plain keys rather than unique constraints so the migration cannot fail on
-- a database that already contains duplicates. Both `username` and `email` are treated
-- as identities by the code, so tightening them to UNIQUE is worthwhile once verified:
--
--     SELECT `username`, COUNT(*) AS total FROM `system_access`
--         GROUP BY `username` HAVING total > 1;
--     SELECT `email`, COUNT(*) AS total FROM `system_access`
--         GROUP BY `email` HAVING total > 1;

ALTER TABLE `system_access` ADD KEY `index_by_username` (`username`);
ALTER TABLE `system_access` ADD KEY `index_by_email` (`email`);
ALTER TABLE `system_retries` ADD KEY `index_by_address` (`address`);
