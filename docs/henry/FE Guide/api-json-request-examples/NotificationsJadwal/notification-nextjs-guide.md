# Next.js Guide — Notifications (Jadwal)

This guide shows a minimal and practical approach to integrate the backend notification endpoints into a Next.js frontend: fetching notifications, marking one as read, and marking all as read.

**Prerequisites**
- API endpoints available: `GET /api/notifications`, `PATCH /api/notifications/{id}/mark-read`, `POST /api/notifications/read-all` (use Sanctum token or Bearer token).
- HTTP client (fetch or axios). This guide uses `fetch` but includes notes for `axios` + SWR.

**Auth**
- Use whatever auth flow your app uses (Bearer token in `Authorization` header, or cookie-based auth for Sanctum). Ensure requests include the token:

```js
const headers = { 'Accept': 'application/json', 'Authorization': `Bearer ${token}` };
```

**API helpers**
Create a small helpers file `lib/api/notifications.js`:

```js
const API_ROOT = process.env.NEXT_PUBLIC_API_BASE || '';

export async function fetchNotifications(token, { per_page = 15, page = 1, only_unread = false } = {}) {
  const url = new URL(`${API_ROOT}/api/notifications`);
  url.searchParams.set('per_page', per_page);
  url.searchParams.set('page', page);
  if (only_unread) url.searchParams.set('only_unread', 'true');

  const res = await fetch(url.toString(), { headers: { 'Accept': 'application/json', 'Authorization': `Bearer ${token}` } });
  if (!res.ok) throw new Error('Failed to fetch notifications');
  return res.json();
}

export async function markNotificationRead(token, id) {
  const res = await fetch(`${API_ROOT}/api/notifications/${id}/mark-read`, {
    method: 'PATCH',
    headers: { 'Accept': 'application/json', 'Authorization': `Bearer ${token}` }
  });
  if (!res.ok) throw new Error('Failed to mark read');
  return res.json();
}

export async function markAllRead(token) {
  const res = await fetch(`${API_ROOT}/api/notifications/read-all`, {
    method: 'POST',
    headers: { 'Accept': 'application/json', 'Authorization': `Bearer ${token}` }
  });
  if (!res.ok) throw new Error('Failed to mark all read');
  return res.json();
}
```

**React component example (hooks + simple polling)**
File: `components/NotificationsList.js`

```jsx
import { useEffect, useState, useCallback } from 'react';
import { fetchNotifications, markNotificationRead, markAllRead } from '../lib/api/notifications';

export default function NotificationsList({ token }) {
  const [data, setData] = useState({ data: [], meta: {} });
  const [loading, setLoading] = useState(false);

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const json = await fetchNotifications(token, { per_page: 20, only_unread: false });
      setData(json);
    } finally { setLoading(false); }
  }, [token]);

  useEffect(() => { load(); const id = setInterval(load, 30_000); return () => clearInterval(id); }, [load]);

  const handleMarkRead = async (id) => {
    // optimistic update
    setData(prev => ({
      ...prev,
      data: prev.data.map(n => n.id === id ? { ...n, read_at: (new Date()).toISOString() } : n)
    }));
    try { await markNotificationRead(token, id); } catch (e) { load(); }
  };

  const handleMarkAll = async () => {
    try {
      await markAllRead(token);
      // reflect locally
      setData(prev => ({ ...prev, data: prev.data.map(n => ({ ...n, read_at: (new Date()).toISOString() })) }));
    } catch (e) { load(); }
  };

  return (
    <div>
      <div style={{ display:'flex', justifyContent:'space-between', alignItems:'center' }}>
        <h3>Notifications</h3>
        <button onClick={handleMarkAll} disabled={loading}>Mark all read</button>
      </div>
      {loading && <div>Loading…</div>}
      <ul>
        {data.data.map(n => (
          <li key={n.id} style={{ opacity: n.read_at ? 0.6 : 1 }}>
            <div>{n.data?.pesan}</div>
            <small>{n.data?.nama_mapel} • {n.created_at}</small>
            {!n.read_at && <button onClick={() => handleMarkRead(n.id)}>Mark read</button>}
          </li>
        ))}
      </ul>
    </div>
  );
}
```

**With SWR (recommended)**
- Use `swr` for caching and revalidation. Wrap `fetchNotifications` in an async fetcher and call `useSWR(['/api/notifications', token], fetcher)`.
- Use `mutate()` for optimistic updates after marking read.

**Real-time (optional)**
- For push notifications, integrate Pusher/Redis/Laravel Echo on backend and Echo client in Next.js to receive events and call `mutate()` to refresh the list.

**Testing locally (quick)**
1. Ensure `notifications` table exists (backend migrations done).
2. Log in as a santri and obtain token.
3. Run Next.js dev server: `pnpm dev` or `npm run dev`.
4. Open the page with `NotificationsList` and trigger a jadwal change (via admin UI or `php artisan tinker`) to confirm a new item arrives on next poll or after refresh.

**Troubleshooting**
- 401/403: token missing or invalid — double-check auth header or cookie setup.
- No notifications: confirm `JadwalPembelajaranObserver` finds matching `DataAkunSantri` rows for the changed jadwal's `kode_kelas`.
- Slow display: shorten polling interval or enable push updates.

---

If you want, I can also:
- generate the `lib/api/notifications.js` and `components/NotificationsList.js` files in the repo, or
- add an example page under `pages/notifications.js` that uses the component.
Which would you like next?