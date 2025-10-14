#!/usr/bin/env python3
"""
Advanced Sync Manager for QR Code Attendance System
Handles bidirectional synchronization between local and web data
"""

import pandas as pd
import json
import os
import requests
import sqlite3
from datetime import datetime, timedelta
import time
import threading
from urllib.parse import urljoin
import urllib3
import pytz
from settings import SettingsManager

# Disable SSL warnings
urllib3.disable_warnings(urllib3.exceptions.InsecureRequestWarning)

class SyncManager:
    def __init__(self):
        # Initialize settings manager
        self.settings = SettingsManager()
        
        # Load configuration from settings
        self.CSV_FILE = "attendance.csv"
        self.STUDENTS_FILE = "students.json"
        self.OFFLINE_FILE = "offline_data.json"
        self.LOCAL_DB = "attendance_local.db"
        self.SYNC_DATA_FILE = "sync_data.json"
        self.WEBSITE_URL = self.settings.get('website_url', 'http://localhost/qr_attendance/public')
        self.API_ENDPOINT = self.settings.get('api_endpoint_attendance', '/api/api_attendance.php')
        self.DASHBOARD_API = "/dashboard_api.php"
        self.ADMIN_API = "/admin_api.php"
        self.API_KEY = self.settings.get('api_key', 'attendance_2025_xyz789_secure')
        self.SYNC_INTERVAL = self.settings.get('sync_interval_seconds', 30)
        self.is_syncing = False
        self.timezone = pytz.timezone(self.settings.get('timezone', 'Asia/Karachi'))
    
    def get_current_time(self):
        """Get current time in Asia/Karachi timezone."""
        return datetime.now(self.timezone)
    
    def format_time(self, dt=None):
        """Format datetime in Asia/Karachi timezone."""
        if dt is None:
            dt = self.get_current_time()
        return dt.strftime("%Y-%m-%d %H:%M:%S")
        
    def check_internet_connection(self):
        """Check if internet connection is available."""
        try:
            response = requests.get("https://www.google.com", timeout=3, verify=False)
            return response.status_code == 200
        except:
            return False
    
    def check_website_connection(self):
        """Check if the website is accessible."""
        try:
            # Try the main website first
            response = requests.get(self.WEBSITE_URL, timeout=5, verify=False)
            if response.status_code == 200:
                return True
            
            # Try the API endpoint directly
            api_url = self.WEBSITE_URL + self.API_ENDPOINT
            response = requests.get(api_url, timeout=5, verify=False)
            return response.status_code in [200, 404, 405]  # 404/405 means server is running but endpoint might not exist
        except Exception as e:
            print(f"Website connection check failed: {e}")
            return False
    
    def load_local_data(self):
        """Load data from local CSV file."""
        try:
            if os.path.exists(self.CSV_FILE):
                df = pd.read_csv(self.CSV_FILE)
                return df.to_dict('records')
            return []
        except Exception as e:
            print(f"Error loading local data: {e}")
            return []
    
    def load_offline_data(self):
        """Load offline data."""
        try:
            if os.path.exists(self.OFFLINE_FILE):
                with open(self.OFFLINE_FILE, 'r') as f:
                    return json.load(f)
            return []
        except Exception as e:
            print(f"Error loading offline data: {e}")
            return []
    
    def save_offline_data(self, data):
        """Save data to offline storage."""
        try:
            offline_data = self.load_offline_data()
            if isinstance(data, list):
                offline_data.extend(data)
            else:
                offline_data.append(data)
            
            with open(self.OFFLINE_FILE, 'w') as f:
                json.dump(offline_data, f, indent=2)
            
            print(f"Saved {len(data) if isinstance(data, list) else 1} records to offline storage")
            return True
        except Exception as e:
            print(f"Error saving offline data: {e}")
            return False
    
    def sync_to_website(self):
        """Sync local data to website."""
        if not self.check_internet_connection() or not self.check_website_connection():
            return False
        
        try:
            # Load offline data
            offline_data = self.load_offline_data()
            if not offline_data:
                return True
            
            # Check if website API supports POST requests
            url = self.WEBSITE_URL + self.API_ENDPOINT
            test_response = requests.post(url, json={"test": "data"}, timeout=5, verify=False)
            
            if test_response.status_code == 405:
                print("Website API is read-only. Data will be saved locally only.")
                return False
            
            # Prepare API data
            api_data = {
                "api_key": self.API_KEY,
                "attendance_data": offline_data
            }
            
            # Send to website
            response = requests.post(url, json=api_data, timeout=10, verify=False)
            
            if response.status_code == 200:
                # Clear offline data after successful sync
                if os.path.exists(self.OFFLINE_FILE):
                    os.remove(self.OFFLINE_FILE)
                print(f"Successfully synced {len(offline_data)} records to website")
                return True
            else:
                print(f"Sync failed: {response.status_code} - {response.text}")
                return False
                
        except Exception as e:
            print(f"Sync error: {e}")
            return False
    
    def sync_from_website(self):
        """Sync data from website to local storage."""
        if not self.check_internet_connection() or not self.check_website_connection():
            return False
        
        try:
            # Get data from website
            url = self.WEBSITE_URL + self.API_ENDPOINT
            response = requests.get(url, timeout=10, verify=False)
            
            if response.status_code == 200:
                data = response.json()
                if data.get('success') and data.get('data'):
                    # Update local CSV with website data
                    website_data = data['data']
                    self.update_local_csv(website_data)
                    print(f"Successfully synced {len(website_data)} records from website")
                    return True
            else:
                print(f"Failed to fetch from website: {response.status_code}")
                return False
                
        except Exception as e:
            print(f"Error syncing from website: {e}")
            return False
    
    def update_local_csv(self, website_data):
        """Update local CSV with website data."""
        try:
            # Load existing local data
            local_data = self.load_local_data()
            
            # Create a set of existing records for deduplication
            existing_records = set()
            for record in local_data:
                key = f"{record['ID']}_{record['Timestamp']}_{record['Status']}"
                existing_records.add(key)
            
            # Add new records from website
            new_records = []
            for record in website_data:
                key = f"{record['student_id']}_{record['timestamp']}_{record['status']}"
                if key not in existing_records:
                    new_record = {
                        'ID': record['student_id'],
                        'Name': record['student_name'],
                        'Timestamp': record['timestamp'],
                        'Status': record['status']
                    }
                    new_records.append(new_record)
                    existing_records.add(key)
            
            # Combine and save
            if new_records:
                all_data = local_data + new_records
                df = pd.DataFrame(all_data)
                df.to_csv(self.CSV_FILE, index=False)
                print(f"Added {len(new_records)} new records to local CSV")
            
        except Exception as e:
            print(f"Error updating local CSV: {e}")
    
    def bidirectional_sync(self):
        """Perform bidirectional synchronization."""
        if self.is_syncing:
            return
        
        self.is_syncing = True
        try:
            print("Starting bidirectional sync...")
            
            # Sync local data to website
            if self.sync_to_website():
                print("+ Local to website sync completed")
            
            # Sync website data to local
            if self.sync_from_website():
                print("+ Website to local sync completed")
            
            print("Bidirectional sync completed successfully")
            
        except Exception as e:
            print(f"Bidirectional sync error: {e}")
        finally:
            self.is_syncing = False
    
    def start_auto_sync(self):
        """Start automatic synchronization."""
        def sync_loop():
            while True:
                try:
                    if self.check_internet_connection() and self.check_website_connection():
                        self.bidirectional_sync()
                    time.sleep(self.SYNC_INTERVAL)
                except Exception as e:
                    print(f"Auto sync error: {e}")
                    time.sleep(60)
        
        sync_thread = threading.Thread(target=sync_loop, daemon=True)
        sync_thread.start()
        print(f"Auto sync started (every {self.SYNC_INTERVAL} seconds)")
    
    def get_sync_status(self):
        """Get current synchronization status."""
        status = {
            'internet': self.check_internet_connection(),
            'website': self.check_website_connection(),
            'offline_records': len(self.load_offline_data()),
            'local_records': len(self.load_local_data()),
            'is_syncing': self.is_syncing
        }
        return status
    
    def force_sync(self):
        """Force immediate synchronization."""
        print("Forcing immediate sync...")
        self.bidirectional_sync()
    
    def check_sync_data(self):
        """Check for sync data from admin panel."""
        try:
            if os.path.exists(self.SYNC_DATA_FILE):
                with open(self.SYNC_DATA_FILE, 'r') as f:
                    sync_data = json.load(f)
                return sync_data
            return None
        except Exception as e:
            print(f"Error reading sync data: {e}")
            return None
    
    def apply_admin_changes(self):
        """Apply changes from admin panel to local system."""
        try:
            sync_data = self.check_sync_data()
            if not sync_data:
                return False
            
            print("Applying admin panel changes to local system...")
            
            # Update students.json
            if 'students' in sync_data:
                with open(self.STUDENTS_FILE, 'w') as f:
                    students_dict = {}
                    for student in sync_data['students']:
                        students_dict[student['student_id']] = {
                            'name': student['name'],
                            'email': student.get('email', ''),
                            'phone': student.get('phone', '')
                        }
                    json.dump(students_dict, f, indent=2)
                print(f"Updated {len(sync_data['students'])} students")
            
            # Update attendance.csv
            if 'attendance' in sync_data:
                import pandas as pd
                attendance_data = []
                for record in sync_data['attendance']:
                    attendance_data.append({
                        'ID': record['student_id'],
                        'Name': record['student_name'],
                        'Timestamp': record['timestamp'],
                        'Status': record['status']
                    })
                
                if attendance_data:
                    df = pd.DataFrame(attendance_data)
                    df.to_csv(self.CSV_FILE, index=False)
                    print(f"Updated {len(attendance_data)} attendance records")
            
            # Remove sync data file after processing
            if os.path.exists(self.SYNC_DATA_FILE):
                os.remove(self.SYNC_DATA_FILE)
            
            print("Admin changes applied successfully")
            return True
            
        except Exception as e:
            print(f"Error applying admin changes: {e}")
            return False
    
    def push_to_admin(self):
        """Push local data to admin panel."""
        try:
            if not self.check_internet_connection() or not self.check_website_connection():
                return False
            
            # Prepare local data
            local_data = self.load_local_data()
            students_data = self.load_students()
            
            # Convert students to admin format
            admin_students = []
            for student_id, info in students_data.items():
                admin_students.append({
                    'student_id': student_id,
                    'name': info['name'],
                    'email': info.get('email', ''),
                    'phone': info.get('phone', '')
                })
            
            # Prepare sync data
            sync_data = {
                'timestamp': datetime.now().isoformat(),
                'students': admin_students,
                'attendance': local_data
            }
            
            # Save sync data file
            with open(self.SYNC_DATA_FILE, 'w') as f:
                json.dump(sync_data, f, indent=2)
            
            print("Local data prepared for admin panel sync")
            return True
            
        except Exception as e:
            print(f"Error pushing to admin: {e}")
            return False
    
    def load_students(self):
        """Load students from JSON file."""
        try:
            if os.path.exists(self.STUDENTS_FILE):
                with open(self.STUDENTS_FILE, 'r') as f:
                    return json.load(f)
            return {}
        except Exception as e:
            print(f"Error loading students: {e}")
            return {}
    
    def enhanced_bidirectional_sync(self):
        """Enhanced bidirectional sync with admin panel support."""
        if self.is_syncing:
            return
        
        self.is_syncing = True
        sync_start_time = time.time()
        sync_log = {
            'sync_type': 'bidirectional',
            'status': 'success',
            'records_processed': 0,
            'records_failed': 0,
            'error_message': None,
            'sync_duration': 0,
            'ip_address': self.get_client_ip(),
            'user_agent': 'Python Sync Manager'
        }
        
        try:
            print("Starting enhanced bidirectional sync...")
            
            # Check for admin changes first
            admin_changes_applied = 0
            if self.apply_admin_changes():
                print("+ Admin changes applied")
                admin_changes_applied = 1
            
            # Sync local data to website
            local_to_web_success = False
            local_to_web_records = 0
            if self.sync_to_website():
                print("✓ Local to website sync completed")
                local_to_web_success = True
                local_to_web_records = len(self.load_offline_data())
            
            # Sync website data to local
            web_to_local_success = False
            web_to_local_records = 0
            if self.sync_from_website():
                print("✓ Website to local sync completed")
                web_to_local_success = True
                web_to_local_records = len(self.load_local_data())
            
            # Push local data to admin panel
            admin_push_success = False
            if self.push_to_admin():
                print("✓ Local data pushed to admin panel")
                admin_push_success = True
            
            # Calculate sync metrics
            total_records = admin_changes_applied + local_to_web_records + web_to_local_records
            sync_log['records_processed'] = total_records
            sync_log['sync_duration'] = round(time.time() - sync_start_time, 3)
            
            # Log successful sync
            self.log_sync_activity(sync_log)
            
            print(f"Enhanced bidirectional sync completed successfully - {total_records} records processed in {sync_log['sync_duration']}s")
            
        except Exception as e:
            sync_log['status'] = 'failed'
            sync_log['error_message'] = str(e)
            sync_log['sync_duration'] = round(time.time() - sync_start_time, 3)
            self.log_sync_activity(sync_log)
            print(f"Enhanced sync error: {e}")
        finally:
            self.is_syncing = False
    
    def get_client_ip(self):
        """Get client IP address."""
        try:
            import socket
            s = socket.socket(socket.AF_INET, socket.SOCK_DGRAM)
            s.connect(("8.8.8.8", 80))
            ip = s.getsockname()[0]
            s.close()
            return ip
        except:
            return "unknown"
    
    def log_sync_activity(self, sync_log):
        """Log sync activity to database."""
        try:
            if not self.check_website_connection():
                return False
            
            import requests
            url = f"{self.WEBSITE_URL}/api/sync_api.php"
            response = requests.post(url, json={
                'action': 'log_sync',
                'sync_data': sync_log
            }, timeout=10, verify=False)
            
            return response.status_code == 200
        except Exception as e:
            print(f"Failed to log sync activity: {e}")
            return False

