# API Reference - /auth

/auth provides authentication, and security management for Administrators.

* [Tokens](#tokens)
* [/auth](#auth)
* [/auth/user](#authuser)
* [/auth/user/{user}](#authuseruser)
* [/auth/user/{user}/group](#authuserusergroup)
* [/auth/user/{user}/group/{group}](#authuserusergroupgroup)
* [/auth/user/{user}/token](#authuserusertoken)
* [/auth/user/{user}/token/{token}](#authuserusertokentoken)
* [/auth/group](#authgroup)
* [/auth/group/{group}](#authgroupgroup)

### Tokens

For authentication using an API token use the _X-API-Key_ header.

| HTTP Request Header |
|---|
| _X-API-Key: [token]_ |

```javascript
curl -X POST -H 'X-API-Key: nLO0v3OxbsRVyq6w58CyU8NZp1' 'https://myserver.fqdn/php.api/v2/path/to/example/000001'
```

### /auth

| Method | Description |
|---|---|
| GET | Returns authorization state |
| POST | Login with username and password |
| PUT | Update authorization / keepalive |
| DELETE | Logout |

GET:
```javascript
// Response
{ "active": true }
```

POST:
```javascript
// Payload
{
  "username": "myusername",
  "password": "myPa%%w0rD"
}

// Response
{ "active": true }
```

PUT:
```javascript
// Payload
[]

// Response
{ "active": true }
```

DELETE:
```javascript
// Response
{ "active": false }
```

### /auth/user

_Only allowed by SuperAdmin_

| Method | Description |
|---|---|
| GET | List users |
| POST | Create new user |

GET:
```javascript
// Response
[
  {
    "id": "8de44456-a869-46bb-8387-17ac2e1cda38",
    "username": "admin",
    "firstname": null,
    "lastname": null,
    "tokens": false,
    "sa": true
  },
  {..}
]
```

POST:
```javascript
// Payload
{
  "username": "myusername",
  "password": "myPa%%w0rD",
  "firstname": "John",
  "lastname": "Doe",
  "tokens": true,
  "sa": false
}

// Response
{ "id": "b01ac1e5-ce38-4189-99ac-b965013d9e24" }
```

### /auth/user/{user}

Since the user id remains hidden for the user itself, actions for the active user can be accessed by requesting **self** instead of the id (so: _**/auth/user/self**_). This can be used to e.g. gather the display name using GET, or resetting the password using POST.

_/auth/user/self is allowed for all users_

_Others only allowed by SuperAdmin_

| Method | Description |
|---|---|
| GET | Get user info |
| PUT | Update user info |
| DELETE | Delete user |

GET:
```javascript
// Response
{
  "username": "admin",
  "firstname": null,
  "lastname": null,
  "tokens": false,
  "sa": true
}
```

PUT:
```javascript
// Payload
{
  "username": "myusername",
  "password": "myPa%%w0rD",
  "firstname": "John",
  "lastname": "Doe",
  "tokens": true,
  "sa": false
}

// Response
[]
```

DELETE:
```javascript
// Response
{ "delete": true }
```

### /auth/user/{user}/group

_Only allowed by SuperAdmin_

| Method | Description |
|---|---|
| GET | List user group membership |
| POST | Add user group membership |

GET:
```javascript
// Response
[
  {
    "id": "ab8bb53c-5cd1-43be-a0a0-7f41f9480bfa",
    "groupname": "Testgroup",
    "ldapcn": "cn=testgroup,cn=groups,cn=accounts,dc=example,dc=local",
    "radiusattr": "testgroup"
  },
  {..}
]
```

POST:
```javascript
// Payload
{
  "groupname": "Testgroup",
  "ldapcn": "cn=testgroup,cn=groups,cn=accounts,dc=example,dc=local",
  "radiusattr": "testgroup"
}

// Response
{ "id": "ab8bb53c-5cd1-43be-a0a0-7f41f9480bfa" }
```

### /auth/user/{user}/group/{group}

_Only allowed by SuperAdmin_

| Method | Description |
|---|---|
| GET | Get group info |
| DELETE | Remove group membership |

GET:
```javascript
// Response
{
  "groupname": "Testgroup",
  "ldapcn": "cn=testgroup,cn=groups,cn=accounts,dc=example,dc=local",
  "radiusattr": "testgroup"
}
```

DELETE:
```javascript
// Response
{ "delete": true }
```

### /auth/user/{user}/token

_Only allowed by Self and SuperAdmin_

| Method | Description |
|---|---|
| GET | List API tokens |
| POST | Create API token |

GET:
```javascript
// Response
[
  {
    "id": "efd34367-5aa6-49e6-bd99-fb6c5543ca4c",
    "name": "myTokenName",
    "expiry": "2024-01-01 12:00"
  },
  {..}
]
```

POST:
```javascript
// Payload
{
  "name": "myTokenName",
  "expiry": "2024-01-01 12:00"
}

// Response
{ "token": "nLO0v3OxbsRVyq6w58CyU8NZp1" }
```

### /auth/user/{user}/token/{token}

_Only allowed by Self and SuperAdmin_

| Method | Description |
|---|---|
| GET | Get API token info |
| PUT | Update API token |
| DELETE | Delete API token |

GET:
```javascript
// Response
{
  "name": "myTokenName",
  "expiry": "2999-12-31 23:59"
}
```

PUT:
```javascript
// Payload
{
  "name": "myTokenName",
  "expiry": "2999-12-31 23:59"
}

// Response
[]
```

DELETE:
```javascript
// Response
{ "delete": true }
```

### /auth/group

_Only allowed by SuperAdmin_

| Method | Description |
|---|---|
| GET | List group |
| POST | Create new group |

GET:
```javascript
// Response
[
    {
    "id": "ab8bb53c-5cd1-43be-a0a0-7f41f9480bfa",
    "groupname": "Testgroup",
    "ldapcn": "cn=testgroup,cn=groups,cn=accounts,dc=example,dc=local",
    "radiusattr": "testgroup"
  },
  {..}
]
```

POST:
```javascript
// Payload
{
  "groupname": "Testgroup",
  "ldapcn": "cn=testgroup,cn=groups,cn=accounts,dc=example,dc=local",
  "radiusattr": "testgroup"
}

// Response
{ "id": "ab8bb53c-5cd1-43be-a0a0-7f41f9480bfa" }
```

### /auth/group/{group}

_Only allowed by SuperAdmin_

| Method | Description |
|---|---|
| GET | Get group info |
| PUT | Update group info |
| DELETE | Delete group |

GET:
```javascript
// Response
{
  "groupname": "Testgroup",
  "ldapcn": "cn=testgroup,cn=groups,cn=accounts,dc=example,dc=local",
  "radiusattr": "testgroup"
}
```

PUT:
```javascript
// Payload
{
  "groupname": "Testgroup",
  "ldapcn": "cn=testgroup,cn=groups,cn=accounts,dc=example,dc=local",
  "radiusattr": "testgroup"
}

// Response
[]
```

DELETE:
```javascript
// Response
{ "delete": true }
```
