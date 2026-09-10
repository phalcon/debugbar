/**
 * This file is part of the Phalcon Framework.
 *
 * (c) Phalcon Team <team@phalcon.io>
 *
 * For the full copyright and license information, please view the LICENSE.txt
 * file that was distributed with this source code.
 */

'use strict';

function TestElement(tagName) {
    this.tagName = String(tagName).toUpperCase();
    this.children = [];
    this.parentNode = null;
    this.style = {};
    this.disabled = false;
    this.type = '';
    this.attributes = {};
    this._className = '';
    this._listeners = {};
    this._textContent = '';
}

Object.defineProperty(TestElement.prototype, 'className', {
    get: function () {
        return this._className;
    },
    set: function (value) {
        this._className = String(value || '');
    }
});

Object.defineProperty(TestElement.prototype, 'classList', {
    get: function () {
        var element = this;

        return {
            add: function (name) {
                var classes = element._classes();
                if (classes.indexOf(name) === -1) {
                    classes.push(name);
                    element.className = classes.join(' ');
                }
            },
            contains: function (name) {
                return element._classes().indexOf(name) !== -1;
            },
            remove: function (name) {
                element.className = element._classes().filter(function (current) {
                    return current !== name;
                }).join(' ');
            },
            toggle: function (name, force) {
                var enabled = force === undefined ? !this.contains(name) : Boolean(force);
                if (enabled) {
                    this.add(name);
                } else {
                    this.remove(name);
                }

                return enabled;
            }
        };
    }
});

Object.defineProperty(TestElement.prototype, 'innerHTML', {
    get: function () {
        return '';
    },
    set: function () {
        this.children = [];
        this._textContent = '';
    }
});

Object.defineProperty(TestElement.prototype, 'textContent', {
    get: function () {
        return this._textContent + this.children.map(function (child) {
            return child.textContent;
        }).join('');
    },
    set: function (value) {
        this.children = [];
        this._textContent = String(value || '');
    }
});

TestElement.prototype._classes = function () {
    return this.className.split(/\s+/).filter(Boolean);
};

TestElement.prototype.addEventListener = function (name, listener) {
    this._listeners[name] = listener;
};

TestElement.prototype.appendChild = function (child) {
    child.parentNode = this;
    this.children.push(child);

    return child;
};

TestElement.prototype.click = function () {
    if (!this.disabled && this._listeners.click) {
        this._listeners.click();
    }
};

TestElement.prototype.querySelectorAll = function (selector) {
    var className = selector.charAt(0) === '.' ? selector.slice(1) : '';
    var attribute = /^\[([^\]]+)\]$/.exec(selector);
    var matches = [];

    this.children.forEach(function (child) {
        if (
            (className && child.classList.contains(className))
            || (attribute && Object.prototype.hasOwnProperty.call(child.attributes, attribute[1]))
        ) {
            matches.push(child);
        }
        matches = matches.concat(child.querySelectorAll(selector));
    });

    return matches;
};

TestElement.prototype.setAttribute = function (name, value) {
    this.attributes[name] = String(value);
    if (name === 'class') {
        this.className = value;
    }
};

TestElement.prototype.getAttribute = function (name) {
    return Object.prototype.hasOwnProperty.call(this.attributes, name)
        ? this.attributes[name]
        : null;
};

function createDocument(elements) {
    elements = elements || {};

    return {
        readyState: 'complete',
        createElement: function (tagName) {
            return new TestElement(tagName);
        },
        createElementNS: function (namespace, tagName) {
            return new TestElement(tagName);
        },
        getElementById: function (id) {
            return elements[id] || null;
        }
    };
}

function findByClass(root, className) {
    return root.querySelectorAll('.' + className)[0] || null;
}

function flushPromises() {
    return new Promise(function (resolve) {
        setImmediate(resolve);
    });
}

module.exports = {
    TestElement: TestElement,
    createDocument: createDocument,
    findByClass: findByClass,
    flushPromises: flushPromises
};
