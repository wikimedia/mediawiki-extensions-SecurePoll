-- Postgres version of the schema for the SecurePoll extension
-- See SecurePoll.sql for detailed comments

\set ON_ERROR_STOP on
BEGIN;

-- i18n text associated with an entity
CREATE TABLE securepoll_msgs (
  msg_entity INTEGER NOT NULL PRIMARY KEY
    REFERENCES securepoll_entity(en_id) ON UPDATE CASCADE ON DELETE CASCADE,
  msg_lang   TEXT    NOT NULL,
  msg_key    TEXT    NOT NULL,
  msg_text   TEXT    NOT NULL
);
CREATE UNIQUE INDEX spmsg_entity ON securepoll_msgs (msg_entity, msg_lang, msg_key);


-- List of elections (or polls, surveys, etc)
CREATE TABLE securepoll_elections (
  el_entity       INTEGER NOT NULL PRIMARY KEY
    REFERENCES securepoll_entity(en_id) ON UPDATE CASCADE ON DELETE CASCADE,
  el_title        TEXT    NOT NULL,
  el_owner        INTEGER NOT NULL,
  el_ballot       TEXT    NOT NULL,
  el_tally        TEXT    NOT NULL,
  el_primary_lang TEXT    NOT NULL,
  el_start_date   TIMESTAMPTZ,
  el_end_date     TIMESTAMPTZ,
  el_auth_type    TEXT    NOT NULL
);
CREATE UNIQUE INDEX spel_title ON securepoll_elections (el_title);


-- Voter list, independent for each election
-- See Voter.php
CREATE SEQUENCE securepoll_voters_voter_id_seq;
CREATE TABLE securepoll_voters (
  voter_id         INTEGER NOT NULL PRIMARY KEY DEFAULT nextval('securepoll_voters_voter_id_seq'),
  voter_election   INTEGER NOT NULL
    REFERENCES securepoll_elections(el_entity) ON UPDATE CASCADE ON DELETE CASCADE,
  voter_name       TEXT NOT NULL,
  voter_type       TEXT NOT NULL,
  voter_domain     TEXT NOT NULL,
  voter_url        TEXT,
  voter_properties TEXT
);
CREATE INDEX spvoter_elec_name_domain ON securepoll_voters (voter_election, voter_name, voter_domain);


-- Votes that have been cast
-- Contains a blob with answers to all questions
CREATE SEQUENCE securepoll_votes_vote_id_seq;
CREATE TABLE securepoll_votes (
  vote_id           INTEGER NOT NULL PRIMARY KEY DEFAULT nextval('securepoll_votes_vote_id_seq'),
  vote_election     INTEGER NOT NULL
    REFERENCES securepoll_elections(el_entity) ON UPDATE CASCADE ON DELETE CASCADE,
  vote_voter        INTEGER NOT NULL
    REFERENCES securepoll_voters(voter_id) ON UPDATE CASCADE ON DELETE CASCADE,
  vote_voter_name   TEXT NOT NULL,
  vote_voter_domain TEXT NOT NULL,
  vote_struck       SMALLINT NOT NULL,
  vote_record       TEXT NOT NULL,
  vote_ip           TEXT NOT NULL,
  vote_xff          TEXT NOT NULL,
  vote_ua           TEXT NOT NULL,
  vote_timestamp    TIMESTAMPTZ NOT NULL,
  vote_current      SMALLINT NOT NULL,
  vote_token_match  SMALLINT NOT NULL,
  vote_cookie_dup   SMALLINT NOT NULL
);
CREATE INDEX spvote_timestamp ON securepoll_votes (vote_election, vote_timestamp);
CREATE INDEX spvote_voter_name ON securepoll_votes (vote_election, vote_voter_name, vote_timestamp);
CREATE INDEX spvote_voter_domain ON securepoll_votes (vote_election, vote_voter_domain, vote_timestamp);
CREATE INDEX spvote_ip ON securepoll_votes (vote_election, vote_ip, vote_timestamp);


-- Log of admin strike actions
CREATE SEQUENCE securepoll_strike_st_id_seq;
CREATE TABLE securepoll_strike (
  -- Primary key
  st_id        INTEGER NOT NULL PRIMARY KEY DEFAULT nextval('securepoll_strike_st_id_seq'),
  st_vote      INTEGER NOT NULL
    REFERENCES securepoll_votes(vote_id) ON UPDATE CASCADE ON DELETE CASCADE,
  st_timestamp TIMESTAMPTZ NOT NULL,
  st_action    TEXT NOT NULL,
  st_reason    TEXT NOT NULL,
  st_user      INTEGER NOT NULL
);
CREATE INDEX spstrike_vote ON securepoll_strike (st_vote, st_timestamp);


-- Suspicious cookie match logs
CREATE SEQUENCE securepoll_cookie_match_cm_id_seq;
CREATE TABLE securepoll_cookie_match (
  -- Primary key
  cm_id        INTEGER NOT NULL PRIMARY KEY DEFAULT nextval('securepoll_cookie_match_cm_id_seq'),
  cm_election  INTEGER NOT NULL
    REFERENCES securepoll_elections(el_entity) ON UPDATE CASCADE ON DELETE CASCADE,
  cm_voter_1   INTEGER NOT NULL
    REFERENCES securepoll_voters(voter_id) ON UPDATE CASCADE ON DELETE CASCADE,
  cm_voter_2   INTEGER NOT NULL
    REFERENCES securepoll_voters(voter_id) ON UPDATE CASCADE ON DELETE CASCADE,
  cm_timestamp TIMESTAMPTZ NOT NULL
);
CREATE INDEX spcookie_match_voter_1 ON securepoll_cookie_match (cm_voter_1, cm_timestamp);
CREATE INDEX spcookie_match_voter_2 ON securepoll_cookie_match (cm_voter_2, cm_timestamp);

COMMIT;
