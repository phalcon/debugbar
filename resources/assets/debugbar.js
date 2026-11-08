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
    var HISTORY_VERSION = 1;
    var REQUEST_TIMEOUT_MS = 10000;

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
        return group;
    }

    // `icon_type` in DebugBarTypes.php lists the same names, and a test compares the two.
    var ICONS = {
        clock: function (svg) {
            svg.appendChild(svgElement('circle', {cx: '9', cy: '9', r: '6.5'}));
            svg.appendChild(svgElement('path', {d: 'M9 5.2V9l2.8 1.7'}));
        },
        cogs: function (svg) {
            svg.appendChild(gear(6.2, 10.5, 3.1));
            svg.appendChild(gear(12.2, 6.2, 2.4));
        },
        search: function (svg) {
            svg.appendChild(svgElement('circle', {cx: '7.5', cy: '7.5', r: '4.5'}));
            svg.appendChild(svgElement('path', {d: 'M10.8 10.8l4 4'}));
        }
    };

    function indicatorIcon(name) {
        if (!Object.prototype.hasOwnProperty.call(ICONS, name)) {
            return null;
        }

        var svg = svgElement('svg', {
            'aria-hidden': 'true',
            'class': 'phalcon-debugbar-indicator-icon',
            'viewBox': '0 0 18 18'
        });
        ICONS[name](svg);
        return svg;
    }

    function metricIndicator(icon, label, value) {
        var indicator = el('button', 'phalcon-debugbar-indicator phalcon-debugbar-metric');
        var drawing = indicatorIcon(icon);
        indicator.type = 'button';
        indicator.title = label;
        if (drawing) {
            indicator.appendChild(drawing);
        }
        indicator.appendChild(el('span', 'phalcon-debugbar-indicator-value', scalar(value)));
        return indicator;
    }

    function valueAtPath(value, path) {
        if (!Array.isArray(path) || path.length === 0) {
            return undefined;
        }

        path.forEach(function (key) {
            value = value && typeof value === 'object' ? value[key] : undefined;
        });

        return value;
    }

    function renderMetricIndicators(mount, data, widgets, bindPanel) {
        Object.keys(widgets).forEach(function (collector) {
            var definition = (widgets[collector] || {}).indicator;
            if (!definition || !data[collector]) {
                return;
            }
            var value = valueAtPath(data[collector], definition.path);
            var indicator = metricIndicator(
                scalar(definition.icon),
                scalar(definition.label),
                value
            );
            bindPanel(collector, indicator);
            mount.appendChild(indicator);
        });
    }

    function renderRequestControl(mount, options) {
        var widgets = options.widgets;
        var onHistoryToggle = options.onHistoryToggle;
        var historyOpen = options.historyOpen;
        var historyTrigger = null;
        var requestName = Object.keys(widgets).find(function (name) {
            return widgets[name] && widgets[name].request;
        });
        var requestDefinition = requestName ? widgets[requestName].request : {};
        var request = requestName ? (options.data[requestName] || {}) : {};
        var method = scalar(valueAtPath(request, requestDefinition.method || []));
        var uri = scalar(valueAtPath(request, requestDefinition.uri || []));
        var requestMetadata = options.requestMetadata || {};
        method = method || scalar(requestMetadata.method);
        uri = uri || scalar(requestMetadata.uri);
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
            return 'code';
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
        var source = '';
        if (typeof panel === 'string') {
            source = panel;
        } else if (panel && panel.source !== undefined) {
            source = panel.source;
        }
        return el('pre', 'phalcon-debugbar-code', scalar(source));
    }

    function renderHtml(panel) {
        var node = el('div', 'phalcon-debugbar-html');
        node.innerHTML = typeof panel === 'string' ? panel : '';
        return node;
    }

    /*
     * The exceptions and logs panels: a row with a detail (the trace or the
     * context) opens to show it, and a row with no detail is a plain line.
     * `kind` names the classes: phalcon-debugbar-exception-*, phalcon-debugbar-log-*.
     */
    function renderEntries(panel, kind, detailKey) {
        if (!Array.isArray(panel) || !panel.length) {
            return el('div', 'phalcon-debugbar-empty', 'No data');
        }
        var prefix = 'phalcon-debugbar-' + kind;
        var wrap = el('div', prefix + 's');
        panel.forEach(function (row) {
            row = row || {};
            var detail = scalar(row[detailKey]);
            var label = el('span', prefix + '-label', scalar(row.label));
            var message = el('span', prefix + '-message', scalar(row.message));
            if (detail === '') {
                var line = el('div', prefix);
                line.appendChild(label);
                line.appendChild(message);
                wrap.appendChild(line);
                return;
            }
            var details = el('details', prefix);
            var summary = el('summary', prefix + '-summary');
            summary.appendChild(label);
            summary.appendChild(message);
            details.appendChild(summary);
            details.appendChild(el('pre', prefix + '-' + detailKey, detail));
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
                return renderEntries(panel, 'exception', 'trace');
            case 'logs':
                return renderEntries(panel, 'log', 'context');
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

        // A request that does not end must not keep the history controls disabled.
        var timer = null;
        if (window.AbortController && window.setTimeout && window.clearTimeout) {
            var controller = new window.AbortController();
            options.signal = controller.signal;
            timer = window.setTimeout(function () {
                controller.abort();
            }, REQUEST_TIMEOUT_MS);
        }

        function stopTimer() {
            if (timer !== null) {
                window.clearTimeout(timer);
            }
        }

        return window.fetch(url, options).then(function (response) {
            if (!response.ok) {
                throw new Error('HTTP ' + response.status);
            }

            return response.json();
        }).then(function (data) {
            stopTimer();
            return data;
        }, function (error) {
            stopTimer();
            throw error;
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

    function renderHistoryBrowser(mount, options) {
        mount.innerHTML = '';

        var panel = options.panel;
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
        var requestGuard = createHistoryRequestGuard(options.isCurrent);
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
                    if (id === options.selectedId) {
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

                    var rowError = null;
                    button.addEventListener('click', function () {
                        var selection = requestGuard.startDetail();
                        if (selection === null) {
                            return;
                        }

                        button.disabled = true;
                        button.classList.remove('is-error');
                        loadJson(historyUrl(url, id)).then(function (detail) {
                            if (!requestGuard.isDetailCurrent(selection)) {
                                button.disabled = false;
                                return;
                            }

                            if (
                                detail
                                && detail.request
                                && detail.request.version === HISTORY_VERSION
                                && detail.request.payload
                            ) {
                                Array.prototype.forEach.call(
                                    list.querySelectorAll('.phalcon-debugbar-history-request'),
                                    function (requestButton) {
                                        requestButton.classList.remove('is-selected');
                                    }
                                );
                                button.classList.add('is-selected');
                                options.onSelect(detail.request.payload, detail.request.meta || {}, id);
                            }
                            button.disabled = false;
                        }).catch(function () {
                            button.disabled = false;
                            button.classList.add('is-error');
                            if (rowError === null) {
                                rowError = el('span', 'phalcon-debugbar-history-request-error', 'Unable to load');
                                button.appendChild(rowError);
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

                options.onClear();
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

    if (typeof document === 'undefined') {
        if (typeof module !== 'undefined' && module.exports) {
            module.exports = {
                createHistoryRequestGuard: createHistoryRequestGuard,
                iconNames: Object.keys(ICONS),
                renderHistoryBrowser: renderHistoryBrowser
            };
        }
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

        // renderData() sets both before any panel reads them.
        var data;
        var widgets;

        var bar = el('div', 'phalcon-debugbar-bar');
        var historyBrowser = el('div', 'phalcon-debugbar-history-browser');
        var body = el('div', 'phalcon-debugbar-body');
        var row = el('div', 'phalcon-debugbar-row');
        var tabs = el('div', 'phalcon-debugbar-tabs');
        tabs.setAttribute('role', 'tablist');
        var indicators = el('div', 'phalcon-debugbar-indicators');
        var toggle = el('button', 'phalcon-debugbar-toggle', '⚡');
        toggle.type = 'button';
        toggle.title = 'Toggle Phalcon DebugBar';
        toggle.setAttribute('aria-label', 'Toggle Phalcon DebugBar');
        body.style.display = 'none';

        var active = null;
        var selectedHistoryId = '';
        var historyOpen = false;
        var historyPanel = null;
        var historyRenderGeneration = 0;
        var historyTrigger = null;
        var activeBeforeHistory = null;

        // A metric indicator also opens a panel, but only a tab has aria-selected.
        function setSelected(node, selected) {
            node.classList.toggle('is-active', selected);
            if (node.getAttribute('role') === 'tab') {
                node.setAttribute('aria-selected', selected ? 'true' : 'false');
            }
        }

        function closePanel() {
            body.style.display = 'none';
            active = null;
            Array.prototype.forEach.call(row.querySelectorAll('[data-panel-tab]'), function (child) {
                setSelected(child, false);
            });
        }

        function renderOpenHistory() {
            if (!historyOpen || !historyPanel) {
                historyBrowser.style.display = 'none';
                return;
            }

            var generation = ++historyRenderGeneration;
            renderHistoryBrowser(historyBrowser, {
                panel: historyPanel,
                selectedId: selectedHistoryId,
                onSelect: function (storedPayload, storedMetadata, id) {
                    var preferredActive = activeBeforeHistory;
                    selectedHistoryId = id;
                    setHistoryOpen(false);
                    activeBeforeHistory = null;
                    renderData(storedPayload, preferredActive, storedMetadata);
                },
                onClear: function () {
                    selectedHistoryId = '';
                },
                isCurrent: function () {
                    return historyOpen && generation === historyRenderGeneration;
                }
            });
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
            toggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
        }

        toggle.addEventListener('click', function () {
            var collapsed = !mount.classList.contains('is-collapsed');
            setCollapsed(collapsed);
            writeCollapsed(collapsed);
        });

        // The listener is on the bar, so it gets only the keys pressed while the focus is in the bar.
        mount.addEventListener('keydown', function (event) {
            if (event.key !== 'Escape') {
                return;
            }

            if (historyOpen) {
                setHistoryOpen(false);
            } else if (active !== null) {
                closePanel();
            }
        });

        function activate(name, tab, entry, type) {
            closePanel();
            setHistoryOpen(false);
            setSelected(tab, true);
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
            var current = nextPayload || {};
            data = current.data || {};
            widgets = (current.meta && current.meta.widgets) || {};
            tabs.innerHTML = '';
            body.innerHTML = '';
            body.style.display = 'none';
            active = null;

            var historyName = Object.keys(widgets).find(function (name) {
                return widgets[name] && widgets[name].panel === 'history';
            });
            var historyEntry = historyName ? (data[historyName] || {}) : {};
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
            function bindPanel(name, node) {
                var entry = data[name] || {};
                var widget = widgets[name] || {};
                var type = widget.panel || inferType(entry.panel);

                node.setAttribute('data-panel-tab', name);
                node.addEventListener('click', function () {
                    setHistoryOpen(false);
                    if (active === name) {
                        closePanel();
                        return;
                    }
                    activate(name, node, entry, type);
                });
                if (name === preferredActive) {
                    preferred = [name, node, entry, type];
                }
            }

            Object.keys(data).forEach(function (name) {
                var entry = data[name] || {};
                var widget = widgets[name] || {};
                if (widget.panel === 'history' || widget.indicator) {
                    return;
                }
                var label = widget.label || titleize(name);

                var tab = el('button', 'phalcon-debugbar-tab');
                tab.type = 'button';
                tab.setAttribute('role', 'tab');
                tab.setAttribute('aria-selected', 'false');
                tab.appendChild(el('span', 'phalcon-debugbar-tab-label', label));
                if (hasBadge(entry.badge)) {
                    tab.appendChild(el('span', 'phalcon-debugbar-badge', scalar(entry.badge)));
                }

                bindPanel(name, tab);
                tabs.appendChild(tab);
            });

            indicators.innerHTML = '';
            renderMetricIndicators(indicators, data, widgets, bindPanel);
            historyTrigger = renderRequestControl(indicators, {
                data: data,
                widgets: widgets,
                requestMetadata: requestMetadata,
                historyOpen: historyOpen,
                onHistoryToggle: historyPanel ? function () {
                    if (!historyOpen) {
                        activeBeforeHistory = active;
                        closePanel();
                    }
                    setHistoryOpen(!historyOpen);
                } : null
            });

            if (preferred) {
                activate(preferred[0], preferred[1], preferred[2], preferred[3]);
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
