import {compareVersions, pickLatestRelease} from '../src/services/appUpdate';

jest.mock('@react-native-async-storage/async-storage', () => ({getItem: jest.fn(), setItem: jest.fn()}));
jest.mock('react-native', () => ({NativeModules: {}, Platform: {OS: 'android'}}));
jest.mock('axios', () => ({get: jest.fn()}));

const rel = (tag: string, extra: any = {}) => ({
  tag_name: tag,
  draft: false,
  prerelease: false,
  body: `notes ${tag}`,
  published_at: '2026-11-06T00:00:00Z',
  html_url: `https://github.com/x/y/releases/${tag}`,
  assets: [{name: `vehicle-manager-${tag}.apk`, browser_download_url: `https://github.com/x/y/releases/download/${tag}/a.apk`}],
  ...extra,
});

describe('compareVersions', () => {
  test.each([
    ['1.0.1', '1.0.0', 1],
    ['1.10.0', '1.9.9', 1],
    ['1.0.0', '1.0.0', 0],
    ['1.0', '1.0.0', 0],
    ['0.9.0', '1.0.0', -1],
  ])('%s vs %s', (a, b, sign) => {
    expect(Math.sign(compareVersions(a, b))).toBe(sign);
  });
});

describe('pickLatestRelease', () => {
  test('picks highest mobile release with an apk', () => {
    const r = pickLatestRelease([rel('mobile-v1.2.0'), rel('mobile-v1.10.0'), rel('v2.0.0'), rel('mobile-v1.9.0')] as any);
    expect(r?.version).toBe('1.10.0');
    expect(r?.apkUrl).toContain('mobile-v1.10.0');
  });

  test('ignores drafts, prereleases, missing apk and non-github urls', () => {
    const r = pickLatestRelease([
      rel('mobile-v3.0.0', {draft: true}),
      rel('mobile-v2.5.0', {prerelease: true}),
      rel('mobile-v2.4.0', {assets: []}),
      rel('mobile-v2.3.0', {assets: [{name: 'a.apk', browser_download_url: 'https://evil.example/a.apk'}]}),
      rel('mobile-v1.1.0'),
    ] as any);
    expect(r?.version).toBe('1.1.0');
  });

  test('null when nothing matches', () => {
    expect(pickLatestRelease([rel('v1.0.0')] as any)).toBeNull();
  });
});
