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
from time_validator import validate_checkin_time, validate_checkin_time_by_shift, validate_checkout_time_by_shift
from student_sync import student_sync
from qr_scanner import QRScanner

# Disable SSL warnings
urllib3.disable_warnings(urllib3.exceptions.InsecureRequestWarning)

# Import settings manager and secure configuration
from settings import SettingsManager
from secure_config import get_config, get_api_config

# Initialize settings manager
settings = SettingsManager()

# Configuration - Load from settings with fallbacks
CSV_FILE = "attendance.csv"
STUDENTS_FILE = "students.json"
OFFLINE_FILE = "offline_data.json"
HEADERS = ["ID", "Name", "Timestamp", "Status", "Shift", "Program", "Current_Year", "Admission_Year", "Check_In_Time", "Check_Out_Time", "Duration_Minutes"]

# Timezone Configuration
TIMEZONE = pytz.timezone(settings.get('timezone', 'Asia/Karachi'))

# Website Configuration - Load from secure configuration
WEBSITE_URL = get_config('WEBSITE_URL', 'http://localhost/qr_attendance/public')
API_ENDPOINT = settings.get('api_endpoint_attendance', '/api/api_attendance.php')

# Load secure API configuration
api_config = get_api_config()
API_KEY = api_config['key']
API_SECRET = api_config['secret']
JWT_SECRET = api_config['jwt_secret']
ENCRYPTION_KEY = api_config['encryption_key']

SYNC_INTERVAL = settings.get('sync_interval_seconds', 30)  # Sync interval from settings
ADMIN_ATTENDANCE_API_URL = settings.get('admin_attendance_api_url', f"{WEBSITE_URL}/api/api_attendance.php")
ADMIN_API_KEY = API_KEY  # Use the same secure API key
ATT_PULL_LOOKBACK_DAYS = settings.get('attendance_pull_lookback_days', 7)
ATT_PULL_PAGE_SIZE = settings.get('attendance_pull_page_size', 1000)

# Global sync lock to prevent race conditions
sync_lock = threading.Lock()

def get_current_time():
    """Get current time in Asia/Karachi timezone."""
    return datetime.now(TIMEZONE)

def format_time(dt=None):
    """Format datetime in Asia/Karachi timezone with 12-hour format."""
    if dt is None:
        dt = get_current_time()
    return dt.strftime("%Y-%m-%d %I:%M:%S %p")

def format_time_24h(dt=None):
    """Format datetime in Asia/Karachi timezone with 24-hour format for API."""
    if dt is None:
        dt = get_current_time()
    return dt.strftime("%Y-%m-%d %H:%M:%S")

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
        # Validate existing CSV file
        try:
            df = pd.read_csv(CSV_FILE)
            if len(df.columns) != len(HEADERS):
                print(f"⚠️ CSV header mismatch. Recreating {CSV_FILE}...")
                backup_file = f"{CSV_FILE}.backup_{int(time.time())}"
                os.rename(CSV_FILE, backup_file)
                df = pd.DataFrame(columns=HEADERS)
                df.to_csv(CSV_FILE, index=False)
                print(f"✅ CSV recreated. Backup saved as: {backup_file}")
            else:
                print(f"Using existing attendance file: {CSV_FILE}")
        except Exception as e:
            print(f"⚠️ Error reading CSV, recreating: {e}")
            backup_file = f"{CSV_FILE}.backup_{int(time.time())}"
            if os.path.exists(CSV_FILE):
                os.rename(CSV_FILE, backup_file)
            df = pd.DataFrame(columns=HEADERS)
            df.to_csv(CSV_FILE, index=False)
            print(f"✅ CSV recreated. Backup saved as: {backup_file}")

def load_students():
    """Load student data from JSON file with enhanced error handling."""
    try:
        if not os.path.exists(STUDENTS_FILE):
            print(f"⚠️ {STUDENTS_FILE} not found. Creating empty students database.")
            return {}
        
        with open(STUDENTS_FILE, 'r', encoding='utf-8') as f:
            data = json.load(f)
        
        # Handle nested structure from student_sync
        if 'students' in data:
            students = data['students']
        else:
            # Handle direct structure (fallback)
            students = data
        
        # Validate student data structure
        validated_students = {}
        for student_id, student_info in students.items():
            if isinstance(student_info, dict) and 'name' in student_info:
                validated_students[student_id] = student_info
            else:
                print(f"⚠️ Invalid student data for {student_id}: {student_info}")
        
        print(f"✅ Loaded {len(validated_students)} students from {STUDENTS_FILE}")
        return validated_students
        
    except Exception as e:
        print(f"❌ Error loading students: {e}")
        return {}

def get_student_shift(student_id):
    """Get student shift from student data or roll number."""
    try:
        # Load student data
        students = load_students()
        student_data = students.get(student_id)
        
        if student_data and 'shift' in student_data:
            return student_data['shift']
        
        # If not found in student data, try to parse from roll number
        try:
            from roll_parser import get_shift
            shift = get_shift(student_id)
            if shift:
                return shift
        except ImportError:
            pass
        
        # Default to morning if shift cannot be determined
        return 'Morning'
        
    except Exception as e:
        print(f"❌ Error getting student shift for {student_id}: {e}")
        return 'Morning'  # Default to morning

