/**
 * This file is part of the Phalcon Framework.
 *
 * (c) Phalcon Team <team@phalcon.io>
 *
 * For the full copyright and license information, please view the LICENSE.txt
 * file that was distributed with this source code.
 *
 * Phalcon DebugBar client. Reads the JSON payload injected by the PHP renderer
 * and builds the bottom bar: interactive collector tabs on the left and compact
 * request metrics on the right. It is self-describing where possible
 * (meta.widgets) and infers the panel type from the data shape otherwise. The
 * bar collapses to a bottom-right handle so it never permanently covers the
 * host page's own controls.
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

    function svgElement(tag, attributes) {
        var node = document.createElementNS('http://www.w3.org/2000/svg', tag);
        Object.keys(attributes).forEach(function (name) {
            node.setAttribute(name, attributes[name]);
        });
        return node;
    }

    function indicatorIcon(name) {
        var svg = svgElement('svg', {
            'aria-hidden': 'true',
            'class': 'phalcon-debugbar-indicator-icon',
            'viewBox': '0 0 18 18'
        });

        if (name === 'clock') {
            svg.appendChild(svgElement('circle', {cx: '9', cy: '9', r: '6.5'}));
            svg.appendChild(svgElement('path', {d: 'M9 5.2V9l2.8 1.7'}));
            return svg;
        }

        if (name === 'search') {
            svg.appendChild(svgElement('circle', {cx: '7.5', cy: '7.5', r: '4.5'}));
            svg.appendChild(svgElement('path', {d: 'M10.8 10.8l4 4'}));
            return svg;
        }

        function gear(cx, cy, radius) {
            var group = svgElement('g', {});
            group.appendChild(svgElement('circle', {cx: cx, cy: cy, r: radius}));
            group.appendChild(svgElement('circle', {cx: cx, cy: cy, r: radius / 3}));
            for (var index = 0; index < 8; index++) {
                var angle = index * Math.PI / 4;
                group.appendChild(svgElement('line', {
                    x1: cx + Math.cos(angle) * radius,
                    y1: cy + Math.sin(angle) * radius,
                    x2: cx + Math.cos(angle) * (radius + 1.5),
                    y2: cy + Math.sin(angle) * (radius + 1.5)
                }));
            }
            svg.appendChild(group);
        }

        gear(6.2, 10.5, 3.1);
        gear(12.2, 6.2, 2.4);
        return svg;
    }

    function metricIndicator(icon, label, value) {
        var indicator = el('span', 'phalcon-debugbar-indicator');
        indicator.title = label;
        indicator.appendChild(indicatorIcon(icon));
        indicator.appendChild(el('span', 'phalcon-debugbar-indicator-value', scalar(value)));
        return indicator;
    }

    function renderIndicators(mount, data, onHistoryToggle, historyOpen, requestMetadata) {
        mount.innerHTML = '';
        var historyTrigger = null;

        var time = data.time || {};
        if (hasBadge(time.badge)) {
            mount.appendChild(metricIndicator('clock', 'Request time', time.badge));
        }

        var memory = data.memory || {};
        var memoryPanel = memory.panel || {};
        var currentMemory = memoryPanel['Current usage'];
        if (hasBadge(currentMemory)) {
            mount.appendChild(metricIndicator('cogs', 'Current memory usage', currentMemory));
        }

        var request = data.request || {};
        var requestPanel = request.panel || {};
        var method = scalar(requestPanel.Method);
        var uri = scalar(requestPanel.URI);
        var historyPanel = (data.history && data.history.panel) || {};
        requestMetadata = requestMetadata || {};
        method = method || scalar(historyPanel.method) || scalar(requestMetadata.method);
        uri = uri || scalar(historyPanel.uri) || scalar(requestMetadata.uri);
        if (method || uri || onHistoryToggle) {
            var requestControl = el(
                onHistoryToggle ? 'button' : 'span',
                'phalcon-debugbar-indicator phalcon-debugbar-request-control'
            );
            var requestLabel = (method + ' ' + uri).trim() || 'History';
            requestControl.title = onHistoryToggle
                ? (historyOpen ? 'Close request history: ' : 'Open request history: ') + requestLabel
                : requestLabel;
            if (onHistoryToggle) {
                requestControl.appendChild(indicatorIcon('search'));
            }
            requestControl.appendChild(el('strong', 'phalcon-debugbar-request-method', method));
            requestControl.appendChild(el('span', 'phalcon-debugbar-request-uri', uri || 'History'));
            if (onHistoryToggle) {
                requestControl.type = 'button';
                requestControl.classList.add('is-history-trigger');
                requestControl.setAttribute('data-history-label', requestLabel);
                requestControl.classList.toggle('is-active', Boolean(historyOpen));
                requestControl.setAttribute('aria-expanded', historyOpen ? 'true' : 'false');
                requestControl.addEventListener('click', onHistoryToggle);
                historyTrigger = requestControl;
            }
            mount.appendChild(requestControl);
        }

        return historyTrigger;
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
            if (panel.length && panel[0] && typeof panel[0] === 'object') {
                if ('trace' in panel[0]) {
                    return 'exceptions';
                }
                if ('context' in panel[0]) {
                    return 'logs';
                }
            }
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
            var value = el('td', 'phalcon-debugbar-value');
            var occurrences = Number(row.occurrences);

            tr.appendChild(el('td', 'phalcon-debugbar-key', scalar(row.label)));
            value.appendChild(el('span', 'phalcon-debugbar-message', scalar(row.message)));
            if (occurrences > 1) {
                tr.classList.add('is-duplicate');

                value.appendChild(el(
                    'span',
                    'phalcon-debugbar-duplicate-count',
                    'Executed ' + occurrences + ' times'
                ));
            }
            tr.appendChild(value);
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

    function renderExceptions(panel) {
        if (!Array.isArray(panel) || !panel.length) {
            return el('div', 'phalcon-debugbar-empty', 'No data');
        }
        var wrap = el('div', 'phalcon-debugbar-exceptions');
        panel.forEach(function (row) {
            row = row || {};
            var details = el('details', 'phalcon-debugbar-exception');
            var summary = el('summary', 'phalcon-debugbar-exception-summary');
            summary.appendChild(el('span', 'phalcon-debugbar-exception-label', scalar(row.label)));
            summary.appendChild(el('span', 'phalcon-debugbar-exception-message', scalar(row.message)));
            details.appendChild(summary);
            details.appendChild(el('pre', 'phalcon-debugbar-exception-trace', scalar(row.trace)));
            wrap.appendChild(details);
        });
        return wrap;
    }

    function renderLogs(panel) {
        if (!Array.isArray(panel) || !panel.length) {
            return el('div', 'phalcon-debugbar-empty', 'No data');
        }
        var wrap = el('div', 'phalcon-debugbar-logs');
        panel.forEach(function (row) {
            row = row || {};
            var context = scalar(row.context);
            if (context === '') {
                var line = el('div', 'phalcon-debugbar-log');
                line.appendChild(el('span', 'phalcon-debugbar-log-label', scalar(row.label)));
                line.appendChild(el('span', 'phalcon-debugbar-log-message', scalar(row.message)));
                wrap.appendChild(line);
                return;
            }
            var details = el('details', 'phalcon-debugbar-log');
            var summary = el('summary', 'phalcon-debugbar-log-summary');
            summary.appendChild(el('span', 'phalcon-debugbar-log-label', scalar(row.label)));
            summary.appendChild(el('span', 'phalcon-debugbar-log-message', scalar(row.message)));
            details.appendChild(summary);
            details.appendChild(el('pre', 'phalcon-debugbar-log-context', context));
            wrap.appendChild(details);
        });
        return wrap;
    }

    function renderPanel(type, panel) {
        switch (type) {
            case 'list':
                return renderList(panel);
            case 'code':
                return renderCode(panel);
            case 'html':
                return renderHtml(panel);
            case 'exceptions':
                return renderExceptions(panel);
            case 'logs':
                return renderLogs(panel);
            case 'grid':
            default:
                return renderGrid(panel);
        }
    }

    function renderSummary(summary) {
        if (!Array.isArray(summary) || !summary.length) {
            return null;
        }

        var wrap = el('div', 'phalcon-debugbar-summary');
        summary.forEach(function (item) {
            item = item || {};
            var metric = el('div', 'phalcon-debugbar-summary-metric');
            metric.appendChild(el('span', 'phalcon-debugbar-summary-label', titleize(item.label)));
            metric.appendChild(el('strong', 'phalcon-debugbar-summary-value', scalar(item.value)));
            wrap.appendChild(metric);
        });

        return wrap;
    }

    function hasBadge(badge) {
        return badge !== null && badge !== undefined && badge !== '' && badge !== 0;
    }

    function historyUrl(url, id) {
        if (!id) {
            return url;
        }

        return url + (url.indexOf('?') === -1 ? '?' : '&') + 'id=' + encodeURIComponent(id);
    }

    function requestJson(url, options) {
        options = options || {};
        options.credentials = 'same-origin';
        options.headers = options.headers || {};
        options.headers.Accept = 'application/json';

        return window.fetch(url, options).then(function (response) {
            if (!response.ok) {
                throw new Error('HTTP ' + response.status);
            }

            return response.json();
        });
    }

    function loadJson(url) {
        return requestJson(url, {});
    }

    function createHistoryRequestGuard(isCurrent) {
        var clearInProgress = false;
        var listGeneration = 0;
        var detailGeneration = 0;

        return {
            startList: function () {
                if (clearInProgress) {
                    return null;
                }

                detailGeneration++;
                return ++listGeneration;
            },
            startDetail: function () {
                if (clearInProgress) {
                    return null;
                }

                return ++detailGeneration;
            },
            startClear: function () {
                if (clearInProgress) {
                    return null;
                }

                clearInProgress = true;
                detailGeneration++;

                return ++listGeneration;
            },
            finishClear: function (generation) {
                if (!clearInProgress || !isCurrent() || generation !== listGeneration) {
                    return false;
                }

                clearInProgress = false;

                return true;
            },
            isListCurrent: function (generation) {
                return isCurrent() && generation === listGeneration;
            },
            isDetailCurrent: function (generation) {
                return isCurrent() && generation === detailGeneration;
            }
        };
    }

    function renderHistoryBrowser(mount, panel, selectedId, onSelect, onClear, isCurrent) {
        mount.innerHTML = '';

        var url = panel && typeof panel.url === 'string' ? panel.url : '';
        if (!url || typeof window.fetch !== 'function') {
            mount.style.display = 'none';
            return;
        }

        mount.style.display = 'block';
        var toolbar = el('div', 'phalcon-debugbar-history-toolbar');
        var title = el('strong', 'phalcon-debugbar-history-title', 'Request history');
        var actions = el('div', 'phalcon-debugbar-history-actions');
        var refreshButton = el('button', 'phalcon-debugbar-history-action', 'Refresh');
        var clearButton = el('button', 'phalcon-debugbar-history-action is-danger', 'Clear');
        var content = el('div', 'phalcon-debugbar-history-content');
        var requestGuard = createHistoryRequestGuard(isCurrent);
        refreshButton.type = 'button';
        clearButton.type = 'button';
        clearButton.disabled = true;
        actions.appendChild(refreshButton);
        actions.appendChild(clearButton);
        toolbar.appendChild(title);
        toolbar.appendChild(actions);
        mount.appendChild(toolbar);
        mount.appendChild(content);

        function message(className, text) {
            content.innerHTML = '';
            content.appendChild(el('div', className, text));
        }

        function refresh() {
            var generation = requestGuard.startList();
            if (generation === null) {
                return;
            }

            refreshButton.disabled = true;
            message('phalcon-debugbar-history-loading', 'Loading request history...');

            loadJson(url).then(function (result) {
                if (!requestGuard.isListCurrent(generation)) {
                    return;
                }

                content.innerHTML = '';
                var requests = result && Array.isArray(result.requests) ? result.requests : [];
                clearButton.disabled = !requests.length;
                if (!requests.length) {
                    content.appendChild(el('div', 'phalcon-debugbar-history-empty', 'No stored requests'));
                    return;
                }

                var list = el('div', 'phalcon-debugbar-history-list');
                requests.forEach(function (request) {
                    request = request || {};
                    var id = scalar(request.id);
                    var button = el('button', 'phalcon-debugbar-history-request');
                    button.type = 'button';
                    if (id === selectedId) {
                        button.classList.add('is-selected');
                    }

                    button.appendChild(el(
                        'span',
                        'phalcon-debugbar-history-method method-' + scalar(request.method).toLowerCase(),
                        scalar(request.method)
                    ));
                    button.appendChild(el('span', 'phalcon-debugbar-history-uri', scalar(request.uri)));
                    button.appendChild(el('span', 'phalcon-debugbar-history-status', scalar(request.status)));
                    button.appendChild(el('time', 'phalcon-debugbar-history-time', scalar(request.requested_at)));

                    button.addEventListener('click', function () {
                        var selection = requestGuard.startDetail();
                        if (selection === null) {
                            return;
                        }

                        button.disabled = true;
                        loadJson(historyUrl(url, id)).then(function (detail) {
                            if (!requestGuard.isDetailCurrent(selection)) {
                                button.disabled = false;
                                return;
                            }

                            if (detail && detail.request && detail.request.payload) {
                                Array.prototype.forEach.call(
                                    list.querySelectorAll('.phalcon-debugbar-history-request'),
                                    function (requestButton) {
                                        requestButton.classList.remove('is-selected');
                                    }
                                );
                                button.classList.add('is-selected');
                                onSelect(detail.request.payload, detail.request.meta || {}, id);
                            }
                            button.disabled = false;
                        }).catch(function () {
                            if (requestGuard.isDetailCurrent(selection)) {
                                button.disabled = false;
                            }
                        });
                    });

                    list.appendChild(button);
                });
                content.appendChild(list);
            }).catch(function () {
                if (requestGuard.isListCurrent(generation)) {
                    message('phalcon-debugbar-history-error', 'Unable to load request history');
                }
            }).then(function () {
                if (requestGuard.isListCurrent(generation)) {
                    refreshButton.disabled = false;
                }
            });
        }

        refreshButton.addEventListener('click', refresh);
        clearButton.addEventListener('click', function () {
            if (!window.confirm('Clear request history?')) {
                return;
            }

            var generation = requestGuard.startClear();
            if (generation === null) {
                return;
            }

            refreshButton.disabled = true;
            clearButton.disabled = true;
            message('phalcon-debugbar-history-loading', 'Clearing request history...');
            requestJson(url, {method: 'DELETE'}).then(function () {
                if (!requestGuard.finishClear(generation)) {
                    return;
                }

                onClear();
                refresh();
            }).catch(function () {
                if (requestGuard.finishClear(generation)) {
                    refreshButton.disabled = false;
                    clearButton.disabled = false;
                    message('phalcon-debugbar-history-error', 'Unable to clear request history');
                }
            });
        });

        refresh();
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
            /* storage unavailable - collapse still works for the session */
        }
    }

    if (typeof module !== 'undefined' && module.exports) {
        module.exports = {
            createHistoryRequestGuard: createHistoryRequestGuard,
            renderHistoryBrowser: renderHistoryBrowser
        };
    }

    if (typeof document === 'undefined') {
        return;
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
        var historyBrowser = el('div', 'phalcon-debugbar-history-browser');
        var body = el('div', 'phalcon-debugbar-body');
        var row = el('div', 'phalcon-debugbar-row');
        var tabs = el('div', 'phalcon-debugbar-tabs');
        var indicators = el('div', 'phalcon-debugbar-indicators');
        var toggle = el('button', 'phalcon-debugbar-toggle', '⚡');
        toggle.type = 'button';
        toggle.title = 'Toggle Phalcon DebugBar';
        body.style.display = 'none';

        var active = null;
        var selectedHistoryId = '';
        var historyOpen = false;
        var historyPanel = null;
        var historyRenderGeneration = 0;
        var historyTrigger = null;

        function closePanel() {
            body.style.display = 'none';
            active = null;
            Array.prototype.forEach.call(tabs.querySelectorAll('[data-panel-tab]'), function (child) {
                child.classList.remove('is-active');
            });
        }

        function renderOpenHistory() {
            if (!historyOpen || !historyPanel) {
                historyBrowser.style.display = 'none';
                return;
            }

            var generation = ++historyRenderGeneration;
            renderHistoryBrowser(
                historyBrowser,
                historyPanel,
                selectedHistoryId,
                function (storedPayload, storedMetadata, id) {
                    var activeBeforeSelection = active;
                    selectedHistoryId = id;
                    setHistoryOpen(false);
                    renderData(storedPayload, activeBeforeSelection, storedMetadata);
                },
                function () {
                    selectedHistoryId = '';
                },
                function () {
                    return historyOpen && generation === historyRenderGeneration;
                }
            );
        }

        function setHistoryOpen(open) {
            historyOpen = Boolean(open && historyPanel);
            if (historyTrigger) {
                historyTrigger.classList.toggle('is-active', historyOpen);
                historyTrigger.setAttribute('aria-expanded', historyOpen ? 'true' : 'false');
                historyTrigger.title = (historyOpen ? 'Close request history: ' : 'Open request history: ')
                    + historyTrigger.getAttribute('data-history-label');
            }

            if (!historyOpen) {
                historyRenderGeneration++;
                historyBrowser.style.display = 'none';
            } else {
                renderOpenHistory();
            }
        }

        function setCollapsed(collapsed) {
            if (collapsed) {
                closePanel();
                setHistoryOpen(false);
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

        function activate(name, tab, entry, type) {
            closePanel();
            setHistoryOpen(false);
            tab.classList.add('is-active');
            body.innerHTML = '';
            var summary = renderSummary(entry.summary);
            if (summary) {
                body.appendChild(summary);
            }
            body.appendChild(renderPanel(type, entry.panel));
            body.style.display = 'block';
            active = name;
        }

        function renderData(nextPayload, preferredActive, requestMetadata) {
            payload = nextPayload || {};
            data = payload.data || {};
            widgets = (payload.meta && payload.meta.widgets) || {};
            tabs.innerHTML = '';
            body.innerHTML = '';
            body.style.display = 'none';
            active = null;

            var historyEntry = data.history || {};
            historyPanel = null;
            if (
                historyEntry.panel
                && typeof historyEntry.panel.url === 'string'
            ) {
                historyPanel = historyEntry.panel;
            } else {
                historyOpen = false;
            }

            var preferred = null;
            Object.keys(data).forEach(function (name) {
                var entry = data[name] || {};
                var widget = widgets[name] || {};
                if (
                    name === 'history'
                    || (historyPanel && (name === 'time' || name === 'memory'))
                ) {
                    return;
                }
                var label = widget.label || titleize(name);
                var type = widget.panel || inferType(entry.panel);

                var tab = el('button', 'phalcon-debugbar-tab');
                tab.type = 'button';
                tab.setAttribute('data-panel-tab', name);
                tab.appendChild(el('span', 'phalcon-debugbar-tab-label', label));
                if (hasBadge(entry.badge)) {
                    tab.appendChild(el('span', 'phalcon-debugbar-badge', scalar(entry.badge)));
                }

                tab.addEventListener('click', function () {
                    setHistoryOpen(false);
                    if (active === name) {
                        closePanel();
                        return;
                    }
                    activate(name, tab, entry, type);
                });

                tabs.appendChild(tab);
                if (name === preferredActive) {
                    preferred = [name, tab, entry, type];
                }
            });

            if (preferred) {
                activate(preferred[0], preferred[1], preferred[2], preferred[3]);
            }

            if (historyPanel) {
                historyTrigger = renderIndicators(
                    indicators,
                    data,
                    function () {
                        if (!historyOpen) {
                            closePanel();
                        }
                        setHistoryOpen(!historyOpen);
                    },
                    historyOpen,
                    requestMetadata
                );
            } else {
                indicators.innerHTML = '';
                historyTrigger = null;
            }
            setHistoryOpen(historyOpen);
        }

        row.appendChild(tabs);
        row.appendChild(indicators);
        row.appendChild(toggle);
        bar.appendChild(historyBrowser);
        bar.appendChild(body);
        bar.appendChild(row);
        mount.appendChild(bar);

        renderData(payload, null, null);
        setCollapsed(readCollapsed());
    });
})();
