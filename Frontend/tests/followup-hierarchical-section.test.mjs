import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import {
  indexSectionTree,
  rowMatchesSectionFilters,
  searchSectionOptions,
  sectionToken,
  serviceToken,
  selectionFieldsFromSection,
} from '../src/utils/followupSectionTree.js';

const source = readFileSync(new URL('../src/components/flwup.vue', import.meta.url), 'utf8');

test('follow-up loads hierarchical section tree helpers and chips UI', () => {
  assert.match(source, /from '@\/utils\/followupSectionTree'/);
  assert.match(source, /section-filter-chips/);
  assert.match(source, /clearSubsectionFilters/);
  assert.match(source, /rowMatchesSectionFilters/);
  assert.match(source, /selectionFieldsFromSection/);
  assert.match(source, /ensureSectionTreeLoaded/);
});

test('section tree search and ancestor filter match descendants and services', () => {
  const payload = {
    tree: [],
    sections: [
      { id: 1, name: 'لیزر', parent_id: null, path: 'لیزر', path_ids: [1], is_leaf: false },
      { id: 2, name: 'بانوان', parent_id: 1, path: 'لیزر / بانوان', path_ids: [1, 2], is_leaf: true },
    ],
    services: [
      { id: 9, name: 'فول بادی', section_id: 2, path: 'لیزر / بانوان / فول بادی', path_ids: [1, 2] },
    ],
  };
  const index = indexSectionTree(payload);
  const hits = searchSectionOptions(index, 'بانوان');
  assert.ok(hits.some((item) => item.value === sectionToken(2)));
  assert.ok(hits.some((item) => item.value === serviceToken(9)));

  const childRow = {
    subsectionId: 2,
    sectionPathIds: [1, 2],
    sectionPath: 'لیزر / بانوان',
  };
  assert.equal(rowMatchesSectionFilters(childRow, [sectionToken(1)], index), true);
  assert.equal(rowMatchesSectionFilters(childRow, [serviceToken(9)], index), false);
  assert.equal(rowMatchesSectionFilters({ ...childRow, serviceId: 9 }, [serviceToken(9)], index), true);
  assert.equal(rowMatchesSectionFilters({}, ['__none__'], index), true);

  const fields = selectionFieldsFromSection(payload.sections[1], payload.services[0]);
  assert.deepEqual(fields.sectionPathIds, [1, 2]);
  assert.equal(fields.serviceId, 9);
  assert.equal(fields.rootSectionId, 1);
});
