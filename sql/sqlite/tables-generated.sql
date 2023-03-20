-- This file is automatically generated using maintenance/generateSchemaSql.php.
-- Source: ./sql/tables.json
-- Do not modify this file directly.
-- See https://www.mediawiki.org/wiki/Manual:Schema_changes
CREATE TABLE /*_*/securepoll_entity (
  en_id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
  en_type BLOB NOT NULL
);


CREATE TABLE /*_*/securepoll_properties (
  pr_entity INTEGER NOT NULL,
  pr_key BLOB NOT NULL,
  pr_value BLOB NOT NULL,
  PRIMARY KEY(pr_entity, pr_key)
);


CREATE TABLE /*_*/securepoll_questions (
  qu_entity INTEGER NOT NULL,
  qu_election INTEGER NOT NULL,
  qu_index INTEGER NOT NULL,
  PRIMARY KEY(qu_entity)
);

CREATE INDEX spqu_election_index ON /*_*/securepoll_questions (qu_election, qu_index, qu_entity);


CREATE TABLE /*_*/securepoll_options (
  op_entity INTEGER NOT NULL,
  op_election INTEGER NOT NULL,
  op_question INTEGER NOT NULL,
  PRIMARY KEY(op_entity)
);

CREATE INDEX spop_question ON /*_*/securepoll_options (op_question, op_entity);

CREATE INDEX spop_election ON /*_*/securepoll_options (op_election);


CREATE TABLE /*_*/securepoll_lists (
  li_name BLOB DEFAULT NULL, li_member INTEGER NOT NULL
);

CREATE INDEX splists_name ON /*_*/securepoll_lists (li_name, li_member);

CREATE INDEX splists_member ON /*_*/securepoll_lists (li_member, li_name);


CREATE TABLE /*_*/securepoll_log (
  spl_id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
  spl_timestamp BLOB NOT NULL, spl_election_id INTEGER NOT NULL,
  spl_user INTEGER UNSIGNED NOT NULL,
  spl_type SMALLINT UNSIGNED NOT NULL,
  spl_target INTEGER UNSIGNED DEFAULT NULL
);

CREATE INDEX spl_timestamp ON /*_*/securepoll_log (spl_timestamp);


CREATE TABLE /*_*/securepoll_msgs (
  msg_entity INTEGER NOT NULL,
  msg_lang BLOB NOT NULL,
  msg_key BLOB NOT NULL,
  msg_text CLOB NOT NULL,
  PRIMARY KEY(msg_entity, msg_lang, msg_key)
);


CREATE TABLE /*_*/securepoll_elections (
  el_entity INTEGER NOT NULL,
  el_title VARCHAR(255) NOT NULL,
  el_owner INTEGER NOT NULL,
  el_ballot VARCHAR(32) NOT NULL,
  el_tally VARCHAR(32) NOT NULL,
  el_primary_lang BLOB NOT NULL,
  el_start_date BLOB DEFAULT NULL,
  el_end_date BLOB DEFAULT NULL,
  el_auth_type BLOB NOT NULL,
  PRIMARY KEY(el_entity)
);

CREATE UNIQUE INDEX spel_title ON /*_*/securepoll_elections (el_title);


CREATE TABLE /*_*/securepoll_voters (
  voter_id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
  voter_election INTEGER NOT NULL, voter_name BLOB NOT NULL,
  voter_type BLOB NOT NULL, voter_domain BLOB NOT NULL,
  voter_url BLOB DEFAULT NULL, voter_properties BLOB DEFAULT NULL
);

CREATE INDEX spvoter_elec_name_domain ON /*_*/securepoll_voters (
  voter_election, voter_name, voter_domain
);


CREATE TABLE /*_*/securepoll_votes (
  vote_id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
  vote_election INTEGER NOT NULL, vote_voter INTEGER NOT NULL,
  vote_voter_name BLOB NOT NULL, vote_voter_domain BLOB NOT NULL,
  vote_struck SMALLINT NOT NULL, vote_record BLOB NOT NULL,
  vote_ip BLOB NOT NULL, vote_xff BLOB NOT NULL,
  vote_ua BLOB NOT NULL, vote_timestamp BLOB NOT NULL,
  vote_current SMALLINT NOT NULL, vote_token_match SMALLINT NOT NULL,
  vote_cookie_dup SMALLINT NOT NULL
);

CREATE INDEX spvote_timestamp ON /*_*/securepoll_votes (vote_election, vote_timestamp);

CREATE INDEX spvote_voter_name ON /*_*/securepoll_votes (
  vote_election, vote_voter_name, vote_timestamp
);

CREATE INDEX spvote_voter_domain ON /*_*/securepoll_votes (
  vote_election, vote_voter_domain,
  vote_timestamp
);

CREATE INDEX spvote_ip ON /*_*/securepoll_votes (
  vote_election, vote_ip, vote_timestamp
);


CREATE TABLE /*_*/securepoll_strike (
  st_id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
  st_vote INTEGER NOT NULL,
  st_timestamp BLOB NOT NULL,
  st_action BLOB NOT NULL,
  st_reason VARCHAR(255) NOT NULL,
  st_user INTEGER NOT NULL
);

CREATE INDEX spstrike_vote ON /*_*/securepoll_strike (st_vote, st_timestamp);


CREATE TABLE /*_*/securepoll_cookie_match (
  cm_id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
  cm_election INTEGER NOT NULL, cm_voter_1 INTEGER NOT NULL,
  cm_voter_2 INTEGER NOT NULL, cm_timestamp BLOB NOT NULL
);

CREATE INDEX spcookie_match_voter_1 ON /*_*/securepoll_cookie_match (cm_voter_1, cm_timestamp);

CREATE INDEX spcookie_match_voter_2 ON /*_*/securepoll_cookie_match (cm_voter_2, cm_timestamp);