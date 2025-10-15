import pandas as pd
from datetime import datetime, timedelta
import os
import sys
import json
import requests
import time
import threading
from urllib.parse import urljoin
import urllib3
from sync_manager import SyncManager
import pytz
from roll_parser import parse_roll_number, get_shift, get_program, get_academic_year_info
from time_validator import TimeValidator, validate_checkin_time
from student_sync import student_sync

# Disable SSL warnings
urllib3.disable_warnings(urllib3.exceptions.InsecureRequestWarning)

# Import settings manager
from settings import SettingsManager

# Initialize settings manager
settings = SettingsManager()

# Configuration - Load from settings with fallbacks
CSV_FILE = "attendance.csv"
STUDENTS_FILE = "students.json"
OFFLINE_FILE = "offline_data.json"
HEADERS = ["ID", "Name", "Timestamp", "Status"]

# Timezone Configuration
TIMEZONE = pytz.timezone(settings.get('timezone', 'Asia/Karachi'))

# Website Configuration - Load from settings
WEBSITE_URL = settings.get('website_url', 'http://localhost/qr_attendance/public')
API_ENDPOINT = settings.get('api_endpoint_attendance', '/api/api_attendance.php')
API_KEY = settings.get('api_key', 'attendance_2025_xyz789_secure')
SYNC_INTERVAL = settings.get('sync_interval_seconds', 30)  # Sync interval from settings
ADMIN_ATTENDANCE_API_URL = settings.get('admin_attendance_api_url', f"{WEBSITE_URL}/api/api_attendance.php")
ADMIN_API_KEY = settings.get('admin_api_key', '')
ATT_PULL_LOOKBACK_DAYS = settings.get('attendance_pull_lookback_days', 7)
ATT_PULL_PAGE_SIZE = settings.get('attendance_pull_page_size', 1000)

def get_current_time():
    """Get current time in Asia/Karachi timezone."""
    return datetime.now(TIMEZONE)

def format_time(dt=None):
    """Format datetime in Asia/Karachi timezone with 12-hour format."""
    if dt is None:
        dt = get_current_time()
    return dt.strftime("%Y-%m-%d %I:%M:%S %p")

def initialize_students():
    """Initialize student database with sample data."""
    if not os.path.exists(STUDENTS_FILE):
        students = {
            "24-SWT-01": {"name": "John Doe"},
            "24-SWT-02": {"name": "Jane Smith"},
            "24-SWT-03": {"name": "Mike Johnson"},
            "24-SWT-04": {"name": "Sarah Wilson"},
            "24-SWT-05": {"name": "David Brown"}
        }
        with open(STUDENTS_FILE, 'w') as f:
            json.dump(students, f, indent=2)
        print(f"Created student database: {STUDENTS_FILE}")
    else:
        print(f"Using existing student database: {STUDENTS_FILE}")

def initialize_csv():
    """Create CSV file with headers if it doesn't exist."""
    if not os.path.exists(CSV_FILE):
        df = pd.DataFrame(columns=HEADERS)
        df.to_csv(CSV_FILE, index=False)
        print(f"Created new attendance file: {CSV_FILE}")
    else:
        print(f"Using existing attendance file: {CSV_FILE}")

def load_students():
    """Load student data from JSON file."""
    try:
        with open(STUDENTS_FILE, 'r') as f:
            data = json.load(f)
        
        # Handle nested structure from student_sync
        if 'students' in data:
            return data['students']
        else:
            # Handle direct structure (fallback)
            return data
    except Exception as e:
        print(f"Error loading students: {e}")
        return {}

def get_student_status_for_date(student_id, date=None):
    """Get student's check-in/check-out status for a specific date."""
    if date is None:
        date = get_current_time().strftime("%Y-%m-%d")
    
    try:
        # Load attendance records
        if not os.path.exists(CSV_FILE):
            return {'status': 'Not checked in', 'check_in_time': None, 'check_out_time': None}
        
        df = pd.read_csv(CSV_FILE, names=['ID', 'Name', 'Timestamp', 'Status', 'Shift', 'Program', 'Current_Year', 'Admission_Year'])
        
        # Filter records for this student and date
        student_records = df[
            (df['ID'] == student_id) & 
            (df['Timestamp'].str.startswith(date))
        ].sort_values('Timestamp')
        
        if student_records.empty:
            return {'status': 'Not checked in', 'check_in_time': None, 'check_out_time': None}
        
        # Find latest check-in and check-out
        check_in_time = None
        check_out_time = None
        
        for _, record in student_records.iterrows():
            if record['Status'] == 'Check-in' and check_in_time is None:
                check_in_time = record['Timestamp']
            elif record['Status'] == 'Check-in' and check_in_time is not None:
                # Multiple check-ins detected
                return {
                    'status': 'Multiple check-ins detected',
                    'check_in_time': check_in_time,
                    'check_out_time': None,
                    'error': 'Already checked in'
                }
            elif record['Status'] == 'Check-out' and check_in_time is not None:
                check_out_time = record['Timestamp']
            elif record['Status'] == 'Check-out' and check_in_time is None:
                # Check-out without check-in
                return {
                    'status': 'Invalid sequence',
                    'check_in_time': None,
                    'check_out_time': record['Timestamp'],
                    'error': 'Check-out without check-in'
                }
        
        # Determine current status
        if check_in_time and check_out_time:
            return {'status': 'Checked out', 'check_in_time': check_in_time, 'check_out_time': check_out_time}
        elif check_in_time:
            return {'status': 'Checked in', 'check_in_time': check_in_time, 'check_out_time': None}
        else:
            return {'status': 'Not checked in', 'check_in_time': None, 'check_out_time': None}
            
    except Exception as e:
        print(f"Error checking student status: {e}")
        return {'status': 'Error', 'check_in_time': None, 'check_out_time': None, 'error': str(e)}

def check_internet_connection():
    """Check if internet connection is available."""
    try:
        # Try to connect to a reliable server
        response = requests.get("https://www.google.com", timeout=3, verify=False)
        return response.status_code == 200
    except:
        return False

def check_website_connection():
    """Check if the website is accessible."""
    try:
        # Try the main website first
        response = requests.get(WEBSITE_URL, timeout=10, verify=False)
        if response.status_code == 200:
            return True
        
        # Try the API endpoint directly
        api_url = urljoin(WEBSITE_URL, API_ENDPOINT)
        response = requests.get(api_url, timeout=10, verify=False)
        return response.status_code in [200, 404, 405]  # 404/405 means server is running but endpoint might not exist
    except Exception as e:
        # Don't print error messages during normal operation
        return False

def check_admin_panel_connection():
    """Check if admin panel APIs are accessible."""
    try:
        # Check settings API with authentication
        settings_url = f"{WEBSITE_URL}/api/settings_api.php"
        headers = {
            'X-API-Key': API_KEY,
            'Content-Type': 'application/json'
        }
        
        response = requests.get(f"{settings_url}?action=get_all", headers=headers, timeout=10, verify=False)
        
        if response.status_code == 200:
            data = response.json()
            return data.get('success', False)
        
        return False
        
    except Exception as e:
        print(f"Admin panel connection check failed: {e}")
        return False

def is_offline_mode():
    """Check if system should run in offline mode."""
    return not check_website_connection()

def save_offline_data(attendance_data):
    """Save attendance data to offline storage."""
    try:
        # Load existing offline data
        if os.path.exists(OFFLINE_FILE):
            with open(OFFLINE_FILE, 'r') as f:
                offline_data = json.load(f)
        else:
            offline_data = []
        
        # Add new data
        offline_data.append(attendance_data)
        
        # Save back to file
        with open(OFFLINE_FILE, 'w') as f:
            json.dump(offline_data, f, indent=2)
        
        print(f"Data saved offline for sync later")
    except Exception as e:
        print(f"Error saving offline data: {e}")

def sync_to_website():
    """Sync offline data to website when connection is available."""
    if not check_internet_connection():
        return False
    
    if not check_website_connection():
        return False
    
    try:
        # Load offline data
        if not os.path.exists(OFFLINE_FILE):
            return True
        
        with open(OFFLINE_FILE, 'r') as f:
            offline_data = json.load(f)
        
        if not offline_data:
            return True
        
        # Prepare data for API
        api_data = {
            "api_key": API_KEY,
            "attendance_data": offline_data
        }
        
        # Send to website
        url = f"{WEBSITE_URL.rstrip('/')}/{API_ENDPOINT.lstrip('/')}"
        response = requests.post(url, json=api_data, timeout=5, verify=False)
        
        if response.status_code == 200:
            # Clear offline data after successful sync
            os.remove(OFFLINE_FILE)
            print(f"Successfully synced {len(offline_data)} records to website")
            return True
        else:
            print(f"Sync failed: {response.status_code} - {response.text}")
            return False
            
    except Exception as e:
        print(f"Sync error: {e}")
        return False

