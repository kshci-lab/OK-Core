const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const path = require('node:path');

let source = fs.readFileSync(path.join(__dirname, '../js/organizational-combination-tab.js'), 'utf8');
source = source.replace(/\}\)\(\);\s*$/, `
  window.selectionTest = {
    refs: getSelectedFragmentRefs,
    toggle: toggleAdditionalSelection,
    setFilter: function(types){ sourceFilterState['knowledge-tree'] = types; },
    visible: function(types){ return matchesSourceFilter(types, 'knowledge-tree'); }
  };
})();`);
const document = {
  readyState: 'loading', addEventListener() {},
  getElementById() { return null; }, querySelectorAll() { return []; },
};
const window = { activeKnowledgeFragmentId: 42, activeKnowledgeFragmentSourceType: 'experience' };
vm.runInNewContext(source, { window, document, console, setTimeout, clearTimeout });
const api = window.selectionTest;
assert.ok(api, 'Selection test instrumentation must load.');
const workspace = { querySelectorAll() { return []; } };
api.toggle(workspace, '42', 'discussion');
assert.deepEqual(Array.from(api.refs()), ['experience:42', 'discussion:42']);
api.toggle(workspace, '43', 'discussion');
assert.deepEqual(Array.from(api.refs()), ['experience:42', 'discussion:42', 'discussion:43']);
api.toggle(workspace, '42', 'experience');
assert.deepEqual(Array.from(api.refs()), ['experience:42', 'discussion:42', 'discussion:43'], 'Clicking primary must preserve the mixed selection.');
api.toggle(workspace, '42', 'discussion');
assert.deepEqual(Array.from(api.refs()), ['experience:42', 'discussion:43']);
for (const type of ['experience', 'discussion']) {
  api.setFilter([type]);
  assert.equal(api.visible(['experience', 'discussion']), true, 'Mixed organizational knowledge must appear under either source filter.');
}
console.log('PASS: mixed selection, equal numeric IDs, primary preservation and both source filters.');
