# API Reference - /valuemap

/valuemap provides access to value maps.

* [/valuemap](#valuemap)
* [/valuemap/{valuemap}](#valuemapvaluemap)
* [/valuemap/{valuemap}/value](#valuemapvaluemapvalue)
* [/valuemap/{valuemap}/value/{value}](#valuemapvaluemapvaluevalue)

### /valuemap

| Method | Description |
|---|---|
| GET | Returns a list of value maps |
| POST | Create new value map |

* By default the values configured in a value map are ordered by name. When _prio_ is set to _true_ the values are returned in the order as they are configured.
* Optional: This method allows to submit a list of all the values by adding them under key "_value_" as stated in the following example. This is always concidered to be the full list. When using this option existing values that are not submitted in this key will be deleted. Individual vlues can be managed by using [/valuemap/{valuemap}/value/{value}](#valuemap-valuemap-value-value-).
* For new values PUT/POST them under _value_ with id _null_.
* As stated before, when _prio_ is set to _true_ the order of the values under _value_ determines in which order the values are returned. So for reordering just submit the values in the order as desired.

GET:
```javascript
// Response
[
  {
    "id": "e471fda2-5c33-4f2d-a899-8ea1e0a79c45",
    "name": "My ValueMap"
  },
  {..}
]
```

POST:
```javascript
// Payload
{
  "name": "My ValueMap",
  "prio": true,
  "value": [
    {
      "id": null,
      "name": "My first value"
    },
    {..}
  ]
}

// Response
{ "id": "e471fda2-5c33-4f2d-a899-8ea1e0a79c45" }
```

### /valuemap/{valuemap}

| Method | Description |
|---|---|
| GET | Returns the value map details |
| PUT | Update value map |
| DELETE | Delete value map |

By default the values configured in a value map are ordered by name. When setting _prio_ to _true_ the values are returned in the order as they are configured.

GET:
```javascript
// Response
{
  "name": "My ValueMap",
  "prio": true
}
```

PUT:
```javascript
// Payload
{
  "name": "My ValueMap",
  "prio": true,
  "value": [
    {
      "id": null,
      "name": "My new first value"
    },
    {
      "id": "81d6396e-0b55-4b92-a44e-b92dea7a8c58",
      "name": "My first value"
    },
    {..}
  ]
}

// Response
[]
```

DELETE:
```javascript
// Response
{ "delete": true }
```

### /valuemap/{valuemap}/value

| Method | Description |
|---|---|
| GET | Returns a list of values |
| POST | Create new value |

GET:
```javascript
// Response
[
  {
    "id": "81d6396e-0b55-4b92-a44e-b92dea7a8c58",
    "name": "My first value"
  },
  {..}
]
```

POST:
```javascript
// Payload
{ "name": "My first value" }

// Response
{ "id": "81d6396e-0b55-4b92-a44e-b92dea7a8c58" }
```

### /valuemap/{valuemap}/value/{value}

| Method | Description |
|---|---|
| GET | Returns the value |
| PUT | Update value |
| DELETE | Delete value |

GET:
```javascript
// Response
{ "name": "My Value" }
```

PUT:
```javascript
// Payload
{ "name": "My Value" }

// Response
[]
```

DELETE:
```javascript
// Response
{ "delete": true }
```
