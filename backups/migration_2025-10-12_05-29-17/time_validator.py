"""
Time Validator Module for College Attendance System
Validates check-in times based on shift timings
"""

from datetime import datetime, time
from typing import Dict, Optional, Tuple
import pytz
from settings import SettingsManager


class TimeValidator:
    """Time validation for shift-based check-ins"""
    
    def __init__(self, timezone='Asia/Karachi'):
        """Initialize time validator with timezone"""
        self.timezone = pytz.timezone(timezone)
        self.settings = SettingsManager()
        
        # Load timing settings from settings manager
        self._load_timing_constants()
    
    def _load_timing_constants(self):
        """Load timing constants from settings"""
        # Morning shift timings
        morning_start = self.settings.get('morning_checkin_start', '09:00:00')
        morning_end = self.settings.get('morning_checkin_end', '11:00:00')
        morning_checkout_start = self.settings.get('morning_checkout_start', '12:00:00')
        morning_checkout_end = self.settings.get('morning_checkout_end', '13:40:00')
        morning_class_end = self.settings.get('morning_class_end', '13:40:00')
        
        # Evening shift timings
        evening_start = self.settings.get('evening_checkin_start', '15:00:00')
        evening_end = self.settings.get('evening_checkin_end', '18:00:00')
        evening_checkout_start = self.settings.get('evening_checkout_start', '15:00:00')
        evening_checkout_end = self.settings.get('evening_checkout_end', '18:00:00')
        evening_class_end = self.settings.get('evening_class_end', '18:00:00')
        
        # Convert to time objects
        self.MORNING_CHECKIN_START = datetime.strptime(morning_start, '%H:%M:%S').time()
        self.MORNING_CHECKIN_END = datetime.strptime(morning_end, '%H:%M:%S').time()
        self.MORNING_CLASS_END = datetime.strptime(morning_class_end, '%H:%M:%S').time()
        self.MORNING_CHECKOUT_START = datetime.strptime(morning_checkout_start, '%H:%M:%S').time()
        self.MORNING_CHECKOUT_END = datetime.strptime(morning_checkout_end, '%H:%M:%S').time()
        
        self.EVENING_CHECKIN_START = datetime.strptime(evening_start, '%H:%M:%S').time()
        self.EVENING_CHECKIN_END = datetime.strptime(evening_end, '%H:%M:%S').time()
        self.EVENING_CLASS_END = datetime.strptime(evening_class_end, '%H:%M:%S').time()
        self.EVENING_CHECKOUT_START = datetime.strptime(evening_checkout_start, '%H:%M:%S').time()
        self.EVENING_CHECKOUT_END = datetime.strptime(evening_checkout_end, '%H:%M:%S').time()
        
        # Minimum duration from settings
        self.MINIMUM_DURATION_MINUTES = self.settings.get('minimum_duration_minutes', 120)
    
    def get_shift_timings(self, shift: str) -> Dict:
        """
        Get timing information for a specific shift.
        
        Args:
            shift (str): 'Morning' or 'Evening'
        
        Returns:
            Dict: Timing information for the shift
        """
        if shift.lower() == 'morning':
            return {
                'shift': 'Morning',
                'checkin_start': self.MORNING_CHECKIN_START,
                'checkin_end': self.MORNING_CHECKIN_END,
                'checkout_start': self.MORNING_CHECKOUT_START,
                'checkout_end': self.MORNING_CHECKOUT_END,
                'class_end': self.MORNING_CLASS_END,
                'checkin_window_hours': 2,
                'total_class_hours': 4.67  # 4 hours 40 minutes
            }
        elif shift.lower() == 'evening':
            return {
                'shift': 'Evening',
                'checkin_start': self.EVENING_CHECKIN_START,
                'checkin_end': self.EVENING_CHECKIN_END,
                'checkout_start': self.EVENING_CHECKIN_START,  # Same as checkin for free access
                'checkout_end': self.EVENING_CLASS_END,
                'class_end': self.EVENING_CLASS_END,
                'checkin_window_hours': 3,  # Extended to 3 hours
                'total_class_hours': 3
            }
        else:
            raise ValueError(f"Invalid shift: {shift}. Must be 'Morning' or 'Evening'")
    
    def is_within_checkin_window(self, current_time: datetime, shift: str) -> bool:
        """
        Check if current time is within the allowed check-in window for the shift.
        
        Args:
            current_time (datetime): Current time to check
            shift (str): 'Morning' or 'Evening'
        
        Returns:
            bool: True if within check-in window
        """
        # Convert to timezone if needed
        if current_time.tzinfo is None:
            current_time = self.timezone.localize(current_time)
        elif current_time.tzinfo != self.timezone:
            current_time = current_time.astimezone(self.timezone)
        
        current_time_only = current_time.time()
        timings = self.get_shift_timings(shift)
        
        return timings['checkin_start'] <= current_time_only <= timings['checkin_end']
    
    def is_within_checkout_window(self, current_time: datetime, shift: str) -> bool:
        """
        Check if current time is within the allowed check-out window for the shift.
        
        Args:
            current_time (datetime): Current time to check
            shift (str): 'Morning' or 'Evening'
        
        Returns:
            bool: True if within check-out window
        """
        # Convert to timezone if needed
        if current_time.tzinfo is None:
            current_time = self.timezone.localize(current_time)
        elif current_time.tzinfo != self.timezone:
            current_time = current_time.astimezone(self.timezone)
        
        current_time_only = current_time.time()
        timings = self.get_shift_timings(shift)
        
        return timings['checkout_start'] <= current_time_only <= timings['checkout_end']
    
    def validate_checkin_time(self, student_id: str, current_time: Optional[datetime] = None, 
                            shift: Optional[str] = None) -> Dict:
        """
        Validate if a student can check in at the current time.
        
        Args:
            student_id (str): Student ID
            current_time (datetime, optional): Current time. Defaults to now.
            shift (str, optional): Student's shift. If not provided, will be determined from roll number.
        
        Returns:
            Dict: Validation result with timing information
        """
        if current_time is None:
            current_time = datetime.now(self.timezone)
        
        # If shift not provided, try to determine from roll number
        if shift is None:
            try:
                from roll_parser import get_shift
                shift = get_shift(student_id)
            except ImportError:
                # Fallback to morning if roll parser not available
                shift = 'Morning'
        
        # Get shift timings
        try:
            timings = self.get_shift_timings(shift)
        except ValueError as e:
            return {
                'valid': False,
                'error': str(e),
                'student_id': student_id,
                'current_time': current_time.isoformat()
            }
        
        # Check if within check-in window
        is_within_window = self.is_within_checkin_window(current_time, shift)
        
        # Calculate time until window closes
        current_time_only = current_time.time()
        time_until_close = None
        if is_within_window:
            # Calculate minutes until window closes
            checkin_end = timings['checkin_end']
            if current_time_only < checkin_end:
                end_datetime = datetime.combine(current_time.date(), checkin_end)
                if current_time.tzinfo:
                    end_datetime = self.timezone.localize(end_datetime)
                time_diff = end_datetime - current_time
                time_until_close = int(time_diff.total_seconds() / 60)
        
        return {
            'valid': is_within_window,
            'student_id': student_id,
            'shift': shift,
            'current_time': current_time.isoformat(),
            'checkin_start': timings['checkin_start'].strftime('%H:%M'),
            'checkin_end': timings['checkin_end'].strftime('%H:%M'),
            'class_end': timings['class_end'].strftime('%H:%M'),
            'is_within_window': is_within_window,
            'time_until_close': time_until_close,
            'error': None if is_within_window else f"Check-in not allowed. Window: {timings['checkin_start'].strftime('%H:%M')} - {timings['checkin_end'].strftime('%H:%M')}"
        }
    
    def validate_checkout_time(self, student_id: str, current_time: Optional[datetime] = None, 
                             shift: Optional[str] = None) -> Dict:
        """
        Validate if a student can check out at the current time.
        
        Args:
            student_id (str): Student ID
            current_time (datetime, optional): Current time. Defaults to now.
            shift (str, optional): Student's shift. If not provided, will be determined from roll number.
        
        Returns:
            Dict: Validation result with timing information
        """
        if current_time is None:
            current_time = datetime.now(self.timezone)
        
        # If shift not provided, try to determine from roll number
        if shift is None:
            try:
                from roll_parser import get_shift
                shift = get_shift(student_id)
            except ImportError:
                # Fallback to morning if roll parser not available
                shift = 'Morning'
        
        # Get shift timings
        try:
            timings = self.get_shift_timings(shift)
        except ValueError as e:
            return {
                'valid': False,
                'error': str(e),
                'student_id': student_id,
                'current_time': current_time.isoformat()
            }
        
        # Check if within check-out window
        is_within_window = self.is_within_checkout_window(current_time, shift)
        
        # Calculate time until window closes
        current_time_only = current_time.time()
        time_until_close = None
        if is_within_window:
            # Calculate minutes until window closes
            checkout_end = timings['checkout_end']
            if current_time_only < checkout_end:
                end_datetime = datetime.combine(current_time.date(), checkout_end)
                if current_time.tzinfo:
                    end_datetime = self.timezone.localize(end_datetime)
                time_diff = end_datetime - current_time
                time_until_close = int(time_diff.total_seconds() / 60)
        
        return {
            'valid': is_within_window,
            'student_id': student_id,
            'shift': shift,
            'current_time': current_time.isoformat(),
            'checkout_start': timings['checkout_start'].strftime('%H:%M'),
            'checkout_end': timings['checkout_end'].strftime('%H:%M'),
            'class_end': timings['class_end'].strftime('%H:%M'),
            'is_within_window': is_within_window,
            'time_until_close': time_until_close,
            'error': None if is_within_window else f"Check-out not allowed. Window: {timings['checkout_start'].strftime('%H:%M')} - {timings['checkout_end'].strftime('%H:%M')}"
        }
    
    def get_next_checkin_window(self, shift: str, current_time: Optional[datetime] = None) -> Dict:
        """
        Get information about the next check-in window for a shift.
        
        Args:
            shift (str): 'Morning' or 'Evening'
            current_time (datetime, optional): Current time. Defaults to now.
        
        Returns:
            Dict: Next check-in window information
        """
        if current_time is None:
            current_time = datetime.now(self.timezone)
        
        timings = self.get_shift_timings(shift)
        current_time_only = current_time.time()
        
        # Check if we're in today's window
        if timings['checkin_start'] <= current_time_only <= timings['checkin_end']:
            return {
                'is_current_window': True,
                'window_start': timings['checkin_start'].strftime('%H:%M'),
                'window_end': timings['checkin_end'].strftime('%H:%M'),
                'time_remaining': self._calculate_time_remaining(current_time, timings['checkin_end']),
                'message': 'Currently in check-in window'
            }
        
        # Check if we're before today's window
        if current_time_only < timings['checkin_start']:
            time_until_start = self._calculate_time_until(current_time, timings['checkin_start'])
            return {
                'is_current_window': False,
                'window_start': timings['checkin_start'].strftime('%H:%M'),
                'window_end': timings['checkin_end'].strftime('%H:%M'),
                'time_until_start': time_until_start,
                'message': f'Check-in window starts in {time_until_start} minutes'
            }
        
        # We're after today's window, next window is tomorrow
        tomorrow = current_time.replace(hour=0, minute=0, second=0, microsecond=0) + timedelta(days=1)
        next_window_start = tomorrow.replace(
            hour=timings['checkin_start'].hour,
            minute=timings['checkin_start'].minute
        )
        
        time_until_tomorrow = self._calculate_time_until(current_time, next_window_start)
        
        return {
            'is_current_window': False,
            'window_start': timings['checkin_start'].strftime('%H:%M'),
            'window_end': timings['checkin_end'].strftime('%H:%M'),
            'time_until_start': time_until_tomorrow,
            'message': f'Next check-in window is tomorrow at {timings["checkin_start"].strftime("%H:%M")}'
        }
    
    def _calculate_time_remaining(self, current_time: datetime, end_time: time) -> int:
        """Calculate minutes remaining in current window"""
        end_datetime = datetime.combine(current_time.date(), end_time)
        if current_time.tzinfo:
            end_datetime = self.timezone.localize(end_datetime)
        time_diff = end_datetime - current_time
        return max(0, int(time_diff.total_seconds() / 60))
    
    def _calculate_time_until(self, current_time: datetime, target_time: datetime) -> int:
        """Calculate minutes until target time"""
        time_diff = target_time - current_time
        return max(0, int(time_diff.total_seconds() / 60))
    
    def get_shift_schedule(self, shift: str) -> Dict:
        """
        Get complete schedule information for a shift.
        
        Args:
            shift (str): 'Morning' or 'Evening'
        
        Returns:
            Dict: Complete schedule information
        """
        timings = self.get_shift_timings(shift)
        
        return {
            'shift': shift,
            'schedule': {
                'checkin_window': {
                    'start': timings['checkin_start'].strftime('%H:%M'),
                    'end': timings['checkin_end'].strftime('%H:%M'),
                    'duration_hours': timings['checkin_window_hours']
                },
                'checkout_window': {
                    'start': timings['checkout_start'].strftime('%H:%M'),
                    'end': timings['checkout_end'].strftime('%H:%M')
                },
                'class_session': {
                    'start': timings['checkin_start'].strftime('%H:%M'),
                    'end': timings['class_end'].strftime('%H:%M'),
                    'duration_hours': timings['total_class_hours']
                },
                'minimum_duration': {
                    'minutes': self.MINIMUM_DURATION_MINUTES,
                    'hours': self.MINIMUM_DURATION_MINUTES / 60
                }
            },
            'description': f"{shift} shift: Check-in {timings['checkin_start'].strftime('%H:%M')}-{timings['checkin_end'].strftime('%H:%M')}, Check-out {timings['checkout_start'].strftime('%H:%M')}-{timings['checkout_end'].strftime('%H:%M')}, Class until {timings['class_end'].strftime('%H:%M')}"
        }


