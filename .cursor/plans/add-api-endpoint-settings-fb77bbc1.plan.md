<!-- fb77bbc1-95bd-4c26-a80f-4e99e364cbc2 d2fca6fd-d39f-4fbe-aa24-a6159e6c24a5 -->
# Add New API Endpoint Settings

## Overview

Add 6 new API endpoint configuration fields (`api_endpoint_students`, `api_endpoint_settings`, `api_endpoint_settings_api`, `api_endpoint_student_api`, `api_endpoint_admin_attendance`, `api_endpoint_sync`) to the system settings infrastructure.

## Implementation Steps

### 1. Add Database Records

**File:** Create SQL migration file `database/add_api_endpoints.sql`

Insert 6 new rows into `system_settings` table:

- `api_endpoint_students` - `/api/students_sync.php`
- `api_endpoint_settings` - `/api/settings_sync.php`
- `api_endpoint_settings_api` - `/api/settings_api.php`
- `api_endpoint_student_api` - `/api/student_api_simple.php`
- `api_endpoint_admin_attendance` - `/admin/api/attendance.php`
- `api_endpoint_sync` - `/api/sync_api.php`

All will be:

- Type: `string`
- Category: `integration`
- Validation: `[]` (empty JSON array)

### 2. Update Admin Settings UI

**File:** `public/admin/settings.php`

Add 6 new form fields in the "Website Integration" card (lines 294-331):

- After `api_endpoint_dashboard` field (line 322)
- Before `api_timeout_seconds` field (line 324)

Each field will have:

- Label with descriptive text
- Input type="text" with appropriate ID
- Form text helper describing the endpoint

### 3. Update Settings JavaScript

**File:** `public/admin/assets/js/settings.js`

The existing `populateSettingsForm()` function (around line 94) already handles dynamic field population based on setting keys matching input IDs, so no changes needed. The `collectSettingsData()` function will automatically collect the new fields.

### 4. Update Python Settings Defaults

**File:** `python/settings.py`

The default settings dict (lines 67-75) already has these fields defined from the previous task, so they just need to match the database values.

## Files to Modify

1. **Create:** `database/add_api_endpoints.sql` - SQL insert statements
2. **Modify:** `public/admin/settings.php` - Add 6 input fields in integration tab
3. **No Change:** `public/admin/assets/js/settings.js` - Already handles dynamic fields
4. **No Change:** `public/api/settings_api.php` - Already handles any setting key dynamically
5. **Already Done:** `python/settings.py` - Defaults already added

## Expected Result

After implementation:

- 6 new API endpoint fields visible in admin settings page under Integration tab
- Fields save/load correctly through existing settings API
- Python application can read these settings from settings.json
- Database contains the 6 new setting records

### To-dos

- [ ] Create SQL migration file with INSERT statements for 6 new API endpoint settings
- [ ] Add 6 new input fields to settings.php in the Integration tab
- [ ] Verify settings sync between database and Python settings.json