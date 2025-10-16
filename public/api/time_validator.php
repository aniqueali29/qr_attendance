<?php
/**
 * Time Validator for College Attendance System
 * Validates check-in times based on shift timings
 */

class TimeValidator {
    
    // Shift timing constants
    const MORNING_CHECKIN_START = '09:00:00';    // 9:00 AM
    const MORNING_CHECKIN_END = '11:00:00';     // 11:00 AM
    const MORNING_CLASS_END = '13:40:00';       // 1:40 PM
    const MORNING_CHECKOUT_START = '12:00:00';   // 12:00 PM (12pm)
    const MORNING_CHECKOUT_END = '13:40:00';    // 1:40 PM
    
    const EVENING_CHECKIN_START = '09:00:00';   // 9:00 AM
    const EVENING_CHECKIN_END = '12:00:00';     // 12:00 PM (12 PM)
    const EVENING_CLASS_END = '14:00:00';       // 2:00 PM
    
    const MINIMUM_DURATION_MINUTES = 120;  // 2 hours minimum
    
    private $timezone;
    
    public function __construct($timezone = 'Asia/Karachi') {
        $this->timezone = new DateTimeZone($timezone);
    }
    
    /**
     * Get timing information for a specific shift
     * 
     * @param string $shift 'Morning' or 'Evening'
     * @return array Timing information for the shift
     */
    public function getShiftTimings($shift) {
        if (strtolower($shift) === 'morning') {
            return [
                'shift' => 'Morning',
                'checkin_start' => self::MORNING_CHECKIN_START,
                'checkin_end' => self::MORNING_CHECKIN_END,
                'checkout_start' => self::MORNING_CHECKOUT_START,
                'checkout_end' => self::MORNING_CHECKOUT_END,
                'class_end' => self::MORNING_CLASS_END,
                'checkin_window_hours' => 2,
                'total_class_hours' => 4.67  // 4 hours 40 minutes
            ];
        } elseif (strtolower($shift) === 'evening') {
            return [
                'shift' => 'Evening',
                'checkin_start' => self::EVENING_CHECKIN_START,
                'checkin_end' => self::EVENING_CHECKIN_END,
                'checkout_start' => self::EVENING_CHECKIN_START,  // Same as checkin for free access
                'checkout_end' => self::EVENING_CLASS_END,
                'class_end' => self::EVENING_CLASS_END,
                'checkin_window_hours' => 3,  // 9 AM to 12 PM = 3 hours
                'total_class_hours' => 5  // 9 AM to 2 PM = 5 hours
            ];
        } else {
            throw new InvalidArgumentException("Invalid shift: {$shift}. Must be 'Morning' or 'Evening'");
        }
    }
    
    /**
     * Check if current time is within the allowed check-in window for the shift
     * 
     * @param DateTime $current_time Current time to check
     * @param string $shift 'Morning' or 'Evening'
     * @return bool True if within check-in window
     */
    public function isWithinCheckinWindow($current_time, $shift) {
        // Convert to timezone if needed
        if ($current_time->getTimezone()->getName() !== $this->timezone->getName()) {
            $current_time->setTimezone($this->timezone);
        }
        
        $current_time_only = $current_time->format('H:i:s');
        $timings = $this->getShiftTimings($shift);
        
        return $current_time_only >= $timings['checkin_start'] && 
               $current_time_only <= $timings['checkin_end'];
    }
    
    /**
     * Check if current time is within the allowed check-out window for the shift
     * 
     * @param DateTime $current_time Current time to check
     * @param string $shift 'Morning' or 'Evening'
     * @return bool True if within check-out window
     */
    public function isWithinCheckoutWindow($current_time, $shift) {
        // Convert to timezone if needed
        if ($current_time->getTimezone()->getName() !== $this->timezone->getName()) {
            $current_time->setTimezone($this->timezone);
        }
        
        $current_time_only = $current_time->format('H:i:s');
        $timings = $this->getShiftTimings($shift);
        
        return $current_time_only >= $timings['checkout_start'] && 
               $current_time_only <= $timings['checkout_end'];
    }
    
