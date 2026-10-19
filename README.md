# سَكَن | SAKAN Property Manager

<p align="center">
  <strong>نظام متكامل لإدارة العقارات والإيجارات والتحصيلات المالية</strong>
</p>

<p align="center">
  <a href="#النسخة-العربية">العربية</a> |
  <a href="#english-version">English</a>
</p>

<p align="center">
  <img src="https://img.shields.io/badge/PHP-8%2B-777BB4?style=for-the-badge&logo=php&logoColor=white" alt="PHP">
  <img src="https://img.shields.io/badge/MySQL-MariaDB-4479A1?style=for-the-badge&logo=mysql&logoColor=white" alt="MySQL">
  <img src="https://img.shields.io/badge/Interface-Arabic%20RTL-2E7D32?style=for-the-badge" alt="Arabic RTL">
  <img src="https://img.shields.io/badge/Architecture-PDO-59666C?style=for-the-badge" alt="PDO">
</p>

---

# النسخة العربية

## نبذة عن المشروع

**سَكَن | SAKAN Property Manager** هو نظام ويب لإدارة العقارات والوحدات السكنية وعقود الإيجار والمستأجرين والتحصيلات المالية من خلال لوحة تحكم عربية متكاملة.

يهدف النظام إلى تنظيم العمليات اليومية لمالك العقار، ومتابعة الاستحقاقات المالية، وتوثيق الدفعات والمصروفات، وإدارة طلبات الصيانة، مع توفير التقارير والإشعارات اللازمة لتسهيل متابعة العقارات.

صُمم النظام ليديره مالك العقار أو الموظفون المصرح لهم، دون الحاجة إلى حسابات دخول للمستأجرين.

## المميزات الرئيسية

- **إدارة العقارات والمباني:** تنظيم العقارات ومعلوماتها الأساسية.
- **إدارة الوحدات:** متابعة الوحدات السكنية وحالاتها.
- **إدارة المستأجرين:** حفظ بيانات المستأجرين وربطها بالعقود.
- **إدارة عقود الإيجار:** تسجيل العقود ومددها وقيمها وتفاصيلها.
- **استحقاقات الإيجار:** متابعة مواعيد الاستحقاق والمبالغ المتأخرة.
- **التحصيلات المالية:** تسجيل الدفعات ومتابعة المبالغ المدفوعة والمتبقية.
- **الإيصالات:** توثيق عمليات الدفع والرجوع إليها.
- **إدارة المصروفات:** تنظيم المصروفات العقارية ومتابعة تكاليف التشغيل.
- **الصيانة والبلاغات:** تسجيل طلبات الصيانة ومتابعة حالاتها.
- **التنبيهات:** متابعة الاستحقاقات والتنبيهات المرتبطة بالعمليات.
- **التذكير عبر واتساب:** تجهيز رسالة تذكير للمستأجر المتأخر، مع اشتراط مراجعة المسؤول وتأكيده قبل فتح واتساب وإرسال الرسالة.
- **التقارير:** استخراج التقارير بصيغة CSV حيثما كانت مدعومة.
- **المستخدمون والصلاحيات:** تنظيم وصول المسؤولين والموظفين وفق الصلاحيات.
- **سجل التدقيق:** توثيق العمليات المهمة للمساعدة في المراجعة والمتابعة.
- **النسخ الاحتياطي:** دعم إجراءات النسخ الاحتياطي وفق إعدادات الخادم.

## التقنيات المستخدمة

| التقنية | الاستخدام |
|---|---|
| PHP 8+ | منطق النظام ومعالجة العمليات |
| MySQL / MariaDB | تخزين البيانات وإدارتها |
| PDO | الاتصال بقاعدة البيانات والاستعلامات |
| HTML5 | بناء صفحات النظام |
| CSS3 | التصميم والتنسيق والاستجابة للشاشات |
| JavaScript | التفاعلات داخل الواجهة |
| Apache / WAMP | بيئة التشغيل المحلية |
| phpMyAdmin | إدارة قاعدة البيانات واستيراد ملفات SQL |

## متطلبات التشغيل

- Windows أو بيئة خادم تدعم PHP وMySQL.
- WAMP أو Apache مع PHP 8 أو أحدث.
- MySQL أو MariaDB.
- تفعيل امتداد `pdo_mysql`.
- متصفح ويب حديث.

## طريقة التثبيت

1. نزّل المشروع أو استنسخ المستودع إلى جهازك.
2. ضع مجلد المشروع داخل مجلد `www` في WAMP.
3. أنشئ قاعدة بيانات باسم `sakan_property_manager` أو استخدم الاسم المحدد في إعدادات المشروع.
4. استورد ملف قاعدة البيانات `database/schema.sql` باستخدام phpMyAdmin.
5. راجع إعدادات الاتصال بقاعدة البيانات في ملفات الإعدادات المحلية، واستخدم `config/local.php` إذا كان المشروع يعتمد عليه.
6. شغّل خادم Apache وMySQL من WAMP.
7. افتح صفحة الإعداد الأولي:

   `http://localhost/sakan_property_manager/setup/`

