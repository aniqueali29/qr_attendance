// Minimal Student Modal logic for roll-number autofill on Students page
// Ensures correct field IDs and year-level mapping

(function () {
  'use strict';

  function mapYearNumericToLabel(n) {
    if (n === 1) return '1st';
    if (n === 2) return '2nd';
    if (n === 3) return '3rd';
    return '';
  }

  async function parseRollNumber(rollNumber) {
    if (!rollNumber || rollNumber.length < 5) return;

    try {
      const res = await fetch(`../api/roll_parser_simple.php?action=parse_roll&roll_number=${encodeURIComponent(rollNumber)}`);
      const result = await res.json();

      const statusDiv = document.getElementById('roll-number-status');
      const programSelect = document.getElementById('student-program');
      const shiftSelect = document.getElementById('student-shift');
      const yearLevelSelect = document.getElementById('student-year');
      const admissionYearInput = document.getElementById('student-admission-year');
      const sectionSelect = document.getElementById('student-section');

      if (!result.success) {
        // Fallback: client-side parse and compute year level
        clientSideFillFromRoll(rollNumber);
        if (statusDiv) {
          statusDiv.className = 'text-warning';
          statusDiv.style.display = 'block';
          statusDiv.textContent = (result.error || 'Parsed locally') + ' (fallback)';
        }
        return;
      }

      const data = result.data || {};

      // Admission year
      if (admissionYearInput && data.admission_year) {
        admissionYearInput.value = data.admission_year;
        admissionYearInput.style.backgroundColor = '#e8f5e8';
        admissionYearInput.style.borderColor = '#4caf50';
      }

      // Program
      if (programSelect && data.program_code) {
        for (let opt of programSelect.options) {
          if (opt.value === data.program_code) {
            opt.selected = true;
            programSelect.style.backgroundColor = '#e8f5e8';
            programSelect.style.borderColor = '#4caf50';
            break;
          }
        }
      }

      // Shift
      if (shiftSelect && data.shift) {
        for (let opt of shiftSelect.options) {
          if (opt.value === data.shift) {
            opt.selected = true;
            shiftSelect.style.backgroundColor = '#e8f5e8';
            shiftSelect.style.borderColor = '#4caf50';
            break;
          }
        }
      }

      // Year Level (accept string or numeric) with fallback
      if (yearLevelSelect) {
        let desired = data.year_level || mapYearNumericToLabel(Number(data.year_level_numeric));
        if (!desired) {
          desired = mapYearNumericToLabel(computeYearFromRoll(rollNumber));
        }
        if (desired) {
          for (let opt of yearLevelSelect.options) {
            if (opt.value === desired) {
              opt.selected = true;
              yearLevelSelect.style.backgroundColor = '#e8f5e8';
              yearLevelSelect.style.borderColor = '#4caf50';
              break;
            }
          }
        }
      }

      // Sections (optional)
      if (sectionSelect && Array.isArray(data.available_sections)) {
        sectionSelect.innerHTML = '<option value="">Select Section</option>';
        data.available_sections.forEach(sec => {
          const o = document.createElement('option');
          o.value = sec.id;
          o.textContent = `${sec.section_name} (${sec.current_students}/${sec.capacity})`;
          sectionSelect.appendChild(o);
        });
      }

      if (statusDiv) {
        statusDiv.className = 'text-success';
        statusDiv.style.display = 'block';
        statusDiv.textContent = `Auto-filled: ${data.program_name || data.program_code || ''} - ${data.shift || ''} - ${data.year_level || mapYearNumericToLabel(Number(data.year_level_numeric))}`;
      }

    } catch (e) {
      // Hard fallback on network or server error
      clientSideFillFromRoll(rollNumber);
      const statusDiv = document.getElementById('roll-number-status');
      if (statusDiv) {
        statusDiv.className = 'text-warning';
        statusDiv.style.display = 'block';
        statusDiv.textContent = 'Parsed locally (server unavailable)';
      }
    }
  }

  function computeYearFromRoll(roll) {
    // Expect YY-...-NN or YY-E...-NN
    const m = /^(\d{2})-E?[A-Za-z]{2,10}-(\d{2})$/.exec(roll.trim());
    if (!m) return 0;
    const yy = parseInt(m[1], 10);
    const admissionYear = 2000 + yy;
    const now = new Date();
    const month = now.getMonth() + 1; // 1-12
    let academicYear = now.getFullYear();
    if (month < 9) academicYear -= 1; // starts in September
    let yearsInProgram = academicYear - admissionYear + 1;
    if (yearsInProgram < 1) yearsInProgram = 1;
    if (yearsInProgram > 3) yearsInProgram = 3;
    return yearsInProgram;
  }

  function clientSideFillFromRoll(roll) {
    const programSelect = document.getElementById('student-program');
    const shiftSelect = document.getElementById('student-shift');
    const yearLevelSelect = document.getElementById('student-year');
    const admissionYearInput = document.getElementById('student-admission-year');
    const m = /^(\d{2})-(E)?([A-Za-z]{2,10})-(\d{2})$/.exec(roll.trim());
    if (!m) return;
    const yy = parseInt(m[1], 10);
    const isE = !!m[2];
    const programCode = m[3].toUpperCase();
    const admissionYear = 2000 + yy;
    const shift = isE ? 'Evening' : 'Morning';

    if (admissionYearInput) {
      admissionYearInput.value = admissionYear;
      admissionYearInput.style.backgroundColor = '#e8f5e8';
      admissionYearInput.style.borderColor = '#4caf50';
    }
    if (programSelect) {
      for (let opt of programSelect.options) {
        if (opt.value === programCode) {
          opt.selected = true;
          programSelect.style.backgroundColor = '#e8f5e8';
          programSelect.style.borderColor = '#4caf50';
          break;
        }
      }
    }
    if (shiftSelect) {
      for (let opt of shiftSelect.options) {
        if (opt.value === shift) {
          opt.selected = true;
          shiftSelect.style.backgroundColor = '#e8f5e8';
          shiftSelect.style.borderColor = '#4caf50';
          break;
        }
      }
    }
    if (yearLevelSelect) {
      const desired = mapYearNumericToLabel(computeYearFromRoll(roll));
      for (let opt of yearLevelSelect.options) {
        if (opt.value === desired) {
          opt.selected = true;
          yearLevelSelect.style.backgroundColor = '#e8f5e8';
          yearLevelSelect.style.borderColor = '#4caf50';
          break;
        }
      }
    }
  }

  function setupRollNumberAutoFill() {
    const rollInput = document.getElementById('student-roll');
    if (!rollInput) return;

    let debounceId;
    rollInput.addEventListener('input', function () {
      clearTimeout(debounceId);
      const val = this.value.trim();
      if (!val) return;
      // Basic pattern check before calling API:  YY-PROG-NN or YY-EPROG-NN
      const pat = /^(\d{2})-E?[A-Za-z]{2,10}-(\d{2})$/;
      debounceId = setTimeout(() => {
        if (pat.test(val)) parseRollNumber(val);
      }, 400);
    });

    rollInput.addEventListener('blur', function () {
      const val = this.value.trim();
      if (val) parseRollNumber(val);
    });

    rollInput.addEventListener('keypress', function (e) {
      if (e.key === 'Enter') {
        const val = this.value.trim();
        if (val) parseRollNumber(val);
      }
    });
  }

  document.addEventListener('DOMContentLoaded', setupRollNumberAutoFill);
})();