def background_sync():
    """Background thread for automatic syncing and absent marking."""
    while True:
        try:
            # Check for automatic absent marking
            check_and_mark_automatic_absent()
            
            # Sync to website if online
            if check_internet_connection() and check_website_connection():
                sync_to_website()
            time.sleep(SYNC_INTERVAL)
        except Exception as e:
            time.sleep(60)  # Wait 1 minute before retrying

def start_background_sync():
    """Start background sync thread."""
    sync_thread = threading.Thread(target=background_sync, daemon=True)
    sync_thread.start()
    print(f"Background sync started (every {SYNC_INTERVAL} seconds)")

# ============================================================================
# BIDIRECTIONAL SYNC OPERATIONS
# ============================================================================

def sync_students_from_website():
    """Sync student data from website to local students.json"""
    try:
        if not check_internet_connection():
            print("❌ No internet connection - cannot sync students")
            return False
        
        if not check_website_connection():
            print("❌ Website not accessible - cannot sync students")
            return False
        
        # API configuration for student sync
        students_api_url = settings.get('students_api_url', f"{WEBSITE_URL}/api/students_sync.php")
        students_api_key = settings.get('students_api_key', 'your-secret-api-key-123')
        
        print(f"🔄 Syncing students from website...")
        print(f"   API URL: {students_api_url}")
        
        # Fetch students from API
        params = {
            'action': 'get_students',
            'api_key': students_api_key
        }
        
        response = requests.get(students_api_url, params=params, timeout=30, verify=False)
        
        if response.status_code == 200:
            students_data = response.json()
            
            if students_data.get('success', False):
                # Update local students.json
                with open(STUDENTS_FILE, 'w', encoding='utf-8') as f:
                    json.dump(students_data, f, indent=2, ensure_ascii=False)
                
                total_students = students_data.get('total_students', 0)
                last_updated = students_data.get('last_updated', 'Unknown')
                
                print(f"✅ Successfully synced {total_students} students")
                print(f"   Last updated: {last_updated}")
                print(f"   Updated by: {students_data.get('updated_by', 'Unknown')}")
                
                return True
            else:
                print(f"❌ API returned error: {students_data.get('message', 'Unknown error')}")
                return False
        else:
            print(f"❌ HTTP Error {response.status_code}: {response.text}")
            return False
            
    except requests.exceptions.RequestException as e:
        print(f"❌ Network error during student sync: {e}")
        return False
    except json.JSONDecodeError as e:
        print(f"❌ JSON decode error: {e}")
        return False
    except Exception as e:
        print(f"❌ Unexpected error during student sync: {e}")
        return False

def sync_attendance_to_website():
    """Sync local attendance data to website"""
    try:
        if not check_internet_connection():
            print("❌ No internet connection - cannot sync attendance")
            return False
        
        if not check_website_connection():
            print("❌ Website not accessible - cannot sync attendance")
            return False
        
        # Load local attendance data
        if not os.path.exists(CSV_FILE):
            print("📝 No local attendance data to sync")
            return True
        
        # Read CSV data tolerant of empty/headerless files
        try:
            df = pd.read_csv(CSV_FILE)
        except Exception:
            print("📝 No attendance records to sync")
            return True
        if df.empty:
            print("📝 No attendance records to sync")
            return True
        
        print(f"🔄 Syncing {len(df)} attendance records to website...")
        
        # Prepare attendance data for API
        attendance_data = []
        for _, row in df.iterrows():
            attendance_data.append({
                'student_id': row['ID'],
                'name': row['Name'],
                'timestamp': row['Timestamp'],
                'status': row['Status']
            })
        
        # Send to website API
        api_data = {
            "api_key": API_KEY,
            "attendance_data": attendance_data
        }
        
        url = f"{WEBSITE_URL.rstrip('/')}/{API_ENDPOINT.lstrip('/')}"
        response = requests.post(url, json=api_data, timeout=30, verify=False)
        
        if response.status_code == 200:
            result = response.json()
            if result.get('success', False):
                print(f"✅ Successfully synced {len(attendance_data)} attendance records")
                
                # Optionally clear local data after successful sync
                if settings.get('clear_local_after_sync', False):
                    os.remove(CSV_FILE)
                    print("🗑️ Local attendance data cleared after successful sync")
                
                return True
            else:
                print(f"❌ API error: {result.get('message', 'Unknown error')}")
                return False
        else:
            print(f"❌ HTTP Error {response.status_code}: {response.text}")
            return False
            
    except Exception as e:
        print(f"❌ Error syncing attendance: {e}")
        return False

def _read_local_attendance_df():
    """Read local attendance CSV into a DataFrame with standard headers, tolerant of missing file."""
    if not os.path.exists(CSV_FILE):
        return pd.DataFrame(columns=['ID', 'Name', 'Timestamp', 'Status'])
    try:
        try:
            return pd.read_csv(CSV_FILE)
        except Exception:
            # Fallback to explicit names when file may not have header row
            return pd.read_csv(CSV_FILE, names=['ID', 'Name', 'Timestamp', 'Status', 'Shift', 'Program', 'Current_Year', 'Admission_Year'])
    except Exception:
        return pd.DataFrame(columns=['ID', 'Name', 'Timestamp', 'Status'])

def _safe_write_attendance_df(df: pd.DataFrame):
    """Safely write DataFrame to CSV with header. Uses temp file then replace."""
    tmp_path = CSV_FILE + ".tmp"
    df.to_csv(tmp_path, index=False)
    os.replace(tmp_path, CSV_FILE)

def sync_attendance_from_website():
    """Pull recent attendance from server and reconcile into local CSV (server-wins)."""
    try:
        if not check_internet_connection():
            print("❌ No internet connection - cannot pull attendance")
            return False
        if not check_website_connection():
            print("❌ Website not accessible - cannot pull attendance")
            return False

        lookback_cutoff = get_current_time() - timedelta(days=ATT_PULL_LOOKBACK_DAYS)

        headers = {}

        all_server_records = []
        offset = 0
        page = 0
        while True:
            params = {
                'api_key': API_KEY,
                'limit': ATT_PULL_PAGE_SIZE,
                'offset': offset,
                'since': lookback_cutoff.strftime('%Y-%m-%d')
            }
            resp = requests.get(ADMIN_ATTENDANCE_API_URL, params=params, timeout=30, verify=False)
            if resp.status_code != 200:
                print(f"❌ HTTP Error {resp.status_code} while pulling attendance: {resp.text}")
                break
            data = resp.json()
            records = data.get('data') or data.get('attendance') or []
            if not records:
                break

            # Stop when oldest fetched record is older than lookback cutoff
            oldest_ts = None
            for r in records:
                all_server_records.append({
                    'ID': r.get('student_id') or r.get('studentId') or r.get('ID'),
                    'Name': r.get('student_name') or r.get('name') or r.get('Name'),
                    'Timestamp': r.get('timestamp') or r.get('Timestamp'),
                    'Status': r.get('status') or r.get('Status')
                })
                try:
                    ts_val = r.get('timestamp') or r.get('Timestamp')
                    dt = datetime.strptime(ts_val, '%Y-%m-%d %H:%M:%S') if ts_val and 'AM' not in ts_val and 'PM' not in ts_val else datetime.strptime(ts_val, '%Y-%m-%d %I:%M:%S %p')
                    if oldest_ts is None or dt < oldest_ts:
                        oldest_ts = dt
                except Exception:
                    pass

            page += 1
            offset += ATT_PULL_PAGE_SIZE

            if oldest_ts is not None and oldest_ts < lookback_cutoff.replace(tzinfo=None):
                break

            # Safety cap pages
            if page >= 20:
                break

        if not all_server_records:
            print("📝 No server attendance to reconcile")
            return True

        # Reconcile into local CSV (server wins)
        local_df = _read_local_attendance_df()
        if local_df.empty:
            local_df = pd.DataFrame(columns=['ID', 'Name', 'Timestamp', 'Status'])

        # Normalize local timestamps to strings
        if 'Timestamp' in local_df.columns:
            local_df['Timestamp'] = local_df['Timestamp'].astype(str)

        local_index = {(str(row['ID']), str(row['Timestamp'])): idx for idx, row in local_df.reset_index().iterrows()}

        updated = 0
        added = 0
        for r in all_server_records:
            sid = str(r.get('ID') or '')
            ts = str(r.get('Timestamp') or '')
            if not sid or not ts:
                continue

            key = (sid, ts)
            if key in local_index:
                idx = local_index[key]
                # Overwrite status and name from server
                if 'Status' in local_df.columns:
                    if str(local_df.at[idx, 'Status']) != str(r['Status']):
                        local_df.at[idx, 'Status'] = r['Status']
                        updated += 1
                if 'Name' in local_df.columns and r.get('Name'):
                    if str(local_df.at[idx, 'Name']) != str(r['Name']):
                        local_df.at[idx, 'Name'] = r['Name']
            else:
                # Append new record if within lookback window
                try:
                    ts_val = r.get('Timestamp')
                    dt = datetime.strptime(ts_val, '%Y-%m-%d %H:%M:%S') if ts_val and 'AM' not in ts_val and 'PM' not in ts_val else datetime.strptime(ts_val, '%Y-%m-%d %I:%M:%S %p')
                except Exception:
                    dt = None
                if dt is None or dt >= lookback_cutoff.replace(tzinfo=None):
                    new_row = {
                        'ID': r.get('ID'),
                        'Name': r.get('Name') or '',
                        'Timestamp': r.get('Timestamp'),
                        'Status': r.get('Status')
                    }
                    local_df = pd.concat([local_df, pd.DataFrame([new_row])], ignore_index=True)
                    local_index[key] = len(local_df) - 1
                    added += 1

        # Keep only standard columns when saving
        cols = [c for c in ['ID', 'Name', 'Timestamp', 'Status'] if c in local_df.columns]
        out_df = local_df[cols]
        _safe_write_attendance_df(out_df)

        print(f"✅ Attendance pull complete: updated={updated}, added={added}")
        return True

    except Exception as e:
        print(f"❌ Error pulling attendance: {e}")
        return False

