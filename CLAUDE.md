# 5S Real-Time Dashboard - Rangsit
## Codebase Documentation for AI Assistants

**Project**: 5S Management & Monitoring System  
**Organization**: Rangsit Factory  
**Primary Language**: PHP 7.x+  
**Database**: SQL Server (MSSQL) via PDO  
**Status**: Production  
**Last Updated**: 2026-04-24

---

## 📋 Project Overview

The **5S Real-Time Dashboard** is a web-based monitoring system for the Rangsit factory that:
- Displays real-time 5S (Sort, Set In Order, Shine, Standardize, Sustain) compliance scores
- Tracks performance across 30+ factory and office departments
- Dynamically calculates scores based on actual employee counts and working days
- Provides a 3-column layout optimized for 1080p monitor displays
- Allows administrators to manage employee counts and customize dashboard appearance

### Key Metrics
- **Scoring Basis**: `Possible Score = Employee Count × Question Count × 5 × Working Days`
- **Final Score**: `(Actual Score / Possible Score) × 100%`
- **Refresh Interval**: Auto-refresh every 5 minutes, full reset daily at 07:30
- **Display Areas**: 30 departments across Factory and Office divisions
- **Color Conditions**: 3 tiers (< 49% red, 50-89% green, ≥ 90% blue)

---

## 🗂️ Project Structure

```
/home/user/5S/
├── index.php                      # Main dashboard display page
├── manage_employees.php           # Admin UI for employee counts & config
├── calculate_score.php            # Score calculation logic & SQL queries
├── api_holidays.php              # Holiday/working day API endpoint
├── sqlconnect.php                # Database connection (PDO)
│
├── dashboard_config.json         # UI configuration (colors, fonts, schedule)
├── employee_counts.json          # Employee counts per department
│
├── assets/
│   ├── css/
│   │   ├── bootstrap.min.css     # Bootstrap 5 framework
│   │   └── inter.css             # Inter font styling
│   ├── js/
│   │   └── bootstrap.bundle.min.js
│   └── fonts/                    # Google Fonts (Kanit, Prompt, Sarabun)
│
├── docs/
│   ├── project_spec.md           # Detailed project specifications
│   ├── holiday_management_manual.md
│   └── holiday_and_schedule_design.md
│
└── summary_overall/
    └── index.php                 # Summary/aggregate dashboard view

```

---

## 🏗️ Architecture & Data Flow

### Request Flow
```
Browser Request
    ↓
index.php (Loads config & template)
    ↓
calculate_score.php (Executes SQL, calculates scores)
    ↓
SQL Server (RSMSSQL) - Two databases:
    • RSMSSQL.DAILY_FACTORY_5S (Factory divisions)
    • RSMSSQL.DAILY_OFFICE_5S (Office divisions)
    ↓
Dashboard Rendering (HTML/CSS/JS)
```

### Core Components

#### 1. **index.php** - Main Dashboard
- **Purpose**: Renders the real-time 5S dashboard
- **Key Functions**:
  - `evaluateCondition($score, $cond)` - Evaluates score against color conditions
  - `getScoreColors($score, $config)` - Returns color scheme for a score
  - Loads configuration from `dashboard_config.json`
  - Redirects to i-smartweb for production access
- **Outputs**: 3-column HTML table with dynamic styling
- **Refresh**: Every 5 minutes via JavaScript + daily reset at 07:30

#### 2. **calculate_score.php** - Score Calculation Engine
- **Purpose**: Core logic for computing 5S compliance scores
- **Responsibilities**:
  - Loads schedule configuration (work days, holidays, extra work days)
  - Builds complex SQL query with:
    - Working day calculations (excludes weekends & holidays)
    - Question count retrieval from MASTER_QUESTION tables
    - Actual scores from DALIY_SAFECHECK tables
    - Employee counts from `employee_counts.json`
  - Calculates percentage scores: `(Actual / Possible) × 100`
  - Groups data by AREA and sorts by score
- **SQL Query Strategy**:
  - Uses SQL CTE (Common Table Expressions)
  - Safe parameterization for dates
  - No direct string interpolation (sanitized via `preg_replace`)
  - Handles both Factory and Office databases
- **Output Variables**:
  - `$results` - Array of areas with calculated scores
  - `$average_score` - Master 5S score for entire facility
  - Additional supporting variables for template rendering

#### 3. **manage_employees.php** - Admin Management Interface
- **Purpose**: UI for administrators to manage configuration
- **Features**:
  - Save/update employee counts per department
  - Edit dashboard color scheme (title, month, default colors)
  - Configure column widths (must total 100%)
  - Set font sizes for all UI elements
  - Manage holidays and extra work days
  - Define scoring rules and color conditions (< 49%, 50-89%, ≥ 90%)
  - Validate column width totals before saving
