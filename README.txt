============================================================
  CivicTrack — Local Problem Reporting System
  BCA Project I — Tribhuvan University
============================================================

SETUP INSTRUCTIONS
==================

STEP 1 — Install XAMPP
  Download from: https://www.apachefriends.org
  Install and start Apache + MySQL from the XAMPP Control Panel

STEP 2 — Copy project folder
  Copy the entire "civictrack" folder to:
  Windows:  C:\xampp\htdocs\civictrack\
  Mac/Linux: /opt/lampp/htdocs/civictrack/

STEP 3 — Create the database
  Open browser → http://localhost/phpmyadmin
  Click "New" on the left sidebar
  Create database named: civictrack_db
  Click on civictrack_db → go to "Import" tab
  Choose file: civictrack/database/civictrack.sql
  Click "Go" — all tables and sample data will be created

STEP 4 — Open the project
  http://localhost/civictrack/public/login.php

============================================================
DEMO LOGIN ACCOUNTS (password for ALL is: password)
============================================================

  Municipality Admin → admin@civictrack.gov.np
  Ward Admin (Ward 1) → ward1@civictrack.gov.np
  Dept. Officer (Road) → road1@civictrack.gov.np
  Citizen → citizen1@gmail.com

============================================================
PROJECT FOLDER STRUCTURE
============================================================

civictrack/
├── database/
│   └── civictrack.sql          ← Run this first
├── config/
│   ├── db.php                  ← Database connection
│   └── constants.php           ← System constants
├── public/
│   ├── login.php               ← Entry point
│   ├── index.php               ← Role-based router
│   └── logout.php
├── includes/
│   ├── auth_check.php          ← Security gate
│   ├── header.php
│   ├── sidebar.php
│   └── footer.php
├── modules/
│   ├── admin/                  ← Municipality Admin pages
│   │   ├── dashboard.php
│   │   ├── wards.php
│   │   ├── departments.php
│   │   ├── users.php
│   │   └── issues.php
│   ├── ward/                   ← Ward Admin pages
│   │   ├── dashboard.php
│   │   └── pending.php
│   ├── department/             ← Dept Officer pages
│   │   └── dashboard.php
│   └── citizen/                ← Citizen pages
│       ├── dashboard.php
│       └── report.php
├── assets/
│   └── css/
│       └── style.css
├── uploads/                    ← Image uploads go here
│   ├── issues/
│   └── progress/
└── README.txt

============================================================
WHAT IS WORKING (First Defence)
============================================================

✅ Login / Logout with session management
✅ Role-based routing (4 different dashboards)
✅ Session timeout (2 hours)
✅ Access control — wrong role gets 403 page

Municipality Admin:
  ✅ Dashboard with live stats (6 stat cards)
  ✅ Ward performance progress bars
  ✅ Recent issues table
  ✅ Manage Wards (add/delete)
  ✅ Manage Departments (add/activate/deactivate)
  ✅ Create Users (ward admin, officer, citizen)
  ✅ Deactivate/Activate users
  ✅ View All Issues with status filter tabs

Ward Admin:
  ✅ Dashboard with ward-specific stats
  ✅ Pending issues queue (sorted by priority)
  ✅ Verify / Reject / Assign issues to departments
  ✅ Audit trail recorded for every action

Department Officer:
  ✅ View tasks assigned to department
  ✅ Accept task (self-assign)
  ✅ Add progress updates
  ✅ Mark issue as Resolved

Citizen:
  ✅ Dashboard with personal issue stats
  ✅ Report new issue form with priority calculation
  ✅ View all submitted issues with status tracking

============================================================
