"""
Roll Number Parser Module for College Attendance System
Parses roll number format: YY-[E]PROGRAM-NN
Extracts: admission year (YY), shift (E=Evening, none=Morning), program code, sequence number
"""

import re
from datetime import datetime
from typing import Dict, Optional, Tuple


def parse_roll_number(roll_number: str) -> Dict:
    """
    Parse roll number format: YY-[E]PROGRAM-NN
    
    Args:
        roll_number (str): Roll number to parse (e.g., "25-SWT-01", "24-ECSE-02")
    
    Returns:
        Dict: Parsed roll number information
    """
    if not roll_number or not isinstance(roll_number, str):
        return {
            'valid': False,
            'error': 'Invalid roll number format'
        }
    
    # Clean the roll number
    roll_number = roll_number.strip().upper()
    
    # Pattern: YY-[E]PROGRAM-NN
    # YY: 2-digit year (20-99)
    # E: Optional 'E' for Evening shift (must be followed by dash)
    # PROGRAM: 2-4 character program code
    # NN: 2-digit sequence number
    
    # First try evening shift pattern: YY-EPROGRAM-NN (E must be followed by a known program)
    # Evening shift is only valid for specific program codes that can have an E prefix
    # For D.A.E programs: SWT, CIT (Software Technology, Computer Information Technology)
    evening_programs = ['SWT', 'CIT']
    evening_pattern = r'^(\d{2})-E([A-Z]{2,4})-(\d{2})$'
    match = re.match(evening_pattern, roll_number)
    
    if match:
        year_str, program, sequence = match.groups()
        # Only consider it evening if the program after E is a known D.A.E program
        if program in evening_programs:
            evening_flag = 'E'
        else:
            # This is not a valid evening shift, try morning pattern
            match = None
    
    if not match:
        # Try morning shift pattern: YY-PROGRAM-NN
        morning_pattern = r'^(\d{2})-([A-Z]{2,4})-(\d{2})$'
        match = re.match(morning_pattern, roll_number)
        
        if match:
            year_str, program, sequence = match.groups()
            evening_flag = None
    
    if not match:
        return {
            'valid': False,
            'error': 'Invalid roll number format. Expected: YY-[E]PROGRAM-NN'
        }
    
    # Extract values based on which pattern matched
    if evening_flag == 'E':
        # Evening shift: year_str, program, sequence already extracted
        pass
    else:
        # Morning shift: year_str, program, sequence already extracted
        pass
    
    # Parse year (convert to full year)
    admission_year = int(year_str)
    if admission_year < 20:
        admission_year += 2000
    else:
        admission_year += 2000
    
    # Determine shift
    shift = 'Evening' if evening_flag == 'E' else 'Morning'
    
    # Parse sequence number
    sequence_num = int(sequence)
    
    return {
        'valid': True,
        'roll_number': roll_number,
        'admission_year': admission_year,
        'shift': shift,
        'program': program,
        'sequence_number': sequence_num,
        'is_evening': evening_flag == 'E'
    }


def get_student_year(admission_year: int, current_date: Optional[datetime] = None) -> int:
    """
    Calculate current academic year based on admission year and current date.
    
    Args:
        admission_year (int): Year of admission
        current_date (datetime, optional): Current date. Defaults to now.
    
    Returns:
        int: Current academic year (1-4)
    """
    if current_date is None:
        current_date = datetime.now()
    
    current_year = current_date.year
    current_month = current_date.month
    
    # Academic year starts in September
    if current_month >= 9:
        academic_year = current_year
    else:
        academic_year = current_year - 1
    
    # Calculate year of study
    year_of_study = academic_year - admission_year + 1
    
    # Cap at 4 years (graduation)
    return min(max(year_of_study, 1), 4)


def get_shift(roll_number: str) -> str:
    """
    Extract shift from roll number.
    
    Args:
        roll_number (str): Roll number to parse
    
    Returns:
        str: 'Morning' or 'Evening'
    """
    parsed = parse_roll_number(roll_number)
    if not parsed['valid']:
        return 'Morning'  # Default to morning
    
    return parsed['shift']


def get_program(roll_number: str) -> str:
    """
    Extract program code from roll number.
    
    Args:
        roll_number (str): Roll number to parse
    
    Returns:
        str: Program code
    """
    parsed = parse_roll_number(roll_number)
    if not parsed['valid']:
        return 'UNKNOWN'
    
    return parsed['program']