def bidirectional_sync():
    """Perform bidirectional sync: students from website, attendance to website, then pull server edits"""
    print("🔄 Starting bidirectional sync...")
    print("=" * 50)
    
    # Step 1: Sync students from website
    print("📥 Step 1: Syncing students from website...")
    students_synced = sync_students_from_website()
    
    # Step 2: Sync attendance to website
    print("\n📤 Step 2: Syncing attendance to website...")
    attendance_synced = sync_attendance_to_website()

    # Step 3: Pull attendance from website (server-wins)
    print("\n📥 Step 3: Pulling attendance from website (server-wins)...")
    attendance_pulled = sync_attendance_from_website()
    
    # Summary
    print("\n📊 Sync Summary:")
    print(f"   Students sync: {'✅ Success' if students_synced else '❌ Failed'}")
    print(f"   Attendance to website: {'✅ Success' if attendance_synced else '❌ Failed'}")
    print(f"   Attendance from website: {'✅ Success' if attendance_pulled else '❌ Failed'}")
    
    return students_synced and attendance_synced and attendance_pulled

def start_bidirectional_sync():
    """Start bidirectional sync as background service - runs every minute"""
    def sync_worker():
        while True:
            try:
                print(f"\n🔄 Background Sync Service - {format_time()}")
                print("=" * 50)
                
                # Step 1: Send attendance data to website
                print("📤 Step 1: Sending attendance data to website...")
                attendance_synced = sync_attendance_to_website()
                
                # Step 2: Fetch settings from website
                print("\n📥 Step 2: Fetching settings from website...")
                settings_synced = sync_settings_from_website()
                
                # Step 3: Fetch students from website
                print("\n📥 Step 3: Fetching students from website...")
                students_synced = sync_students_from_website()
                
                # Step 4: Pull attendance changes from website (server-wins)
                print("\n📥 Step 4: Pulling attendance from website (server-wins)...")
                attendance_pulled = sync_attendance_from_website()

                # Summary
                print(f"\n📊 Background Sync Summary:")
                print(f"   Attendance to website: {'✅ Success' if attendance_synced else '❌ Failed'}")
                print(f"   Settings from website: {'✅ Success' if settings_synced else '❌ Failed'}")
                print(f"   Students from website: {'✅ Success' if students_synced else '❌ Failed'}")
                print(f"   Attendance from website: {'✅ Success' if attendance_pulled else '❌ Failed'}")
                
                # Wait 1 minute before next sync
                print(f"\n⏰ Next sync in 60 seconds...")
                time.sleep(60)
                
            except Exception as e:
                print(f"❌ Background sync error: {e}")
                print("⏰ Retrying in 60 seconds...")
                time.sleep(60)  # Wait 1 minute before retrying
    
    sync_thread = threading.Thread(target=sync_worker, daemon=True)
    sync_thread.start()
    print(f"🔄 Background bidirectional sync service started (every 60 seconds)")
    print("   • Sends attendance data to website")
    print("   • Fetches settings from website")
    print("   • Fetches students from website")

def manual_sync():
    """Manual sync function for testing or one-time sync"""
    print("🔄 Manual bidirectional sync...")
    return bidirectional_sync()

# ============================================================================
# SETTINGS BIDIRECTIONAL SYNC OPERATIONS
# ============================================================================

def sync_settings_from_website():
    """Sync settings from website to local settings.json"""
    try:
        if not check_internet_connection():
            print("❌ No internet connection - cannot sync settings")
            return False
        
        if not check_website_connection():
            print("❌ Website not accessible - cannot sync settings")
            return False
        
        # API configuration for settings sync
        settings_api_url = settings.get('settings_api_url', f"{WEBSITE_URL}/api/settings_sync.php")
        settings_api_key = settings.get('settings_api_key', 'your-secret-api-key-123')
        
        print(f"🔄 Syncing settings from website...")
        print(f"   API URL: {settings_api_url}")
        
        # Fetch settings from API
        params = {
            'action': 'get_settings',
            'api_key': settings_api_key
        }
        
        response = requests.get(settings_api_url, params=params, timeout=30, verify=False)
        
        if response.status_code == 200:
            settings_data = response.json()
            
            if settings_data.get('success', False):
                # Update local settings.json
                with open('settings.json', 'w', encoding='utf-8') as f:
                    json.dump(settings_data, f, indent=2, ensure_ascii=False)
                
                total_settings = settings_data.get('total_settings', 0)
                last_updated = settings_data.get('last_updated', 'Unknown')
                
                print(f"✅ Successfully synced {total_settings} settings")
                print(f"   Last updated: {last_updated}")
                print(f"   Updated by: {settings_data.get('updated_by', 'Unknown')}")
                
                return True
            else:
                print(f"❌ API returned error: {settings_data.get('message', 'Unknown error')}")
                return False
        else:
            print(f"❌ HTTP Error {response.status_code}: {response.text}")
            return False
            
    except requests.exceptions.RequestException as e:
        print(f"❌ Network error during settings sync: {e}")
        return False
    except json.JSONDecodeError as e:
        print(f"❌ JSON decode error: {e}")
        return False
    except Exception as e:
        print(f"❌ Unexpected error during settings sync: {e}")
        return False

def sync_settings_to_website():
    """Sync local settings to website"""
    try:
        if not check_internet_connection():
            print("❌ No internet connection - cannot sync settings")
            return False
        
        if not check_website_connection():
            print("❌ Website not accessible - cannot sync settings")
            return False
        
        # Load local settings
        if not os.path.exists('settings.json'):
            print("📝 No local settings data to sync")
            return True
        
        with open('settings.json', 'r', encoding='utf-8') as f:
            settings_data = json.load(f)
        
        settings_dict = settings_data.get('settings', {})
        if not settings_dict:
            print("📝 No settings to sync")
            return True
        
        print(f"🔄 Syncing {len(settings_dict)} settings to website...")
        
        # Send to website API
        api_data = {
            "api_key": settings.get('settings_api_key', 'your-secret-api-key-123'),
            "settings": settings_dict
        }
        
        settings_api_url = settings.get('settings_api_url', f"{WEBSITE_URL}/api/settings_sync.php")
        url = f"{settings_api_url}?action=update_settings"
        response = requests.post(url, json=api_data, timeout=30, verify=False)
        
        if response.status_code == 200:
            result = response.json()
            if result.get('success', False):
                updated_count = result.get('updated_count', 0)
                print(f"✅ Successfully synced {updated_count} settings to website")
                
                # Optionally clear local data after successful sync
                if settings.get('clear_local_after_sync', False):
                    os.remove('settings.json')
                    print("🗑️ Local settings data cleared after successful sync")
                
                return True
            else:
                print(f"❌ API error: {result.get('message', 'Unknown error')}")
                return False
        else:
            print(f"❌ HTTP Error {response.status_code}: {response.text}")
            return False
            
    except Exception as e:
        print(f"❌ Error syncing settings: {e}")
        return False

