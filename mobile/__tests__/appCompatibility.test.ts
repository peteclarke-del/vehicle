import {compareVersions, evaluateAppCompatibility} from '../src/services/appCompatibility';

describe('app compatibility helpers', () => {
  test('compareVersions compares semantic version parts in ascending order', () => {
    expect(compareVersions('1.0.0', '1.0.0')).toBe(0);
    expect(compareVersions('1.0.1', '1.0.0')).toBe(1);
    expect(compareVersions('1.2.0', '1.10.0')).toBe(-1);
  });

  test('evaluateAppCompatibility detects app and server incompatibilities', () => {
    const evaluation = evaluateAppCompatibility(
      {
        server: {releaseVersion: '0.95.0'},
        mobile: {
          minimumSupportedVersion: '1.0.0',
          minimumSupportedServerReleaseVersion: '0.96.0',
        },
        compatibility: {apiCompatibilityVersion: 2},
      },
      '0.99.0',
      [1],
    );

    expect(evaluation.isCompatible).toBe(false);
    expect(evaluation.requiresAppUpdate).toBe(true);
    expect(evaluation.requiresServerUpdate).toBe(true);
    expect(evaluation.reasons).toHaveLength(3);
  });

  const serverPayload = {
    server: {releaseVersion: '0.96.0'},
    mobile: {
      minimumSupportedVersion: '1.0.0',
      latestSupportedVersion: '1.1.0',
      minimumSupportedServerReleaseVersion: '0.96.0',
    },
    compatibility: {apiCompatibilityVersion: 1},
  };

  test('recommends (but does not force) an update when the app is older than the server expects', () => {
    const evaluation = evaluateAppCompatibility(serverPayload, '1.0.0', [1]);
    expect(evaluation.isCompatible).toBe(true);
    expect(evaluation.requiresAppUpdate).toBe(false);
    expect(evaluation.updateRecommended).toBe(true);
    expect(evaluation.recommendedVersion).toBe('1.1.0');
  });

  test('no update prompt when the app is current or newer', () => {
    for (const v of ['1.1.0', '1.2.0']) {
      const evaluation = evaluateAppCompatibility(serverPayload, v, [1]);
      expect(evaluation.updateRecommended).toBe(false);
      expect(evaluation.recommendedVersion).toBeUndefined();
    }
  });

  test('below the minimum blocks instead of recommending', () => {
    const evaluation = evaluateAppCompatibility(
      {...serverPayload, mobile: {...serverPayload.mobile, minimumSupportedVersion: '1.1.0'}},
      '1.0.0',
      [1],
    );
    expect(evaluation.isCompatible).toBe(false);
    expect(evaluation.requiresAppUpdate).toBe(true);
    expect(evaluation.updateRecommended).toBe(false);
  });
});