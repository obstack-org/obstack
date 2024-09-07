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