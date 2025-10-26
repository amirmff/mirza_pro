// Main JavaScript for Mirza Pro Admin Panel

// API Helper
class API {
    static async request(url, options = {}) {
        try {
            const response = await fetch(url, {
                ...options,
                headers: {
                    'Content-Type': 'application/json',
                    ...options.headers
                }
            });
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            return await response.json();
        } catch (error) {
            console.error('API Request failed:', error);
            showNotification('خطا در ارتباط با سرور', 'error');
            throw error;
        }
    }
    
    static get(url) {
        return this.request(url, { method: 'GET' });
    }
    
    static post(url, data) {
        return this.request(url, {
            method: 'POST',
            body: JSON.stringify(data)
        });
    }
    
    static put(url, data) {
        return this.request(url, {
            method: 'PUT',
            body: JSON.stringify(data)
        });
    }
    
    static delete(url) {
        return this.request(url, { method: 'DELETE' });
    }
}

// Notification System
function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.innerHTML = `
        <div class="notification-content">
            <span class="notification-icon">${getNotificationIcon(type)}</span>
            <span class="notification-message">${message}</span>
        </div>
    `;
    
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.classList.add('show');
    }, 100);
    
    setTimeout(() => {
        notification.classList.remove('show');
        setTimeout(() => notification.remove(), 300);
    }, 3000);
}

function getNotificationIcon(type) {
    const icons = {
        'success': '✓',
        'error': '✗',
        'warning': '⚠',
        'info': 'ℹ'
    };
    return icons[type] || icons.info;
}

// Modal System
function showModal(title, content, buttons = []) {
    const modal = document.createElement('div');
    modal.className = 'modal-overlay';
    modal.innerHTML = `
        <div class="modal-content">
            <div class="modal-header">
                <h3>${title}</h3>
                <button class="modal-close" onclick="this.closest('.modal-overlay').remove()">×</button>
            </div>
            <div class="modal-body">${content}</div>
            <div class="modal-footer">
                ${buttons.map(btn => `
                    <button class="btn btn-${btn.type || 'primary'}" 
                            onclick="${btn.onclick}">
                        ${btn.text}
                    </button>
                `).join('')}
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
    
    // Close on overlay click
    modal.addEventListener('click', (e) => {
        if (e.target === modal) {
            modal.remove();
        }
    });
}

// Confirm Dialog
function confirm(message, onConfirm) {
    showModal('تایید', message, [
        {
            text: 'لغو',
            type: 'secondary',
            onclick: 'this.closest(\'.modal-overlay\').remove()'
        },
        {
            text: 'تایید',
            type: 'danger',
            onclick: `(${onConfirm.toString()})(); this.closest('.modal-overlay').remove()`
        }
    ]);
}

// Data Table Helper
class DataTable {
    constructor(container, options = {}) {
        this.container = container;
        this.options = {
            page: 1,
            limit: 20,
            search: '',
            filter: 'all',
            ...options
        };
        this.data = [];
    }
    
    async load() {
        const url = new URL(this.options.endpoint, window.location.origin);
        url.searchParams.append('page', this.options.page);
        url.searchParams.append('limit', this.options.limit);
        url.searchParams.append('search', this.options.search);
        url.searchParams.append('filter', this.options.filter);
        
        try {
            const response = await API.get(url.toString());
            this.data = response.data || [];
            this.render();
            this.renderPagination(response.pages || 1);
        } catch (error) {
            console.error('Failed to load data:', error);
        }
    }
    
    render() {
        if (!this.options.columns) {
            console.error('No columns defined');
            return;
        }
        
        const table = document.createElement('table');
        table.innerHTML = `
            <thead>
                <tr>
                    ${this.options.columns.map(col => `<th>${col.header}</th>`).join('')}
                </tr>
            </thead>
            <tbody>
                ${this.data.map(row => `
                    <tr>
                        ${this.options.columns.map(col => `
                            <td>${col.render ? col.render(row) : row[col.field]}</td>
                        `).join('')}
                    </tr>
                `).join('')}
            </tbody>
        `;
        
        this.container.innerHTML = '';
        this.container.appendChild(table);
    }
    
    renderPagination(totalPages) {
        if (totalPages <= 1) return;
        
        const pagination = document.createElement('div');
        pagination.className = 'pagination';
        
        for (let i = 1; i <= totalPages; i++) {
            const button = document.createElement('button');
            button.textContent = i;
            button.className = i === this.options.page ? 'active' : '';
            button.onclick = () => {
                this.options.page = i;
                this.load();
            };
            pagination.appendChild(button);
        }
        
        this.container.parentElement.appendChild(pagination);
    }
}

// Format numbers
function formatNumber(number) {
    return new Intl.NumberFormat('fa-IR').format(number);
}

// Format date
function formatDate(timestamp) {
    const date = new Date(timestamp * 1000);
    return new Intl.DateTimeFormat('fa-IR', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit'
    }).format(date);
}

// Copy to clipboard
function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(() => {
        showNotification('کپی شد!', 'success');
    }).catch(err => {
        showNotification('خطا در کپی', 'error');
    });
}

// Initialize tooltips
function initTooltips() {
    const tooltips = document.querySelectorAll('[data-tooltip]');
    tooltips.forEach(el => {
        el.addEventListener('mouseenter', (e) => {
            const tooltip = document.createElement('div');
            tooltip.className = 'tooltip';
            tooltip.textContent = el.getAttribute('data-tooltip');
            document.body.appendChild(tooltip);
            
            const rect = el.getBoundingClientRect();
            tooltip.style.top = rect.top - tooltip.offsetHeight - 5 + 'px';
            tooltip.style.left = rect.left + (rect.width / 2) - (tooltip.offsetWidth / 2) + 'px';
            
            el._tooltip = tooltip;
        });
        
        el.addEventListener('mouseleave', (e) => {
            if (el._tooltip) {
                el._tooltip.remove();
                el._tooltip = null;
            }
        });
    });
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    initTooltips();
    
    // Add smooth scrolling
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({ behavior: 'smooth' });
            }
        });
    });
});

// Export for use in other files
window.API = API;
window.showNotification = showNotification;
window.showModal = showModal;
window.confirmDialog = confirm;
window.DataTable = DataTable;
window.formatNumber = formatNumber;
window.formatDate = formatDate;
window.copyToClipboard = copyToClipboard;
