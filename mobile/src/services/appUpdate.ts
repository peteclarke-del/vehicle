import {NativeModules, Platform} from 'react-native';
import axios from 'axios';
import AsyncStorage from '@react-native-async-storage/async-storage';

/**
 * Self-update from GitHub Releases (Android, sideloaded builds only).
 *
 * CI publishes releases tagged `mobile-vX.Y.Z` with a single `.apk` asset
 * (see .github/workflows/mobile-release.yml). The app compares the newest
 * such tag with its own versionName and offers to download + install it.
 */
export const GITHUB_REPO = 'peteclarke-del/vehicle';
export const RELEASE_TAG_PREFIX = 'mobile-v';
const DISMISSED_KEY = 'appUpdate.dismissedVersion';

export interface AvailableUpdate {
  version: string;
  tag: string;
  apkUrl: string;
  apkName: string;
  notes: string;
  publishedAt: string;
  htmlUrl: string;
}

interface GithubRelease {
  tag_name: string;
  draft: boolean;
  prerelease: boolean;
  body: string | null;
  published_at: string;
  html_url: string;
  assets: {name: string; browser_download_url: string}[];
}

const {AppUpdater} = NativeModules;

export const isUpdaterAvailable = (): boolean => Platform.OS === 'android' && !!AppUpdater;

/** Compare dotted numeric versions. Returns >0 if a > b. */
export const compareVersions = (a: string, b: string): number => {
  const pa = a.split(/[.+-]/).map(n => parseInt(n, 10) || 0);
  const pb = b.split(/[.+-]/).map(n => parseInt(n, 10) || 0);
  for (let i = 0; i < Math.max(pa.length, pb.length, 3); i++) {
    const d = (pa[i] || 0) - (pb[i] || 0);
    if (d !== 0) {
      return d;
    }
  }
  return 0;
};

/** Pick the newest stable mobile release with an APK asset. */
export const pickLatestRelease = (releases: GithubRelease[]): AvailableUpdate | null => {
  let best: AvailableUpdate | null = null;
  for (const r of releases) {
    if (r.draft || r.prerelease || !r.tag_name.startsWith(RELEASE_TAG_PREFIX)) {
      continue;
    }
    const apk = r.assets.find(a => a.name.toLowerCase().endsWith('.apk'));
    if (!apk || !apk.browser_download_url.startsWith('https://github.com/')) {
      continue;
    }
    const version = r.tag_name.slice(RELEASE_TAG_PREFIX.length);
    if (!/^\d+\.\d+\.\d+$/.test(version)) {
      continue;
    }
    if (!best || compareVersions(version, best.version) > 0) {
      best = {
        version,
        tag: r.tag_name,
        apkUrl: apk.browser_download_url,
        apkName: apk.name,
        notes: r.body || '',
        publishedAt: r.published_at,
        htmlUrl: r.html_url,
      };
    }
  }
  return best;
};

export const getInstalledVersion = async (fallback: string): Promise<string> => {
  if (!isUpdaterAvailable()) {
    return fallback;
  }
  try {
    const v = await AppUpdater.getVersion();
    return v?.versionName || fallback;
  } catch {
    return fallback;
  }
};

/** Returns the newer release, or null if up to date. Throws on network errors. */
export const checkForUpdate = async (installedVersion: string): Promise<AvailableUpdate | null> => {
  const res = await axios.get<GithubRelease[]>(
    `https://api.github.com/repos/${GITHUB_REPO}/releases?per_page=30`,
    {timeout: 15000, headers: {Accept: 'application/vnd.github+json'}},
  );
  const latest = pickLatestRelease(res.data || []);
  return latest && compareVersions(latest.version, installedVersion) > 0 ? latest : null;
};

/**
 * Download and launch the installer. If the user hasn't allowed installs from
 * this app yet, opens that settings page and returns 'needs-permission'.
 */
export const installUpdate = async (update: AvailableUpdate): Promise<'installing' | 'needs-permission'> => {
  if (!isUpdaterAvailable()) {
    throw new Error('Updates are only supported on Android');
  }
  if (!(await AppUpdater.canInstall())) {
    await AppUpdater.openInstallPermissionSettings();
    return 'needs-permission';
  }
  await AppUpdater.downloadAndInstall(update.apkUrl, update.apkName);
  return 'installing';
};

export const getDismissedVersion = async (): Promise<string | null> => {
  try {
    return await AsyncStorage.getItem(DISMISSED_KEY);
  } catch {
    return null;
  }
};

export const dismissVersion = async (version: string): Promise<void> => {
  try {
    await AsyncStorage.setItem(DISMISSED_KEY, version);
  } catch {
    // non-critical
  }
};