def settings_bidirectional_sync():
    """Perform bidirectional settings sync: settings from website, settings to website"""
    print("🔄 Starting settings bidirectional sync...")
    print("=" * 50)
    
    # Step 1: Sync settings from website
    print("📥 Step 1: Syncing settings from website...")
    settings_synced = sync_settings_from_website()
    
    # Step 2: Sync settings to website
    print("\n📤 Step 2: Syncing settings to website...")
    settings_to_website_synced = sync_settings_to_website()
    
    # Summary
    print("\n📊 Settings Sync Summary:")
    print(f"   Settings from website: {'✅ Success' if settings_synced else '❌ Failed'}")
    print(f"   Settings to website: {'✅ Success' if settings_to_website_synced else '❌ Failed'}")
    
    return settings_synced and settings_to_website_synced

def start_settings_bidirectional_sync():
    """Start settings bidirectional sync in background thread"""
    def settings_sync_worker():
        while True:
            try:
                settings_bidirectional_sync()
                time.sleep(settings.get('settings_sync_interval_seconds', 60))  # Default 1 minute
            except Exception as e:
                print(f"❌ Settings bidirectional sync error: {e}")
                time.sleep(60)  # Wait 1 minute before retrying
    
    sync_thread = threading.Thread(target=settings_sync_worker, daemon=True)
    sync_thread.start()
    print(f"🔄 Settings bidirectional sync started (every {settings.get('settings_sync_interval_seconds', 60)} seconds)")

def manual_settings_sync():
    """Manual settings sync function for testing or one-time sync"""
    print("🔄 Manual settings bidirectional sync...")
    return settings_bidirectional_sync()

def log_attendance(student_id, sync_manager=None):
    """Log attendance entry using enhanced check-in/check-out system with roll number parsing and time validation."""
    from checkin_manager import CheckInManager
    
    timestamp = format_time()
    students = load_students()
    
    # Parse roll number to get student metadata
    roll_data = parse_roll_number(student_id)
    if not roll_data['valid']:
        print(f"INVALID ROLL NUMBER: {student_id} - {roll_data['error']}")
        return False
    
    # Get academic year info for current_year
    from roll_parser import get_academic_year_info
    academic_info = get_academic_year_info(student_id)
    if academic_info['valid']:
        roll_data['current_year'] = academic_info['current_year']
    else:
        roll_data['current_year'] = 1  # Default fallback
    
    if student_id not in students:
        print(f"Student {student_id} not found in database!")
        return False
    
    student_name = students[student_id]["name"]
    
    # Check current status to prevent duplicates
    current_status = get_student_status_for_date(student_id)
    
    if current_status['status'] == 'Checked in':
        print(f"DUPLICATE CHECK-IN DENIED: {student_name} ({student_id}) - Already checked in at {current_status['check_in_time']}")
        return False
    elif current_status['status'] == 'Checked out':
        print(f"DUPLICATE CHECK-IN DENIED: {student_name} ({student_id}) - Already checked out at {current_status['check_out_time']}")
        return False
    elif current_status['status'] == 'Multiple check-ins detected':
        print(f"DUPLICATE CHECK-IN DENIED: {student_name} ({student_id}) - Multiple check-ins already detected")
        return False
    
    # Validate check-in time based on shift
    time_validator = TimeValidator()
    time_validation = time_validator.validate_checkin_time(student_id, None, roll_data['shift'])
    
    if not time_validation['valid']:
        print(f"CHECK-IN DENIED: {student_name} ({student_id}) - {time_validation['error']}")
        print(f"  Shift: {roll_data['shift']}")
        print(f"  Allowed Window: {time_validation['checkin_start']} - {time_validation['checkin_end']}")
        print(f"  Current Time: {time_validation['current_time']}")
        return False
    
    # Process attendance directly (no API calls needed)
    action = "Check-in"  # Mark as check-in for better clarity
    
    print(f"SUCCESS: {student_name} ({student_id}) - {action} at {timestamp}")
    print(f"  Shift: {roll_data['shift']}, Program: {roll_data['program']}, Year: {roll_data['current_year']}")
    
    # Save to local CSV for backup with enhanced metadata
    attendance_record = {
        "ID": student_id,
        "Name": student_name,
        "Timestamp": timestamp,
        "Status": action,
        "Shift": roll_data['shift'],
        "Program": roll_data['program'],
        "Current_Year": roll_data['current_year'],
        "Admission_Year": roll_data['admission_year']
    }
    
    # Save to CSV
    new_entry = pd.DataFrame([attendance_record])
    new_entry.to_csv(CSV_FILE, mode='a', header=False, index=False)
    
    # If offline, save to offline data
    if not check_internet_connection():
        if sync_manager:
            sync_manager.save_offline_data(attendance_record)
        else:
            save_offline_data(attendance_record)
        print(f"[OFFLINE] Data saved for sync later")
    
    return True

def log_checkout(student_id):
    """Log student check-out and update attendance record."""
    from checkin_manager import CheckInManager
    
    timestamp = format_time()
    students = load_students()
    
    # Parse roll number to get student metadata
    roll_data = parse_roll_number(student_id)
    if not roll_data['valid']:
        print(f"INVALID ROLL NUMBER: {student_id} - {roll_data['error']}")
        return False
    
    # Get academic year info for current_year
    from roll_parser import get_academic_year_info
    academic_info = get_academic_year_info(student_id)
    if academic_info['valid']:
        roll_data['current_year'] = academic_info['current_year']
    else:
        roll_data['current_year'] = 1  # Default fallback
    
    if student_id not in students:
        print(f"Student {student_id} not found in database!")
        return False

    student_name = students[student_id]["name"]
    
    # Check current status to prevent duplicates
    current_status = get_student_status_for_date(student_id)
    
    if current_status['status'] == 'Not checked in':
        print(f"CHECK-OUT DENIED: {student_name} ({student_id}) - Must check in first")
        return False
    elif current_status['status'] == 'Checked out':
        print(f"DUPLICATE CHECK-OUT DENIED: {student_name} ({student_id}) - Already checked out at {current_status['check_out_time']}")
        return False
    
    # Validate check-out time based on shift
    time_validator = TimeValidator()
    time_validation = time_validator.validate_checkout_time(student_id, None, roll_data['shift'])
    
    if not time_validation['valid']:
        print(f"CHECK-OUT DENIED: {student_name} ({student_id}) - {time_validation['error']}")
        print(f"  Shift: {roll_data['shift']}")
        print(f"  Allowed Window: {time_validation['checkout_start']} - {time_validation['checkout_end']}")
        print(f"  Current Time: {time_validation['current_time']}")
        return False
    
    # Update the existing attendance record instead of creating a new one
    try:
        # Load existing attendance records
        if os.path.exists(CSV_FILE):
            df = pd.read_csv(CSV_FILE, names=['ID', 'Name', 'Timestamp', 'Status', 'Shift', 'Program', 'Current_Year', 'Admission_Year'])
            
            # Find the student's check-in record for today
            today = get_current_time().strftime("%Y-%m-%d")
            student_records = df[
                (df['ID'] == student_id) & 
                (df['Timestamp'].str.startswith(today)) &
                (df['Status'] == 'Check-in')
            ]
            
            if not student_records.empty:
                # Update the existing record to show check-out time
                check_in_time = student_records.iloc[0]['Timestamp']
                duration = calculate_duration(check_in_time, timestamp)
                
                # Update the record with check-out information
                df.loc[
                    (df['ID'] == student_id) & 
                    (df['Timestamp'].str.startswith(today)) &
                    (df['Status'] == 'Check-in'),
                    'Status'
                ] = f'Check-in (Checked out at {timestamp})'
                
                # Save updated CSV
                df.to_csv(CSV_FILE, mode='w', header=False, index=False)
                
                print(f"SUCCESS: {student_name} ({student_id}) - Check-out at {timestamp}")
                print(f"  Check-in: {check_in_time}")
                print(f"  Check-out: {timestamp}")
                print(f"  Duration: {duration}")
                print(f"  Status: Present (Attended class)")
                print(f"  Shift: {roll_data['shift']}, Program: {roll_data['program']}, Year: {roll_data['current_year']}")
                
                return True
            else:
                print(f"ERROR: No check-in record found for {student_name} ({student_id})")
                return False
        else:
            print(f"ERROR: No attendance file found")
            return False
            
    except Exception as e:
        print(f"Error updating attendance record: {e}")
        return False

