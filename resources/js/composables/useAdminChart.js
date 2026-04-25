import Chart from 'chart.js/auto';

export const ADMIN_CHART_BLUE = '#E8553E';
export const ADMIN_CHART_GREEN = '#16A34A';

let defaultsApplied = false;

const isObject = (value) => Object.prototype.toString.call(value) === '[object Object]';

const cloneValue = (value) => {
    if (Array.isArray(value)) {
        return value.map((entry) => cloneValue(entry));
    }

    if (isObject(value)) {
        return Object.keys(value).reduce((carry, key) => {
            carry[key] = cloneValue(value[key]);
            return carry;
        }, {});
    }

    return value;
};

const ensureTooltipElement = (chart) => {
    if (typeof document === 'undefined' || !chart?.canvas?.parentNode) {
        return null;
    }

    const parent = chart.canvas.parentNode;
    const existing = parent.querySelector('.admin-chart-tooltip');

    if (existing) {
        return existing;
    }

    const tooltipElement = document.createElement('div');
    tooltipElement.className = 'admin-chart-tooltip';
    tooltipElement.style.position = 'absolute';
    tooltipElement.style.pointerEvents = 'none';
    tooltipElement.style.opacity = '0';
    tooltipElement.style.transform = 'translate(-50%, calc(-100% - 4px)) scale(0.9)';
    tooltipElement.style.transformOrigin = 'center bottom';
    tooltipElement.style.transition = 'opacity 180ms ease, transform 220ms cubic-bezier(0.16, 1, 0.3, 1)';
    tooltipElement.style.background = '#2D2D2D';
    tooltipElement.style.color = '#F0E8E0';
    tooltipElement.style.border = '1px solid rgba(255,255,255,0.1)';
    tooltipElement.style.borderRadius = '8px';
    tooltipElement.style.padding = '10px 12px';
    tooltipElement.style.boxShadow = '0 14px 32px rgba(15,23,42,0.28)';
    tooltipElement.style.zIndex = '20';
    tooltipElement.style.whiteSpace = 'nowrap';
    tooltipElement.style.fontFamily = "'Nunito', sans-serif";

    if (typeof window !== 'undefined' && window.getComputedStyle(parent).position === 'static') {
        parent.style.position = 'relative';
    }

    parent.appendChild(tooltipElement);
    return tooltipElement;
};

const renderSpringTooltip = (context) => {
    const { chart, tooltip } = context;
    const tooltipElement = ensureTooltipElement(chart);

    if (!tooltipElement) {
        return;
    }

    if (!tooltip || tooltip.opacity === 0) {
        tooltipElement.style.opacity = '0';
        tooltipElement.style.transform = 'translate(-50%, calc(-100% - 4px)) scale(0.9)';
        return;
    }

    const title = tooltip.title || [];
    const body = tooltip.body || [];

    const titleMarkup = title.length
        ? `<div style="font-size:13px;font-weight:600;color:#FFF8F0;margin-bottom:6px;">${title.join('<br/>')}</div>`
        : '';

    const bodyMarkup = body.map((item, index) => {
        const color = tooltip.labelColors?.[index]?.borderColor || '#F0E8E0';
        const lines = item.lines || [];

        return `<div style="display:flex;align-items:center;gap:8px;font-size:12px;line-height:1.4;">
            <span style="width:8px;height:8px;border-radius:999px;background:${color};display:inline-block;"></span>
            <span>${lines.join('<br/>')}</span>
        </div>`;
    }).join('');

    tooltipElement.innerHTML = `${titleMarkup}${bodyMarkup}`;
    tooltipElement.style.left = `${tooltip.caretX}px`;
    tooltipElement.style.top = `${tooltip.caretY}px`;
    tooltipElement.style.opacity = '1';
    tooltipElement.style.transform = 'translate(-50%, calc(-100% - 8px)) scale(1)';
};

const deepMerge = (target, source) => {
    if (!isObject(target) || !isObject(source)) {
        return source;
    }

    const merged = { ...target };

    Object.keys(source).forEach((key) => {
        const sourceValue = source[key];
        const targetValue = merged[key];

        if (isObject(sourceValue) && isObject(targetValue)) {
            merged[key] = deepMerge(targetValue, sourceValue);
            return;
        }

        merged[key] = sourceValue;
    });

    return merged;
};

const defaultOptions = {
    responsive: true,
    maintainAspectRatio: false,
    animation: {
        duration: 600,
        easing: 'easeOutQuart',
    },
    plugins: {
        legend: { display: false },
        tooltip: {
            enabled: false,
            external: renderSpringTooltip,
            backgroundColor: '#2D2D2D',
            titleColor: '#FFF8F0',
            bodyColor: '#F0E8E0',
            padding: 12,
            cornerRadius: 8,
            titleFont: { weight: '600', size: 13 },
            bodyFont: { size: 12 },
            borderColor: 'rgba(255,255,255,0.1)',
            borderWidth: 1,
        },
    },
    scales: {
        x: {
            grid: { display: false },
            border: { display: false },
            ticks: { color: '#9CA3AF', font: { size: 11 } },
        },
        y: {
            grid: { color: '#F1F5F9', drawBorder: false },
            border: { display: false, dash: [4, 4] },
            ticks: { color: '#9CA3AF', font: { size: 11 } },
        },
    },
};

const applyDefaults = () => {
    if (defaultsApplied) {
        return;
    }

    Chart.defaults.font.family = "'Nunito', sans-serif";
    Chart.defaults.font.size = 12;
    Chart.defaults.color = '#9CA3AF';
    defaultsApplied = true;
};

const toCanvasElement = (target) => {
    if (!target) {
        return null;
    }

    return target.value ?? target;
};

export function useAdminChart() {
    applyDefaults();

    const createChart = (target, config) => {
        const canvas = toCanvasElement(target);

        if (!canvas) {
            return null;
        }

        const mergedOptions = deepMerge(cloneValue(defaultOptions), config.options || {});

        return new Chart(canvas, {
            ...config,
            options: mergedOptions,
        });
    };

    const createLineDataset = (label, data, color = ADMIN_CHART_BLUE, overrides = {}) => ({
        label,
        data,
        borderColor: color,
        backgroundColor: color,
        fill: false,
        tension: 0.4,
        pointRadius: 3,
        pointHoverRadius: 6,
        ...overrides,
    });

    const createBarDataset = (label, data, color = ADMIN_CHART_BLUE, overrides = {}) => ({
        label,
        data,
        backgroundColor: color,
        borderRadius: 4,
        barThickness: 24,
        maxBarThickness: 24,
        ...overrides,
    });

    const destroyChart = (chart) => {
        const tooltipElement = chart?.canvas?.parentNode?.querySelector?.('.admin-chart-tooltip');
        tooltipElement?.remove();
        chart?.destroy();
    };

    const replayChart = async (renderer, destroyer, delay = 80) => {
        if (typeof destroyer === 'function') {
            destroyer();
        }

        await new Promise((resolve) => setTimeout(resolve, delay));

        if (typeof renderer === 'function') {
            return renderer();
        }

        return null;
    };

    return {
        createChart,
        createLineDataset,
        createBarDataset,
        destroyChart,
        replayChart,
        defaultOptions,
        colors: {
            blue: ADMIN_CHART_BLUE,
            green: ADMIN_CHART_GREEN,
        },
    };
}
