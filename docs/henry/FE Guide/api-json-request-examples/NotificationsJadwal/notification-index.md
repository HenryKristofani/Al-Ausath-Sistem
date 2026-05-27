# Notification — List (notification.index)

GET /api/notifications

Authentication: Bearer token (Sanctum)

Query parameters:
- `per_page` (integer) — optional, default 15
- `page` (integer) — optional
- `only_unread` (boolean) — optional, when true returns only unread notifications

Example request (curl):

```bash
curl -X GET "http://localhost:8000/api/notifications?per_page=10&only_unread=true" \
  -H "Authorization: Bearer <SANTRI_TOKEN>" \
  -H "Accept: application/json"
```

Example response (200):

```json
{
  "data": [
    {
      "id": "b3f9c1a6-...",
      "type": "App\\Notifications\\JadwalBerubah",
      "notifiable_type": "App\\Models\\DataAkunSantri",
      "notifiable_id": 123,
      "data": {
        "pesan": "Jadwal pembelajaran berubah",
        "jadwal_id": 42,
        "nama_mapel": "Matematika",
        "hari": "Senin",
        "jam_mulai": "08:00",
        "jam_selesai": "09:30",
        "ruangan": "R101",
        "perubahan": ["ruangan"]
      },
      "read_at": null,
      "created_at": "2026-05-25T07:12:34Z"
    }
  ],
  "meta": {
    "current_page": 1,
    "per_page": 10,
    "total": 1
  }
}
```