const test = require('node:test');
const assert = require('node:assert/strict');
const vm = require('node:vm');
const fs = require('node:fs');
const source = fs.readFileSync('assets/js/post-editor.js', 'utf8');

function setup(mode, recovered = null) {
    const classes = new Set(['d-none']);
    const alert = {textContent: '', focused: false, focus() { this.focused = true; },
        classList: {add: c => classes.add(c), remove: c => classes.delete(c)}};
    const submitter = {name: 'save_manual', value: '', disabled: false};
    const fields = {
        title: {value: 'Draft title', type: 'text'}, content: {value: 'Long unsaved content', type: 'textarea'},
        slug: {value: 'custom-slug', dataset: {}, type: 'text'},
        is_top_story: {checked: true, type: 'checkbox'},
        image_file: {type: 'file', files: [{name: 'photo.jpg'}]}
    };
    let submit;
    const form = {dataset: {}, elements: {namedItem: n => fields[n]},
        addEventListener(event, callback) { submit = callback; },
        querySelector: selector => selector === '[data-editor-error]' ? alert : submitter,
        querySelectorAll: () => [submitter]};
    let payload, navigation, shown = false;
    class FormData {
        constructor(form) { this.form = form; this.values = {}; payload = this; }
        set(k, v) { this.values[k] = v; }
    }
    const errorNode = {textContent: 'Image upload exceeds the server limit.'};
    class DOMParser {
        parseFromString() { return {querySelector(selector) {
            if (mode === 'error' && selector.includes('error')) return errorNode;
            if (mode === 'success' && selector.includes('success')) return {};
            return null;
        }}; }
    }
    const window = {FormData, DOMParser, location: {href: 'https://example.test/admin/posts', assign(url) { navigation = url; }},
        async fetch() {
            if (mode === 'network') throw new Error('Disconnected');
            return {ok: true, redirected: false, text: async () => '<html></html>'};
        }, bootstrap: {Modal: {getOrCreateInstance: () => ({show() { shown = true; }})}}};
    const document = {readyState: 'complete', querySelectorAll: () => [form], querySelector: () => errorNode,
        getElementById(id) {
            if (id === 'failed-post-editor') return {textContent: JSON.stringify(recovered)};
            return {querySelector: () => form};
        }};
    vm.runInNewContext(source, {window, document, FormData, DOMParser, fetch: window.fetch, bootstrap: window.bootstrap});
    return {form, fields, submitter, alert, classes,
        submit: () => submit({preventDefault() {}, submitter}),
        payload: () => payload, navigation: () => navigation, shown: () => shown};
}

for (const mode of ['error', 'network', 'unexpected']) {
    test(`${mode}: preserve draft and selected file; show inline error and allow retry`, async () => {
        const ui = setup(mode);
        const file = ui.fields.image_file.files[0];
        await ui.submit();
        assert.equal(ui.fields.title.value, 'Draft title');
        assert.equal(ui.fields.content.value, 'Long unsaved content');
        assert.equal(ui.fields.image_file.files[0], file);
        assert.equal(ui.submitter.disabled, false);
        assert.equal(ui.form.dataset.saving, undefined);
        assert.equal(ui.classes.has('d-none'), false);
        assert(ui.alert.focused);
        assert.equal(ui.navigation(), undefined);
        assert.equal(ui.payload().values.save_manual, '1');
    });
}
test('confirmed save refreshes registry with GET', async () => {
    const ui = setup('success');
    await ui.submit();
    assert.equal(ui.navigation(), 'https://example.test/admin/posts');
});
test('regular failed POST restores fields and reopens the appropriate modal', () => {
    const ui = setup('error', {modal: 'editModal', fields: {title: 'Restored draft', slug: 'my-slug', is_top_story: false}});
    assert.equal(ui.fields.title.value, 'Restored draft');
    assert.equal(ui.fields.slug.value, 'my-slug');
    assert.equal(ui.fields.slug.dataset.edited, '1');
    assert.equal(ui.fields.is_top_story.checked, false);
    assert(ui.shown());
    assert(ui.alert.textContent.includes('reselect the image'));
});