def get_student_status_for_date(student_id, date=None):
    """Get student's check-in/check-out status for a specific date with enhanced logic."""
    if date is None:
        date = get_current_time().strftime("%Y-%m-%d")
    
    try:
        # Load attendance records
        if not os.path.exists(CSV_FILE):
            return {'status': 'Not checked in', 'check_in_time': None, 'check_out_time': None}
        
        df = pd.read_csv(CSV_FILE)
        
        if df.empty:
            return {'status': 'Not checked in', 'check_in_time': None, 'check_out_time': None}
        
        # Filter records for this student and date
        student_records = df[
            (df['ID'] == student_id) & 
            (df['Timestamp'].str.startswith(date))
        ].sort_values('Timestamp')
        
        if student_records.empty:
            return {'status': 'Not checked in', 'check_in_time': None, 'check_out_time': None}
        
        # Enhanced status detection logic
        check_in_records = student_records[student_records['Status'] == 'Check-in']
        present_records = student_records[student_records['Status'] == 'Present']
        absent_records = student_records[student_records['Status'] == 'Absent']
        
        # Check for multiple check-ins (should not happen with new logic)
        if len(check_in_records) > 1:
            print(f"⚠️ Multiple check-ins detected for {student_id} on {date}")
            # Use the latest check-in
            latest_checkin = check_in_records.iloc[-1]
            return {
                'status': 'Multiple check-ins detected', 
                'check_in_time': latest_checkin['Timestamp'], 
                'check_out_time': None,
                'error': 'Multiple check-ins detected'
            }
        
        # Determine current status based on records
        if not check_in_records.empty and not present_records.empty:
            # Student has both check-in and present (checked out)
            check_in_time = check_in_records.iloc[0]['Timestamp']
            check_out_record = present_records.iloc[0]
            return {
                'status': 'Checked out', 
                'check_in_time': check_in_time, 
                'check_out_time': check_out_record['Timestamp']
            }
        elif not check_in_records.empty:
            # Student has check-in but no check-out (still checked in)
            check_in_time = check_in_records.iloc[0]['Timestamp']
            return {
                'status': 'Checked in', 
                'check_in_time': check_in_time, 
                'check_out_time': None
            }
        elif not absent_records.empty:
            # Student marked absent
            absent_time = absent_records.iloc[0]['Timestamp']
            return {
                'status': 'Absent', 
                'check_in_time': absent_time, 
                'check_out_time': absent_time
            }
        else:
            return {'status': 'Not checked in', 'check_in_time': None, 'check_out_time': None}
            
    except Exception as e:
        print(f"❌ Error checking student status: {e}")
        return {'status': 'Error', 'check_in_time': None, 'check_out_time': None, 'error': str(e)}

def check_internet_connection():
    """Check if internet connection is available with enhanced reliability."""
    try:
        # Try local server first
        try:
            response = requests.get(WEBSITE_URL, timeout=3, verify=False)
            if response.status_code in [200, 404, 403]:
                return True
        except:
            pass
        
        # Try multiple external URLs with timeout
        test_urls = [
            "https://www.google.com",
            "https://httpbin.org/get",
            "https://www.github.com"
        ]
        
        for url in test_urls:
            try:
                response = requests.get(url, timeout=5, verify=False)
                if response.status_code == 200:
                    return True
            except:
                continue
        
        return False
    except Exception as e:
        print(f"❌ Internet connection check failed: {e}")
        return False

def check_website_connection():
    """Check if the website is accessible with enhanced error handling."""
    try:
        # Try the main website first
        response = requests.get(WEBSITE_URL, timeout=10, verify=False)
        if response.status_code == 200:
            return True
        
        # Try the API endpoint directly
        api_url = urljoin(WEBSITE_URL, API_ENDPOINT)
        response = requests.get(api_url, timeout=10, verify=False)
        if response.status_code in [200, 404, 405]:
            return True
        
        return False
    except Exception as e:
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
        print(f"❌ Admin panel connection check failed: {e}")
        return False

def is_offline_mode():
    """Check if system should run in offline mode."""
    return not check_website_connection()

def save_offline_data(attendance_data):
    """Save attendance data to offline storage with enhanced error handling."""
    try:
        # Load existing offline data
        offline_data = []
        if os.path.exists(OFFLINE_FILE):
            try:
                with open(OFFLINE_FILE, 'r') as f:
                    offline_data = json.load(f)
                if not isinstance(offline_data, list):
                    offline_data = []
            except:
                offline_data = []
        
        # Add new data with timestamp
        attendance_data['offline_saved_at'] = format_time_24h()
        offline_data.append(attendance_data)
        
        # Save back to file
        with open(OFFLINE_FILE, 'w') as f:
            json.dump(offline_data, f, indent=2)
        
        print(f"✅ Data saved offline for sync later (Total: {len(offline_data)} records)")
        return True
    except Exception as e:
        print(f"❌ Error saving offline data: {e}")
        return False

def sync_to_website():
    """Sync offline data to website when connection is available with enhanced reliability."""
    with sync_lock:  # Prevent multiple simultaneous syncs
        if not check_internet_connection():
            print("❌ No internet connection - cannot sync")
            return False
        
        if not check_website_connection():
            print("❌ Website not accessible - cannot sync")
            return False
        
        try:
            # Load offline data
            if not os.path.exists(OFFLINE_FILE):
                return True
            
            with open(OFFLINE_FILE, 'r') as f:
                offline_data = json.load(f)
            
            if not offline_data:
                return True
            
            print(f"🔄 Syncing {len(offline_data)} offline records to website...")
            
            # Prepare data for API
            api_data = {
                "api_key": API_KEY,
                "attendance_data": offline_data
            }
            
            # Send to website
            url = f"{WEBSITE_URL.rstrip('/')}/{API_ENDPOINT.lstrip('/')}"
            response = requests.post(url, json=api_data, timeout=30, verify=False)
            
            if response.status_code == 200:
                result = response.json()
                if result.get('success', False):
                    # Clear offline data after successful sync
                    os.remove(OFFLINE_FILE)
                    print(f"✅ Successfully synced {len(offline_data)} records to website")
                    return True
                else:
                    print(f"❌ API returned error: {result.get('message', 'Unknown error')}")
                    return False
            else:
                print(f"❌ Sync failed: {response.status_code} - {response.text}")
                return False
                
        except Exception as e:
            print(f"❌ Sync error: {e}")
            return False