def calculate_duration(check_in_time, check_out_time):
    """Calculate duration between check-in and check-out."""
    try:
        from datetime import datetime
        check_in = datetime.strptime(check_in_time, '%Y-%m-%d %I:%M:%S %p')
        check_out = datetime.strptime(check_out_time, '%Y-%m-%d %I:%M:%S %p')
        duration = check_out - check_in
        hours = duration.total_seconds() / 3600
        return f"{hours:.1f} hours"
    except:
        return "Unknown duration"

def process_qr_scan(student_id, sync_manager):
    """Process QR code scan and determine check-in or check-out."""
    # Check current status
    current_status = get_student_status_for_date(student_id)
    
    if current_status['status'] == 'Not checked in':
        # Student needs to check in
        print(f"🟢 CHECK-IN: {student_id}")
        return log_attendance(student_id, sync_manager)
    elif current_status['status'] == 'Checked in':
        # Student needs to check out
        print(f"🔴 CHECK-OUT: {student_id}")
        return log_checkout(student_id)
    elif current_status['status'] == 'Checked out':
        print(f"❌ ALREADY COMPLETED: {student_id} - Already checked out for today")
        return False
    else:
        print(f"❌ ERROR: {student_id} - Status: {current_status['status']}")
        return False

def consolidate_attendance_records():
    """Consolidate check-in and check-out records into single attendance records."""
    try:
        if not os.path.exists(CSV_FILE):
            return
        
        # Load attendance records
        df = pd.read_csv(CSV_FILE, names=['ID', 'Name', 'Timestamp', 'Status', 'Shift', 'Program', 'Current_Year', 'Admission_Year'])
        
        if df.empty:
            return
        
        # Get today's date
        today = get_current_time().strftime("%Y-%m-%d")
        
        # Find students who have both check-in and check-out today
        today_records = df[df['Timestamp'].str.startswith(today)]
        
        consolidated_records = []
        processed_students = set()
        
        for _, record in today_records.iterrows():
            student_id = record['ID']
            
            if student_id in processed_students:
                continue
                
            # Get all records for this student today
            student_records = today_records[today_records['ID'] == student_id].sort_values('Timestamp')
            
            if len(student_records) >= 2:
                # Student has both check-in and check-out
                check_in = student_records[student_records['Status'] == 'Check-in'].iloc[0]
                check_out = student_records[student_records['Status'] == 'Check-out'].iloc[0]
                
                # Create consolidated record
                duration = calculate_duration(check_in['Timestamp'], check_out['Timestamp'])
                
                consolidated_record = {
                    'ID': student_id,
                    'Name': check_in['Name'],
                    'Timestamp': f"{check_in['Timestamp']} to {check_out['Timestamp']}",
                    'Status': 'Present (Attended)',
                    'Shift': check_in['Shift'],
                    'Program': check_in['Program'],
                    'Current_Year': check_in['Current_Year'],
                    'Admission_Year': check_in['Admission_Year'],
                    'Duration': duration
                }
                
                consolidated_records.append(consolidated_record)
                processed_students.add(student_id)
                
                print(f"✅ CONSOLIDATED: {check_in['Name']} ({student_id}) - Attended class")
                print(f"  Check-in: {check_in['Timestamp']}")
                print(f"  Check-out: {check_out['Timestamp']}")
                print(f"  Duration: {duration}")
        
        # Remove old records and add consolidated ones
        if consolidated_records:
            # Remove today's records
            df = df[~df['Timestamp'].str.startswith(today)]
            
            # Add consolidated records
            for record in consolidated_records:
                new_record = pd.DataFrame([{
                    'ID': record['ID'],
                    'Name': record['Name'],
                    'Timestamp': record['Timestamp'],
                    'Status': record['Status'],
                    'Shift': record['Shift'],
                    'Program': record['Program'],
                    'Current_Year': record['Current_Year'],
                    'Admission_Year': record['Admission_Year']
                }])
                df = pd.concat([df, new_record], ignore_index=True)
            
            # Save updated CSV
            df.to_csv(CSV_FILE, mode='w', header=False, index=False)
            print(f"📊 Consolidated {len(consolidated_records)} attendance records")
            
    except Exception as e:
        print(f"Error consolidating attendance records: {e}")

def mark_absent_students():
    """Mark all students as absent for current date."""
    students = load_students()
    current_date = get_current_time().strftime("%Y-%m-%d")
    
    # Get students who attended today
    df = pd.read_csv(CSV_FILE)
    if not df.empty:
        today_attended = df[
            (df['Timestamp'].str.startswith(current_date)) & 
            (df['Status'] == 'Check-in')
        ]['ID'].unique()
    else:
        today_attended = []
    
    # Mark absent students
    absent_count = 0
    for student_id, student_info in students.items():
        if student_id not in today_attended:
            # Check if already marked absent today
            if not df.empty:
                already_absent = df[
                    (df['ID'] == student_id) & 
                    (df['Timestamp'].str.startswith(current_date)) & 
                    (df['Status'] == 'Absent')
                ]
                if not already_absent.empty:
                    continue
            
            # Mark as absent
            absent_entry = pd.DataFrame({
                "ID": [student_id],
                "Name": [student_info["name"]],
                "Timestamp": [datetime.now().strftime("%Y-%m-%d %I:%M:%S %p")],
                "Status": ["Absent"]
            })
            absent_entry.to_csv(CSV_FILE, mode='a', header=False, index=False)
            absent_count += 1
    
    if absent_count > 0:
        print(f"Marked {absent_count} students as absent")
    return absent_count

def mark_absent_for_shift(shift):
    """Mark absent students for a specific shift based on 2-hour rule."""
    from time_validator import TimeValidator
    from roll_parser import parse_roll_number
    
    students = load_students()
    current_time = get_current_time()
    current_date = current_time.strftime("%Y-%m-%d")
    
    # Get shift timings
    time_validator = TimeValidator()
    shift_timings = time_validator.get_shift_timings(shift)
    
    # Calculate 2 hours after shift start
    shift_start = shift_timings['checkin_start']
    absent_deadline = datetime.combine(current_time.date(), shift_start) + timedelta(hours=2)
    
    # Make timezone-aware for comparison
    if absent_deadline.tzinfo is None:
        absent_deadline = TIMEZONE.localize(absent_deadline)
    
    # Only proceed if we're past the 2-hour deadline
    if current_time < absent_deadline:
        return 0
    
    print(f"\nAUTOMATIC ABSENT MARKING - {shift.upper()} SHIFT")
    print(f"Shift Start: {shift_start.strftime('%I:%M %p')}")
    print(f"Absent Deadline: {absent_deadline.strftime('%I:%M:%S %p')}")
    print(f"Current Time: {current_time.strftime('%I:%M:%S %p')}")
    
    # Load attendance data
    df = pd.read_csv(CSV_FILE) if os.path.exists(CSV_FILE) else pd.DataFrame()
    
    # Get students who checked in for this shift today
    shift_attended = []
    if not df.empty:
        # Get students who checked in during the shift window
        shift_start_time = datetime.combine(current_time.date(), shift_start)
        shift_end_time = datetime.combine(current_time.date(), shift_timings['checkin_end'])
        
        shift_attended = df[
            (df['Timestamp'].str.startswith(current_date)) & 
            (df['Status'] == 'Present') &
            (pd.to_datetime(df['Timestamp']).dt.time >= shift_start) &
            (pd.to_datetime(df['Timestamp']).dt.time <= shift_timings['checkin_end'])
        ]['ID'].unique()
    
    # Find students for this shift who didn't check in
    absent_count = 0
    for student_id, student_info in students.items():
        # Parse roll number to determine shift
        roll_data = parse_roll_number(student_id)
        if not roll_data['valid']:
            continue
            
        student_shift = roll_data['shift']
        
        # Only process students for the specified shift
        if student_shift.lower() != shift.lower():
            continue
            
        # Skip if student already checked in for this shift
        if student_id in shift_attended:
            continue
            
        # Check if already marked absent for this shift today
        if not df.empty:
            already_absent = df[
                (df['ID'] == student_id) & 
                (df['Timestamp'].str.startswith(current_date)) & 
                (df['Status'] == 'Absent')
            ]
            if not already_absent.empty:
                continue
        
        # Mark as absent
        absent_entry = pd.DataFrame({
            "ID": [student_id],
            "Name": [student_info["name"]],
            "Timestamp": [current_time.strftime("%Y-%m-%d %I:%M:%S %p")],
            "Status": ["Absent"],
            "Shift": [shift],
            "Auto_Marked": ["Yes"]
        })
        absent_entry.to_csv(CSV_FILE, mode='a', header=False, index=False)
        absent_count += 1
        
        print(f"  AUTO-ABSENT: {student_info['name']} ({student_id}) - {shift} shift")
    
    if absent_count > 0:
        print(f"\nMarked {absent_count} students as absent for {shift} shift")
    else:
        print(f"No students to mark absent for {shift} shift")
    
    return absent_count

