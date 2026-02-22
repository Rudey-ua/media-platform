# API Response Conventions

Applies to API JSON responses (`/api/*` or requests with `expectsJson()`).

## Contract

Response is discriminated by `type`.

### `type = data`

Used for successful responses with payload.

```json
{
  "type": "data",
  "data": {}
}
```

### `type = info`

Used for successful responses without payload.

```json
{
  "type": "info",
  "message": "Successfully logged out"
}
```

### `type = validation_error`

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

Rules:
- `message` = first non-empty validation message.
- Fallback `message` = `Validation failed`.
- `details` is a list of `{ field, messages[] }`.

### `type = domain_error`

Used for business/domain conflicts (`DomainException`).

```json
{
  "type": "domain_error",
  "message": "Incorrect code, please try again"
}
```

### `type = error`

Used for generic/auth/http/server errors.

```json
{
  "type": "error",
  "message": "Invalid credentials"
}
```

## Status Mapping

- `data`: usually `200`, `201`.
- `info`: usually `200`.
- `validation_error`: always `422`.
- `domain_error`: currently `409`.
- `error`: typically `401`, `403`, `404`, `405`, `5xx`.

## Exception Rendering

Defined in `bootstrap/app.php`:
- `ApiException` -> `type=error`, status from exception.
- `DomainException` -> `type=domain_error`, status `409`.
- `ValidationException` -> `type=validation_error`, status `422`.
- `AuthenticationException` -> `type=error`, status `401`.
- `AuthorizationException` -> `type=error`, status `403`.
- `HttpExceptionInterface` -> `type=error`, keeps HTTP status.
- fallback `Throwable` -> `type=error`, status `500`, message `Internal server error`.
