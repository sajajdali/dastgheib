import { readFileSync } from "node:fs";
import test from "node:test";
import assert from "node:assert/strict";

const leadsReport = readFileSync(new URL("../public/reports/leads.html", import.meta.url), "utf8");
const legacyLeads = readFileSync(new URL("../src/components/Notif.vue", import.meta.url), "utf8");
const menu = readFileSync(new URL("../src/components/menu.vue", import.meta.url), "utf8");

test("the leads page does not import today's appointment list", () => {
  assert.doesNotMatch(leadsReport, /loadAppointmentItems/);
  assert.doesNotMatch(leadsReport, /fetch\('\/api\/appointments'/);
  assert.doesNotMatch(leadsReport, /امروز وقت .* هست/);
});

test("legacy leads and its badge do not add daily appointment reminders", () => {
  assert.doesNotMatch(legacyLeads, /loadTodayAppointmentSummaryNotification\(\)/);
  assert.doesNotMatch(legacyLeads, /loadTodayVipAppointmentNotification\(\)/);
  assert.doesNotMatch(menu, /this\.notificationCounts\.Vaghtdahi = await this\.countTodayAppointmentSummary\(\)/);
  assert.doesNotMatch(menu, /this\.notificationCounts\.Vaghtdahi \+= await this\.countTodayVipAppointmentWarning\(\)/);
});
