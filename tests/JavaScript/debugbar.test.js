/**
 * This file is part of the Phalcon Framework.
 *
 * (c) Phalcon Team <team@phalcon.io>
 *
 * For the full copyright and license information, please view the LICENSE.txt
 * file that was distributed with this source code.
 */

'use strict';

var test = require('node:test');
var assert = require('node:assert/strict');
var fs = require('node:fs');
var vm = require('node:vm');
var debugbarPath = require.resolve('../../resources/assets/debugbar.js');
var debugbar = require(debugbarPath);
var dom = require('./support/dom.js');
var createHistoryRequestGuard = debugbar.createHistoryRequestGuard;
var renderHistoryBrowser = debugbar.renderHistoryBrowser;

test.afterEach(function () {
    delete require.cache[debugbarPath];
    delete global.document;
    delete global.window;
});

function response(body) {
    return {
        ok: true,
        json: function () {
            return Promise.resolve(body);
        }
    };
}

function errorResponse(status) {
    return {ok: false, status: status};
}

function panelContract() {
    return JSON.parse(fs.readFileSync(require.resolve('../support/assets/DebugBar/panel-contract.json'), 'utf8'));
}

function renderPayload(payload, windowObject) {
    var dataNode = new dom.TestElement('script');
    var mount = new dom.TestElement('div');
    dataNode.textContent = JSON.stringify(payload);
    global.document = dom.createDocument({
        'phalcon-debugbar': mount,
        'phalcon-debugbar-data': dataNode
    });
    global.window = windowObject || {
        localStorage: {
            getItem: function () {
                return null;
            },
            setItem: function () {}
        }
    };

    delete require.cache[debugbarPath];
    require(debugbarPath);

    return mount;
}

function htmlWrites(root) {
    var writes = root.innerHTML ? [root.innerHTML] : [];
    root.children.forEach(function (child) {
        writes = writes.concat(htmlWrites(child));
    });

    return writes;
}

function messagesPayload() {
    return {
        data: {messages: {panel: []}},
        meta: {widgets: {messages: {label: 'Messages', panel: 'list'}}}
    };
}

function renderIndicator(icon) {
    return renderPayload({
        data: {time: {panel: [], badge: '12.34ms'}},
        meta: {
            widgets: {
                time: {
                    label: 'Time',
                    panel: 'list',
                    indicator: {icon: icon, label: 'Request time', path: ['badge']}
                }
            }
        }
    });
}

function iconShapes(icon) {
    return icon.children.map(function (shape) {
        return shape.tagName;
    });
}

function deferred() {
    var reject;
    var resolve;
    var promise = new Promise(function (promiseResolve, promiseReject) {
        reject = promiseReject;
        resolve = promiseResolve;
    });

    return {promise: promise, reject: reject, resolve: resolve};
}

function historyOptions(overrides) {
    return Object.assign({
        panel: {url: '/_debugbar/open'},
        selectedId: '',
        onSelect: function () {},
        onClear: function () {},
        isCurrent: function () {
            return true;
        }
    }, overrides);
}

test('browser execution does not replace a host CommonJS export', function () {
    var hostExports = {host: true};
    var context = {
        document: {
            addEventListener: function () {},
            getElementById: function () {
                return null;
            }
        },
        module: {exports: hostExports}
    };

    vm.runInNewContext(fs.readFileSync(debugbarPath, 'utf8'), context);

    assert.equal(context.module.exports, hostExports);
});

test('history request guard keeps only the latest list request', function () {
    var guard = createHistoryRequestGuard(function () {
        return true;
    });
    var first = guard.startList();
    var second = guard.startList();

    assert.equal(guard.isListCurrent(first), false);
    assert.equal(guard.isListCurrent(second), true);
});

test('history request guard keeps only the latest detail request', function () {
    var guard = createHistoryRequestGuard(function () {
        return true;
    });
    var first = guard.startDetail();
    var second = guard.startDetail();

    assert.equal(guard.isDetailCurrent(first), false);
    assert.equal(guard.isDetailCurrent(second), true);
});

test('refresh invalidates a pending detail request', function () {
    var guard = createHistoryRequestGuard(function () {
        return true;
    });
    var detail = guard.startDetail();

    guard.startList();

    assert.equal(guard.isDetailCurrent(detail), false);
});

test('closing history invalidates every pending request', function () {
    var open = true;
    var guard = createHistoryRequestGuard(function () {
        return open;
    });
    var list = guard.startList();
    var detail = guard.startDetail();

    open = false;

    assert.equal(guard.isListCurrent(list), false);
    assert.equal(guard.isDetailCurrent(detail), false);
});

test('clear blocks list requests until it completes', function () {
    var guard = createHistoryRequestGuard(function () {
        return true;
    });
    var list = guard.startList();
    var detail = guard.startDetail();
    var clear = guard.startClear();

    assert.equal(guard.isListCurrent(list), false);
    assert.equal(guard.isDetailCurrent(detail), false);
    assert.equal(guard.startList(), null);
    assert.equal(guard.startDetail(), null);
    assert.equal(guard.startClear(), null);
    assert.equal(guard.finishClear(clear), true);
    assert.equal(guard.finishClear(clear), false);
    assert.equal(typeof guard.startList(), 'number');
});

test('empty request history renders its empty state', async function () {
    global.document = dom.createDocument();
    global.window = {
        fetch: function () {
            return Promise.resolve(response({requests: []}));
        }
    };
    var mount = new dom.TestElement('div');

    renderHistoryBrowser(mount, historyOptions());
    await dom.flushPromises();

    assert.equal(dom.findByClass(mount, 'phalcon-debugbar-history-empty').textContent, 'No stored requests');
    assert.equal(dom.findByClass(mount, 'phalcon-debugbar-history-action').disabled, false);
    assert.equal(dom.findByClass(mount, 'is-danger').disabled, true);
});

