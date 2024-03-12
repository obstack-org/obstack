# API Reference - /objecttype

/objecttype provides all access to both objecttypes and objects.

* [/objecttype](#objecttype)
* [/objecttype/{objecttype}](#objecttypeobjecttype)

* [/objecttype/{objecttype}/property](#objecttypeobjecttype-roperty)
* [/objecttype/{objecttype}/property/{property}](#objecttypeobjecttypepropertyproperty)

* [/objecttype/{objecttype}/object](#objecttypeobjecttypeobject)
* [/objecttype/{objecttype}/object/{object}](#objecttypeobjecttypeobjectobject)

* [/objecttype/{objecttype}/object/{object}/relation](#objecttypeobjecttypeobjectobjectrelation)
* [/objecttype/{objecttype}/object/{object}/relation/available](#objecttypeobjecttypeobjectobjectrelationavailable)
* [/objecttype/{objecttype}/object/{object}/relation/{relation}](#objecttypeobjecttypeobjectobjectrelationrelation)

* [/objecttype/{objecttype}/object/{object}/log](#objecttypeobjecttypeobjectobjectlog)

### /objecttype

| Method | Description |
|---|---|
| GET | Returns a list of object types |
| POST | Create new object type |

GET:
```javascript
// Response
[
  {
    "id": "5a774411-81fb-4929-87a5-5c4b41890aeb",
    "name": "My ObjectType"
  },
  {..}
]
```

POST:
```javascript
// Payload
{
  "name": "My ObjectType",
  "log": true,
  "short": 2
}

// Response
{ "id": "5a774411-81fb-4929-87a5-5c4b41890aeb" }
```

### /objecttype/{objecttype}

| Method | Description |
|---|---|
| GET | Returns object type details |
| PUT | Update object type |
| DELETE | Delete object type |

GET:
```javascript
// Response
{
  "name": "My ObjectType",
  "log": true,
  "short": 2
}
```

PUT:
```javascript
// Payload
{
  "name": "My ObjectType",
  "log": true,
  "short": 2
}

// Response
[]
```

DELETE:
```javascript
// Response
{ "delete": true }
```

### /objecttype/{objecttype}/property

| Method | Description |
|---|---|
| GET | Returns a list of object type properties |
| POST | Create new property for this object type |

Object type property key/value explanation:

| Key | Description |
|---|---|
| name | Display name |
| type | Object property type _(next table)_ |
| type_objtype | UUID of object type when property type is 3 |
| type_valuemap | UUID of valuemap when property type is 4 |
| required | Set _true_ when value cannot be empty |
| frm&#95;readonly | Set _true_ when readonly on web GUI form |
| frm&#95;visible | Set _true_ when visible on web GUI form |
| tbl&#95;visible | Set _true_ when visible on web GUI table |
| tbl&#95;orderable | Set _true_ when orderable on web GUI table |

Each object property has a type, that determines both the internal data type and the type of field that is displayed in the web GUI.

| Type | Description |
|---|---|
| 1 | Text - Single line input field |
| 2 | Number - Limited to 8 digits and 8 decimals |
| 3 | Object Type - Select / Dropdown |
| 4 | Value Map - Select / Dropdown |
| 5 | Checkbox |
| 6 | Textbox - Multi line input field |
| 8 | Date |
| 9 | Date and time |
| 11 | Password (hash) |
| 12 | Password (recoverable) |

GET:
```javascript
// Response
[
  {
    "id": "8c28ea28-b94a-4176-ad2a-5d81028e25a0",
    "name": "Name",
    "type": 1,
    "type_objtype": null,
    "type_valuemap": null,
    "required": false,
    "frm_readonly": false,
    "frm_visible": true,
    "tbl_visible": true,
    "tbl_orderable": false
  },
  {..}
]
```

POST:
```javascript
// Payload
{
  "name": "Name",
  "type": 1,
  "type_objtype": null,
  "type_valuemap": null,
  "required": false,
  "frm_readonly": false,
  "frm_visible": true,
  "tbl_visible": true,
  "tbl_orderable": false
}

// Response
{ "id": "8c28ea28-b94a-4176-ad2a-5d81028e25a0" }
```

## /objecttype/{objecttype}/property/{property}

| Method | Description |
|---|---|
| GET | Returns object type property details |
| PUT | Update object type property |
| DELETE | Delete object type property |

GET:
```javascript
// Payload
{
  "name": "Name",
  "type": 1,
  "type_objtype": null,
  "type_valuemap": null,
  "required": false,
  "frm_readonly": false,
  "frm_visible": true,
  "tbl_visible": true,
  "tbl_orderable": false
}
```

PUT:
```javascript
// Payload
{
  "name": "Name",
  "type": 1,
  "type_objtype": null,
  "type_valuemap": null,
  "required": false,
  "frm_readonly": false,
  "frm_visible": true,
  "tbl_visible": true,
  "tbl_orderable": false
}

// Response
[]
```

DELETE:
```javascript
// Response
{ "delete": true }
```

### /objecttype/{objecttype}/object

| Method | Description |
|---|---|
| GET | Returns a list of objects |
| POST | Create new object |

* For saving an object (POST,PUT) only submit the property UUID as key with the desired value, as stated in the following examples.
* Optional: This method also allows to submit a list of related objects UUID's. This is always concidered to be the full list. When using this option existing relations that are not submitted in this key will be deleted. Individual relations can be managed by using [/objecttype/{objecttype}/object/{object}/relation](#objecttype-objecttype-object-object-relation).

GET:
```javascript
// Response
[
  {
    "id": "19dc6465-4668-4e8d-a3f5-9ae7576c4a39",
    "Name": "My Object",
    "2nd Property": 88
  },
  {..}
]
```

POST:
```javascript
// Payload
{
  "19dc6465-4668-4e8d-a3f5-9ae7576c4a39": "My Object",
  "e8dd76d7-ed02-449f-aadf-6977c6a0b277": "88",
  "relations" [
    "4d18a0ab-baf3-459c-ad46-4ce4309cdfe7",
    "882fc152-b732-4f0b-afab-973359ce82a6"
  ]
}

// Response
{ "id": "19dc6465-4668-4e8d-a3f5-9ae7576c4a39" }
```

### /objecttype/{objecttype}/object/{object}

| Method | Description |
|---|---|
| GET | Returns object list of details per value |
| PUT | Update object |
| DELETE | Delete object |

Object key/value explanation:

| Key | Description |
|---|---|
| id | UUID of the stored value |
| type | Object property type _([/objecttype/{objecttype}/property](#objecttype-objecttype-property))_ |
| label | Object property name |
| value | Stored value |

GET:
```javascript
// Response
[
  {
    "id": "19dc6465-4668-4e8d-a3f5-9ae7576c4a39",
    "type": 1,
    "label": "Name",
    "value": "My Object"
  },
  {
    "id": "e8dd76d7-ed02-449f-aadf-6977c6a0b277",
    "type": 2,
    "label": "2nd Property",
    "value": "88"
  }
]
```

PUT:
```javascript
// Payload
{
  "19dc6465-4668-4e8d-a3f5-9ae7576c4a39": "My Object",
  "e8dd76d7-ed02-449f-aadf-6977c6a0b277": "88",
  "relations" [
    "4d18a0ab-baf3-459c-ad46-4ce4309cdfe7",
    "882fc152-b732-4f0b-afab-973359ce82a6"
  ]
}

// Response
[]
```

### /objecttype/{objecttype}/object/{object}/relation

| Method | Description |
|---|---|
| GET | Returns a detailed list of related objects |
| POST | Assign an object |

GET:
```javascript
// Response
[
  {
    "id": "19dc6465-4668-4e8d-a3f5-9ae7576c4a39",
    "objtype": "5a774411-81fb-4929-87a5-5c4b41890aeb"
    "objtype_name": "My ObjectType",
    "Name": "My Object",
    "2nd Property": "88"
  },
  {..}
]
```

POST:
```javascript
// Payload
{ "id": "19dc6465-4668-4e8d-a3f5-9ae7576c4a39" }

// Response
{ "assign": true }
```

### /objecttype/{objecttype}/object/{object}/relation/available

| Method | Description |
|---|---|
| GET | Returns a detailed list of objects available to assign |

GET:
```javascript
// Response
[
  {
    "id": "19dc6465-4668-4e8d-a3f5-9ae7576c4a39",
    "objtype": "5a774411-81fb-4929-87a5-5c4b41890aeb"
    "objtype_name": "My ObjectType",
    "Name": "My Object",
    "2nd Property": "88"
  },
  {..}
]
```

### /objecttype/{objecttype}/object/{object}/relation/{relation}

| Method | Description |
|---|---|
| DELETE | Unassign related object |

GET:
```javascript
// Response
{ "delete": true }
```

### /objecttype/{objecttype}/object/{object}/log

| Method | Description |
|---|---|
| GET | Returns the object's change log (if enabled) |

Action type explanation:

| Action | Description |
|---|---|
| 1 | Object created |
| 2 | Updated values |
| 5 | Assign object |
| 6 | Unassign object |
| 10 | Object type related action |

GET:
```javascript
// Response
[
  {
    "timestamp": "2023-02-09 16:06",
    "username": "myuser",
    "action": 10,
    "details": "Logging enabled"
  },
  {..}
]
```