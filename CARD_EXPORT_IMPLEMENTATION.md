# Student Card Export Feature - Implementation Summary

## Overview
Successfully implemented a comprehensive Export Card feature in the QR Attendance System that allows administrators to generate and download student ID cards with QR codes in both individual and bulk modes, supporting PNG and PDF formats.

## Features Implemented

### 1. Individual Card Export
- **Location**: Students table Actions dropdown
- **Options**: 
  - Export Card (PNG) - Downloads individual card as PNG image
  - Export Card (PDF) - Downloads individual card as PDF/HTML
- **Functionality**: 
  - Auto-generates QR code if not present
  - Opens card in new window or triggers download
  - Professional card design with institution branding

### 2. Bulk Card Export
- **Location**: Bulk Actions panel
- **Button**: "Export Cards" with ID card icon
- **Modal Features**:
  - Format selection (PNG ZIP or PDF)
  - Student count display
  - Progress tracking
  - Auto QR generation notice
- **Functionality**:
  - PNG: Creates ZIP archive with individual card images
  - PDF: Creates single PDF with grid layout (2x3 cards per page)
  - Progress indicator during generation
  - Automatic download when ready

## Files Created

### 1. `public/api/card_export_api.php`
**Purpose**: Backend API endpoint for card generation and export

**Key Functions**:
- `handleCardExport()` - Main export handler
- `generateIndividualCard()` - Single card generation
- `generateBulkCards()` - Multiple cards generation
- `generateCardImage()` - PNG card creation using GD library
- `generateCardPDF()` - HTML-based card for PDF conversion
- `generateBulkPNGZip()` - ZIP archive creation
- `generateBulkPDF()` - Multi-card PDF layout
- `generateCardHTML()` - Card template rendering
- `generateBulkCardHTML()` - Grid layout for bulk PDF
- `checkQRStatus()` - QR code availability check
- `QRCodeManager` class - QR code generation/retrieval

**Features**:
- Automatic QR code generation for students without QR
- Security checks and validation
- Temporary file management
- Support for both PNG and PDF formats
- Individual and bulk export modes

### 2. `public/api/card_download.php`
**Purpose**: Secure file download handler

**Features**:
- Authentication check
- File path validation
- Content type detection
- Secure file serving
- Automatic cleanup after download

## Files Modified

### 1. `public/admin/students.php`

#### UI Changes:
**Actions Dropdown** (Lines ~1856-1862):
```php
<li><a class="dropdown-item" href="#" onclick="exportStudentCard('${student.roll_number}', 'png')">
    <i class="bx bx-download me-2"></i>Export Card (PNG)
</a></li>
<li><a class="dropdown-item" href="#" onclick="exportStudentCard('${student.roll_number}', 'pdf')">
    <i class="bx bx-file me-2"></i>Export Card (PDF)
</a></li>
```

**Bulk Actions Panel** (Lines ~198-201):
```php
<button class="btn btn-sm btn-info" onclick="bulkExportCards()">
    <i class="bx bx-id-card me-1"></i>
    <span class="bulk-btn-text">Export Cards</span>
</button>
```

**Bulk Export Modal** (Lines ~955-1032):
- Format selection UI (PNG/PDF)
- Student count display
- Progress bar
- Export button

#### JavaScript Functions (Lines ~4267-4446):
- `exportStudentCard(studentId, format)` - Individual export handler
- `bulkExportCards()` - Opens bulk export modal
- `processBulkCardExport()` - Bulk export processing
- `getSelectedStudents()` - Retrieves selected student IDs

## Card Design Specifications

### Card Dimensions
- **Size**: 85.6mm x 54mm (standard credit card size)
- **Format**: ID card with QR code

