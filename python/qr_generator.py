#!/usr/bin/env python3
"""
QR Code Generator for Testing QR Scanner
Generates QR codes for student roll numbers
"""

import qrcode
from PIL import Image
import os

def generate_qr_code(roll_number, filename=None):
    """Generate QR code for a roll number"""
    if not filename:
        filename = f"qr_{roll_number.replace('-', '_')}.png"
    
    # Create QR code
    qr = qrcode.QRCode(
        version=1,
        error_correction=qrcode.constants.ERROR_CORRECT_L,
        box_size=10,
        border=4,
    )
    
    qr.add_data(roll_number)
    qr.make(fit=True)
    
    # Create image
    img = qr.make_image(fill_color="black", back_color="white")
    
    # Save image
    img.save(filename)
    print(f"✅ QR code generated: {filename}")
    
    return filename

def generate_test_qr_codes():
    """Generate test QR codes for different roll numbers"""
    test_roll_numbers = [
        "22-SWT-02",
        "23-SWT-122", 
        "21-ESWT-122",
        "20-SWT-86",
        "24-CIT-01",
        "25-ECSE-50"
    ]
    
    print("🎯 Generating Test QR Codes...")
    print("=" * 40)
    
    # Create qr_codes directory if it doesn't exist
    if not os.path.exists("qr_codes"):
        os.makedirs("qr_codes")
    
    for roll_number in test_roll_numbers:
        filename = f"qr_codes/{roll_number.replace('-', '_')}.png"
        generate_qr_code(roll_number, filename)
    
    print(f"\n✅ Generated {len(test_roll_numbers)} test QR codes in 'qr_codes' directory")
    print("📱 You can now test the QR scanner with these codes!")

if __name__ == "__main__":
    generate_test_qr_codes()
