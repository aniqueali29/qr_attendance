#!/usr/bin/env python3
"""
Student Synchronization Module for QR Code Attendance System
Handles synchronization between Python and web student data
"""

import json
import os
import requests
from datetime import datetime
from typing import Dict, Any, Optional, List
import urllib3
from urllib.parse import urljoin

# Disable SSL warnings
urllib3.disable_warnings(urllib3.exceptions.InsecureRequestWarning)

from settings import SettingsManager

class StudentSync:
    """Student synchronization between Python and web"""
    
    def __init__(self, website_url=None, api_key=None):
        self.settings_manager = SettingsManager()
        self.website_url = website_url or self.settings_manager.get('website_url', 'http://localhost/qr_attendance/public')
        self.api_key = api_key or self.settings_manager.get('api_key', 'attendance_2025_secure_key_3e13bd5acfdf332ecece2d60aa29db78')
        self.students_file = "students.json"
        
        # Student API endpoint
        self.student_api = f"{self.website_url}{self.settings_manager.get('api_endpoint_student_api', '/api/student_api_simple.php')}"
    
    def sync_from_web(self) -> bool:
        """Sync students from web to Python students.json"""
        try:
            print("Syncing students from web...")
            
            # Check if website is accessible
            if not self._check_website_connection():
                print("Website not accessible, skipping sync")
                return False
            
            # Get students from web API
            response = requests.get(
                f"{self.student_api}?action=get_all",
                timeout=10,
                verify=False
            )
            
            if response.status_code == 200:
                result = response.json()
                if result.get('success'):
                    students_data = result.get('students', [])
                    
                    # Convert to the format expected by students.json
                    students_dict = {}
                    for student in students_data:
                        student_id = student.get('student_id')
                        if student_id:
                            students_dict[student_id] = {
                                'name': student.get('name', ''),
                                'email': student.get('email', ''),
                                'phone': student.get('phone', ''),
                                'is_active': bool(student.get('is_active', 1)),
                                'created_at': student.get('created_at', ''),
                                'updated_at': student.get('updated_at', ''),
                                'admission_year': student.get('admission_year', ''),
                                'current_year': student.get('current_year', ''),
                                'shift': student.get('shift', ''),
                                'program': student.get('program', ''),
                                'is_graduated': bool(student.get('is_graduated', 0)),
                                'last_year_update': student.get('last_year_update', '')
                            }
                    
                    # Save to students.json
                    success = self._save_students_to_json(students_dict)
                    
                    if success:
                        print(f"Students synced from web: {len(students_dict)} students updated")
                        return True
                    else:
                        print("Failed to save students to students.json")
                        return False
                else:
                    print(f"Web API error: {result.get('message', 'Unknown error')}")
                    return False
            else:
                print(f"HTTP error: {response.status_code}")
                return False
                
        except Exception as e:
            print(f"Sync from web error: {e}")
            return False
    
    def _check_website_connection(self) -> bool:
        """Check if website is accessible"""
        try:
            response = requests.get(self.website_url, timeout=5, verify=False)
            return response.status_code == 200
        except:
            return False
    
    def _save_students_to_json(self, students_dict: Dict[str, Any]) -> bool:
        """Save students to students.json file"""
        try:
            # Create the JSON structure
            json_data = {
                'students': students_dict,
                'last_updated': datetime.now().strftime('%Y-%m-%d %H:%M:%S'),
                'updated_by': 'python_sync',
                'total_students': len(students_dict)
            }
            
            # Write to file
            with open(self.students_file, 'w') as f:
                json.dump(json_data, f, indent=2)
            
            print(f"Students saved to {self.students_file}")
            return True
            
        except Exception as e:
            print(f"Error saving to students.json: {e}")
            return False
    
    def load_students(self) -> Dict[str, Any]:
        """Load students from students.json file"""
        try:
            if not os.path.exists(self.students_file):
                return {}
            
            with open(self.students_file, 'r') as f:
                data = json.load(f)
            
            # Extract students from the JSON structure
            if 'students' in data:
                return data['students']
            else:
                # If no 'students' key, assume the file contains students directly
                return data
                
        except Exception as e:
            print(f"Error loading from students.json: {e}")
            return {}
    
    def get_student(self, student_id: str) -> Optional[Dict[str, Any]]:
        """Get a specific student by ID"""
        students = self.load_students()
        return students.get(student_id)
    
    def get_all_students(self) -> Dict[str, Any]:
        """Get all students"""
        return self.load_students()
    
    def get_students_by_shift(self, shift: str) -> Dict[str, Any]:
        """Get students filtered by shift"""
        all_students = self.load_students()
        filtered_students = {}
        
        for student_id, student_data in all_students.items():
            if student_data.get('shift', '').lower() == shift.lower():
                filtered_students[student_id] = student_data
        
        return filtered_students
    
    def get_students_by_program(self, program: str) -> Dict[str, Any]:
        """Get students filtered by program"""
        all_students = self.load_students()
        filtered_students = {}
        
        for student_id, student_data in all_students.items():
            if student_data.get('program', '').lower() == program.lower():
                filtered_students[student_id] = student_data
        
        return filtered_students
    
    def get_active_students(self) -> Dict[str, Any]:
        """Get only active students"""
        all_students = self.load_students()
        active_students = {}
        
        for student_id, student_data in all_students.items():
            if student_data.get('is_active', True):
                active_students[student_id] = student_data
        
        return active_students
    
    def get_sync_status(self) -> Dict[str, Any]:
        """Get current synchronization status"""
        try:
            students = self.load_students()
            total_students = len(students)
            active_students = len([s for s in students.values() if s.get('is_active', True)])
            
            # Check if students.json exists and when it was last updated
            last_updated = None
            if os.path.exists(self.students_file):
                with open(self.students_file, 'r') as f:
                    data = json.load(f)
                    last_updated = data.get('last_updated')
            
            return {
                'total_students': total_students,
                'active_students': active_students,
                'inactive_students': total_students - active_students,
                'last_updated': last_updated,
                'website_accessible': self._check_website_connection(),
                'students_file_exists': os.path.exists(self.students_file)
            }
            
        except Exception as e:
            return {
                'error': str(e),
                'total_students': 0,
                'active_students': 0,
                'inactive_students': 0,
                'last_updated': None,
                'website_accessible': False,
                'students_file_exists': False
            }
    
    def force_sync(self) -> bool:
        """Force immediate student synchronization"""
        print("Forcing immediate student sync...")
        return self.sync_from_web()


