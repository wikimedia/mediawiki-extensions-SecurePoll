
CREATE TABLE /*_*/securepoll_entity (
	en_id int not null primary key auto_increment,
	en_type varbinary(32) not null
);

CREATE TABLE /*_*/securepoll_msgs (
	msg_entity int not null,
	msg_lang varbinary(32) not null,
	msg_key varbinary(32) not null,
	msg_text mediumtext not null
);
CREATE UNIQUE INDEX /*i*/spmsg_entity ON /*_*/securepoll_msgs (msg_entity, msg_lang, msg_key);

CREATE TABLE /*_*/securepoll_properties (
	pr_entity int not null,
	pr_key varbinary(32) not null,
	pr_value mediumblob not null
);
CREATE UNIQUE INDEX /*i*/sppr_entity ON /*_*/securepoll_properties (pr_entity, pr_key);

CREATE TABLE /*_*/securepoll_elections (
	el_entity int not null primary key,
	el_title varchar(255) not null,
	el_ballot varchar(32) not null,
	el_tally varchar(32) not null,
	el_primary_lang varbinary(32) not null,
	el_start_date varbinary(14),
	el_end_date varbinary(14),
	el_auth_type varbinary(32) not null
);
CREATE UNIQUE INDEX /*i*/spel_title ON /*_*/securepoll_elections (el_title);

CREATE TABLE /*_*/securepoll_questions (
	qu_entity int not null primary key,
	qu_election int not null,
	qu_index int not null
);
CREATE INDEX /*i*/spqu_election_index ON /*_*/securepoll_questions (qu_election, qu_index, qu_entity);

CREATE TABLE /*_*/securepoll_options (
	op_entity int not null primary key,
	op_election int not null,
	op_question int not null
);
CREATE INDEX /*i*/spop_question ON /*_*/securepoll_options (op_question, op_entity);

CREATE TABLE /*_*/securepoll_voters (
	voter_id int not null primary key auto_increment,
	voter_name varchar(255) binary not null,
	voter_type varbinary(32) not null,
	voter_domain varbinary(255) not null,
	voter_authority blob,
	voter_properties blob
);
CREATE UNIQUE INDEX /*i*/spvoter_name_domain ON /*_*/securepoll_voters (voter_name, voter_domain);

CREATE TABLE /*_*/securepoll_votes (
	vote_id int not null primary key auto_increment,
	vote_election int not null,
	vote_user int not null,
	vote_record blob not null,
	vote_ip varbinary(32) not null,
	vote_xff varbinary(255) not null,
	vote_ua varbinary(255) not null,
	vote_timestamp varbinary(14) not null,
	vote_current tinyint not null,
	vote_token_match tinyint(1) not null
);
CREATE INDEX /*i*/spvote_timestamp ON /*_*/securepoll_votes (vote_election, vote_timestamp);

