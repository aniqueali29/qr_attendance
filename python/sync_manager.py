import threading
import time
import json
import os
from datetime import datetime, timedelta
import requests
from settings import get_setting
from secure_config import get_config

class SyncManager:
    """Enhanced synchronization manager with conflict resolution and retry logic."""
    
    def __init__(self):
        self.sync_lock = threading.Lock()
        self.last_sync_time = None
        self.sync_attempts = 0
        self.max_retries = 3
        self.retry_delay = 5  # seconds
        
    def sync_attendance_to_server(self, attendance_data):
        """Sync attendance data to server with enhanced retry logic."""
        with self.sync_lock:
            for attempt in range(self.max_retries):
                try:
                    print(f"🔄 Syncing attendance to server (attempt {attempt + 1}/{self.max_retries})...")
                    
                    # Prepare API data
                    api_data = {
                        'api_key': get_config('api.key'),
                        'attendance_data': attendance_data,
                        'sync_timestamp': datetime.now().isoformat()
                    }
                    
                    # Send to server
                    website_url = get_config('website.url', 'http://localhost/qr_attendance/public')
                    api_endpoint = get_setting('api_endpoint_attendance', '/api/api_attendance.php')
                    url = f"{website_url.rstrip('/')}/{api_endpoint.lstrip('/')}"
                    
                    response = requests.post(
                        url, 
                        json=api_data, 
                        timeout=30,
                        verify=False
                    )
                    
                    if response.status_code == 200:
                        result = response.json()
                        if result.get('success', False):
                            self.last_sync_time = datetime.now()
                            self.sync_attempts = 0
                            print(f"✅ Attendance synced successfully ({len(attendance_data)} records)")
                            return True
                        else:
                            print(f"❌ Server returned error: {result.get('message', 'Unknown error')}")
                    else:
                        print(f"❌ HTTP error {response.status_code}: {response.text}")
                    
                    # If not successful, wait before retry
                    if attempt < self.max_retries - 1:
                        print(f"⏰ Retrying in {self.retry_delay} seconds...")
                        time.sleep(self.retry_delay)
                        
                except requests.exceptions.RequestException as e:
                    print(f"❌ Network error during sync (attempt {attempt + 1}): {e}")
                    if attempt < self.max_retries - 1:
                        print(f"⏰ Retrying in {self.retry_delay} seconds...")
                        time.sleep(self.retry_delay)
                except Exception as e:
                    print(f"❌ Unexpected error during sync (attempt {attempt + 1}): {e}")
                    if attempt < self.max_retries - 1:
                        print(f"⏰ Retrying in {self.retry_delay} seconds...")
                        time.sleep(self.retry_delay)
            
            # All attempts failed
            self.sync_attempts += 1
            print(f"❌ All sync attempts failed for {len(attendance_data)} records")
            return False
    
    def sync_attendance_from_server(self, since_days=7):
        """Pull attendance data from server with enhanced conflict resolution."""
        with self.sync_lock:
            try:
                print(f"🔄 Pulling attendance from server (since {since_days} days)...")
                
                # Calculate cutoff date
                cutoff_date = datetime.now() - timedelta(days=since_days)
                
                # Prepare request
                params = {
                    'api_key': get_config('api.key'),
                    'since': cutoff_date.strftime('%Y-%m-%d'),
                    'limit': get_setting('attendance_pull_page_size', 1000)
                }
                
                website_url = get_config('website.url', 'http://localhost/qr_attendance/public')
                api_url = get_setting('admin_attendance_api_url', f"{website_url}/api/api_attendance.php")
                
                response = requests.get(
                    api_url,
                    params=params,
                    timeout=30,
                    verify=False
                )
                
                if response.status_code == 200:
                    result = response.json()
                    
                    if result.get('success', False):
                        server_data = result.get('data', [])
                        print(f"✅ Retrieved {len(server_data)} records from server")
                        return server_data
                    else:
                        print(f"❌ Server returned error: {result.get('message', 'Unknown error')}")
                        return []
                else:
                    print(f"❌ HTTP error {response.status_code}: {response.text}")
                    return []
                    
            except Exception as e:
                print(f"❌ Error pulling attendance from server: {e}")
                return []
    
    def resolve_conflicts(self, local_data, server_data):
        """Resolve conflicts between local and server data with enhanced logic."""
        resolved_data = []
        conflicts = 0
        
        # Create lookup dictionaries
        local_lookup = {f"{item['ID']}_{item['Timestamp']}": item for item in local_data}
        server_lookup = {f"{item['ID']}_{item['Timestamp']}": item for item in server_data}
        
        all_keys = set(local_lookup.keys()) | set(server_lookup.keys())
        
        for key in all_keys:
            local_record = local_lookup.get(key)
            server_record = server_lookup.get(key)
            
            if local_record and server_record:
                # Conflict detected - use server data (server wins)
                resolved_data.append(server_record)
                conflicts += 1
                print(f"⚠️ Conflict resolved for {key} (server wins)")
            elif local_record:
                # Only exists locally
                resolved_data.append(local_record)
            elif server_record:
                # Only exists on server
                resolved_data.append(server_record)
        
        print(f"✅ Conflict resolution complete: {conflicts} conflicts resolved")
        return resolved_data
    
    def get_sync_status(self):
        """Get current synchronization status."""
        status = {
            'last_sync_time': self.last_sync_time.isoformat() if self.last_sync_time else 'Never',
            'sync_attempts': self.sync_attempts,
            'max_retries': self.max_retries,
            'is_syncing': self.sync_lock.locked()
        }
        return status

# Global sync manager instance
sync_manager = SyncManager()

# Convenience functions
def sync_to_server(attendance_data):
    """Convenience function to sync data to server."""
    return sync_manager.sync_attendance_to_server(attendance_data)

def sync_from_server(since_days=7):
    """Convenience function to pull data from server."""
    return sync_manager.sync_attendance_from_server(since_days)

def get_sync_status():
    """Convenience function to get sync status."""
    return sync_manager.get_sync_status()

if __name__ == "__main__":
    # Test the sync manager
    print("Sync Manager Test:")
    print(f"Sync Status: {get_sync_status()}")
    
    # Test with sample data
    sample_data = [{
        'ID': '24-SWT-01',
        'Name': 'Test Student',
        'Timestamp': datetime.now().strftime('%Y-%m-%d %I:%M:%S %p'),
        'Status': 'Present'
    }]
    
    # This will fail without a real server, but shows the flow
    result = sync_to_server(sample_data)
    print(f"Sync result: {result}")