import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

const source = readFileSync(new URL('../src/components/Time.vue', import.meta.url), 'utf8');

test('timeline appointment service picker follows every nested subsection level', () => {
  assert.match(
    source,
    /timeline-service-fields[\s\S]*?v-for="level in serviceSectionLevels\(service\)"[\s\S]*?setTimelineServiceSectionLevel\(service, level, \$event\.target\.value\)/
  );
  assert.match(
    source,
    /:disabled="!serviceCanChooseItem\(service\)"[\s\S]*?:placeholder="serviceCanChooseItem\(service\) \? 'نام خدمت را جست‌وجو کنید' : 'مسیر زیر‌بخش را کامل کنید'"/
  );
});

test('a single selected timeline section is assigned to existing and newly added service rows', () => {
  assert.match(
    source,
    /onRowServiceTypesChanged\(row\)[\s\S]*?row\.serviceTypes\.length === 1[\s\S]*?service\.rootSectionId = row\.serviceTypes\[0\]/
  );
  assert.match(
    source,
    /addTimelineService\(\)[\s\S]*?selectedRoots\.length === 1[\s\S]*?addedService\.rootSectionId = selectedRoots\[0\]/
  );
});

test('choosing a service path does not discard other sections selected in the timeline form', () => {
  assert.match(
    source,
    /timelineServiceRootSectionIds\(activeTimelineDraft\)\.length > 1[\s\S]*?v-for="sectionId in timelineServiceRootSectionIds\(activeTimelineDraft\)"/
  );
  assert.match(
    source,
    /setTimelineServiceRootSection\(service, sectionId\)[\s\S]*?const selectedRoots = \[\.\.\.this\.activeTimelineDraft\.serviceTypes\][\s\S]*?this\.activeTimelineDraft\.serviceTypes = selectedRoots/
  );
  assert.match(
    source,
    /setTimelineServiceSectionLevel\(service, level, sectionId\)[\s\S]*?const selectedRoots = \[\.\.\.this\.activeTimelineDraft\.serviceTypes\][\s\S]*?this\.activeTimelineDraft\.serviceTypes = selectedRoots/
  );
});

test('timeline service dropdown keeps a usable height when multiselect opens upward', () => {
  assert.match(
    source,
    /\.timeline-service-multiselect\.multiselect--above\s+\.multiselect__content-wrapper\s*\{[^}]*top:\s*auto;[^}]*bottom:\s*calc\(100% \+ 6px\);[^}]*\}/s,
  );
});