    /**
     * Validate if a student can check in at the current time
     * 
     * @param string $student_id Student ID
     * @param DateTime|null $current_time Current time. Defaults to now
     * @param string|null $shift Student's shift. If not provided, will be determined from roll number
     * @return array Validation result with timing information
     */
    public function validateCheckinTime($student_id, $current_time = null, $shift = null) {
        if ($current_time === null) {
            $current_time = new DateTime('now', $this->timezone);
        }
        
        // If shift not provided, try to determine from roll number
        if ($shift === null) {
            try {
                require_once 'roll_parser.php';
                $shift = RollParser::getShift($student_id);
            } catch (Exception $e) {
                // Fallback to morning if roll parser not available
                $shift = 'Morning';
            }
        }
        
        // Get shift timings
        try {
            $timings = $this->getShiftTimings($shift);
        } catch (InvalidArgumentException $e) {
            return [
                'valid' => false,
                'error' => $e->getMessage(),
                'student_id' => $student_id,
                'current_time' => $current_time->format('Y-m-d H:i:s')
            ];
        }
        
        // Check if within check-in window
        $is_within_window = $this->isWithinCheckinWindow($current_time, $shift);
        
        // Calculate time until window closes
        $current_time_only = $current_time->format('H:i:s');
        $time_until_close = null;
        if ($is_within_window) {
            // Calculate minutes until window closes
            $checkin_end = $timings['checkin_end'];
            if ($current_time_only < $checkin_end) {
                $end_time = new DateTime($current_time->format('Y-m-d') . ' ' . $checkin_end, $this->timezone);
                $time_diff = $end_time->diff($current_time);
                $time_until_close = ($time_diff->h * 60) + $time_diff->i;
            }
        }
        
        return [
            'valid' => $is_within_window,
            'student_id' => $student_id,
            'shift' => $shift,
            'current_time' => $current_time->format('Y-m-d H:i:s'),
            'checkin_start' => $timings['checkin_start'],
            'checkin_end' => $timings['checkin_end'],
            'class_end' => $timings['class_end'],
            'is_within_window' => $is_within_window,
            'time_until_close' => $time_until_close,
            'error' => $is_within_window ? null : "Check-in not allowed. Window: {$timings['checkin_start']} - {$timings['checkin_end']}"
        ];
    }
    
    /**
     * Validate if a student can check out at the current time
     * 
     * @param string $student_id Student ID
     * @param DateTime|null $current_time Current time. Defaults to now
     * @param string|null $shift Student's shift. If not provided, will be determined from roll number
     * @return array Validation result with timing information
     */
    public function validateCheckoutTime($student_id, $current_time = null, $shift = null) {
        if ($current_time === null) {
            $current_time = new DateTime('now', $this->timezone);
        }
        
        // If shift not provided, try to determine from roll number
        if ($shift === null) {
            try {
                require_once 'roll_parser.php';
                $shift = RollParser::getShift($student_id);
            } catch (Exception $e) {
                // Fallback to morning if roll parser not available
                $shift = 'Morning';
            }
        }
        
        // Get shift timings
        try {
            $timings = $this->getShiftTimings($shift);
        } catch (InvalidArgumentException $e) {
            return [
                'valid' => false,
                'error' => $e->getMessage(),
                'student_id' => $student_id,
                'current_time' => $current_time->format('Y-m-d H:i:s')
            ];
        }
        
        // Check if within check-out window
        $is_within_window = $this->isWithinCheckoutWindow($current_time, $shift);
        
        // Calculate time until window closes
        $current_time_only = $current_time->format('H:i:s');
        $time_until_close = null;
        if ($is_within_window) {
            // Calculate minutes until window closes
            $checkout_end = $timings['checkout_end'];
            if ($current_time_only < $checkout_end) {
                $end_time = new DateTime($current_time->format('Y-m-d') . ' ' . $checkout_end, $this->timezone);
                $time_diff = $end_time->diff($current_time);
                $time_until_close = ($time_diff->h * 60) + $time_diff->i;
            }
        }
        
        return [
            'valid' => $is_within_window,
            'student_id' => $student_id,
            'shift' => $shift,
            'current_time' => $current_time->format('Y-m-d H:i:s'),
            'checkout_start' => $timings['checkout_start'],
            'checkout_end' => $timings['checkout_end'],
            'class_end' => $timings['class_end'],
            'is_within_window' => $is_within_window,
            'time_until_close' => $time_until_close,
            'error' => $is_within_window ? null : "Check-out not allowed. Window: {$timings['checkout_start']} - {$timings['checkout_end']}"
        ];
    }
    