def background_sync():
    """Background thread for automatic syncing and absent marking with enhanced error handling."""
    while True:
        try:
            # Check for automatic absent marking
            check_and_mark_automatic_absent()
            
            # Sync to website if online
            if check_internet_connection() and check_website_connection():
                sync_to_website()
            
            time.sleep(SYNC_INTERVAL)
        except Exception as e:
            print(f"❌ Background sync error: {e}")
            time.sleep(60)  # Wait 1 minute before retrying

def start_background_sync():
    """Start background sync thread."""
    sync_thread = threading.Thread(target=background_sync, daemon=True)
    sync_thread.start()
    print(f"✅ Background sync started (every {SYNC_INTERVAL} seconds)")
    time.sleep(1)  # Small delay to let background services initialize

# ============================================================================
# BIDIRECTIONAL SYNC OPERATIONS - ENHANCED
# ============================================================================

def sync_students_from_website(quiet=False):
    """Sync student data from website to local students.json with enhanced error handling."""
    with sync_lock:
        try:
            if not check_internet_connection():
                if not quiet:
                    print("❌ No internet connection - cannot sync students")
                return False
            
            if not check_website_connection():
                if not quiet:
                    print("❌ Website not accessible - cannot sync students")
                return False
            
            # API configuration for student sync
            students_api_url = settings.get('students_api_url', f"{WEBSITE_URL}/api/students_sync.php")
            students_api_key = settings.get('students_api_key', settings.get('api_key', API_KEY))
            
            if not quiet:
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
    """Sync local attendance data to website with enhanced data validation."""
    with sync_lock:
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
            
            # Read CSV data with enhanced error handling
            try:
                df = pd.read_csv(CSV_FILE)
                if df.empty:
                    print("📝 No attendance records to sync")
                    return True
            except Exception as e:
                print(f"❌ Error reading CSV file: {e}")
                return False
            
            print(f"🔄 Syncing {len(df)} attendance records to website...")
            
            # Prepare attendance data for API with data validation
            attendance_data = []
            for _, row in df.iterrows():
                # Enhanced NaN handling and data validation
                def safe_get(column, default):
                    value = row.get(column, default)
                    if pd.isna(value):
                        return default
                    return value
                
                # Validate required fields
                student_id = str(row['ID'])
                if not student_id or student_id == 'nan':
                    print(f"⚠️ Skipping record with invalid student ID: {row}")
                    continue
                
                attendance_record = {
                    'student_id': student_id,
                    'name': str(safe_get('Name', 'Unknown')),
                    'timestamp': str(safe_get('Timestamp', format_time_24h())),
                    'status': str(safe_get('Status', 'Present')),
                    'shift': str(safe_get('Shift', 'Morning')),
                    'program': str(safe_get('Program', '')),
                    'current_year': int(safe_get('Current_Year', 1)),
                    'admission_year': int(safe_get('Admission_Year', 2025)),
                    'check_in_time': str(safe_get('Check_In_Time', '')),
                    'check_out_time': str(safe_get('Check_Out_Time', '')),
                    'session_duration': int(safe_get('Duration_Minutes', 0))
                }
                attendance_data.append(attendance_record)
            
            if not attendance_data:
                print("📝 No valid attendance records to sync")
                return True
            
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
    """Read local attendance CSV into a DataFrame with enhanced error handling."""
    if not os.path.exists(CSV_FILE):
        return pd.DataFrame(columns=HEADERS)
    
    try:
        df = pd.read_csv(CSV_FILE)
        
        # Validate column structure
        if len(df.columns) != len(HEADERS):
            print(f"⚠️ CSV column mismatch. Expected {len(HEADERS)} columns, got {len(df.columns)}")
            # Try to fix by reading with explicit headers
            df = pd.read_csv(CSV_FILE, names=HEADERS, header=0)
        
        return df
    except Exception as e:
        print(f"❌ Error reading CSV: {e}")
        # Fallback to explicit names when file may not have header row
        try:
            return pd.read_csv(CSV_FILE, names=HEADERS)
        except:
            return pd.DataFrame(columns=HEADERS)

def _safe_write_attendance_df(df: pd.DataFrame):
    """Safely write DataFrame to CSV with backup and validation."""
    try:
        # Create backup before writing
        if os.path.exists(CSV_FILE):
            backup_file = f"{CSV_FILE}.backup_{int(time.time())}"
            import shutil
            shutil.copy2(CSV_FILE, backup_file)
        
        # Ensure proper column order
        df = df[HEADERS] if all(col in df.columns for col in HEADERS) else df
        
        # Write to temporary file first
        tmp_path = CSV_FILE + ".tmp"
        df.to_csv(tmp_path, index=False)
        
        # Validate the written file
        validation_df = pd.read_csv(tmp_path)
        if len(validation_df.columns) == len(HEADERS):
            # Replace original file
            os.replace(tmp_path, CSV_FILE)
            print(f"✅ Attendance data saved successfully ({len(df)} records)")
        else:
            raise ValueError("Written file validation failed")
            
    except Exception as e:
        print(f"❌ Error writing attendance data: {e}")
        if os.path.exists(tmp_path):
            os.remove(tmp_path)