- **Validation**:
  - Column widths must total exactly 100% (0.1% tolerance)
  - Numeric type enforcement for dimensions
  - Preserves text and color fields as strings
  - Dynamic condition reindexing
- **Files Modified**:
  - `dashboard_config.json` - UI and schedule config
  - `employee_counts.json` - Employee data

#### 4. **sqlconnect.php** - Database Connection
- **Credentials**: PDO connection to SQL Server
- **Server**: `192.168.115.253` (TUBE database)
- **Authentication**: `tubecuring:tubecuring@2021`
- **Protocol**: SQL Server Native Client (sqlsrv)
- **Error Handling**: PDO Exception mode enabled
- **Note**: Credentials are hardcoded - should use environment variables in production

#### 5. **api_holidays.php** - Holiday Endpoint
- **Purpose**: Provides holiday/working day data via API
- **Usage**: Called by JavaScript for date calculations

---

## 🔧 Configuration System

### dashboard_config.json
Centralized configuration file with three main sections:

#### 1. **column_widths** (Total must = 100%)
```json
{
  "no": 10,      // Row number column
  "area": 73,    // Department name column
  "score": 17    // Score value column
}
```

#### 2. **font_sizes** (in pixels)
```json
{
  "title": 70,           // Main header
  "month": 46,           // Month/year display
  "table_head": 28,      // Column headers
  "table_body": 37,      // Table row text
  "col_no": 35,          // Number font
  "col_area": 36,        // Area name font
  "col_score": 33        // Score number font
}
```

#### 3. **colors** - Color Scheme
- `title_color`: Main header color
- `month_color`: Month/year color
- `summary_header_bg`: Summary box background
- `default_score_color`: Default score color
- `scoring_mode`: "standard" (currently unused, for future expansion)

#### 4. **conditions** - Score-Based Color Rules
Array of objects with operators: `<`, `<=`, `>`, `>=`, `between`

**Example Condition**:
```json
{
  "operator": "<",
  "threshold": 49,
  "color": "#ff0000",        // Base text color
  "bg_color": "#000000",     // Background color
  "use_custom_no": true,     // Apply to row numbers?
  "color_no": "#ff0000",     // Custom color for numbers
  "bg_no": "#000000",        // Custom background for numbers
  "color_area": "#ffffff",   // Custom color for area names
  "bg_area": "#000000",      // Custom background for area names
  "use_custom_score": true,  // Apply to score column?
  "color_score": "#000000",  // Custom color for scores
  "bg_score": "#000000"      // Custom background for scores
}
```

#### 5. **schedule** - Working Days & Holidays
```json
{
  "work_days": [2, 3, 4, 5, 6],     // Mon-Fri (DATEPART(dw) format)
  "holidays": [
    {
      "date": "2026-04-13",
      "name": "Songkran"
    }
  ],
  "extra_work_days": []              // Days to count as working days
}
```

### employee_counts.json
Maps each department to factory/office employee counts:
```json
{
  "ENGINEERING": {
    "factory": "11",
    "office": "6"
  },
  "TIRE CURING": {
    "factory": "19",
    "office": "3"
  }
}
```

---

## 📊 Database Schema (External)

The system reads from **SQL Server** databases (not included in repo):
- `RSMSSQL.DAILY_FACTORY_5S.dbo.MASTER_QUESTION` - Factory questions
- `RSMSSQL.DAILY_OFFICE_5S.dbo.MASTER_QUESTION` - Office questions
- `RSMSSQL.DAILY_FACTORY_5S.dbo.DALIY_SAFECHECK` - Factory compliance records
- `RSMSSQL.DAILY_OFFICE_5S.dbo.DALIY_SAFECHECK` - Office compliance records

**Key Columns**:
- `AREA` - Department name
- `KEYID` - Question ID (1, 2 excluded from factory calculations)
- Implicit columns for date and compliance scores

---

## 🎨 UI/UX Design

### Layout
- **3-Column Table Format**: No.  |  Area Name  |  Score (%)
- **Responsive to 1080p**: Optimized for 24-27" monitors
- **30 Rows Per Page**: Shows all major departments without pagination
- **Color Coding**:
  - 🔴 Red (< 49%): Critical
  - 🟢 Green (50-89%): Acceptable
  - 🔵 Blue (≥ 90%): Excellent
- **Summary Box**: Large score display (top-right) with master 5S percentage

