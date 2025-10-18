#!/usr/bin/env python3
"""
Secure Configuration Manager for Python QR Attendance System
Loads configuration from config.env file with secure defaults
"""

import os
import json
import secrets
import hashlib
from typing import Dict, Any, Optional
from pathlib import Path


class SecureConfig:
    """Secure configuration manager that loads from config.env file"""
    
    def __init__(self, env_file: str = None):
        self.env_file = env_file or self._find_env_file()
        self._config = {}
        self._loaded = False
        
    def _find_env_file(self) -> str:
        """Find the config.env file in the project root"""
        # Look for config.env in the parent directory (project root)
        current_dir = Path(__file__).parent
        project_root = current_dir.parent
        env_file = project_root / "config.env"
        
        if env_file.exists():
            return str(env_file)
        
        # Fallback to local config.env in python directory
        local_env = current_dir / "config.env"
        if local_env.exists():
            return str(local_env)
            
        # If no config.env found, return a default path
        return str(project_root / "config.env")
    
    def load(self) -> Dict[str, Any]:
        """Load configuration from config.env file"""
        if self._loaded:
            return self._config
            
        # Load from config.env file if it exists
        if os.path.exists(self.env_file):
            self._load_from_env_file()
        else:
            print(f"Warning: Config file not found at {self.env_file}")
        
        # Load configuration with secure defaults
        self._config = {
            # Database Configuration
            'DB_HOST': self._get_env('DB_HOST', 'localhost'),
            'DB_NAME': self._get_env('DB_NAME', 'qr_attendance'),
            'DB_USER': self._get_env('DB_USER', 'root'),
            'DB_PASS': self._get_env('DB_PASS', ''),
            'DB_PORT': self._get_env('DB_PORT', '3306'),
            
            # API Configuration
            'API_KEY': self._get_env('API_KEY', self._generate_secure_key()),
            'API_SECRET': self._get_env('API_SECRET', self._generate_secure_key(32)),
            
            # SMTP Configuration
            'SMTP_HOST': self._get_env('SMTP_HOST', 'smtp.gmail.com'),
            'SMTP_PORT': self._get_env('SMTP_PORT', '587'),
            'SMTP_USERNAME': self._get_env('SMTP_USERNAME', ''),
            'SMTP_PASSWORD': self._get_env('SMTP_PASSWORD', ''),
            
            # Security Configuration
            'JWT_SECRET': self._get_env('JWT_SECRET', self._generate_secure_key(32)),
            'ENCRYPTION_KEY': self._get_env('ENCRYPTION_KEY', self._generate_secure_key(32)),
            
            # System Configuration
            'DEBUG_MODE': self._get_env('DEBUG_MODE', 'false').lower() == 'true',
            'LOG_LEVEL': self._get_env('LOG_LEVEL', 'error'),
            'TIMEZONE': self._get_env('TIMEZONE', 'Asia/Karachi'),
            
            # File Upload Configuration
            'MAX_FILE_SIZE': int(self._get_env('MAX_FILE_SIZE', '5242880')),
            'UPLOAD_PATH': self._get_env('UPLOAD_PATH', 'uploads/'),
            'QR_CODE_PATH': self._get_env('QR_CODE_PATH', 'qr_codes/'),
            
            # Session Configuration
            'SESSION_TIMEOUT': int(self._get_env('SESSION_TIMEOUT', '3600')),
            'SESSION_NAME': self._get_env('SESSION_NAME', 'QR_ATTENDANCE_SESSION'),
            
            # Security Settings
            'MAX_LOGIN_ATTEMPTS': int(self._get_env('MAX_LOGIN_ATTEMPTS', '5')),
            'LOGIN_LOCKOUT_TIME': int(self._get_env('LOGIN_LOCKOUT_TIME', '900')),
            'PASSWORD_MIN_LENGTH': int(self._get_env('PASSWORD_MIN_LENGTH', '8')),
            
            # Email Verification
            'VERIFICATION_CODE_EXPIRY': int(self._get_env('VERIFICATION_CODE_EXPIRY', '900')),
            'EMAIL_DEBUG_MODE': self._get_env('EMAIL_DEBUG_MODE', 'false').lower() == 'true',
            
            # File Upload Security
            'MAX_PROFILE_PICTURE_SIZE': int(self._get_env('MAX_PROFILE_PICTURE_SIZE', '2097152')),
            'ALLOWED_IMAGE_TYPES': self._get_env('ALLOWED_IMAGE_TYPES', 'image/jpeg,image/png,image/gif,image/webp').split(','),
            
            # Logging Configuration
            'LOG_STUDENT_ACTIONS': self._get_env('LOG_STUDENT_ACTIONS', 'true').lower() == 'true',
            'LOG_ADMIN_ACTIONS': self._get_env('LOG_ADMIN_ACTIONS', 'true').lower() == 'true',
            'LOG_SECURITY_EVENTS': self._get_env('LOG_SECURITY_EVENTS', 'true').lower() == 'true',
            
            # Rate Limiting
            'RATE_LIMIT_ENABLED': self._get_env('RATE_LIMIT_ENABLED', 'true').lower() == 'true',
            'RATE_LIMIT_REQUESTS': int(self._get_env('RATE_LIMIT_REQUESTS', '100')),
            'RATE_LIMIT_WINDOW': int(self._get_env('RATE_LIMIT_WINDOW', '3600')),
            
            # CORS Configuration
            'ALLOWED_ORIGINS': self._get_env('ALLOWED_ORIGINS', 'http://localhost,https://yourdomain.com').split(','),
            'CORS_CREDENTIALS': self._get_env('CORS_CREDENTIALS', 'true').lower() == 'true',
            
            # Backup Configuration
            'BACKUP_ENABLED': self._get_env('BACKUP_ENABLED', 'true').lower() == 'true',
            'BACKUP_RETENTION_DAYS': int(self._get_env('BACKUP_RETENTION_DAYS', '30')),
            'BACKUP_PATH': self._get_env('BACKUP_PATH', 'backups/'),
            
            # Monitoring
            'MONITORING_ENABLED': self._get_env('MONITORING_ENABLED', 'true').lower() == 'true',
            'ALERT_EMAIL': self._get_env('ALERT_EMAIL', 'admin@yourdomain.com'),
        }
        
        self._loaded = True
        return self._config
    
    def _load_from_env_file(self):
        """Load environment variables from config.env file"""
        try:
            with open(self.env_file, 'r', encoding='utf-8') as f:
                for line in f:
                    line = line.strip()
                    if line and not line.startswith('#'):
                        if '=' in line:
                            key, value = line.split('=', 1)
                            key = key.strip()
                            value = value.strip()
                            
                            # Remove quotes if present
                            if ((value.startswith('"') and value.endswith('"')) or 
                                (value.startswith("'") and value.endswith("'"))):
                                value = value[1:-1]
                            
                            os.environ[key] = value
        except Exception as e:
            print(f"Error loading config.env file: {e}")
    
    def _get_env(self, key: str, default: Any = None) -> str:
        """Get environment variable with fallback to default"""
        return os.environ.get(key, default)
    
    def _generate_secure_key(self, length: int = 16) -> str:
        """Generate a secure random key"""
        return secrets.token_hex(length)
    
    def get(self, key: str, default: Any = None) -> Any:
        """Get a configuration value"""
        if not self._loaded:
            self.load()
        return self._config.get(key, default)
    
    def get_database_config(self) -> Dict[str, str]:
        """Get database configuration"""
        return {
            'host': self.get('DB_HOST'),
            'database': self.get('DB_NAME'),
            'user': self.get('DB_USER'),
            'password': self.get('DB_PASS'),
            'port': int(self.get('DB_PORT', '3306'))
        }
    
    def get_api_config(self) -> Dict[str, str]:
        """Get API configuration"""
        return {
            'key': self.get('API_KEY'),
            'secret': self.get('API_SECRET'),
            'jwt_secret': self.get('JWT_SECRET'),
            'encryption_key': self.get('ENCRYPTION_KEY')
        }
    
    def get_smtp_config(self) -> Dict[str, str]:
        """Get SMTP configuration"""
        return {
            'host': self.get('SMTP_HOST'),
            'port': int(self.get('SMTP_PORT', '587')),
            'username': self.get('SMTP_USERNAME'),
            'password': self.get('SMTP_PASSWORD')
        }
    
    def is_secure(self) -> bool:
        """Check if configuration is secure (no default values)"""
        if not self._loaded:
            self.load()
        
        # Check for default/insecure values
        insecure_keys = []
        
        if self.get('DB_PASS') == '':
            insecure_keys.append('DB_PASS')
        
        if self.get('SMTP_USERNAME') == '':
            insecure_keys.append('SMTP_USERNAME')
        
        if self.get('SMTP_PASSWORD') == '':
            insecure_keys.append('SMTP_PASSWORD')
        
        if self.get('API_KEY') == self._generate_secure_key():
            insecure_keys.append('API_KEY')
        
        if self.get('JWT_SECRET') == self._generate_secure_key(32):
            insecure_keys.append('JWT_SECRET')
        
        return len(insecure_keys) == 0, insecure_keys
    
    def validate_config(self) -> Dict[str, Any]:
        """Validate configuration and return status"""
        if not self._loaded:
            self.load()
        
        validation = {
            'valid': True,
            'errors': [],
            'warnings': [],
            'security_status': 'unknown'
        }
        
        # Check required fields
        required_fields = ['DB_HOST', 'DB_NAME', 'DB_USER', 'API_KEY']
        for field in required_fields:
            if not self.get(field):
                validation['errors'].append(f"Required field {field} is missing")
                validation['valid'] = False
        
        # Check security
        is_secure, insecure_keys = self.is_secure()
        if not is_secure:
            validation['warnings'].extend([f"{key} is using default/insecure value" for key in insecure_keys])
            validation['security_status'] = 'insecure'
        else:
            validation['security_status'] = 'secure'
        
        # Check database connection
        try:
            import pymysql
            db_config = self.get_database_config()
            conn = pymysql.connect(**db_config)
            conn.close()
        except Exception as e:
            validation['errors'].append(f"Database connection failed: {e}")
            validation['valid'] = False
        
        return validation