def sync_attendance_from_website():
    """Pull recent attendance from server and reconcile into local CSV with enhanced conflict resolution."""
    with sync_lock:
        try:
            if not check_internet_connection():
                print("❌ No internet connection - cannot pull attendance")
                return False
            if not check_website_connection():
                print("❌ Website not accessible - cannot pull attendance")
                return False

            lookback_cutoff = get_current_time() - timedelta(days=ATT_PULL_LOOKBACK_DAYS)

            all_server_records = []
            offset = 0
            page = 0
            
            print(f"🔄 Pulling attendance from website (lookback: {ATT_PULL_LOOKBACK_DAYS} days)...")
            
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

                # Process records with enhanced validation
                for r in records:
                    # Validate required fields
                    student_id = r.get('student_id') or r.get('studentId') or r.get('ID')
                    timestamp = r.get('timestamp') or r.get('Timestamp')
                    
                    if not student_id or not timestamp:
                        continue
                    
                    # Normalize timestamp to 12-hour format for consistency
                    try:
                        if 'AM' not in timestamp and 'PM' not in timestamp:
                            # Convert 24-hour format to 12-hour format
                            dt = datetime.strptime(timestamp, '%Y-%m-%d %H:%M:%S')
                            normalized_timestamp = dt.strftime('%Y-%m-%d %I:%M:%S %p')
                        else:
                            normalized_timestamp = timestamp
                    except:
                        normalized_timestamp = timestamp
                    
                    server_record = {
                        'ID': str(student_id),
                        'Name': r.get('student_name') or r.get('name') or r.get('Name') or '',
                        'Timestamp': normalized_timestamp,
                        'Status': r.get('status') or r.get('Status') or 'Present',
                        'Shift': r.get('shift') or r.get('Shift') or '',
                        'Program': r.get('program') or r.get('Program') or '',
                        'Current_Year': int(r.get('current_year') or r.get('Current_Year') or 1),
                        'Admission_Year': int(r.get('admission_year') or r.get('Admission_Year') or 2025),
                        'Check_In_Time': '' if (r.get('check_in_time') == '0000-00-00 00:00:00' or not r.get('check_in_time')) else (r.get('check_in_time') or r.get('Check_In_Time') or ''),
                        'Check_Out_Time': '' if (r.get('check_out_time') == '0000-00-00 00:00:00' or not r.get('check_out_time')) else (r.get('check_out_time') or r.get('Check_Out_Time') or ''),
                        'Duration_Minutes': int(r.get('session_duration') or r.get('Duration_Minutes') or 0)
                    }
                    all_server_records.append(server_record)

                page += 1
                offset += ATT_PULL_PAGE_SIZE

                # Safety cap pages
                if page >= 20:
                    break

            if not all_server_records:
                print("📝 No server attendance to reconcile")
                return True

            # Reconcile into local CSV (server wins with timestamp comparison)
            local_df = _read_local_attendance_df()
            
            # Remove duplicates from local data first
            local_df = local_df.drop_duplicates(subset=['ID', 'Timestamp'], keep='first')
            
            # Create index for existing records
            local_index = {}
            for idx, row in local_df.iterrows():
                student_id = str(row['ID'])
                timestamp = str(row['Timestamp'])
                local_index[(student_id, timestamp)] = idx

            updated = 0
            added = 0
            conflicts_resolved = 0
            
            for server_record in all_server_records:
                sid = server_record['ID']
                ts = server_record['Timestamp']
                key = (sid, ts)
                
                if key in local_index:
                    idx = local_index[key]
                    local_record = local_df.iloc[idx]
                    
                    # Enhanced conflict resolution based on timestamp and status
                    try:
                        # Parse timestamps for comparison
                        server_ts = datetime.strptime(ts, '%Y-%m-%d %I:%M:%S %p')
                        local_ts = datetime.strptime(str(local_record['Timestamp']), '%Y-%m-%d %I:%M:%S %p')
                        
                        # Server wins if timestamp is newer OR if status is different
                        time_diff = abs((server_ts - local_ts).total_seconds())
                        should_update = (server_ts > local_ts) or (time_diff <= 5 and server_record['Status'] != local_record['Status'])
                        
                        if should_update:
                            for col in HEADERS:
                                if col in local_df.columns and col in server_record:
                                    local_df.at[idx, col] = server_record[col]
                            updated += 1
                            conflicts_resolved += 1
                            
                    except Exception as e:
                        # If timestamp parsing fails, update all fields
                        for col in HEADERS:
                            if col in local_df.columns and col in server_record:
                                local_df.at[idx, col] = server_record[col]
                        updated += 1
                else:
                    # Add new record
                    new_row = {col: server_record.get(col, '') for col in HEADERS}
                    local_df = pd.concat([local_df, pd.DataFrame([new_row])], ignore_index=True)
                    added += 1

            # Save reconciled data
            _safe_write_attendance_df(local_df)

            print(f"✅ Attendance pull complete: updated={updated}, added={added}, conflicts_resolved={conflicts_resolved}")
            return True

        except Exception as e:
            print(f"❌ Error pulling attendance: {e}")
            return False

