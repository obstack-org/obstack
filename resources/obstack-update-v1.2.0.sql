
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

INSERT INTO setting_decimal (name, value) VALUES
('db_version',120),
('totp_default_enabled',0);

CREATE TABLE ntree (
	id uuid NOT NULL DEFAULT uuid_generate_v4(),
	parent uuid,
	prio int4 NULL,
	name varchar(64) NOT NULL,
	CONSTRAINT ntree_pk PRIMARY KEY (id),
	CONSTRAINT ntree_ntree_fk FOREIGN KEY (parent) REFERENCES ntree(id)
);

ALTER TABLE sessman_user ADD COLUMN totp bool NOT NULL DEFAULT false;
ALTER TABLE sessman_user ADD COLUMN totp_secret varchar;

ALTER TABLE objtype ADD map uuid;
ALTER TABLE objtype ADD CONSTRAINT objtype_ntree_fk FOREIGN KEY (map) REFERENCES ntree(id);

UPDATE objproperty SET type = 11 WHERE type = 7;

CREATE TABLE objtype_objtype (
	objtype uuid NOT NULL,
	objtype_ref uuid NOT NULL,
	CONSTRAINT objtype_objtype_check CHECK ((objtype >= objtype_ref)),
	CONSTRAINT objtype_objtype_pkey PRIMARY KEY (objtype, objtype_ref),
	CONSTRAINT objtype_objtype_fk FOREIGN KEY (objtype) REFERENCES objtype(id),
	CONSTRAINT objtype_objtype_fk_1 FOREIGN KEY (objtype_ref) REFERENCES objtype(id)
);

CREATE TABLE public.value_blob (
	obj uuid NOT NULL,
	objproperty uuid NOT NULL,
	value varchar NULL,
	data bytea NULL,
	CONSTRAINT value_blob_pk PRIMARY KEY (obj, objproperty)
);
