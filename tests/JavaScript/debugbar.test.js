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

function deferred() {
    var reject;
    var resolve;
    var promise = new Promise(function (promiseResolve, promiseReject) {
        reject = promiseReject;
        resolve = promiseResolve;
    });

    return {promise: promise, reject: reject, resolve: resolve};
}

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

    renderHistoryBrowser(mount, {url: '/_debugbar/open'}, '', function () {}, function () {}, function () {
        return true;
    });
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

    renderHistoryBrowser(
        mount,
        {url: '/_debugbar/open'},
        'request-one',
        function () {},
        function () {},
        function () {
            return true;
        }
    );
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
                request: {payload: {data: {route: {panel: '/orders/42'}}}}
            }));
        }
    };
    var mount = new dom.TestElement('div');

    renderHistoryBrowser(mount, {url: '/_debugbar/open'}, '', function (payload, id) {
        selected = {payload: payload, id: id};
    }, function () {}, function () {
        return true;
    });
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

    renderHistoryBrowser(mount, {url: '/_debugbar/open'}, '', function () {}, function () {}, function () {
        return true;
    });
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

    renderHistoryBrowser(mount, {url: '/_debugbar/open'}, '', function () {}, function () {
        cleared++;
    }, function () {
        return true;
    });
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

    renderHistoryBrowser(mount, {url: '/_debugbar/open'}, '', function () {}, function () {}, function () {
        return true;
    });
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

    renderHistoryBrowser(mount, {url: '/_debugbar/open'}, '', function () {
        selected = true;
    }, function () {}, function () {
        return true;
    });
    await dom.flushPromises();

    var request = dom.findByClass(mount, 'phalcon-debugbar-history-request');
    request.click();
    await dom.flushPromises();

    assert.equal(selected, false);
    assert.equal(request.disabled, false);
    assert.equal(request.classList.contains('is-selected'), false);
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

    renderHistoryBrowser(mount, {url: '/_debugbar/open'}, '', function () {}, function () {
        cleared = true;
    }, function () {
        return true;
    });
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

test('selecting stored data preserves the open history browser', async function () {
    var dataNode = new dom.TestElement('script');
    var mount = new dom.TestElement('div');
    var historyPanel = {url: '/_debugbar/open'};
    var widgets = {
        history: {label: 'History', panel: 'history'},
        route: {label: 'Route', panel: 'grid'}
    };
    dataNode.textContent = JSON.stringify({
        data: {
            history: {panel: historyPanel},
            route: {panel: {path: '/current'}}
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
                    payload: {
                        data: {
                            history: {panel: historyPanel},
                            route: {panel: {path: '/stored'}}
                        },
                        meta: {widgets: widgets}
                    }
                }
            }));
        }
    };

    require(debugbarPath);
    var tabs = mount.querySelectorAll('.phalcon-debugbar-tab');
    var historyTab = tabs.filter(function (tab) {
        return tab.textContent === 'History';
    })[0];
    historyTab.click();
    await dom.flushPromises();

    var historyBrowser = dom.findByClass(mount, 'phalcon-debugbar-history-browser');
    var request = dom.findByClass(historyBrowser, 'phalcon-debugbar-history-request');
    request.click();
    await dom.flushPromises();

    assert.equal(calls, 2);
    assert.equal(dom.findByClass(mount, 'phalcon-debugbar-history-browser'), historyBrowser);
    assert.equal(historyBrowser.style.display, 'block');
    assert.equal(request.classList.contains('is-selected'), true);
});