def bidirectional_sync(quiet=False):
    """Perform bidirectional sync with enhanced error handling and reporting."""
    with sync_lock:
        if not quiet:
            print("🔄 Starting enhanced bidirectional sync...")
            print("=" * 50)
        
        sync_results = {
            'students_synced': False,
            'attendance_to_website': False,
            'attendance_from_website': False,
            'settings_synced': False
        }
        
        try:
            # Step 1: Sync students from website
            if not quiet:
                print("📥 Step 1: Syncing students from website...")
            sync_results['students_synced'] = sync_students_from_website(quiet=quiet)
            
            # Step 2: Sync attendance to website
            print("\n📤 Step 2: Syncing attendance to website...")
            sync_results['attendance_to_website'] = sync_attendance_to_website()

            # Step 3: Sync settings from website
            print("\n⚙️ Step 3: Syncing settings from website...")
            sync_results['settings_synced'] = sync_settings_from_website()

            # Step 4: Pull attendance from website
            print("\n📥 Step 4: Pulling attendance from website...")
            sync_results['attendance_from_website'] = sync_attendance_from_website()
            
            # Summary
            print("\n📊 Enhanced Sync Summary:")
            print(f"   Students sync: {'✅ Success' if sync_results['students_synced'] else '❌ Failed'}")
            print(f"   Attendance to website: {'✅ Success' if sync_results['attendance_to_website'] else '❌ Failed'}")
            print(f"   Settings from website: {'✅ Success' if sync_results['settings_synced'] else '❌ Failed'}")
            print(f"   Attendance from website: {'✅ Success' if sync_results['attendance_from_website'] else '❌ Failed'}")
            
            success_count = sum(sync_results.values())
            total_steps = len(sync_results)
            
            if success_count == total_steps:
                print(f"🎉 All {total_steps} sync steps completed successfully!")
                return True
            elif success_count > 0:
                print(f"⚠️ {success_count}/{total_steps} sync steps completed successfully")
                return True
            else:
                print("❌ All sync steps failed")
                return False
            
        except Exception as e:
            print(f"❌ Bidirectional sync failed: {e}")
            return False

def start_settings_sync():
    """Start dedicated settings sync service that runs every minute."""
    def settings_sync_worker():
        # Wait a bit before starting
        time.sleep(5)
        
        while True:
            try:
                # Sync settings from website
                success = sync_settings_from_website()
                if success:
                    print(f"✅ Settings auto-synced at {format_time()}")
                else:
                    print(f"⚠️ Settings auto-sync failed at {format_time()}")
                
                # Wait 60 seconds before next sync
                time.sleep(60)
                
            except Exception as e:
                print(f"❌ Settings sync error: {e}")
                time.sleep(60)
    
    settings_thread = threading.Thread(target=settings_sync_worker, daemon=True, name="SettingsSync")
    settings_thread.start()
    print(f"✅ Settings auto-sync service started (every 60 seconds)")

def start_bidirectional_sync():
    """Start bidirectional sync as background service with enhanced error handling."""
    def sync_worker():
        # Wait a bit before starting to let the main menu display first
        time.sleep(3)
        
        while True:
            try:
                # Try settings sync first (most important for user)
                settings_success = sync_settings_from_website()
                if settings_success:
                    print(f"✅ Settings synced at {format_time()}")
                else:
                    print(f"⚠️ Settings sync failed at {format_time()}")
                
                # Try other sync operations with timeout
                try:
                    # Use threading with timeout for other sync operations
                    result = [None]
                    exception = [None]
                    
                    def sync_worker_inner():
                        try:
                            result[0] = bidirectional_sync(quiet=True)
                        except Exception as e:
                            exception[0] = e
                    
                    sync_thread_inner = threading.Thread(target=sync_worker_inner, daemon=True)
                    sync_thread_inner.start()
                    sync_thread_inner.join(timeout=30)  # 30 second timeout
                    
                    if sync_thread_inner.is_alive():
                        print(f"⚠️ Background sync timed out at {format_time()}")
                    elif exception[0]:
                        print(f"⚠️ Background sync error at {format_time()}: {exception[0]}")
                    elif result[0] is not None and not result[0]:
                        print(f"⚠️ Background sync failed at {format_time()}")
                    
                except Exception as e:
                    print(f"⚠️ Background sync error at {format_time()}: {e}")
                
                # Wait 60 seconds before next sync
                time.sleep(60)
                
            except Exception as e:
                print(f"❌ Background sync error: {e}")
                time.sleep(60)
    
    sync_thread = threading.Thread(target=sync_worker, daemon=True, name="BidirectionalSync")
    sync_thread.start()
    print(f"✅ Enhanced background sync service started (every 60 seconds)")
    time.sleep(1)  # Small delay to let background services initialize

def manual_sync():
    """Manual sync function for testing or one-time sync."""
    print("🔄 Manual enhanced bidirectional sync...")
    
    try:
        # Use threading with timeout to prevent hanging
        import threading
        import time
        
        result = [None]
        exception = [None]
        
        def sync_worker():
            try:
                result[0] = bidirectional_sync(quiet=False)
            except Exception as e:
                exception[0] = e
        
        # Start sync in a separate thread
        sync_thread = threading.Thread(target=sync_worker, daemon=True)
        sync_thread.start()
        
        # Wait for completion with timeout
        sync_thread.join(timeout=60)  # 60 second timeout
        
        if sync_thread.is_alive():
            print("⏰ Sync operation timed out after 60 seconds")
            print("💡 This might be due to network issues or server problems")
            print("🔄 You can try again or check your internet connection")
            return False
        
        if exception[0]:
            print(f"❌ Manual sync failed: {exception[0]}")
            return False
        
        if result[0] is None:
            print("❌ Manual sync completed but returned no result")
            return False
        
        return result[0]
            
    except Exception as e:
        print(f"❌ Manual sync error: {e}")
        return False

