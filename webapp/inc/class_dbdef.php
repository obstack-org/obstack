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
            [
                'p' => [ 'id' ],
                'u' => [ 'name' ]
            ]
        ],
        'setting_decimal' => [
            [
                'id'            => ['uuid',     null,   false,  'uuiddef'],
                'name'          => ['varchar',  64,     false,  null],
                'value'         => ['numeric',  null,   false,  null]
            ],
            [
                'p' => [ 'id' ],
                'u' => [ 'name' ]
            ]
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
            [
                'p' => [ 'id' ],
                'u' => [ 'username' ]
            ]
        ],
        'sessman_group' => [
            [
                'id'            => ['uuid',     null,   false,  'uuiddef'],
                'groupname'     => ['varchar',  128,    false,  null],
                'ldapcn'        => ['varchar',  1024,   true,   null],
                'radiusattr'    => ['varchar',  1024,   true,   null]
            ],
            [
                'p' => [ 'id' ],
                'u' => [ 'groupname' ]
            ]
        ],
        'sessman_usergroups' => [
            [
                'smuser'        => ['uuid',     null,   false,  null],
                'smgroup'       => ['uuid',     null,   false,  null]
            ],
            [
                'p' => [ 'smuser', 'smgroup' ],
                'f' => [
                    'smuser'    =>  [ 'sessman_user', 'id' ]
                ]
            ]
        ],
        'sessman_usertoken' => [
            [
                'id'            => ['uuid',     null,   false,  'uuiddef'],
                'name'          => ['varchar',  128,    false,  null],
                'token'         => ['varchar',  128,    false,  null],
                'expiry'        => ['timestamp',null,   false,  null]
            ],
            [
                'p' => [ 'id' ],
                'f' => [
                    'smuser'    =>  [ 'sessman_user', 'id' ]
                ]
            ]
        ],
        // NTree
        'ntree' => [
            [
                'id'            => ['uuid',     null,   false,  'uuiddef'],
                'parent'        => ['uuid',     null,   true,   null],
                'prio'          => ['int4',     null,   true,   null],
                'name'          => ['varchar',  64,     false,  null]
            ],
            [
                'p' => [ 'id' ],
                'f' => [
                    'parent'    =>  [ 'ntree', 'id' ]
                ]
            ]
        ],
        // Object
        'obj' => [
            [
                'id'            => ['uuid',     null,   false,  'uuiddef'],
                'objtype'       => ['uuid',     null,   false,  null]
            ],
            [
                'p' => [ 'id', 'objtype' ]
            ]
        ],
        'obj_obj' => [
            [
                'obj'           => ['uuid',     null,   false,  null],
                'obj_ref'       => ['uuid',     null,   false,  null]
            ],
            [
                'p' => [ 'obj', 'obj_ref' ],
                'c' => [ 'obj > obj_ref' ]
            ]
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
            [
                'p' => [ 'id' ],
                'f' => [
                    'map'    =>  [ 'ntree', 'id' ]
                ]
            ]
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
            [
                'p' => [ 'objtype', 'smgroup' ]
            ]
        ],
        'objtype_objtype' => [
            [
                'objtype'       => ['uuid',     null,   false,  null],
                'objtype_ref'   => ['uuid',     null,   false,  null]
            ],
            [
                'p' => [ 'objtype', 'objtype_ref' ],
                'c' => [ 'objtype >= objtype_ref' ],
                'f' => [
                    'objtype'       =>  [ 'objtype', 'id' ],
                    'objtype_ref'   =>  [ 'objtype', 'id' ]
                ]
            ]
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
            [
                'p' => [ 'id', 'objtype' ],
                'f' => [
                    'objtype'       =>  [ 'objtype', 'id' ]
                ]
            ]
        ],
        // Values
        'value_decimal' => [
            [
                'obj'           => ['uuid',     null,   false,  'uuiddef'],
                'objproperty'   => ['uuid',     null,   false,  null],
                'value'         => ['numeric',  null,   true,   null]
            ],
            [
                'p' => [ 'obj', 'objproperty' ],
                'f' => [
                    'obj'           =>  [ 'obj', 'id' ],
                    'objproperty'   =>  [ 'objproperty', 'id' ]
                ]
            ]
        ],
        'value_text' => [
            [
                'obj'           => ['uuid',     null,   false,  'uuiddef'],
                'objproperty'   => ['uuid',     null,   false,  null],
                'value'         => ['text',  null,   true,   null]
            ],
            [
                'p' => [ 'obj', 'objproperty' ],
                'f' => [
                    'obj'           =>  [ 'obj', 'id' ],
                    'objproperty'   =>  [ 'objproperty', 'id' ]
                ]
            ]
        ],
        'value_timestamp' => [
            [
                'obj'           => ['uuid',     null,   false,  'uuiddef'],
                'objproperty'   => ['uuid',     null,   false,  null],
                'value'         => ['timestamp',null,   true,   null]
            ],
            [
                'p' => [ 'obj', 'objproperty' ],
                'f' => [
                    'obj'           =>  [ 'obj', 'id' ],
                    'objproperty'   =>  [ 'objproperty', 'id' ]
                ]
            ]
        ],
        'value_uuid' => [
            [
                'obj'           => ['uuid',     null,   false,  'uuiddef'],
                'objproperty'   => ['uuid',     null,   false,  null],
                'value'         => ['uuid',     null,   true,   null]
            ],
            [
                'p' => [ 'obj', 'objproperty' ],
                'f' => [
                    'obj'           =>  [ 'obj', 'id' ],
                    'objproperty'   =>  [ 'objproperty', 'id' ]
                ]
            ]
        ],
        'value_varchar' => [
            [
                'obj'           => ['uuid',     null,   false,  'uuiddef'],
                'objproperty'   => ['uuid',     null,   false,  null],
                'value'         => ['varchar',  1024,   true,   null]
            ],
            [
                'p' => [ 'obj', 'objproperty' ],
                'f' => [
                    'obj'           =>  [ 'obj', 'id' ],
                    'objproperty'   =>  [ 'objproperty', 'id' ]
                ]
            ]
        ],
        'value_blob' => [
            [
                'obj'           => ['uuid',     null,   false,  null],
                'objproperty'   => ['uuid',     null,   false,  null],
                'value'         => ['varchar',  36,     false,  null],
                'data'          => ['blob',     null,   false,  null]
            ],
            [
                'p' => [ 'obj', 'objproperty' ],
                'f' => [
                    'obj'           =>  [ 'obj', 'id' ],
                    'objproperty'   =>  [ 'objproperty', 'id' ]
                ]
            ]
        ],
        // Value Map
        'valuemap' => [
            [
                'id'            => ['uuid',     null,   false,  'uuiddef'],
                'name'          => ['varchar',  128,    false,  null],
                'prio'          => ['bool',     null,   true,   null]
            ],
            [
                'p' => [ 'id' ]
            ]
        ],
        'valuemap_value' => [
            [
                'id'            => ['uuid',     null,   false,  'uuiddef'],
                'valuemap'      => ['uuid',     null,   false,  null],
                'prio'          => ['int4',     null,   true,   null],
                'name'          => ['varchar',  128,    false,  null]
            ],
            [
                'p' => [ 'id', 'valuemap' ],
                'f' => [
                    'valuemap'      =>  [ 'valuemap', 'id' ]
                ]
            ]
        ]
    ];
  }
}