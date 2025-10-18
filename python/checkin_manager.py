#!/usr/bin/env python3
"""
Check-in/Check-out Manager for QR Code Attendance System
Handles check-in and check-out operations with time validation
"""

import requests
import json
import time
from datetime import datetime, timedelta
import urllib3
import pytz
from settings import SettingsManager

# Disable SSL warnings
urllib3.disable_warnings(urllib3.exceptions.InsecureRequestWarning)

class CheckInManager:
    def __init__(self, base_url=None):
        # Initialize settings manager
        self.settings = SettingsManager()
        
        # Load configuration from settings
        self.base_url = base_url or self.settings.get('website_url', 'http://localhost/qr_attendance/public')
        self.checkin_api = f"{self.base_url}{self.settings.get('api_endpoint_checkin', '/api/checkin_api.php')}"
        self.session = requests.Session()
        self.session.verify = False
        self.timezone = pytz.timezone(self.settings.get('timezone', 'Asia/Karachi'))
    
    def get_current_time(self):
        """Get current time in Asia/Karachi timezone."""
        return datetime.now(self.timezone)
    
    def format_time(self, dt=None):
        """Format datetime in Asia/Karachi timezone."""
        if dt is None:
            dt = self.get_current_time()
        return dt.strftime("%Y-%m-%d %H:%M:%S")
        
    def check_in_student(self, student_id):
        """Check in a student"""
        try:
            data = {
                'action': 'check_in',
                'student_id': student_id
            }
            
            response = self.session.post(self.checkin_api, json=data, timeout=10)
            
            if response.status_code == 200:
                result = response.json()
                if result.get('success'):
                    print(f"+ Check-in successful for {student_id}")
                    return True, result.get('data', {})
                else:
                    print(f"- Check-in failed: {result.get('message')}")
                    return False, result.get('message', 'Unknown error')
            else:
                print(f"- HTTP Error: {response.status_code}")
                return False, f"HTTP Error: {response.status_code}"
                
        except Exception as e:
            print(f"- Check-in error: {e}")
            return False, str(e)
    
    def check_out_student(self, student_id):
        """Check out a student"""
        try:
            data = {
                'action': 'check_out',
                'student_id': student_id
            }
            
            response = self.session.post(self.checkin_api, json=data, timeout=10)
            
            if response.status_code == 200:
                result = response.json()
                if result.get('success'):
                    print(f"+ Check-out successful for {student_id}")
                    return True, result.get('data', {})
                else:
                    print(f"- Check-out failed: {result.get('message')}")
                    return False, result.get('message', 'Unknown error')
            else:
                print(f"- HTTP Error: {response.status_code}")
                return False, f"HTTP Error: {response.status_code}"
                
        except Exception as e:
            print(f"- Check-out error: {e}")
            return False, str(e)
    
    def get_student_status(self, student_id):
        """Get current status of a student from server database"""
        try:
            # Call PHP API to get real server status
            data = {
                'action': 'get_status',
                'student_id': student_id
            }
            
            response = self.session.post(self.checkin_api, json=data, timeout=10)
            
            if response.status_code == 200:
                result = response.json()
                if result.get('success'):
                    server_data = result.get('data', {})
                    return True, {
                        'status': server_data.get('status', 'Not checked in'),
                        'student_id': student_id,
                        'name': server_data.get('student_name', 'Unknown'),
                        'shift': server_data.get('shift', 'Unknown'),
                        'program': server_data.get('program', 'Unknown'),
                        'check_in_time': server_data.get('check_in_time'),
                        'check_out_time': server_data.get('check_out_time')
                    }
                else:
                    return False, result.get('message', 'Unknown error')
            else:
                return False, f"HTTP Error: {response.status_code}"
                
        except Exception as e:
            return False, str(e)
    
    def process_qr_scan(self, student_id):
        """Process QR code scan and determine appropriate action"""
        try:
            # Import time validator for checkout validation
            from time_validator import TimeValidator, validate_checkout_time
            from roll_parser import parse_roll_number
            
            # First, get current status
            success, status_data = self.get_student_status(student_id)
            
            if not success:
                print(f"- Failed to get status for {student_id}: {status_data}")
                return False, status_data
            
            current_status = status_data.get('status', 'Unknown')
            
            if current_status == 'Not checked in':
                # Student can check in
                print(f"Student {student_id} is not checked in. Processing check-in...")
                return self.check_in_student(student_id)
                
            elif current_status == 'Checked-in':
                # Parse roll number to get shift information
                roll_data = parse_roll_number(student_id)
                if not roll_data['valid']:
                    print(f"Invalid roll number: {roll_data['error']}")
                    return False, f"Invalid roll number: {roll_data['error']}"
                
                shift = roll_data['shift']
                
                # Validate checkout time based on shift
                time_validator = TimeValidator()
                checkout_validation = time_validator.validate_checkout_time(student_id, None, shift)
                
                if checkout_validation['valid']:
                    print(f"Student {student_id} can check out. Processing check-out...")
                    return self.check_out_student(student_id)
                else:
                    print(f"Student {student_id} cannot check out: {checkout_validation['error']}")
                    return False, checkout_validation['error']
            else:
                print(f"Student {student_id} has status: {current_status}")
                return False, f"Invalid status: {current_status}"
                
        except Exception as e:
            print(f"- QR scan processing error: {e}")
            return False, str(e)
    
    def simulate_attendance_flow(self, student_id):
        """Simulate a complete attendance flow for testing"""
        print(f"\n=== Simulating attendance flow for {student_id} ===")
        
        # Step 1: Check in
        print("\n1. Attempting check-in...")
        success, data = self.check_in_student(student_id)
        if not success:
            print(f"Check-in failed: {data}")
            return False
        
        # Step 2: Try to check out immediately (should fail)
        print("\n2. Attempting immediate check-out (should fail)...")
        success, data = self.check_out_student(student_id)
        if success:
            print("ERROR: Check-out should have failed!")
            return False
        else:
            print(f"Expected failure: {data}")
        
        # Step 3: Check status
        print("\n3. Checking current status...")
        success, status = self.get_student_status(student_id)
        if success:
            print(f"Current status: {status}")
        
        # Step 4: Wait and try check-out again (simulate 2+ hours)
        print("\n4. Simulating 2+ hours wait and attempting check-out...")
        # Note: In real scenario, you would wait 2 hours
        # For testing, we'll just show what would happen
        print("(In real scenario, wait 2 hours before check-out)")
        
        return True

def main():
    """Main function for testing check-in/check-out functionality"""
    print("QR Code Check-in/Check-out System")
    print("=" * 40)
    
    # Initialize check-in manager
    manager = CheckInManager()
    
    # Test with a sample student
    test_student_id = "24-SWT-01"
    
    print(f"Testing with student: {test_student_id}")
    
    # Get initial status
    print("\nGetting initial status...")
    success, status = manager.get_student_status(test_student_id)
    if success:
        print(f"Initial status: {status}")
    else:
        print(f"Failed to get status: {status}")
        return
    
    # Process QR scan
    print(f"\nProcessing QR scan for {test_student_id}...")
    success, result = manager.process_qr_scan(test_student_id)
    
    if success:
        print(f"QR scan processed successfully: {result}")
    else:
        print(f"QR scan failed: {result}")
    
    # Get updated status
    print("\nGetting updated status...")
    success, status = manager.get_student_status(test_student_id)
    if success:
        print(f"Updated status: {status}")
    else:
        print(f"Failed to get updated status: {status}")

if __name__ == "__main__":
    main()
