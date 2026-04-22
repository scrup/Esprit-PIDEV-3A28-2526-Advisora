document.addEventListener('DOMContentLoaded', () => {
    if (typeof Chart === 'undefined') {
        return;
    }

    const palette = {
        terracotta: '#c37d5d',
        terracottaDark: '#8d563d',
        terracottaSoft: '#ead8ca',
        sage: '#8faa7e',
        gold: '#d3b179',
        ink: '#2c2418',
        muted: '#6b5a48'
    };

    const cards = document.querySelectorAll('[data-project-chart]');
    cards.forEach((card) => {
        const canvas = card.querySelector('canvas');
        const emptyState = card.querySelector('[data-project-chart-empty]');

        if (!canvas) {
            return;
        }

        let dataset = {};
        try {
            dataset = JSON.parse(card.dataset.chart || '{}');
        } catch (error) {
            canvas.remove();
            if (emptyState) {
                emptyState.hidden = false;
            }

            return;
        }

        const labels = Array.isArray(dataset.labels) ? dataset.labels : [];
        const values = Array.isArray(dataset.values) ? dataset.values.map((value) => Number(value) || 0) : [];
        const hasValues = values.some((value) => value > 0);

        if (labels.length === 0 || !hasValues) {
            canvas.remove();
            if (emptyState) {
                emptyState.hidden = false;
            }

            return;
        }

        const kind = card.dataset.chartKind || 'bar';
        const config = buildChartConfig(kind, labels, values, palette);

        new Chart(canvas, config);
    });
});

function buildChartConfig(kind, labels, values, palette) {
    const sharedOptions = {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: kind === 'doughnut',
                position: 'bottom',
                labels: {
                    color: palette.ink,
                    boxWidth: 12,
                    padding: 18
                }
            },
            tooltip: {
                callbacks: {
                    label: (context) => {
                        const value = context.parsed.x ?? context.parsed.y ?? context.parsed;
                        return `${context.label}: ${new Intl.NumberFormat('fr-FR').format(value)}`;
                    }
                }
            }
        },
        scales: kind === 'doughnut' ? {} : {
            x: {
                beginAtZero: true,
                grid: {
                    color: 'rgba(107, 90, 72, 0.12)'
                },
                ticks: {
                    color: palette.muted
                }
            },
            y: {
                grid: {
                    display: false
                },
                ticks: {
                    color: palette.muted
                }
            }
        }
    };

    if (kind === 'doughnut') {
        return {
            type: 'doughnut',
            data: {
                labels,
                datasets: [{
                    data: values,
                    backgroundColor: [palette.gold, palette.sage, palette.terracotta],
                    borderColor: '#fffdfa',
                    borderWidth: 3,
                    hoverOffset: 6
                }]
            },
            options: sharedOptions
        };
    }

    if (kind === 'line') {
        return {
            type: 'line',
            data: {
                labels,
                datasets: [{
                    data: values,
                    borderColor: palette.terracotta,
                    backgroundColor: 'rgba(195, 125, 93, 0.14)',
                    fill: true,
                    tension: 0.35,
                    borderWidth: 3,
                    pointBackgroundColor: palette.terracottaDark,
                    pointRadius: 4
                }]
            },
            options: sharedOptions
        };
    }

    if (kind === 'bar-horizontal') {
        return {
            type: 'bar',
            data: {
                labels,
                datasets: [{
                    data: values,
                    backgroundColor: [palette.gold, palette.sage, palette.terracotta],
                    borderRadius: 10,
                    borderSkipped: false
                }]
            },
            options: {
                ...sharedOptions,
                indexAxis: 'y',
                plugins: {
                    ...sharedOptions.plugins,
                    legend: {
                        display: false
                    }
                }
            }
        };
    }

    return {
        type: 'bar',
        data: {
            labels,
            datasets: [{
                data: values,
                backgroundColor: labels.map((_, index) => {
                    const colors = [palette.terracotta, palette.sage, palette.gold, palette.terracottaDark];
                    return colors[index % colors.length];
                }),
                borderRadius: 12,
                borderSkipped: false
            }]
        },
        options: {
            ...sharedOptions,
            plugins: {
                ...sharedOptions.plugins,
                legend: {
                    display: false
                }
            }
        }
    };
}
