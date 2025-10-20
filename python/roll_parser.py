import re
from datetime import datetime

def parse_roll_number(roll_number):
    """
    Parse roll number to extract academic information with enhanced validation.
    
    Expected format: YY-PPP-RR
    - YY: Admission year (last two digits)
    - PPP: Program code (e.g., SWT, MIT, BSC)
    - RR: Roll sequence number
    
    Example: 24-SWT-01 -> Admission Year: 2024, Program: SWT, Roll: 01
    """
    try:
        # Clean the input
        roll_number = str(roll_number).strip().upper()
        
        # Enhanced pattern matching for various roll number formats
        patterns = [
            r'^(\d{2})[-_]?([A-Z]{2,4})[-_]?(\d{2,3})$',  # 24-SWT-01 or 24_SWT_01
            r'^([A-Z]{2,4})[-_]?(\d{2})[-_]?(\d{2,3})$',  # SWT-24-01
            r'^(\d{2})([A-Z]{2,4})(\d{2,3})$',           # 24SWT01
        ]
        
        for pattern in patterns:
            match = re.match(pattern, roll_number)
            if match:
                groups = match.groups()
                
                if len(groups) == 3:
                    # Determine format based on group content
                    if groups[0].isdigit() and len(groups[0]) == 2 and groups[1].isalpha():
                        # Format: YY-PPP-RR
                        admission_year_short = int(groups[0])
                        program_code = groups[1]
                        roll_sequence = groups[2]
                    elif groups[0].isalpha() and groups[1].isdigit() and len(groups[1]) == 2:
                        # Format: PPP-YY-RR
                        program_code = groups[0]
                        admission_year_short = int(groups[1])
                        roll_sequence = groups[2]
                    else:
                        continue
                    
                    # Calculate full admission year
                    current_year = datetime.now().year
                    current_century = current_year // 100 * 100
                    admission_year = current_century + admission_year_short
                    
                    # Adjust for future/past years
                    if admission_year > current_year + 5:
                        admission_year -= 100
                    elif admission_year < current_year - 10:
                        admission_year += 100
                    
                    return {
                        'admission_year': admission_year,
                        'program_code': program_code,
                        'roll_sequence': roll_sequence,
                        'original_roll': roll_number
                    }
        
        # If no pattern matched, try to extract basic information
        print(f"⚠️ Could not parse roll number format: {roll_number}")
        return {
            'admission_year': datetime.now().year,
            'program_code': 'UNK',
            'roll_sequence': '01',
            'original_roll': roll_number
        }
        
    except Exception as e:
        print(f"❌ Error parsing roll number {roll_number}: {e}")
        return {
            'admission_year': datetime.now().year,
            'program_code': 'UNK',
            'roll_sequence': '01',
            'original_roll': roll_number
        }

def get_shift(roll_number):
    """Determine student shift based on roll number with enhanced logic."""
    try:
        parsed = parse_roll_number(roll_number)
        program = parsed['program_code']
        
        # Enhanced shift determination logic
        shift_mapping = {
            'SWT': 'Morning',
            'MIT': 'Evening', 
            'BSC': 'Morning',
            'MSC': 'Evening',
            'PHD': 'Evening'
        }
        
        return shift_mapping.get(program, 'Morning')  # Default to Morning
        
    except Exception as e:
        print(f"❌ Error determining shift for {roll_number}: {e}")
        return 'Morning'

def get_program(roll_number):
    """Get full program name from roll number with enhanced mapping."""
    try:
        parsed = parse_roll_number(roll_number)
        program_code = parsed['program_code']
        
        program_mapping = {
            'SWT': 'Software Technology',
            'MIT': 'Master of IT',
            'BSC': 'Bachelor of Science',
            'MSC': 'Master of Science',
            'PHD': 'Doctor of Philosophy',
            'CS': 'Computer Science',
            'IT': 'Information Technology',
            'SE': 'Software Engineering'
        }
        
        return program_mapping.get(program_code, f"Program {program_code}")
        
    except Exception as e:
        print(f"❌ Error determining program for {roll_number}: {e}")
        return 'Unknown Program'

def get_academic_year_info(roll_number):
    """Calculate current academic year and admission year with enhanced logic."""
    try:
        parsed = parse_roll_number(roll_number)
        admission_year = parsed['admission_year']
        current_year = datetime.now().year
        current_month = datetime.now().month
        
        # Academic year calculation (starts in August)
        if current_month >= 8:  # August to December
            academic_year = current_year - admission_year + 1
        else:  # January to July
            academic_year = current_year - admission_year
        
        # Ensure academic year is reasonable
        if academic_year < 1:
            academic_year = 1
        elif academic_year > 10:  # Maximum reasonable years
            academic_year = 10
        
        return academic_year, admission_year
        
    except Exception as e:
        print(f"❌ Error calculating academic year for {roll_number}: {e}")
        return 1, datetime.now().year

def validate_roll_number(roll_number):
    """Validate roll number format with enhanced checks."""
    try:
        parsed = parse_roll_number(roll_number)
        
        # Check if parsing was successful
        if parsed['program_code'] == 'UNK':
            return False
        
        # Additional validation checks
        current_year = datetime.now().year
        admission_year = parsed['admission_year']
        
        # Check if admission year is reasonable
        if admission_year < 2000 or admission_year > current_year + 1:
            return False
        
        # Check roll sequence
        roll_seq = parsed['roll_sequence']
        if not roll_seq.isdigit() or int(roll_seq) <= 0:
            return False
        
        return True
        
    except Exception as e:
        print(f"❌ Error validating roll number {roll_number}: {e}")
        return False

if __name__ == "__main__":
    # Test the roll parser
    test_rolls = ['24-SWT-01', '23-MIT-15', 'SWT-24-01', '25BSC05', 'INVALID']
    
    print("Roll Parser Test:")
    for roll in test_rolls:
        print(f"\nRoll: {roll}")
        parsed = parse_roll_number(roll)
        print(f"Parsed: {parsed}")
        print(f"Shift: {get_shift(roll)}")
        print(f"Program: {get_program(roll)}")
        print(f"Academic Year: {get_academic_year_info(roll)}")
        print(f"Valid: {validate_roll_number(roll)}")