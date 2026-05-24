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
});