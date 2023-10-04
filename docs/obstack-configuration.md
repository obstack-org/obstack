# Configuration

* [Base configuration](#base-authentication)
* [Configuring external authentication](#configuring-external-authentication)

### Base configuration
* Database and external authentication is set in _config.php_.
* The application display name can be changed in _js/obstack.js_
* The CSS color scheme can be adjusted in _css/index.css_ under _:root_

#### config.php

Database:
```php
$db_connectionstring = 'pgsql:host=127.0.0.1;dbname=obstack;user=obstack;password=obstack';
```

Authentication options:
```php
$sessman_config = [

  'timeout'  => 600,

  'ldap'    => [
    'enabled'       => false,
    'host'          => '127.0.0.1',
    'port'          => 389,
    'userdn'        => 'cn=users,cn=accounts,dc=example,dc=local',
    'group-auth'    => 'cn=obstack-auth,cn=groups,cn=accounts,dc=example,dc=local',
    'group-sa'      => 'cn=obstack-sa,cn=groups,cn=accounts,dc=example,dc=local'
  ],

  'radius'  => [
    'enabled'       => false,
    'host'          => '127.0.0.1',
    'port'          => 1812,
    'secret'        => 'testing123',
    'attr'          => 230,
    'group-auth'    => 'auth',
    'group-sa'      => 'sa'
  ],

];
```

For more details on LDAP/Radius go to: [Configuring external authentication](#configuring-external-authentication)

#### js/obstack.js

Display name:
```javascript
const title = 'ObStack';
```

#### css/index.css

Color scheme
```css
:root {
  --titlebar-height: 60px;
  --titlebar-color: #ddeeee;
  --titlebar-background: #1b5f98;
  --sidebar-width: 180px;
  --sidebar-color: #bfcad0;
  --sidebar-background: #1c1c1c;
  --content-color: #111111;
  --content-background: #ffffee;
}
```

### Configuring external authentication

When implementing LDAP and/or Radius authentication please note:

* Authentication will commence in the following order: LDAP, Radius, Database
* When authentication fails on a step, it will try authenticating with the same parameters on the next step. Authentication will fail if all methods have failed.
* Access is allowed when the LDAP/Radius user is member of the configured group or atttribute as configured in _group-auth_.
* Likewise, SuperAdmin permissions require membership of the group configured in _group-sa_.
* User tokens are primarily designed for usage by dedicated API users. Tokens for LDAP/Radius users is not yet supported.
* Radius support requires package _php-pecl-radius_

Groups can have an _ldapcn_ or _radiusattr_ value configured. Users authenticated by LDAP or Radius will automatically inherit a group's permissions when exactly matching these configurations.
* LDAP: Matching the full DN, e.g.: _cn=mygroup-auth,cn=groups,cn=accounts,dc=example,dc=local_
* Radius: Matching name in the attribute's string

![Group configuration](../img/os-cf1.png)

And last, but not least:
* LDAP support has been developed and tested _only_ with freeIPA
* Radius support has been developed and tested _only_ with freeradius

#### Radius attribute configuration

While Radius is not meant for this kind of application it may still be nice to have. Since there is no such thing as groups in Radius, matching groups is still supported by adding a comma separated list of group names/tags as a Radius attribute. A small description:

Mase sure the configured attribute id matches the entry in your freeradius dictonary

config.php:

```php
$config_radius = [
  ...
  'attr'          => 230,
  'group-auth'    => 'auth',
  'group-sa'      => 'sa'
],
```

FreeRADIUS:

_/etc/raddb/dictionary_

```javascript
ATTRIBUTE    MyAppGroups    230    string
```

_/etc/raddb/sites-enabled/default_ > _post-auth_

```javascript
update reply {
  MyAppGroup = "auth, sa, Custom Group 1"
}
```