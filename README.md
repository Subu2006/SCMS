Smart College Management System (SCMS) - Enterprise Edition

🚀 Overview

A God-Level, ERP-grade full-stack PHP application featuring Academic Risk Detection, an AI-powered Automatic Study Planner, and dynamic Chart.js analytics. Built strictly on 3-tier architecture with normalized 3NF MySQL.

⚙️ Features

Academic Risk Engine: Automatically detects students with Attendance < 75% or Marks < 40.

AI Study Planner: Dynamically generates weekly revision plans using JavaScript logic.

Role-Based Access: Complete isolation between Admin, Faculty, and Student Data.

Bank-Grade Security: PDO Prepared statements, password_hash() for Bcrypt security.

💡 How to Run

Move the scms folder to your local server (XAMPP -> htdocs).

Open phpMyAdmin and create a database named scms.

Import the generated database.sql code into the scms database.

Visit http://localhost/scms/ in your browser.

🔐 Demo Credentials

Admin: admin@gmail.com | Pass: Admin@123

Faculty: subratp762@gmail.com | Pass: Subrat

Student: shreyanshp10072014@gmail.com | Pass: Shreyansh


# 🎓 Smart College Management System (SCMS) - Enterprise Edition

![PHP](https://img.shields.io/badge/PHP-8.0+-777BB4?style=for-the-badge&logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-3NF_Normalized-4479A1?style=for-the-badge&logo=mysql&logoColor=white)
![Bootstrap](https://img.shields.io/badge/Bootstrap-5.3-7952B3?style=for-the-badge&logo=bootstrap&logoColor=white)
![JavaScript](https://img.shields.io/badge/JavaScript-Async_DOM-F7DF1E?style=for-the-badge&logo=javascript&logoColor=black)
![Chart.js](https://img.shields.io/badge/Chart.js-Data_Visuals-FF6384?style=for-the-badge&logo=chartdotjs&logoColor=white)

An enterprise-grade, full-stack web application designed to digitalize, automate, and optimize the administrative, academic, and financial operations of modern educational institutions.

## 🚀 Overview

Traditional collegiate infrastructures rely heavily on fragmented, spreadsheet-based systems. **SCMS** resolves these bottlenecks by implementing a centralized, 3-Tier Client-Server architecture. Beyond standard CRUD operations, this project introduces next-generation enterprise functionalities including a self-healing database engine, real-time asynchronous data filtering, bulk demographic automation, and predictive AI analytics.

## ✨ Enterprise Features

* **🛡️ Role-Based Access Control (RBAC):** Strictly isolated, secure environments for Administrators, Faculty, and Students.
* **🧠 Academic Risk Engine:** A backend algorithm that continuously scans the database, instantly flagging students with attendance < 75% or aggregate marks < 40.
* **🤖 AI Study Planner:** A client-side JavaScript engine that analyzes a student's weakest subjects and dynamically generates a personalized, weekly remedial revision schedule.
* **⚡ 1-Click Enterprise Automations:**
  * **Batch Grading:** Excel-style grid for lag-free, mass transcript updates.
  * **Bulk Semester Promotion:** Upgrades entire departments instantly.
  * **Mass Invoicing:** Generates thousands of financial fee records in milliseconds.
* **📈 Real-Time BI Dashboard:** Integrates `Chart.js` to parse raw financial and attendance data into interactive visual KPIs.
* **🛠️ Zero-Setup Database Healer:** An automated initialization script that detects missing tables/columns and auto-alters the MySQL schema upon boot to prevent runtime crashes.

## 🏗️ System Architecture

SCMS is built on a strictly normalized **3rd Normal Form (3NF)** relational database to ensure absolute data integrity and zero redundancy.

```mermaid
graph TD
    subgraph Presentation_Tier [Frontend - Presentation Tier]
        UI[HTML5 / CSS3 / Bootstrap 5]
        Dash[Role-Based Dashboards]
        Charts[Chart.js BI Analytics]
    end

    subgraph Application_Tier [Backend - Application Tier]
        Auth[Bcrypt Security & Session Router]
        Core[PHP 8.0 Core Business Logic]
        AI[AI Study Planner Engine]
        Risk[Academic Risk Detector]
    end

    subgraph Data_Tier [Database - Data Tier]
        DB[(MySQL 3NF Database)]
        SelfHeal[Zero-Setup Auto-Healing Engine]
    end

    UI <-->|AJAX / HTTP| Auth
    Dash <--> Core
    Charts <--> Core
    Auth <--> DB
    Core <-->|PDO Prepared Statements| DB
    AI <--> DB
    Risk <--> DB
