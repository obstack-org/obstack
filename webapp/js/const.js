/******************************************************************
 *
 * Global constants
 *
 ******************************************************************/

// Definitions for global types
const def = {
    // prop: {
    // text            : { id: 1, jftype: 'string',    name: 'Text' },
    // number          : { id: 2, jftype: 'number',    name: 'Number' },
    // select_objtype  : { id: 3, jftype: 'select',    name: 'Select (Object Type)' },
    // select_valuemap : { id: 4, jftype: 'select',    name: 'Select (Value Map)' },
    // checkbox        : { id: 5, jftype: 'boolean',   name: 'Checkbox' },
    // textbox         : { id: 6, jftype: 'textarea',  name: 'Textbox' },
    // password        : { id: 7, jftype: 'password',  name: 'Password' },
    // date            : { id: 8, jftype: 'string',    name: 'Date' },
    // datetime        : { id: 9, jftype: 'string',    name: 'DateTime' }
    // },
    property: {
      1:'Text',
      2:'Number',
      3:'Select (Object Type)',
      4:'Select (Value Map)',
      5:'Checkbox',
      6:'Textbox',
      // 7:'Password',
      8:'Date',
      9:'DateTime',
      10:'DateTime (now)',
      11:'Password (hash)',
      12:'Password (encrypt)',
    },
    property_type: {
      1:'string',
      2:'number',
      3:'select',
      4:'select',
      5:'checkbox',
      6:'textarea',
      // 7:'password',
      8:'date',
      9:'datetime',
      10:'datetime',
      11:'password',
      12:'password'
    },

    logtype         : { 1:'Create', 2:'Update', 5:'Assign', 6:'Unassign', 9:'Delete', 10:'ObjectType' }
  };
