# Notification — Mark All Read (notification.readAll)

POST /api/notifications/read-all

Authentication: Bearer token (Sanctum)

Body: none

Example request (curl):

```bash
curl -X POST "http://localhost:8000/api/notifications/read-all" \
  -H "Authorization: Bearer <SANTRI_TOKEN>" \
  -H "Accept: application/json"
```

Example response (200):

```json
{
  "success": true,
  "marked_count": 5
}
```