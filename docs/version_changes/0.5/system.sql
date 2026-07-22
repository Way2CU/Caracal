-- This file modifies database to adapt it to changes introduced with the
-- migration of `system_access` passwords to `password_hash`/`password_verify`.
-- Existing hashes remain valid and are upgraded in place on the next successful
-- login, so no password reset is required. The `salt` column is kept only for
-- verifying not-yet-upgraded legacy hashes and may be dropped once every account
-- has logged in at least once.

ALTER TABLE `system_access` MODIFY `password` varchar(255) COLLATE utf8_bin NOT NULL;