def main():
    """Test the sync manager."""
    sync_manager = SyncManager()
    
    print("=" * 60)
    print("QR Code Attendance System - Sync Manager")
    print("=" * 60)
    
    # Check status
    status = sync_manager.get_sync_status()
    print(f"Internet Connection: {'ONLINE' if status['internet'] else 'OFFLINE'}")
    print(f"Website Connection: {'ONLINE' if status['website'] else 'OFFLINE'}")
    print(f"Offline Records: {status['offline_records']}")
    print(f"Local Records: {status['local_records']}")
    print(f"Currently Syncing: {'YES' if status['is_syncing'] else 'NO'}")
    
    # Start auto sync
    sync_manager.start_auto_sync()
    
    print("\nAvailable Commands:")
    print("  • 'sync': Force immediate sync")
    print("  • 'status': Check sync status")
    print("  • 'quit': Exit")
    
    try:
        while True:
            user_input = input("\nEnter command: ").strip().lower()
            
            if user_input == 'quit':
                break
            elif user_input == 'sync':
                sync_manager.force_sync()
            elif user_input == 'status':
                status = sync_manager.get_sync_status()
                print(f"\nSYNC STATUS")
                print(f"{'='*40}")
                print(f"Internet: {'ONLINE' if status['internet'] else 'OFFLINE'}")
                print(f"Website: {'ONLINE' if status['website'] else 'OFFLINE'}")
                print(f"Offline Records: {status['offline_records']}")
                print(f"Local Records: {status['local_records']}")
                print(f"Syncing: {'YES' if status['is_syncing'] else 'NO'}")
                print(f"{'='*40}")
            else:
                print("Invalid command!")
                
    except KeyboardInterrupt:
        print("\nSync manager stopped.")

if __name__ == "__main__":
    main()
