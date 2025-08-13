<?php

/******************************************************************
 *
 * dbdef()
 *  -> definitions($db)
 *  -> translations($db)
 *
 ******************************************************************/

class dbdef {

  public static function translations($db) {

    return ($db->driver()->mysql)
    ? [
        'uuid'      => 'varchar(36)',
        'uuiddef'   => "(LOWER(CONCAT(LPAD(HEX(RAND()*0xffffffff),8,'0'),'-',LPAD(HEX(RAND()*0xffff),4,'0'),'-4',LPAD(HEX(RAND()*0xfff),3,'0'),'-',HEX(FLOOR(RAND()*4)+8), LPAD(HEX(RAND()*0xfff),3,'0'),'-',LPAD(HEX(RAND()*0xffffffffffff),12,'0'))))",
        'varchar'   => 'varchar',
        'bool'      => 'tinyint',
        'int2'      => 'smallint',
        'int4'      => 'int',
        'numeric'   => 'float',
        'timestamp' => 'timestamp',
        'timestdef' => 'current_timestamp()'
        ]
    : [
        'uuid'      => 'uuid',
        'uuiddef'   => 'uuid_generate_v4()',
        'character varying'   => 'varchar',
        'bool'      => 'boolean',
        'int2'      => 'smallint',
        'int4'      => 'integer',
        'numeric'   => 'numeric',
        'blob'      => 'bytea',
        'timestdef' => 'now()'
    ];
  }

