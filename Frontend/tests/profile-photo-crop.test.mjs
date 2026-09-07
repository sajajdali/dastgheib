import { readFileSync } from "node:fs";
import test from "node:test";
import assert from "node:assert/strict";

const source = readFileSync(new URL("../src/components/parvande.vue", import.meta.url), "utf8");

function zIndexFor(selector) {
  const match = source.match(new RegExp(`\\.${selector}\\s*\\{[\\s\\S]*?z-index:\\s*(\\d+)`));
  return Number(match?.[1] || 0);
}

test("profile crop is teleported and stays above the gallery overlay", () => {
  assert.match(source, /<Teleport to="body">[\s\S]*?profile-crop-overlay[\s\S]*?<\/Teleport>/);
  assert.ok(zIndexFor("profile-crop-overlay") > zIndexFor("media-overlay"));
});

test("profile upload keeps its patient id independently from the gallery modal", () => {
  assert.match(source, /patientId:\s*this\.activeMediaPatient\.id/);
  assert.match(source, /`\/api\/patients\/\$\{patientId\}\/profile-photo`/);
});
