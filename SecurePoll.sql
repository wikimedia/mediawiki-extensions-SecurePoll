
-- i18n text associated with an entity
CREATE TABLE /*_*/securepoll_msgs (
	-- securepoll_entity.en_id
	msg_entity int not null,

	-- Language code
	msg_lang varbinary(32) not null,

	-- Message key
	msg_key varbinary(32) not null,

	-- Message text, UTF-8 encoded
	msg_text mediumtext not null
) /*$wgDBTableOptions*/;
CREATE UNIQUE INDEX /*i*/spmsg_entity ON /*_*/securepoll_msgs (msg_entity, msg_lang, msg_key);


-- List of elections (or polls, surveys, etc)
CREATE TABLE /*_*/securepoll_elections (
	-- securepoll_entity.en_id
	el_entity int not null primary key,

	-- Election title
	-- Only used for the election list on the entry page
	el_title varchar(255) not null,

	-- Owner user.user_id
	el_owner int not null,

	-- Ballot type, see Ballot.php
	el_ballot varchar(32) not null,

	-- Tally type, see Tally.php
	el_tally varchar(32) not null,

	-- Primary (administrative) language
	-- This is the primary source for translations
	el_primary_lang varbinary(32) not null,

	-- Start date, in 14-char MW format
	el_start_date varbinary(14),

	-- End date, in 14-char MW format
	el_end_date varbinary(14),

	-- User authorization type, see Auth.php
	el_auth_type varbinary(32) not null
) /*$wgDBTableOptions*/;
CREATE UNIQUE INDEX /*i*/spel_title ON /*_*/securepoll_elections (el_title);


-- Voter list, independent for each election
-- See Voter.php
CREATE TABLE /*_*/securepoll_voters (
	-- Primary key
	voter_id int not null primary key auto_increment,
	-- securepoll_elections.el_id
	voter_election int not null,
	-- The voter's name, as it appears on the remote site
	voter_name varchar(255) binary not null,
	-- The auth type that created this voter
	voter_type varbinary(32) not null,
	-- The voter's domain, should be fully-qualified
	voter_domain varbinary(255) not null,
	-- A URL uniquely identifying the voter
	voter_url blob,
	-- serialized properties blob
	voter_properties blob
) /*$wgDBTableOptions*/;
CREATE INDEX /*i*/spvoter_elec_name_domain ON /*_*/securepoll_voters
	(voter_election, voter_name, voter_domain);

-- Votes that have been cast
-- Contains a blob with answers to all questions
CREATE TABLE /*_*/securepoll_votes (
	vote_id int not null primary key auto_increment,
	-- securepoll_elections.el_id
	vote_election int not null,
	-- securepoll_voters.voter_id
	vote_voter int not null,

	-- Denormalized fields from the user table for efficient sorting

	-- securepoll_voters.voter_name
	vote_voter_name varchar(255) binary not null,
	-- securepoll_voters.voter_domain
	vote_voter_domain varbinary(32) not null,

	-- Denormalized field from the strike table
	-- 1 if struck, 0 if not struck
	vote_struck tinyint not null,

	-- The voting record, produced and interpreted by the ballot type
	-- May be encrypted
	vote_record blob not null,

	-- The IP address, in hexadecimal form (IPUtils::toHex())
	vote_ip varbinary(35) not null,

	-- The X-Forwarded-For header
	vote_xff varbinary(255) not null,

	-- The User-Agent header
	vote_ua varbinary(255) not null,

	-- MW-format timestamp when the vote was cast
	vote_timestamp varbinary(14) not null,

	-- 1 if the vote is current, 0 if old
	-- Only one vote with a given voter will have vote_current=1
	vote_current tinyint not null,

	-- 1 if the CSRF token matched (good), 0 for a potential hack
	vote_token_match tinyint not null,

	-- 1 if the vote is flagged as being made by a potential sockpuppet
	-- Details in securepoll_cookie_match
	vote_cookie_dup tinyint not null
) /*$wgDBTableOptions*/;
-- For list subpage, sorted by timestamp
CREATE INDEX /*i*/spvote_timestamp ON /*_*/securepoll_votes
	(vote_election, vote_timestamp);
-- For list subpage, sorted by name
CREATE INDEX /*i*/spvote_voter_name ON /*_*/securepoll_votes
	(vote_election, vote_voter_name, vote_timestamp);
-- For list subpage, sorted by domain
CREATE INDEX /*i*/spvote_voter_domain ON /*_*/securepoll_votes
	(vote_election, vote_voter_domain, vote_timestamp);
-- For list subpage, sorted by IP
CREATE INDEX /*i*/spvote_ip ON /*_*/securepoll_votes
	(vote_election, vote_ip, vote_timestamp);

-- Log of admin strike actions
CREATE TABLE /*_*/securepoll_strike (
	-- Primary key
	st_id int not null primary key auto_increment,

	-- securepoll_votes.vote_id
	st_vote int not null,

	-- Time at which the action occurred
	st_timestamp varbinary(14) not null,

	-- "strike" or "unstrike"
	st_action varbinary(32) not null,

	-- Explanatory reason
	st_reason varchar(255) not null,

	-- user.user_id who did the action
	st_user int not null
) /*$wgDBTableOptions*/;
-- For details subpage (strike log)
CREATE INDEX /*i*/spstrike_vote ON /*_*/securepoll_strike
	(st_vote, st_timestamp);


-- Suspicious cookie match logs
CREATE TABLE /*_*/securepoll_cookie_match (
	-- Primary key
	cm_id int not null primary key auto_increment,
	-- securepoll_elections.el_id
	cm_election int not null,
	-- securepoll_voters.voter_id
	cm_voter_1 int not null,
	-- securepoll_voters.voter_id
	cm_voter_2 int not null,
	-- Timestamp at which the match was logged
	cm_timestamp varbinary(14) not null
) /*$wgDBTableOptions*/;
CREATE INDEX /*i*/spcookie_match_voter_1 ON /*_*/securepoll_cookie_match
	(cm_voter_1, cm_timestamp);
CREATE INDEX /*i*/spcookie_match_voter_2 ON /*_*/securepoll_cookie_match
	(cm_voter_2, cm_timestamp);
