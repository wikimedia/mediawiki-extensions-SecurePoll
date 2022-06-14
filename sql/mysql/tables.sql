-- This file is automatically generated using maintenance/generateSchemaSql.php.
-- Source: ./sql/tables.json
-- Do not modify this file directly.
-- See https://www.mediawiki.org/wiki/Manual:Schema_changes
CREATE TABLE /*_*/securepoll_entity (
  en_id INT AUTO_INCREMENT NOT NULL,
  en_type VARBINARY(32) NOT NULL,
  PRIMARY KEY(en_id)
) /*$wgDBTableOptions*/;


CREATE TABLE /*_*/securepoll_properties (
  pr_entity INT NOT NULL,
  pr_key VARBINARY(32) NOT NULL,
  pr_value MEDIUMBLOB NOT NULL,
  UNIQUE INDEX sppr_entity (pr_entity, pr_key)
) /*$wgDBTableOptions*/;


CREATE TABLE /*_*/securepoll_questions (
  qu_entity INT NOT NULL,
  qu_election INT NOT NULL,
  qu_index INT NOT NULL,
  INDEX spqu_election_index (qu_election, qu_index, qu_entity),
  PRIMARY KEY(qu_entity)
) /*$wgDBTableOptions*/;


CREATE TABLE /*_*/securepoll_options (
  op_entity INT NOT NULL,
  op_election INT NOT NULL,
  op_question INT NOT NULL,
  INDEX spop_question (op_question, op_entity),
  INDEX spop_election (op_election),
  PRIMARY KEY(op_entity)
) /*$wgDBTableOptions*/;


CREATE TABLE /*_*/securepoll_lists (
  li_name VARBINARY(255) DEFAULT NULL,
  li_member INT NOT NULL,
  INDEX splists_name (li_name, li_member),
  INDEX splists_member (li_member, li_name)
) /*$wgDBTableOptions*/;


CREATE TABLE /*_*/securepoll_log (
  spl_id INT UNSIGNED AUTO_INCREMENT NOT NULL,
  spl_timestamp BINARY(14) NOT NULL,
  spl_election_id INT NOT NULL,
  spl_user INT UNSIGNED NOT NULL,
  spl_type TINYINT UNSIGNED NOT NULL,
  spl_target INT UNSIGNED DEFAULT NULL,
  INDEX spl_timestamp (spl_timestamp),
  PRIMARY KEY(spl_id)
) /*$wgDBTableOptions*/;


CREATE TABLE /*_*/securepoll_msgs (
  msg_entity INT NOT NULL,
  msg_lang VARBINARY(32) NOT NULL,
  msg_key VARBINARY(32) NOT NULL,
  msg_text MEDIUMTEXT NOT NULL,
  UNIQUE INDEX spmsg_entity (msg_entity, msg_lang, msg_key)
) /*$wgDBTableOptions*/;


CREATE TABLE /*_*/securepoll_elections (
  el_entity INT NOT NULL,
  el_title VARCHAR(255) NOT NULL,
  el_owner INT NOT NULL,
  el_ballot VARCHAR(32) NOT NULL,
  el_tally VARCHAR(32) NOT NULL,
  el_primary_lang VARBINARY(32) NOT NULL,
  el_start_date BINARY(14) DEFAULT NULL,
  el_end_date BINARY(14) DEFAULT NULL,
  el_auth_type VARBINARY(32) NOT NULL,
  UNIQUE INDEX spel_title (el_title),
  PRIMARY KEY(el_entity)
) /*$wgDBTableOptions*/;


CREATE TABLE /*_*/securepoll_voters (
  voter_id INT AUTO_INCREMENT NOT NULL,
  voter_election INT NOT NULL,
  voter_name VARBINARY(255) NOT NULL,
  voter_type VARBINARY(32) NOT NULL,
  voter_domain VARBINARY(255) NOT NULL,
  voter_url BLOB DEFAULT NULL,
  voter_properties BLOB DEFAULT NULL,
  INDEX spvoter_elec_name_domain (
    voter_election, voter_name, voter_domain
  ),
  PRIMARY KEY(voter_id)
) /*$wgDBTableOptions*/;


CREATE TABLE /*_*/securepoll_votes (
  vote_id INT AUTO_INCREMENT NOT NULL,
  vote_election INT NOT NULL,
  vote_voter INT NOT NULL,
  vote_voter_name VARBINARY(255) NOT NULL,
  vote_voter_domain VARBINARY(32) NOT NULL,
  vote_struck TINYINT NOT NULL,
  vote_record BLOB NOT NULL,
  vote_ip VARBINARY(35) NOT NULL,
  vote_xff VARBINARY(255) NOT NULL,
  vote_ua VARBINARY(255) NOT NULL,
  vote_timestamp BINARY(14) NOT NULL,
  vote_current TINYINT NOT NULL,
  vote_token_match TINYINT NOT NULL,
  vote_cookie_dup TINYINT NOT NULL,
  INDEX spvote_timestamp (vote_election, vote_timestamp),
  INDEX spvote_voter_name (
    vote_election, vote_voter_name, vote_timestamp
  ),
  INDEX spvote_voter_domain (
    vote_election, vote_voter_domain,
    vote_timestamp
  ),
  INDEX spvote_ip (
    vote_election, vote_ip, vote_timestamp
  ),
  PRIMARY KEY(vote_id)
) /*$wgDBTableOptions*/;


CREATE TABLE /*_*/securepoll_strike (
  st_id INT AUTO_INCREMENT NOT NULL,
  st_vote INT NOT NULL,
  st_timestamp BINARY(14) NOT NULL,
  st_action VARBINARY(32) NOT NULL,
  st_reason VARCHAR(255) NOT NULL,
  st_user INT NOT NULL,
  INDEX spstrike_vote (st_vote, st_timestamp),
  PRIMARY KEY(st_id)
) /*$wgDBTableOptions*/;


CREATE TABLE /*_*/securepoll_cookie_match (
  cm_id INT AUTO_INCREMENT NOT NULL,
  cm_election INT NOT NULL,
  cm_voter_1 INT NOT NULL,
  cm_voter_2 INT NOT NULL,
  cm_timestamp BINARY(14) NOT NULL,
  INDEX spcookie_match_voter_1 (cm_voter_1, cm_timestamp),
  INDEX spcookie_match_voter_2 (cm_voter_2, cm_timestamp),
  PRIMARY KEY(cm_id)
) /*$wgDBTableOptions*/;
