# COVID-19 Management System

A comprehensive web-based COVID-19 management system built with PHP and MySQL. This system provides separate interfaces for administrators, hospitals, and patients to manage COVID-19 related activities including appointments, test results, vaccinations, and more.

## Features

### Admin Panel
- **Dashboard**: Overview statistics for patients, hospitals, appointments, and vaccines
- **Hospital Management**: Approve/reject hospital registrations
- **Vaccine Management**: Add vaccines and manage availability status
- **Patient Management**: View patient details, appointments, and medical history
- **Appointment Management**: View appointments with date filters and Excel export
- **Secure Authentication**: Admin login/logout system

### Hospital Panel
- **Dashboard**: Hospital-specific statistics and recent activities
- **Appointment Management**: Approve/reject patient appointments
- **Test Results Management**: Add and manage COVID-19 test results
- **Vaccination Management**: Record vaccination doses and manage vaccination records
- **Profile Management**: Update hospital information and settings
- **Approval System**: Pending approval notification for new hospitals

### Patient Panel
- **Dashboard**: Personal statistics and recent activities
- **Appointment Booking**: Book test and vaccination appointments
- **Appointment Management**: View and cancel appointments
- **Test Results**: View COVID-19 test history and results
- **Vaccination History**: Track vaccination doses and view available vaccines
- **Profile Management**: Update personal information and settings

## Technology Stack

- **Backend**: PHP 7.4+
- **Database**: MySQL 5.7+
- **Frontend**: HTML5, CSS3, JavaScript
- **Framework**: Bootstrap 5.1.3
- **Icons**: Font Awesome 6.0.0
- **Additional Libraries**: SheetJS (for Excel export)

## Database Schema

The system uses the following main tables:
- `users` - User accounts (admin, hospital, patient)
- `hospitals` - Hospital information and approval status
- `patients` - Patient profiles and details
- `appointments` - Test and vaccination appointments
- `test_results` - COVID-19 test results
- `vaccines` - Available vaccines information
- `vaccinations` - Vaccination records and doses

## Installation

### Prerequisites
- XAMPP/WAMP/LAMP server
- PHP 7.4 or higher
- MySQL 5.7 or higher
- Web browser

### Setup Instructions

1. **Clone/Download the project**
   ```
   Place the covid19 folder in your web server directory (e.g., C:\xampp\htdocs\)
   ```

2. **Database Setup**
   - Start your MySQL server
   - Open phpMyAdmin or MySQL command line
   - Create a new database named `covid19`
   - Import the `covid19.sql` file to create tables and sample data

3. **Configuration**
   - Update database credentials in:
     - `admin_panel/config/database.php`
     - `userpanel/config/database.php`
   - Default settings are configured for localhost with root user and no password

4. **File Permissions**
   - Ensure web server has read/write permissions to the project directory

## Default Login Credentials

### Admin Panel (`/admin_panel/login.php`)
- **Email**: admin@covid19system.com
- **Password**: admin123

### User Panel (`/userpanel/login.php`)
Sample accounts are created during database setup:

**Hospital Account**:
- **Email**: hospital@example.com
- **Password**: hospital123
- **Role**: Hospital

**Patient Account**:
- **Email**: patient@example.com
- **Password**: patient123
- **Role**: Patient

## Usage

### For Administrators
1. Access the admin panel at `/admin_panel/login.php`
2. Login with admin credentials
3. Manage hospitals, vaccines, patients, and appointments
4. Export appointment data to Excel

### For Hospitals
1. Register at `/userpanel/register.php` or login at `/userpanel/login.php`
2. Wait for admin approval (new hospitals)
3. Manage appointments, add test results, record vaccinations
4. Update hospital profile and information

### For Patients
1. Register at `/userpanel/register.php` or login at `/userpanel/login.php`
2. Book appointments for tests or vaccinations
3. View test results and vaccination history
4. Manage personal profile

## Security Features