def check_and_mark_automatic_absent():
    """Check if it's time to automatically mark absent students for any shift."""
    current_time = get_current_time()
    current_hour = current_time.hour
    
    # Morning shift: Check at 11:00 AM (2 hours after 9:00 AM start)
    if current_hour == 11 and current_time.minute < 5:  # Within 5 minutes of 11:00 AM
        return mark_absent_for_shift('Morning')
    
    # Evening shift: Check at 5:00 PM (2 hours after 3:00 PM start)  
    elif current_hour == 17 and current_time.minute < 5:  # Within 5 minutes of 5:00 PM
        return mark_absent_for_shift('Evening')
    
    return 0

def calculate_attendance_percentage(student_id=None):
    """Calculate attendance percentage for student."""
    df = pd.read_csv(CSV_FILE)
    
    if df.empty:
        
        print("No attendance data found!")
        return
    
    # Filter by student
    filtered_df = df.copy()
    if student_id:
        filtered_df = filtered_df[filtered_df['ID'] == student_id]
    
    if filtered_df.empty:
        print(f"No data found for the specified criteria!")
        return
    
    # Calculate percentages
    total_classes = len(filtered_df[filtered_df['Status'].isin(['Check-in', 'Check-out', 'Absent'])])
    present_count = len(filtered_df[filtered_df['Status'].isin(['Check-in', 'Check-out'])])
    absent_count = len(filtered_df[filtered_df['Status'] == 'Absent'])
    
    if total_classes > 0:
        attendance_percentage = (present_count / total_classes) * 100
    else:
        attendance_percentage = 0
    
    # Display results
    print(f"\nATTENDANCE REPORT")
    print(f"{'='*50}")
    if student_id:
        student_name = filtered_df.iloc[0]['Name'] if not filtered_df.empty else "Unknown"
        print(f"Student: {student_name} ({student_id})")
    print(f"Total Classes: {total_classes}")
    print(f"Present: {present_count}")
    print(f"Absent: {absent_count}")
    print(f"Attendance Percentage: {attendance_percentage:.1f}%")
    print(f"{'='*50}\n")
    
    return attendance_percentage

def show_attendance_summary():
    """Show overall attendance summary."""
    df = pd.read_csv(CSV_FILE)
    
    if df.empty:
        print("No attendance data found!")
        return
    
    print(f"\nATTENDANCE SUMMARY")
    print(f"{'='*60}")
    
    # Overall statistics
    total_records = len(df)
    present_count = len(df[df['Status'].isin(['Check-in', 'Check-out'])])
    absent_count = len(df[df['Status'] == 'Absent'])
    
    print(f"Total Records: {total_records}")
    print(f"Present: {present_count}")
    print(f"Absent: {absent_count}")
    
    # By student
    print(f"\nBy Student:")
    student_stats = df.groupby(['ID', 'Name'])['Status'].value_counts().unstack(fill_value=0)
    for (student_id, student_name), row in student_stats.iterrows():
        present = row.get('Present', 0)
        absent = row.get('Absent', 0)
        total = present + absent
        percentage = (present / total * 100) if total > 0 else 0
        print(f"  {student_name} ({student_id}): {present}/{total} ({percentage:.1f}%)")
    
    print(f"{'='*60}\n")

def manual_attendance_entry():
    """Allow manual entry of attendance without QR scanner."""
    print("\n" + "="*50)
    print("MANUAL ATTENDANCE ENTRY")
    print("="*50)
    
    students = load_students()
    
    # Display available students
    print("\nAvailable Students:")
    for i, (student_id, info) in enumerate(students.items(), 1):
        print(f"{i}. {info['name']} ({student_id})")
    
    print("\nOptions:")
    print("1. Mark student as Present")
    print("2. Mark student as Absent")
    print("3. Mark all students as Present")
    print("4. Mark all students as Absent")
    print("5. Back to main menu")
    
    try:
        choice = input("\nEnter your choice (1-5): ").strip()
        
        if choice == "1":
            # Mark specific student as present
            try:
                student_input = input("Enter student number (1-5): ").strip()
                student_num = int(student_input) - 1
                student_ids = list(students.keys())
                if 0 <= student_num < len(student_ids):
                    student_id = student_ids[student_num]
                    log_attendance(student_id)
                else:
                    print("Invalid student number! Please enter 1-5.")
            except ValueError:
                print("Please enter a valid number (1-5)!")
                
        elif choice == "2":
            # Mark specific student as absent
            try:
                student_input = input("Enter student number (1-5): ").strip()
                student_num = int(student_input) - 1
                student_ids = list(students.keys())
                if 0 <= student_num < len(student_ids):
                    student_id = student_ids[student_num]
                    student_name = students[student_id]["name"]
                    timestamp = datetime.now().strftime("%Y-%m-%d %I:%M:%S %p")
                    
                    # Create absent record
                    absent_record = {
                        "ID": student_id,
                        "Name": student_name,
                        "Timestamp": timestamp,
                        "Status": "Absent"
                    }
                    
                    # Save to CSV
                    new_entry = pd.DataFrame([absent_record])
                    new_entry.to_csv(CSV_FILE, mode='a', header=False, index=False)
                    
                    # Try to sync to website
                    if check_internet_connection():
                        try:
                            api_data = {
                                "api_key": API_KEY,
                                "attendance_data": [absent_record]
                            }
                            url = urljoin(WEBSITE_URL, API_ENDPOINT)
                            response = requests.post(url, json=api_data, timeout=5, verify=False)
                            
                            if response.status_code == 200:
                                print(f"SUCCESS: {student_name} ({student_id}) marked absent at {timestamp} [SYNCED]")
                            else:
                                print(f"SUCCESS: {student_name} ({student_id}) marked absent at {timestamp} [OFFLINE]")
                                save_offline_data(absent_record)
                        except:
                            print(f"SUCCESS: {student_name} ({student_id}) marked absent at {timestamp} [OFFLINE]")
                            save_offline_data(absent_record)
                    else:
                        print(f"SUCCESS: {student_name} ({student_id}) marked absent at {timestamp} [OFFLINE]")
                        save_offline_data(absent_record)
                else:
                    print("Invalid student number! Please enter 1-5.")
            except ValueError:
                print("Please enter a valid number (1-5)!")
                
        elif choice == "3":
            # Mark all students as present
            print("Marking all students as present...")
            for student_id in students.keys():
                log_attendance(student_id)
                
        elif choice == "4":
            # Mark all students as absent
            print("Marking all students as absent...")
            mark_absent_students()
            
        elif choice == "5":
            return
        else:
            print("Invalid choice!")
            
    except KeyboardInterrupt:
        print("\nOperation cancelled.")
    except Exception as e:
        print(f"Error: {e}")