  public static function definitions() {

    return [
        // Settings
        'setting_varchar' => [
            [
                'id'            => ['uuid',     null,   false,  'uuiddef'],
                'name'          => ['varchar',  64,     false,  null],
                'value'         => ['varchar',  128,    false,  null]
            ],
            []
        ],
        'setting_decimal' => [
            [
                'id'            => ['uuid',     null,   false,  'uuiddef'],
                'name'          => ['varchar',  64,     false,  null],
                'value'         => ['numeric',  null,   false,  null]
            ],
            []
        ],
        // Sessman
        'sessman_user' => [
            [
                'id'            => ['uuid',     null,   false,  'uuiddef'],
                'username'      => ['varchar',  128,    false,  null],
                'secret'        => ['varchar',  1024,   false,  null],
                'firstname'     => ['varchar',  128,    true,   null],
                'lastname'      => ['varchar',  128,    true,   null],
                'active'        => ['bool',     null,   false,  'true'],
                'tokens'        => ['bool',     null,   false,  'false'],
                'sa'            => ['bool',     null,   false,  'false'],
                'totp'          => ['bool',     null,   false,  'false'],
                'totp_secret'   => ['varchar',  128,    true,   null]
            ],
            []
        ],
        'sessman_group' => [
            [
                'id'            => ['uuid',     null,   false,  'uuiddef'],
                'groupname'     => ['varchar',  128,    false,  null],
                'ldapcn'        => ['varchar',  1024,   true,   null],
                'radiusattr'    => ['varchar',  1024,   true,   null]
            ],
            []
        ],
        'sessman_usergroups' => [
            [
                'smuser'        => ['uuid',     null,   false,  null],
                'smgroup'       => ['uuid',     null,   false,  null]
            ],
            []
        ],
        'sessman_usertoken' => [
            [
                'id'            => ['uuid',     null,   false,  'uuiddef'],
                'name'          => ['varchar',  128,    false,  null],
                'token'         => ['varchar',  128,    false,  null],
                'expiry'        => ['timestamp',null,   false,  null]
            ],
            []
        ],
        // NTree
        'ntree' => [
            [
                'id'            => ['uuid',     null,   false,  'uuiddef'],
                'parent'        => ['uuid',     null,   true,   null],
                'prio'          => ['int4',     null,   true,   null],
                'name'          => ['varchar',  64,     false,  null]
            ],
            []
        ],
        // Object
        'obj' => [
            [
                'id'            => ['uuid',     null,   false,  'uuiddef'],
                'objtype'       => ['uuid',     null,   false,  null]
            ],
            []
        ],
        'obj_obj' => [
            [
                'obj'           => ['uuid',     null,   false,  null],
                'obj_ref'       => ['uuid',     null,   false,  null]
            ],
            []
        ],
        'obj_log' => [
            [
                'obj'           => ['uuid',     null,   false,  'uuiddef'],
                'timestamp'     => ['timestamp',null,   false,  'now()'],
                'username'      => ['varchar',  128,    false,  null],
                'details'       => ['varchar',  1024,   false,  null]
            ],
            []
        ],
        // Object Type
        'objtype' => [
            [
                'id'            => ['uuid',     null,   false,  'uuiddef'],
                'name'          => ['varchar',  128,    false,  null],
                'log'           => ['bool',     null,   true,   false],
                'short'         => ['int2',     null,   false,  false],
                'map'           => ['uuid',     null,   false,  null]
            ],
            []
        ],
        'objtype_acl' => [
            [
                'objtype'       => ['uuid',     null,   false,  null],
                'smgroup'       => ['uuid',     null,   false,  null],
                'read'          => ['bool',     null,   true,   false],
                'create'        => ['bool',     null,   true,   false],
                'update'        => ['bool',     null,   true,   false],
                'delete'        => ['bool',     null,   true,   false]
            ],
            []
        ],
        'objtype_objtype' => [
            [
                'objtype'       => ['uuid',     null,   false,  null],
                'objtype_ref'   => ['uuid',     null,   false,  null]
            ],
            []
        ],
        'objtype_log' => [
            [
                'objtype'       => ['uuid',     null,   false,  null],
                'timestamp'     => ['timestamp',null,   false,  'now()'],
                'username'      => ['varchar',  null,   false,  null],
                'action'        => ['int2',     null,   false,  null],
                'details'       => ['varchar',  null,   false,  null]
            ],
            []
        ],
        // Object Property
        'objproperty' => [
            [
                'id'            => ['uuid',     null,   false,  'uuiddef'],
                'objtype'       => ['uuid',     null,   false,  null],
                'name'          => ['varchar',  128,    false,  null],
                'type'          => ['int4',     null,   false,  null],
                'prio'          => ['int4',     null,   true,   null],
                'required'      => ['bool',     null,   true,   null],
                'validate_regex'=> ['varchar',  128,    false,  null],
                'validate_msg'  => ['varchar',  128,    false,  null],
                'type_objtype'  => ['uuid',     null,   true,   null],
                'type_valuemap' => ['uuid',     null,   true,   null],
                'frm_visible'   => ['bool',     null,   true,   null],
                'frm_readonly'  => ['bool',     null,   true,   null],
                'tbl_visible'   => ['bool',     null,   true,   null],
                'tbl_orderable' => ['bool',     null,   true,   null]
            ],
            []
        ],
        // Values
        'value_decimal' => [
            [
                'obj'           => ['uuid',     null,   false,  'uuiddef'],
                'objproperty'   => ['uuid',     null,   false,  null],
                'value'         => ['numeric',  null,   true,   null]
            ],
            []
        ],
        'value_text' => [
            [
                'obj'           => ['uuid',     null,   false,  'uuiddef'],
                'objproperty'   => ['uuid',     null,   false,  null],
                'value'         => ['text',  null,   true,   null]
            ],
            []
        ],
        'value_timestamp' => [
            [
                'obj'           => ['uuid',     null,   false,  'uuiddef'],
                'objproperty'   => ['uuid',     null,   false,  null],
                'value'         => ['timestamp',null,   true,   null]
            ],
            []
        ],
        'value_uuid' => [
            [
                'obj'           => ['uuid',     null,   false,  'uuiddef'],
                'objproperty'   => ['uuid',     null,   false,  null],
                'value'         => ['uuid',     null,   true,   null]
            ],
            []
        ],
        'value_varchar' => [
            [
                'obj'           => ['uuid',     null,   false,  'uuiddef'],
                'objproperty'   => ['uuid',     null,   false,  null],
                'value'         => ['varchar',  1024,   true,   null]
            ],
            []
        ],
        'value_blob' => [
            [
                'obj'           => ['uuid',     null,   false,  null],
                'objproperty'   => ['uuid',     null,   false,  null],
                'value'         => ['varchar',  36,     false,  null],
                'data'          => ['blob',     null,   false,  null]
            ],
            []
        ],
        // Value Map
        'valuemap' => [
            [
                'id'            => ['uuid',     null,   false,  'uuiddef'],
                'name'          => ['varchar',  128,    false,  null],
                'prio'          => ['bool',     null,   true,   null]
            ],
            []
        ],
        'valuemap_value' => [
            [
                'id'            => ['uuid',     null,   false,  'uuiddef'],
                'valuemap'      => ['uuid',     null,   false,  null],
                'prio'          => ['int4',     null,   true,   null],
                'name'          => ['varchar',  128,    false,  null]
            ],
            []
        ]
    ];
  }
}