def validate_roll_number(roll_number: str) -> bool:
    """
    Validate roll number format.
    
    Args:
        roll_number (str): Roll number to validate
    
    Returns:
        bool: True if valid, False otherwise
    """
    parsed = parse_roll_number(roll_number)
    return parsed['valid']


def get_academic_year_info(roll_number: str, current_date: Optional[datetime] = None) -> Dict:
    """
    Get comprehensive academic year information for a roll number.
    
    Args:
        roll_number (str): Roll number to analyze
        current_date (datetime, optional): Current date. Defaults to now.
    
    Returns:
        Dict: Academic year information
    """
    parsed = parse_roll_number(roll_number)
    
    if not parsed['valid']:
        return {
            'valid': False,
            'error': parsed['error']
        }
    
    current_year = get_student_year(parsed['admission_year'], current_date)
    
    return {
        'valid': True,
        'roll_number': roll_number,
        'admission_year': parsed['admission_year'],
        'current_year': current_year,
        'shift': parsed['shift'],
        'program': parsed['program'],
        'sequence_number': parsed['sequence_number'],
        'is_evening': parsed['is_evening'],
        'is_graduated': current_year > 4,
        'years_remaining': max(0, 4 - current_year)
    }


def get_batch_info(admission_year: int) -> Dict:
    """
    Get batch information for a given admission year.
    
    Args:
        admission_year (int): Admission year
    
    Returns:
        Dict: Batch information
    """
    current_date = datetime.now()
    current_year = get_student_year(admission_year, current_date)
    
    return {
        'admission_year': admission_year,
        'current_year': current_year,
        'batch_name': f"Batch {admission_year}",
        'is_graduated': current_year > 4,
        'years_remaining': max(0, 4 - current_year)
    }


def get_program_info(program_code: str) -> Dict:
    """
    Get program information based on program code.
    
    Args:
        program_code (str): Program code (e.g., 'SWT', 'ECSE', 'CS')
    
    Returns:
        Dict: Program information
    """
    program_mapping = {
        'SWT': {'name': 'Software Technology', 'duration': 3, 'type': 'D.A.E'},
        'CIT': {'name': 'Computer Information Technology', 'duration': 3, 'type': 'D.A.E'},
        # Legacy support for other programs
        'ECSE': {'name': 'Electrical & Computer Systems Engineering', 'duration': 4, 'type': 'Bachelor'},
        'CS': {'name': 'Computer Science', 'duration': 4, 'type': 'Bachelor'},
        'IT': {'name': 'Information Technology', 'duration': 4, 'type': 'Bachelor'},
        'SE': {'name': 'Software Engineering', 'duration': 4, 'type': 'Bachelor'},
        'CE': {'name': 'Computer Engineering', 'duration': 4, 'type': 'Bachelor'},
        'EE': {'name': 'Electrical Engineering', 'duration': 4, 'type': 'Bachelor'},
        'ME': {'name': 'Mechanical Engineering', 'duration': 4, 'type': 'Bachelor'},
        'CIVIL': {'name': 'Civil Engineering', 'duration': 4, 'type': 'Bachelor'},
        'MATH': {'name': 'Mathematics', 'duration': 4, 'type': 'Bachelor'},
        'PHYS': {'name': 'Physics', 'duration': 4, 'type': 'Bachelor'},
        'CHEM': {'name': 'Chemistry', 'duration': 4, 'type': 'Bachelor'},
        'BIO': {'name': 'Biology', 'duration': 4, 'type': 'Bachelor'},
        'BUS': {'name': 'Business Administration', 'duration': 4, 'type': 'Bachelor'},
        'ECON': {'name': 'Economics', 'duration': 4, 'type': 'Bachelor'},
        'PSY': {'name': 'Psychology', 'duration': 4, 'type': 'Bachelor'},
        'ENG': {'name': 'English', 'duration': 4, 'type': 'Bachelor'},
        'HIST': {'name': 'History', 'duration': 4, 'type': 'Bachelor'},
        'ART': {'name': 'Art', 'duration': 4, 'type': 'Bachelor'},
        'MUS': {'name': 'Music', 'duration': 4, 'type': 'Bachelor'}
    }
    
    program_info = program_mapping.get(program_code.upper(), {
        'name': f'Program {program_code}',
        'duration': 4,
        'type': 'Bachelor'
    })
    
    return {
        'code': program_code.upper(),
        'name': program_info['name'],
        'duration': program_info['duration'],
        'type': program_info['type']
    }


