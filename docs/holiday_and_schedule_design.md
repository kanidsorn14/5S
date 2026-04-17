# Specification: Holiday and Working Schedule Management

## Overview
Enhance the 5S Dashboard to support flexible working schedules and holiday management. This prevents over/under-counting of scores on days when the factory or office is closed or specifically open.

## Requirements
- Ability to define standard working days (e.g., Mon-Fri).
- Ability to define specific dates as "Holidays" (Exclude from calculation).
- Ability to define specific dates as "Extra Working Days" (Include in calculation, even if they fall on a standard holiday).
- Avoid hardcoding dates in PHP or SQL.
- User-friendly UI for non-technical staff.

## Design

### 1. Data Structure (`dashboard_config.json`)
We will add a `schedule` object to the existing configuration:
```json
{
  "schedule": {
    "work_days": [1, 2, 3, 4, 5], 
    "holidays": [
      { "date": "2026-04-13", "name": "Songkran" }
    ],
    "extra_work_days": [
      { "date": "2026-04-18", "name": "Special Project Sat" }
    ]
  }
}
```
*Note: `work_days` uses 1 (Mon) to 7 (Sun) or follows SQL/PHP standards.*

### 2. UI Management (`manage_employees.php`)
- **New Tab: "Holidays & Schedule"**
- **Weekly Schedule Section**: Checkboxes for Monday through Sunday.
- **Holidays Section**: 
  - Form: Date Input + Optional Name.
  - Table: List of holidays with "Remove" button.
- **Extra Working Days Section**:
  - Form: Date Input + Optional Name.
  - Table: List of extra work days with "Remove" button.
- **Save Logic**: Update the POST handler to process these new fields and save to `dashboard_config.json`.

### 3. Calculation Logic (`calculate_score.php`)
The SQL query uses a CTE named `MonthDates` and `WorkingDays`. We will modify it to:
1. Extract list of holiday dates and extra work dates from JSON.
2. In SQL:
   - Use the `work_days` array to filter standard working days.
   - Use `NOT IN` for specific holiday dates.
   - Use `UNION` or `OR` logic to include specific extra work dates.

#### Proposed SQL Logic Change:
```sql
WorkingDays AS (
    SELECT COUNT(*) AS WorkDayCount
    FROM MonthDates
    WHERE 
        (
            -- Standard Working Days (Checked in UI)
            DATEPART(dw, DateValue) IN (@WorkDaysList)
            -- AND NOT a specific holiday
            AND DateValue NOT IN (@HolidayDatesList)
        )
        OR 
        (
            -- OR it is specifically marked as an Extra Working Day
            DateValue IN (@ExtraWorkDatesList)
        )
)
```

## Error Handling
- Default to Mon-Fri if no schedule is defined.
- Ensure date formats are consistent (YYYY-MM-DD).
- Handle empty lists for holiday/extra days (using a dummy date like '1900-01-01' in `NOT IN`).

## Verification Plan
- Add a holiday on a weekday: Score frequency/working days should decrease.
- Add a working day on a weekend: Score frequency/working days should increase.
- Uncheck Saturday in weekly schedule: Working days count should exclude all Saturdays except if added specifically as extra days.