### Fonts
- Thai Text: Kanit, Prompt, Sarabun (via Google Fonts)
- English Text: Inter
- All configured in `/assets/fonts/`

### Auto-Refresh Mechanism
1. **Minor Refresh**: Every 5 minutes (data update only)
2. **Full Reset**: Daily at 07:30 (page reload + cache clear)
3. **Timezone**: Asia/Bangkok

---

## 🔐 Security Considerations

### Current Issues
- **⚠️ Hardcoded Credentials**: Database credentials in `sqlconnect.php` (line 3-6)
  - **Impact**: High - credentials exposed in version control
  - **Fix**: Use environment variables (`.env` file) with `getenv()`

### Current Protections
- **SQL Injection Prevention**:
  - Date values sanitized via `preg_replace('/[^0-9-]/', '', ...)`
  - Array casting for working_days_sql (`intval`)
  - PDO connection (parameterized queries ready but static SQL currently)
- **File Inclusion**: No user-controlled file paths
- **Session Management**: `session_start()` in place

### Recommendations
1. Move credentials to environment variables or `.env` file
2. Use prepared statements for any user input (currently not applicable)
3. Validate JSON file contents before processing
4. Implement access control for `manage_employees.php`
5. Add CSRF tokens to POST forms in `manage_employees.php`

---

## 🚀 Development Workflow

### Setting Up Locally
1. **Prerequisites**:
   - PHP 7.4+ with SQL Server PDO driver (sqlsrv extension)
   - Web server (Apache/Nginx)
   - Access to SQL Server database `TUBE` at `192.168.115.253`

2. **Installation**:
   ```bash
   git clone <repo>
   cd /home/user/5S
   # Create .env file with credentials (not in version control)
   # Start web server
   ```

3. **Configuration**:
   - Edit `dashboard_config.json` for UI customization
   - Edit `employee_counts.json` for department employee counts
   - Update holidays in `manage_employees.php` admin UI

### Making Changes

#### Adding a New Department
1. Add entry to `employee_counts.json`:
   ```json
   "NEW_DEPARTMENT": {
     "factory": "5",
     "office": "2"
   }
   ```
2. Employee counts must exist for scoring to calculate correctly
3. Department appears automatically once data exists in SQL database

#### Modifying Color Scheme
1. Edit `dashboard_config.json` colors section, OR
2. Use `manage_employees.php` admin UI (preferred)
3. Restart/refresh dashboard for changes to apply

#### Changing Refresh Interval
- **Minor refresh (5 min)**: Search "5 * 60 * 1000" in `index.php` JavaScript
- **Daily reset (07:30)**: Modify schedule logic in `index.php`

#### Adjusting Column Widths
- Must total exactly 100% (validated in `manage_employees.php`)
- Edit `dashboard_config.json` → `column_widths`
- All three columns must be present

### Testing Changes
1. **Local Testing**: Load `index.php` in browser, verify layout
2. **Admin Testing**: Use `manage_employees.php` to test config changes
3. **Database Testing**: Verify SQL Server connection in `sqlconnect.php`
4. **Refresh Testing**: Check auto-refresh works at 5-min and 07:30 intervals

### Git Workflow
- **Branch Strategy**: Feature branches from `main`
- **Commit Messages**: Clear, descriptive (e.g., "Fix score calculation for office 5S")
- **Push**: To designated feature branches only
- **Production**: Deploy from `main` branch after testing

---

## 🐛 Debugging Tips

### Common Issues

#### "No data showing on dashboard"
- Check SQL Server connection in `sqlconnect.php`
- Verify `TUBE` database is accessible at `192.168.115.253`
- Ensure `employee_counts.json` has entries for departments
- Check browser console for JavaScript errors

#### "Column widths not valid (not 100%)"
- Verify all three widths in `column_widths` sum to exactly 100%
- Allow 0.1% tolerance for floating-point math
- Check `manage_employees.php` validation message

#### "Scores not updating every 5 minutes"
- Check `index.php` JavaScript setInterval (should be 5*60*1000 ms)
- Verify browser doesn't have page freeze/sleep settings
- Check browser developer tools for fetch errors

#### "Color conditions not applying"
- Verify condition operators are valid: `<`, `<=`, `>`, `>=`, `between`
- For "between", ensure `threshold` < `threshold2`
- Check JSON syntax in `dashboard_config.json`
- Clear browser cache and reload

#### "New employee counts not reflected"
- Save changes via `manage_employees.php` (updates JSON file)
- Database query doesn't cache results, should update on refresh
- Check file permissions on `employee_counts.json` (must be writable)

