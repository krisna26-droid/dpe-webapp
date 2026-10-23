const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');
const assert = require('node:assert/strict');
const markup = JSON.parse(fs.readFileSync(path.join(__dirname, '../fixtures/payment-confirmation.json'), 'utf8'));
const snapshot = 'a'.repeat(64);
const origin = 'http://127.0.0.1:8000';
const current = { csrf_token: 'session-current', expected_snapshot: snapshot, can_upload: true };
const response = (status, data = {}, json = true) => ({ status, ok: status >= 200 && status < 300,
    headers: { get: () => json ? 'application/json' : 'text/html' }, json: async () => data });
const contextResponse = () => response(200, current);
const png = Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aU7sAAAAASUVORK5CYII=', 'base64');
const validFile = () => new File([png], 'bukti.png', { type: 'image/png' });

class Event {
    constructor(type, options = {}) { this.type = type; Object.assign(this, options); this.defaultPrevented = false; }
    preventDefault() { this.defaultPrevented = true; }
    stopImmediatePropagation() { this.stopped = true; }
}
class Element {
    constructor(tag = 'div', attrs = {}) {
        this.tag = tag; this.attrs = { ...attrs }; this.children = []; this.listeners = {}; this.dataset = {};
        this.hidden = Object.hasOwn(attrs, 'hidden'); this.disabled = Object.hasOwn(attrs, 'disabled');
        this.value = attrs.value || ''; this._text = ''; this.files = [];
        this.classList = { add() {}, remove() {}, toggle() {}, contains: () => false };
        for (const [name, value] of Object.entries(attrs)) {
            if (name.startsWith('data-')) this.dataset[name.slice(5).replace(/-([a-z])/g, (_, c) => c.toUpperCase())] = value;
        }
    }
    append(node) { node.parent = this; this.children.push(node); return node; }
    matches(selector) {
        selector = selector.trim();
        const excluded = selector.match(/:not\(([^)]+)\)$/);
        if (excluded) return this.matches(selector.slice(0, excluded.index)) && !this.matches(excluded[1]);
        const space = selector.lastIndexOf(' ');
        if (space !== -1) {
            if (!this.matches(selector.slice(space + 1))) return false;
            for (let parent = this.parent; parent; parent = parent.parent) {
                if (parent.matches(selector.slice(0, space))) return true;
            }
            return false;
        }
        const match = selector.trim().match(/^([a-z]+)?(?:\[([^=\]]+)(?:="([^"]*)")?\])?$/);
        if (!match) throw new Error('Unsupported selector: ' + selector);
        const [, tag, attr, value] = match;
        return (!tag || tag === this.tag) && (!attr || (Object.hasOwn(this.attrs, attr) && (value === undefined || this.attrs[attr] === value)));
    }
    querySelectorAll(selector) {
        const selectors = selector.split(','); const found = [];
        for (const child of this.children) {
            if (selectors.some(s => child.matches(s))) found.push(child);
            found.push(...child.querySelectorAll(selector));
        }
        return found;
    }
    querySelector(selector) { return this.querySelectorAll(selector)[0] || null; }
    get textContent() { return this._text + this.children.map(child => child.textContent).join(''); }
    set textContent(value) {
        // DOM textContent removes descendants, including successful form controls.
        for (const child of this.children) child.parent = null;
        this.children = []; this._text = String(value);
    }
    addEventListener(type, callback, capture = false) { (this.listeners[type] ||= []).push({ callback, capture }); }
    dispatchEvent(event) {
        event.target = event.currentTarget = this;
        for (const listener of [...(this.listeners[event.type] || [])].sort((a, b) => Number(b.capture) - Number(a.capture))) {
            listener.callback(event); if (event.stopped) break;
        }
        return !event.defaultPrevented;
    }
    emit(type, options = {}) { return this.dispatchEvent(new Event(type, options)); }
    focus() { this.focused = true; }
    contains(node) { return node === this || this.children.some(child => child.contains(node)); }
    setAttribute(key, value) { this.attrs[key] = value; }
    removeAttribute(key) { delete this.attrs[key]; }
    setCustomValidity(value) { this.validation = value; }
    reportValidity() { this.validityShown = true; return true; }
    set innerHTML(_) { throw new Error('Unsafe HTML assignment'); }
}
class Form extends Element {
    constructor(attrs = {}) { super('form', attrs); }
    get action() { return this.attrs.action || ''; }
    set action(value) { this.attrs.action = value; }
    submit() { this.nativeSubmissions = (this.nativeSubmissions || 0) + 1; this.nativePayload = new FormData(this); }
}
class FormData extends globalThis.FormData {
    constructor(form) {
        super();
        for (const input of form.querySelectorAll('input')) {
            if (input.disabled || !input.attrs.name) continue;
            if (input.attrs.type === 'file') {
                this.append(input.attrs.name, input.files[0] || new File([], '', { type: 'application/octet-stream' }));
            } else {
                this.append(input.attrs.name, input.value);
            }
        }
    }
}
function fromMarkup(node) {
    const element = node.tag === 'form' ? new Form(node.attrs) : new Element(node.tag, node.attrs);
    element._text = node.text || '';
    for (const child of node.children || []) element.append(fromMarkup(child));
    return element;
}
function fixture(queue = [contextResponse(), response(201, { message: 'Bukti tersimpan' })], options = {}) {
    const document = new Element('document'); document.readyState = 'complete'; document.body = document.append(fromMarkup(markup));
    const form = document.querySelector('[data-payment-upload]');
    const image = form.querySelector('input[name="image"]'); image.files = [validFile()];
    const version = form.querySelector('input[name="expected_snapshot"]'); version.value = snapshot;
    const token = form.querySelector('input[name="_token"]'); token.value = 'session-expired';
    const button = form.querySelector('button[type="submit"]');
    form.action = origin + '/student/payments/charge-1/upload';
    form.dataset.paymentContextUrl = origin + '/student/payments/charge-1/upload-context';
    form.dataset.paymentReturnUrl = origin + '/student/payments/charge-1';
    const ordinary = document.body.append(new Form({ 'data-confirm-form': '', 'data-confirm-title': 'Hapus?', 'data-confirm-message': 'Konfirmasi penghapusan' }));
    ordinary.dataset.confirmTitle = 'Hapus?'; ordinary.dataset.confirmMessage = 'Konfirmasi penghapusan';
    ordinary.append(new Element('input', { name: '_token', type: 'hidden', value: 'ordinary-token' }));
    ordinary.append(new Element('input', { name: '_method', type: 'hidden', value: 'DELETE' }));
    ordinary.append(new Element('button', { type: 'submit' }));
    const modal = document.querySelector('[data-dashboard-confirm]');
    const nodes = Object.fromEntries(['payment-feedback', 'payment-message'].map(key => ['[data-' + key + ']', document.querySelector('[data-' + key + ']')]));
    nodes['[data-dashboard-confirm]'] = modal;
    for (const key of ['confirm-backdrop', 'confirm-cancel', 'confirm-submit', 'confirm-title', 'confirm-message']) nodes['[data-' + key + ']'] = modal.querySelector('[data-' + key + ']');
    const calls = [], redirects = [];
    const window = { FormData, addEventListener() {}, location: { href: origin + '/student/payments/charge-1', origin, assign: url => redirects.push(url) },
        fetch: async (url, config) => {
            calls.push({ url, options: config });
            if (options.fetch) return options.fetch(url, config);
            const next = queue.shift();
            if (next instanceof Error) throw next;
            if (typeof next === 'function') return await next();
            assert.ok(next, 'Unexpected extra network request'); return next;
        } };
    const sandbox = { document, window, URL, FormData, CustomEvent: Event, HTMLFormElement: Form, TypeError, setTimeout, clearTimeout };
    vm.runInNewContext(fs.readFileSync(path.join(__dirname, '../../../public/assets/dpe/dashboard.js'), 'utf8'), sandbox);
    vm.runInNewContext(fs.readFileSync(path.join(__dirname, '../../../public/assets/dpe/portal-payment.js'), 'utf8'), sandbox);
    const settle = async () => { await new Promise(setImmediate); await new Promise(setImmediate); };
    const confirm = async () => { form.emit('submit'); nodes['[data-confirm-submit]'].emit('click'); await settle(); };
    return { form, ordinary, image, version, token, button, nodes, modal, document, calls, redirects, settle, confirm, queue, window };
}
module.exports = { fixture, response, contextResponse, current, snapshot, origin, validFile };
