import test from "node:test";
import assert from "node:assert/strict";
import {
  filterRolePermissionGroups,
  replaceVisiblePermissions,
} from "../src/utils/rolePermissionModules.js";

const groups = [
  { key: "appointments", permissions: [{ name: "appointments.view" }] },
  { key: "inventory", permissions: [{ name: "inventory.view" }] },
  {
    key: "patients",
    permissions: [
      { name: "patients.view" },
      { name: "patients.wallet" },
    ],
  },
  { key: "roles", permissions: [{ name: "roles.manage" }] },
];

test("role editor only exposes permissions for active tenant modules", () => {
  const filtered = filterRolePermissionGroups(groups, ["patients", "booking"]);

  assert.deepEqual(
    filtered.map(group => [group.key, group.permissions.map(permission => permission.name)]),
    [
      ["appointments", ["appointments.view"]],
      ["patients", ["patients.view"]],
      ["roles", ["roles.manage"]],
    ]
  );
});

test("null feature list keeps demo and backward-compatible views unrestricted", () => {
  assert.equal(filterRolePermissionGroups(groups, null), groups);
});

test("changing visible checkboxes preserves permissions belonging to hidden modules", () => {
  const result = replaceVisiblePermissions(
    ["appointments.view", "inventory.view", "roles.manage"],
    ["appointments.view", "roles.manage"],
    ["roles.manage"]
  );

  assert.deepEqual(result, ["inventory.view", "roles.manage"]);
});