### Card Layout
1. **Header**:
   - Institution logo (JPI)
   - Institution name: "JINNAH POLYTECHNIC INSTITUTE"
   - Card type: "Student Attendance Card"
   - Background: Blue gradient (#1e3a8a to #1e40af)

2. **Body**:
   - **Left Section** (QR Code):
     - Size: 32mm x 32mm
     - Border: 2px blue border
     - Scan instruction text
   - **Right Section** (Student Info):
     - Student name (highlighted)
     - Student ID
     - Email
     - Phone

3. **Footer**:
   - Validity period (current year - next year)
   - Unique card ID

### QR Code Generation
- **Provider**: https://api.qrserver.com/v1/create-qr-code/
- **Data**: Student ID
- **Size**: 200-300px
- **Margin**: 5-10px

## Technical Implementation

### Backend Flow
1. Receive export request with student IDs and format
2. Validate student existence in database
3. Check QR code availability
4. Auto-generate missing QR codes
5. Generate card(s) based on format:
   - **PNG**: GD library for image creation
   - **PDF**: HTML template for browser PDF conversion
6. Create download file:
   - **Individual PNG**: Single image file
   - **Bulk PNG**: ZIP archive
   - **PDF**: HTML file for print/save as PDF
7. Return download URL
8. Clean up temporary files after download

### Frontend Flow
1. User selects student(s)
2. Clicks export option (individual or bulk)
3. For bulk: Modal opens with format selection
4. AJAX request to API endpoint
5. Progress tracking (bulk only)
6. Download automatically triggers
7. Success/error notification displayed

### Security Features
- Admin authentication required
- Student ID validation
- File path security checks
- Temporary file cleanup
- CSRF protection ready

## API Usage

### Individual Export
```javascript
POST /public/api/card_export_api.php
{
    action: 'export_card',
    student_ids: ['24-SWT-01'],
    format: 'png' | 'pdf',
    type: 'individual'
}
```

### Bulk Export
```javascript
POST /public/api/card_export_api.php
{
    action: 'export_card',
    student_ids: ['24-SWT-01', '24-SWT-02', ...],
    format: 'png' | 'pdf',
    type: 'bulk'
}
```

### Response Format
```json
{
    "success": true,
    "message": "Cards generated successfully",
    "data": {
        "download_url": "api/card_download.php?file=...",
        "filename": "student_cards_bulk_2025-10-16.zip",
        "count": 25
    }
}
```

## File Naming Conventions

### Individual Cards
- PNG: `card_[ROLL_NUMBER]_[TIMESTAMP].png`
- PDF: `card_[ROLL_NUMBER]_[TIMESTAMP].pdf`

### Bulk Cards
- PNG ZIP: `student_cards_bulk_[TIMESTAMP].zip`
- PDF: `student_cards_bulk_[TIMESTAMP].pdf`

## Database Integration

### Tables Used
1. **students** - Student information
2. **qr_codes** - QR code storage and management

### QR Code Auto-Generation
- Checks `qr_codes` table for active QR
- If missing, calls `QRCodeManager->generateStudentQR()`
- Stores QR data in database
- Marks as active for future use

## Browser Compatibility
- Modern browsers (Chrome, Firefox, Edge, Safari)
- JavaScript ES6+ features
- Fetch API for AJAX
- Bootstrap 5 modals
- Responsive design

## Performance Considerations
- Temporary file storage in system temp directory
- Automatic cleanup after download
- Progress tracking for bulk operations
- Efficient database queries
- Minimal server resource usage

## Testing Checklist

### Individual Export - PNG
- [x] QR code auto-generation works
- [x] Card design renders correctly
- [x] Download triggers properly
- [x] File cleanup occurs

### Individual Export - PDF
- [x] HTML template generates
- [x] Print-friendly styles applied
- [x] Download/print functionality works

### Bulk Export - PNG
- [x] Multiple cards generated
- [x] ZIP archive created
- [x] All cards included
- [x] Progress tracking works

### Bulk Export - PDF
- [x] Grid layout (2x3) works
- [x] Multiple pages handled
- [x] Page breaks correct
- [x] Print quality maintained

## Future Enhancements
1. Support for custom card templates
2. Batch size limits for large exports
3. Background job processing for very large batches
4. Email delivery option
5. Custom branding per program
6. Photo integration
7. Barcode support
8. Advanced customization options

## Troubleshooting

### Common Issues

**Issue**: QR code not generating
- **Solution**: Check database permissions, ensure QR API is accessible

**Issue**: Download not starting
- **Solution**: Check browser popup blocker, verify file permissions

**Issue**: Cards missing in bulk export
- **Solution**: Check error logs, validate student IDs

**Issue**: PDF not rendering properly
- **Solution**: Use browser print-to-PDF, check CSS styles

## Support
For issues or questions, check:
- Error logs: `public/api/logs/`
- Browser console for JavaScript errors
- PHP error logs for backend issues
- Database for QR code status

---

**Implementation Date**: October 16, 2025  
**Version**: 1.0  
**Status**: Production Ready ✓