def main():
    """Main program loop."""
    print("=" * 60)
    print("Enhanced QR Code Attendance System")
    print("=" * 60)
    
    # Initialize files
    initialize_students()
    initialize_csv()
    
    # Initialize sync manager
    sync_manager = SyncManager()
    
    # Check system mode with detailed status
    internet_status = check_internet_connection()
    website_status = check_website_connection()
    
    # Check admin panel connection
    admin_status = check_admin_panel_connection()
    
    print(f"\nConnection Status:")
    print(f"   Internet: {'ONLINE' if internet_status else 'OFFLINE'}")
    print(f"   Website: {'ONLINE' if website_status else 'OFFLINE'}")
    print(f"   Admin Panel: {'ONLINE' if admin_status else 'OFFLINE'}")
    print(f"   Website URL: {WEBSITE_URL}")
    print(f"   Admin Panel URL: {settings.get('website_url', 'http://localhost/qr_attendance/public')}/admin")
    
    if admin_status:
        print(f"   ✅ Full integration with admin panel available")
    else:
        print(f"   ⚠️ Admin panel APIs unavailable - using local mode")
    
    if is_offline_mode():
        print("\n🔄 OFFLINE MODE - No web server detected")
        print("   • Data will be saved locally")
        print("   • Use 'manual' for attendance entry")
        print("   • Data can be synced later when web server is available")
    else:
        print("\n🌐 ONLINE MODE - Web server detected")
        print("   • Data will sync automatically")
        print("   • Dashboard available at web server")
    
    # Start advanced sync manager
    sync_manager.start_auto_sync()
    
    # Start CSV auto-sync (every 1 minute) - in addition to regular sync
    sync_manager.start_csv_auto_sync(interval_seconds=60)
    
    # Start bidirectional sync as background service (every 1 minute)
    start_bidirectional_sync()
    
    print("\nAvailable Commands:")
    print("  • Scan QR code: Just scan the student's QR code")
    print("  • 'manual': Manual attendance entry (no scanner needed)")
    print("  • 'mark_absent': Mark absent students")
    print("  • 'summary': Show overall attendance summary")
    print("  • 'sync_students': Force sync students from website with complete dataset")
    print("  • 'bidirectional_sync': Sync students from website AND attendance to website")
    print("  • 'start_bidirectional_sync': Start automatic bidirectional sync in background")
    print("  • 'sync_settings': Force sync settings from website with complete dataset")
    print("  • 'settings_bidirectional_sync': Sync settings from website AND settings to website")
    print("  • 'start_settings_bidirectional_sync': Start automatic settings bidirectional sync in background")
    print("  • 'consolidate': Consolidate check-in/check-out records into single attendance records")
    print("  • 'status': Check connection and sync status")
    print("  • 'quit': Exit the system")
    print("\nReady to scan QR codes or use manual entry...")
    print("Tip: Press Ctrl+C to exit\n")
    
    try:
        while True:
            # Wait for input
            user_input = input().strip()
            
            # Ignore empty input
            if not user_input:
                continue
            
            # Handle commands
            if user_input.lower() == 'quit':
                print("\n👋 Attendance system stopped. Goodbye!")
                break
            elif user_input.lower() == 'manual':
                manual_attendance_entry()
            elif user_input.lower() == 'summary':
                show_attendance_summary()
            elif user_input.lower() == 'mark_absent':
                mark_absent_students()
            elif user_input.lower() == 'sync_students':
                print("🔄 Syncing students from website with complete dataset...")
                if sync_students_from_website():
                    print("✅ Students synced successfully with complete dataset!")
                    
                    # Show enhanced sync status
                    try:
                        with open(STUDENTS_FILE, 'r', encoding='utf-8') as f:
                            students_data = json.load(f)
                        
                        total_students = students_data.get('total_students', 0)
                        last_updated = students_data.get('last_updated', 'Unknown')
                        updated_by = students_data.get('updated_by', 'Unknown')
                        
                        print(f"📊 Sync Status:")
                        print(f"   Total students: {total_students}")
                        print(f"   Last updated: {last_updated}")
                        print(f"   Updated by: {updated_by}")
                        
                        # Show sample of synced data
                        students = students_data.get('students', {})
                        if students:
                            first_student_id = list(students.keys())[0]
                            first_student = students[first_student_id]
                            print(f"\n📋 Sample Student Data:")
                            print(f"   Student ID: {first_student_id}")
                            print(f"   Name: {first_student.get('name', 'N/A')}")
                            print(f"   Email: {first_student.get('email', 'N/A')}")
                            print(f"   Password: {first_student.get('password', 'N/A')}")
                            print(f"   Program: {first_student.get('program', 'N/A')}")
                            print(f"   Shift: {first_student.get('shift', 'N/A')}")
                            print(f"   Section: {first_student.get('section', 'N/A')}")
                            print(f"   Roll Number: {first_student.get('roll_number', 'N/A')}")
                            print(f"   Is Active: {first_student.get('is_active', 'N/A')}")
                            
                    except Exception as e:
                        print(f"⚠️ Could not load sync status: {e}")
                else:
                    print("❌ Student sync failed - check website connection and API settings")
            elif user_input.lower() == 'bidirectional_sync':
                print("🔄 Starting bidirectional sync...")
                if manual_sync():
                    print("✅ Bidirectional sync completed successfully!")
                else:
                    print("❌ Bidirectional sync failed - check connections")
            elif user_input.lower() == 'start_bidirectional_sync':
                print("🔄 Starting bidirectional sync in background...")
                start_bidirectional_sync()
                print("✅ Bidirectional sync started in background!")
            elif user_input.lower() == 'sync_settings':
                print("🔄 Syncing settings from website with complete dataset...")
                if sync_settings_from_website():
                    print("✅ Settings synced successfully with complete dataset!")
                    
                    # Show enhanced sync status
                    try:
                        with open('settings.json', 'r', encoding='utf-8') as f:
                            settings_data = json.load(f)
                        
                        total_settings = len(settings_data.get('settings', {}))
                        last_updated = settings_data.get('last_updated', 'Unknown')
                        updated_by = settings_data.get('updated_by', 'Unknown')
                        
                        print(f"📊 Settings Sync Status:")
                        print(f"   Total settings: {total_settings}")
                        print(f"   Last updated: {last_updated}")
                        print(f"   Updated by: {updated_by}")
                        
                        # Show sample of synced settings
                        settings_dict = settings_data.get('settings', {})
                        if settings_dict:
                            sample_keys = list(settings_dict.keys())[:5]  # Show first 5 settings
                            print(f"\n📋 Sample Settings:")
                            for key in sample_keys:
                                value = settings_dict[key]
                                print(f"   {key}: {value}")
                            if len(settings_dict) > 5:
                                print(f"   ... and {len(settings_dict) - 5} more settings")
                            
                    except Exception as e:
                        print(f"⚠️ Could not load settings sync status: {e}")
                else:
                    print("❌ Settings sync failed - check website connection and API settings")
            elif user_input.lower() == 'settings_bidirectional_sync':
                print("🔄 Starting settings bidirectional sync...")
                if manual_settings_sync():
                    print("✅ Settings bidirectional sync completed successfully!")
                else:
                    print("❌ Settings bidirectional sync failed - check connections")
            elif user_input.lower() == 'start_settings_bidirectional_sync':
                print("🔄 Starting settings bidirectional sync in background...")
                start_settings_bidirectional_sync()
                print("✅ Settings bidirectional sync started in background!")
            elif user_input.startswith('status '):
                student_id = user_input.split(' ', 1)[1]
                print(f"Checking status for {student_id}...")
                status = get_student_status_for_date(student_id)
                print(f"Status: {status['status']}")
                if status['check_in_time']:
                    print(f"Check-in time: {status['check_in_time']}")
                if status['check_out_time']:
                    print(f"Check-out time: {status['check_out_time']}")
                if status.get('error'):
                    print(f"Error: {status['error']}")
            elif user_input.lower() == 'show_settings':
                print("📋 CURRENT SETTINGS")
                print("=" * 50)
                current_settings = settings.load_settings(force_reload=False)
                
                # Show all time-related settings
                time_settings = [
                    'morning_checkin_start', 'morning_checkin_end', 'morning_class_end',
                    'evening_checkin_start', 'evening_checkin_end', 'evening_class_end',
                    'morning_checkout_start', 'morning_checkout_end',
                    'evening_checkout_start', 'evening_checkout_end'
                ]
                
                print("🕐 TIME SETTINGS:")
                for setting in time_settings:
                    if setting in current_settings:
                        value = current_settings[setting]
                        print(f"  • {setting}: {value}")
                
                # Show other important settings
                other_settings = ['sync_interval_seconds', 'timezone', 'website_url', 'api_key']
                print("\n⚙️ OTHER SETTINGS:")
                for setting in other_settings:
                    if setting in current_settings:
                        value = current_settings[setting]
                        if setting == 'api_key':
                            # Mask API key for security
                            masked_value = value[:8] + '...' + value[-4:] if len(value) > 12 else '***'
                            print(f"  • {setting}: {masked_value}")
                        else:
                            print(f"  • {setting}: {value}")
                
                print(f"\n📊 Total settings loaded: {len(current_settings)}")
                
            elif user_input.lower() == 'consolidate':
                print("🔄 CONSOLIDATING ATTENDANCE RECORDS")
                print("=" * 50)
                consolidate_attendance_records()
                print("✅ Attendance records consolidated!")
            elif user_input.lower() in ['sync_settings', 'syncs_settings']:
                print("🔄 ADVANCED SETTINGS SYNC")
                print("=" * 50)
                
                # Get current settings before sync
                print("📋 Getting current settings...")
                current_settings = settings.load_settings(force_reload=False)
                
                # Show key settings before sync
                print("\n📊 BEFORE SYNC:")
                key_settings = [
                    'morning_checkin_start', 'morning_checkin_end', 'morning_class_end',
                    'evening_checkin_start', 'evening_checkin_end', 'evening_class_end',
                    'sync_interval_seconds', 'timezone'
                ]
                before_values = {}
                for key in key_settings:
                    if key in current_settings:
                        before_values[key] = current_settings[key]
                        print(f"  • {key}: {current_settings[key]}")
                
                print(f"\n🔄 Syncing from admin panel...")
                sync_result = settings.force_reload_settings()
                
                if sync_result:
                    print("✅ Settings sync completed!")
                    
                    # Get updated settings
                    updated_settings = settings.load_settings(force_reload=True)
                    print(f"📊 Loaded {len(updated_settings)} settings")
                    
                    # Show changes
                    print("\n📊 AFTER SYNC:")
                    changes_detected = False
                    for key in key_settings:
                        if key in updated_settings:
                            new_value = updated_settings[key]
                            old_value = before_values.get(key, 'Not set')
                            print(f"  • {key}: {new_value}")
                            
                            if old_value != new_value:
                                changes_detected = True
                                print(f"    🔄 CHANGED: {old_value} → {new_value}")
                    
                    if changes_detected:
                        print("\n✅ Changes detected and applied!")
                    else:
                        print("\nℹ️ No changes detected - settings are up to date")
                    
                    # Validate critical settings
                    print("\n🔍 VALIDATION:")
                    critical_settings = ['morning_checkin_start', 'evening_checkin_start']
                    for setting in critical_settings:
                        if setting in updated_settings:
                            value = updated_settings[setting]
                            if ':' in value and len(value.split(':')) >= 2:
                                print(f"  ✅ {setting}: {value} (valid format)")
                            else:
                                print(f"  ⚠️ {setting}: {value} (check format)")
                    
                    print(f"\n🎯 Settings sync completed successfully!")
                    
                else:
                    print("❌ Settings sync failed!")
                    print("🔍 Troubleshooting:")
                    print("  • Check admin panel connection")
                    print("  • Verify API key is correct")
                    print("  • Ensure settings_api.php is accessible")
                    print("  • Check website URL in config.json")
            elif user_input.lower() == 'help':
                print("\n📋 AVAILABLE COMMANDS:")
                print("=" * 50)
                print("📱 ATTENDANCE:")
                print("  • Scan QR code: Just scan the student's QR code")
                print("  • 'manual': Manual attendance entry (no scanner needed)")
                print("  • 'mark_absent': Mark absent students")
                print()
                print("📊 REPORTS:")
                print("  • 'summary': Show overall attendance summary")
                print("  • 'status': Check connection and sync status")
                print("  • 'status [student_id]': Check student's attendance status")
                print("  • 'show_settings': Display current settings")
                print()
                print("🔄 SYNC:")
                print("  • 'sync_students': Force sync students from website with complete dataset")
                print("  • 'sync_settings': Force sync settings from admin panel")
                print()
                print("🔧 SYSTEM:")
                print("  • 'quit': Exit the system")
                print("=" * 50)
                print()
            elif user_input.lower() == 'status':
                # Get sync manager status
                sync_status = sync_manager.get_sync_status()
                
                print(f"\n📊 SYSTEM STATUS")
                print(f"{'='*60}")
                print(f"Internet Connection: {'✅ ONLINE' if sync_status['internet'] else '❌ OFFLINE'}")
                print(f"Website Status: {'✅ ONLINE' if sync_status['website'] else '❌ OFFLINE'}")
                print(f"Website URL: {WEBSITE_URL}")
                print(f"Sync Interval: {SYNC_INTERVAL} seconds")
                print(f"Currently Syncing: {'✅ YES' if sync_status['is_syncing'] else '❌ NO'}")
                print(f"CSV Auto-Sync: ✅ ACTIVE (every 60 seconds)")
                print(f"Background Sync: ✅ ACTIVE (every 60 seconds)")
                print(f"Offline Records: {sync_status['offline_records']}")
                print(f"Local Records: {sync_status['local_records']}")
                
                # Check CSV data
                try:
                    df = pd.read_csv(CSV_FILE)
                    print(f"Total Attendance Records: {len(df)}")
                except:
                    print("Total Attendance Records: 0")
                
                # Show students sync status
                try:
                    if os.path.exists(STUDENTS_FILE):
                        with open(STUDENTS_FILE, 'r', encoding='utf-8') as f:
                            students_data = json.load(f)
                        print(f"\n📋 STUDENTS SYNC STATUS")
                        print(f"   Total students: {students_data.get('total_students', 0)}")
                        print(f"   Last updated: {students_data.get('last_updated', 'Unknown')}")
                        print(f"   Updated by: {students_data.get('updated_by', 'Unknown')}")
                    else:
                        print(f"\n📋 STUDENTS SYNC STATUS")
                        print("   No students.json file found")
                except Exception as e:
                    print(f"\n📋 STUDENTS SYNC STATUS")
                    print(f"   Error reading students.json: {e}")
                
                # Show settings sync status
                try:
                    if os.path.exists('settings.json'):
                        with open('settings.json', 'r', encoding='utf-8') as f:
                            settings_data = json.load(f)
                        print(f"\n⚙️ SETTINGS SYNC STATUS")
                        print(f"   Total settings: {len(settings_data.get('settings', {}))}")
                        print(f"   Last updated: {settings_data.get('last_updated', 'Unknown')}")
                        print(f"   Updated by: {settings_data.get('updated_by', 'Unknown')}")
                    else:
                        print(f"\n⚙️ SETTINGS SYNC STATUS")
                        print("   No settings.json file found")
                except Exception as e:
                    print(f"\n⚙️ SETTINGS SYNC STATUS")
                    print(f"   Error reading settings.json: {e}")
                
                print(f"\n🔄 BACKGROUND SYNC OPERATIONS")
                print(f"   • Sends attendance data to website")
                print(f"   • Fetches settings from website")
                print(f"   • Fetches students from website")
                print(f"   • Runs every 60 seconds automatically")
                
                print(f"{'='*60}\n")
            else:
                # Check if input looks like a roll number (YY-[E]PROGRAM-NN format)
                if (len(user_input) >= 6 and 
                    user_input.count('-') >= 2 and 
                    not user_input.startswith('sync') and
                    not user_input.startswith('status') and
                    not user_input.startswith('manual') and
                    not user_input.startswith('summary') and
                    not user_input.startswith('mark') and
                    not user_input.startswith('quit')):
                    # Treat as QR code scan - determine check-in or check-out
                    process_qr_scan(user_input, sync_manager)
                else:
                    print(f"❌ Unknown command: '{user_input}'")
                    print("💡 Type 'help' for all available commands or scan a QR code")
                    print("🔧 Quick commands: 'manual', 'sync_students', 'sync_settings', 'status', 'quit'")
            
    except KeyboardInterrupt:
        print("\n\nAttendance system stopped. Goodbye!")
        sys.exit(0)
    except Exception as e:
        print(f"\nError: {e}")
        sys.exit(1)

if __name__ == "__main__":
    main()