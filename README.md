# Data API plugin

An API plugin for exposing data to external applications.

## API Key

To use the plugin you need an API key for leantime.

See <https://docs.leantime.io/api/usage?id=connect>.

The API should be set as a header for all requests to the API.

E.g.

```shell
curl https://{{YOURDOMAIN}}/apidata/api/tickets
   -H "x-api-key: {{YOURAPIKEY}}"
   -H "Content-Type: application/json"
```
