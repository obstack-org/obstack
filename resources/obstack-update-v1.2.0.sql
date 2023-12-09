CREATE TABLE settings (
	id uuid NOT NULL DEFAULT uuid_generate_v4(),
	name varchar(64) NOT NULL,
	value varchar(128) NOT NULL,
	CONSTRAINT settings_pk PRIMARY KEY (id),
	CONSTRAINT settings_un UNIQUE (name)
);

INSERT INTO settings (name, value) VALUES
('db_version','1.2.0');

CREATE TABLE ntree (
	id uuid NOT NULL DEFAULT uuid_generate_v4(),
	parent uuid,
	prio int4 NULL,
	name varchar(64) NOT NULL,
	CONSTRAINT ntree_pk PRIMARY KEY (id),
	CONSTRAINT ntree_ntree_fk FOREIGN KEY (parent) REFERENCES ntree(id)
);

ALTER TABLE objtype ADD map uuid;

ALTER TABLE objtype ADD CONSTRAINT objtype_ntree_fk FOREIGN KEY (map) REFERENCES ntree(id);
