#!/usr/bin/env python3
"""
Settings Manager for QR Code Attendance System
Centralized configuration management with database and file fallbacks
"""

import json
import os
import sqlite3
import requests
from datetime import datetime, time, timedelta
from typing import Dict, Any, Optional, List, Union
import pytz
import urllib3
from urllib.parse import urljoin
import pymysql

# Import secure configuration
from secure_config import get_config, get_database_config, get_api_config, get_smtp_config

# Disable SSL warnings
urllib3.disable_warnings(urllib3.exceptions.InsecureRequestWarning)


class SettingsManager:
    """Centralized settings management with database and file fallbacks"""
    
    def __init__(self, config_file="config.json", local_db="attendance_local.db"):
        self.config_file = config_file
        self.local_db = local_db
        
        # Load secure configuration
        self.website_url = get_config('WEBSITE_URL', "http://localhost/qr_attendance/public")
        self.api_key = get_config('API_KEY', "attendance_2025_secure_key_3e13bd5acfdf332ecece2d60aa29db78")
        self.timezone = pytz.timezone(get_config('TIMEZONE', 'Asia/Karachi'))
        
        self._settings_cache = {}
        self._cache_timestamp = None
        self._cache_duration = 60  # 1 minute for more frequent updates
        
        # Hardcoded defaults as fallback
        self._default_settings = {
            # Morning Shift Timings
            'morning_checkin_start': '09:00:00',
            'morning_checkin_end': '11:00:00',
            'morning_checkout_start': '12:00:00',
            'morning_checkout_end': '13:40:00',
            'morning_class_end': '13:40:00',
            
            # Evening Shift Timings
            'evening_checkin_start': '15:00:00',
            'evening_checkin_end': '18:00:00',
            'evening_checkout_start': '15:00:00',
            'evening_checkout_end': '18:00:00',
            'evening_class_end': '18:00:00',
            
            # System Configuration
            'minimum_duration_minutes': 120,
            'sync_interval_seconds': 30,
            'timezone': 'Asia/Karachi',
            'academic_year_start_month': 9,
            'auto_absent_morning_hour': 11,
            'auto_absent_evening_hour': 17,
            
            # Integration Settings
            'website_url': get_config('WEBSITE_URL', 'http://localhost/qr_attendance/public'),
            'api_endpoint_attendance': '/api/api_attendance.php',
            'api_endpoint_checkin': '/api/checkin_api.php',
            'api_endpoint_dashboard': '/api/dashboard_api.php',
            'api_endpoint_students': '/api/students_sync.php',
            'api_endpoint_settings': '/api/settings_sync.php',
            'api_endpoint_settings_api': '/api/settings_api.php',
            'api_endpoint_student_api': '/api/student_api_simple.php',
            'api_endpoint_admin_attendance': '/admin/api/attendance.php',
            'api_endpoint_sync': '/api/sync_api.php',
            'api_key': get_config('API_KEY', 'attendance_2025_secure_key_3e13bd5acfdf332ecece2d60aa29db78'),
            'api_secret': get_config('API_SECRET', ''),
            'jwt_secret': get_config('JWT_SECRET', ''),
            'encryption_key': get_config('ENCRYPTION_KEY', ''),
            'api_timeout_seconds': 30,
            
            # Advanced Settings
            'debug_mode': get_config('DEBUG_MODE', True),
            'log_errors': get_config('LOG_STUDENT_ACTIONS', True),
            'show_errors': get_config('DEBUG_MODE', True),
            'session_timeout_seconds': get_config('SESSION_TIMEOUT', 3600),
            'max_login_attempts': get_config('MAX_LOGIN_ATTEMPTS', 5),
            'login_lockout_minutes': get_config('LOGIN_LOCKOUT_TIME', 15),
            'password_min_length': get_config('PASSWORD_MIN_LENGTH', 8),
            'max_sync_records': 1000,
            'api_rate_limit': get_config('RATE_LIMIT_REQUESTS', 100),
            
            # QR Code Settings
            'qr_code_size': 200,
            'qr_code_margin': 10,
            'qr_code_path': 'assets/img/qr_codes/',
            
            # File Upload Settings
            'max_file_size_mb': 5,
            'allowed_extensions': 'csv,json,xlsx',
            
            # Email Settings
            'smtp_host': get_config('SMTP_HOST', 'smtp.gmail.com'),
            'smtp_port': get_config('SMTP_PORT', 587),
            'smtp_username': get_config('SMTP_USERNAME', ''),
            'smtp_password': get_config('SMTP_PASSWORD', ''),
            'smtp_from_email': get_config('SMTP_USERNAME', 'noreply@example.com'),
            'smtp_from_name': 'QR Attendance System'
        }
    
    def load_settings(self, force_reload=False):
        """Load settings from settings.json file with fallback to defaults"""
        current_time = datetime.now()
        
        # Check if cache is still valid
        if (not force_reload and 
            self._cache_timestamp and 
            (current_time - self._cache_timestamp).seconds < self._cache_duration):
            return self._settings_cache
        
        settings = {}
        
        # Try to sync from admin panel API first (for hosting setup)
        if not force_reload:
            try:
                if self.sync_from_admin_panel():
                    print("Settings synced from admin panel API")
                    # Get the synced settings
                    settings.update(self._settings_cache)
                else:
                    print("Admin panel API sync failed, trying local file")
            except Exception as e:
                print(f"Admin panel sync failed: {e}")
        
        # If no settings from API, try local settings.json file
        if not settings:
            try:
                json_settings = self._load_from_settings_json()
                if json_settings:
                    settings.update(json_settings)
                    print("Settings loaded from local settings.json file")
                else:
                    print("No local settings.json file found")
            except Exception as e:
                print(f"Failed to load from settings.json: {e}")
        
        # Load from config file for sensitive settings (database credentials, etc.)
        try:
            config_settings = self._load_from_config_file()
            if config_settings:
                settings.update(config_settings)
                print("Sensitive settings loaded from config file")
        except Exception as e:
            print(f"Failed to load from config file: {e}")
        
        # Fill in any missing settings with defaults
        for key, default_value in self._default_settings.items():
            if key not in settings:
                settings[key] = default_value
        
        # Update cache
        self._settings_cache = settings
        self._cache_timestamp = current_time
        
        return settings
    
    def _load_from_settings_json(self):
        """Load settings from settings.json file"""
        settings_file = "settings.json"
        if not os.path.exists(settings_file):
            return {}
        
        try:
            with open(settings_file, 'r') as f:
                data = json.load(f)
            
            # Extract settings from the JSON structure
            if 'settings' in data:
                settings = data['settings']
                # Remove metadata fields
                settings.pop('last_updated', None)
                settings.pop('updated_by', None)
                return settings
            else:
                # If no 'settings' key, assume the file contains settings directly
                return data
                
        except Exception as e:
            print(f"Error loading from settings.json: {e}")
            return {}
    
    def _convert_to_12h_format(self, time_str):
        """Convert 24-hour format time to 12-hour format with AM/PM."""
        try:
            # Try to parse as 24-hour format first
            if ':' in time_str:
                # Handle different 24-hour formats
                if len(time_str.split(':')) == 2:  # HH:MM
                    dt = datetime.strptime(time_str, '%H:%M')
                elif len(time_str.split(':')) == 3:  # HH:MM:SS
                    dt = datetime.strptime(time_str, '%H:%M:%S')
                else:
                    return time_str  # Return as-is if can't parse
                
                # Convert to 12-hour format
                return dt.strftime('%I:%M %p')
            else:
                return time_str  # Return as-is if not a time format
        except:
            return time_str  # Return as-is if conversion fails

    def _save_to_settings_json(self, settings_dict):
        """Save settings to settings.json file with 12-hour format conversion"""
        settings_file = "settings.json"
        try:
            # Convert time settings to 12-hour format
            converted_settings = settings_dict.copy()
            time_keys = [
                'morning_checkin_start', 'morning_checkin_end', 'morning_class_end',
                'morning_checkout_start', 'morning_checkout_end',
                'evening_checkin_start', 'evening_checkin_end', 'evening_class_end',
                'evening_checkout_start', 'evening_checkout_end'
            ]
            
            for key in time_keys:
                if key in converted_settings:
                    converted_settings[key] = self._convert_to_12h_format(converted_settings[key])
            
            # Create the JSON structure
            json_data = {
                'settings': converted_settings,
                'last_updated': datetime.now().strftime('%Y-%m-%d %H:%M:%S'),
                'updated_by': 'python_app'
            }
            
            # Write to file
            with open(settings_file, 'w') as f:
                json.dump(json_data, f, indent=2)
            
            print(f"Settings saved to {settings_file} (converted to 12-hour format)")
            return True
            
        except Exception as e:
            print(f"Error saving to settings.json: {e}")
            return False
    
    def _load_from_database(self):
        """Load settings from database (MySQL admin panel or SQLite fallback)"""
        # Try MySQL admin panel database first
        try:
            settings = self._load_from_mysql_database()
            if settings:
                print("Settings loaded from MySQL admin panel database")
                return settings
        except Exception as e:
            print(f"Failed to load from MySQL database: {e}")
        
        # Fallback to local SQLite database
        try:
            settings = self._load_from_sqlite_database()
            if settings:
                print("Settings loaded from local SQLite database")
                return settings
        except Exception as e:
            print(f"Failed to load from SQLite database: {e}")
        
        return {}
    
    def _load_from_mysql_database(self):
        """Load settings from MySQL admin panel database"""
        # Load database config from secure configuration
        try:
            db_config = get_database_config()
            conn = pymysql.connect(
                host=db_config['host'],
                user=db_config['user'],
                password=db_config['password'],
                database=db_config['database'],
                port=db_config['port'],
                charset='utf8mb4'
            )
            
            cursor = conn.cursor()
            cursor.execute('''
                SELECT setting_key, setting_value, setting_type 
                FROM system_settings 
                ORDER BY setting_key
            ''')
            rows = cursor.fetchall()
            
            settings = {}
            for row in rows:
                key, value, setting_type = row
                # Convert value based on type
                if setting_type == 'integer':
                    settings[key] = int(value)
                elif setting_type == 'boolean':
                    settings[key] = value.lower() in ('true', '1', 'yes')
                elif setting_type == 'time':
                    settings[key] = value
                else:
                    settings[key] = value
            
            conn.close()
            return settings
            
        except Exception as e:
            print(f"Error loading from MySQL database: {e}")
            return {}
    
    def _load_from_sqlite_database(self):
        """Load settings from local SQLite database"""
        if not os.path.exists(self.local_db):
            return {}
        
        try:
            conn = sqlite3.connect(self.local_db)
            cursor = conn.cursor()
            
            # Create settings table if it doesn't exist
            cursor.execute('''
                CREATE TABLE IF NOT EXISTS system_settings (
                    setting_key TEXT PRIMARY KEY,
                    setting_value TEXT NOT NULL,
                    setting_type TEXT NOT NULL,
                    category TEXT NOT NULL,
                    description TEXT,
                    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )
            ''')
            
            # Load settings
            cursor.execute('SELECT setting_key, setting_value, setting_type FROM system_settings')
            rows = cursor.fetchall()
            
            settings = {}
            for row in rows:
                key, value, setting_type = row
                # Convert value based on type
                if setting_type == 'integer':
                    settings[key] = int(value)
                elif setting_type == 'boolean':
                    settings[key] = value.lower() in ('true', '1', 'yes')
                elif setting_type == 'time':
                    settings[key] = value
                else:
                    settings[key] = value
            
            conn.close()
            return settings
            
        except Exception as e:
            print(f"Error loading from SQLite database: {e}")
            return {}
    
    def _load_from_config_file(self):
        """Load sensitive settings from config file"""
        if not os.path.exists(self.config_file):
            return {}
        
        try:
            with open(self.config_file, 'r') as f:
                config = json.load(f)
            
            # Extract sensitive settings
            sensitive_settings = {}
            if 'database' in config:
                sensitive_settings.update({
                    'db_host': config['database'].get('host', 'localhost'),
                    'db_name': config['database'].get('name', 'qr_attendance'),
                    'db_username': config['database'].get('username', 'root'),
                    'db_password': config['database'].get('password', '')
                })
            
            if 'api' in config:
                sensitive_settings.update({
                    'api_key': config['api'].get('key', self.api_key),
                    'api_endpoint': config['api'].get('endpoint', '/api_attendance.php')
                })
            
            if 'website' in config:
                sensitive_settings.update({
                    'website_url': config['website'].get('url', self.website_url)
                })
            
            return sensitive_settings
            
        except Exception as e:
            print(f"Error loading from config file: {e}")
            return {}
    
    def get(self, key: str, default: Any = None):
        """Get a setting value with fallback to default"""
        settings = self.load_settings()
        return settings.get(key, default)
    
    def get_timing_settings(self, shift: str) -> Dict[str, Any]:
        """Get all timing settings for a specific shift"""
        settings = self.load_settings()
        
        if shift.lower() == 'morning':
            return {
                'shift': 'Morning',
                'checkin_start': settings.get('morning_checkin_start', '09:00:00'),
                'checkin_end': settings.get('morning_checkin_end', '11:00:00'),
                'checkout_start': settings.get('morning_checkout_start', '12:00:00'),
                'checkout_end': settings.get('morning_checkout_end', '13:40:00'),
                'class_end': settings.get('morning_class_end', '13:40:00'),
                'checkin_window_hours': 2,
                'total_class_hours': 4.67
            }
        elif shift.lower() == 'evening':
            return {
                'shift': 'Evening',
                'checkin_start': settings.get('evening_checkin_start', '15:00:00'),
                'checkin_end': settings.get('evening_checkin_end', '18:00:00'),
                'checkout_start': settings.get('evening_checkout_start', '15:00:00'),
                'checkout_end': settings.get('evening_checkout_end', '18:00:00'),
                'class_end': settings.get('evening_class_end', '18:00:00'),
                'checkin_window_hours': 3,
                'total_class_hours': 3
            }
        else:
            raise ValueError(f"Invalid shift: {shift}. Must be 'Morning' or 'Evening'")
    
    def validate_timings(self, shift: str, timings: Dict[str, str]) -> Dict[str, Any]:
        """Validate timing settings for a shift"""
        errors = []
        warnings = []
        
        try:
            # Parse time strings
            checkin_start = datetime.strptime(timings.get('checkin_start', '09:00:00'), '%H:%M:%S').time()
            checkin_end = datetime.strptime(timings.get('checkin_end', '11:00:00'), '%H:%M:%S').time()
            checkout_start = datetime.strptime(timings.get('checkout_start', '12:00:00'), '%H:%M:%S').time()
            checkout_end = datetime.strptime(timings.get('checkout_end', '13:40:00'), '%H:%M:%S').time()
            class_end = datetime.strptime(timings.get('class_end', '13:40:00'), '%H:%M:%S').time()
            
            # Validation rules
            if checkin_end <= checkin_start:
                errors.append("Check-in end time must be after check-in start time")
            
            if checkout_start < checkin_start:
                errors.append("Check-out start time must be during or after check-in start time")
            
            if checkout_end <= checkout_start:
                errors.append("Check-out end time must be after check-out start time")
            
            if class_end <= checkin_start:
                errors.append("Class end time must be after check-in start time")
            
            # Check for reasonable time windows
            checkin_duration = (datetime.combine(datetime.today(), checkin_end) - 
                               datetime.combine(datetime.today(), checkin_start)).seconds / 3600
            if checkin_duration < 0.5:
                warnings.append("Check-in window is very short (less than 30 minutes)")
            elif checkin_duration > 4:
                warnings.append("Check-in window is very long (more than 4 hours)")
            
            # Check for overlapping shifts
            if shift.lower() == 'morning':
                evening_start = datetime.strptime(self.get('evening_checkin_start', '15:00:00'), '%H:%M:%S').time()
                if class_end > evening_start:
                    warnings.append("Morning class end time overlaps with evening shift start")
            
            return {
                'valid': len(errors) == 0,
                'errors': errors,
                'warnings': warnings,
                'timings': timings
            }
            
        except ValueError as e:
            return {
                'valid': False,
                'errors': [f"Invalid time format: {e}"],
                'warnings': [],
                'timings': timings
            }
    
    def sync_from_web(self) -> bool:
        """Sync settings from web database"""
        try:
            if not self._check_website_connection():
                print("Website not accessible, skipping sync")
                return False
            
            # Get settings from web API
            url = f"{self.website_url}{self.get('api_endpoint_settings_api', '/api/settings_api.php')}"
            response = requests.get(
                f"{url}?action=get_all",
                timeout=10,
                verify=False
            )
            
            if response.status_code == 200:
                web_settings = response.json()
                if web_settings.get('success'):
                    # Update local database
                    self._update_local_database(web_settings.get('data', {}))
                    print("Settings synced from web")
                    return True
                else:
                    print(f"Web API error: {web_settings.get('message', 'Unknown error')}")
                    return False
            else:
                print(f"HTTP error: {response.status_code}")
                return False
                
        except Exception as e:
            print(f"Sync error: {e}")
            return False
    
    def _check_website_connection(self) -> bool:
        """Check if website is accessible"""
        try:
            response = requests.get(self.website_url, timeout=5, verify=False)
            return response.status_code == 200
        except:
            return False
    
    def _update_local_database(self, web_settings: Dict[str, Any]):
        """Update local database with web settings"""
        try:
            conn = sqlite3.connect(self.local_db)
            cursor = conn.cursor()
            
            for key, value in web_settings.items():
                cursor.execute('''
                    INSERT OR REPLACE INTO system_settings 
                    (setting_key, setting_value, setting_type, category, last_updated)
                    VALUES (?, ?, ?, ?, ?)
                ''', (key, str(value), 'string', 'web_sync', datetime.now().isoformat()))
            
            conn.commit()
            conn.close()
            
            # Clear cache to force reload
            self._settings_cache = {}
            self._cache_timestamp = None
            
        except Exception as e:
            print(f"Error updating local database: {e}")
    
    def get_all_by_category(self, category: str) -> Dict[str, Any]:
        """Get all settings for a specific category"""
        settings = self.load_settings()
        category_settings = {}
        
        for key, value in settings.items():
            if key.startswith(category.lower() + '_') or key in self._get_category_keys(category):
                category_settings[key] = value
        
        return category_settings
    
    def _get_category_keys(self, category: str) -> List[str]:
        """Get keys that belong to a category"""
        category_mapping = {
            'shift_timings': [
                'morning_checkin_start', 'morning_checkin_end', 'morning_checkout_start',
                'morning_checkout_end', 'morning_class_end', 'evening_checkin_start',
                'evening_checkin_end', 'evening_checkout_start', 'evening_checkout_end',
                'evening_class_end'
            ],
            'system_config': [
                'minimum_duration_minutes', 'sync_interval_seconds', 'timezone',
                'academic_year_start_month', 'auto_absent_morning_hour', 'auto_absent_evening_hour'
            ],
            'integration': [
                'website_url', 'api_endpoint_attendance', 'api_endpoint_checkin',
                'api_endpoint_dashboard', 'api_key', 'api_timeout_seconds'
            ],
            'advanced': [
                'debug_mode', 'log_errors', 'show_errors', 'session_timeout_seconds',
                'max_login_attempts', 'login_lockout_minutes', 'password_min_length',
                'max_sync_records', 'api_rate_limit'
            ]
        }
        
        return category_mapping.get(category, [])
    
    def export_settings(self, filepath: str = None) -> str:
        """Export settings to JSON file"""
        if filepath is None:
            filepath = f"settings_export_{datetime.now().strftime('%Y%m%d_%H%M%S')}.json"
        
        settings = self.load_settings()
        export_data = {
            'exported_at': datetime.now().isoformat(),
            'settings': settings,
            'version': '1.0'
        }
        
        with open(filepath, 'w') as f:
            json.dump(export_data, f, indent=2)
        
        return filepath
    
    def import_settings(self, filepath: str) -> bool:
        """Import settings from JSON file"""
        try:
            with open(filepath, 'r') as f:
                import_data = json.load(f)
            
            if 'settings' in import_data:
                settings = import_data['settings']
                
                # Update local database
                conn = sqlite3.connect(self.local_db)
                cursor = conn.cursor()
                
                for key, value in settings.items():
                    cursor.execute('''
                        INSERT OR REPLACE INTO system_settings 
                        (setting_key, setting_value, setting_type, category, last_updated)
                        VALUES (?, ?, ?, ?, ?)
                    ''', (key, str(value), 'string', 'import', datetime.now().isoformat()))
                
                conn.commit()
                conn.close()
                
                # Clear cache
                self._settings_cache = {}
                self._cache_timestamp = None
                
                print(f"Settings imported from {filepath}")
                return True
            else:
                print("Invalid import file format")
                return False
                
        except Exception as e:
            print(f"Import error: {e}")
            return False
    
    def get_current_time(self):
        """Get current time in system timezone"""
        return datetime.now(self.timezone)
    
    def format_time(self, dt=None):
        """Format datetime in system timezone"""
        if dt is None:
            dt = self.get_current_time()
        return dt.strftime("%Y-%m-%d %H:%M:%S")
    
    def sync_from_admin_panel(self):
        """Sync settings from admin panel API"""
        try:
            # Load config from secure configuration
            website_url = get_config('WEBSITE_URL', self.website_url)
            api_key = get_config('API_KEY', self.api_key)
            settings_endpoint = self.get('api_endpoint_settings_api', '/api/settings_api.php')
            
            # Make API request to admin panel
            api_url = f"{website_url}{settings_endpoint}"
            headers = {
                'X-API-Key': api_key,
                'Content-Type': 'application/json'
            }
            
            # Add action parameter for settings API (GET request)
            api_url_with_action = f"{api_url}?action=get_all"
            response = requests.get(api_url_with_action, headers=headers, timeout=30, verify=False)
            
            if response.status_code == 200:
                data = response.json()
                if data.get('success'):
                    # Update local cache with admin panel settings
                    admin_settings = data.get('data', {})
                    if admin_settings:
                        # Convert admin panel format to our format
                        converted_settings = {}
                        for category, settings_list in admin_settings.items():
                            for setting in settings_list:
                                key = setting.get('key')
                                value = setting.get('value')
                                if key and value is not None:
                                    converted_settings[key] = value
                        
                        # Update cache
                        self._settings_cache.update(converted_settings)
                        self._cache_timestamp = datetime.now()
                        
                        # Save to settings.json file
                        self._save_to_settings_json(converted_settings)
                        
                        print(f"Synced {len(converted_settings)} settings from admin panel")
                        return True
                else:
                    print(f"Admin panel API error: {data.get('message', 'Unknown error')}")
            else:
                print(f"API request failed with status {response.status_code}")
                
        except Exception as e:
            print(f"Error syncing from admin panel: {e}")
        
        return False
    
    def force_reload_settings(self):
        """Force reload settings from admin panel API"""
        try:
            # Clear cache to force reload
            self._cache_timestamp = None
            # Force sync from admin panel
            if self.sync_from_admin_panel():
                print("Settings force synced from admin panel API")
                return True
            else:
                # Fallback to local file
                self.load_settings(force_reload=True)
                print("Settings reloaded from local file")
                return True
        except Exception as e:
            print(f"Force reload failed: {e}")
            return False