    /**
     * Get information about the next check-in window for a shift
     * 
     * @param string $shift 'Morning' or 'Evening'
     * @param DateTime|null $current_time Current time. Defaults to now
     * @return array Next check-in window information
     */
    public function getNextCheckinWindow($shift, $current_time = null) {
        if ($current_time === null) {
            $current_time = new DateTime('now', $this->timezone);
        }
        
        $timings = $this->getShiftTimings($shift);
        $current_time_only = $current_time->format('H:i:s');
        
        // Check if we're in today's window
        if ($current_time_only >= $timings['checkin_start'] && $current_time_only <= $timings['checkin_end']) {
            $end_time = new DateTime($current_time->format('Y-m-d') . ' ' . $timings['checkin_end'], $this->timezone);
            $time_diff = $end_time->diff($current_time);
            $time_remaining = ($time_diff->h * 60) + $time_diff->i;
            
            return [
                'is_current_window' => true,
                'window_start' => $timings['checkin_start'],
                'window_end' => $timings['checkin_end'],
                'time_remaining' => $time_remaining,
                'message' => 'Currently in check-in window'
            ];
        }
        
        // Check if we're before today's window
        if ($current_time_only < $timings['checkin_start']) {
            $start_time = new DateTime($current_time->format('Y-m-d') . ' ' . $timings['checkin_start'], $this->timezone);
            $time_diff = $start_time->diff($current_time);
            $time_until_start = ($time_diff->h * 60) + $time_diff->i;
            
            return [
                'is_current_window' => false,
                'window_start' => $timings['checkin_start'],
                'window_end' => $timings['checkin_end'],
                'time_until_start' => $time_until_start,
                'message' => "Check-in window starts in {$time_until_start} minutes"
            ];
        }
        
        // We're after today's window, next window is tomorrow
        $tomorrow = clone $current_time;
        $tomorrow->setTime(0, 0, 0);
        $tomorrow->add(new DateInterval('P1D'));
        
        $next_window_start = clone $tomorrow;
        $next_window_start->setTime(
            intval(substr($timings['checkin_start'], 0, 2)),
            intval(substr($timings['checkin_start'], 3, 2)),
            intval(substr($timings['checkin_start'], 6, 2))
        );
        
        $time_diff = $next_window_start->diff($current_time);
        $time_until_tomorrow = ($time_diff->h * 60) + $time_diff->i;
        
        return [
            'is_current_window' => false,
            'window_start' => $timings['checkin_start'],
            'window_end' => $timings['checkin_end'],
            'time_until_start' => $time_until_tomorrow,
            'message' => "Next check-in window is tomorrow at {$timings['checkin_start']}"
        ];
    }
    
    /**
     * Get complete schedule information for a shift
     * 
     * @param string $shift 'Morning' or 'Evening'
     * @return array Complete schedule information
     */
    public function getShiftSchedule($shift) {
        $timings = $this->getShiftTimings($shift);
        
        return [
            'shift' => $shift,
            'schedule' => [
                'checkin_window' => [
                    'start' => $timings['checkin_start'],
                    'end' => $timings['checkin_end'],
                    'duration_hours' => $timings['checkin_window_hours']
                ],
                'class_session' => [
                    'start' => $timings['checkin_start'],
                    'end' => $timings['class_end'],
                    'duration_hours' => $timings['total_class_hours']
                ],
                'minimum_duration' => [
                    'minutes' => self::MINIMUM_DURATION_MINUTES,
                    'hours' => self::MINIMUM_DURATION_MINUTES / 60
                ]
            ],
            'description' => "{$shift} shift: Check-in {$timings['checkin_start']}-{$timings['checkin_end']}, Class until {$timings['class_end']}"
        ];
    }
    
    /**
     * Check if a check-out is allowed (minimum duration met)
     * 
     * @param DateTime $checkin_time Time when student checked in
     * @param DateTime $checkout_time Time when student wants to check out
     * @return array Check-out validation result
     */
    public function validateCheckoutDuration($checkin_time, $checkout_time) {
        $duration = $checkout_time->diff($checkin_time);
        $total_minutes = ($duration->h * 60) + $duration->i;
        
        $is_allowed = $total_minutes >= self::MINIMUM_DURATION_MINUTES;
        $remaining_minutes = max(0, self::MINIMUM_DURATION_MINUTES - $total_minutes);
        
        return [
            'valid' => $is_allowed,
            'checkin_time' => $checkin_time->format('Y-m-d H:i:s'),
            'checkout_time' => $checkout_time->format('Y-m-d H:i:s'),
            'duration_minutes' => $total_minutes,
            'minimum_required' => self::MINIMUM_DURATION_MINUTES,
            'remaining_minutes' => $remaining_minutes,
            'error' => $is_allowed ? null : "Cannot check out yet. Please wait {$remaining_minutes} more minutes."
        ];
    }
    
