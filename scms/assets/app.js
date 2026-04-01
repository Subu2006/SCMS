// --- 1. Analytics Dashboard (Chart.js) ---
document.addEventListener("DOMContentLoaded", function () {
    const ctx = document.getElementById('performanceChart');
    if (ctx && typeof chartLabels !== 'undefined' && typeof chartDataValues !== 'undefined') {
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: chartLabels,
                datasets: [{
                    label: 'Average Marks',
                    data: chartDataValues,
                    backgroundColor: 'rgba(67, 97, 238, 0.7)',
                    borderColor: 'rgba(67, 97, 238, 1)',
                    borderWidth: 1,
                    borderRadius: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: { y: { beginAtZero: true, max: 100 } },
                plugins: { legend: { display: false } }
            }
        });
    }
});

// --- 2. Automatic Study Planner Generation (JS Engine) ---
function generatePlanner() {
    const container = document.getElementById('plannerTableContainer');
    const resultSection = document.getElementById('plannerResult');
    
    if (!container || typeof weakSubjects === 'undefined') return;

    let html = '<div class="table-responsive"><table class="table table-bordered text-center align-middle">';
    html += '<thead class="table-light"><tr><th>Day</th><th>Focus Subject</th><th>Hours</th><th>Activity</th></tr></thead><tbody>';

    const days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
    
    days.forEach((day, index) => {
        let focusSubject = "General Revision";
        let hours = 2;
        let activity = "Review Class Notes";

        if (weakSubjects.length > 0) {
            focusSubject = weakSubjects[index % weakSubjects.length];
            hours = 3; 
            activity = '<span class="text-danger fw-semibold"><i class="fa-solid fa-fire text-danger me-1"></i> Intensive Practice</span>';
            
            if (day === 'Sunday') {
                focusSubject = "Mock Test / Evaluation";
                hours = 4;
                activity = "Self-Assessment of " + weakSubjects.join(', ');
            }
        } else if (day === 'Sunday') {
            focusSubject = "Rest & Hobby";
            hours = 0;
            activity = "Relaxation";
        }

        html += `<tr>
            <td class="fw-bold">${day}</td>
            <td class="text-primary fw-semibold">${focusSubject}</td>
            <td>${hours > 0 ? hours + ' Hrs' : '-'}</td>
            <td>${activity}</td>
        </tr>`;
    });

    html += '</tbody></table></div>';
    html += '<p class="text-muted small mt-2"><i class="fa-solid fa-robot me-1"></i> Generated dynamically based on your latest performance metrics.</p>';
    
    container.innerHTML = html;
    
    // Animate display
    resultSection.classList.remove('d-none');
    resultSection.style.opacity = 0;
    setTimeout(() => {
        resultSection.style.transition = 'opacity 0.5s ease-in-out';
        resultSection.style.opacity = 1;
    }, 50);
}