test('request history renders request metadata and selection', async function () {
    global.document = dom.createDocument();
    global.window = {
        fetch: function () {
            return Promise.resolve(response({
                requests: [{
                    id: 'request-one',
                    method: 'POST',
                    uri: '/orders',
                    status: 201,
                    requested_at: '2026-09-08T08:30:00+00:00'
                }]
            }));
        }
    };
    var mount = new dom.TestElement('div');

    renderHistoryBrowser(mount, historyOptions({selectedId: 'request-one'}));
    await dom.flushPromises();

    assert.equal(dom.findByClass(mount, 'phalcon-debugbar-history-method').textContent, 'POST');
    assert.equal(dom.findByClass(mount, 'phalcon-debugbar-history-uri').textContent, '/orders');
    assert.equal(dom.findByClass(mount, 'phalcon-debugbar-history-status').textContent, '201');
    assert.equal(
        dom.findByClass(mount, 'phalcon-debugbar-history-time').textContent,
        '2026-09-08T08:30:00+00:00'
    );
    assert.equal(dom.findByClass(mount, 'phalcon-debugbar-history-request').classList.contains('is-selected'), true);
    assert.equal(dom.findByClass(mount, 'is-danger').disabled, false);
});

test('selecting a request loads and exposes its stored payload', async function () {
    var calls = [];
    var selected = null;
    global.document = dom.createDocument();
    global.window = {
        fetch: function (url) {
            calls.push(url);
            if (calls.length === 1) {
                return Promise.resolve(response({
                    requests: [{id: 'request/one', method: 'GET', uri: '/orders', status: 200}]
                }));
            }

            return Promise.resolve(response({
                request: {version: 1, payload: {data: {route: {panel: '/orders/42'}}}}
            }));
        }
    };
    var mount = new dom.TestElement('div');

    renderHistoryBrowser(mount, historyOptions({
        onSelect: function (payload, metadata, id) {
            selected = {payload: payload, id: id};
        }
    }));
    await dom.flushPromises();

    var request = dom.findByClass(mount, 'phalcon-debugbar-history-request');
    request.click();
    await dom.flushPromises();

    assert.equal(calls[1], '/_debugbar/open?id=request%2Fone');
    assert.deepEqual(selected, {
        payload: {data: {route: {panel: '/orders/42'}}},
        id: 'request/one'
    });
    assert.equal(request.classList.contains('is-selected'), true);
    assert.equal(request.disabled, false);
});

test('selecting a request rejects an incompatible stored payload version', async function () {
    var selected = false;
    global.document = dom.createDocument();
    global.window = {
        fetch: function (url) {
            if (url.includes('?id=')) {
                return Promise.resolve(response({
                    request: {version: 2, payload: {data: {}, meta: {}}}
                }));
            }

            return Promise.resolve(response({
                requests: [{id: 'stale', method: 'GET', uri: '/stale', status: 200}]
            }));
        }
    };
    var mount = new dom.TestElement('div');

    renderHistoryBrowser(mount, historyOptions({
        onSelect: function () {
            selected = true;
        }
    }));
    await dom.flushPromises();

    var request = dom.findByClass(mount, 'phalcon-debugbar-history-request');
    request.click();
    await dom.flushPromises();

    assert.equal(selected, false);
    assert.equal(request.classList.contains('is-selected'), false);
    assert.equal(request.disabled, false);
});

test('refresh replaces the rendered request list', async function () {
    var responses = [
        {requests: [{id: 'first', method: 'GET', uri: '/first', status: 200}]},
        {requests: [{id: 'second', method: 'GET', uri: '/second', status: 200}]}
    ];
    var calls = 0;
    global.document = dom.createDocument();
    global.window = {
        fetch: function () {
            return Promise.resolve(response(responses[calls++]));
        }
    };
    var mount = new dom.TestElement('div');

    renderHistoryBrowser(mount, historyOptions());
    await dom.flushPromises();

    dom.findByClass(mount, 'phalcon-debugbar-history-action').click();
    await dom.flushPromises();

    assert.equal(calls, 2);
    assert.equal(dom.findByClass(mount, 'phalcon-debugbar-history-uri').textContent, '/second');
});

test('clear blocks interaction and refreshes after deletion', async function () {
    var deletion = deferred();
    var calls = [];
    var cleared = 0;
    global.document = dom.createDocument();
    global.window = {
        confirm: function () {
            return true;
        },
        fetch: function (url, options) {
            calls.push({url: url, options: options});
            if (calls.length === 1) {
                return Promise.resolve(response({
                    requests: [{id: 'first', method: 'GET', uri: '/first', status: 200}]
                }));
            }
            if (calls.length === 2) {
                return deletion.promise;
            }

            return Promise.resolve(response({requests: []}));
        }
    };
    var mount = new dom.TestElement('div');

    renderHistoryBrowser(mount, historyOptions({
        onClear: function () {
            cleared++;
        }
    }));
    await dom.flushPromises();

    var refresh = dom.findByClass(mount, 'phalcon-debugbar-history-action');
    var clear = dom.findByClass(mount, 'is-danger');
    clear.click();

    assert.equal(calls.length, 2);
    assert.equal(calls[1].options.method, 'DELETE');
    assert.equal(refresh.disabled, true);
    assert.equal(clear.disabled, true);
    assert.equal(dom.findByClass(mount, 'phalcon-debugbar-history-loading').textContent, 'Clearing request history...');
    refresh.click();
    assert.equal(calls.length, 2);

    deletion.resolve(response({cleared: 1}));
    await dom.flushPromises();

    assert.equal(calls.length, 3);
    assert.equal(cleared, 1);
    assert.equal(dom.findByClass(mount, 'phalcon-debugbar-history-empty').textContent, 'No stored requests');
});

