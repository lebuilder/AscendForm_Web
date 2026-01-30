// AscendForm - JavaScript pour views_stats.php

function initCharts(registrationsByDay, loginsByDay) {
    // Labels (dates) et valeurs pour inscriptions
    const regLabels = Object.keys(registrationsByDay);
    const regCounts = Object.values(registrationsByDay);
    
    // Labels (dates) et valeurs pour connexions
    const loginLabels = Object.keys(loginsByDay);
    const loginCounts = Object.values(loginsByDay);
    
    // Graphique inscriptions (ligne)
    new Chart(document.getElementById('registrationsChart'), {
        type: 'line',
        data: {
            labels: regLabels.map(d => {
                const date = new Date(d);
                return date.toLocaleDateString('fr-FR', { day: '2-digit', month: 'short' });
            }),
            datasets: [{
                label: 'Inscriptions',
                data: regCounts,
                borderColor: 'rgba(102, 126, 234, 1)',
                backgroundColor: 'rgba(102, 126, 234, 0.2)',
                tension: 0.4,
                fill: true,
                pointRadius: 4,
                pointHoverRadius: 6,
                pointBackgroundColor: 'rgba(102, 126, 234, 1)',
                pointBorderColor: '#fff',
                pointBorderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { 
                    labels: { 
                        color: 'white',
                        font: { size: 14 }
                    }
                },
                tooltip: {
                    backgroundColor: 'rgba(0, 0, 0, 0.8)',
                    titleColor: 'white',
                    bodyColor: 'white',
                    borderColor: 'rgba(102, 126, 234, 1)',
                    borderWidth: 1
                }
            },
            scales: {
                x: { 
                    ticks: { 
                        color: 'white',
                        maxRotation: 45,
                        minRotation: 45,
                        font: { size: 14 }
                    },
                    grid: { color: 'rgba(255,255,255,0.1)' }
                },
                y: { 
                    ticks: { 
                        color: 'white',
                        stepSize: 1,
                        beginAtZero: true,
                        font: { size: 14 }
                    },
                    grid: { color: 'rgba(255,255,255,0.1)' }
                }
            }
        }
    });
    
    // Graphique connexions (barres)
    new Chart(document.getElementById('loginsChart'), {
        type: 'bar',
        data: {
            labels: loginLabels.map(d => {
                const date = new Date(d);
                return date.toLocaleDateString('fr-FR', { day: '2-digit', month: 'short' });
            }),
            datasets: [{
                label: 'Connexions',
                data: loginCounts,
                backgroundColor: 'rgba(118, 75, 162, 0.7)',
                borderColor: 'rgba(118, 75, 162, 1)',
                borderWidth: 2,
                borderRadius: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { 
                    labels: { 
                        color: 'white',
                        font: { size: 14 }
                    }
                },
                tooltip: {
                    backgroundColor: 'rgba(0, 0, 0, 0.8)',
                    titleColor: 'white',
                    bodyColor: 'white',
                    borderColor: 'rgba(118, 75, 162, 1)',
                    borderWidth: 1
                }
            },
            scales: {
                x: { 
                    ticks: { 
                        color: 'white',
                        maxRotation: 45,
                        minRotation: 45,
                        font: { size: 14 }
                    },
                    grid: { color: 'rgba(255,255,255,0.1)' }
                },
                y: { 
                    ticks: { 
                        color: 'white',
                        stepSize: 1,
                        beginAtZero: true,
                        font: { size: 14 }
                    },
                    grid: { color: 'rgba(255,255,255,0.1)' }
                }
            }
        }
    });
}