def parse_multiple_roll_numbers(roll_numbers: list) -> Dict:
    """
    Parse multiple roll numbers and return summary statistics.
    
    Args:
        roll_numbers (list): List of roll numbers to parse
    
    Returns:
        Dict: Summary statistics
    """
    valid_rolls = []
    invalid_rolls = []
    
    for roll in roll_numbers:
        parsed = parse_roll_number(roll)
        if parsed['valid']:
            valid_rolls.append(parsed)
        else:
            invalid_rolls.append({'roll_number': roll, 'error': parsed['error']})
    
    # Analyze valid rolls
    programs = {}
    shifts = {'Morning': 0, 'Evening': 0}
    years = {}
    
    for roll in valid_rolls:
        # Count programs
        program = roll['program']
        programs[program] = programs.get(program, 0) + 1
        
        # Count shifts
        shifts[roll['shift']] += 1
        
        # Count years
        year = roll['admission_year']
        years[year] = years.get(year, 0) + 1
    
    return {
        'total_rolls': len(roll_numbers),
        'valid_rolls': len(valid_rolls),
        'invalid_rolls': len(invalid_rolls),
        'valid_roll_data': valid_rolls,
        'invalid_roll_data': invalid_rolls,
        'programs': programs,
        'shifts': shifts,
        'years': years
    }


# Example usage and testing
if __name__ == "__main__":
    # Test cases for D.A.E programs
    test_rolls = [
        "24-SWT-01",      # Valid morning shift - Software Technology
        "24-CIT-02",      # Valid morning shift - Computer Information Technology
        "24-ESWT-03",     # Valid evening shift - Software Technology
        "24-ECIT-04",     # Valid evening shift - Computer Information Technology
        "25-SWT-01",      # Valid morning shift - Next year
        "25-ESWT-02",     # Valid evening shift - Next year
        "invalid-roll",   # Invalid
        "24-SWT",         # Invalid (missing sequence)
        "24-SWT-1",       # Invalid (single digit sequence)
        "24-ABC-01",      # Invalid (unknown program)
        "24-ESWT-1",      # Invalid (single digit sequence for evening)
        "24-ESWT",        # Invalid (missing sequence for evening)
    ]
    
    print("Roll Number Parser Test Results")
    print("=" * 50)
    
    for roll in test_rolls:
        result = parse_roll_number(roll)
        print(f"Roll: {roll}")
        if result['valid']:
            print(f"  Valid: Yes")
            print(f"  Admission Year: {result['admission_year']}")
            print(f"  Shift: {result['shift']}")
            print(f"  Program: {result['program']}")
            print(f"  Sequence: {result['sequence_number']}")
            
            # Get academic year info
            academic_info = get_academic_year_info(roll)
            if academic_info['valid']:
                print(f"  Current Year: {academic_info['current_year']}")
                print(f"  Graduated: {academic_info['is_graduated']}")
        else:
            print(f"  Valid: No - {result['error']}")
        print()
    
    # Test batch analysis
    print("Batch Analysis")
    print("=" * 30)
    batch_info = get_batch_info(2024)
    print(f"Batch 2024: Year {batch_info['current_year']}, Graduated: {batch_info['is_graduated']}")
    
    # Test program info
    print("\nProgram Information")
    print("=" * 30)
    program_info = get_program_info('SWT')
    print(f"SWT: {program_info['name']} ({program_info['type']})")
    
    # Test multiple roll parsing
    print("\nMultiple Roll Analysis")
    print("=" * 30)
    analysis = parse_multiple_roll_numbers(test_rolls[:5])  # Only valid ones
    print(f"Total: {analysis['total_rolls']}, Valid: {analysis['valid_rolls']}, Invalid: {analysis['invalid_rolls']}")
    print(f"Programs: {analysis['programs']}")
    print(f"Shifts: {analysis['shifts']}")
    print(f"Years: {analysis['years']}")