def test_individual_sync():
    """Test individual sync operations to identify which one is causing issues."""
    print("🧪 Testing Individual Sync Operations")
    print("=" * 50)
    
    # Test 1: Check connections
    print("🔍 Step 1: Testing connections...")
    internet_ok = check_internet_connection()
    website_ok = check_website_connection()
    admin_ok = check_admin_panel_connection()
    
    print(f"   Internet: {'✅' if internet_ok else '❌'}")
    print(f"   Website: {'✅' if website_ok else '❌'}")
    print(f"   Admin Panel: {'✅' if admin_ok else '❌'}")
    
    if not internet_ok:
        print("❌ No internet connection - cannot proceed with sync tests")
        return
    
    # Test 2: Sync students
    print("\n🔍 Step 2: Testing student sync...")
    try:
        result = sync_students_from_website(quiet=False)
        print(f"   Student sync: {'✅' if result else '❌'}")
    except Exception as e:
        print(f"   Student sync: ❌ Error - {e}")
    
    # Test 3: Sync attendance to website
    print("\n🔍 Step 3: Testing attendance sync to website...")
    try:
        result = sync_attendance_to_website()
        print(f"   Attendance to website: {'✅' if result else '❌'}")
    except Exception as e:
        print(f"   Attendance to website: ❌ Error - {e}")
    
    # Test 4: Sync attendance from website
    print("\n🔍 Step 4: Testing attendance sync from website...")
    try:
        result = sync_attendance_from_website()
        print(f"   Attendance from website: {'✅' if result else '❌'}")
    except Exception as e:
        print(f"   Attendance from website: ❌ Error - {e}")
    
    print("\n✅ Individual sync tests completed")

# ============================================================================
# SETTINGS BIDIRECTIONAL SYNC OPERATIONS - ENHANCED
# ============================================================================

