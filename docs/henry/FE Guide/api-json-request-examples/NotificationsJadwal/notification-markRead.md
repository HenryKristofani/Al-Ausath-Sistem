# Notification — Mark Read (notification.markRead)

PATCH /api/notifications/{id}/mark-read

Authentication: Bearer token (Sanctum)

Path parameter:
- `id` — notification id (string)

Body: none

Example request (curl):

```bash
curl -X PATCH "http://localhost:8000/api/notifications/b3f9c1a6-.../mark-read" \
  -H "Authorization: Bearer <SANTRI_TOKEN>" \
  -H "Accept: application/json"
```

Example response (200):

```json
{
  "success": true,
  "notification": {
    "id": "b3f9c1a6-...",
    "read_at": "2026-05-25T07:15:00Z"
  }
}
```