- **Password Hashing**: All passwords are hashed using PHP's `password_hash()`
- **SQL Injection Protection**: PDO prepared statements used throughout
- **Session Management**: Secure session handling with role-based access
- **Input Validation**: Server-side validation for all forms
- **Role-based Access Control**: Different access levels for admin, hospital, and patient

## File Structure

```
covid19/
├── admin_panel/
│   ├── config/
│   │   └── database.php
│   ├── appointments.php
│   ├── dashboard.php
│   ├── hospitals.php
│   ├── login.php
│   ├── logout.php
│   ├── patient_details.php
│   ├── patients.php
│   └── vaccines.php
├── userpanel/
│   ├── config/
│   │   └── database.php
│   ├── book_appointment.php
│   ├── dashboard.php
│   ├── hospital_dashboard.php
│   ├── hospital_pending.php
│   ├── hospital_profile.php
│   ├── login.php
│   ├── logout.php
│   ├── manage_appointments.php
│   ├── my_appointments.php
│   ├── patient_dashboard.php
│   ├── profile.php
│   ├── register.php
│   ├── test_results.php
│   ├── test_results_management.php
│   ├── vaccination_history.php
│   └── vaccination_management.php
├── covid19.sql
└── README.md
```

## Key Features Explained

### Appointment System
- Patients can book appointments for tests or vaccinations
- Hospitals can approve/reject appointments
- Date and time scheduling with validation
- Status tracking (pending, approved, rejected)

### Test Results Management
- Hospitals can add test results for approved test appointments
- Results categorized as positive/negative with remarks
- Patients can view their test history and statistics

### Vaccination Management
- Multi-dose vaccination tracking (1st dose, 2nd dose, boosters)
- Vaccine inventory management by admin
- Vaccination records with dose numbers and dates
- Available vaccines information for patients

### Reporting and Analytics
- Dashboard statistics for all user types
- Excel export functionality for appointment data
- Visual charts and metrics
- Historical data tracking

## Customization

### Adding New Vaccine Types
1. Login as admin
2. Go to Vaccine Management
3. Add new vaccine with manufacturer details
4. Set availability status

### Modifying User Roles
- Edit the `role` ENUM in the `users` table
- Update role checking functions in config files
- Modify navigation and access controls accordingly

### Styling Customization
- CSS styles are embedded in each PHP file
- Bootstrap classes can be modified for different themes
- Font Awesome icons can be replaced or updated

## Troubleshooting

### Common Issues

1. **Database Connection Error**
   - Check MySQL server is running
   - Verify database credentials in config files
   - Ensure `covid19` database exists

2. **Login Issues**
   - Verify user exists in database
   - Check password hashing (use default accounts first)
   - Clear browser cache and cookies

3. **Permission Errors**
   - Check file permissions on web server
   - Ensure PHP has write access to session directory

4. **Missing Features**
   - Verify all files are uploaded correctly
   - Check for PHP errors in server logs
   - Ensure all database tables are created

### Error Logging
- Enable PHP error reporting for development
- Check web server error logs for detailed information
- Use browser developer tools for JavaScript errors

## Future Enhancements

Potential improvements for the system:
- Email notifications for appointments and results
- SMS integration for alerts
- Mobile responsive improvements
- API endpoints for mobile app integration
- Advanced reporting and analytics
- Multi-language support
- Backup and restore functionality

## Support

For technical support or questions:
- Check the troubleshooting section
- Review PHP and MySQL error logs
- Ensure all prerequisites are met
- Verify database schema matches the provided SQL file

## License

This project is developed for educational and demonstration purposes. Feel free to modify and use according to your needs.

## Version History

- **v1.0** - Initial release with core functionality
  - Admin panel with hospital and vaccine management
  - Patient registration and appointment booking
  - Hospital dashboard and appointment management
  - Test results and vaccination tracking
  - Excel export functionality

---

**Note**: This system is designed for demonstration purposes. For production use, additional security measures, error handling, and testing should be implemented.
