/******************************************************************
 *
 * Global constants
 *
 ******************************************************************/

// Content for loader screen
const loader =
  $('<div/>', { class: 'content-wrapper', style: 'z-index: 20' }).append(
    $('<img/>', { src: 'img/sqbf.gif', class: 'center', width: 40 })
  )

// Definitions for global types
const def = {
    prop: {
    text            : { id: 1, jftype: 'string',    name: 'Text' },
    number          : { id: 2, jftype: 'number',    name: 'Number' },
    select_objtype  : { id: 3, jftype: 'select',    name: 'Select (Object Type)' },
    select_valuemap : { id: 4, jftype: 'select',    name: 'Select (Value Map)' },
    checkbox        : { id: 5, jftype: 'boolean',   name: 'Checkbox' },
    textbox         : { id: 6, jftype: 'textarea',  name: 'Textbox' },
    password        : { id: 7, jftype: 'password',  name: 'Password' },
    date            : { id: 8, jftype: 'string',    name: 'Date' },
    datetime        : { id: 9, jftype: 'string',    name: 'DateTime' }
    },
    logtype         : { 1:'Create', 2:'Update', 5:'Assign', 6:'Unassign', 9:'Delete', 10:'ObjectType' }
  };