    /**
     * Get current shift based on time
     * 
     * @param DateTime|null $current_time Current time. Defaults to now
     * @return string Current shift or 'None'
     */
    public function getCurrentShift($current_time = null) {
        if ($current_time === null) {
            $current_time = new DateTime('now', $this->timezone);
        }
        
        $current_time_only = $current_time->format('H:i:s');
        
        // Check morning shift
        if ($current_time_only >= self::MORNING_CHECKIN_START && $current_time_only <= self::MORNING_CLASS_END) {
            return 'Morning';
        }
        
        // Check evening shift
        if ($current_time_only >= self::EVENING_CHECKIN_START && $current_time_only <= self::EVENING_CLASS_END) {
            return 'Evening';
        }
        
        return 'None';
    }
}

// Standalone functions for easy import
function getShiftTimings($shift) {
    $validator = new TimeValidator();
    return $validator->getShiftTimings($shift);
}

function isWithinCheckinWindow($current_time, $shift) {
    $validator = new TimeValidator();
    return $validator->isWithinCheckinWindow($current_time, $shift);
}

function validateCheckinTime($student_id, $current_time = null, $shift = null) {
    $validator = new TimeValidator();
    return $validator->validateCheckinTime($student_id, $current_time, $shift);
}

// Example usage and testing
if (basename(__FILE__) == basename($_SERVER['SCRIPT_NAME'])) {
    echo "Time Validator Test Results\n";
    echo str_repeat("=", 50) . "\n";
    
    // Test different times and shifts
    $test_times = [];
    $base_time = new DateTime('now', new DateTimeZone('Asia/Karachi'));
    
    $test_times[] = clone $base_time;
    $test_times[0]->setTime(9, 30);   // Morning window
    
    $test_times[] = clone $base_time;
    $test_times[1]->setTime(10, 30);  // Morning window
    
    $test_times[] = clone $base_time;
    $test_times[2]->setTime(11, 30);  // After morning window
    
    $test_times[] = clone $base_time;
    $test_times[3]->setTime(15, 30);  // Evening window
    
    $test_times[] = clone $base_time;
    $test_times[4]->setTime(16, 30);  // After evening window
    
    $test_times[] = clone $base_time;
    $test_times[5]->setTime(8, 30);   // Before morning window
    
    $validator = new TimeValidator();
    
    foreach ($test_times as $test_time) {
        echo "Testing time: " . $test_time->format('H:i') . "\n";
        
        // Test morning shift
        $morning_result = $validator->validateCheckinTime("25-SWT-01", $test_time, "Morning");
        echo "  Morning: " . ($morning_result['valid'] ? '✓' : '✗') . " - " . ($morning_result['error'] ?? 'OK') . "\n";
        
        // Test evening shift
        $evening_result = $validator->validateCheckinTime("25-ESWT-01", $test_time, "Evening");
        echo "  Evening: " . ($evening_result['valid'] ? '✓' : '✗') . " - " . ($evening_result['error'] ?? 'OK') . "\n";
        
        echo "\n";
    }
    
    // Test shift schedules
    echo "Shift Schedules\n";
    echo str_repeat("=", 30) . "\n";
    
    foreach (['Morning', 'Evening'] as $shift) {
        $schedule = $validator->getShiftSchedule($shift);
        echo "{$shift} Shift:\n";
        echo "  Check-in: {$schedule['schedule']['checkin_window']['start']} - {$schedule['schedule']['checkin_window']['end']}\n";
        echo "  Class: {$schedule['schedule']['class_session']['start']} - {$schedule['schedule']['class_session']['end']}\n";
        echo "  Min Duration: {$schedule['schedule']['minimum_duration']['minutes']} minutes\n";
        echo "\n";
    }
    
    // Test next window calculation
    echo "Next Window Information\n";
    echo str_repeat("=", 30) . "\n";
    
    $current_time = new DateTime('now', new DateTimeZone('Asia/Karachi'));
    foreach (['Morning', 'Evening'] as $shift) {
        $next_window = $validator->getNextCheckinWindow($shift, $current_time);
        echo "{$shift} Shift:\n";
        echo "  Current Window: " . ($next_window['is_current_window'] ? 'Yes' : 'No') . "\n";
        echo "  Message: {$next_window['message']}\n";
        if (isset($next_window['time_remaining'])) {
            echo "  Time Remaining: {$next_window['time_remaining']} minutes\n";
        }
        if (isset($next_window['time_until_start'])) {
            echo "  Time Until Start: {$next_window['time_until_start']} minutes\n";
        }
        echo "\n";
    }
}
?>
