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
from year_progression import YearProgression, check_and_update_years

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

def get_current_time():
    """Get current time in Asia/Karachi timezone."""
    return datetime.now(TIMEZONE)

def format_time(dt=None):
    """Format datetime in Asia/Karachi timezone."""
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
        print(f"Using existing attendance file: {CSV_FILE}")

def load_students():
    """Load student data from JSON file."""
    with open(STUDENTS_FILE, 'r') as f:
        return json.load(f)

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
        url = urljoin(WEBSITE_URL, API_ENDPOINT)
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
    
    if student_id not in students:
        print(f"Student {student_id} not found in database!")
        return False
    
    student_name = students[student_id]["name"]
    
    # Validate check-in time based on shift
    time_validator = TimeValidator()
    time_validation = time_validator.validate_checkin_time(student_id, None, roll_data['shift'])
    
    if not time_validation['valid']:
        print(f"CHECK-IN DENIED: {student_name} ({student_id}) - {time_validation['error']}")
        print(f"  Shift: {roll_data['shift']}")
        print(f"  Allowed Window: {time_validation['checkin_start']} - {time_validation['checkin_end']}")
        print(f"  Current Time: {time_validation['current_time']}")
        return False
    
    # Initialize check-in manager
    checkin_manager = CheckInManager()
    
    # Process QR scan (handles check-in/check-out logic)
    success, result = checkin_manager.process_qr_scan(student_id)
    
    if success:
        # Get the action performed
        action = result.get('status', 'Unknown')
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
    else:
        print(f"FAILED: {student_name} ({student_id}) - {result}")
        return False