test('request history reports list failures and restores refresh', async function () {
    global.document = dom.createDocument();
    global.window = {
        fetch: function () {
            return Promise.resolve(errorResponse(503));
        }
    };
    var mount = new dom.TestElement('div');

    renderHistoryBrowser(mount, historyOptions());
    await dom.flushPromises();

    assert.equal(
        dom.findByClass(mount, 'phalcon-debugbar-history-error').textContent,
        'Unable to load request history'
    );
    assert.equal(dom.findByClass(mount, 'phalcon-debugbar-history-action').disabled, false);
});

test('request history restores a row after a detail failure', async function () {
    var calls = 0;
    var selected = false;
    global.document = dom.createDocument();
    global.window = {
        fetch: function () {
            calls++;
            if (calls === 1) {
                return Promise.resolve(response({
                    requests: [{id: 'first', method: 'GET', uri: '/first', status: 200}]
                }));
            }

            return Promise.resolve(errorResponse(404));
        }
    };
    var mount = new dom.TestElement('div');

    renderHistoryBrowser(mount, historyOptions({
        onSelect: function () {
            selected = true;
        }
    }));
    await dom.flushPromises();

    var request = dom.findByClass(mount, 'phalcon-debugbar-history-request');
    request.click();
    await dom.flushPromises();

    assert.equal(selected, false);
    assert.equal(request.disabled, false);
    assert.equal(request.classList.contains('is-selected'), false);
});

test('a history request that does not end times out after 10 seconds', async function () {
    var timers = [];
    global.document = dom.createDocument();
    global.window = {
        AbortController: AbortController,
        setTimeout: function (callback, delay) {
            timers.push({callback: callback, delay: delay});
            return timers.length;
        },
        clearTimeout: function () {},
        fetch: function (url, options) {
            return new Promise(function (resolve, reject) {
                if (options.signal) {
                    options.signal.addEventListener('abort', function () {
                        reject(new Error('aborted'));
                    });
                }
            });
        }
    };
    var mount = new dom.TestElement('div');

    renderHistoryBrowser(mount, historyOptions());
    await dom.flushPromises();

    assert.equal(dom.findByClass(mount, 'phalcon-debugbar-history-action').disabled, true);
    assert.deepEqual(timers.map(function (timer) {
        return timer.delay;
    }), [10000]);

    timers[0].callback();
    await dom.flushPromises();

    assert.equal(
        dom.findByClass(mount, 'phalcon-debugbar-history-error').textContent,
        'Unable to load request history'
    );
    assert.equal(dom.findByClass(mount, 'phalcon-debugbar-history-action').disabled, false);
});

test('a history request that ends clears its timeout', async function () {
    var results = [response({requests: []}), errorResponse(503)];
    var cleared = [];
    global.document = dom.createDocument();

    for (var index = 0; index < results.length; index++) {
        global.window = {
            AbortController: AbortController,
            setTimeout: function () {
                return 'timer';
            },
            clearTimeout: function (id) {
                cleared.push(id);
            },
            fetch: function () {
                return Promise.resolve(results[index]);
            }
        };
        renderHistoryBrowser(new dom.TestElement('div'), historyOptions());
        await dom.flushPromises();
    }

    assert.deepEqual(cleared, ['timer', 'timer']);
});

test('a detail failure shows "Unable to load" in its row until the next click', async function () {
    var details = [];
    global.document = dom.createDocument();
    global.window = {
        fetch: function (url) {
            if (url.includes('?id=first')) {
                var detail = deferred();
                details.push(detail);

                return detail.promise;
            }

            return Promise.resolve(response({
                requests: [{id: 'first', method: 'GET', uri: '/first', status: 200}]
            }));
        }
    };
    var mount = new dom.TestElement('div');

    renderHistoryBrowser(mount, historyOptions());
    await dom.flushPromises();

    var row = dom.findByClass(mount, 'phalcon-debugbar-history-request');
    row.click();
    details[0].resolve(errorResponse(404));
    await dom.flushPromises();

    assert.equal(row.classList.contains('is-error'), true);
    assert.equal(
        dom.findByClass(row, 'phalcon-debugbar-history-request-error').textContent,
        'Unable to load'
    );

    row.click();
    assert.equal(row.classList.contains('is-error'), false);
    details[1].reject(new Error('network'));
    await dom.flushPromises();

    assert.equal(row.classList.contains('is-error'), true);
    assert.equal(row.querySelectorAll('.phalcon-debugbar-history-request-error').length, 1);
});

test('request history restores a superseded row after its detail fails', async function () {
    var failFirst = null;
    global.document = dom.createDocument();
    global.window = {
        fetch: function (url) {
            if (url.includes('?id=first')) {
                return new Promise(function (resolve, reject) {
                    failFirst = reject;
                });
            }
            if (url.includes('?id=second')) {
                return Promise.resolve(response({
                    request: {version: 1, payload: {data: {route: {panel: '/second'}}}}
                }));
            }

            return Promise.resolve(response({
                requests: [
                    {id: 'first', method: 'GET', uri: '/first', status: 200},
                    {id: 'second', method: 'GET', uri: '/second', status: 200}
                ]
            }));
        }
    };
    var mount = new dom.TestElement('div');

    renderHistoryBrowser(mount, historyOptions());
    await dom.flushPromises();

    var rows = mount.querySelectorAll('.phalcon-debugbar-history-request');
    rows[0].click();
    rows[1].click();
    await dom.flushPromises();
    failFirst(new Error('network'));
    await dom.flushPromises();

    assert.equal(rows[1].classList.contains('is-selected'), true);
    assert.equal(rows[0].disabled, false);
});

