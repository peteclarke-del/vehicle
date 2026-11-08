export interface AppCompatibilityPayload {
  server?: {
    releaseVersion?: string;
    internalVersion?: string;
    compatibilityBaselineCommit?: string;
    compatibilityBaselineLabel?: string;
  };
  mobile?: {
    minimumSupportedVersion?: string;
    latestSupportedVersion?: string;
    minimumSupportedServerReleaseVersion?: string;
  };
  compatibility?: {
    apiCompatibilityVersion?: number;
    checkedAt?: string;
  };
}

export interface AppCompatibilityEvaluation {
  isCompatible: boolean;
  requiresAppUpdate: boolean;
  /**
   * The app still works with this server but is older than the version the
   * server was released with (mobile.latestSupportedVersion) - prompt the
   * user to update, without blocking.
   */
  updateRecommended?: boolean;
  /** The app version the server expects, when updateRecommended. */
  recommendedVersion?: string;
  requiresServerUpdate: boolean;
  reasons: string[];
}

const parseVersion = (value?: string): [number, number, number] => {
  const parts = (value || '0.0.0')
    .split('.')
    .slice(0, 3)
    .map(part => parseInt(part, 10) || 0);

  while (parts.length < 3) {
    parts.push(0);
  }

  return [parts[0], parts[1], parts[2]];
};

export const compareVersions = (left?: string, right?: string): number => {
  const a = parseVersion(left);
  const b = parseVersion(right);

  for (let index = 0; index < 3; index += 1) {
    if (a[index] !== b[index]) {
      return a[index] > b[index] ? 1 : -1;
    }
  }

  return 0;
};

export const evaluateAppCompatibility = (
  payload: AppCompatibilityPayload,
  appVersion: string,
  supportedApiCompatibilityVersions: number[],
): AppCompatibilityEvaluation => {
  const reasons: string[] = [];
  const minMobileVersion = payload.mobile?.minimumSupportedVersion;
  const minServerReleaseVersion = payload.mobile?.minimumSupportedServerReleaseVersion;
  const serverReleaseVersion = payload.server?.releaseVersion;
  const apiCompatibilityVersion = payload.compatibility?.apiCompatibilityVersion;

  let requiresAppUpdate = false;
  let requiresServerUpdate = false;

  if (minMobileVersion && compareVersions(appVersion, minMobileVersion) < 0) {
    requiresAppUpdate = true;
    reasons.push(`Mobile app ${appVersion} is older than the minimum supported version ${minMobileVersion}.`);
  }

  if (
    typeof apiCompatibilityVersion === 'number'
    && !supportedApiCompatibilityVersions.includes(apiCompatibilityVersion)
  ) {
    requiresAppUpdate = true;
    reasons.push(
      `Server API compatibility version ${apiCompatibilityVersion} is not supported by this mobile build.`,
    );
  }

  if (
    minServerReleaseVersion
    && serverReleaseVersion
    && compareVersions(serverReleaseVersion, minServerReleaseVersion) < 0
  ) {
    requiresServerUpdate = true;
    reasons.push(
      `Server version ${serverReleaseVersion} is older than the minimum supported server version ${minServerReleaseVersion}.`,
    );
  }

  const latestMobileVersion = payload.mobile?.latestSupportedVersion;
  const updateRecommended = !requiresAppUpdate
    && !!latestMobileVersion
    && compareVersions(appVersion, latestMobileVersion) < 0;

  return {
    isCompatible: reasons.length === 0,
    requiresAppUpdate,
    requiresServerUpdate,
    updateRecommended,
    recommendedVersion: updateRecommended ? latestMobileVersion : undefined,
    reasons,
  };
};