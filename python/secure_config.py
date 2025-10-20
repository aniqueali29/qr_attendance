import os
import json
import base64
import hashlib
from cryptography.fernet import Fernet
from cryptography.hazmat.primitives import hashes
from cryptography.hazmat.primitives.kdf.pbkdf2 import PBKDF2HMAC

# Try to load from config.env file
try:
    from dotenv import load_dotenv
    # Load from parent directory config.env
    config_env_path = os.path.join(os.path.dirname(os.path.dirname(__file__)), 'config.env')
    if os.path.exists(config_env_path):
        load_dotenv(config_env_path)
except ImportError:
    # dotenv not available, continue without it
    pass

class SecureConfig:
    """Enhanced secure configuration manager with encryption."""
    
    def __init__(self, config_file='config.secure', key_file='.encryption.key'):
        self.config_file = config_file
        self.key_file = key_file
        self._config = {}
        self._fernet = None
        self._load_or_create_keys()
        self._load_config()
    
    def _load_or_create_keys(self):
        """Load or create encryption keys with enhanced security."""
        try:
            # Try to load existing key
            if os.path.exists(self.key_file):
                with open(self.key_file, 'rb') as f:
                    key = f.read()
            else:
                # Generate new key
                key = Fernet.generate_key()
                with open(self.key_file, 'wb') as f:
                    f.write(key)
                # Set secure permissions
                os.chmod(self.key_file, 0o600)
            
            self._fernet = Fernet(key)
            
        except Exception as e:
            print(f"❌ Error loading/creating encryption keys: {e}")
            # Fallback to basic encoding (not secure but functional)
            self._fernet = None
    
    def _encrypt_value(self, value):
        """Encrypt a value with enhanced error handling."""
        try:
            if self._fernet:
                return self._fernet.encrypt(value.encode()).decode()
            else:
                # Fallback to base64 encoding
                return base64.b64encode(value.encode()).decode()
        except Exception as e:
            print(f"❌ Error encrypting value: {e}")
            return value
    
    def _decrypt_value(self, encrypted_value):
        """Decrypt a value with enhanced error handling."""
        try:
            if self._fernet:
                return self._fernet.decrypt(encrypted_value.encode()).decode()
            else:
                # Fallback to base64 decoding
                return base64.b64decode(encrypted_value.encode()).decode()
        except Exception:
            # Return as-is if decryption fails (might be plaintext)
            return encrypted_value
    
    def _load_config(self):
        """Load configuration from encrypted file."""
        try:
            if not os.path.exists(self.config_file):
                print(f"⚠️ Config file not found. Creating: {self.config_file}")
                self._config = self._get_default_config()
                self._save_config()
                return
            
            with open(self.config_file, 'r', encoding='utf-8') as f:
                encrypted_data = f.read()
            
            if encrypted_data:
                # Try to decrypt the entire file
                try:
                    decrypted_data = self._decrypt_value(encrypted_data)
                    self._config = json.loads(decrypted_data)
                except:
                    # File might be in plaintext (migration scenario)
                    self._config = json.loads(encrypted_data)
                    # Re-save encrypted
                    self._save_config()
            else:
                self._config = self._get_default_config()
                
            print(f"✅ Secure config loaded from {self.config_file}")
            
        except Exception as e:
            print(f"❌ Error loading secure config: {e}")
            self._config = self._get_default_config()
    
    def _save_config(self):
        """Save configuration to encrypted file."""
        try:
            # Encrypt the entire config
            config_json = json.dumps(self._config, indent=2)
            encrypted_data = self._encrypt_value(config_json)
            
            with open(self.config_file, 'w', encoding='utf-8') as f:
                f.write(encrypted_data)
            
            # Set secure permissions
            os.chmod(self.config_file, 0o600)
            print(f"✅ Secure config saved to {self.config_file}")
            
        except Exception as e:
            print(f"❌ Error saving secure config: {e}")
    
    def _get_default_config(self):
        """Get default secure configuration."""
        return {
            'api': {
                'key': 'default_api_key_' + hashlib.sha256(os.urandom(32)).hexdigest()[:16],
                'secret': 'default_api_secret_' + hashlib.sha256(os.urandom(32)).hexdigest()[:32],
                'jwt_secret': 'default_jwt_secret_' + hashlib.sha256(os.urandom(32)).hexdigest()[:32],
                'encryption_key': base64.urlsafe_b64encode(os.urandom(32)).decode()
            },
            'website': {
                'url': 'http://localhost/qr_attendance/public'
            },
            'security': {
                'encryption_enabled': True,
                'ssl_verify': False
            }
        }
    
    def get(self, key, default=None):
        """Get configuration value with enhanced key path support."""
        try:
            # Handle nested keys (e.g., 'api.key')
            keys = key.split('.')
            value = self._config
            for k in keys:
                if isinstance(value, dict) and k in value:
                    value = value[k]
                else:
                    return default
            return value
        except Exception as e:
            print(f"❌ Error getting config {key}: {e}")
            return default
    
    def set(self, key, value):
        """Set configuration value with enhanced key path support."""
        try:
            keys = key.split('.')
            config = self._config
            
            # Navigate to the parent level
            for k in keys[:-1]:
                if k not in config or not isinstance(config[k], dict):
                    config[k] = {}
                config = config[k]
            
            # Set the value
            config[keys[-1]] = value
            
            # Save changes
            self._save_config()
            return True
            
        except Exception as e:
            print(f"❌ Error setting config {key}: {e}")
            return False
    
    def get_api_config(self):
        """Get API configuration with enhanced validation."""
        return {
            'key': self.get('api.key', 'default_api_key'),
            'secret': self.get('api.secret', 'default_api_secret'),
            'jwt_secret': self.get('api.jwt_secret', 'default_jwt_secret'),
            'encryption_key': self.get('api.encryption_key', base64.urlsafe_b64encode(os.urandom(32)).decode())
        }

# Global secure config instance
_secure_config = SecureConfig()

# Public functions
def get_config(key, default=None):
    """Get configuration value from secure storage or environment variables."""
    # First try to get from environment variables
    env_value = os.getenv(key)
    if env_value is not None:
        return env_value
    
    # Fall back to secure storage
    return _secure_config.get(key, default)

def set_config(key, value):
    """Set configuration value in secure storage."""
    return _secure_config.set(key, value)

def get_api_config():
    """Get API configuration from environment variables or secure storage."""
    return {
        'key': get_config('API_KEY', _secure_config.get_api_config()['key']),
        'secret': get_config('API_SECRET', _secure_config.get_api_config()['secret']),
        'jwt_secret': get_config('JWT_SECRET', _secure_config.get_api_config()['jwt_secret']),
        'encryption_key': get_config('ENCRYPTION_KEY', _secure_config.get_api_config()['encryption_key'])
    }

def reload_config():
    """Reload configuration from file."""
    _secure_config._load_config()

if __name__ == "__main__":
    # Test the secure config
    print("Secure Config Test:")
    api_config = get_api_config()
    print(f"API Key: {api_config['key'][:10]}...")
    print(f"API Secret: {api_config['secret'][:10]}...")
    print(f"Website URL: {get_config('website.url')}")