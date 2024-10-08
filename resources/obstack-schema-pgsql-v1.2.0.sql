
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";

CREATE EXTENSION IF NOT EXISTS "pgcrypto";

CREATE TABLE sessman_user (
	id uuid NOT NULL DEFAULT uuid_generate_v4(),
	username varchar NOT NULL,
	secret varchar NOT NULL,
	totp bool NOT NULL DEFAULT false,
	totp_secret varchar,
	firstname varchar NULL,
	lastname varchar NULL,
	active bool NULL DEFAULT true,
	tokens bool NULL DEFAULT false,
	sa bool NULL DEFAULT false,
	CONSTRAINT sessman_user_pk PRIMARY KEY (id),
	CONSTRAINT sessman_user_un UNIQUE (username)
);

CREATE TABLE sessman_group (
	id uuid NOT NULL DEFAULT uuid_generate_v4(),
	groupname varchar NOT NULL,
	ldapcn varchar NULL,
	radiusattr varchar NULL,
	CONSTRAINT sessman_group_pk PRIMARY KEY (id)
);

CREATE TABLE sessman_usergroups (
	smuser uuid NOT NULL,
	smgroup uuid NOT NULL,
	CONSTRAINT sessman_usergroups_pk PRIMARY KEY (smuser, smgroup),
	CONSTRAINT sessman_usergroups_fk FOREIGN KEY (smuser) REFERENCES sessman_user(id),
	CONSTRAINT sessman_usergroups_fk_1 FOREIGN KEY (smgroup) REFERENCES sessman_group(id)
);

CREATE TABLE sessman_usertokens (
	id uuid NOT NULL DEFAULT uuid_generate_v4(),
	smuser uuid NOT NULL,
	"name" varchar NULL,
	"token" varchar NOT NULL,
	expiry timestamp NULL,
	CONSTRAINT sessman_usertokens_pk PRIMARY KEY (id),
	CONSTRAINT sessman_usertokens_fk FOREIGN KEY (smuser) REFERENCES sessman_user(id)
);

CREATE TABLE setting_varchar (
	id uuid NOT NULL DEFAULT uuid_generate_v4(),
	name varchar(64) NOT NULL,
	value varchar(128) NOT NULL,
	CONSTRAINT setting_varchar_pk PRIMARY KEY (id),
	CONSTRAINT setting_varchar_un UNIQUE (name)
);

CREATE TABLE setting_decimal (
	id uuid NOT NULL DEFAULT uuid_generate_v4(),
	name varchar(64) NOT NULL,
	value numeric(16, 8) NOT NULL,
	CONSTRAINT setting_decimal_pk PRIMARY KEY (id),
	CONSTRAINT setting_decimal_un UNIQUE (name)
);

CREATE TABLE ntree (
	id uuid NOT NULL DEFAULT uuid_generate_v4(),
	parent uuid,
	prio int4 NULL,
	name varchar(64) NOT NULL,
	CONSTRAINT ntree_pk PRIMARY KEY (id),
	CONSTRAINT ntree_ntree_fk FOREIGN KEY (parent) REFERENCES ntree(id)
);

CREATE TABLE obj (
	id uuid NOT NULL DEFAULT uuid_generate_v4(),
	objtype uuid NOT NULL,
	CONSTRAINT obj_pkey PRIMARY KEY (id, objtype)
);

CREATE TABLE obj_obj (
	obj uuid NOT NULL,
	obj_ref uuid NOT NULL,
	CONSTRAINT obj_obj_check CHECK ((obj > obj_ref)),
	CONSTRAINT obj_obj_pkey PRIMARY KEY (obj, obj_ref)
);

CREATE TABLE obj_log (
	obj uuid NOT NULL,
	"timestamp" timestamp NOT NULL DEFAULT now(),
	username varchar NOT NULL,
	"action" int2 NOT NULL,
	details varchar NULL
);

CREATE TABLE objtype (
	id uuid NOT NULL DEFAULT uuid_generate_v4(),
	"name" varchar NOT NULL,
	map uuid,
	log bool NULL DEFAULT false,
	short int2 NOT NULL DEFAULT 4,
	CONSTRAINT obj_type_pkey PRIMARY KEY (id),
	CONSTRAINT objtype_ntree_fk FOREIGN KEY (map) REFERENCES ntree(id)
);

