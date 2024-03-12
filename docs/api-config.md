# API Reference - /config

/config provides configuration of settings and the navigation tree

* [/config](#config)

### /config

| Method | Description |
|---|---|
| GET | Returns the configuration based on the authorized state |
| PUT | Update configuration (available for admin only) |

Settings are read by name. Saving is only allowed for a predefined list of names. It is highly recommended to set configuration by the WebUI.

GET:
```javascript
// Response
{
  "navigation": [
    {
      "id": "60b9ce5f-544c-4a88-9d3d-858066af286d",
      "parent": null,
      "name": "Company"
    },
    {..}
  ],
  "settings": [
    {
      "id": "cf6cac2f-b74b-49bd-aed0-82a6a38ddd24",
      "name": "title",
      "value": "MyCompany"
    },
    {..}
  ]
}
```

PUT:
```javascript
// Payload
{
  "navigation": [
    {
      "id": "60b9ce5f-544c-4a88-9d3d-858066af286d",
      "parent": null,
      "name": "Company"
    },
    {
      "id": null,
      "parent": "60b9ce5f-544c-4a88-9d3d-858066af286d",
      "name": "NewSubMap"
    },
    {..}
  ],
  "settings": [
    {
      "id": "cf6cac2f-b74b-49bd-aed0-82a6a38ddd24",
      "name": "title",
      "value": "MyCompany"
    },
    {..}
  ]
}
```