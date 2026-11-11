// attendance.js - Frontend interactions for attendance module

document.addEventListener('DOMContentLoaded', () => {
    console.log("Attendance module script loaded successfully.");

    // Example function to fetch reports asynchronously if needed
    window.fetchAttendanceReports = async function() {
        try {
            let response = await fetch('attendance_api.php');
            let result = await response.json();
            if (result.status === 'success') {
                console.log("Fetched reports:", result.data);
            } else {
                console.error("Error fetching reports:", result.message);
            }
        } catch (error) {
            console.error("Network or parsing error:", error);
        }
    };
});