CREATE TABLE objtype_objtype (
	objtype uuid NOT NULL,
	objtype_ref uuid NOT NULL,
	CONSTRAINT objtype_objtype_check CHECK ((objtype >= objtype_ref)),
	CONSTRAINT objtype_objtype_pkey PRIMARY KEY (objtype, objtype_ref),
	CONSTRAINT objtype_objtype_fk FOREIGN KEY (objtype) REFERENCES objtype(id),
	CONSTRAINT objtype_objtype_fk_1 FOREIGN KEY (objtype_ref) REFERENCES objtype(id)
);

CREATE TABLE objtype_acl (
	objtype uuid NOT NULL,
	smgroup uuid NOT NULL,
	"read" bool NULL,
	"create" bool NULL,
	"update" bool NULL,
	"delete" bool NULL,
	CONSTRAINT objtype_acl_pk PRIMARY KEY (objtype, smgroup),
	CONSTRAINT objtype_acl_fk_group FOREIGN KEY (smgroup) REFERENCES sessman_group(id),
	CONSTRAINT objtype_acl_fk_objtype FOREIGN KEY (objtype) REFERENCES objtype(id)
);

CREATE TABLE objtype_log (
	objtype uuid NOT NULL,
	"timestamp" timestamp NOT NULL DEFAULT now(),
	username varchar NOT NULL,
	"action" int2 NOT NULL,
	details varchar NULL
);

CREATE TABLE objproperty (
	id uuid NOT NULL DEFAULT uuid_generate_v4(),
	objtype uuid NOT NULL,
	"name" varchar NOT NULL,
	"type" int4 NOT NULL,
	prio int4 NULL,
	required bool NULL,
	validate_regex varchar NULL,
	validate_msg varchar NULL,
	type_objtype uuid NULL,
	type_valuemap uuid NULL,
	frm_visible bool NULL,
	frm_readonly bool NULL,
	tbl_visible bool NULL,
	tbl_orderable bool NULL,
	CONSTRAINT obj_property_pkey PRIMARY KEY (id, objtype)
);

CREATE TABLE value_decimal (
	obj uuid NOT NULL,
	objproperty uuid NOT NULL,
	value numeric(16, 8) NULL,
	CONSTRAINT value_decimal_pk PRIMARY KEY (obj, objproperty)
);

CREATE TABLE value_text (
	obj uuid NOT NULL,
	objproperty uuid NOT NULL,
	value text NULL,
	CONSTRAINT value_text_pk PRIMARY KEY (obj, objproperty)
);

CREATE TABLE value_timestamp (
	obj uuid NOT NULL,
	objproperty uuid NOT NULL,
	value timestamp NULL,
	CONSTRAINT value_timestamp_pk PRIMARY KEY (obj, objproperty)
);

CREATE TABLE value_uuid (
	obj uuid NOT NULL,
	objproperty uuid NOT NULL,
	value uuid NULL,
	CONSTRAINT value_uuid_pk PRIMARY KEY (obj, objproperty)
);

CREATE TABLE value_varchar (
	obj uuid NOT NULL,
	objproperty uuid NOT NULL,
	value varchar NULL,
	CONSTRAINT value_varchar_pk PRIMARY KEY (obj, objproperty)
);

CREATE TABLE value_blob (
	obj uuid NOT NULL,
	objproperty uuid NOT NULL,
	value varchar NULL,
	data bytea NULL,
	CONSTRAINT value_blob_pk PRIMARY KEY (obj, objproperty)
);

CREATE TABLE valuemap (
	id uuid NOT NULL DEFAULT uuid_generate_v4(),
	"name" varchar NOT NULL,
	prio bool NULL
);

CREATE TABLE valuemap_value (
	id uuid NOT NULL DEFAULT uuid_generate_v4(),
	valuemap uuid NOT NULL,
	prio int4 NULL,
	"name" varchar NOT NULL,
	CONSTRAINT valuemap_option_pkey PRIMARY KEY (id, valuemap)
);

INSERT INTO sessman_user (username,secret,active,sa) VALUES ('admin', crypt('admin', gen_salt('bf')), true, true);
INSERT INTO setting_decimal (name, value) VALUES ('db_version', 120), ('totp_default_enabled',0), ('session_timeout',600);
