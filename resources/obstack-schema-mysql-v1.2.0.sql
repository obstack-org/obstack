# Minimal db_version(s)
#     MySQL 8.0
#     MariaDB v10.2
# For MariaDB v10.10:
#   id varchar(36) NOT NULL DEFAULT ()
#   LOWER(CONCAT(HEX(RANDOM_BYTES(4)),'-',HEX(RANDOM_BYTES(2)),'-4',SUBSTR(HEX(RANDOM_BYTES(2)),2,3),'-',HEX(FLOOR(ASCII(RANDOM_BYTES(1))/64)+8),SUBSTR(HEX(RANDOM_BYTES(2)),2,3),'-',HEX(RANDOM_BYTES(6))))


DELIMITER $$
CREATE FUNCTION uuid_generate_v4() RETURNS CHAR(36)
BEGIN
  RETURN LOWER(CONCAT(LPAD(HEX(RAND()*0xffffffff),8,'0'),'-',LPAD(HEX(RAND()*0xffff),4,'0'),'-4',LPAD(HEX(RAND()*0xfff),3,'0'),'-',HEX(FLOOR(RAND()*4)+8), LPAD(HEX(RAND()*0xfff),3,'0'),'-',LPAD(HEX(RAND()*0xffffffffffff),12,'0')));
END$$
DELIMITER ;

CREATE TABLE sessman_user (
	id varchar(36) NOT NULL DEFAULT (LOWER(CONCAT(LPAD(HEX(RAND()*0xffffffff),8,'0'),'-',LPAD(HEX(RAND()*0xffff),4,'0'),'-4',LPAD(HEX(RAND()*0xfff),3,'0'),'-',HEX(FLOOR(RAND()*4)+8), LPAD(HEX(RAND()*0xfff),3,'0'),'-',LPAD(HEX(RAND()*0xffffffffffff),12,'0')))),
	username varchar(128) NOT NULL,
	secret varchar(1024) NOT NULL,
	firstname varchar(128) NULL,
	lastname varchar(128) NULL,
	active bool NULL DEFAULT true,
	tokens bool NULL DEFAULT false,
	sa bool NULL DEFAULT false,
	totp bool NOT NULL DEFAULT false,
	totp_secret varchar(128),
	PRIMARY KEY (id),
	UNIQUE (username)
);

CREATE TABLE sessman_group (
	id varchar(36) NOT NULL DEFAULT (LOWER(CONCAT(LPAD(HEX(RAND()*0xffffffff),8,'0'),'-',LPAD(HEX(RAND()*0xffff),4,'0'),'-4',LPAD(HEX(RAND()*0xfff),3,'0'),'-',HEX(FLOOR(RAND()*4)+8), LPAD(HEX(RAND()*0xfff),3,'0'),'-',LPAD(HEX(RAND()*0xffffffffffff),12,'0')))),
	groupname varchar(128) NOT NULL,
	ldapcn varchar(1024) NULL,
	radiusattr varchar(1024) NULL,
	PRIMARY KEY (id)
);

CREATE TABLE sessman_usergroups (
	smuser varchar(36) NOT NULL,
	smgroup varchar(36) NOT NULL,
	PRIMARY KEY (smuser, smgroup),
	CONSTRAINT sessman_usergroups_fk FOREIGN KEY (smuser) REFERENCES sessman_user(id),
	CONSTRAINT sessman_usergroups_fk_1 FOREIGN KEY (smgroup) REFERENCES sessman_group(id)
);

CREATE TABLE sessman_usertokens (
	id varchar(36) NOT NULL DEFAULT (LOWER(CONCAT(LPAD(HEX(RAND()*0xffffffff),8,'0'),'-',LPAD(HEX(RAND()*0xffff),4,'0'),'-4',LPAD(HEX(RAND()*0xfff),3,'0'),'-',HEX(FLOOR(RAND()*4)+8), LPAD(HEX(RAND()*0xfff),3,'0'),'-',LPAD(HEX(RAND()*0xffffffffffff),12,'0')))),
	smuser varchar(36) NOT NULL,
	name varchar(128) NULL,
	token varchar(128) NOT NULL,
	expiry timestamp NULL,
	CONSTRAINT sessman_usertokens_pk PRIMARY KEY (id),
	CONSTRAINT sessman_usertokens_fk FOREIGN KEY (smuser) REFERENCES sessman_user(id)
);

