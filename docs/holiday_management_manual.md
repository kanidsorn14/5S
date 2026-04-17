# User Manual: Holiday & Schedule Management

## Introduction
The 5S Dashboard now supports dynamic holiday management. This ensures that scores are calculated only for actual working days, improving the accuracy of the Real-Time score.

## Accessing the Management Page
1. Open your browser and go to: `http://192.168.106.48/5s/manage_employees.php`
2. Click on the **"Holidays & Schedule"** tab.

## Features

### 1. Weekly Working Schedule
- **Purpose**: Define which days of the week are standard working days.
- **How to use**: Tick the boxes for days your company normally works. (Default: Mon-Fri).

### 2. Public Holidays (Exclusions)
- **Purpose**: Exclude specific dates from the calculation (e.g., National Holidays).
- **How to use**: 
    1. Click **"+ Add Holiday"**.
    2. Select the date.
    3. (Optional) Enter the holiday name for reference.
- **Rule**: Even if the holiday falls on a standard working day, it will be **excluded**.

### 3. Special Working Days (Inclusions)
- **Purpose**: Include specific dates that are normally holidays (e.g., Sunday shifts).
- **How to use**:
    1. Click **"+ Add Extra Work Day"**.
    2. Select the date.
    3. (Optional) Enter the event name.
- **Rule**: These dates will **always be included** in the score calculation.

---

## API for Developers
If you need to fetch the holiday and schedule data for other systems, use the following endpoint:

**Endpoint**: `http://192.168.106.48/5s/api_holidays.php`
**Method**: `GET`
**Response Format**: `JSON`

### Example Response:
```json
{
  "status": "success",
  "data": {
    "weekly_schedule": [
      { "day_id": 2, "day_name": "Monday" },
      ...
    ],
    "public_holidays": [
      { "date": "2026-04-13", "name": "Songkran" }
    ],
    "extra_working_days": [],
    "last_updated": "2026-04-10 09:17:40"
  }
}
```
