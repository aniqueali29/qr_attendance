"""
Year Progression System for College Attendance System
Handles automatic year updates and graduation management
"""

import os
import json
import sqlite3
import requests
from datetime import datetime, timedelta
from typing import Dict, List, Optional
import pytz
from settings import SettingsManager


class YearProgression:
    """Handles academic year progression and graduation management"""
    
    def __init__(self, timezone=None):
        # Initialize settings manager
        self.settings = SettingsManager()
        
        # Load configuration from settings
        self.timezone = pytz.timezone(timezone or self.settings.get('timezone', 'Asia/Karachi'))
        self.students_file = "students.json"
        self.offline_file = "offline_data.json"
        self.website_url = self.settings.get('website_url', 'http://localhost/qr_attendance/public')
        self.api_key = self.settings.get('api_key', 'attendance_2025_xyz789_secure')
    
    def get_academic_year(self, current_date: Optional[datetime] = None) -> int:
        """
        Get current academic year based on date.
        Academic year starts in September.
        
        Args:
            current_date (datetime, optional): Current date. Defaults to now.
        
        Returns:
            int: Current academic year
        """
        if current_date is None:
            current_date = datetime.now(self.timezone)
        
        current_year = current_date.year
        current_month = current_date.month
        
        # Academic year starts in September
        if current_month >= 9:
            return current_year
        else:
            return current_year - 1
    
    def should_progress_year(self, current_date: Optional[datetime] = None) -> bool:
        """
        Check if year progression should happen.
        Progression happens in September.
        
        Args:
            current_date (datetime, optional): Current date. Defaults to now.
        
        Returns:
            bool: True if progression should happen
        """
        if current_date is None:
            current_date = datetime.now(self.timezone)
        
        return current_date.month == 9
    
    def calculate_student_year(self, admission_year: int, current_date: Optional[datetime] = None) -> int:
        """
        Calculate student's current year based on admission year.
        
        Args:
            admission_year (int): Year of admission
            current_date (datetime, optional): Current date. Defaults to now.
        
        Returns:
            int: Current year (1-4)
        """
        if current_date is None:
            current_date = datetime.now(self.timezone)
        
        academic_year = self.get_academic_year(current_date)
        year_of_study = academic_year - admission_year + 1
        
        # Cap at 4 years (graduation)
        return min(max(year_of_study, 1), 4)
    
    def update_student_year(self, student_id: str, current_date: Optional[datetime] = None) -> Dict:
        """
        Update a single student's year and graduation status.
        
        Args:
            student_id (str): Student ID
            current_date (datetime, optional): Current date. Defaults to now.
        
        Returns:
            Dict: Update result
        """
        if current_date is None:
            current_date = datetime.now(self.timezone)
        
        try:
            # Load student data
            if not os.path.exists(self.students_file):
                return {'success': False, 'error': 'Students file not found'}
            
            with open(self.students_file, 'r') as f:
                students = json.load(f)
            
            if student_id not in students:
                return {'success': False, 'error': 'Student not found'}
            
            student = students[student_id]
            
            # Parse roll number to get admission year
            try:
                from roll_parser import parse_roll_number
                roll_data = parse_roll_number(student_id)
                
                if not roll_data['valid']:
                    return {'success': False, 'error': f'Invalid roll number: {roll_data["error"]}'}
                
                admission_year = roll_data['admission_year']
                current_year = self.calculate_student_year(admission_year, current_date)
                is_graduated = current_year > 4
                
                # Update student data
                student['admission_year'] = admission_year
                student['current_year'] = current_year
                student['is_graduated'] = is_graduated
                student['last_year_update'] = current_date.strftime('%Y-%m-%d')
                
                # Update students file
                students[student_id] = student
                with open(self.students_file, 'w') as f:
                    json.dump(students, f, indent=2)
                
                return {
                    'success': True,
                    'student_id': student_id,
                    'admission_year': admission_year,
                    'current_year': current_year,
                    'is_graduated': is_graduated,
                    'updated_at': current_date.isoformat()
                }
                
            except ImportError:
                return {'success': False, 'error': 'Roll parser not available'}
            
        except Exception as e:
            return {'success': False, 'error': str(e)}
    
    def check_and_update_years(self, current_date: Optional[datetime] = None) -> Dict:
        """
        Check if year progression should happen and update all students.
        
        Args:
            current_date (datetime, optional): Current date. Defaults to now.
        
        Returns:
            Dict: Progression result
        """
        if current_date is None:
            current_date = datetime.now(self.timezone)
        
        if not self.should_progress_year(current_date):
            return {
                'success': True,
                'message': 'Year progression not needed',
                'current_month': current_date.month,
                'progression_month': 9
            }
        
        try:
            # Load student data
            if not os.path.exists(self.students_file):
                return {'success': False, 'error': 'Students file not found'}
            
            with open(self.students_file, 'r') as f:
                students = json.load(f)
            
            updated_students = []
            graduated_students = []
            errors = []
            
            for student_id in students.keys():
                result = self.update_student_year(student_id, current_date)
                
                if result['success']:
                    updated_students.append({
                        'student_id': student_id,
                        'current_year': result['current_year'],
                        'is_graduated': result['is_graduated']
                    })
                    
                    if result['is_graduated']:
                        graduated_students.append(student_id)
                else:
                    errors.append({
                        'student_id': student_id,
                        'error': result['error']
                    })
            
            # Log progression
            progression_log = {
                'date': current_date.isoformat(),
                'academic_year': self.get_academic_year(current_date),
                'total_students': len(students),
                'updated_students': len(updated_students),
                'graduated_students': len(graduated_students),
                'errors': len(errors),
                'details': {
                    'updated': updated_students,
                    'graduated': graduated_students,
                    'errors': errors
                }
            }
            
            # Save progression log
            log_file = f"progression_log_{current_date.strftime('%Y-%m')}.json"
            with open(log_file, 'w') as f:
                json.dump(progression_log, f, indent=2)
            
            return {
                'success': True,
                'message': 'Year progression completed',
                'academic_year': self.get_academic_year(current_date),
                'total_students': len(students),
                'updated_students': len(updated_students),
                'graduated_students': len(graduated_students),
                'errors': len(errors),
                'log_file': log_file
            }
            
        except Exception as e:
            return {'success': False, 'error': str(e)}
    
    def sync_to_website(self) -> bool:
        """
        Sync year progression data to website.
        
        Returns:
            bool: True if sync successful
        """
        try:
            # Check if website is accessible
            response = requests.get(self.website_url, timeout=5, verify=False)
            if response.status_code != 200:
                return False
            
            # Prepare sync data
            if not os.path.exists(self.students_file):
                return False
            
            with open(self.students_file, 'r') as f:
                students = json.load(f)
            
            # Filter students with updated year data
            updated_students = []
            for student_id, student_data in students.items():
                if 'current_year' in student_data and 'admission_year' in student_data:
                    updated_students.append({
                        'student_id': student_id,
                        'name': student_data.get('name', ''),
                        'admission_year': student_data['admission_year'],
                        'current_year': student_data['current_year'],
                        'is_graduated': student_data.get('is_graduated', False),
                        'last_year_update': student_data.get('last_year_update', '')
                    })
            
            if not updated_students:
                return True
            
            # Send to website
            sync_data = {
                'api_key': self.api_key,
                'action': 'update_student_years',
                'students': updated_students
            }
            
            response = requests.post(
                f"{self.website_url}/sync_api.php",
                json=sync_data,
                timeout=10,
                verify=False
            )
            
            return response.status_code == 200
            
        except Exception as e:
            print(f"Sync error: {e}")
            return False
    
    def get_progression_status(self) -> Dict:
        """
        Get current progression status.
        
        Returns:
            Dict: Current status information
        """
        try:
            current_date = datetime.now(self.timezone)
            academic_year = self.get_academic_year(current_date)
            
            # Load student data
            if not os.path.exists(self.students_file):
                return {'success': False, 'error': 'Students file not found'}
            
            with open(self.students_file, 'r') as f:
                students = json.load(f)
            
            # Analyze student years
            year_distribution = {}
            graduated_count = 0
            needs_update = 0
            
            for student_id, student_data in students.items():
                if 'current_year' in student_data:
                    year = student_data['current_year']
                    year_distribution[year] = year_distribution.get(year, 0) + 1
                    
                    if student_data.get('is_graduated', False):
                        graduated_count += 1
                else:
                    needs_update += 1
            
            return {
                'success': True,
                'current_date': current_date.isoformat(),
                'academic_year': academic_year,
                'should_progress': self.should_progress_year(current_date),
                'total_students': len(students),
                'year_distribution': year_distribution,
                'graduated_count': graduated_count,
                'needs_update': needs_update
            }
            
        except Exception as e:
            return {'success': False, 'error': str(e)}
    
    def manual_year_override(self, student_id: str, new_year: int, reason: str = '') -> Dict:
        """
        Manually override a student's year.
        
        Args:
            student_id (str): Student ID
            new_year (int): New year (1-4)
            reason (str): Reason for override
        
        Returns:
            Dict: Override result
        """
        if not 1 <= new_year <= 4:
            return {'success': False, 'error': 'Year must be between 1 and 4'}
        
        try:
            # Load student data
            if not os.path.exists(self.students_file):
                return {'success': False, 'error': 'Students file not found'}
            
            with open(self.students_file, 'r') as f:
                students = json.load(f)
            
            if student_id not in students:
                return {'success': False, 'error': 'Student not found'}
            
            # Update student
            old_year = students[student_id].get('current_year', 1)
            students[student_id]['current_year'] = new_year
            students[student_id]['is_graduated'] = new_year > 4
            students[student_id]['last_year_update'] = datetime.now(self.timezone).strftime('%Y-%m-%d')
            students[student_id]['manual_override'] = {
                'old_year': old_year,
                'new_year': new_year,
                'reason': reason,
                'date': datetime.now(self.timezone).isoformat()
            }
            
            # Save updated data
            with open(self.students_file, 'w') as f:
                json.dump(students, f, indent=2)
            
            return {
                'success': True,
                'student_id': student_id,
                'old_year': old_year,
                'new_year': new_year,
                'reason': reason,
                'updated_at': datetime.now(self.timezone).isoformat()
            }
            
        except Exception as e:
            return {'success': False, 'error': str(e)}