# Global sync instance
student_sync = StudentSync()


# Convenience functions
def sync_students_from_web():
    """Sync students from web"""
    return student_sync.sync_from_web()


def get_all_students():
    """Get all students"""
    return student_sync.get_all_students()


def get_student(student_id: str):
    """Get a specific student"""
    return student_sync.get_student(student_id)


def get_students_by_shift(shift: str):
    """Get students by shift"""
    return student_sync.get_students_by_shift(shift)


def get_students_by_program(program: str):
    """Get students by program"""
    return student_sync.get_students_by_program(program)


def get_active_students():
    """Get active students"""
    return student_sync.get_active_students()


def get_student_sync_status():
    """Get student sync status"""
    return student_sync.get_sync_status()


def force_student_sync():
    """Force student sync"""
    return student_sync.force_sync()


# Example usage and testing
if __name__ == "__main__":
    print("Student Synchronization Test")
    print("=" * 50)
    
    # Test sync status
    status = student_sync.get_sync_status()
    print(f"Student Sync Status:")
    print(f"  Total Students: {status['total_students']}")
    print(f"  Active Students: {status['active_students']}")
    print(f"  Website Accessible: {status['website_accessible']}")
    print(f"  Students File Exists: {status['students_file_exists']}")
    print(f"  Last Updated: {status.get('last_updated', 'Never')}")
    
    # Test sync from web
    print(f"\nTesting sync from web...")
    success = student_sync.sync_from_web()
    print(f"  Sync result: {'Success' if success else 'Failed'}")
    
    if success:
        # Test loading students
        students = student_sync.get_all_students()
        print(f"  Loaded {len(students)} students")
        
        # Show first few students
        print(f"\nFirst few students:")
        for i, (student_id, student_data) in enumerate(list(students.items())[:3]):
            print(f"  {student_id}: {student_data.get('name', 'Unknown')} ({student_data.get('program', 'Unknown')})")
    
    print(f"\nStudent sync ready!")
