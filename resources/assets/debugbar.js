/**
 * This file is part of the Phalcon Framework.
 *
 * (c) Phalcon Team <team@phalcon.io>
 *
 * For the full copyright and license information, please view the LICENSE.txt
 * file that was distributed with this source code.
 *
 * Phalcon DebugBar client. Reads the JSON payload injected by the PHP renderer
 * and builds the bottom bar: one tab per collector, a panel per tab. It is
 * self-describing where possible (meta.widgets) and infers the panel type from
 * the data shape otherwise. The bar collapses to a bottom-right handle so it
 * never permanently covers the host page's own controls.
 */
(function () {
    'use strict';

    var STORAGE_KEY = 'phalcon-debugbar-collapsed';

    function ready(fn) {
        if (document.readyState !== 'loading') {
            fn();
        } else {
            document.addEventListener('DOMContentLoaded', fn);
        }
    }

    function el(tag, className, text) {
        var node = document.createElement(tag);
        if (className) {
            node.className = className;
        }
        if (text !== undefined && text !== null) {
            node.textContent = text;
        }
        return node;
    }

    function titleize(name) {
        return String(name)
            .replace(/[-_]+/g, ' ')
            .replace(/\b\w/g, function (c) {
                return c.toUpperCase();
            });
    }

    function inferType(panel) {
        if (typeof panel === 'string') {
            return 'html';
        }
        if (Array.isArray(panel)) {
            return 'list';
        }
        if (panel && typeof panel === 'object') {
            return ('source' in panel) ? 'code' : 'grid';
        }
        return 'grid';
    }

    function scalar(value) {
        if (value === null || value === undefined) {
            return '';
        }
        return String(value);
    }

    function renderGrid(panel) {
        var keys = panel && typeof panel === 'object' ? Object.keys(panel) : [];
        if (!keys.length) {
            return el('div', 'phalcon-debugbar-empty', 'No data');
        }
        var table = el('table', 'phalcon-debugbar-grid');
        keys.forEach(function (key) {
            var tr = el('tr');
            tr.appendChild(el('th', null, key));
            tr.appendChild(el('td', null, scalar(panel[key])));
            table.appendChild(tr);
        });
        return table;
    }

    function renderList(panel) {
        if (!Array.isArray(panel) || !panel.length) {
            return el('div', 'phalcon-debugbar-empty', 'No data');
        }
        var table = el('table', 'phalcon-debugbar-list');
        panel.forEach(function (row) {
            row = row || {};
            var tr = el('tr');
            tr.appendChild(el('td', 'phalcon-debugbar-key', scalar(row.label)));
            tr.appendChild(el('td', 'phalcon-debugbar-value', scalar(row.message)));
            table.appendChild(tr);
        });
        return table;
    }

    function renderCode(panel) {
        var source = (panel && panel.source !== undefined) ? panel.source : '';
        return el('pre', 'phalcon-debugbar-code', scalar(source));
    }

    function renderHtml(panel) {
        var node = el('div', 'phalcon-debugbar-html');
        node.innerHTML = typeof panel === 'string' ? panel : '';
        return node;
    }

    function renderPanel(type, panel) {
        switch (type) {
            case 'list':
                return renderList(panel);
            case 'code':
                return renderCode(panel);
            case 'html':
                return renderHtml(panel);
            case 'grid':
            default:
                return renderGrid(panel);
        }
    }

    function hasBadge(badge) {
        return badge !== null && badge !== undefined && badge !== '' && badge !== 0;
    }

    function readCollapsed() {
        try {
            return window.localStorage.getItem(STORAGE_KEY) === '1';
        } catch (error) {
            return false;
        }
    }

    function writeCollapsed(collapsed) {
        try {
            window.localStorage.setItem(STORAGE_KEY, collapsed ? '1' : '0');
        } catch (error) {
            /* storage unavailable — collapse still works for the session */
        }
    }

    ready(function () {
        var dataNode = document.getElementById('phalcon-debugbar-data');
        var mount = document.getElementById('phalcon-debugbar');
        if (!dataNode || !mount) {
            return;
        }

        var payload;
        try {
            payload = JSON.parse(dataNode.textContent);
        } catch (error) {
            return;
        }

        var data = payload.data || {};
        var widgets = (payload.meta && payload.meta.widgets) || {};

        var bar = el('div', 'phalcon-debugbar-bar');
        var body = el('div', 'phalcon-debugbar-body');
        var row = el('div', 'phalcon-debugbar-row');
        var tabs = el('div', 'phalcon-debugbar-tabs');
        var toggle = el('button', 'phalcon-debugbar-toggle', '⚡');
        toggle.type = 'button';
        toggle.title = 'Toggle Phalcon DebugBar';
        body.style.display = 'none';

        var active = null;

        function closePanel() {
            body.style.display = 'none';
            active = null;
            Array.prototype.forEach.call(tabs.children, function (child) {
                child.classList.remove('is-active');
            });
        }

        function setCollapsed(collapsed) {
            if (collapsed) {
                closePanel();
                mount.classList.add('is-collapsed');
            } else {
                mount.classList.remove('is-collapsed');
            }
        }

        toggle.addEventListener('click', function () {
            var collapsed = !mount.classList.contains('is-collapsed');
            setCollapsed(collapsed);
            writeCollapsed(collapsed);
        });

        Object.keys(data).forEach(function (name) {
            var entry = data[name] || {};
            var widget = widgets[name] || {};
            var label = widget.label || titleize(name);
            var type = widget.panel || inferType(entry.panel);

            var tab = el('button', 'phalcon-debugbar-tab');
            tab.type = 'button';
            tab.appendChild(el('span', 'phalcon-debugbar-tab-label', label));
            if (hasBadge(entry.badge)) {
                tab.appendChild(el('span', 'phalcon-debugbar-badge', scalar(entry.badge)));
            }

            tab.addEventListener('click', function () {
                if (active === name) {
                    closePanel();
                    return;
                }
                closePanel();
                tab.classList.add('is-active');
                body.innerHTML = '';
                body.appendChild(renderPanel(type, entry.panel));
                body.style.display = 'block';
                active = name;
            });

            tabs.appendChild(tab);
        });

        row.appendChild(tabs);
        row.appendChild(toggle);
        bar.appendChild(body);
        bar.appendChild(row);
        mount.appendChild(bar);

        setCollapsed(readCollapsed());
    });
})();