CREATE TABLE ntree (
	id varchar(36) NOT NULL DEFAULT (LOWER(CONCAT(LPAD(HEX(RAND()*0xffffffff),8,'0'),'-',LPAD(HEX(RAND()*0xffff),4,'0'),'-4',LPAD(HEX(RAND()*0xfff),3,'0'),'-',HEX(FLOOR(RAND()*4)+8), LPAD(HEX(RAND()*0xfff),3,'0'),'-',LPAD(HEX(RAND()*0xffffffffffff),12,'0')))),
	parent varchar(64),
	prio int4 NULL,
	name varchar(64) NOT NULL,
	PRIMARY KEY (id),
	CONSTRAINT ntree_ntree_fk FOREIGN KEY (parent) REFERENCES ntree(id)
);

CREATE TABLE setting_varchar (
	id varchar(36) NOT NULL DEFAULT (LOWER(CONCAT(LPAD(HEX(RAND()*0xffffffff),8,'0'),'-',LPAD(HEX(RAND()*0xffff),4,'0'),'-4',LPAD(HEX(RAND()*0xfff),3,'0'),'-',HEX(FLOOR(RAND()*4)+8), LPAD(HEX(RAND()*0xfff),3,'0'),'-',LPAD(HEX(RAND()*0xffffffffffff),12,'0')))),
	name varchar(64) NOT NULL,
	value varchar(128) NOT NULL,
	CONSTRAINT setting_varchar_pk PRIMARY KEY (id),
	CONSTRAINT setting_varchar_un UNIQUE (name)
);

CREATE TABLE setting_decimal (
	id varchar(36) NOT NULL DEFAULT (LOWER(CONCAT(LPAD(HEX(RAND()*0xffffffff),8,'0'),'-',LPAD(HEX(RAND()*0xffff),4,'0'),'-4',LPAD(HEX(RAND()*0xfff),3,'0'),'-',HEX(FLOOR(RAND()*4)+8), LPAD(HEX(RAND()*0xfff),3,'0'),'-',LPAD(HEX(RAND()*0xffffffffffff),12,'0')))),
	name varchar(64) NOT NULL,
	value float NOT NULL,
	CONSTRAINT setting_decimal_pk PRIMARY KEY (id),
	CONSTRAINT setting_decimal_un UNIQUE (name)
);

CREATE TABLE obj (
	id varchar(36) NOT NULL DEFAULT (LOWER(CONCAT(LPAD(HEX(RAND()*0xffffffff),8,'0'),'-',LPAD(HEX(RAND()*0xffff),4,'0'),'-4',LPAD(HEX(RAND()*0xfff),3,'0'),'-',HEX(FLOOR(RAND()*4)+8), LPAD(HEX(RAND()*0xfff),3,'0'),'-',LPAD(HEX(RAND()*0xffffffffffff),12,'0')))),
	objtype varchar(36) NOT NULL,
	PRIMARY KEY (id, objtype)
);

CREATE TABLE obj_obj (
	obj varchar(36) NOT NULL,
	obj_ref varchar(36) NOT NULL,
	PRIMARY KEY (obj, obj_ref),
	CONSTRAINT obj_obj_check CHECK ((obj > obj_ref))
);

CREATE TABLE obj_log (
	obj varchar(36) NOT NULL,
	timestamp timestamp NOT NULL DEFAULT now(),
	username varchar(128) NOT NULL,
	action int2 NOT NULL,
	details varchar(1024) NULL
);

CREATE TABLE objtype (
	id varchar(36) NOT NULL DEFAULT (LOWER(CONCAT(LPAD(HEX(RAND()*0xffffffff),8,'0'),'-',LPAD(HEX(RAND()*0xffff),4,'0'),'-4',LPAD(HEX(RAND()*0xfff),3,'0'),'-',HEX(FLOOR(RAND()*4)+8), LPAD(HEX(RAND()*0xfff),3,'0'),'-',LPAD(HEX(RAND()*0xffffffffffff),12,'0')))),
	name varchar(128) NOT NULL,
	log bool NULL DEFAULT false,
	short int2 NOT NULL DEFAULT 4,
	map varchar(64),
	PRIMARY KEY (id),
	CONSTRAINT objtype_ntree_fk FOREIGN KEY (map) REFERENCES ntree(id)
);

CREATE TABLE objtype_acl (
	objtype varchar(36) NOT NULL,
	smgroup varchar(36) NOT NULL,
	`read` bool NULL,
	`create` bool NULL,
	`update` bool NULL,
	`delete` bool NULL,
	PRIMARY KEY (objtype, smgroup),
	CONSTRAINT objtype_acl_fk_group FOREIGN KEY (smgroup) REFERENCES sessman_group(id),
	CONSTRAINT objtype_acl_fk_objtype FOREIGN KEY (objtype) REFERENCES objtype(id)
);

