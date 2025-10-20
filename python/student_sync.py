import requests
import json
import os
from datetime import datetime
from settings import get_setting
from secure_config import get_config

def sync_students_from_server():
    """Enhanced student synchronization from server with better error handling."""
    try:
        print("🔄 Syncing students from server...")
        
        # Get configuration
        website_url = get_config('website.url', 'http://localhost/qr_attendance/public')
        api_key = get_config('api.key', 'default_api_key')
        students_api_url = get_setting('students_api_url', f"{website_url}/api/students_sync.php")
        
        # Prepare request
        params = {
            'action': 'get_students',
            'api_key': api_key
        }
        
        response = requests.get(
            students_api_url,
            params=params,
            timeout=30,
            verify=False
        )
        
        if response.status_code == 200:
            result = response.json()
            
            if result.get('success', False):
                # Save students data
                students_data = result
                
                with open('students.json', 'w', encoding='utf-8') as f:
                    json.dump(students_data, f, indent=2, ensure_ascii=False)
                
                total_students = students_data.get('total_students', 0)
                print(f"✅ Successfully synced {total_students} students from server")
                return True
            else:
                print(f"❌ Server returned error: {result.get('message', 'Unknown error')}")
                return False
        else:
            print(f"❌ HTTP error {response.status_code}: {response.text}")
            return False
            
    except requests.exceptions.RequestException as e:
        print(f"❌ Network error during student sync: {e}")
        return False
    except Exception as e:
        print(f"❌ Unexpected error during student sync: {e}")
        return False

def get_student_info(student_id):
    """Get student information from local database with enhanced fallback."""
    try:
        # Load local students database
        if not os.path.exists('students.json'):
            print("⚠️ Local students database not found")
            return None
        
        with open('students.json', 'r', encoding='utf-8') as f:
            students_data = json.load(f)
        
        # Handle different data structures
        students = students_data.get('students', students_data)
        
        # Look for student
        if student_id in students:
            return students[student_id]
        
        # Try case-insensitive search
        student_id_lower = student_id.lower()
        for sid, info in students.items():
            if sid.lower() == student_id_lower:
                return info
        
        print(f"⚠️ Student {student_id} not found in local database")
        return None
        
    except Exception as e:
        print(f"❌ Error getting student info for {student_id}: {e}")
        return None

def update_student_info(student_id, info):
    """Update student information in local database with enhanced error handling."""
    try:
        # Load existing data
        students_data = {}
        if os.path.exists('students.json'):
            with open('students.json', 'r', encoding='utf-8') as f:
                students_data = json.load(f)
        
        # Update student info
        students = students_data.get('students', students_data)
        students[student_id] = info
        students_data['students'] = students
        
        # Add metadata
        students_data['last_updated'] = datetime.now().isoformat()
        students_data['total_students'] = len(students)
        
        # Save back
        with open('students.json', 'w', encoding='utf-8') as f:
            json.dump(students_data, f, indent=2, ensure_ascii=False)
        
        print(f"✅ Updated student info for {student_id}")
        return True
        
    except Exception as e:
        print(f"❌ Error updating student info for {student_id}: {e}")
        return False

def student_sync():
    """Main student synchronization function."""
    return sync_students_from_server()

if __name__ == "__main__":
    # Test student sync
    print("Student Sync Test:")
    success = student_sync()
    print(f"Sync result: {success}")
    
    # Test getting student info
    if success:
        test_id = '24-SWT-01'
        info = get_student_info(test_id)
        print(f"Student {test_id} info: {info}")