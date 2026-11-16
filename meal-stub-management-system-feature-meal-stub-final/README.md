Free Meal Stub Management System

A web-based system for managing attendance reports, meal stub issuance, distribution, and reporting at La Verdad Christian College (LVCC).
User Roles
- Secretary: Records and submits attendance reports, then tracks meal stub distribution.
- PSAS Staff: Reviews attendance reports, approves or rejects them, and generates meal stub batches from approved reports.
Main Modules
1. Login and user management
2. Attendance report encoding and submission
3. Attendance verification and approval
4. Meal stub batch generation
5. Individual stub distribution tracking
6. Reports and quality assurance
System Workflow
1. The Secretary logs in and records an attendance report.
2. The Secretary submits the report for review.
3. PSAS Staff checks the submitted information.
4. PSAS Staff approves or rejects the report.
5. Once approved, the system creates a stub batch and individual meal stub records.
6. The Secretary tracks the distribution of generated stubs.
7. Reports are reviewed for monitoring and summary purposes.
Technology Stack
- PHP
- SQLite
- PDO
- HTML, CSS, and Vanilla JavaScript
- PHP built-in development server
Requirements
- PHP with PDO SQLite enabled
- A web browser
- A code editor such as Visual Studio Code
Check enabled PHP extensions:
php -m
Look for PDO, pdo_sqlite, and sqlite3.
Running Locally
1. Open a terminal in the project directory.
2. Start the PHP development server:
   php -S 127.0.0.1:8000 router.php
3. Open http://127.0.0.1:8000 in your browser.
Database Notes
The SQLite database is stored at storage/database.sqlite. The application uses PDO to communicate with SQLite.

Important: If you are using an existing project database, do not run setup, migration, or seeding scripts unless your team has confirmed they are needed and has made a backup. Some scripts are intended only for a fresh database and may create or alter data.
Security Practices
Where implemented, the application should:
- Store passwords as hashes rather than plain text.
- Verify passwords during login.
- Check the user's session and role before allowing access to protected modules.
- Use PDO prepared statements for database queries.
- Validate submitted form data on the server.
- Protect state-changing requests against CSRF.
- Escape user-provided output when displaying it in HTML.
Group Task Distribution
Member	Assigned responsibility
Clark	Database connection and login
Kyle	Frontend layout and user interface
Febe	Secretary module: attendance
Matthew	PSAS Staff module: attendance verification and stub generator
Rovie	Secretary module: stub distribution
Irrad	Reports, integration, and quality assurance


Suggested Demonstration Flow
1. Show the login page and sign in using a prepared account.
2. Show the main interface and navigation.
3. Demonstrate attendance report encoding and submission as Secretary.
4. Switch to the PSAS Staff account and review the submitted report.
5. Demonstrate approval or rejection.
6. If approved, show the generated stub batch and individual stub records.
7. Demonstrate distribution tracking.
8. Show the reports or summary page.
Demonstration Reminders
- Use prepared test data and accounts.
- Confirm that the correct database is being used before recording.
- Avoid running setup or migration scripts on the original database during the demonstration.
- Do not show real student personal information or passwords in the recording.