def sync_settings_from_website():
    """Sync settings from website to local settings.json with enhanced error handling."""
    with sync_lock:
        try:
            if not check_internet_connection():
                print("❌ No internet connection - cannot sync settings")
                return False
            
            if not check_website_connection():
                print("❌ Website not accessible - cannot sync settings")
                return False
            
            # API configuration for settings sync
            settings_api_url = settings.get('settings_api_url', f"{WEBSITE_URL}/api/settings_sync.php")
            settings_api_key = settings.get('settings_api_key', settings.get('api_key', API_KEY))
            
            print(f"🔄 Syncing settings from website...")
            print(f"   API URL: {settings_api_url}")
            
            # Fetch settings from API
            params = {
                'action': 'get_settings',
                'api_key': API_KEY  # Use the secure API key
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
                    
                    # Reload settings in memory
                    settings.force_reload_settings()
                    print("✅ Settings reloaded in memory")
                    
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
    """Sync local settings to website with enhanced error handling."""
    with sync_lock:
        try:
            if not check_internet_connection():
                print("❌ No internet connection - cannot sync settings")
                return False
            
            if not check_website_connection():
                print("❌ Website not accessible - cannot sync settings")
                return False
            
            # API configuration for settings sync
            settings_api_url = settings.get('settings_api_url', f"{WEBSITE_URL}/api/settings_sync.php")
            settings_api_key = settings.get('settings_api_key', settings.get('api_key', API_KEY))
            
            print(f"🔄 Syncing settings to website...")
            print(f"   API URL: {settings_api_url}")
            
            # Load local settings
            if not os.path.exists('settings.json'):
                print("❌ No local settings to sync")
                return False
            
            with open('settings.json', 'r', encoding='utf-8') as f:
                local_settings = json.load(f)
            
            # Send settings to API
            api_data = {
                'action': 'update_settings',
                'api_key': API_KEY,  # Use the secure API key
                'settings': local_settings
            }
            
            response = requests.post(settings_api_url, json=api_data, timeout=30, verify=False)
            
            if response.status_code == 200:
                result = response.json()
                if result.get('success', False):
                    print(f"✅ Successfully synced settings to website")
                    print(f"   Updated by: {result.get('updated_by', 'Unknown')}")
                    print(f"   Last updated: {result.get('last_updated', 'Unknown')}")
                    return True
                else:
                    print(f"❌ API returned error: {result.get('message', 'Unknown error')}")
                    return False
            else:
                print(f"❌ HTTP Error {response.status_code}: {response.text}")
                return False
                
        except Exception as e:
            print(f"❌ Error syncing settings: {e}")
            return False

# ============================================================================
# AUTOMATIC ABSENT MARKING - ENHANCED
# ============================================================================

def check_and_mark_automatic_absent():
    """Automatically mark students as absent who haven't checked in by the deadline with enhanced logic."""
    try:
        current_time = get_current_time()
        current_date = current_time.strftime("%Y-%m-%d")
        
        # Check if automatic absent marking is enabled
        if not settings.get('enable_automatic_absent', True):
            return
        
        # Get absent marking time from settings
        absent_time_str = settings.get('automatic_absent_time', '10:00 AM')
        
        try:
            # Parse the absent time
            absent_time = datetime.strptime(f"{current_date} {absent_time_str}", "%Y-%m-%d %I:%M %p")
            absent_time = TIMEZONE.localize(absent_time)
        except ValueError:
            # Try 24-hour format
            try:
                absent_time = datetime.strptime(f"{current_date} {absent_time_str}", "%Y-%m-%d %H:%M")
                absent_time = TIMEZONE.localize(absent_time)
            except:
                print(f"❌ Invalid automatic absent time format: {absent_time_str}")
                return
        
        # Check if current time is past the absent marking time
        if current_time < absent_time:
            return
        
        # Check if absent marking has already been done today
        if os.path.exists(CSV_FILE):
            df = pd.read_csv(CSV_FILE)
            today_absent = df[
                (df['Timestamp'].str.startswith(current_date)) & 
                (df['Status'] == 'Absent')
            ]
            if not today_absent.empty:
                return  # Already marked absent for today
        
        # Load students
        students = load_students()
        if not students:
            return
        
        # Load today's attendance
        today_attendance = []
        if os.path.exists(CSV_FILE):
            df = pd.read_csv(CSV_FILE)
            today_attendance = df[df['Timestamp'].str.startswith(current_date)]['ID'].tolist()
        
        # Mark absent for students who haven't checked in
        absent_marked = 0
        for student_id, student_info in students.items():
            if student_id not in today_attendance:
                # Mark student as absent
                name = student_info.get('name', 'Unknown')
                shift = get_shift(student_id)
                program = get_program(student_id)
                current_year, admission_year = get_academic_year_info(student_id)
                
                attendance_data = {
                    "ID": student_id,
                    "Name": name,
                    "Timestamp": format_time(absent_time),
                    "Status": "Absent",
                    "Shift": shift,
                    "Program": program,
                    "Current_Year": current_year,
                    "Admission_Year": admission_year,
                    "Check_In_Time": "",
                    "Check_Out_Time": "",
                    "Duration_Minutes": 0
                }
                
                # Save to CSV
                df = pd.read_csv(CSV_FILE) if os.path.exists(CSV_FILE) else pd.DataFrame(columns=HEADERS)
                df = pd.concat([df, pd.DataFrame([attendance_data])], ignore_index=True)
                df.to_csv(CSV_FILE, index=False)
                
                absent_marked += 1
                print(f"✅ Automatically marked {student_id} as absent")
        
        if absent_marked > 0:
            print(f"✅ Automatically marked {absent_marked} students as absent")
            
    except Exception as e:
        print(f"❌ Error in automatic absent marking: {e}")

# ============================================================================
# ATTENDANCE RECORDING - ENHANCED
# ============================================================================

def record_attendance(student_id, status="Present", check_in_time=None, check_out_time=None):
    """Record attendance with enhanced validation and duplicate prevention."""
    try:
        # Validate student exists
        students = load_students()
        if student_id not in students:
            print(f"❌ Student {student_id} not found in database")
            return False
        
        # Get student info
        name = students[student_id].get('name', 'Unknown')
        shift = get_shift(student_id)
        program = get_program(student_id)
        current_year, admission_year = get_academic_year_info(student_id)
        
        current_time = get_current_time()
        current_date = current_time.strftime("%Y-%m-%d")
        
        # Enhanced duplicate prevention
        if os.path.exists(CSV_FILE):
            df = pd.read_csv(CSV_FILE)
            
            # Check for existing records for this student today
            today_records = df[
                (df['ID'] == student_id) & 
                (df['Timestamp'].str.startswith(current_date))
            ]
            
            if not today_records.empty:
                # Check for same status within a short time window (5 minutes)
                for _, record in today_records.iterrows():
                    record_time_str = record['Timestamp']
                    try:
                        record_time = datetime.strptime(record_time_str, "%Y-%m-%d %I:%M:%S %p")
                        record_time = TIMEZONE.localize(record_time)
                        time_diff = abs((current_time - record_time).total_seconds())
                        
                        if time_diff < 300 and record['Status'] == status:  # 5 minutes
                            print(f"⚠️ Duplicate {status.lower()} detected for {student_id} within {time_diff:.0f} seconds")
                            return False
                    except:
                        pass
        
        # Calculate duration if both check-in and check-out times are provided
        duration_minutes = 0
        if check_in_time and check_out_time:
            try:
                check_in_dt = datetime.strptime(check_in_time, "%Y-%m-%d %I:%M:%S %p")
                check_out_dt = datetime.strptime(check_out_time, "%Y-%m-%d %I:%M:%S %p")
                duration_minutes = int((check_out_dt - check_in_dt).total_seconds() / 60)
            except:
                duration_minutes = 0
        
        # Prepare attendance data
        attendance_data = {
            "ID": student_id,
            "Name": name,
            "Timestamp": format_time(current_time),
            "Status": status,
            "Shift": shift,
            "Program": program,
            "Current_Year": current_year,
            "Admission_Year": admission_year,
            "Check_In_Time": check_in_time or "",
            "Check_Out_Time": check_out_time or "",
            "Duration_Minutes": duration_minutes
        }
        
        # Save to CSV
        df = pd.read_csv(CSV_FILE) if os.path.exists(CSV_FILE) else pd.DataFrame(columns=HEADERS)
        df = pd.concat([df, pd.DataFrame([attendance_data])], ignore_index=True)
        df.to_csv(CSV_FILE, index=False)
        
        print(f"✅ {status} recorded for {student_id} ({name})")
        
        # Try to sync to website immediately if online
        if check_internet_connection() and check_website_connection():
            try:
                api_data = {
                    "api_key": API_KEY,
                    "attendance_data": [attendance_data]
                }
                
                url = f"{WEBSITE_URL.rstrip('/')}/{API_ENDPOINT.lstrip('/')}"
                response = requests.post(url, json=api_data, timeout=10, verify=False)
                
                if response.status_code == 200:
                    result = response.json()
                    if result.get('success', False):
                        print(f"✅ {status} synced to website for {student_id}")
                    else:
                        print(f"⚠️ {status} saved locally but website sync failed: {result.get('message', 'Unknown error')}")
                        save_offline_data(attendance_data)
                else:
                    print(f"⚠️ {status} saved locally but website sync failed: {response.status_code}")
                    save_offline_data(attendance_data)
            except Exception as e:
                print(f"⚠️ {status} saved locally but website sync failed: {e}")
                save_offline_data(attendance_data)
        else:
            # Save offline for later sync
            save_offline_data(attendance_data)
        
        return True
        
    except Exception as e:
        print(f"❌ Error recording attendance: {e}")
        return False

def check_in_student(student_id):
    """Check in a student with enhanced validation."""
    try:
        # Get student shift and validate check-in time
        student_shift = get_student_shift(student_id)
        is_valid, error_message = validate_checkin_time_by_shift(student_shift)
        
        if not is_valid:
            print(f"❌ {error_message}")
            return False
        
        # Check if student is already checked in today
        current_status = get_student_status_for_date(student_id)
        if current_status['status'] == 'Checked in':
            print(f"⚠️ Student {student_id} is already checked in")
            return False
        elif current_status['status'] == 'Checked out':
            print(f"⚠️ Student {student_id} has already checked out today")
            return False
        
        # Record check-in
        current_time = format_time()
        success = record_attendance(
            student_id=student_id,
            status="Check-in",
            check_in_time=current_time,
            check_out_time=""
        )
        
        if success:
            print(f"✅ Check-in successful for {student_id}")
            return True
        else:
            print(f"❌ Check-in failed for {student_id}")
            return False
            
    except Exception as e:
        print(f"❌ Error during check-in: {e}")
        return False

def check_out_student(student_id):
    """Check out a student with enhanced validation."""
    try:
        # Get student shift and validate check-out time
        student_shift = get_student_shift(student_id)
        is_valid, error_message = validate_checkout_time_by_shift(student_shift)
        
        if not is_valid:
            print(f"❌ {error_message}")
            return False
        
        # Check if student is checked in
        current_status = get_student_status_for_date(student_id)
        if current_status['status'] != 'Checked in':
            print(f"❌ Student {student_id} is not checked in")
            return False
        
        # Record check-out
        current_time = format_time()
        success = record_attendance(
            student_id=student_id,
            status="Present",
            check_in_time=current_status['check_in_time'],
            check_out_time=current_time
        )
        
        if success:
            print(f"✅ Check-out successful for {student_id}")
            return True
        else:
            print(f"❌ Check-out failed for {student_id}")
            return False
            
    except Exception as e:
        print(f"❌ Error during check-out: {e}")
        return False

# ============================================================================
# MAIN APPLICATION - ENHANCED
# ============================================================================

def main():
    """Main application function with enhanced initialization."""
    print("🚀 Starting Enhanced QR Code Attendance System")
    print("=" * 50)
    
    # Initialize components
    print("📁 Initializing system components...")
    initialize_students()
    initialize_csv()
    
    # Load students
    students = load_students()
    print(f"📚 Loaded {len(students)} students")
    
    # Check connections
    print("\n🌐 Checking connections...")
    internet_status = "✅ Connected" if check_internet_connection() else "❌ No internet"
    website_status = "✅ Connected" if check_website_connection() else "❌ Not accessible"
    admin_status = "✅ Connected" if check_admin_panel_connection() else "❌ Not accessible"
    
    print(f"   Internet: {internet_status}")
    print(f"   Website: {website_status}")
    print(f"   Admin Panel: {admin_status}")
    
    # Start background services
    print("\n🔄 Starting background services...")
    start_background_sync()
    start_settings_sync()  # Start dedicated settings sync
    start_bidirectional_sync()
    
    print("\n🎯 System Ready!")
    print("=" * 50)
    
    # Small delay to let background services initialize
    time.sleep(2)
    
    # Main loop
    while True:
        try:
            print("\n📋 Options:")
            print("1. Check-in Student")
            print("2. Check-out Student")
            print("3. QR Code Scanner")
            print("4. Manual Sync")
            print("5. Sync Settings Only")
            print("6. View Today's Attendance")
            print("7. Exit")
            
            choice = input("\nEnter your choice (1-7): ").strip()
            
            if choice == "1":
                student_id = input("Enter Student ID: ").strip()
                if student_id in students:
                    check_in_student(student_id)
                else:
                    print(f"❌ Student {student_id} not found")
            
            elif choice == "2":
                student_id = input("Enter Student ID: ").strip()
                if student_id in students:
                    check_out_student(student_id)
                else:
                    print(f"❌ Student {student_id} not found")
            
            elif choice == "3":
                print("📷 Starting QR Code Scanner...")
                qr_scanner = QRScanner()
                try:
                    qr_scanner.scan_qr_codes(display_window=True)
                except Exception as e:
                    print(f"❌ QR Scanner error: {e}")
                finally:
                    qr_scanner.stop_scanning()
            
            elif choice == "4":
                print("🔄 Starting manual sync...")
                print("📋 Sync Options:")
                print("   a) Full bidirectional sync")
                print("   b) Test individual sync operations")
                print("   c) Cancel")
                
                sync_choice = input("Choose sync option (a/b/c): ").strip().lower()
                
                if sync_choice == "a":
                    manual_sync()
                elif sync_choice == "b":
                    test_individual_sync()
                elif sync_choice == "c":
                    print("❌ Sync cancelled")
                else:
                    print("❌ Invalid choice")
            
            elif choice == "5":
                print("⚙️ Syncing settings from website...")
                success = sync_settings_from_website()
                if success:
                    print("✅ Settings synced successfully!")
                else:
                    print("❌ Settings sync failed")
            
            elif choice == "6":
                if os.path.exists(CSV_FILE):
                    df = pd.read_csv(CSV_FILE)
                    today = get_current_time().strftime("%Y-%m-%d")
                    today_records = df[df['Timestamp'].str.startswith(today)]
                    print(f"\n📊 Today's Attendance ({today}):")
                    print(today_records.to_string(index=False))
                else:
                    print("📊 No attendance records found")
            
            elif choice == "7":
                print("👋 Exiting...")
                break
            
            else:
                print("❌ Invalid choice")
                
        except KeyboardInterrupt:
            print("\n👋 Exiting...")
            break
        except Exception as e:
            print(f"❌ Error in main loop: {e}")

if __name__ == "__main__":
    main()
    