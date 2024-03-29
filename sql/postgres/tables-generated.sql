-- This file is automatically generated using maintenance/generateSchemaSql.php.
-- Source: ./sql/tables.json
-- Do not modify this file directly.
-- See https://www.mediawiki.org/wiki/Manual:Schema_changes
CREATE TABLE securepoll_entity (
  en_id SERIAL NOT NULL,
  en_type TEXT NOT NULL,
  PRIMARY KEY(en_id)
);


CREATE TABLE securepoll_properties (
  pr_entity INT NOT NULL,
  pr_key TEXT NOT NULL,
  pr_value TEXT NOT NULL,
  PRIMARY KEY(pr_entity, pr_key)
);


CREATE TABLE securepoll_questions (
  qu_entity INT NOT NULL,
  qu_election INT NOT NULL,
  qu_index INT NOT NULL,
  PRIMARY KEY(qu_entity)
);

CREATE INDEX spqu_election_index ON securepoll_questions (qu_election, qu_index, qu_entity);


CREATE TABLE securepoll_options (
  op_entity INT NOT NULL,
  op_election INT NOT NULL,
  op_question INT NOT NULL,
  PRIMARY KEY(op_entity)
);

CREATE INDEX spop_question ON securepoll_options (op_question, op_entity);

CREATE INDEX spop_election ON securepoll_options (op_election);


CREATE TABLE securepoll_lists (
  li_name TEXT DEFAULT NULL, li_member INT NOT NULL
);

CREATE INDEX splists_name ON securepoll_lists (li_name, li_member);

CREATE INDEX splists_member ON securepoll_lists (li_member, li_name);


CREATE TABLE securepoll_log (
  spl_id SERIAL NOT NULL,
  spl_timestamp TIMESTAMPTZ NOT NULL,
  spl_election_id INT NOT NULL,
  spl_user INT NOT NULL,
  spl_type SMALLINT NOT NULL,
  spl_target INT DEFAULT NULL,
  PRIMARY KEY(spl_id)
);

CREATE INDEX spl_timestamp ON securepoll_log (spl_timestamp);


CREATE TABLE securepoll_msgs (
  msg_entity INT NOT NULL,
  msg_lang TEXT NOT NULL,
  msg_key TEXT NOT NULL,
  msg_text TEXT NOT NULL,
  PRIMARY KEY(msg_entity, msg_lang, msg_key)
);


CREATE TABLE securepoll_elections (
  el_entity INT NOT NULL,
  el_title VARCHAR(255) NOT NULL,
  el_owner INT NOT NULL,
  el_ballot VARCHAR(32) NOT NULL,
  el_tally VARCHAR(32) NOT NULL,
  el_primary_lang TEXT NOT NULL,
  el_start_date TIMESTAMPTZ DEFAULT NULL,
  el_end_date TIMESTAMPTZ DEFAULT NULL,
  el_auth_type TEXT NOT NULL,
  PRIMARY KEY(el_entity)
);

CREATE UNIQUE INDEX spel_title ON securepoll_elections (el_title);


CREATE TABLE securepoll_voters (
  voter_id SERIAL NOT NULL,
  voter_election INT NOT NULL,
  voter_name TEXT NOT NULL,
  voter_type TEXT NOT NULL,
  voter_domain TEXT NOT NULL,
  voter_url TEXT DEFAULT NULL,
  voter_properties TEXT DEFAULT NULL,
  PRIMARY KEY(voter_id)
);

CREATE INDEX spvoter_elec_name_domain ON securepoll_voters (
  voter_election, voter_name, voter_domain
);


CREATE TABLE securepoll_votes (
  vote_id SERIAL NOT NULL,
  vote_election INT NOT NULL,
  vote_voter INT NOT NULL,
  vote_voter_name TEXT NOT NULL,
  vote_voter_domain TEXT NOT NULL,
  vote_struck SMALLINT NOT NULL,
  vote_record TEXT NOT NULL,
  vote_ip TEXT NOT NULL,
  vote_xff TEXT NOT NULL,
  vote_ua TEXT NOT NULL,
  vote_timestamp TIMESTAMPTZ NOT NULL,
  vote_current SMALLINT NOT NULL,
  vote_token_match SMALLINT NOT NULL,
  vote_cookie_dup SMALLINT NOT NULL,
  PRIMARY KEY(vote_id)
);

CREATE INDEX spvote_timestamp ON securepoll_votes (vote_election, vote_timestamp);

CREATE INDEX spvote_voter_name ON securepoll_votes (
  vote_election, vote_voter_name, vote_timestamp
);

CREATE INDEX spvote_voter_domain ON securepoll_votes (
  vote_election, vote_voter_domain,
  vote_timestamp
);

CREATE INDEX spvote_ip ON securepoll_votes (
  vote_election, vote_ip, vote_timestamp
);


CREATE TABLE securepoll_strike (
  st_id SERIAL NOT NULL,
  st_vote INT NOT NULL,
  st_timestamp TIMESTAMPTZ NOT NULL,
  st_action TEXT NOT NULL,
  st_reason VARCHAR(255) NOT NULL,
  st_user INT NOT NULL,
  PRIMARY KEY(st_id)
);

CREATE INDEX spstrike_vote ON securepoll_strike (st_vote, st_timestamp);


CREATE TABLE securepoll_cookie_match (
  cm_id SERIAL NOT NULL,
  cm_election INT NOT NULL,
  cm_voter_1 INT NOT NULL,
  cm_voter_2 INT NOT NULL,
  cm_timestamp TIMESTAMPTZ NOT NULL,
  PRIMARY KEY(cm_id)
);

CREATE INDEX spcookie_match_voter_1 ON securepoll_cookie_match (cm_voter_1, cm_timestamp);

CREATE INDEX spcookie_match_voter_2 ON securepoll_cookie_match (cm_voter_2, cm_timestamp);