# Standalone functions for easy import
def get_shift_timings(shift: str) -> Dict:
    """Get timing information for a specific shift"""
    validator = TimeValidator()
    return validator.get_shift_timings(shift)


def is_within_checkin_window(current_time: datetime, shift: str) -> bool:
    """Check if current time is within the allowed check-in window"""
    validator = TimeValidator()
    return validator.is_within_checkin_window(current_time, shift)


def validate_checkin_time(student_id: str, current_time: Optional[datetime] = None, 
                         shift: Optional[str] = None) -> Dict:
    """Validate if a student can check in at the current time"""
    validator = TimeValidator()
    return validator.validate_checkin_time(student_id, current_time, shift)


def validate_checkout_time(student_id: str, current_time: Optional[datetime] = None, 
                          shift: Optional[str] = None) -> Dict:
    """Validate if a student can check out at the current time"""
    validator = TimeValidator()
    return validator.validate_checkout_time(student_id, current_time, shift)


# Example usage and testing
if __name__ == "__main__":
    from datetime import datetime, timedelta
    
    print("Time Validator Test Results")
    print("=" * 50)
    
    # Test different times and shifts
    test_times = [
        datetime.now().replace(hour=9, minute=30),   # Morning window
        datetime.now().replace(hour=10, minute=30),  # Morning window
        datetime.now().replace(hour=11, minute=30),  # After morning window
        datetime.now().replace(hour=15, minute=30),   # Evening window
        datetime.now().replace(hour=16, minute=30),   # After evening window
        datetime.now().replace(hour=8, minute=30),    # Before morning window
    ]
    
    validator = TimeValidator()
    
    for test_time in test_times:
        print(f"Testing time: {test_time.strftime('%H:%M')}")
        
        # Test morning shift
        morning_result = validator.validate_checkin_time("25-SWT-01", test_time, "Morning")
        print(f"  Morning: {'✓' if morning_result['valid'] else '✗'} - {morning_result.get('error', 'OK')}")
        
        # Test evening shift
        evening_result = validator.validate_checkin_time("25-ESWT-01", test_time, "Evening")
        print(f"  Evening: {'✓' if evening_result['valid'] else '✗'} - {evening_result.get('error', 'OK')}")
        
        print()
    
    # Test shift schedules
    print("Shift Schedules")
    print("=" * 30)
    
    for shift in ['Morning', 'Evening']:
        schedule = validator.get_shift_schedule(shift)
        print(f"{shift} Shift:")
        print(f"  Check-in: {schedule['schedule']['checkin_window']['start']} - {schedule['schedule']['checkin_window']['end']}")
        print(f"  Class: {schedule['schedule']['class_session']['start']} - {schedule['schedule']['class_session']['end']}")
        print(f"  Min Duration: {schedule['schedule']['minimum_duration']['minutes']} minutes")
        print()
    
    # Test next window calculation
    print("Next Window Information")
    print("=" * 30)
    
    current_time = datetime.now()
    for shift in ['Morning', 'Evening']:
        next_window = validator.get_next_checkin_window(shift, current_time)
        print(f"{shift} Shift:")
        print(f"  Current Window: {next_window['is_current_window']}")
        print(f"  Message: {next_window['message']}")
        if 'time_remaining' in next_window:
            print(f"  Time Remaining: {next_window['time_remaining']} minutes")
        if 'time_until_start' in next_window:
            print(f"  Time Until Start: {next_window['time_until_start']} minutes")
        print()
