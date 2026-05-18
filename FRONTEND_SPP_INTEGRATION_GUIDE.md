# Frontend Integration Guide - SPP Billing Fix

## 🎯 What Changed for Frontend

### ✅ NEW ENDPOINT AVAILABLE

```
POST /api/spp/provision-bills
```

This endpoint allows admins to manually trigger bill provisioning without waiting for the automatic schedule.

---

## 📡 API Usage

### Endpoint Details
```
Method:   POST
Path:     /api/spp/provision-bills
Auth:     Sanctum Bearer token required
Content:  application/json
```

### Example 1: Provision All Active Santri
```javascript
// TypeScript/React Hook
const provisionBills = async () => {
  try {
    const response = await fetch('/api/spp/provision-bills', {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${token}`,
        'Content-Type': 'application/json',
      }
    });
    
    const data = await response.json();
    console.log(`Bills provisioned for ${data.data.santri_processed} santri`);
    
    // Show success toast
    toast.success(data.message);
    
    // Refresh dashboard or tagihan list
    refetch(); // if using React Query
  } catch (error) {
    toast.error('Failed to provision bills');
  }
};
```

### Example 2: Provision for Specific Santri
```javascript
const provisionForSantri = async (idSantri) => {
  const response = await fetch(
    `/api/spp/provision-bills?id_santri=${idSantri}`,
    {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${token}`,
      }
    }
  );
  
  const data = await response.json();
  console.log(data.message); // "Bills berhasil diprovision untuk santri."
};
```

### Example 3: Provision for Specific Unit
```javascript
const provisionForUnit = async (idUnit) => {
  const response = await fetch(
    `/api/spp/provision-bills?id_unit=${idUnit}`,
    {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${token}`,
      }
    }
  );
  
  const data = await response.json();
  console.log(`${data.data.santri_processed} santri processed`);
};
```

---

## 🔄 Response Format

### Success Response (200)
```json
{
  "message": "Bills berhasil diprovision untuk 45 santri aktif.",
  "data": {
    "santri_processed": 45,
    "total_active_santri": 50
  }
}
```

### Error Response (404) - Santri Not Found
```json
{
  "message": "Santri tidak ditemukan."
}
```

### Error Response (422) - Validation
```json
{
  "message": "Gunakan id_santri atau id_pendaftaran saat membuat tagihan SPP."
}
```

---

## 🎨 UI Integration Examples

### Option 1: "Refresh Bills" Button
```jsx
// In Pengaturan component
<Button 
  onClick={() => provisionBills()}
  disabled={isLoading}
>
  {isLoading ? 'Provisioning...' : 'Refresh Bills'}
</Button>
```

### Option 2: Auto-trigger After Setting Save
```jsx
const handleSaveSetting = async (setting) => {
  // Save the setting
  await saveSetting(setting);
  
  // If setting was activated, provision immediately
  if (setting.aktif) {
    await provisionBills();
    toast.success('Bills automatically provisioned!');
  }
};
```

### Option 3: Add to Dashboard
```jsx
// In Dashboard component
<Card title="SPP Provisioning">
  <p>Manually trigger bill generation for santri</p>
  <Button onClick={() => provisionBills()}>
    Provision Bills Now
  </Button>
</Card>
```

---

## 🔔 Expected Behavior After Fix

### Scenario 1: Create New Setting
```
1. Admin opens Pengaturan → Tambah Setting
2. Admin fills form with:
   - Unit/Kelas/Jenjang
   - Nominal/Jumlah
   - Aktif: ✓ checked
3. Admin clicks Simpan
4. EXPECTED: Bills created immediately (no refresh needed)
5. Admin goes to Dashboard → Detail Tagihan
6. EXPECTED: Bills now visible ✅
```

### Scenario 2: Dashboard Shows Bills
```
BEFORE: "Tidak ada data"
AFTER:  Lists bills for each santri
        └─ Jumlah Invoice: X
        └─ Total Tagihan: Rp XXX.XXX
        └─ Sudah Dibayar: Rp XXX.XXX
        └─ Belum Dibayar: Rp XXX.XXX
```

---

## 📋 What NOT Changed

❌ These endpoints work the same:
- `GET /api/spp/setting` - still returns settings
- `POST/PUT/DELETE /api/spp/setting/{id}` - still manages settings
- `GET /api/spp/pembayaran` - still lists bills
- `POST /api/administrasi/dashboard` - still shows dashboard stats

✅ Everything is **backward compatible**

---

## 🧪 Quick Test Checklist

- [ ] Create SPP setting with `aktif=true`
- [ ] Verify bills appear in Dashboard immediately
- [ ] Call `/api/spp/provision-bills` endpoint
- [ ] Verify response includes santri count
- [ ] Refresh Tagihan list and confirm bills are there
- [ ] Try provision endpoint twice - verify idempotency (no duplicates)

---

## 🐛 If Bills Still Don't Appear

1. **Check if setting is `aktif=true`**
   ```
   GET /api/spp/setting
   Look for: "aktif": true
   ```

2. **Try manual provision endpoint**
   ```
   POST /api/spp/provision-bills
   ```

3. **Check if santri is AKTIF status**
   ```
   GET /api/data-santri?status=AKTIF
   ```

4. **Check logs**
   ```
   storage/logs/laravel.log
   Look for: "Provisioning SPP for santri"
   ```

---

## 💡 Pro Tips

1. **No UI refresh needed** - Observer triggers instantly
2. **Can call endpoint multiple times** - idempotent (won't create duplicates)
3. **Auto-runs daily at 01:00 AM** - scheduled job as safety net
4. **Better logging** - check laravel.log if debugging

---

## 📞 Questions?

If bills still not showing after:
- Creating/updating setting to aktif=true
- Calling provision endpoint
- Refreshing page

Check:
1. Network tab - verify POST request succeeded
2. laravel.log - look for errors
3. Database - verify spp_setting.aktif = 1
4. Database - verify pembayaran_spp records exist for santri

---

**Last Updated**: May 2026
**Status**: Production Ready ✅
