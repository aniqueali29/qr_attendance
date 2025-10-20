import json
import os
import time
from typing import Any, Dict, Optional

class SettingsManager:
    """Enhanced settings manager with file watching and validation."""
    
    def __init__(self, settings_file: str = 'settings.json'):
        self.settings_file = settings_file
        self._settings: Dict[str, Any] = {}
        self._last_modified = 0
        self._default_settings = {
            'timezone': 'Asia/Karachi',
            'sync_interval_seconds': 30,
            'api_endpoint_attendance': '/api/api_attendance.php',
            'enable_automatic_absent': True,
            'automatic_absent_time': '10:00 AM',
            'attendance_pull_lookback_days': 7,
            'attendance_pull_page_size': 1000,
            'clear_local_after_sync': False,
            'check_in_start_time': '08:00 AM',
            'check_in_end_time': '10:00 AM',
            'check_out_start_time': '02:00 PM',
            'check_out_end_time': '05:00 PM',
            'students_api_url': 'http://localhost/qr_attendance/public/api/students_sync.php',
            'settings_api_url': 'http://localhost/qr_attendance/public/api/settings_sync.php',
            'admin_attendance_api_url': 'http://localhost/qr_attendance/public/api/api_attendance.php'
        }
        self.load_settings()
    
    def load_settings(self) -> bool:
        """Load settings from file with enhanced error handling."""
        try:
            if not os.path.exists(self.settings_file):
                print(f"⚠️ Settings file not found. Creating with defaults: {self.settings_file}")
                self._settings = self._default_settings.copy()
                self.save_settings()
                return True
            
            # Check if file has been modified
            current_modified = os.path.getmtime(self.settings_file)
            if current_modified <= self._last_modified:
                return True  # No changes
            
            with open(self.settings_file, 'r', encoding='utf-8') as f:
                loaded_settings = json.load(f)
            
            # Handle nested settings structure
            if 'settings' in loaded_settings:
                loaded_settings = loaded_settings['settings']
            
            # Merge with defaults
            self._settings = {**self._default_settings, **loaded_settings}
            self._last_modified = current_modified
            
            print(f"✅ Settings loaded from {self.settings_file}")
            return True
            
        except json.JSONDecodeError as e:
            print(f"❌ Error parsing settings file: {e}")
            self._settings = self._default_settings.copy()
            return False
        except Exception as e:
            print(f"❌ Error loading settings: {e}")
            self._settings = self._default_settings.copy()
            return False
    
    def save_settings(self) -> bool:
        """Save settings to file with enhanced error handling."""
        try:
            with open(self.settings_file, 'w', encoding='utf-8') as f:
                json.dump(self._settings, f, indent=2, ensure_ascii=False)
            
            self._last_modified = os.path.getmtime(self.settings_file)
            print(f"✅ Settings saved to {self.settings_file}")
            return True
        except Exception as e:
            print(f"❌ Error saving settings: {e}")
            return False
            
    def get(self, key: str, default: Any = None) -> Any:
        """Get setting value with enhanced validation."""
        self.load_settings()  # Reload to check for changes
        
        # Handle nested keys
        if '.' in key:
            keys = key.split('.')
            value = self._settings
            for k in keys:
                if isinstance(value, dict) and k in value:
                    value = value[k]
                else:
                    return default
            return value
        else:
            return self._settings.get(key, default)
    
    def set(self, key: str, value: Any) -> bool:
        """Set setting value with enhanced validation."""
        try:
            # Handle nested keys
            if '.' in key:
                keys = key.split('.')
                current = self._settings
                for k in keys[:-1]:
                    if k not in current or not isinstance(current[k], dict):
                        current[k] = {}
                    current = current[k]
                current[keys[-1]] = value
            else:
                self._settings[key] = value
                
            return self.save_settings()
        except Exception as e:
            print(f"❌ Error setting {key}: {e}")
        return False
    
    def force_reload_settings(self):
        """Force reload settings from file."""
        self._last_modified = 0
        self.load_settings()
    
    def get_all_settings(self) -> Dict[str, Any]:
        """Get all settings with defaults applied."""
        self.load_settings()
        return self._settings.copy()
    
    def reset_to_defaults(self) -> bool:
        """Reset all settings to defaults."""
        self._settings = self._default_settings.copy()
        return self.save_settings()

# Global settings instance
settings_manager = SettingsManager()

# Convenience functions
def get_setting(key: str, default: Any = None) -> Any:
    """Convenience function to get a setting."""
    return settings_manager.get(key, default)

def set_setting(key: str, value: Any) -> bool:
    """Convenience function to set a setting."""
    return settings_manager.set(key, value)

def reload_settings():
    """Convenience function to reload settings."""
    settings_manager.force_reload_settings()

if __name__ == "__main__":
    # Test the settings manager
    print("Settings Manager Test:")
    print(f"Timezone: {get_setting('timezone')}")
    print(f"Sync Interval: {get_setting('sync_interval_seconds')}")
    print(f"All Settings: {settings_manager.get_all_settings()}")