CREATE TABLE objtype_log (
	objtype varchar(36) NOT NULL,
	timestamp timestamp NOT NULL DEFAULT now(),
	username varchar(128) NOT NULL,
	action int2 NOT NULL,
	details varchar(1024) NULL
);

CREATE TABLE objtype_objtype (
	objtype varchar(36) NOT NULL,
	objtype_ref varchar(36) NOT NULL,
	PRIMARY KEY (objtype, objtype_ref),
	CONSTRAINT objtype_objtype_check CHECK ((objtype >= objtype_ref)),
	CONSTRAINT objtype_objtype_fk FOREIGN KEY (objtype) REFERENCES objtype(id),
	CONSTRAINT objtype_objtype_fk_1 FOREIGN KEY (objtype_ref) REFERENCES objtype(id)
);

CREATE TABLE objproperty (
	id varchar(36) NOT NULL DEFAULT (LOWER(CONCAT(LPAD(HEX(RAND()*0xffffffff),8,'0'),'-',LPAD(HEX(RAND()*0xffff),4,'0'),'-4',LPAD(HEX(RAND()*0xfff),3,'0'),'-',HEX(FLOOR(RAND()*4)+8), LPAD(HEX(RAND()*0xfff),3,'0'),'-',LPAD(HEX(RAND()*0xffffffffffff),12,'0')))),
	objtype varchar(36) NOT NULL,
	name varchar(128) NOT NULL,
	type int4 NOT NULL,
	prio int4 NULL,
	required bool NULL,
	validate_regex varchar(128) NULL,
	validate_msg varchar(128) NULL,
	type_objtype varchar(36) NULL,
	type_valuemap varchar(36) NULL,
	frm_visible bool NULL,
	frm_readonly bool NULL,
	tbl_visible bool NULL,
	tbl_orderable bool NULL,
	PRIMARY KEY (id, objtype)
);

CREATE TABLE value_decimal (
	obj varchar(36) NOT NULL,
	objproperty varchar(36) NOT NULL,
	value float NULL,
	PRIMARY KEY (obj, objproperty)
);

CREATE TABLE value_text (
	obj varchar(36) NOT NULL,
	objproperty varchar(36) NOT NULL,
	value text NULL,
	PRIMARY KEY (obj, objproperty)
);

CREATE TABLE value_timestamp (
	obj varchar(36) NOT NULL,
	objproperty varchar(36) NOT NULL,
	value timestamp NULL,
	PRIMARY KEY (obj, objproperty)
);

CREATE TABLE value_uuid (
	obj varchar(36) NOT NULL,
	objproperty varchar(36) NOT NULL,
	value varchar(36) NULL,
	PRIMARY KEY (obj, objproperty)
);

CREATE TABLE value_varchar (
	obj varchar(36) NOT NULL,
	objproperty varchar(36) NOT NULL,
	value varchar(1024) NULL,
	PRIMARY KEY (obj, objproperty)
);

CREATE TABLE value_blob (
	obj varchar(36) NOT NULL,
	objproperty varchar(36) NOT NULL,
	value varchar(36) NULL,
	data longblob NULL,
	PRIMARY KEY (obj, objproperty)
);

CREATE TABLE valuemap (
	id varchar(36) NOT NULL DEFAULT (LOWER(CONCAT(LPAD(HEX(RAND()*0xffffffff),8,'0'),'-',LPAD(HEX(RAND()*0xffff),4,'0'),'-4',LPAD(HEX(RAND()*0xfff),3,'0'),'-',HEX(FLOOR(RAND()*4)+8), LPAD(HEX(RAND()*0xfff),3,'0'),'-',LPAD(HEX(RAND()*0xffffffffffff),12,'0')))),
	name varchar(128) NOT NULL,
	prio bool NULL
);

CREATE TABLE valuemap_value (
	id varchar(36) NOT NULL DEFAULT (LOWER(CONCAT(LPAD(HEX(RAND()*0xffffffff),8,'0'),'-',LPAD(HEX(RAND()*0xffff),4,'0'),'-4',LPAD(HEX(RAND()*0xfff),3,'0'),'-',HEX(FLOOR(RAND()*4)+8), LPAD(HEX(RAND()*0xfff),3,'0'),'-',LPAD(HEX(RAND()*0xffffffffffff),12,'0')))),
	valuemap varchar(36) NOT NULL,
	prio int4 NULL,
	name varchar(128) NOT NULL,
	PRIMARY KEY (id, valuemap)
);

INSERT INTO sessman_user (username,secret,active,sa) VALUES ('admin', PASSWORD('admin'), true, true);
INSERT INTO setting_decimal (name, value) VALUES ('db_version', 120), ('totp_default_enabled',0), ('session_timeout',600);