# Global settings instance
settings = SettingsManager()


# Convenience functions for backward compatibility
def get_setting(key: str, default: Any = None):
    """Get a setting value"""
    return settings.get(key, default)


def get_timing_settings(shift: str):
    """Get timing settings for a shift"""
    return settings.get_timing_settings(shift)


def validate_timings(shift: str, timings: Dict[str, str]):
    """Validate timing settings"""
    return settings.validate_timings(shift, timings)


def sync_settings_from_web():
    """Sync settings from web"""
    return settings.sync_from_web()


# Example usage and testing
if __name__ == "__main__":
    print("Settings Manager Test")
    print("=" * 50)
    
    # Test loading settings
    print("Loading settings...")
    all_settings = settings.load_settings()
    print(f"Loaded {len(all_settings)} settings")
    
    # Test getting specific settings
    print(f"\nMorning check-in start: {settings.get('morning_checkin_start')}")
    print(f"Sync interval: {settings.get('sync_interval_seconds')} seconds")
    print(f"Debug mode: {settings.get('debug_mode')}")
    
    # Test timing settings
    print(f"\nMorning shift timings:")
    morning_timings = settings.get_timing_settings('Morning')
    for key, value in morning_timings.items():
        print(f"  {key}: {value}")
    
    # Test validation
    print(f"\nTesting timing validation:")
    test_timings = {
        'checkin_start': '09:00:00',
        'checkin_end': '11:00:00',
        'checkout_start': '12:00:00',
        'checkout_end': '13:40:00',
        'class_end': '13:40:00'
    }
    
    validation = settings.validate_timings('Morning', test_timings)
    print(f"Valid: {validation['valid']}")
    if validation['errors']:
        print(f"Errors: {validation['errors']}")
    if validation['warnings']:
        print(f"Warnings: {validation['warnings']}")
    
    # Test export
    print(f"\nExporting settings...")
    export_file = settings.export_settings()
    print(f"Settings exported to: {export_file}")
    
    print(f"\nSettings manager ready!")
