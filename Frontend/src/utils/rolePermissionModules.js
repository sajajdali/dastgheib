const GROUP_FEATURES = {
  patients: "patients",
  appointments: "booking",
  photos: "gallery",
  followups: "followups",
  reports: "report",
  inventory: "inventory",
  beauty: "beauty",
  resources: "resources",
  tickets: "tickets",
  services: "finder",
  bills: "bills",
  attendance: "attendance",
};

const PERMISSION_FEATURES = {
  "patients.wallet": "wallet",
};

const FEATURE_ALIASES = {
  appointments: "booking",
  appointment: "booking",
  time: "booking",
  Vaghtdahi: "booking",
  shop: "online_store",
  store: "online_store",
};

function enabledFeatureSet(enabledFeatures) {
  if (!Array.isArray(enabledFeatures)) return null;

  return new Set(enabledFeatures.map(feature => FEATURE_ALIASES[feature] || feature));
}

export function filterRolePermissionGroups(groups, enabledFeatures) {
  const enabled = enabledFeatureSet(enabledFeatures);
  if (!enabled) return groups;

  return (groups || []).flatMap(group => {
    const permissions = (group.permissions || []).filter(permission => {
      const requiredFeature = PERMISSION_FEATURES[permission.name] || GROUP_FEATURES[group.key];
      return !requiredFeature || enabled.has(requiredFeature);
    });

    return permissions.length ? [{ ...group, permissions }] : [];
  });
}

export function replaceVisiblePermissions(currentPermissions, visibleNames, nextVisibleNames) {
  const visible = new Set(visibleNames);
  const hidden = (currentPermissions || []).filter(name => !visible.has(name));
  return [...new Set([...hidden, ...(nextVisibleNames || [])])];
}