# Standalone functions for easy import
def check_and_update_years(current_date: Optional[datetime] = None) -> Dict:
    """Check if year progression should happen and update all students"""
    progression = YearProgression()
    return progression.check_and_update_years(current_date)


def update_student_year(student_id: str, current_date: Optional[datetime] = None) -> Dict:
    """Update a single student's year"""
    progression = YearProgression()
    return progression.update_student_year(student_id, current_date)


def get_academic_year(current_date: Optional[datetime] = None) -> int:
    """Get current academic year"""
    progression = YearProgression()
    return progression.get_academic_year(current_date)


def should_progress_year(current_date: Optional[datetime] = None) -> bool:
    """Check if year progression should happen"""
    progression = YearProgression()
    return progression.should_progress_year(current_date)


# Example usage and testing
if __name__ == "__main__":
    print("Year Progression System Test")
    print("=" * 50)
    
    progression = YearProgression()
    
    # Test current status
    status = progression.get_progression_status()
    print(f"Current Status:")
    print(f"  Academic Year: {status.get('academic_year', 'Unknown')}")
    print(f"  Should Progress: {status.get('should_progress', False)}")
    print(f"  Total Students: {status.get('total_students', 0)}")
    print(f"  Year Distribution: {status.get('year_distribution', {})}")
    print(f"  Graduated: {status.get('graduated_count', 0)}")
    print()
    
    # Test individual student update
    test_students = ["25-SWT-01", "24-ECSE-02", "23-CS-03"]
    
    for student_id in test_students:
        result = progression.update_student_year(student_id)
        if result['success']:
            print(f"Updated {student_id}: Year {result['current_year']}, Graduated: {result['is_graduated']}")
        else:
            print(f"Failed to update {student_id}: {result['error']}")
    
    print()
    
    # Test year progression
    print("Testing Year Progression:")
    result = progression.check_and_update_years()
    if result['success']:
        print(f"  Message: {result['message']}")
        print(f"  Updated Students: {result.get('updated_students', 0)}")
        print(f"  Graduated Students: {result.get('graduated_students', 0)}")
        print(f"  Errors: {result.get('errors', 0)}")
    else:
        print(f"  Error: {result['error']}")
    
    print()
    
    # Test manual override
    print("Testing Manual Override:")
    override_result = progression.manual_year_override("25-SWT-01", 3, "Academic adjustment")
    if override_result['success']:
        print(f"  Override successful: {override_result['old_year']} → {override_result['new_year']}")
    else:
        print(f"  Override failed: {override_result['error']}")