# Global configuration instance
config = SecureConfig()


# Convenience functions
def get_config(key: str, default: Any = None) -> Any:
    """Get a configuration value"""
    return config.get(key, default)


def get_database_config() -> Dict[str, str]:
    """Get database configuration"""
    return config.get_database_config()


def get_api_config() -> Dict[str, str]:
    """Get API configuration"""
    return config.get_api_config()


def get_smtp_config() -> Dict[str, str]:
    """Get SMTP configuration"""
    return config.get_smtp_config()


def is_config_secure() -> tuple[bool, list]:
    """Check if configuration is secure"""
    return config.is_secure()


def validate_configuration() -> Dict[str, Any]:
    """Validate configuration"""
    return config.validate_config()


# Example usage and testing
if __name__ == "__main__":
    print("Secure Configuration Manager Test")
    print("=" * 50)
    
    # Load configuration
    print("Loading configuration...")
    all_config = config.load()
    print(f"Loaded {len(all_config)} configuration values")
    
    # Test specific configurations
    print(f"\nDatabase Host: {config.get('DB_HOST')}")
    print(f"API Key: {config.get('API_KEY')[:20]}..." if config.get('API_KEY') else "API Key: Not set")
    print(f"Debug Mode: {config.get('DEBUG_MODE')}")
    
    # Test database configuration
    print(f"\nDatabase Configuration:")
    db_config = config.get_database_config()
    for key, value in db_config.items():
        if key == 'password':
            value = '[HIDDEN]' if value else '[EMPTY]'
        print(f"  {key}: {value}")
    
    # Test API configuration
    print(f"\nAPI Configuration:")
    api_config = config.get_api_config()
    for key, value in api_config.items():
        if 'secret' in key.lower() or 'key' in key.lower():
            value = f"{value[:20]}..." if value else "[NOT SET]"
        print(f"  {key}: {value}")
    
    # Test security
    print(f"\nSecurity Check:")
    is_secure, insecure_keys = config.is_secure()
    print(f"Configuration is secure: {is_secure}")
    if insecure_keys:
        print(f"Insecure keys: {', '.join(insecure_keys)}")
    
    # Test validation
    print(f"\nConfiguration Validation:")
    validation = config.validate_config()
    print(f"Valid: {validation['valid']}")
    print(f"Security Status: {validation['security_status']}")
    if validation['errors']:
        print(f"Errors: {validation['errors']}")
    if validation['warnings']:
        print(f"Warnings: {validation['warnings']}")
    
    print(f"\nSecure configuration manager ready!")
