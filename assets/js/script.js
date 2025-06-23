/**
 * Link Rotator System - Frontend Scripts
 * 
 * Fitur:
 * - Validasi form
 * - Interaksi admin panel
 * - Tampilan statistik
 * - Fitur tambahan untuk UI
 */

document.addEventListener('DOMContentLoaded', function() {
    // Fungsi umum untuk semua halaman
    initGeneralFeatures();
    
    // Fungsi khusus halaman
    if (document.querySelector('.admin-links')) {
        initLinksPage();
    }
    
    if (document.querySelector('.admin-stats')) {
        initStatsPage();
    }
});

/**
 * Fungsi umum untuk semua halaman
 */
function initGeneralFeatures() {
    // Tooltip untuk elemen dengan atribut data-tooltip
    const tooltipElements = document.querySelectorAll('[data-tooltip]');
    tooltipElements.forEach(el => {
        el.addEventListener('mouseenter', showTooltip);
        el.addEventListener('mouseleave', hideTooltip);
    });
    
    // Toggle password visibility
    const togglePasswordButtons = document.querySelectorAll('.toggle-password');
    togglePasswordButtons.forEach(btn => {
        btn.addEventListener('click', function() {
            const input = this.previousElementSibling;
            const type = input.getAttribute('type') === 'password' ? 'text' : 'password';
            input.setAttribute('type', type);
            this.textContent = type === 'password' ? '👁️' : '👁️‍🗨️';
        });
    });
    
    // Confirm sebelum hapus
    const deleteForms = document.querySelectorAll('form[data-confirm]');
    deleteForms.forEach(form => {
        form.addEventListener('submit', function(e) {
            if (!confirm(this.getAttribute('data-confirm'))) {
                e.preventDefault();
            }
        });
    });
}

/**
 * Fungsi untuk halaman manajemen link
 */
function initLinksPage() {
    // Toggle advanced options
    const toggleAdvancedBtn = document.getElementById('toggle-advanced');
    if (toggleAdvancedBtn) {
        toggleAdvancedBtn.addEventListener('click', function() {
            const advancedSection = document.getElementById('advanced-options');
            advancedSection.classList.toggle('hidden');
            this.textContent = advancedSection.classList.contains('hidden') ? 
                'Show Advanced Options' : 'Hide Advanced Options';
        });
    }
    
    // Validasi form tambah/edit link
    const linkForm = document.getElementById('link-form');
    if (linkForm) {
        linkForm.addEventListener('submit', function(e) {
            const targets = document.getElementById('targets').value.trim();
            if (!targets) {
                e.preventDefault();
                alert('Please enter at least one target URL');
                return false;
            }
            
            // Validasi URL
            const urls = targets.split('\n').filter(url => url.trim());
            for (let url of urls) {
                if (!isValidUrl(url)) {
                    e.preventDefault();
                    alert(`Invalid URL: ${url}\nPlease enter valid URLs starting with http:// or https://`);
                    return false;
                }
            }
            
            return true;
        });
    }
    
    // Copy short URL
    const copyButtons = document.querySelectorAll('.copy-short-url');
    copyButtons.forEach(btn => {
        btn.addEventListener('click', function() {
            const shortUrl = this.getAttribute('data-url');
            navigator.clipboard.writeText(shortUrl).then(() => {
                const originalText = this.textContent;
                this.textContent = 'Copied!';
                setTimeout(() => {
                    this.textContent = originalText;
                }, 2000);
            });
        });
    });
    
    // Quick preview target URLs
    const previewButtons = document.querySelectorAll('.preview-targets');
    previewButtons.forEach(btn => {
        btn.addEventListener('click', function() {
            const targets = JSON.parse(this.getAttribute('data-targets'));
            let previewText = targets.join('\n');
            alert('Target URLs:\n\n' + previewText);
        });
    });
}

/**
 * Fungsi untuk halaman statistik
 */
function initStatsPage() {
    // Filter date range
    const dateRangeFilter = document.getElementById('date-range-filter');
    if (dateRangeFilter) {
        dateRangeFilter.addEventListener('change', function() {
            const value = this.value;
            let fromDate, toDate;
            
            switch(value) {
                case 'today':
                    fromDate = toDate = new Date().toISOString().split('T')[0];
                    break;
                case 'yesterday':
                    const yesterday = new Date();
                    yesterday.setDate(yesterday.getDate() - 1);
                    fromDate = toDate = yesterday.toISOString().split('T')[0];
                    break;
                case 'week':
                    const today = new Date();
                    const weekStart = new Date(today);
                    weekStart.setDate(today.getDate() - today.getDay());
                    fromDate = weekStart.toISOString().split('T')[0];
                    toDate = today.toISOString().split('T')[0];
                    break;
                case 'month':
                    const now = new Date();
                    fromDate = new Date(now.getFullYear(), now.getMonth(), 1).toISOString().split('T')[0];
                    toDate = now.toISOString().split('T')[0];
                    break;
                default:
                    // All time - no filter
                    fromDate = toDate = null;
            }
            
            // Apply filter (ini akan memerlukan implementasi server-side)
            // Dalam implementasi nyata, ini akan mengirim permintaan ke server
            // atau memfilter data yang sudah dimuat di client
            console.log(`Filter date range: ${fromDate} to ${toDate}`);
        });
    }
    
    // Export data
    const exportButtons = document.querySelectorAll('.export-data');
    exportButtons.forEach(btn => {
        btn.addEventListener('click', function() {
            const format = this.getAttribute('data-format');
            const hash = document.getElementById('hash').value;
            
            // Dalam implementasi nyata, ini akan mengarahkan ke endpoint export
            console.log(`Exporting data for ${hash} in ${format} format`);
            alert(`Export feature would download ${format} file in a real implementation`);
        });
    });
}

/**
 * Fungsi bantuan (helper functions)
 */

// Validasi URL
function isValidUrl(string) {
    try {
        new URL(string);
        return true;
    } catch (_) {
        return false;
    }
}

// Tooltip
function showTooltip(e) {
    const tooltipText = this.getAttribute('data-tooltip');
    const tooltip = document.createElement('div');
    tooltip.className = 'tooltip';
    tooltip.textContent = tooltipText;
    
    document.body.appendChild(tooltip);
    
    const rect = this.getBoundingClientRect();
    tooltip.style.top = `${rect.top - tooltip.offsetHeight - 10}px`;
    tooltip.style.left = `${rect.left + rect.width / 2 - tooltip.offsetWidth / 2}px`;
    
    this.tooltip = tooltip;
}

function hideTooltip() {
    if (this.tooltip) {
        this.tooltip.remove();
        this.tooltip = null;
    }
}

// AJAX helper
function makeRequest(method, url, data, callback) {
    const xhr = new XMLHttpRequest();
    xhr.open(method, url, true);
    xhr.setRequestHeader('Content-Type', 'application/json');
    
    xhr.onload = function() {
        if (xhr.status >= 200 && xhr.status < 300) {
            callback(null, JSON.parse(xhr.responseText));
        } else {
            callback(new Error(xhr.statusText));
        }
    };
    
    xhr.onerror = function() {
        callback(new Error('Network error'));
    };
    
    xhr.send(JSON.stringify(data));
}

// Format angka
function formatNumber(num) {
    return num.toString().replace(/(\d)(?=(\d{3})+(?!\d))/g, '$1,');
}