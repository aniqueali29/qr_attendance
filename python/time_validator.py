from datetime import datetime, time
import pytz
from settings import get_setting

# Timezone Configuration
TIMEZONE = pytz.timezone(get_setting('timezone', 'Asia/Karachi'))

def get_current_time():
    """Get current time in configured timezone."""
    return datetime.now(TIMEZONE)

def parse_time_string(time_str):
    """Parse time string in 12-hour or 24-hour format."""
    try:
        # Try 12-hour format with AM/PM
        time_formats = [
            "%I:%M %p",    # 02:30 PM
            "%I:%M:%S %p", # 02:30:45 PM
            "%H:%M",       # 14:30
            "%H:%M:%S"     # 14:30:45
        ]
        
        for fmt in time_formats:
            try:
                parsed_time = datetime.strptime(time_str, fmt).time()
                return parsed_time
            except ValueError:
                continue
        
        raise ValueError(f"Unable to parse time: {time_str}")
    except Exception as e:
        print(f"❌ Error parsing time '{time_str}': {e}")
        return None

def is_time_between(check_time, start_time_str, end_time_str):
    """Check if current time is between start and end times with enhanced validation."""
    try:
        current_time = get_current_time().time()
        
        # Parse start and end times
        start_time = parse_time_string(start_time_str)
        end_time = parse_time_string(end_time_str)
        
        if not start_time or not end_time:
            return False
        
        # Handle overnight time ranges
        if start_time < end_time:
            # Normal range within same day
            return start_time <= current_time <= end_time
        else:
            # Overnight range (e.g., 10 PM to 6 AM)
            return current_time >= start_time or current_time <= end_time
            
    except Exception as e:
        print(f"❌ Error checking time range: {e}")
        return False

def validate_checkin_time():
    """Validate if current time is within check-in hours with enhanced logic."""
    try:
        # Use the actual settings from JSON file
        start_time = get_setting('morning_checkin_start', '09:00')
        end_time = get_setting('morning_checkin_end', '12:00')
        
        if not start_time or not end_time:
            print("⚠️ Check-in time settings not configured")
            return True  # Allow if not configured
        
        return is_time_between(get_current_time().time(), start_time, end_time)
        
    except Exception as e:
        print(f"❌ Error validating check-in time: {e}")
        return False

def validate_checkout_time():
    """Validate if current time is within check-out hours with enhanced logic."""
    try:
        # Use the actual settings from JSON file
        start_time = get_setting('morning_checkout_start', '12:00')
        end_time = get_setting('morning_checkout_end', '13:40')
        
        if not start_time or not end_time:
            print("⚠️ Check-out time settings not configured")
            return True  # Allow if not configured
        
        return is_time_between(get_current_time().time(), start_time, end_time)
        
    except Exception as e:
        print(f"❌ Error validating check-out time: {e}")
        return False

def format_time_12hour(time_str):
    """Convert 24-hour format to 12-hour format for display."""
    try:
        # Parse the time string
        if ':' in time_str:
            hour, minute = time_str.split(':')
            hour = int(hour)
            minute = int(minute)
            
            # Convert to 12-hour format
            if hour == 0:
                return f"12:{minute:02d} AM"
            elif hour < 12:
                return f"{hour}:{minute:02d} AM"
            elif hour == 12:
                return f"12:{minute:02d} PM"
            else:
                return f"{hour-12}:{minute:02d} PM"
        else:
            return time_str
    except Exception as e:
        print(f"❌ Error formatting time '{time_str}': {e}")
        return time_str

def validate_checkin_time_by_shift(shift):
    """Validate if current time is within check-in hours for specific shift."""
    try:
        # Load appropriate time windows based on shift
        if shift.lower() == 'morning':
            start_time = get_setting('morning_checkin_start', '09:00')
            end_time = get_setting('morning_checkin_end', '12:00')
        elif shift.lower() == 'evening':
            start_time = get_setting('evening_checkin_start', '09:00')
            end_time = get_setting('evening_checkin_end', '12:00')
        else:
            # Default to morning if shift not recognized
            start_time = get_setting('morning_checkin_start', '09:00')
            end_time = get_setting('morning_checkin_end', '12:00')
        
        if not start_time or not end_time:
            return True, "Check-in time settings not configured"
        
        # Check if current time is within window
        is_valid = is_time_between(get_current_time().time(), start_time, end_time)
        
        if is_valid:
            return True, "Check-in allowed"
        else:
            # Format error message with time windows
            current_time = get_current_time().strftime("%I:%M %p")
            start_formatted = format_time_12hour(start_time)
            end_formatted = format_time_12hour(end_time)
            
            error_msg = f"Check-in allowed between {start_formatted} - {end_formatted}. Current time: {current_time}. Please try during this window."
            return False, error_msg
        
    except Exception as e:
        return False, f"Error validating check-in time: {e}"