def mark_absent_students():
    """Mark all students as absent for current date."""
    students = load_students()
    current_date = get_current_time().strftime("%Y-%m-%d")
    
    # Get students who attended today
    df = pd.read_csv(CSV_FILE)
    if not df.empty:
        today_attended = df[
            (df['Timestamp'].str.startswith(current_date)) & 
            (df['Status'] == 'Present')
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
                "Timestamp": [datetime.now().strftime("%Y-%m-%d %H:%M:%S")],
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
    print(f"Shift Start: {shift_start}")
    print(f"Absent Deadline: {absent_deadline.strftime('%H:%M:%S')}")
    print(f"Current Time: {current_time.strftime('%H:%M:%S')}")
    
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
            "Timestamp": [current_time.strftime("%Y-%m-%d %H:%M:%S")],
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
    total_classes = len(filtered_df[filtered_df['Status'].isin(['Present', 'Absent'])])
    present_count = len(filtered_df[filtered_df['Status'] == 'Present'])
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
    present_count = len(df[df['Status'] == 'Present'])
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
                    timestamp = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
                    
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
    
    print(f"\nConnection Status:")
    print(f"   Internet: {'ONLINE' if internet_status else 'OFFLINE'}")
    print(f"   Website: {'ONLINE' if website_status else 'OFFLINE'}")
    print(f"   Website URL: {WEBSITE_URL}")
    print(f"   Note: Website API is read-only (GET only)")
    
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
    
    print("\nAvailable Commands:")
    print("  • Scan QR code: Just scan the student's QR code")
    print("  • 'manual': Manual attendance entry (no scanner needed)")
    print("  • 'mark_absent': Mark absent students")
    print("  • 'report [student_id]': Show attendance report")
    print("  • 'summary': Show overall attendance summary")
    print("  • 'end_class': End class and mark absent students")
    print("  • 'sync_now': Manually sync data to website")
    print("  • 'sync_from_web': Sync data from website to local")
    print("  • 'bidirectional_sync': Full bidirectional sync")
    print("  • 'enhanced_sync': Enhanced sync with admin panel")
    print("  • 'status': Check connection and sync status")
    print("  • 'parse_roll [roll_number]': Parse and display roll number info")
    print("  • 'check_time [student_id]': Check if student can check in now")
    print("  • 'progression_status': Check year progression status")
    print("  • 'update_years': Manually trigger year progression")
    print("  • 'shift_schedule [shift]': Show shift timing schedule")
    print("  • 'auto_absent [shift]': Manually trigger automatic absent marking")
    print("  • 'check_auto_absent': Check if automatic absent marking should run")
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
            elif user_input.startswith('report'):
                parts = user_input.split()
                student_id = parts[1] if len(parts) > 1 else None
                calculate_attendance_percentage(student_id)
            elif user_input.lower() == 'end_class':
                print(f"Ending class...")
                mark_absent_students()
                print(f"Class ended")
            elif user_input.lower() == 'sync_now':
                print("Attempting to sync data to website...")
                if sync_manager.sync_to_website():
                    print("Sync completed successfully!")
                else:
                    print("Sync failed - data saved offline")
            elif user_input.lower() == 'sync_from_web':
                print("Attempting to sync data from website...")
                if sync_manager.sync_from_website():
                    print("Sync from website completed successfully!")
                else:
                    print("Sync from website failed")
            elif user_input.lower() == 'bidirectional_sync':
                print("Starting bidirectional sync...")
                sync_manager.bidirectional_sync()
            elif user_input.lower() == 'enhanced_sync':
                print("Starting enhanced sync with admin panel...")
                sync_manager.enhanced_bidirectional_sync()
            elif user_input.lower() == 'status':
                # Get sync manager status
                sync_status = sync_manager.get_sync_status()
                
                print(f"\nSYSTEM STATUS")
                print(f"{'='*60}")
                print(f"Internet Connection: {'ONLINE' if sync_status['internet'] else 'OFFLINE'}")
                print(f"Website Status: {'ONLINE' if sync_status['website'] else 'OFFLINE'}")
                print(f"Website URL: {WEBSITE_URL}")
                print(f"Sync Interval: {SYNC_INTERVAL} seconds")
                print(f"Currently Syncing: {'YES' if sync_status['is_syncing'] else 'NO'}")
                print(f"Offline Records: {sync_status['offline_records']}")
                print(f"Local Records: {sync_status['local_records']}")
                
                # Check CSV data
                try:
                    df = pd.read_csv(CSV_FILE)
                    print(f"Total Attendance Records: {len(df)}")
                except:
                    print("Total Attendance Records: 0")
                
                print(f"{'='*60}\n")
            elif user_input.startswith('parse_roll'):
                parts = user_input.split()
                if len(parts) > 1:
                    roll_number = parts[1]
                    roll_data = parse_roll_number(roll_number)
                    if roll_data['valid']:
                        # Get academic year info for current_year
                        from roll_parser import get_academic_year_info
                        academic_info = get_academic_year_info(roll_number)
                        
                        print(f"\nROLL NUMBER ANALYSIS")
                        print(f"{'='*50}")
                        print(f"Roll Number: {roll_data['roll_number']}")
                        print(f"Admission Year: {roll_data['admission_year']}")
                        if academic_info['valid']:
                            print(f"Current Year: {academic_info['current_year']}")
                            print(f"Graduated: {'Yes' if academic_info['is_graduated'] else 'No'}")
                            print(f"Years Remaining: {academic_info['years_remaining']}")
                        else:
                            print(f"Current Year: Error - {academic_info.get('error', 'Unknown')}")
                        print(f"Shift: {roll_data['shift']}")
                        print(f"Program: {roll_data['program']}")
                        print(f"Sequence: {roll_data['sequence_number']}")
                        print(f"Evening Shift: {'Yes' if roll_data['is_evening'] else 'No'}")
                        print(f"{'='*50}\n")
                    else:
                        print(f"Invalid roll number: {roll_data['error']}")
                else:
                    print("Usage: parse_roll [roll_number]")
            elif user_input.startswith('check_time'):
                parts = user_input.split()
                if len(parts) > 1:
                    student_id = parts[1]
                    time_validator = TimeValidator()
                    time_validation = time_validator.validate_checkin_time(student_id)
                    print(f"\nTIME VALIDATION")
                    print(f"{'='*50}")
                    print(f"Student ID: {student_id}")
                    print(f"Valid: {'Yes' if time_validation['valid'] else 'No'}")
                    if not time_validation['valid']:
                        print(f"Error: {time_validation['error']}")
                    print(f"Shift: {time_validation['shift']}")
                    print(f"Current Time: {time_validation['current_time']}")
                    print(f"Check-in Window: {time_validation['checkin_start']} - {time_validation['checkin_end']}")
                    print(f"Class Ends: {time_validation['class_end']}")
                    if time_validation.get('time_until_close'):
                        print(f"Time Until Close: {time_validation['time_until_close']} minutes")
                    print(f"{'='*50}\n")
                else:
                    print("Usage: check_time [student_id]")
            elif user_input.lower() == 'progression_status':
                progression = YearProgression()
                status = progression.get_progression_status()
                if status['success']:
                    print(f"\nYEAR PROGRESSION STATUS")
                    print(f"{'='*50}")
                    print(f"Current Date: {status['current_date']}")
                    print(f"Academic Year: {status['academic_year']}")
                    print(f"Should Progress: {'Yes' if status['should_progress'] else 'No'}")
                    print(f"Total Students: {status['total_students']}")
                    print(f"Year Distribution: {status['year_distribution']}")
                    print(f"Graduated: {status['graduated_count']}")
                    print(f"Needs Update: {status['needs_update']}")
                    print(f"{'='*50}\n")
                else:
                    print(f"Error getting progression status: {status['error']}")
            elif user_input.lower() == 'update_years':
                print("Triggering year progression...")
                result = check_and_update_years()
                if result['success']:
                    print(f"Year progression completed:")
                    print(f"  Message: {result['message']}")
                    print(f"  Updated Students: {result.get('updated_students', 0)}")
                    print(f"  Graduated Students: {result.get('graduated_students', 0)}")
                    print(f"  Errors: {result.get('errors', 0)}")
                else:
                    print(f"Year progression failed: {result['error']}")
            elif user_input.startswith('shift_schedule'):
                parts = user_input.split()
                shift = parts[1] if len(parts) > 1 else 'Morning'
                time_validator = TimeValidator()
                schedule = time_validator.get_shift_schedule(shift)
                print(f"\n{shift.upper()} SHIFT SCHEDULE")
                print(f"{'='*50}")
                print(f"Shift: {schedule['shift']}")
                print(f"Check-in Window: {schedule['schedule']['checkin_window']['start']} - {schedule['schedule']['checkin_window']['end']}")
                print(f"Class Session: {schedule['schedule']['class_session']['start']} - {schedule['schedule']['class_session']['end']}")
                print(f"Minimum Duration: {schedule['schedule']['minimum_duration']['minutes']} minutes")
                print(f"Description: {schedule['description']}")
                print(f"{'='*50}\n")
            elif user_input.startswith('auto_absent'):
                parts = user_input.split()
                if len(parts) > 1:
                    shift = parts[1]
                    if shift.lower() in ['morning', 'evening']:
                        print(f"Manually triggering automatic absent marking for {shift} shift...")
                        count = mark_absent_for_shift(shift)
                        print(f"Marked {count} students as absent for {shift} shift")
                    else:
                        print("Invalid shift. Use 'morning' or 'evening'")
                else:
                    print("Usage: auto_absent [morning|evening]")
            elif user_input.lower() == 'check_auto_absent':
                print("Checking automatic absent marking...")
                current_time = get_current_time()
                print(f"Current Time: {current_time.strftime('%Y-%m-%d %H:%M:%S')}")
                
                # Check morning shift (11:00 AM)
                morning_deadline = current_time.replace(hour=11, minute=0, second=0, microsecond=0)
                evening_deadline = current_time.replace(hour=17, minute=0, second=0, microsecond=0)
                
                print(f"Morning shift absent deadline: {morning_deadline.strftime('%H:%M:%S')}")
                print(f"Evening shift absent deadline: {evening_deadline.strftime('%H:%M:%S')}")
                
                if current_time >= morning_deadline:
                    print("✓ Morning shift absent marking should have run")
                else:
                    print("⏳ Morning shift absent marking not yet due")
                    
                if current_time >= evening_deadline:
                    print("✓ Evening shift absent marking should have run")
                else:
                    print("⏳ Evening shift absent marking not yet due")
                
                # Trigger check
                count = check_and_mark_automatic_absent()
                if count > 0:
                    print(f"Marked {count} students as absent")
                else:
                    print("No automatic absent marking needed at this time")
            else:
                # Treat as QR code scan
                log_attendance(user_input, sync_manager)
            
    except KeyboardInterrupt:
        print("\n\nAttendance system stopped. Goodbye!")
        sys.exit(0)
    except Exception as e:
        print(f"\nError: {e}")
        sys.exit(1)

if __name__ == "__main__":
    main()