test('request history reports clear failures and restores controls', async function () {
    var calls = 0;
    var cleared = false;
    global.document = dom.createDocument();
    global.window = {
        confirm: function () {
            return true;
        },
        fetch: function () {
            calls++;
            if (calls === 1) {
                return Promise.resolve(response({
                    requests: [{id: 'first', method: 'GET', uri: '/first', status: 200}]
                }));
            }

            return Promise.resolve(errorResponse(500));
        }
    };
    var mount = new dom.TestElement('div');

    renderHistoryBrowser(mount, historyOptions({
        onClear: function () {
            cleared = true;
        }
    }));
    await dom.flushPromises();

    var refresh = dom.findByClass(mount, 'phalcon-debugbar-history-action');
    var clear = dom.findByClass(mount, 'is-danger');
    clear.click();
    await dom.flushPromises();

    assert.equal(cleared, false);
    assert.equal(refresh.disabled, false);
    assert.equal(clear.disabled, false);
    assert.equal(
        dom.findByClass(mount, 'phalcon-debugbar-history-error').textContent,
        'Unable to clear request history'
    );
});

test('indicator collectors render once on the right and open their panels', function () {
    var dataNode = new dom.TestElement('script');
    var mount = new dom.TestElement('div');
    dataNode.textContent = JSON.stringify({
        data: {
            messages: {panel: []},
            time: {panel: [], badge: '12.34ms'},
            memory: {
                panel: {'Translated current usage': 'panel-value', 'Peak usage': '8MB'},
                badge: '8MB',
                metrics: {current_usage: '7.5MB'}
            },
            request: {
                panel: {Method: 'GET', URI: '/orders'},
                metrics: {method: 'GET', uri: '/orders'}
            },
            history: {panel: {url: '/_debugbar/open'}}
        },
        meta: {
            widgets: {
                messages: {label: 'Messages', panel: 'list'},
                time: {
                    label: 'Time',
                    panel: 'list',
                    indicator: {icon: 'clock', label: 'Request time', path: ['badge']}
                },
                memory: {
                    label: 'Memory',
                    panel: 'grid',
                    indicator: {
                        icon: 'cogs',
                        label: 'Current memory usage',
                        path: ['metrics', 'current_usage']
                    }
                },
                request: {
                    label: 'Request',
                    panel: 'grid',
                    request: {method: ['metrics', 'method'], uri: ['metrics', 'uri']}
                },
                history: {label: 'History', panel: 'history'}
            }
        }
    });
    global.document = dom.createDocument({
        'phalcon-debugbar': mount,
        'phalcon-debugbar-data': dataNode
    });
    global.window = {
        localStorage: {
            getItem: function () {
                return null;
            },
            setItem: function () {}
        }
    };

    require(debugbarPath);

    var labels = mount.querySelectorAll('.phalcon-debugbar-tab-label').map(function (label) {
        return label.textContent;
    });
    var indicators = dom.findByClass(mount, 'phalcon-debugbar-indicators');
    assert.deepEqual(labels, ['Messages', 'Request']);
    assert.ok(indicators);
    assert.equal(indicators.textContent, '12.34ms7.5MBGET/orders');
    var metrics = indicators.querySelectorAll('.phalcon-debugbar-metric');
    assert.equal(metrics.length, 2);
    assert.equal(metrics[0].tagName, 'BUTTON');
    metrics[0].click();
    assert.equal(metrics[0].classList.contains('is-active'), true);
    assert.equal(dom.findByClass(mount, 'phalcon-debugbar-body').style.display, 'block');
    metrics[0].click();
    assert.equal(metrics[0].classList.contains('is-active'), false);
    assert.equal(dom.findByClass(mount, 'phalcon-debugbar-body').style.display, 'none');
});

test('an indicator draws the icon that it names', function () {
    var clock = dom.findByClass(renderIndicator('clock'), 'phalcon-debugbar-indicator-icon');
    var cogs = dom.findByClass(renderIndicator('cogs'), 'phalcon-debugbar-indicator-icon');

    assert.deepEqual(iconShapes(clock), ['CIRCLE', 'PATH']);
    assert.equal(clock.children[0].getAttribute('r'), '6.5');
    assert.deepEqual(iconShapes(cogs), ['G', 'G']);
});

test('the request control draws the search icon', function () {
    var mount = renderPayload({
        data: {history: {panel: {url: '/_debugbar/open'}}},
        meta: {widgets: {history: {label: 'History', panel: 'history'}}}
    });
    var control = dom.findByClass(mount, 'phalcon-debugbar-request-control');
    var search = dom.findByClass(control, 'phalcon-debugbar-indicator-icon');

    assert.deepEqual(iconShapes(search), ['CIRCLE', 'PATH']);
    assert.equal(search.children[0].getAttribute('r'), '4.5');
});

test('the request control shows History when no widget defines the request', function () {
    var mount = renderPayload({
        data: {history: {panel: {url: '/_debugbar/open'}}},
        meta: {widgets: {history: {label: 'History', panel: 'history'}}}
    });
    var control = dom.findByClass(mount, 'phalcon-debugbar-request-control');

    assert.equal(control.textContent, 'History');
    assert.equal(control.title, 'Open request history: History');
});

test('an indicator with an unknown icon name shows its value and no icon', function () {
    ['clcok', 'constructor', ''].forEach(function (name) {
        var mount = renderIndicator(name);
        var indicator = dom.findByClass(mount, 'phalcon-debugbar-metric');

        assert.equal(dom.findByClass(indicator, 'phalcon-debugbar-indicator-icon'), null);
        assert.equal(indicator.textContent, '12.34ms');
    });
});

