# API Response Contract

All API responses use a custom envelope with a top-level `type` field.

## 1. Data Response

Used for successful responses that return payload data.

```json
{
  "type": "data",
  "data": {}
}
```

## 2. Info Response

Used for successful responses with a message and no payload.

```json
{
  "type": "info",
  "message": "Successfully logged out"
}
```

## 3. Error Response

Used for generic errors.

```json
{
  "type": "error",
  "message": "Invalid credentials"
}
```

## 4. Validation Error Response

Used for validation failures.

```json
{
  "type": "validation_error",
  "message": "Email is taken",
  "details": [
    {
      "field": "email",
      "messages": [
        "Email is taken"
      ]
    }
  ]
}
```

`details` is an array of objects:

- `field`: string
- `messages`: string[]

## 5. Domain Error Response

Used for business-rule violations.

```json
{
  "type": "domain_error",
  "message": "Business rule violated"
}
```

## Web Routes

Current protected web pages:

- `/` - video player
- `/video-upload` - upload page

Authentication page:

- `/login`

Guests are redirected to `/login` when opening protected pages.
