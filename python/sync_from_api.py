#!/usr/bin/env python3
"""
Sync students from web API to local students.json
This script fetches student data from the hosting server and updates the local JSON file
"""

import requests
import json
import os
from datetime import datetime

# Configuration
API_BASE_URL = "http://your-domain.com/api/students_sync.php"  # Change this to your hosting URL
API_KEY = "your-secret-api-key-123"  # Change this to match the API key in PHP
STUDENTS_JSON_PATH = "students.json"

def fetch_students_from_api():
    """Fetch students data from the web API"""
    try:
        # Method 1: Using API key in URL parameter
        url = f"{API_BASE_URL}?action=get_students&api_key={API_KEY}"
        
        print(f"Fetching students from: {url}")
        response = requests.get(url, timeout=30)
        
        if response.status_code == 200:
            data = response.json()
            print(f"Successfully fetched {data.get('total_students', 0)} students")
            return data
        else:
            print(f"API Error: {response.status_code} - {response.text}")
            return None
            
    except requests.exceptions.RequestException as e:
        print(f"Network error: {e}")
        return None
    except json.JSONDecodeError as e:
        print(f"JSON decode error: {e}")
        return None

def save_students_to_json(students_data):
    """Save students data to local JSON file"""
    try:
        # Update metadata
        students_data['last_updated'] = datetime.now().strftime('%Y-%m-%d %H:%M:%S')
        students_data['updated_by'] = 'python_sync'
        
        # Write to file
        with open(STUDENTS_JSON_PATH, 'w', encoding='utf-8') as f:
            json.dump(students_data, f, indent=2, ensure_ascii=False)
        
        print(f"Successfully saved {students_data.get('total_students', 0)} students to {STUDENTS_JSON_PATH}")
        return True
        
    except Exception as e:
        print(f"Error saving to JSON: {e}")
        return False

def sync_students():
    """Main sync function"""
    print("Starting student sync...")
    print(f"API URL: {API_BASE_URL}")
    print(f"Local JSON: {STUDENTS_JSON_PATH}")
    print("-" * 50)
    
    # Fetch data from API
    students_data = fetch_students_from_api()
    
    if students_data is None:
        print("Failed to fetch students from API")
        return False
    
    # Save to local JSON
    success = save_students_to_json(students_data)
    
    if success:
        print("✅ Sync completed successfully!")
        
        # Print summary
        students = students_data.get('students', {})
        print(f"\n📊 Summary:")
        print(f"   Total students: {len(students)}")
        print(f"   Last updated: {students_data.get('last_updated', 'Unknown')}")
        
        # Show first few students as example
        if students:
            print(f"\n👥 Sample students:")
            for i, (student_id, student_data) in enumerate(list(students.items())[:3]):
                print(f"   {student_id}: {student_data.get('name', 'Unknown')} ({student_data.get('email', 'No email')})")
            if len(students) > 3:
                print(f"   ... and {len(students) - 3} more")
    else:
        print("❌ Sync failed!")
        return False
    
    return success

def main():
    """Main function"""
    print("🔄 Student Sync Tool")
    print("=" * 50)
    
    # Check if students.json exists
    if os.path.exists(STUDENTS_JSON_PATH):
        print(f"📁 Found existing {STUDENTS_JSON_PATH}")
    else:
        print(f"📁 Creating new {STUDENTS_JSON_PATH}")
    
    # Run sync
    success = sync_students()
    
    if success:
        print("\n✅ Sync completed successfully!")
    else:
        print("\n❌ Sync failed! Check the error messages above.")
        exit(1)

if __name__ == "__main__":
    main()