def validate_checkout_time_by_shift(shift):
    """Validate if current time is within check-out hours for specific shift."""
    try:
        # Load appropriate time windows based on shift
        if shift.lower() == 'morning':
            start_time = get_setting('morning_checkout_start', '12:00')
            end_time = get_setting('morning_checkout_end', '13:40')
        elif shift.lower() == 'evening':
            start_time = get_setting('evening_checkout_start', '09:00')
            end_time = get_setting('evening_checkout_end', '14:00')
        else:
            # Default to morning if shift not recognized
            start_time = get_setting('morning_checkout_start', '12:00')
            end_time = get_setting('morning_checkout_end', '13:40')
        
        if not start_time or not end_time:
            return True, "Check-out time settings not configured"
        
        # Check if current time is within window
        is_valid = is_time_between(get_current_time().time(), start_time, end_time)
        
        if is_valid:
            return True, "Check-out allowed"
        else:
            # Format error message with time windows
            current_time = get_current_time().strftime("%I:%M %p")
            start_formatted = format_time_12hour(start_time)
            end_formatted = format_time_12hour(end_time)
            
            error_msg = f"Check-out allowed between {start_formatted} - {end_formatted}. Current time: {current_time}. Please try during this window."
            return False, error_msg
        
    except Exception as e:
        return False, f"Error validating check-out time: {e}"

def get_current_operation_window():
    """Get which operations are currently allowed based on all time windows."""
    try:
        current_time = get_current_time().time()
        allowed_operations = []
        
        # Check morning shift windows
        morning_checkin_start = parse_time_string(get_setting('morning_checkin_start', '09:00'))
        morning_checkin_end = parse_time_string(get_setting('morning_checkin_end', '12:00'))
        morning_checkout_start = parse_time_string(get_setting('morning_checkout_start', '12:00'))
        morning_checkout_end = parse_time_string(get_setting('morning_checkout_end', '13:40'))
        
        # Check evening shift windows
        evening_checkin_start = parse_time_string(get_setting('evening_checkin_start', '09:00'))
        evening_checkin_end = parse_time_string(get_setting('evening_checkin_end', '12:00'))
        evening_checkout_start = parse_time_string(get_setting('evening_checkout_start', '09:00'))
        evening_checkout_end = parse_time_string(get_setting('evening_checkout_end', '14:00'))
        
        # Check if current time falls in any check-in window
        if (morning_checkin_start and morning_checkin_end and 
            morning_checkin_start <= current_time <= morning_checkin_end):
            allowed_operations.append('checkin')
        
        if (evening_checkin_start and evening_checkin_end and 
            evening_checkin_start <= current_time <= evening_checkin_end):
            if 'checkin' not in allowed_operations:
                allowed_operations.append('checkin')
        
        # Check if current time falls in any check-out window
        if (morning_checkout_start and morning_checkout_end and 
            morning_checkout_start <= current_time <= morning_checkout_end):
            allowed_operations.append('checkout')
        
        if (evening_checkout_start and evening_checkout_end and 
            evening_checkout_start <= current_time <= evening_checkout_end):
            if 'checkout' not in allowed_operations:
                allowed_operations.append('checkout')
        
        return allowed_operations
        
    except Exception as e:
        print(f"❌ Error getting current operation window: {e}")
        return []

def get_time_restrictions():
    """Get current time restrictions for user feedback."""
    return {
        'check_in_start': get_setting('morning_checkin_start', '09:00'),
        'check_in_end': get_setting('morning_checkin_end', '12:00'),
        'check_out_start': get_setting('morning_checkout_start', '12:00'),
        'check_out_end': get_setting('morning_checkout_end', '13:40')
    }

if __name__ == "__main__":
    # Test the time validator
    print("Time Validator Test:")
    print(f"Current Time: {get_current_time()}")
    print(f"Can Check-in: {validate_checkin_time()}")
    print(f"Can Check-out: {validate_checkout_time()}")
    print(f"Time Restrictions: {get_time_restrictions()}")