test('the icon names in PHP match the drawings in the client', function () {
    var types = fs.readFileSync(require.resolve('../../src/DebugBar/DebugBarTypes.php'), 'utf8');
    var definition = /@phpstan-type icon_type (\S+)/.exec(types);

    assert.ok(definition, 'DebugBarTypes must define icon_type');
    assert.deepEqual(
        definition[1].replace(/'/g, '').split('|').sort(),
        debugbar.iconNames.slice().sort()
    );
});

test('the client renders the output of the real collectors', function () {
    var dataNode = new dom.TestElement('script');
    var mount = new dom.TestElement('div');
    dataNode.textContent = JSON.stringify(panelContract().payload);
    global.document = dom.createDocument({
        'phalcon-debugbar': mount,
        'phalcon-debugbar-data': dataNode
    });
    global.window = {
        localStorage: {
            getItem: function () {
                return null;
            },
            setItem: function () {}
        }
    };

    require(debugbarPath);

    var tabs = mount.querySelectorAll('.phalcon-debugbar-tab');
    var labels = tabs.map(function (tab) {
        return dom.findByClass(tab, 'phalcon-debugbar-tab-label').textContent;
    });
    var body = dom.findByClass(mount, 'phalcon-debugbar-body');
    var metrics = mount.querySelectorAll('.phalcon-debugbar-metric');
    function panelText(label) {
        tabs[labels.indexOf(label)].click();

        return body.textContent;
    }

    assert.deepEqual(
        labels,
        ['Version', 'Messages', 'Logs', 'Exceptions', 'Database', 'View', 'Route', 'Cache', 'Request', 'Config']
    );
    assert.equal(dom.findByClass(mount, 'phalcon-debugbar-indicators').textContent, '1ms1MBGET/orders?page=2');
    assert.ok(dom.findByClass(mount, 'is-history-trigger'));
    assert.equal(panelText('Version'), 'PHP8.0.0Phalcon6.0.0');
    assert.equal(panelText('Messages'), 'infouser resolved');
    assert.equal(panelText('Logs'), 'infouser login{\n    "user_id": 7\n}debugping');
    assert.equal(panelText('Exceptions'), 'RuntimeExceptionboom (file.php:1)#0 {main}');
    assert.equal(
        panelText('Database'),
        'Queries3Duplicate Runs1Total Time1ms'
            + '1msSELECT * FROM orders WHERE id = :id  {"id":42}Executed 2 times'
            + '1msSELECT * FROM orders WHERE id = :id  {"id":42}Executed 2 times'
            + '1msSELECT 1'
    );
    assert.equal(panelText('View'), '1msorders/index');
    assert.equal(panelText('Route'), 'ActionGetOrdersAttributes{"id":"42"}');
    assert.equal(panelText('Cache'), 'Getorders');
    assert.equal(
        panelText('Request'),
        'MethodGETURI/orders?page=2Query.page2Headers.Accepttext/html'
    );
    assert.equal(panelText('Config'), 'app.namedemo');
    metrics[0].click();
    assert.equal(body.textContent, 'Request1msRender1ms');
    metrics[1].click();
    assert.equal(body.textContent, 'Current usage1MBPeak usage1MB');
});

test('a string panel renders as text unless its widget sets html', function () {
    var markup = '<img src=x onerror=alert(1)>';
    var mount = renderPayload({
        data: {
            custom: {panel: markup, badge: null},
            trusted: {panel: markup, badge: null}
        },
        meta: {widgets: {trusted: {label: 'Trusted', panel: 'html'}}}
    });
    var tabs = mount.querySelectorAll('.phalcon-debugbar-tab');
    var body = dom.findByClass(mount, 'phalcon-debugbar-body');

    tabs[0].click();
    assert.equal(dom.findByClass(body, 'phalcon-debugbar-code').textContent, markup);
    assert.equal(dom.findByClass(body, 'phalcon-debugbar-html'), null);

    tabs[1].click();
    assert.ok(dom.findByClass(body, 'phalcon-debugbar-html'));
    assert.equal(dom.findByClass(body, 'phalcon-debugbar-code'), null);
});

test('panel values with markup are never written as HTML', function () {
    var markup = '<img src=x onerror=alert(1)>';
    var mount = renderPayload({
        data: {
            list: {panel: [{label: 'info', message: markup}], badge: null},
            grid: {panel: {key: markup}, badge: null},
            code: {panel: markup, badge: null}
        },
        meta: {
            widgets: {
                list: {label: 'List', panel: 'list'},
                grid: {label: 'Grid', panel: 'grid'},
                code: {label: 'Code', panel: 'code'}
            }
        }
    });
    var body = dom.findByClass(mount, 'phalcon-debugbar-body');
    var tabs = mount.querySelectorAll('.phalcon-debugbar-tab');

    assert.equal(tabs.length, 3);
    tabs.forEach(function (tab) {
        tab.click();

        assert.notEqual(body.textContent.indexOf(markup), -1);
        assert.deepEqual(htmlWrites(mount), []);
    });
});

test('an html panel writes its string as HTML', function () {
    var markup = '<b>trusted</b>';
    var mount = renderPayload({
        data: {trusted: {panel: markup, badge: null}},
        meta: {widgets: {trusted: {label: 'Trusted', panel: 'html'}}}
    });

    mount.querySelectorAll('.phalcon-debugbar-tab')[0].click();

    assert.deepEqual(htmlWrites(mount), [markup]);
});

test('the client accepts the stored entry of the real history code', async function () {
    var contract = panelContract();
    var selected = null;
    global.document = dom.createDocument();
    global.window = {
        fetch: function (url) {
            return Promise.resolve(response(
                url.includes('?id=')
                    ? {request: contract.storedEntry}
                    : {requests: [contract.storedEntry.meta]}
            ));
        }
    };
    var mount = new dom.TestElement('div');

    renderHistoryBrowser(mount, historyOptions({
        panel: contract.payload.data.history.panel,
        onSelect: function (payload, metadata, id) {
            selected = {payload: payload, metadata: metadata, id: id};
        }
    }));
    await dom.flushPromises();

    var request = dom.findByClass(mount, 'phalcon-debugbar-history-request');
    assert.equal(
        request.textContent,
        'GET/orders?page=2200' + contract.storedEntry.meta.requested_at
    );
    request.click();
    await dom.flushPromises();

    assert.deepEqual(selected, {
        payload: contract.storedEntry.payload,
        metadata: contract.storedEntry.meta,
        id: contract.storedEntry.meta.id
    });
});

test('request metrics use widget metadata when the collector has a different name', function () {
    var dataNode = new dom.TestElement('script');
    var mount = new dom.TestElement('div');
    dataNode.textContent = JSON.stringify({
        data: {
            time: {panel: [], badge: '12.34ms'},
            http: {
                panel: {Method: 'GET', URI: '/orders'},
                metrics: {method: 'GET', uri: '/orders'}
            }
        },
        meta: {
            widgets: {
                time: {
                    label: 'Time',
                    panel: 'list',
                    indicator: {icon: 'clock', label: 'Request time', path: ['badge']}
                },
                http: {
                    label: 'Request',
                    panel: 'grid',
                    request: {method: ['metrics', 'method'], uri: ['metrics', 'uri']}
                }
            }
        }
    });
    global.document = dom.createDocument({
        'phalcon-debugbar': mount,
        'phalcon-debugbar-data': dataNode
    });
    global.window = {
        localStorage: {
            getItem: function () {
                return null;
            },
            setItem: function () {}
        }
    };

    require(debugbarPath);

    var labels = mount.querySelectorAll('.phalcon-debugbar-tab-label').map(function (label) {
        return label.textContent;
    });
    var indicators = dom.findByClass(mount, 'phalcon-debugbar-indicators');
    assert.deepEqual(labels, ['Request']);
    assert.equal(indicators.textContent, '12.34msGET/orders');
    assert.equal(dom.findByClass(indicators, 'phalcon-debugbar-metric').tagName, 'BUTTON');
    assert.equal(dom.findByClass(indicators, 'phalcon-debugbar-request-control').tagName, 'SPAN');
});

test('request control replaces the History tab and opens request history', async function () {
    var dataNode = new dom.TestElement('script');
    var mount = new dom.TestElement('div');
    dataNode.textContent = JSON.stringify({
        data: {
            messages: {panel: []},
            request: {
                panel: {Method: 'GET', URI: '/orders'},
                metrics: {method: 'GET', uri: '/orders'}
            },
            history: {panel: {url: '/_debugbar/open'}}
        },
        meta: {
            widgets: {
                messages: {label: 'Messages', panel: 'list'},
                request: {
                    label: 'Request',
                    panel: 'grid',
                    request: {method: ['metrics', 'method'], uri: ['metrics', 'uri']}
                },
                history: {label: 'History', panel: 'history'}
            }
        }
    });
    global.document = dom.createDocument({
        'phalcon-debugbar': mount,
        'phalcon-debugbar-data': dataNode
    });
    global.window = {
        fetch: function () {
            return Promise.resolve(response({requests: []}));
        }
    };

    delete require.cache[debugbarPath];
    require(debugbarPath);

    var tabs = dom.findByClass(mount, 'phalcon-debugbar-tabs');
    var requestControl = dom.findByClass(mount, 'phalcon-debugbar-request-control');
    assert.equal(requestControl.tagName, 'BUTTON');
    assert.equal(requestControl.textContent, 'GET/orders');
    assert.equal(requestControl.querySelectorAll('.phalcon-debugbar-indicator-icon').length, 1);
    assert.equal(requestControl.attributes['aria-expanded'], 'false');
    assert.equal(requestControl.title, 'Open request history: GET /orders');
    assert.equal(tabs.querySelectorAll('.phalcon-debugbar-tab-label').map(function (label) {
        return label.textContent;
    }).includes('History'), false);
    var requestTab = tabs.querySelectorAll('.phalcon-debugbar-tab').filter(function (tab) {
        return tab.textContent === 'Request';
    })[0];
    requestTab.click();
    requestControl.click();
    await dom.flushPromises();
    requestControl = dom.findByClass(mount, 'phalcon-debugbar-request-control');
    assert.equal(requestControl.attributes['aria-expanded'], 'true');
    assert.equal(requestControl.title, 'Close request history: GET /orders');
    assert.equal(dom.findByClass(mount, 'phalcon-debugbar-history-browser').style.display, 'block');
    assert.equal(dom.findByClass(mount, 'phalcon-debugbar-body').style.display, 'none');
    assert.equal(requestTab.classList.contains('is-active'), false);

    requestTab.click();
    requestControl = dom.findByClass(mount, 'phalcon-debugbar-request-control');
    assert.equal(requestControl.attributes['aria-expanded'], 'false');
    assert.equal(requestControl.title, 'Open request history: GET /orders');
    assert.equal(dom.findByClass(mount, 'phalcon-debugbar-history-browser').style.display, 'none');
    assert.equal(dom.findByClass(mount, 'phalcon-debugbar-body').style.display, 'block');
    assert.equal(requestTab.classList.contains('is-active'), true);
});

test('selecting stored data closes history and uses metadata without a request collector', async function () {
    var dataNode = new dom.TestElement('script');
    var mount = new dom.TestElement('div');
    var historyPanel = {url: '/_debugbar/open'};
    var widgets = {
        history: {label: 'History', panel: 'history'},
        time: {
            label: 'Time',
            panel: 'list',
            indicator: {icon: 'clock', label: 'Request time', path: ['badge']}
        },
        request: {
            label: 'Request',
            panel: 'grid',
            request: {method: ['metrics', 'method'], uri: ['metrics', 'uri']}
        }
    };
    dataNode.textContent = JSON.stringify({
        data: {
            history: {panel: historyPanel},
            time: {panel: [{label: 'Request', message: '10ms'}], badge: '10ms'},
            request: {
                panel: {Method: 'GET', URI: '/current'},
                metrics: {method: 'GET', uri: '/current'}
            }
        },
        meta: {widgets: widgets}
    });
    global.document = dom.createDocument({
        'phalcon-debugbar': mount,
        'phalcon-debugbar-data': dataNode
    });
    var calls = 0;
    global.window = {
        fetch: function () {
            calls++;
            if (calls === 1) {
                return Promise.resolve(response({
                    requests: [{id: 'stored', method: 'GET', uri: '/stored', status: 200}]
                }));
            }

            return Promise.resolve(response({
                request: {
                    version: 1,
                    meta: {method: 'POST', uri: '/stored'},
                    payload: {
                        data: {
                            history: {panel: historyPanel},
                            time: {panel: [{label: 'Request', message: '20ms'}], badge: '20ms'}
                        },
                        meta: {widgets: widgets}
                    }
                }
            }));
        }
    };

    require(debugbarPath);
    var timeIndicator = dom.findByClass(mount, 'phalcon-debugbar-metric');
    timeIndicator.click();
    assert.equal(timeIndicator.classList.contains('is-active'), true);
    var requestControl = dom.findByClass(mount, 'phalcon-debugbar-request-control');
    requestControl.click();
    await dom.flushPromises();

    var historyBrowser = dom.findByClass(mount, 'phalcon-debugbar-history-browser');
    var request = dom.findByClass(historyBrowser, 'phalcon-debugbar-history-request');
    request.click();
    await dom.flushPromises();

    assert.equal(calls, 2);
    assert.equal(dom.findByClass(mount, 'phalcon-debugbar-history-browser'), historyBrowser);
    assert.equal(historyBrowser.style.display, 'none');
    assert.equal(request.classList.contains('is-selected'), true);
    assert.equal(dom.findByClass(mount, 'phalcon-debugbar-request-control').textContent, 'POST/stored');
    var storedTimeIndicator = dom.findByClass(mount, 'phalcon-debugbar-metric');
    assert.equal(storedTimeIndicator.textContent, '20ms');
    assert.equal(storedTimeIndicator.classList.contains('is-active'), true);
    assert.equal(dom.findByClass(mount, 'phalcon-debugbar-body').style.display, 'block');
});

test('stored data with no widget that defines the request uses the request metadata', async function () {
    var dataNode = new dom.TestElement('script');
    var mount = new dom.TestElement('div');
    var historyPanel = {url: '/_debugbar/open'};
    var widgets = {history: {label: 'History', panel: 'history'}};
    dataNode.textContent = JSON.stringify({
        data: {history: {panel: historyPanel}},
        meta: {widgets: widgets}
    });
    global.document = dom.createDocument({
        'phalcon-debugbar': mount,
        'phalcon-debugbar-data': dataNode
    });
    var calls = 0;
    global.window = {
        fetch: function () {
            calls++;
            if (calls === 1) {
                return Promise.resolve(response({
                    requests: [{id: 'stored', method: 'POST', uri: '/stored', status: 200}]
                }));
            }

            return Promise.resolve(response({
                request: {
                    version: 1,
                    meta: {method: 'POST', uri: '/stored'},
                    payload: {data: {history: {panel: historyPanel}}, meta: {widgets: widgets}}
                }
            }));
        }
    };

    require(debugbarPath);
    dom.findByClass(mount, 'phalcon-debugbar-request-control').click();
    await dom.flushPromises();
    dom.findByClass(mount, 'phalcon-debugbar-history-request').click();
    await dom.flushPromises();

    assert.equal(calls, 2);
    assert.equal(dom.findByClass(mount, 'phalcon-debugbar-request-control').textContent, 'POST/stored');
});

test('selecting stored data reopens the collector tab active before history', async function () {
    var dataNode = new dom.TestElement('script');
    var mount = new dom.TestElement('div');
    var historyPanel = {url: '/_debugbar/open'};
    var widgets = {
        database: {label: 'Database', panel: 'list'},
        history: {label: 'History', panel: 'history'},
        request: {
            label: 'Request',
            panel: 'grid',
            request: {method: ['metrics', 'method'], uri: ['metrics', 'uri']}
        }
    };
    dataNode.textContent = JSON.stringify({
        data: {
            database: {panel: [{label: 'Current', message: 'SELECT 1'}]},
            history: {panel: historyPanel},
            request: {metrics: {method: 'GET', uri: '/current'}, panel: {}}
        },
        meta: {widgets: widgets}
    });
    global.document = dom.createDocument({
        'phalcon-debugbar': mount,
        'phalcon-debugbar-data': dataNode
    });
    var calls = 0;
    global.window = {
        fetch: function () {
            calls++;
            if (calls === 1) {
                return Promise.resolve(response({
                    requests: [{id: 'stored', method: 'GET', uri: '/stored', status: 200}]
                }));
            }

            return Promise.resolve(response({
                request: {
                    version: 1,
                    meta: {method: 'GET', uri: '/stored'},
                    payload: {
                        data: {
                            database: {panel: [{label: 'Stored', message: 'SELECT 2'}]},
                            history: {panel: historyPanel},
                            request: {metrics: {method: 'GET', uri: '/stored'}, panel: {}}
                        },
                        meta: {widgets: widgets}
                    }
                }
            }));
        }
    };

    require(debugbarPath);
    var databaseTab = mount.querySelectorAll('.phalcon-debugbar-tab').filter(function (tab) {
        return tab.textContent === 'Database';
    })[0];
    databaseTab.click();
    dom.findByClass(mount, 'phalcon-debugbar-request-control').click();
    await dom.flushPromises();
    dom.findByClass(mount, 'phalcon-debugbar-history-request').click();
    await dom.flushPromises();

    var storedDatabaseTab = mount.querySelectorAll('.phalcon-debugbar-tab').filter(function (tab) {
        return tab.textContent === 'Database';
    })[0];
    assert.equal(storedDatabaseTab.classList.contains('is-active'), true);
    assert.equal(dom.findByClass(mount, 'phalcon-debugbar-body').textContent, 'StoredSELECT 2');
});

test('the collapse toggle restores and stores its state', function () {
    var stored = [];
    var mount = renderPayload(messagesPayload(), {
        localStorage: {
            getItem: function (key) {
                return key === 'phalcon-debugbar-collapsed' ? '1' : null;
            },
            setItem: function (key, value) {
                stored.push([key, value]);
            }
        }
    });
    var toggle = dom.findByClass(mount, 'phalcon-debugbar-toggle');

    assert.equal(mount.classList.contains('is-collapsed'), true);
    toggle.click();
    assert.equal(mount.classList.contains('is-collapsed'), false);
    toggle.click();
    assert.equal(mount.classList.contains('is-collapsed'), true);
    assert.deepEqual(stored, [['phalcon-debugbar-collapsed', '0'], ['phalcon-debugbar-collapsed', '1']]);
});

test('the collapse toggle works when localStorage throws', function () {
    var mount = renderPayload(messagesPayload(), {
        localStorage: {
            getItem: function () {
                throw new Error('denied');
            },
            setItem: function () {
                throw new Error('denied');
            }
        }
    });

    assert.equal(mount.classList.contains('is-collapsed'), false);
    dom.findByClass(mount, 'phalcon-debugbar-toggle').click();
    assert.equal(mount.classList.contains('is-collapsed'), true);
});

function twoTabsPayload() {
    return {
        data: {messages: {panel: []}, route: {panel: []}},
        meta: {
            widgets: {
                messages: {label: 'Messages', panel: 'list'},
                route: {label: 'Route', panel: 'list'}
            }
        }
    };
}

function selectedTabs(tabs) {
    return tabs.map(function (tab) {
        return tab.getAttribute('aria-selected');
    });
}

test('the collapse toggle has a name and reports whether the bar is open', function () {
    var mount = renderPayload(messagesPayload());
    var toggle = dom.findByClass(mount, 'phalcon-debugbar-toggle');

    assert.equal(toggle.getAttribute('aria-label'), 'Toggle Phalcon DebugBar');
    assert.equal(toggle.getAttribute('aria-expanded'), 'true');

    toggle.click();

    assert.equal(toggle.getAttribute('aria-expanded'), 'false');
});

test('the tabs are an ARIA tab list that marks the selected tab', function () {
    var mount = renderPayload(twoTabsPayload());
    var tabs = mount.querySelectorAll('.phalcon-debugbar-tab');

    assert.equal(dom.findByClass(mount, 'phalcon-debugbar-tabs').getAttribute('role'), 'tablist');
    assert.deepEqual(tabs.map(function (tab) {
        return tab.getAttribute('role');
    }), ['tab', 'tab']);
    assert.deepEqual(selectedTabs(tabs), ['false', 'false']);

    tabs[1].click();
    assert.deepEqual(selectedTabs(tabs), ['false', 'true']);

    tabs[0].click();
    assert.deepEqual(selectedTabs(tabs), ['true', 'false']);

    tabs[0].click();
    assert.deepEqual(selectedTabs(tabs), ['false', 'false']);
});

test('Escape in the bar closes the open panel, and other keys do nothing', function () {
    var mount = renderPayload(twoTabsPayload());
    var tab = mount.querySelectorAll('.phalcon-debugbar-tab')[0];
    var body = dom.findByClass(mount, 'phalcon-debugbar-body');

    tab.click();
    mount.keydown('Enter');
    assert.equal(body.style.display, 'block');

    mount.keydown('Escape');
    assert.equal(body.style.display, 'none');
    assert.equal(tab.classList.contains('is-active'), false);
    assert.equal(tab.getAttribute('aria-selected'), 'false');
});

test('Escape in the bar closes the request history', async function () {
    var mount = renderPayload({
        data: {
            request: {panel: {Method: 'GET', URI: '/orders'}, metrics: {method: 'GET', uri: '/orders'}},
            history: {panel: {url: '/_debugbar/open'}}
        },
        meta: {
            widgets: {
                request: {
                    label: 'Request',
                    panel: 'grid',
                    request: {method: ['metrics', 'method'], uri: ['metrics', 'uri']}
                },
                history: {label: 'History', panel: 'history'}
            }
        }
    }, {
        fetch: function () {
            return Promise.resolve(response({requests: []}));
        }
    });

    dom.findByClass(mount, 'phalcon-debugbar-request-control').click();
    await dom.flushPromises();
    assert.equal(dom.findByClass(mount, 'phalcon-debugbar-history-browser').style.display, 'block');

    mount.keydown('Escape');

    assert.equal(dom.findByClass(mount, 'phalcon-debugbar-history-browser').style.display, 'none');
    assert.equal(dom.findByClass(mount, 'phalcon-debugbar-request-control').getAttribute('aria-expanded'), 'false');
});