---

## 📝 Code Conventions

### PHP
- **Spacing**: 4-space indentation
- **Naming**: `$snake_case` for variables, `camelCase()` for functions
- **Comments**: Minimal; only explain non-obvious logic (score calculations, SQL optimization)
- **Error Handling**: Try-catch for database exceptions, silent fail for missing config files (fallback defaults)
- **Files**: UTF-8 encoding with BOM-free headers

### Configuration JSON
- **Formatting**: Pretty-printed with 4-space indentation (JSON_PRETTY_PRINT)
- **Naming**: `snake_case` for all keys
- **Validation**: Column widths checked in PHP before saving
- **Comments**: Not used in JSON (use adjacent MD file for documentation)

### HTML/CSS
- **Structure**: Semantic HTML5, Bootstrap 5 grid system
- **Responsive**: Optimized for 1080p (1920×1080) displays
- **Fonts**: External Google Fonts loaded from CDN
- **Classes**: BEM-inspired naming (e.g., `score-cell__value`)

### JavaScript
- **Vanilla JS**: No framework dependencies (no jQuery)
- **Naming**: `camelCase` for variables and functions
- **Timing**: setInterval for periodic refresh; use 5*60*1000 for milliseconds
- **DOM**: querySelector for element selection

---

## 📚 Documentation Files

Existing documentation in `/docs/`:
- **project_spec.md** - Detailed technical specifications
- **holiday_management_manual.md** - Instructions for managing holidays
- **holiday_and_schedule_design.md** - Design notes for schedule system

---

## 🔄 Maintenance & Operations

### Daily Operations
- **07:30 AM**: Automatic full page reset (cache clear + reload)
- **Every 5 min**: Data refresh via fetch request
- **No manual interventions needed** for normal operation

### Weekly Maintenance
- Review score trends in `summary_overall/` view
- Check for any SQL Server connectivity issues
- Verify employee counts are up-to-date in `manage_employees.php`

### Monthly Maintenance
- Update holidays in `dashboard_config.json` for next month
- Review and adjust color thresholds if needed
- Check database query performance (large datasets)

### Backup & Recovery
- **Config Backup**: `dashboard_config.json` + `employee_counts.json`
- **Database**: External backup (handled by IT, not in repo)
- **Version Control**: All code changes tracked in Git

---

## 📞 Support & Contact

### Key Contacts (to be filled)
- **IT Support**: [Department/Contact]
- **Database Admin**: [Department/Contact]
- **Dashboard Owner**: [Department/Contact]

### Troubleshooting
1. Check `/docs/` for common issues
2. Review error messages in browser console (F12)
3. Verify database connectivity: `sqlconnect.php` → test line 10-16
4. Check JSON file syntax: Use online JSON validator

---

## 🔮 Future Enhancements

Potential improvements for future versions:
1. **Environment-based configuration**: Use `.env` for credentials
2. **Database migrations**: Version control for schema changes
3. **API layer**: RESTful API for third-party integrations
4. **Caching**: Redis caching for score calculations
5. **Notifications**: Email alerts for critical scores (< 50%)
6. **Historical graphs**: Trend analysis over time
7. **Role-based access**: Different permissions for admins vs. viewers
8. **Mobile responsiveness**: Optimize for tablets/phones
9. **Performance optimization**: Indexed SQL queries, query caching
10. **Logging system**: Audit trail for config changes

---

## 📄 File Modification Reference

### Critical Files (Rarely Modified)
- `sqlconnect.php` - Database connection (only update if DB moves)
- `calculate_score.php` - Scoring logic (thorough testing required)

### Frequently Modified
- `dashboard_config.json` - Colors, fonts, holidays (via UI or direct edit)
- `employee_counts.json` - Employee data (via `manage_employees.php`)

### Development Files
- `index.php` - Main dashboard template (layout changes)
- `manage_employees.php` - Admin UI (new features or improvements)

---

## ✅ Quality Checklist for AI Assistants

Before committing any changes:
- [ ] Column widths in config total 100% (if modified)
- [ ] JSON files are valid (no syntax errors)
- [ ] SQL queries properly sanitize dates and variables
- [ ] Database connection credentials are not hardcoded in new code
- [ ] No new external dependencies added without approval
- [ ] Code follows established conventions (spacing, naming)
- [ ] Changes are tested on local instance
- [ ] Commit messages are clear and descriptive
- [ ] No sensitive data (passwords, API keys) in commits

---

**Generated**: 2026-04-24  
**Version**: 1.0  
**Maintainer**: Claude Code Assistant