8. أكمل إعداد النظام وأنشئ حساب المسؤول الأول وفق تعليمات صفحة الإعداد.
9. تأكد من ضبط صلاحيات المجلدات وإعدادات رفع الملفات والنسخ الاحتياطي قبل الاستخدام الفعلي.

> **ملاحظة:** قد تختلف بعض الخطوات بحسب إعدادات جهازك ونسخة المشروع. راجع ملفات التوثيق وإعدادات المشروع قبل تشغيله على خادم إنتاجي.

## الأمان وحماية البيانات

يراعي تصميم النظام ممارسات أمنية مهمة، منها استخدام الاستعلامات المجهزة عبر PDO، وإدارة كلمات المرور بصورة آمنة، والتحقق من المدخلات والصلاحيات، وحماية النماذج من طلبات CSRF، والحد من مخاطر حقن SQL وXSS.

يُنصح بمراجعة إعدادات الخادم والنسخ الاحتياطي وصلاحيات الملفات قبل استخدام النظام مع بيانات عقارية ومالية حقيقية.

## المطوّر

**أواب باشري | Awab Bashery**  
العلامة البرمجية: **AwabBuilds**

GitHub: [awabwdbashry-sketch](https://github.com/awabwdbashry-sketch)

مستودع المشروع: [sakan_property_manager](https://github.com/awabwdbashry-sketch/sakan_property_manager)

---

# English Version

## Project Overview

**SAKAN Property Manager** is a web-based property management system designed to organize properties, residential units, tenants, rental contracts, rent installments, payments, expenses, and maintenance operations through an Arabic right-to-left interface.

The system is intended for a property owner and authorized staff to manage daily operations, monitor rental obligations, document financial transactions, and follow up on maintenance requests from one centralized dashboard.

It is designed for a single property management entity and does not require tenant login accounts.

## Key Features

- **Property Management:** Organize properties and buildings.
- **Unit Management:** Maintain unit information and occupancy status.
- **Tenant Management:** Store tenant records and associate them with rental contracts.
- **Rental Contracts:** Manage lease details, durations, and rental values.
- **Rent Installments:** Track due dates, outstanding balances, and overdue rent.
- **Payment Collection:** Record payments and monitor paid and remaining amounts.
- **Receipts:** Maintain records of payment transactions.
- **Expense Management:** Organize property-related expenses.
- **Maintenance Requests:** Record maintenance issues and track their status.
- **Notifications:** Follow up on relevant rental and operational events.
- **WhatsApp Reminders:** Prepare overdue-rent reminder messages for review. An authorized user must confirm before WhatsApp is opened and the message is sent; messages are not sent automatically.
- **Reports:** Export supported reports in CSV format.
- **User Roles and Permissions:** Control access for administrators and authorized staff.
- **Audit Logs:** Maintain records of important system activities.
- **Backups:** Support backup procedures subject to server configuration.

## Technologies Used

| Technology | Purpose |
|---|---|
| PHP 8+ | Application logic and server-side processing |
| MySQL / MariaDB | Relational database management |
| PDO | Database connectivity and prepared statements |
| HTML5 | Page structure |
| CSS3 | Styling and responsive layouts |
| JavaScript | Client-side interactions |
| Apache / WAMP | Local development environment |
| phpMyAdmin | Database administration and SQL imports |

## System Requirements

- Windows or a server environment supporting PHP and MySQL.
- WAMP or Apache with PHP 8 or later.
- MySQL or MariaDB.
- The `pdo_mysql` PHP extension enabled.
- A modern web browser.

## Installation

1. Download or clone the project repository.
2. Place the project directory inside the WAMP `www` directory.
3. Create a database named `sakan_property_manager`, or use the database name specified by the project configuration.
4. Import `database/schema.sql` through phpMyAdmin.
5. Review the database connection settings and configure the local settings file, such as `config/local.php`, if required by the project.
6. Start Apache and MySQL from WAMP.
7. Open the setup page:

   `http://localhost/sakan_property_manager/setup/`

8. Complete the initial setup and create the first administrator account as instructed by the setup page.
9. Configure file permissions, upload limits, and backup settings before production use.

> **Note:** Installation details may vary depending on the project version and local environment. Review the included documentation and configuration files before deployment.

## Security Considerations

The system follows important security practices, including PDO prepared statements, secure password handling, input and permission validation, CSRF protection, and measures against SQL injection and cross-site scripting (XSS).

Before using the system with real property or financial data, review the server configuration, file permissions, backup procedures, and deployment security.

## Developer

**Awab Bashery | أواب باشري**  
Developer Brand: **AwabBuilds**

GitHub Profile: [awabwdbashry-sketch](https://github.com/awabwdbashry-sketch)

Project Repository: [sakan_property_manager](https://github.com/awabwdbashry-sketch/sakan_property_manager)

---

<p align="center">
  <strong>SAKAN Property Manager — Built by AwabBuilds</strong>
</p>
