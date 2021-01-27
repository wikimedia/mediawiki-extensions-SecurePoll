-- This file is automatically generated using maintenance/generateSchemaSql.php.
-- Source: ../../extensions/SecurePoll/sql/securepoll_log.json
-- Do not modify this file directly.
-- See https://www.mediawiki.org/wiki/Manual:Schema_changes
CREATE TABLE /*_*/securepoll_log (
  spl_id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
  spl_timestamp BLOB NOT NULL, spl_election_id INTEGER NOT NULL,
  spl_user INTEGER UNSIGNED NOT NULL,
  spl_type SMALLINT UNSIGNED NOT NULL,
  spl_target INTEGER UNSIGNED DEFAULT NULL
);

CREATE INDEX spl_timestamp ON /*_*/securepoll_log (spl_timestamp);
