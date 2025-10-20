#!/usr/bin/env python3
"""
QR Code Scanner Module for QR Attendance System
Automatically scans QR codes and processes student check-in/check-out
"""

import cv2
import numpy as np
from pyzbar import pyzbar
import time
import threading
from datetime import datetime
import re
from checkin_manager import CheckInManager
from time_validator import validate_checkin_time, validate_checkout_time
from roll_parser import parse_roll_number, get_shift, get_program, get_academic_year_info
from settings import SettingsManager
import pytz

class QRScanner:
    """QR Code Scanner with automatic processing"""
    
    def __init__(self):
        self.settings = SettingsManager()
        self.checkin_manager = CheckInManager()
        self.is_scanning = False
        self.camera = None
        self.timezone = pytz.timezone(self.settings.get('timezone', 'Asia/Karachi'))
        
        # QR Code validation pattern for roll numbers
        # Format: YY-[E]PROGRAM-NN (e.g., 22-SWT-02, 23-SWT-122, 21-ESWT-122, 20-SWT-86)
        self.roll_number_pattern = re.compile(r'^(\d{2})-E?([A-Z]{2,4})-(\d{2,3})$')
        
        # Allowed programs
        self.allowed_programs = ['SWT', 'CIT', 'ECSE', 'CS', 'IT', 'SE', 'CE', 'EE', 'ME', 'CIVIL', 'CSE']
        
        # Scan history to prevent duplicate processing
        self.scan_history = {}
        self.history_cleanup_interval = 300  # 5 minutes
        
    def validate_roll_number(self, roll_number):
        """Validate roll number format"""
        if not roll_number:
            return False, "Empty roll number"
        
        # Check pattern match
        match = self.roll_number_pattern.match(roll_number.strip())
        if not match:
            return False, f"Invalid roll number format: {roll_number}"
        
        year, program, number = match.groups()
        
        # Validate program
        if program not in self.allowed_programs:
            return False, f"Invalid program: {program}"
        
        # Validate year (should be reasonable)
        year_int = int(year)
        current_year = datetime.now().year % 100
        if year_int < 20 or year_int > current_year + 2:
            return False, f"Invalid year: {year}"
        
        # Validate number
        number_int = int(number)
        if number_int < 1 or number_int > 999:
            return False, f"Invalid student number: {number}"
        
        return True, "Valid roll number"
    
    def process_qr_code(self, qr_data):
        """Process scanned QR code data"""
        try:
            # Clean the QR data
            roll_number = qr_data.strip()
            
            # Check if we've processed this recently (prevent duplicates)
            current_time = time.time()
            if roll_number in self.scan_history:
                last_scan = self.scan_history[roll_number]
                if current_time - last_scan < 10:  # 10 seconds cooldown
                    return False, f"Roll number {roll_number} scanned too recently. Please wait."
            
            # Validate roll number
            is_valid, message = self.validate_roll_number(roll_number)
            if not is_valid:
                return False, message
            
            # Add to scan history
            self.scan_history[roll_number] = current_time
            
            # Clean up old history entries
            self._cleanup_scan_history()
            
            # Get current time for logging
            current_datetime = datetime.now(self.timezone)
            
            print(f"\n🔍 QR Code Scanned: {roll_number}")
            print(f"⏰ Time: {current_datetime.strftime('%Y-%m-%d %H:%M:%S')}")
            
            # Parse roll number to get student info
            try:
                student_info = parse_roll_number(roll_number)
                print(f"👤 Student Info: {student_info}")
            except Exception as e:
                print(f"⚠️ Could not parse student info: {e}")
                student_info = {}
            
            # Check if it's check-in or check-out time
            can_checkin = validate_checkin_time()
            can_checkout = validate_checkout_time()
            
            print(f"🕐 Check-in allowed: {'✅' if can_checkin else '❌'}")
            print(f"🕐 Check-out allowed: {'✅' if can_checkout else '❌'}")
            
            # Determine action based on time and current status
            if can_checkin and not can_checkout:
                # Check-in time
                print(f"🔄 Processing CHECK-IN for {roll_number}...")
                success, message = self.checkin_manager.check_in_student(roll_number)
                if success:
                    print(f"✅ Check-in successful: {message}")
                    return True, f"Check-in successful for {roll_number}"
                else:
                    print(f"❌ Check-in failed: {message}")
                    return False, f"Check-in failed: {message}"
            
            elif can_checkout and not can_checkin:
                # Check-out time
                print(f"🔄 Processing CHECK-OUT for {roll_number}...")
                success, message = self.checkin_manager.check_out_student(roll_number)
                if success:
                    print(f"✅ Check-out successful: {message}")
                    return True, f"Check-out successful for {roll_number}"
                else:
                    print(f"❌ Check-out failed: {message}")
                    return False, f"Check-out failed: {message}"
            
            elif can_checkin and can_checkout:
                # Both times allowed - check current status
                print(f"🔄 Checking current status for {roll_number}...")
                success, status_data = self.checkin_manager.get_student_status(roll_number)
                if success and status_data:
                    current_status = status_data.get('status', 'Unknown')
                    print(f"📊 Current status: {current_status}")
                    
                    if current_status in ['Present', 'Checked-in']:
                        # Student is checked in, perform check-out
                        print(f"🔄 Processing CHECK-OUT for {roll_number}...")
                        success, message = self.checkin_manager.check_out_student(roll_number)
                        if success:
                            print(f"✅ Check-out successful: {message}")
                            return True, f"Check-out successful for {roll_number}"
                        else:
                            print(f"❌ Check-out failed: {message}")
                            return False, f"Check-out failed: {message}"
                    else:
                        # Student is not checked in, perform check-in
                        print(f"🔄 Processing CHECK-IN for {roll_number}...")
                        success, message = self.checkin_manager.check_in_student(roll_number)
                        if success:
                            print(f"✅ Check-in successful: {message}")
                            return True, f"Check-in successful for {roll_number}"
                        else:
                            print(f"❌ Check-in failed: {message}")
                            return False, f"Check-in failed: {message}"
                else:
                    # Could not get status, try check-in
                    print(f"🔄 Status unknown, attempting CHECK-IN for {roll_number}...")
                    success, message = self.checkin_manager.check_in_student(roll_number)
                    if success:
                        print(f"✅ Check-in successful: {message}")
                        return True, f"Check-in successful for {roll_number}"
                    else:
                        print(f"❌ Check-in failed: {message}")
                        return False, f"Check-in failed: {message}"
            
            else:
                # Outside allowed times
                return False, f"Outside check-in/check-out hours for {roll_number}"
            
        except Exception as e:
            print(f"❌ Error processing QR code: {e}")
            return False, f"Error processing QR code: {e}"
    
    def _cleanup_scan_history(self):
        """Clean up old scan history entries"""
        current_time = time.time()
        expired_entries = [
            roll_number for roll_number, timestamp in self.scan_history.items()
            if current_time - timestamp > self.history_cleanup_interval
        ]
        for roll_number in expired_entries:
            del self.scan_history[roll_number]
    
    def detect_available_cameras(self):
        """Detect available cameras"""
        available_cameras = []
        for i in range(5):  # Check first 5 camera indices
            try:
                cap = cv2.VideoCapture(i)
                if cap.isOpened():
                    available_cameras.append(i)
                    cap.release()
            except:
                continue
        return available_cameras
    
    def initialize_camera(self, camera_index=None):
        """Initialize camera for QR scanning with auto-detection"""
        try:
            # If no camera index specified, try to find available cameras
            if camera_index is None:
                available_cameras = self.detect_available_cameras()
                if not available_cameras:
                    return False, "No cameras detected. Please connect a camera and try again."
                camera_index = available_cameras[0]
                print(f"📷 Using camera index: {camera_index}")
            
            self.camera = cv2.VideoCapture(camera_index)
            if not self.camera.isOpened():
                return False, f"Could not open camera {camera_index}. Please check if camera is connected."
            
            # Test if camera can actually capture frames
            ret, frame = self.camera.read()
            if not ret or frame is None:
                self.camera.release()
                return False, f"Camera {camera_index} cannot capture frames. Please check camera connection."
            
            # Set camera properties
            self.camera.set(cv2.CAP_PROP_FRAME_WIDTH, 640)
            self.camera.set(cv2.CAP_PROP_FRAME_HEIGHT, 480)
            self.camera.set(cv2.CAP_PROP_FPS, 30)
            
            return True, f"Camera {camera_index} initialized successfully"
        except Exception as e:
            return False, f"Error initializing camera: {e}"
    
    def scan_qr_codes(self, display_window=True, timeout=None):
        """Scan QR codes from camera feed"""
        if not self.camera:
            success, message = self.initialize_camera()
            if not success:
                print(f"❌ {message}")
                print("\n🔄 Falling back to manual QR code input...")
                return self.manual_qr_input()
        
        self.is_scanning = True
        start_time = time.time()
        
        print("📷 QR Scanner started. Press 'q' to quit, 's' to stop scanning")
        print("📱 Point camera at QR code...")
        
        try:
            while self.is_scanning:
                # Check timeout
                if timeout and (time.time() - start_time) > timeout:
                    break
                
                # Read frame from camera
                ret, frame = self.camera.read()
                if not ret:
                    print("❌ Failed to read from camera")
                    break
                
                # Decode QR codes
                qr_codes = pyzbar.decode(frame)
                
                # Process detected QR codes
                for qr_code in qr_codes:
                    qr_data = qr_code.data.decode('utf-8')
                    print(f"\n🔍 QR Code detected: {qr_data}")
                    
                    # Process the QR code
                    success, message = self.process_qr_code(qr_data)
                    
                    if success:
                        print(f"✅ {message}")
                        # Add visual feedback
                        cv2.putText(frame, "SUCCESS", (50, 50), 
                                  cv2.FONT_HERSHEY_SIMPLEX, 1, (0, 255, 0), 2)
                    else:
                        print(f"❌ {message}")
                        # Add visual feedback
                        cv2.putText(frame, "ERROR", (50, 50), 
                                  cv2.FONT_HERSHEY_SIMPLEX, 1, (0, 0, 255), 2)
                
                # Display frame if requested
                if display_window:
                    # Add instructions to frame
                    cv2.putText(frame, "Press 'q' to quit, 's' to stop", 
                              (10, frame.shape[0] - 20), cv2.FONT_HERSHEY_SIMPLEX, 
                              0.6, (255, 255, 255), 2)
                    
                    cv2.imshow('QR Code Scanner', frame)
                    
                    # Check for key presses
                    key = cv2.waitKey(1) & 0xFF
                    if key == ord('q'):
                        break
                    elif key == ord('s'):
                        self.stop_scanning()
                        break
                
                # Small delay to prevent excessive CPU usage
                time.sleep(0.1)
        
        except KeyboardInterrupt:
            print("\n⏹️ Scanning interrupted by user")
        except Exception as e:
            print(f"❌ Error during scanning: {e}")
        finally:
            self.stop_scanning()
            if display_window:
                cv2.destroyAllWindows()
        
        return True, "Scanning completed"
    
    def manual_qr_input(self):
        """Manual QR code input when camera is not available"""
        print("\n📝 Manual QR Code Input Mode")
        print("=" * 40)
        print("Since no camera is available, you can manually enter roll numbers.")
        print("Enter 'quit' to exit manual mode.")
        print("=" * 40)
        
        while True:
            try:
                roll_number = input("\n📱 Enter roll number (or 'quit' to exit): ").strip()
                
                if roll_number.lower() in ['quit', 'exit', 'q']:
                    print("👋 Exiting manual QR input mode...")
                    return True, "Manual input completed"
                
                if not roll_number:
                    print("❌ Please enter a valid roll number")
                    continue
                
                # Process the manually entered roll number
                print(f"\n🔍 Processing roll number: {roll_number}")
                success, message = self.process_qr_code(roll_number)
                
                if success:
                    print(f"✅ {message}")
                else:
                    print(f"❌ {message}")
                
            except KeyboardInterrupt:
                print("\n👋 Exiting manual QR input mode...")
                return True, "Manual input completed"
            except Exception as e:
                print(f"❌ Error: {e}")
    
    def stop_scanning(self):
        """Stop QR code scanning"""
        self.is_scanning = False
        if self.camera:
            self.camera.release()
    
    def scan_single_qr(self, timeout=30):
        """Scan a single QR code and return result"""
        if not self.camera:
            success, message = self.initialize_camera()
            if not success:
                return False, message
        
        print("📱 Scanning for QR code...")
        start_time = time.time()
        
        try:
            while time.time() - start_time < timeout:
                ret, frame = self.camera.read()
                if not ret:
                    continue
                
                qr_codes = pyzbar.decode(frame)
                
                if qr_codes:
                    qr_data = qr_codes[0].data.decode('utf-8')
                    print(f"🔍 QR Code detected: {qr_data}")
                    
                    # Process the QR code
                    success, message = self.process_qr_code(qr_data)
                    return success, message
                
                time.sleep(0.1)
            
            return False, "No QR code detected within timeout"
        
        except Exception as e:
            return False, f"Error scanning QR code: {e}"
    
    def test_camera_detection(self):
        """Test camera detection and show available cameras"""
        print("📷 Camera Detection Test:")
        print("=" * 40)
        
        available_cameras = self.detect_available_cameras()
        
        if available_cameras:
            print(f"✅ Found {len(available_cameras)} available camera(s):")
            for camera_index in available_cameras:
                print(f"   📹 Camera {camera_index}")
        else:
            print("❌ No cameras detected")
            print("💡 Make sure your camera is connected and not being used by another application")
        
        return available_cameras
    
    def test_qr_validation(self):
        """Test QR code validation with sample data"""
        test_cases = [
            "22-SWT-02",
            "23-SWT-122", 
            "21-ESWT-122",
            "20-SWT-86",
            "24-CIT-01",
            "25-ECSE-50",
            "invalid-format",
            "22-INVALID-01",
            "99-SWT-01",  # Invalid year
            "22-SWT-1000"  # Invalid number
        ]
        
        print("🧪 Testing QR Code Validation:")
        print("=" * 50)
        
        for test_case in test_cases:
            is_valid, message = self.validate_roll_number(test_case)
            status = "✅" if is_valid else "❌"
            print(f"{status} {test_case}: {message}")

def main():
    """Main function for testing QR scanner"""
    scanner = QRScanner()
    
    print("QR Code Scanner Test")
    print("=" * 50)
    
    # Test camera detection first
    available_cameras = scanner.test_camera_detection()
    
    print("\n" + "=" * 50)
    
    # Test validation
    scanner.test_qr_validation()
    
    print("\n" + "=" * 50)
    print("Starting QR Scanner...")
    
    # Start scanning (will auto-detect camera or fall back to manual input)
    try:
        scanner.scan_qr_codes(display_window=True)
    except KeyboardInterrupt:
        print("\n👋 Scanner stopped by user")
    finally:
        scanner.stop_scanning()

if __name__ == "__main__":
    main()
